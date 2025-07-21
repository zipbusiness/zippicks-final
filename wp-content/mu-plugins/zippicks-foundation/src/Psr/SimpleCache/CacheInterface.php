<?php
/**
 * PSR-16 Simple Cache Interface
 * 
 * @package ZipPicks\Foundation\Psr\SimpleCache
 * @since 2.0.0
 */

declare(strict_types=1);

namespace Psr\SimpleCache;

/**
 * PSR-16 compatible cache interface
 */
interface CacheInterface
{
    /**
     * Fetches a value from the cache
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Persists data in the cache
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool;

    /**
     * Delete an item from the cache by its unique key
     */
    public function delete(string $key): bool;

    /**
     * Wipes clean the entire cache's keys
     */
    public function clear(): bool;

    /**
     * Obtains multiple cache items by their unique keys
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    /**
     * Persists a set of key => value pairs in the cache
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool;

    /**
     * Deletes multiple cache items in a single operation
     */
    public function deleteMultiple(iterable $keys): bool;

    /**
     * Determines whether an item is present in the cache
     */
    public function has(string $key): bool;
}