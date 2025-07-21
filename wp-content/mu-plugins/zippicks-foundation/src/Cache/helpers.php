<?php
/**
 * Cache Helper Functions
 * 
 * @package ZipPicks\Foundation\Cache
 * @since 2.0.0
 */

declare(strict_types=1);

use ZipPicks\Foundation\Contracts\Cache\CacheRepositoryInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheManagerInterface;

if (!function_exists('cache')) {
    /**
     * Get the cache repository instance
     * 
     * @param string|null $store
     * @return CacheRepositoryInterface
     */
    function cache(?string $store = null): CacheRepositoryInterface
    {
        $foundation = zippicks_foundation();
        
        if ($store) {
            $manager = $foundation->get('cache.manager');
            return $manager->repository($manager->store($store));
        }
        
        return $foundation->get('cache');
    }
}

if (!function_exists('cache_get')) {
    /**
     * Get an item from the cache
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function cache_get(string $key, mixed $default = null): mixed
    {
        return cache()->get($key, $default);
    }
}

if (!function_exists('cache_put')) {
    /**
     * Store an item in the cache
     * 
     * @param string $key
     * @param mixed $value
     * @param int|null $seconds
     * @return bool
     */
    function cache_put(string $key, mixed $value, ?int $seconds = null): bool
    {
        return cache()->put($key, $value, $seconds);
    }
}

if (!function_exists('cache_remember')) {
    /**
     * Get an item from cache or store the result of a callback
     * 
     * @param string $key
     * @param int $seconds
     * @param callable $callback
     * @return mixed
     */
    function cache_remember(string $key, int $seconds, callable $callback): mixed
    {
        return cache()->remember($key, $seconds, $callback);
    }
}

if (!function_exists('cache_forget')) {
    /**
     * Remove an item from the cache
     * 
     * @param string $key
     * @return bool
     */
    function cache_forget(string $key): bool
    {
        return cache()->forget($key);
    }
}

if (!function_exists('cache_flush')) {
    /**
     * Flush the entire cache
     * 
     * @return bool
     */
    function cache_flush(): bool
    {
        return cache()->flush();
    }
}

if (!function_exists('cache_manager')) {
    /**
     * Get the cache manager instance
     * 
     * @return CacheManagerInterface
     */
    function cache_manager(): CacheManagerInterface
    {
        return zippicks_foundation()->get('cache.manager');
    }
}

if (!function_exists('cache_stampede_safe')) {
    /**
     * Execute callback with cache stampede protection
     * 
     * @param string $key
     * @param int $seconds
     * @param callable $callback
     * @return mixed
     */
    function cache_stampede_safe(string $key, int $seconds, callable $callback): mixed
    {
        return cache()->stampedeSafe($key, $seconds, $callback);
    }
}