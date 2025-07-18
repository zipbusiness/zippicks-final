<?php
/**
 * Cache Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Cache
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Cache;

use Psr\SimpleCache\CacheInterface as PsrCacheInterface;

/**
 * Extended cache interface based on PSR-16 with tag support
 */
interface CacheInterface extends PsrCacheInterface
{
    /**
     * Store an item in the cache indefinitely
     * 
     * @param string $key
     * @param mixed $value
     * 
     * @return bool
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Remove an item from the cache and return its value
     * 
     * @param string $key
     * @param mixed $default
     * 
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the cache if the key doesn't exist
     * 
     * @param string $key
     * @param mixed $value
     * @param null|int|\DateInterval $ttl
     * 
     * @return bool
     */
    public function add(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool;

    /**
     * Get the cache key prefix
     * 
     * @return string
     */
    public function getPrefix(): string;

    /**
     * Store an item in the cache with tags
     * 
     * @param string $key
     * @param mixed $value
     * @param null|int|\DateInterval $ttl
     * @param array<string> $tags
     * 
     * @return bool
     */
    public function setWithTags(string $key, mixed $value, null|int|\DateInterval $ttl = null, array $tags = []): bool;

    /**
     * Invalidate all cached items with the given tags
     * 
     * @param array<string> $tags
     * 
     * @return bool
     */
    public function invalidateTags(array $tags): bool;
}