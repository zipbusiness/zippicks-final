<?php
/**
 * Cache Repository Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Cache
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Cache;

/**
 * High-level cache operations with enterprise features
 */
interface CacheRepositoryInterface extends CacheStoreInterface
{
    /**
     * Store an item in cache if the key doesn't exist
     */
    public function add(string $key, mixed $value, ?int $seconds = null): bool;

    /**
     * Get an item from cache, or execute callback and store result
     */
    public function remember(string $key, int $seconds, \Closure $callback): mixed;

    /**
     * Get an item from cache, or execute callback and store result forever
     */
    public function rememberForever(string $key, \Closure $callback): mixed;

    /**
     * Remove an item from cache and return its value
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Store an item in cache for a given duration using DateTimeInterface
     */
    public function putUntil(string $key, mixed $value, \DateTimeInterface $expiration): bool;

    /**
     * Get cache tags instance
     */
    public function tags(string|array $tags): TaggedCacheInterface;

    /**
     * Execute callback without cache stampede
     */
    public function stampedeSafe(string $key, int $seconds, \Closure $callback): mixed;

    /**
     * Get underlying cache store
     */
    public function getStore(): CacheStoreInterface;

    /**
     * Set cache event dispatcher
     */
    public function setEventDispatcher(\ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface $events): void;
}