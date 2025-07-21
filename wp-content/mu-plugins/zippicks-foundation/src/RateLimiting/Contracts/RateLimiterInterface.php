<?php

namespace ZipPicks\Foundation\RateLimiting\Contracts;

use Closure;

/**
 * RateLimiterInterface - Core contract for ZipPicks' $100B rate limiting system
 * 
 * This interface defines the foundation for protecting our Taste Graph,
 * Master Critic AI, and viral growth engines while enabling tier-based monetization.
 */
interface RateLimiterInterface
{
    /**
     * Attempt to execute a callback respecting rate limits
     * 
     * @param string $key The unique identifier (e.g., "user:123:taste_graph")
     * @param int $maxAttempts Maximum attempts allowed
     * @param Closure $callback The operation to execute if allowed
     * @param int $decayMinutes Time window for the limit
     * @param int $cost Cost units for this operation (default 1)
     * @return mixed The callback result or throws exception
     */
    public function attempt(
        string $key, 
        int $maxAttempts, 
        Closure $callback, 
        int $decayMinutes = 1,
        int $cost = 1
    );

    /**
     * Check if too many attempts have been made
     * 
     * @param string $key The unique identifier
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $cost Cost units to check
     * @return bool True if limit exceeded
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $cost = 1): bool;

    /**
     * Record a hit against the rate limit
     * 
     * @param string $key The unique identifier
     * @param int $decayMinutes Time window
     * @param int $cost Cost units for this hit
     * @return int Current hit count after increment
     */
    public function hit(string $key, int $decayMinutes = 1, int $cost = 1): int;

    /**
     * Get seconds until the rate limit resets
     * 
     * @param string $key The unique identifier
     * @return int Seconds until available (-1 if available now)
     */
    public function availableIn(string $key): int;

    /**
     * Clear the rate limit for a key
     * 
     * @param string $key The unique identifier
     * @return void
     */
    public function clear(string $key): void;

    /**
     * Get current usage statistics for a key
     * 
     * @param string $key The unique identifier
     * @return array ['current' => int, 'limit' => int, 'remaining' => int, 'reset_at' => int]
     */
    public function usage(string $key): array;

    /**
     * Set custom limit for a specific key (for tier overrides)
     * 
     * @param string $key The unique identifier
     * @param int $limit New limit
     * @param int $decayMinutes Time window
     * @return void
     */
    public function setLimit(string $key, int $limit, int $decayMinutes = 1): void;

    /**
     * Batch check multiple keys for efficiency
     * 
     * @param array $keys Array of keys to check
     * @param int $maxAttempts Maximum attempts per key
     * @return array Key => bool (true if exceeded)
     */
    public function tooManyAttemptsBatch(array $keys, int $maxAttempts): array;

    /**
     * Get algorithm name for monitoring
     * 
     * @return string Algorithm identifier
     */
    public function getAlgorithm(): string;
}