<?php
/**
 * Cache Service with Redis and WordPress transient fallback
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Services;

class CacheService {
    
    /**
     * Configuration service
     *
     * @var ConfigService
     */
    private $config;
    
    /**
     * Cache prefix
     *
     * @var string
     */
    private $prefix = 'zippicks_bi_';
    
    /**
     * Redis client
     *
     * @var \Redis|null
     */
    private $redis = null;
    
    /**
     * Whether Redis is available
     *
     * @var bool
     */
    private $redis_available = false;
    
    /**
     * Default TTL in seconds
     *
     * @var int
     */
    private $default_ttl;
    
    /**
     * Constructor
     *
     * @param ConfigService $config
     */
    public function __construct(ConfigService $config) {
        $this->config = $config;
        $this->default_ttl = $config->get('cache_ttl', 3600);
        
        // Try to initialize Redis
        $this->init_redis();
    }
    
    /**
     * Initialize Redis connection
     */
    private function init_redis() {
        if (!class_exists('Redis')) {
            return;
        }
        
        try {
            $this->redis = new \Redis();
            
            $host = $this->config->get('redis_host', '127.0.0.1');
            $port = $this->config->get('redis_port', 6379);
            $timeout = $this->config->get('redis_timeout', 2.0);
            
            if ($this->redis->connect($host, $port, $timeout)) {
                // Set Redis options
                $this->redis->setOption(\Redis::OPT_PREFIX, $this->prefix);
                $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
                
                // Test connection
                $this->redis->ping();
                $this->redis_available = true;
                
                // Authentication if required
                $password = $this->config->get('redis_password');
                if ($password) {
                    $this->redis->auth($password);
                }
                
                // Select database
                $database = $this->config->get('redis_database', 0);
                if ($database) {
                    $this->redis->select($database);
                }
            }
        } catch (\Exception $e) {
            $this->redis_available = false;
            $this->redis = null;
            
            if ($this->config->get('debug_mode')) {
                error_log('[ZipPicks BI] Redis connection failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Get value from cache
     *
     * @param string $key Cache key
     * @return mixed|false Cached value or false if not found
     */
    public function get(string $key) {
        // Try Redis first
        if ($this->redis_available) {
            try {
                $value = $this->redis->get($key);
                if ($value !== false) {
                    return $value;
                }
            } catch (\Exception $e) {
                $this->handle_redis_error($e);
            }
        }
        
        // Fallback to WordPress transients
        return $this->get_transient($key);
    }
    
    /**
     * Set value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function set(string $key, $value, ?int $ttl = null): bool {
        $ttl = $ttl ?? $this->default_ttl;
        
        // Try Redis first
        if ($this->redis_available) {
            try {
                $result = $this->redis->setex($key, $ttl, $value);
                if ($result) {
                    // Also set in transients for fallback
                    $this->set_transient($key, $value, $ttl);
                    return true;
                }
            } catch (\Exception $e) {
                $this->handle_redis_error($e);
            }
        }
        
        // Fallback to WordPress transients
        return $this->set_transient($key, $value, $ttl);
    }
    
    /**
     * Delete value from cache
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool {
        $success = false;
        
        // Try Redis first
        if ($this->redis_available) {
            try {
                $this->redis->del($key);
                $success = true;
            } catch (\Exception $e) {
                $this->handle_redis_error($e);
            }
        }
        
        // Always delete from transients
        delete_transient($this->prefix . $key);
        
        return $success;
    }
    
    /**
     * Clear all cache
     *
     * @return bool Success status
     */
    public function flush(): bool {
        global $wpdb;
        
        // Clear Redis
        if ($this->redis_available) {
            try {
                // Use scan to find all keys with our prefix
                $iterator = null;
                while ($keys = $this->redis->scan($iterator, '*', 1000)) {
                    if (!empty($keys)) {
                        $this->redis->del($keys);
                    }
                }
            } catch (\Exception $e) {
                $this->handle_redis_error($e);
            }
        }
        
        // Clear WordPress transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $this->prefix . '%',
                '_transient_timeout_' . $this->prefix . '%'
            )
        );
        
        // Clear database cache
        $wpdb->query("DELETE FROM {$wpdb->prefix}zippicks_business_cache");
        
        return true;
    }
    
    /**
     * Get multiple values from cache
     *
     * @param array $keys Array of cache keys
     * @return array Associative array of key => value
     */
    public function get_multi(array $keys): array {
        $values = [];
        
        // Try Redis first
        if ($this->redis_available) {
            try {
                $redis_values = $this->redis->mget($keys);
                foreach ($keys as $index => $key) {
                    if (isset($redis_values[$index]) && $redis_values[$index] !== false) {
                        $values[$key] = $redis_values[$index];
                    }
                }
            } catch (\Exception $e) {
                $this->handle_redis_error($e);
            }
        }
        
        // Get missing values from transients
        foreach ($keys as $key) {
            if (!isset($values[$key])) {
                $value = $this->get_transient($key);
                if ($value !== false) {
                    $values[$key] = $value;
                }
            }
        }
        
        return $values;
    }
    
    /**
     * Set multiple values in cache
     *
     * @param array $items Associative array of key => value
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function set_multi(array $items, ?int $ttl = null): bool {
        $ttl = $ttl ?? $this->default_ttl;
        $success = true;
        
        foreach ($items as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Store in database cache
     *
     * @param string $zpid Business ZPID
     * @param string $city City name
     * @param array $data Business data
     * @return bool Success status
     */
    public function store_business(string $zpid, string $city, array $data): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_business_cache';
        $expires_at = date('Y-m-d H:i:s', time() + $this->default_ttl);
        
        $result = $wpdb->replace(
            $table,
            [
                'zpid' => $zpid,
                'city' => $city,
                'data' => json_encode($data),
                'expires_at' => $expires_at
            ],
            ['%s', '%s', '%s', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Get businesses from database cache
     *
     * @param string $city City name
     * @return array Array of business data
     */
    public function get_cached_businesses(string $city): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_business_cache';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT data FROM {$table} 
                WHERE city = %s AND expires_at > NOW()
                ORDER BY updated_at DESC",
                $city
            ),
            ARRAY_A
        );
        
        if (!$results) {
            return [];
        }
        
        $businesses = [];
        foreach ($results as $row) {
            $data = json_decode($row['data'], true);
            if ($data) {
                $businesses[] = $data;
            }
        }
        
        return $businesses;
    }
    
    /**
     * Cleanup expired cache entries
     */
    public function cleanup_expired() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_business_cache';
        
        $wpdb->query(
            "DELETE FROM {$table} WHERE expires_at < NOW()"
        );
    }
    
    /**
     * Get cache statistics
     *
     * @return array
     */
    public function get_stats(): array {
        global $wpdb;
        
        $stats = [
            'backend' => $this->redis_available ? 'redis' : 'transients',
            'redis_available' => $this->redis_available,
            'default_ttl' => $this->default_ttl
        ];
        
        // Database cache stats
        $table = $wpdb->prefix . 'zippicks_business_cache';
        $stats['db_cache_count'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $stats['db_cache_expired'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE expires_at < NOW()");
        
        // Redis stats
        if ($this->redis_available) {
            try {
                $info = $this->redis->info();
                $stats['redis_memory'] = $info['used_memory_human'] ?? 'unknown';
                $stats['redis_keys'] = $info['db0']['keys'] ?? 0;
            } catch (\Exception $e) {
                // Ignore
            }
        }
        
        return $stats;
    }
    
    /**
     * Health check
     *
     * @return array
     */
    public function health_check(): array {
        $health = [
            'status' => 'healthy',
            'backend' => $this->redis_available ? 'redis' : 'transients'
        ];
        
        // Test cache operations
        try {
            $test_key = 'health_check_' . time();
            $test_value = ['test' => true];
            
            $this->set($test_key, $test_value, 60);
            $retrieved = $this->get($test_key);
            $this->delete($test_key);
            
            if ($retrieved !== $test_value) {
                $health['status'] = 'degraded';
                $health['error'] = 'Cache read/write test failed';
            }
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['error'] = $e->getMessage();
        }
        
        return $health;
    }
    
    /**
     * Get value from WordPress transient
     *
     * @param string $key Cache key
     * @return mixed|false
     */
    private function get_transient(string $key) {
        return get_transient($this->prefix . $key);
    }
    
    /**
     * Set value in WordPress transient
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live
     * @return bool
     */
    private function set_transient(string $key, $value, int $ttl): bool {
        return set_transient($this->prefix . $key, $value, $ttl);
    }
    
    /**
     * Handle Redis error
     *
     * @param \Exception $e
     */
    private function handle_redis_error(\Exception $e) {
        $this->redis_available = false;
        
        if ($this->config->get('debug_mode')) {
            error_log('[ZipPicks BI] Redis error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all cache keys
     *
     * @param string $pattern Pattern to match (default: '*')
     * @return array Array of cache keys
     */
    public function get_all_keys(string $pattern = '*'): array {
        $keys = [];
        
        if ($this->redis_available) {
            try {
                // Redis SCAN to get keys matching pattern
                $iterator = null;
                $search_pattern = str_replace($this->prefix, '', $pattern);
                while ($scanned_keys = $this->redis->scan($iterator, $this->prefix . $search_pattern, 100)) {
                    foreach ($scanned_keys as $key) {
                        $keys[] = str_replace($this->prefix, '', $key);
                    }
                }
            } catch (\Exception $e) {
                $this->handle_redis_error($e);
            }
        } else {
            // WordPress transients - query options table
            global $wpdb;
            $like_pattern = str_replace('*', '%', $pattern);
            $transient_keys = $wpdb->get_col($wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_' . $this->prefix . $like_pattern
            ));
            
            foreach ($transient_keys as $key) {
                $clean_key = str_replace('_transient_' . $this->prefix, '', $key);
                $keys[] = $clean_key;
            }
        }
        
        return array_unique($keys);
    }
    
    /**
     * Get cache entry details
     *
     * @param string $key Cache key
     * @return array|null Entry details or null if not found
     */
    public function get_entry_details(string $key): ?array {
        $value = $this->get($key);
        if ($value === false) {
            return null;
        }
        
        $details = [
            'key' => $key,
            'value' => $value,
            'type' => gettype($value),
            'size' => $this->get_entry_size($key),
            'ttl' => $this->get_ttl($key),
            'backend' => $this->redis_available ? 'redis' : 'transient'
        ];
        
        // Extract key type from pattern
        if (preg_match('/^(\w+)_/', $key, $matches)) {
            $details['key_type'] = $matches[1];
        } else {
            $details['key_type'] = 'unknown';
        }
        
        return $details;
    }
    
    /**
     * Get entry size in bytes
     *
     * @param string $key Cache key
     * @return int Size in bytes
     */
    public function get_entry_size(string $key): int {
        $value = $this->get($key);
        if ($value === false) {
            return 0;
        }
        
        // Serialize to get actual storage size
        return strlen(serialize($value));
    }
    
    /**
     * Get remaining TTL for a key
     *
     * @param string $key Cache key
     * @return int TTL in seconds, -1 if no expiry, 0 if expired/not found
     */
    private function get_ttl(string $key): int {
        if ($this->redis_available) {
            try {
                $ttl = $this->redis->ttl($this->prefix . $key);
                return $ttl >= 0 ? $ttl : -1;
            } catch (\Exception $e) {
                $this->handle_redis_error($e);
            }
        } else {
            // For transients, check expiration in options table
            global $wpdb;
            $timeout = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} 
                 WHERE option_name = %s",
                '_transient_timeout_' . $this->prefix . $key
            ));
            
            if ($timeout) {
                $remaining = $timeout - time();
                return $remaining > 0 ? $remaining : 0;
            }
        }
        
        return -1;
    }
    
    /**
     * Clear cache entries by pattern
     *
     * @param string $pattern Pattern to match
     * @return int Number of entries cleared
     */
    public function clear_by_pattern(string $pattern): int {
        $cleared = 0;
        $keys = $this->get_all_keys($pattern);
        
        foreach ($keys as $key) {
            if ($this->delete($key)) {
                $cleared++;
            }
        }
        
        // Log the action if logger is available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('Cache cleared by pattern', [
                'pattern' => $pattern,
                'cleared' => $cleared
            ]);
        }
        
        return $cleared;
    }
    
    /**
     * Delete cache entries by pattern (alias for clear_by_pattern)
     *
     * @param string $pattern Pattern to match
     * @return int Number of entries deleted
     */
    public function delete_by_pattern(string $pattern): int {
        return $this->clear_by_pattern($pattern);
    }
}