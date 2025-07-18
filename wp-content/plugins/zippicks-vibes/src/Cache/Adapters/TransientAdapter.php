<?php
/**
 * Transient Cache Adapter
 * 
 * Uses WordPress transients for caching with safe encoding, size limits,
 * configurable TTL, and comprehensive error logging.
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Cache\Adapters;

use ZipPicksVibes\Cache\CacheInterface;

/**
 * Class TransientAdapter
 * 
 * Default fallback cache implementation using WordPress transients
 * with enhanced safety features and logging.
 */
class TransientAdapter implements CacheInterface {
    
    /**
     * Configuration
     * 
     * @var array
     */
    private array $config;
    
    /**
     * Key prefix
     * 
     * @var string
     */
    private string $prefix;
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger;
    
    /**
     * Default TTL in seconds
     * 
     * @var int
     */
    private int $defaultTtl;
    
    /**
     * Maximum allowed size for a single transient value (in bytes)
     * WordPress has a limit of about 1MB for option values
     * 
     * @var int
     */
    private int $maxSize = 900000; // ~900KB to be safe
    
    /**
     * Constructor
     * 
     * @param array $config Configuration options
     */
    public function __construct(array $config = []) {
        $this->config = $config;
        $this->prefix = $config['prefix'] ?? 'zippicks_vibes_';
        $this->logger = $config['logger'] ?? null;
        
        // Support configurable TTL via settings or options
        $this->defaultTtl = $config['default_ttl'] ?? get_option('zippicks_vibes_cache_ttl', 300);
    }
    
    /**
     * Get a value from cache
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null) {
        $transientKey = $this->getTransientKey($key);
        
        try {
            $value = get_transient($transientKey);
            
            if ($value === false) {
                return $default;
            }
            
            // Handle complex data that was serialized
            $decoded = $this->decodeValue($value);
            
            return $decoded !== null ? $decoded : $default;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Transient get failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            return $default;
        }
    }
    
    /**
     * Set a value in cache
     * 
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    public function set(string $key, $value, int $ttl = 0): bool {
        $transientKey = $this->getTransientKey($key);
        
        // Use default TTL if none specified
        if ($ttl === 0) {
            $ttl = $this->defaultTtl;
        }
        
        try {
            // Encode complex data safely
            $encoded = $this->encodeValue($value);
            
            // Check size limit
            $size = strlen(serialize($encoded));
            if ($size > $this->maxSize) {
                if ($this->logger) {
                    $this->logger->warning('Transient value exceeds size limit', [
                        'key' => $key,
                        'size' => $size,
                        'max_size' => $this->maxSize
                    ]);
                }
                return false;
            }
            
            $result = set_transient($transientKey, $encoded, $ttl);
            
            if (!$result) {
                if ($this->logger) {
                    $this->logger->error('set_transient failed', [
                        'key' => $key,
                        'ttl' => $ttl
                    ]);
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Transient set failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Delete a value from cache
     * 
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool {
        $transientKey = $this->getTransientKey($key);
        
        try {
            return delete_transient($transientKey);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Transient delete failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Clear all cache
     * 
     * @return bool
     */
    public function flush(): bool {
        global $wpdb;
        
        try {
            // Delete all transients with our prefix
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} 
                    WHERE option_name LIKE %s 
                    OR option_name LIKE %s",
                    $wpdb->esc_like('_transient_' . $this->prefix) . '%',
                    $wpdb->esc_like('_transient_timeout_' . $this->prefix) . '%'
                )
            );
            
            // If using external object cache
            if (wp_using_ext_object_cache()) {
                wp_cache_flush();
            }
            
            if ($this->logger) {
                $this->logger->info('Transient cache flushed', [
                    'prefix' => $this->prefix,
                    'rows_deleted' => $result
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Transient flush failed', [
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Check if key exists in cache
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool {
        $transientKey = $this->getTransientKey($key);
        
        try {
            // get_transient returns false if not exists
            return get_transient($transientKey) !== false;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Transient has check failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Clear cache by group
     * 
     * @param string $group Group identifier
     * @return void
     */
    public function clearGroup(string $group): void {
        global $wpdb;
        
        try {
            // Delete all transients with our prefix and group
            $pattern = $this->prefix . $group . '_';
            
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} 
                    WHERE option_name LIKE %s 
                    OR option_name LIKE %s",
                    $wpdb->esc_like('_transient_' . $pattern) . '%',
                    $wpdb->esc_like('_transient_timeout_' . $pattern) . '%'
                )
            );
            
            if ($this->logger) {
                $this->logger->info('Transient cache group cleared', [
                    'group' => $group,
                    'rows_deleted' => $result
                ]);
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Transient clearGroup failed', [
                    'group' => $group,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Get multiple values from cache
     * 
     * @param array $keys
     * @param mixed $default
     * @return array
     */
    public function getMultiple(array $keys, $default = null): array {
        $result = [];
        
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        
        return $result;
    }
    
    /**
     * Set multiple values in cache
     * 
     * @param array $values
     * @param int $ttl
     * @return bool
     */
    public function setMultiple(array $values, int $ttl = 300): bool {
        $success = true;
        
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Delete multiple values from cache
     * 
     * @param array $keys
     * @return bool
     */
    public function deleteMultiple(array $keys): bool {
        $success = true;
        
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Increment a numeric value
     * 
     * @param string $key
     * @param int $step
     * @return int|false
     */
    public function increment(string $key, int $step = 1) {
        $value = $this->get($key, 0);
        
        if (!is_numeric($value)) {
            if ($this->logger) {
                $this->logger->warning('Cannot increment non-numeric value', [
                    'key' => $key,
                    'value_type' => gettype($value)
                ]);
            }
            return false;
        }
        
        $newValue = (int) $value + $step;
        
        if ($this->set($key, $newValue, $this->defaultTtl)) {
            return $newValue;
        }
        
        return false;
    }
    
    /**
     * Decrement a numeric value
     * 
     * @param string $key
     * @param int $step
     * @return int|false
     */
    public function decrement(string $key, int $step = 1) {
        $value = $this->get($key, 0);
        
        if (!is_numeric($value)) {
            if ($this->logger) {
                $this->logger->warning('Cannot decrement non-numeric value', [
                    'key' => $key,
                    'value_type' => gettype($value)
                ]);
            }
            return false;
        }
        
        $newValue = (int) $value - $step;
        
        if ($this->set($key, $newValue, $this->defaultTtl)) {
            return $newValue;
        }
        
        return false;
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function stats(): array {
        global $wpdb;
        
        try {
            // Count transients
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->options} 
                    WHERE option_name LIKE %s",
                    $wpdb->esc_like('_transient_' . $this->prefix) . '%'
                )
            );
            
            // Get total size (approximate)
            $size = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
                    WHERE option_name LIKE %s",
                    $wpdb->esc_like('_transient_' . $this->prefix) . '%'
                )
            );
            
            return [
                'type' => 'transient',
                'entries' => (int) $count,
                'size' => (int) $size,
                'size_human' => size_format($size),
                'prefix' => $this->prefix,
                'default_ttl' => $this->defaultTtl,
                'max_size_per_entry' => $this->maxSize,
                'external_object_cache' => wp_using_ext_object_cache()
            ];
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to get transient stats', [
                    'error' => $e->getMessage()
                ]);
            }
            
            return [
                'type' => 'transient',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Remember a value (get or compute)
     * 
     * @param string $key
     * @param callable $callback
     * @param int $ttl
     * @return mixed
     */
    public function remember(string $key, callable $callback, int $ttl = 300) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        try {
            $value = $callback();
            $this->set($key, $value, $ttl);
            return $value;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Transient remember callback failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }
    
    /**
     * Remember a value forever
     * 
     * @param string $key
     * @param callable $callback
     * @return mixed
     */
    public function rememberForever(string $key, callable $callback) {
        // WordPress transients don't truly support "forever" but we can use a very long time
        return $this->remember($key, $callback, YEAR_IN_SECONDS * 10); // 10 years
    }
    
    /**
     * Get the transient key with prefix
     * 
     * @param string $key
     * @return string
     */
    private function getTransientKey(string $key): string {
        // WordPress transients are already prefixed with _transient_
        // but we add our own prefix for namespacing
        return $this->prefix . $key;
    }
    
    /**
     * Encode value for safe storage
     * 
     * @param mixed $value
     * @return string|mixed
     */
    private function encodeValue($value) {
        // For scalar values, return as-is
        if (is_scalar($value)) {
            return $value;
        }
        
        // For complex data, use maybe_serialize
        return maybe_serialize($value);
    }
    
    /**
     * Decode value from storage
     * 
     * @param mixed $value
     * @return mixed
     */
    private function decodeValue($value) {
        // Try to unserialize if it looks serialized
        return maybe_unserialize($value);
    }
}