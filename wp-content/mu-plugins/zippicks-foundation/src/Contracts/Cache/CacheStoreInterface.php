<?php
/**
 * Cache Store Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Cache
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Cache;

/**
 * Enterprise cache store contract for $100B scale
 */
interface CacheStoreInterface
{
    /**
     * Retrieve an item from the cache by key
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Retrieve multiple items from the cache by key
     */
    public function many(array $keys): array;

    /**
     * Store an item in the cache
     */
    public function put(string $key, mixed $value, ?int $seconds = null): bool;

    /**
     * Store multiple items in the cache
     */
    public function putMany(array $values, ?int $seconds = null): bool;

    /**
     * Increment the value of an item in the cache
     */
    public function increment(string $key, int $value = 1): int|bool;

    /**
     * Decrement the value of an item in the cache
     */
    public function decrement(string $key, int $value = 1): int|bool;

    /**
     * Store an item in the cache indefinitely
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Remove an item from the cache
     */
    public function forget(string $key): bool;

    /**
     * Remove all items from the cache
     */
    public function flush(): bool;

    /**
     * Get the cache key prefix
     */
    public function getPrefix(): string;

    /**
     * Get driver metrics for monitoring
     */
    public function getMetrics(): array;

    /**
     * Check if driver is healthy
     */
    public function isHealthy(): bool;

    /**
     * Get driver name
     */
    public function getName(): string;
}