<?php
/**
 * Cache Manager
 * 
 * Manages cache operations with support for multiple cache backends,
 * fallback chaining, comprehensive error logging, and performance benchmarking.
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Cache;

use ZipPicksVibes\Cache\Adapters\TransientAdapter;
use ZipPicksVibes\Cache\Adapters\RedisAdapter;
use ZipPicksVibes\Cache\Adapters\ObjectCacheAdapter;

/**
 * Class CacheManager
 * 
 * Provides a unified caching interface with automatic fallback chaining,
 * performance monitoring, and comprehensive error handling.
 */
class CacheManager implements CacheInterface {
    
    /**
     * Primary cache adapter
     * 
     * @var CacheInterface
     */
    private CacheInterface $adapter;
    
    /**
     * Fallback adapters chain
     * 
     * @var CacheInterface[]
     */
    private array $fallbackAdapters = [];
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger;
    
    /**
     * Cache key prefix
     * 
     * @var string
     */
    private string $prefix = 'zippicks_vibes_';
    
    /**
     * Default TTL
     * 
     * @var int
     */
    private int $defaultTtl = 300; // 5 minutes
    
    /**
     * Cache groups for organized invalidation
     * 
     * @var array
     */
    private array $groups = [];
    
    /**
     * Enable benchmarking
     * 
     * @var bool
     */
    private bool $enableBenchmarking = false;
    
    /**
     * Cache statistics
     * 
     * @var array
     */
    private array $cacheStats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'errors' => 0
    ];
    
    /**
     * Constructor
     * 
     * @param string|null $driver Force specific driver
     * @param array $config Configuration options
     * @param mixed $logger Logger instance
     */
    public function __construct(?string $driver = null, array $config = [], $logger = null) {
        $this->logger = $logger;
        $this->prefix = $config['prefix'] ?? $this->prefix;
        $this->defaultTtl = $config['default_ttl'] ?? $this->defaultTtl;
        $this->enableBenchmarking = $config['enable_benchmarking'] ?? false;
        
        // CRITICAL: Add connection limits
        $maxConnections = $config['max_connections'] ?? 5;
        $connectionTimeout = $config['connection_timeout'] ?? 2;
        
        // Initialize adapters with connection pooling
        $this->initializeAdapters($driver, $maxConnections, $connectionTimeout, $config);
        
        // Log initialization ONCE
        if ($this->logger && method_exists($this->logger, 'info')) {
            $this->logger->info('[ZipPicks] [INFO] Cache manager initialized', [
                'primary_driver' => get_class($this->adapter),
                'fallback_count' => count($this->fallbackAdapters),
                'prefix' => $this->prefix,
                'benchmarking' => $this->enableBenchmarking,
                'instance_id' => spl_object_id($this) // Track instance
            ]);
        }
    }
    
    /**
     * Initialize adapters with connection pooling
     */
    private function initializeAdapters(?string $driver, int $maxConnections, int $timeout, array $config): void {
        // Use static connection pool to prevent multiple connections
        static $connectionPool = [];
        static $adapterInstances = [];
        
        $adapters = [];
        
        // Pass logger and config to adapters
        $config['logger'] = $this->logger;
        $config['prefix'] = $this->prefix;
        $config['redis_timeout'] = $timeout;
        
        // Try Redis first with connection pooling
        if ((!$driver || $driver === 'redis' || $driver === 'auto') && class_exists('Redis')) {
            try {
                $poolKey = 'redis_default';
                
                // Check if we already have a Redis adapter instance
                if (isset($adapterInstances['redis'])) {
                    $redisAdapter = $adapterInstances['redis'];
                    // Check if it's still available
                    if (method_exists($redisAdapter, 'is_available') && $redisAdapter->is_available()) {
                        $adapters[] = $redisAdapter;
                    } else {
                        unset($adapterInstances['redis']);
                    }
                } else {
                    // Create new adapter with connection limits
                    if (count($connectionPool) < $maxConnections) {
                        $redisAdapter = new RedisAdapter($config);
                        if (method_exists($redisAdapter, 'is_available') && $redisAdapter->is_available()) {
                            $adapterInstances['redis'] = $redisAdapter;
                            $adapters[] = $redisAdapter;
                            $connectionPool[$poolKey] = true; // Track connection count
                        }
                    } else {
                        if ($this->logger) {
                            $this->logger->warning('Redis connection limit reached', [
                                'max_connections' => $maxConnections,
                                'current_connections' => count($connectionPool)
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->error('Redis adapter initialization failed', ['error' => $e->getMessage()]);
                }
            }
        }
        
        // Add fallback adapters
        if (!isset($adapterInstances['object-cache'])) {
            $adapterInstances['object-cache'] = new ObjectCacheAdapter($config);
        }
        $adapters[] = $adapterInstances['object-cache'];
        
        if (!isset($adapterInstances['transient'])) {
            $adapterInstances['transient'] = new TransientAdapter($config);
        }
        $adapters[] = $adapterInstances['transient'];
        
        // Set primary and fallbacks
        $this->adapter = array_shift($adapters);
        $this->fallbackAdapters = $adapters;
    }
    
    /**
     * Create specific adapter by driver name
     * 
     * @param string $driver
     * @param array $config
     * @return CacheInterface|null
     */
    private function createSpecificAdapter(string $driver, array $config): ?CacheInterface {
        try {
            switch ($driver) {
                case 'redis':
                    if (class_exists('Redis')) {
                        return new RedisAdapter($config);
                    }
                    break;
                    
                case 'object-cache':
                    if (function_exists('wp_cache_get')) {
                        return new ObjectCacheAdapter($config);
                    }
                    break;
                    
                case 'transient':
                    return new TransientAdapter($config);
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->warning('Failed to create specific adapter', [
                    'driver' => $driver,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return null;
    }
    
    /**
     * Get a value from cache with fallback support
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null) {
        $key = $this->prefixKey($key);
        $startTime = $this->enableBenchmarking ? microtime(true) : null;
        
        // Try primary adapter
        $value = $this->tryAdapter($this->adapter, 'get', [$key, $default]);
        
        if ($value !== null && $value !== $default) {
            $this->recordCacheHit($key, $startTime);
            return $value;
        }
        
        // Try fallback adapters
        foreach ($this->fallbackAdapters as $fallback) {
            $value = $this->tryAdapter($fallback, 'get', [$key, $default]);
            
            if ($value !== null && $value !== $default) {
                // Found in fallback, promote to primary cache
                $this->tryAdapter($this->adapter, 'set', [$key, $value, $this->defaultTtl]);
                $this->recordCacheHit($key, $startTime, 'fallback');
                return $value;
            }
        }
        
        $this->recordCacheMiss($key, $startTime);
        return $default;
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
        $key = $this->prefixKey($key);
        $ttl = $ttl ?: $this->defaultTtl;
        $startTime = $this->enableBenchmarking ? microtime(true) : null;
        
        // Try primary adapter
        $result = $this->tryAdapter($this->adapter, 'set', [$key, $value, $ttl]);
        
        if ($result) {
            // Track in groups if applicable
            $this->trackInGroups($key);
            $this->recordCacheSet($key, $startTime);
            return true;
        }
        
        // Try fallbacks if primary failed
        foreach ($this->fallbackAdapters as $fallback) {
            $result = $this->tryAdapter($fallback, 'set', [$key, $value, $ttl]);
            
            if ($result) {
                $this->trackInGroups($key);
                $this->recordCacheSet($key, $startTime, 'fallback');
                return true;
            }
        }
        
        $this->cacheStats['errors']++;
        return false;
    }
    
    /**
     * Delete a value from cache
     * 
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool {
        $key = $this->prefixKey($key);
        $success = false;
        
        // Delete from all adapters
        $success = $this->tryAdapter($this->adapter, 'delete', [$key]) || $success;
        
        foreach ($this->fallbackAdapters as $fallback) {
            $success = $this->tryAdapter($fallback, 'delete', [$key]) || $success;
        }
        
        if ($success) {
            $this->cacheStats['deletes']++;
        }
        
        return $success;
    }
    
    /**
     * Clear all cache
     * 
     * @return bool
     */
    public function flush(): bool {
        $success = true;
        
        // Clear groups tracking
        $this->groups = [];
        
        // Flush all adapters
        $success = $this->tryAdapter($this->adapter, 'flush', []) && $success;
        
        foreach ($this->fallbackAdapters as $fallback) {
            $success = $this->tryAdapter($fallback, 'flush', []) && $success;
        }
        
        // Reset stats
        $this->cacheStats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
            'errors' => 0
        ];
        
        if ($this->logger) {
            $this->logger->info('Cache flushed', ['success' => $success]);
        }
        
        return $success;
    }
    
    /**
     * Alias for flush() for consistency
     * 
     * @return bool
     */
    public function flushAll(): bool {
        return $this->flush();
    }
    
    /**
     * Check if key exists in cache
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool {
        $key = $this->prefixKey($key);
        
        // Check primary adapter
        if ($this->tryAdapter($this->adapter, 'has', [$key])) {
            return true;
        }
        
        // Check fallbacks
        foreach ($this->fallbackAdapters as $fallback) {
            if ($this->tryAdapter($fallback, 'has', [$key])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Clear cache by group
     * 
     * @param string $group
     * @return void
     */
    public function clearGroup(string $group): void {
        // Clear from all adapters
        $this->tryAdapter($this->adapter, 'clearGroup', [$group]);
        
        foreach ($this->fallbackAdapters as $fallback) {
            $this->tryAdapter($fallback, 'clearGroup', [$group]);
        }
        
        // Remove from tracking
        unset($this->groups[$group]);
        
        if ($this->logger) {
            $this->logger->info('Cache group cleared', ['group' => $group]);
        }
    }
    
    /**
     * Alias for clearGroup() for backward compatibility
     * 
     * @param string $group
     * @return bool
     */
    public function clearByGroup(string $group): bool {
        $this->clearGroup($group);
        return true;
    }
    
    /**
     * Get multiple values from cache
     * 
     * @param array $keys
     * @param mixed $default
     * @return array
     */
    public function getMultiple(array $keys, $default = null): array {
        $prefixedKeys = array_map([$this, 'prefixKey'], $keys);
        $startTime = $this->enableBenchmarking ? microtime(true) : null;
        
        // Try primary adapter
        $values = $this->tryAdapter($this->adapter, 'getMultiple', [$prefixedKeys, $default]);
        
        if ($values && !$this->allDefaultValues($values, $default)) {
            $result = $this->unprefixKeys($keys, $prefixedKeys, $values);
            $this->recordBulkOperation('getMultiple', count($keys), $startTime);
            return $result;
        }
        
        // Try fallbacks
        foreach ($this->fallbackAdapters as $fallback) {
            $values = $this->tryAdapter($fallback, 'getMultiple', [$prefixedKeys, $default]);
            
            if ($values && !$this->allDefaultValues($values, $default)) {
                $result = $this->unprefixKeys($keys, $prefixedKeys, $values);
                $this->recordBulkOperation('getMultiple', count($keys), $startTime, 'fallback');
                return $result;
            }
        }
        
        // Return defaults for all keys
        return array_fill_keys($keys, $default);
    }
    
    /**
     * Set multiple values in cache
     * 
     * @param array $values
     * @param int $ttl
     * @return bool
     */
    public function setMultiple(array $values, int $ttl = 0): bool {
        $ttl = $ttl ?: $this->defaultTtl;
        $startTime = $this->enableBenchmarking ? microtime(true) : null;
        
        // Prefix keys
        $prefixedValues = [];
        foreach ($values as $key => $value) {
            $prefixedKey = $this->prefixKey($key);
            $prefixedValues[$prefixedKey] = $value;
            $this->trackInGroups($prefixedKey);
        }
        
        // Try primary adapter
        if ($this->tryAdapter($this->adapter, 'setMultiple', [$prefixedValues, $ttl])) {
            $this->recordBulkOperation('setMultiple', count($values), $startTime);
            return true;
        }
        
        // Try fallbacks
        foreach ($this->fallbackAdapters as $fallback) {
            if ($this->tryAdapter($fallback, 'setMultiple', [$prefixedValues, $ttl])) {
                $this->recordBulkOperation('setMultiple', count($values), $startTime, 'fallback');
                return true;
            }
        }
        
        $this->cacheStats['errors'] += count($values);
        return false;
    }
    
    /**
     * Delete multiple values from cache
     * 
     * @param array $keys
     * @return bool
     */
    public function deleteMultiple(array $keys): bool {
        $prefixedKeys = array_map([$this, 'prefixKey'], $keys);
        $success = false;
        
        // Delete from all adapters
        $success = $this->tryAdapter($this->adapter, 'deleteMultiple', [$prefixedKeys]) || $success;
        
        foreach ($this->fallbackAdapters as $fallback) {
            $success = $this->tryAdapter($fallback, 'deleteMultiple', [$prefixedKeys]) || $success;
        }
        
        if ($success) {
            $this->cacheStats['deletes'] += count($keys);
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
        $key = $this->prefixKey($key);
        
        // Try primary adapter
        $result = $this->tryAdapter($this->adapter, 'increment', [$key, $step]);
        
        if ($result !== false) {
            return $result;
        }
        
        // Try fallbacks
        foreach ($this->fallbackAdapters as $fallback) {
            $result = $this->tryAdapter($fallback, 'increment', [$key, $step]);
            
            if ($result !== false) {
                return $result;
            }
        }
        
        return false;
    }
    
    /**
     * Alias for increment() - used by RateLimiter
     * 
     * @param string $key
     * @param int $step
     * @return int|false
     */
    public function incr(string $key, int $step = 1) {
        return $this->increment($key, $step);
    }
    
    /**
     * Decrement a numeric value
     * 
     * @param string $key
     * @param int $step
     * @return int|false
     */
    public function decrement(string $key, int $step = 1) {
        $key = $this->prefixKey($key);
        
        // Try primary adapter
        $result = $this->tryAdapter($this->adapter, 'decrement', [$key, $step]);
        
        if ($result !== false) {
            return $result;
        }
        
        // Try fallbacks
        foreach ($this->fallbackAdapters as $fallback) {
            $result = $this->tryAdapter($fallback, 'decrement', [$key, $step]);
            
            if ($result !== false) {
                return $result;
            }
        }
        
        return false;
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function stats(): array {
        $stats = [
            'manager' => [
                'primary_driver' => get_class($this->adapter),
                'fallback_drivers' => array_map('get_class', $this->fallbackAdapters),
                'prefix' => $this->prefix,
                'groups' => array_map('count', $this->groups),
                'benchmarking' => $this->enableBenchmarking,
                'performance' => $this->cacheStats
            ]
        ];
        
        // Get adapter stats
        $stats['primary'] = $this->tryAdapter($this->adapter, 'stats', []) ?: [];
        
        foreach ($this->fallbackAdapters as $i => $fallback) {
            $stats['fallback_' . $i] = $this->tryAdapter($fallback, 'stats', []) ?: [];
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
    public function remember(string $key, callable $callback, int $ttl = 0) {
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
                $this->logger->error('Cache remember callback failed', [
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
    
    /**
     * Try to execute method on adapter with error handling
     * 
     * @param CacheInterface $adapter
     * @param string $method
     * @param array $args
     * @return mixed
     */
    private function tryAdapter(CacheInterface $adapter, string $method, array $args) {
        try {
            return call_user_func_array([$adapter, $method], $args);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Cache adapter operation failed', [
                    'adapter' => get_class($adapter),
                    'method' => $method,
                    'error' => $e->getMessage()
                ]);
            }
            
            $this->cacheStats['errors']++;
            return null;
        }
    }
    
    /**
     * Record cache hit
     * 
     * @param string $key
     * @param float|null $startTime
     * @param string $source
     */
    private function recordCacheHit(string $key, ?float $startTime, string $source = 'primary'): void {
        $this->cacheStats['hits']++;
        
        if ($this->enableBenchmarking && $startTime && $this->logger) {
            $duration = microtime(true) - $startTime;
            
            if ($duration > 0.1) { // Log slow operations
                $this->logger->warning('Slow cache hit', [
                    'key' => $key,
                    'duration' => $duration,
                    'source' => $source
                ]);
            }
        }
    }
    
    /**
     * Record cache miss
     * 
     * @param string $key
     * @param float|null $startTime
     */
    private function recordCacheMiss(string $key, ?float $startTime): void {
        $this->cacheStats['misses']++;
        
        if ($this->enableBenchmarking && $startTime && $this->logger) {
            $duration = microtime(true) - $startTime;
            $this->logger->debug('Cache miss', [
                'key' => $key,
                'duration' => $duration
            ]);
        }
    }
    
    /**
     * Record cache set operation
     * 
     * @param string $key
     * @param float|null $startTime
     * @param string $target
     */
    private function recordCacheSet(string $key, ?float $startTime, string $target = 'primary'): void {
        $this->cacheStats['sets']++;
        
        if ($this->enableBenchmarking && $startTime && $this->logger) {
            $duration = microtime(true) - $startTime;
            
            if ($duration > 0.1) { // Log slow operations
                $this->logger->warning('Slow cache set', [
                    'key' => $key,
                    'duration' => $duration,
                    'target' => $target
                ]);
            }
        }
    }
    
    /**
     * Record bulk operation
     * 
     * @param string $operation
     * @param int $count
     * @param float|null $startTime
     * @param string $source
     */
    private function recordBulkOperation(string $operation, int $count, ?float $startTime, string $source = 'primary'): void {
        if ($this->enableBenchmarking && $startTime && $this->logger) {
            $duration = microtime(true) - $startTime;
            
            if ($duration > 0.5) { // Log slow bulk operations
                $this->logger->warning('Slow bulk cache operation', [
                    'operation' => $operation,
                    'count' => $count,
                    'duration' => $duration,
                    'source' => $source
                ]);
            }
        }
    }
    
    /**
     * Check if all values are default
     * 
     * @param array $values
     * @param mixed $default
     * @return bool
     */
    private function allDefaultValues(array $values, $default): bool {
        foreach ($values as $value) {
            if ($value !== $default) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Unprefix keys in result array
     * 
     * @param array $originalKeys
     * @param array $prefixedKeys
     * @param array $values
     * @return array
     */
    private function unprefixKeys(array $originalKeys, array $prefixedKeys, array $values): array {
        $result = [];
        foreach ($originalKeys as $i => $key) {
            $prefixedKey = $prefixedKeys[$i];
            $result[$key] = $values[$prefixedKey] ?? null;
        }
        return $result;
    }
    
    /**
     * Prefix key with cache prefix
     * 
     * @param string $key
     * @return string
     */
    private function prefixKey(string $key): string {
        return $this->prefix . $key;
    }
    
    /**
     * Track key in groups based on pattern
     * 
     * @param string $key
     */
    private function trackInGroups(string $key): void {
        // Track vibes in vibes group
        if (strpos($key, 'vibe_') !== false) {
            $this->addToGroup($key, 'vibes');
        }
        
        // Track categories in categories group
        if (strpos($key, 'category_') !== false) {
            $this->addToGroup($key, 'categories');
        }
        
        // Track queries in queries group
        if (strpos($key, 'query_') !== false) {
            $this->addToGroup($key, 'queries');
        }
        
        // Track searches in searches group
        if (strpos($key, 'search_') !== false) {
            $this->addToGroup($key, 'searches');
        }
    }
    
    /**
     * Add key to group
     * 
     * @param string $key
     * @param string $group
     */
    public function addToGroup(string $key, string $group): void {
        if (!isset($this->groups[$group])) {
            $this->groups[$group] = [];
        }
        
        $this->groups[$group][] = $key;
        $this->groups[$group] = array_unique($this->groups[$group]);
    }
    
    /**
     * Get the current cache adapter
     * 
     * @return CacheInterface
     */
    public function getAdapter(): CacheInterface {
        return $this->adapter;
    }
    
    /**
     * Get fallback adapters
     * 
     * @return CacheInterface[]
     */
    public function getFallbackAdapters(): array {
        return $this->fallbackAdapters;
    }
    
    /**
     * Enable or disable benchmarking
     * 
     * @param bool $enable
     */
    public function setBenchmarking(bool $enable): void {
        $this->enableBenchmarking = $enable;
    }
    
    /**
     * Get cache hit rate
     * 
     * @return float
     */
    public function getHitRate(): float {
        $total = $this->cacheStats['hits'] + $this->cacheStats['misses'];
        
        if ($total === 0) {
            return 0.0;
        }
        
        return round(($this->cacheStats['hits'] / $total) * 100, 2);
    }
    
    /**
     * Disconnect all adapters
     */
    public function disconnect(): void {
        // Disconnect primary adapter
        if ($this->adapter && method_exists($this->adapter, 'disconnect')) {
            $this->adapter->disconnect();
        }
        
        // Disconnect fallback adapters
        foreach ($this->fallbackAdapters as $adapter) {
            if (method_exists($adapter, 'disconnect')) {
                $adapter->disconnect();
            }
        }
    }
}