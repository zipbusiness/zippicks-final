<?php
/**
 * Cache Interface
 * 
 * Defines the contract for cache implementations with full error handling,
 * TTL support, group isolation, and fallback logic.
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Cache;

/**
 * Interface CacheInterface
 * 
 * All cache adapters must implement this interface to ensure consistency
 * across different caching backends (Redis, Memcached, Transients, etc.)
 */
interface CacheInterface {
    
    /**
     * Get a value from cache
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed The cached value or default if not found
     */
    public function get(string $key, $default = null);
    
    /**
     * Set a value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = no expiration)
     * @return bool True on success, false on failure
     */
    public function set(string $key, $value, int $ttl = 0): bool;
    
    /**
     * Delete a value from cache
     * 
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool;
    
    /**
     * Clear all cache
     * 
     * @return bool True on success, false on failure
     */
    public function flush(): bool;
    
    /**
     * Check if key exists in cache
     * 
     * @param string $key Cache key
     * @return bool True if exists, false otherwise
     */
    public function has(string $key): bool;
    
    /**
     * Clear cache by group
     * 
     * Removes all cache entries belonging to a specific group.
     * Groups are typically identified by key prefixes or patterns.
     * 
     * @param string $group Group identifier
     * @return void
     */
    public function clearGroup(string $group): void;
    
    /**
     * Get multiple values from cache
     * 
     * @param array $keys Array of cache keys
     * @param mixed $default Default value for missing keys
     * @return array Associative array of key => value pairs
     */
    public function getMultiple(array $keys, $default = null): array;
    
    /**
     * Set multiple values in cache
     * 
     * @param array $values Key-value pairs to cache
     * @param int $ttl Time to live in seconds
     * @return bool True if all values were set, false otherwise
     */
    public function setMultiple(array $values, int $ttl = 300): bool;
    
    /**
     * Delete multiple values from cache
     * 
     * @param array $keys Array of cache keys
     * @return bool True if all keys were deleted, false otherwise
     */
    public function deleteMultiple(array $keys): bool;
    
    /**
     * Increment a numeric value
     * 
     * @param string $key Cache key
     * @param int $step Step to increment by
     * @return int|false New value or false on failure
     */
    public function increment(string $key, int $step = 1);
    
    /**
     * Decrement a numeric value
     * 
     * @param string $key Cache key
     * @param int $step Step to decrement by
     * @return int|false New value or false on failure
     */
    public function decrement(string $key, int $step = 1);
    
    /**
     * Get cache statistics
     * 
     * Returns an array of statistics about the cache backend.
     * The exact contents may vary by implementation but should
     * include at least 'type' and basic metrics.
     * 
     * @return array Cache statistics
     */
    public function stats(): array;
    
    /**
     * Remember a value (get or compute)
     * 
     * Gets a value from cache, or computes and caches it if not found.
     * This is useful for expensive operations that should be cached.
     * 
     * @param string $key Cache key
     * @param callable $callback Callback to compute value if not cached
     * @param int $ttl Time to live in seconds
     * @return mixed The cached or computed value
     */
    public function remember(string $key, callable $callback, int $ttl = 300);
    
    /**
     * Remember a value forever
     * 
     * Same as remember() but with no expiration.
     * 
     * @param string $key Cache key
     * @param callable $callback Callback to compute value if not cached
     * @return mixed The cached or computed value
     */
    public function rememberForever(string $key, callable $callback);
}