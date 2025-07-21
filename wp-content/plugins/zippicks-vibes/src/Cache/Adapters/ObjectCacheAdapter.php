<?php
/**
 * WordPress Object Cache Adapter
 * 
 * Uses WordPress object cache API (for external object cache plugins)
 * with improved error handling, namespacing, and fallback support.
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Cache\Adapters;

use ZipPicksVibes\Cache\CacheInterface;

/**
 * Class ObjectCacheAdapter
 * 
 * Provides caching through WordPress object cache API with automatic
 * fallback to transients if object cache operations fail.
 */
class ObjectCacheAdapter implements CacheInterface {
    
    /**
     * Cache group
     * 
     * @var string
     */
    private string $group = 'zippicks_vibes';
    
    /**
     * Key prefix for namespacing
     * 
     * @var string
     */
    private string $prefix = 'zippicks_vibes:';
    
    /**
     * Configuration
     * 
     * @var array
     */
    private array $config;
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger;
    
    /**
     * Fallback adapter (TransientAdapter)
     * 
     * @var CacheInterface|null
     */
    private ?CacheInterface $fallback = null;
    
    /**
     * Track if we should use fallback
     * 
     * @var bool
     */
    private bool $useFallback = false;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration options
     */
    public function __construct(array $config = []) {
        $this->config = $config;
        $this->group = $config['group'] ?? $this->group;
        $this->prefix = $config['prefix'] ?? $this->prefix;
        $this->logger = $config['logger'] ?? null;
        
        // Check if object cache is actually available
        if (!function_exists('wp_cache_get') || !wp_using_ext_object_cache()) {
            $this->initializeFallback();
        }
    }
    
    /**
     * Initialize fallback adapter
     */
    private function initializeFallback(): void {
        if (!$this->fallback) {
            $this->fallback = new TransientAdapter($this->config);
            $this->useFallback = true;
            
            if ($this->logger) {
                $this->logger->warning('Object cache not available, using transient fallback');
            }
        }
    }
    
    /**
     * Get namespaced key
     * 
     * @param string $key
     * @return string
     */
    private function getNamespacedKey(string $key): string {
        return $this->prefix . $key;
    }
    
    /**
     * Get a value from cache
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null) {
        if ($this->useFallback && $this->fallback) {
            return $this->fallback->get($key, $default);
        }
        
        $namespacedKey = $this->getNamespacedKey($key);
        
        try {
            $found = false;
            $value = wp_cache_get($namespacedKey, $this->group, false, $found);
            
            if (!$found) {
                return $default;
            }
            
            return $value;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Object cache get failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Try fallback
            $this->initializeFallback();
            if ($this->fallback) {
                return $this->fallback->get($key, $default);
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
        if ($this->useFallback && $this->fallback) {
            return $this->fallback->set($key, $value, $ttl);
        }
        
        $namespacedKey = $this->getNamespacedKey($key);
        
        try {
            // Check if value is serializable (object cache may fail on resources, etc.)
            if (is_resource($value)) {
                if ($this->logger) {
                    $this->logger->warning('Cannot cache resource type', ['key' => $key]);
                }
                return false;
            }
            
            // Try to serialize to check if it's cacheable
            $testSerialize = @serialize($value);
            if ($testSerialize === false && $value !== false) {
                if ($this->logger) {
                    $this->logger->warning('Value is not serializable', [
                        'key' => $key,
                        'type' => gettype($value)
                    ]);
                }
                return false;
            }
            
            $result = wp_cache_set($namespacedKey, $value, $this->group, $ttl);
            
            if (!$result) {
                if ($this->logger) {
                    $this->logger->warning('wp_cache_set failed', [
                        'key' => $key,
                        'group' => $this->group
                    ]);
                }
                
                // Try fallback
                $this->initializeFallback();
                if ($this->fallback) {
                    return $this->fallback->set($key, $value, $ttl);
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Object cache set failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Try fallback
            $this->initializeFallback();
            if ($this->fallback) {
                return $this->fallback->set($key, $value, $ttl);
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
        if ($this->useFallback && $this->fallback) {
            return $this->fallback->delete($key);
        }
        
        $namespacedKey = $this->getNamespacedKey($key);
        
        try {
            return wp_cache_delete($namespacedKey, $this->group);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Object cache delete failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Try fallback
            if ($this->fallback) {
                return $this->fallback->delete($key);
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
        if ($this->useFallback && $this->fallback) {
            return $this->fallback->flush();
        }
        
        try {
            // Flush the entire group
            if (function_exists('wp_cache_flush_group')) {
                return wp_cache_flush_group($this->group);
            } else {
                // Fallback to global flush (less ideal)
                return wp_cache_flush();
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Object cache flush failed', [
                    'error' => $e->getMessage()
                ]);
            }
            
            // Try fallback
            if ($this->fallback) {
                return $this->fallback->flush();
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
        if ($this->useFallback && $this->fallback) {
            return $this->fallback->has($key);
        }
        
        $namespacedKey = $this->getNamespacedKey($key);
        
        try {
            $found = false;
            wp_cache_get($namespacedKey, $this->group, false, $found);
            return $found;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Object cache has check failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Try fallback
            if ($this->fallback) {
                return $this->fallback->has($key);
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
        if ($this->useFallback && $this->fallback) {
            $this->fallback->clearGroup($group);
            return;
        }
        
        try {
            // WordPress doesn't have native group clearing, so we'll track keys
            // This is a limitation of the object cache API
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group($this->group . '_' . $group);
            } else {
                // Log that we can't clear by group
                if ($this->logger) {
                    $this->logger->warning('Cannot clear cache by group, object cache does not support it');
                }
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Object cache clearGroup failed', [
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
        if ($this->useFallback && $this->fallback) {
            return $this->fallback->getMultiple($keys, $default);
        }
        
        try {
            // Namespace all keys
            $namespacedKeys = array_map([$this, 'getNamespacedKey'], $keys);
            
            // Some object cache implementations support get_multiple
            if (function_exists('wp_cache_get_multiple')) {
                $values = wp_cache_get_multiple($namespacedKeys, $this->group);
                
                // Re-key with original keys
                $result = [];
                foreach ($keys as $i => $key) {
                    $namespacedKey = $namespacedKeys[$i];
                    $result[$key] = isset($values[$namespacedKey]) ? $values[$namespacedKey] : $default;
                }
                
                return $result;
            }
            
            // Fall back to individual gets
            $result = [];
            foreach ($keys as $key) {
                $result[$key] = $this->get($key, $default);
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Object cache getMultiple failed', [
                    'error' => $e->getMessage()
                ]);
            }
            
            // Try fallback
            if ($this->fallback) {
                return $this->fallback->getMultiple($keys, $default);
            }
            
            return array_fill_keys($keys, $default);
        }
    }
    
    /**
     * Set multiple values in cache
     * 
     * @param array $values
     * @param int $ttl
     * @return bool
     */
    public function setMultiple(array $values, int $ttl = 300): bool {
        if ($this->useFallback && $this->fallback) {
            return $this->fallback->setMultiple($values, $ttl);
        }
        
        try {
            // Namespace all keys
            $namespacedValues = [];
            foreach ($values as $key => $value) {
                $namespacedValues[$this->getNamespacedKey($key)] = $value;
            }
            
            // Some object cache implementations support set_multiple
            if (function_exists('wp_cache_set_multiple')) {
                return wp_cache_set_multiple($namespacedValues, $this->group, $ttl);
            }
            
            // Fall back to individual sets
            $success = true;
            foreach ($values as $key => $value) {
                if (!$this->set($key, $value, $ttl)) {
                    $success = false;
                }
            }
            
            return $success;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Object cache setMultiple failed', [
                    'error' => $e->getMessage()
                ]);
            }
            
            // Try fallback
            if ($this->fallback) {
                return $this->fallback->setMultiple($values, $ttl);
            }
            
            return false;
        }
    }
    
    /**
     * Delete multiple values from cache
     * 
     * @param array $keys
     * @return bool
     */
    public function deleteMultiple(array $keys): bool {
        if ($this->useFallback && $this->fallback) {
            return $this->fallback->deleteMultiple($keys);
        }
        
        try {
            // Namespace all keys
            $namespacedKeys = array_map([$this, 'getNamespacedKey'], $keys);
            
            // Some object cache implementations support delete_multiple
            if (function_exists('wp_cache_delete_multiple')) {
                return wp_cache_delete_multiple($namespacedKeys, $this->group);
            }
            
            // Fall back to individual deletes
            $success = true;
            foreach ($keys as $key) {
                if (!$this->delete($key)) {
                    $success = false;
                }
            }
            
            return $success;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Object cache deleteMultiple failed', [
                    'error' => $e->getMessage()
                ]);
            }
            
            // Try fallback
            if ($this->fallback) {
                return $this->fallback->deleteMultiple($keys);
            }
            
            return false;
        }
    }
    
    /**
     * Increment a numeric value
     * 
     * @param string $key
     * @param int $step
     * @return int|false
     */
    public function increment(string $key, int $step = 1) {
        if ($this->useFallback && $this->fallback) {
            return $this->fallback->increment($key, $step);
        }
        
        $namespacedKey = $this->getNamespacedKey($key);
        
        try {
            // wp_cache_incr returns the new value
            $result = wp_cache_incr($namespacedKey, $step, $this->group);
            
            if ($result === false) {
                // Key doesn't exist, initialize it
                if ($this->set($key, $step, HOUR_IN_SECONDS)) {
                    return $step;
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Object cache increment failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Try fallback
            if ($this->fallback) {
                return $this->fallback->increment($key, $step);
            }
            
            return false;
        }
    }
    
    /**
     * Decrement a numeric value
     * 
     * @param string $key
     * @param int $step
     * @return int|false
     */
    public function decrement(string $key, int $step = 1) {
        if ($this->useFallback && $this->fallback) {
            return $this->fallback->decrement($key, $step);
        }
        
        $namespacedKey = $this->getNamespacedKey($key);
        
        try {
            // wp_cache_decr returns the new value
            $result = wp_cache_decr($namespacedKey, $step, $this->group);
            
            if ($result === false) {
                // Key doesn't exist, initialize it
                $value = 0 - $step;
                if ($this->set($key, $value, HOUR_IN_SECONDS)) {
                    return $value;
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Object cache decrement failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Try fallback
            if ($this->fallback) {
                return $this->fallback->decrement($key, $step);
            }
            
            return false;
        }
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function stats(): array {
        $stats = [
            'type' => 'object_cache',
            'group' => $this->group,
            'prefix' => $this->prefix,
            'enabled' => wp_using_ext_object_cache(),
            'using_fallback' => $this->useFallback
        ];
        
        if ($this->useFallback && $this->fallback) {
            $stats['fallback_stats'] = $this->fallback->stats();
            return $stats;
        }
        
        try {
            // Try to get stats from the object cache implementation
            if (function_exists('wp_cache_get_stats')) {
                $cache_stats = wp_cache_get_stats();
                if (is_array($cache_stats)) {
                    $stats = array_merge($stats, $cache_stats);
                }
            }
            
            // Get global groups if available
            if (function_exists('wp_cache_get_global_groups')) {
                $stats['global_groups'] = wp_cache_get_global_groups();
            }
            
            // Get non-persistent groups if available
            if (function_exists('wp_cache_get_non_persistent_groups')) {
                $stats['non_persistent_groups'] = wp_cache_get_non_persistent_groups();
            }
        } catch (\Exception $e) {
            $stats['error'] = $e->getMessage();
        }
        
        return $stats;
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
                $this->logger->error('Object cache remember callback failed', [
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
        return $this->remember($key, $callback, 0);
    }
}