<?php
/**
 * Cache Manager Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Cache
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Cache;

/**
 * Contract for enterprise cache management with multi-tier support
 */
interface CacheManagerInterface
{
    /**
     * Get a cache store instance by name
     */
    public function store(?string $name = null): CacheStoreInterface;

    /**
     * Get the default cache driver name
     */
    public function getDefaultDriver(): string;

    /**
     * Set the default cache driver name
     */
    public function setDefaultDriver(string $name): void;

    /**
     * Create a new cache repository with the given implementation
     */
    public function repository(CacheStoreInterface $store): CacheRepositoryInterface;

    /**
     * Get all configured stores
     */
    public function getStores(): array;

    /**
     * Register a custom driver creator
     */
    public function extend(string $driver, \Closure $callback): void;

    /**
     * Determine if a driver is registered
     */
    public function hasDriver(string $driver): bool;

    /**
     * Warm cache with specified data
     */
    public function warm(array $data): void;

    /**
     * Create a lock instance
     */
    public function lock(string $name, int $seconds = 0, ?string $owner = null): LockInterface;

    /**
     * Get cache statistics across all stores
     */
    public function getStatistics(): array;

    /**
     * Clear all cache stores
     */
    public function flushAll(): bool;
}