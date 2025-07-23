<?php
/**
 * Geo Cache Class
 * 
 * Handles Redis caching for location data, distance calculations,
 * and nearby search results
 * 
 * @package ZipPicks_Geo_Service
 */

namespace ZipPicks\Geo;

class Geo_Cache {
    
    /**
     * Redis instance
     * @var \Redis|null
     */
    private $redis;
    
    /**
     * Cache key prefix
     */
    const KEY_PREFIX = 'zippicks:geo:';
    
    /**
     * TTL configurations (in seconds)
     */
    private $ttl = [
        'user_location' => 300,      // 5 minutes
        'geocode_result' => 86400,   // 24 hours
        'distance_calc' => 3600,     // 1 hour
        'nearby_results' => 300,     // 5 minutes
        'ip_location' => 3600,       // 1 hour
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_redis();
    }
    
    /**
     * Initialize Redis connection
     */
    private function init_redis() {
        // Check if Redis is available through Foundation
        if (function_exists('zippicks') && zippicks()->has('redis')) {
            $this->redis = zippicks()->get('redis');
            return;
        }
        
        // Try to connect directly if available
        if (class_exists('\Redis')) {
            try {
                $this->redis = new \Redis();
                
                // Get Redis configuration from environment or defaults
                $host = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
                $port = defined('REDIS_PORT') ? REDIS_PORT : 6379;
                $timeout = defined('REDIS_TIMEOUT') ? REDIS_TIMEOUT : 2.0;
                
                if ($this->redis->connect($host, $port, $timeout)) {
                    // Check if authentication is required
                    $password = $this->get_redis_password();
                    if ($password !== null) {
                        if (!$this->redis->auth($password)) {
                            throw new \Exception('Redis authentication failed');
                        }
                    }
                    
                    // Select database if specified
                    if (defined('REDIS_DATABASE') && is_numeric(REDIS_DATABASE)) {
                        $this->redis->select(REDIS_DATABASE);
                    }
                    
                    // Set Redis options
                    $this->redis->setOption(\Redis::OPT_PREFIX, self::KEY_PREFIX);
                    $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
                    
                    // Test connection
                    $this->redis->ping();
                } else {
                    $this->redis = null;
                }
            } catch (\Exception $e) {
                $this->redis = null;
                
                if (function_exists('zippicks') && zippicks()->has('logger')) {
                    $logger = zippicks()->get('logger');
                    $logger->error('Redis connection failed', [
                        'error' => $e->getMessage(),
                        'code' => ZIPPICKS_GEO_ERRORS['GEO005'],
                        'host' => $host ?? '127.0.0.1',
                        'port' => $port ?? 6379
                    ]);
                }
            }
        }
        
        // Fall back to WordPress transients if Redis not available
        if (!$this->redis) {
            add_filter('zippicks_geo_cache_fallback', '__return_true');
        }
    }
    
    /**
     * Get user location from cache
     * 
     * @param string $identifier Session or user identifier
     * @return array|null
     */
    public function get_user_location($identifier) {
        $key = $this->make_key('location', $identifier);
        return $this->get($key);
    }
    
    /**
     * Set user location in cache
     * 
     * @param string $identifier
     * @param array $location
     * @param int|null $ttl
     * @return bool
     */
    public function set_user_location($identifier, $location, $ttl = null) {
        $key = $this->make_key('location', $identifier);
        $ttl = $ttl ?? $this->ttl['user_location'];
        
        return $this->set($key, $location, $ttl);
    }
    
    /**
     * Get geocode result from cache
     * 
     * @param string $input Input string (ZIP, address, etc.)
     * @param string $type Input type
     * @return array|null
     */
    public function get_geocode_result($input, $type) {
        $key = $this->make_key('geocode', md5($type . ':' . $input));
        return $this->get($key);
    }
    
    /**
     * Set geocode result in cache
     * 
     * @param string $input
     * @param string $type
     * @param array $result
     * @return bool
     */
    public function set_geocode_result($input, $type, $result) {
        $key = $this->make_key('geocode', md5($type . ':' . $input));
        return $this->set($key, $result, $this->ttl['geocode_result']);
    }
    
    /**
     * Get distance calculation from cache
     * 
     * @param string $cache_key
     * @return float|null
     */
    public function get_distance_calculation($cache_key) {
        $key = $this->make_key('distance', $cache_key);
        return $this->get($key);
    }
    
    /**
     * Set distance calculation in cache
     * 
     * @param string $cache_key
     * @param float $distance
     * @return bool
     */
    public function set_distance_calculation($cache_key, $distance) {
        $key = $this->make_key('distance', $cache_key);
        return $this->set($key, $distance, $this->ttl['distance_calc']);
    }
    
    /**
     * Get nearby results from cache
     * 
     * @param float $lat
     * @param float $lng
     * @param float $radius
     * @param array $filters
     * @return array|null
     */
    public function get_nearby_results($lat, $lng, $radius, $filters = []) {
        // Sort filters to ensure consistent cache keys regardless of array order
        ksort($filters);
        $cache_key = md5(sprintf('%f:%f:%f:%s', $lat, $lng, $radius, json_encode($filters)));
        $key = $this->make_key('nearby', $cache_key);
        
        return $this->get($key);
    }
    
    /**
     * Set nearby results in cache
     * 
     * @param float $lat
     * @param float $lng
     * @param float $radius
     * @param array $results
     * @param array $filters
     * @return bool
     */
    public function set_nearby_results($lat, $lng, $radius, $results, $filters = []) {
        // Sort filters to ensure consistent cache keys regardless of array order
        ksort($filters);
        $cache_key = md5(sprintf('%f:%f:%f:%s', $lat, $lng, $radius, json_encode($filters)));
        $key = $this->make_key('nearby', $cache_key);
        
        return $this->set($key, $results, $this->ttl['nearby_results']);
    }
    
    /**
     * Clear user location cache
     * 
     * @param string $identifier
     * @return bool
     */
    public function clear_user_location($identifier) {
        $key = $this->make_key('location', $identifier);
        return $this->delete($key);
    }
    
    /**
     * Clear all nearby results for a location
     * 
     * @param float $lat
     * @param float $lng
     * @return int Number of keys deleted
     */
    public function clear_nearby_results($lat, $lng) {
        if (!$this->redis) {
            return 0;
        }
        
        // Find all keys matching the pattern
        $pattern = $this->make_key('nearby', sprintf('%f:%f:*', $lat, $lng));
        $keys = $this->redis->keys($pattern);
        
        if (empty($keys)) {
            return 0;
        }
        
        return $this->redis->del($keys);
    }
    
    /**
     * Clear all geo-related caches
     * 
     * @return array Results of the cache clearing operation
     */
    public function clear_all_caches() {
        $results = [
            'success' => true,
            'deleted_count' => 0,
            'method' => $this->redis ? 'redis' : 'transients'
        ];
        
        if ($this->redis) {
            try {
                // Use SCAN to non-blockingly iterate through keys
                $iterator = null;
                $pattern = '*';
                $batch_size = 100;
                $keys_to_delete = [];
                
                do {
                    // SCAN returns [cursor, keys]
                    $scan_result = $this->redis->scan($iterator, [
                        'MATCH' => $pattern,
                        'COUNT' => $batch_size
                    ]);
                    
                    if ($scan_result === false) {
                        break;
                    }
                    
                    // Collect keys for deletion
                    if (!empty($scan_result)) {
                        $keys_to_delete = array_merge($keys_to_delete, $scan_result);
                        
                        // Delete in batches to avoid memory issues
                        if (count($keys_to_delete) >= $batch_size) {
                            $results['deleted_count'] += $this->redis->del($keys_to_delete);
                            $keys_to_delete = [];
                        }
                    }
                } while ($iterator > 0);
                
                // Delete any remaining keys
                if (!empty($keys_to_delete)) {
                    $results['deleted_count'] += $this->redis->del($keys_to_delete);
                }
                
                // Log the operation
                if (function_exists('zippicks') && zippicks()->has('logger')) {
                    $logger = zippicks()->get('logger');
                    $logger->info('Geo cache cleared via Redis', [
                        'keys_deleted' => $results['deleted_count']
                    ]);
                }
            } catch (\Exception $e) {
                $results['success'] = false;
                $results['error'] = $e->getMessage();
                
                // Log the error
                if (function_exists('zippicks') && zippicks()->has('logger')) {
                    $logger = zippicks()->get('logger');
                    $logger->error('Failed to clear geo cache via Redis', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } else {
            // Fallback to clearing WordPress transients
            global $wpdb;
            
            // Use proper prepared queries
            $prefix = $wpdb->esc_like(self::KEY_PREFIX) . '%';
            
            // Delete transient values
            $deleted_values = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_' . $prefix
                )
            );
            
            // Delete transient timeouts
            $deleted_timeouts = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_timeout_' . $prefix
                )
            );
            
            $results['deleted_count'] = $deleted_values + $deleted_timeouts;
            
            // Log the operation
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->info('Geo cache cleared via transients', [
                    'transients_deleted' => $deleted_values,
                    'timeouts_deleted' => $deleted_timeouts
                ]);
            }
        }
        
        // Fire action hook for cache clearing
        do_action('zippicks_geo_cache_cleared', $results);
        
        return $results;
    }
    
    /**
     * Get from cache (with fallback to transients)
     * 
     * @param string $key
     * @return mixed
     */
    private function get($key) {
        if ($this->redis) {
            try {
                return $this->redis->get($key);
            } catch (\Exception $e) {
                // Fall through to transient
            }
        }
        
        // Fallback to WordPress transients
        return get_transient($key);
    }
    
    /**
     * Set in cache (with fallback to transients)
     * 
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    private function set($key, $value, $ttl) {
        if ($this->redis) {
            try {
                return $this->redis->setex($key, $ttl, $value);
            } catch (\Exception $e) {
                // Fall through to transient
            }
        }
        
        // Fallback to WordPress transients
        return set_transient($key, $value, $ttl);
    }
    
    /**
     * Delete from cache
     * 
     * @param string $key
     * @return bool
     */
    private function delete($key) {
        if ($this->redis) {
            try {
                return (bool) $this->redis->del($key);
            } catch (\Exception $e) {
                // Fall through to transient
            }
        }
        
        // Fallback to WordPress transients
        return delete_transient($key);
    }
    
    /**
     * Make cache key
     * 
     * @param string $type
     * @param string $identifier
     * @return string
     */
    private function make_key($type, $identifier) {
        // Remove prefix if using Redis (already set as option)
        if ($this->redis) {
            return "{$type}:{$identifier}";
        }
        
        // Include prefix for transients
        return self::KEY_PREFIX . "{$type}:{$identifier}";
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function get_stats() {
        $stats = [
            'driver' => $this->redis ? 'redis' : 'transients',
            'connected' => (bool) $this->redis,
            'keys' => 0,
            'memory' => 0,
        ];
        
        if ($this->redis) {
            try {
                $info = $this->redis->info();
                $stats['keys'] = $info['db0']['keys'] ?? 0;
                $stats['memory'] = $info['used_memory_human'] ?? '0B';
            } catch (\Exception $e) {
                // Keep defaults
            }
        }
        
        return $stats;
    }
    
    /**
     * Warm cache for popular locations
     * 
     * @param array $locations Array of [lat, lng] pairs
     * @param float $radius Default radius
     * @param Distance_Calculator $calculator Optional calculator instance
     */
    public function warm_cache($locations, $radius = 5, Distance_Calculator $calculator = null) {
        if (!$this->redis || empty($locations)) {
            return;
        }
        
        // If no calculator provided, work directly with cache
        if ($calculator === null) {
            // Pre-populate cache entries directly without circular dependency
            foreach ($locations as $location) {
                if (isset($location['lat']) && isset($location['lng'])) {
                    // Create cache key for this location/radius combination
                    $lat = floatval($location['lat']);
                    $lng = floatval($location['lng']);
                    
                    // Pre-warm with empty result set to indicate "checked but no results"
                    // This prevents unnecessary API calls for areas with no data
                    $this->set_nearby_results($lat, $lng, $radius, [
                        'results' => [],
                        'total' => 0,
                        'cached_at' => time(),
                        'pre_warmed' => true
                    ]);
                    
                    if (function_exists('zippicks') && zippicks()->has('logger')) {
                        $logger = zippicks()->get('logger');
                        $logger->info('Cache warmed for location', [
                            'lat' => $lat,
                            'lng' => $lng,
                            'radius' => $radius
                        ]);
                    }
                }
            }
        } else {
            // Use provided calculator instance (already has cache set externally)
            foreach ($locations as $location) {
                if (isset($location['lat']) && isset($location['lng'])) {
                    $calculator->find_within_radius($location['lat'], $location['lng'], $radius);
                }
            }
        }
    }
    
    /**
     * Get Redis password from various sources
     * 
     * @return string|null Redis password or null if not set
     */
    private function get_redis_password() {
        // Check multiple sources for Redis password
        // Priority order: constant, environment variable, database option
        
        // 1. Check WordPress constant (wp-config.php)
        if (defined('REDIS_PASSWORD') && !empty(REDIS_PASSWORD)) {
            return REDIS_PASSWORD;
        }
        
        // 2. Check environment variable
        $env_password = getenv('REDIS_PASSWORD');
        if ($env_password !== false && !empty($env_password)) {
            return $env_password;
        }
        
        // 3. Check database option (less secure, but allows admin configuration)
        $db_password = get_option('zippicks_redis_password', '');
        if (!empty($db_password)) {
            return $db_password;
        }
        
        // 4. Check if Redis requires authentication by looking for auth constant
        if (defined('REDIS_AUTH') && !empty(REDIS_AUTH)) {
            return REDIS_AUTH;
        }
        
        // No password configured
        return null;
    }
}