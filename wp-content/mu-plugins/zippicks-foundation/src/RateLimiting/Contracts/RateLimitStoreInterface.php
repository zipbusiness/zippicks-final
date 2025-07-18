<?php

namespace ZipPicks\Foundation\RateLimiting\Contracts;

/**
 * RateLimitStoreInterface - Storage backend for rate limit data
 * 
 * Supports Redis clusters, database fallbacks, and in-memory caching
 * to achieve <1ms latency at 10M+ checks/second for our $100B platform
 */
interface RateLimitStoreInterface
{
    /**
     * Increment the counter for a key
     * 
     * @param string $key The rate limit key
     * @param int $decay Seconds until the counter expires
     * @param int $amount Amount to increment (for cost-based limiting)
     * @return int New counter value
     */
    public function increment(string $key, int $decay, int $amount = 1): int;

    /**
     * Get current counter value
     * 
     * @param string $key The rate limit key
     * @return int Current count (0 if not exists)
     */
    public function get(string $key): int;

    /**
     * Reset a counter
     * 
     * @param string $key The rate limit key
     * @return void
     */
    public function reset(string $key): void;

    /**
     * Get TTL remaining for a key
     * 
     * @param string $key The rate limit key
     * @return int Seconds remaining (-1 if no TTL)
     */
    public function ttl(string $key): int;

    /**
     * Batch get multiple keys for efficiency
     * 
     * @param array $keys Array of rate limit keys
     * @return array Key => value mapping
     */
    public function getBatch(array $keys): array;

    /**
     * Atomic increment with limit check (for sliding window)
     * 
     * @param string $key The rate limit key
     * @param int $limit Maximum allowed
     * @param int $window Time window in seconds
     * @param int $amount Amount to increment
     * @return array ['allowed' => bool, 'current' => int, 'ttl' => int]
     */
    public function incrementWithLimit(string $key, int $limit, int $window, int $amount = 1): array;

    /**
     * Store custom metadata for a key (user tier, cost multipliers)
     * 
     * @param string $key The rate limit key
     * @param array $metadata Custom data
     * @param int $ttl Time to live
     * @return void
     */
    public function setMetadata(string $key, array $metadata, int $ttl): void;

    /**
     * Get custom metadata for a key
     * 
     * @param string $key The rate limit key
     * @return array|null Metadata or null if not exists
     */
    public function getMetadata(string $key): ?array;

    /**
     * Check if store is available (for failover)
     * 
     * @return bool True if operational
     */
    public function isAvailable(): bool;

    /**
     * Get store type for monitoring
     * 
     * @return string Store identifier (redis, database, memory)
     */
    public function getType(): string;
}