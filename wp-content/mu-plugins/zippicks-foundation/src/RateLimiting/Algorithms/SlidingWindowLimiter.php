<?php

namespace ZipPicks\Foundation\RateLimiting\Algorithms;

use ZipPicks\Foundation\RateLimiting\RateLimiter;
use ZipPicks\Foundation\RateLimiting\Contracts\RateLimitStoreInterface;
use ZipPicks\Foundation\RateLimiting\Stores\RedisStore;

/**
 * SlidingWindowLimiter - Accurate sliding time window rate limiting
 * 
 * More accurate than fixed window, prevents edge case abuse.
 * Perfect for our Taste Graph calculations and AI scoring
 * where we need precise metering for billing.
 */
class SlidingWindowLimiter extends RateLimiter
{
    /**
     * @var int Window size in seconds
     */
    protected int $windowSize;

    /**
     * @var bool Use Redis sorted sets for precision
     */
    protected bool $useRedisSortedSets;

    /**
     * Constructor
     * 
     * @param RateLimitStoreInterface $store
     * @param int $windowSize Window size in seconds
     */
    public function __construct(RateLimitStoreInterface $store, int $windowSize = 60)
    {
        parent::__construct($store, 'sliding_window');
        $this->windowSize = $windowSize;
        $this->useRedisSortedSets = $store instanceof RedisStore;
    }

    /**
     * {@inheritDoc}
     */
    public function hit(string $key, int $decayMinutes = 1, int $cost = 1): int
    {
        if ($this->useRedisSortedSets) {
            return $this->hitWithSortedSet($key, $cost);
        }
        
        // Fallback to approximation for non-Redis stores
        return $this->hitWithApproximation($key, $cost);
    }

    /**
     * {@inheritDoc}
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $cost = 1): bool
    {
        if ($this->useRedisSortedSets) {
            return $this->checkWithSortedSet($key, $maxAttempts, $cost);
        }
        
        // Fallback to approximation
        return $this->checkWithApproximation($key, $maxAttempts, $cost);
    }

    /**
     * {@inheritDoc}
     */
    public function usage(string $key): array
    {
        if ($this->useRedisSortedSets) {
            return $this->usageWithSortedSet($key);
        }
        
        return parent::usage($key);
    }

    /**
     * Hit using Redis sorted sets for precision
     * 
     * @param string $key
     * @param int $cost
     * @return int
     */
    protected function hitWithSortedSet(string $key, int $cost): int
    {
        $now = microtime(true);
        $windowStart = $now - $this->windowSize;
        
        // Use Lua script for atomicity
        $script = '
            local key = KEYS[1]
            local now = tonumber(ARGV[1])
            local window_start = tonumber(ARGV[2])
            local cost = tonumber(ARGV[3])
            local window_size = tonumber(ARGV[4])
            
            -- Remove old entries
            redis.call("ZREMRANGEBYSCORE", key, 0, window_start)
            
            -- Add new entries (one per cost unit for accurate counting)
            for i = 1, cost do
                redis.call("ZADD", key, now, now .. ":" .. i)
            end
            
            -- Set expiration
            redis.call("EXPIRE", key, window_size + 1)
            
            -- Return current count
            return redis.call("ZCARD", key)
        ';
        
        if ($this->store instanceof RedisStore) {
            $redis = $this->getRedisConnection();
            return (int) $redis->eval($script, [$key, $now, $windowStart, $cost, $this->windowSize], 1);
        }
        
        return 0;
    }

    /**
     * Check using Redis sorted sets
     * 
     * @param string $key
     * @param int $maxAttempts
     * @param int $cost
     * @return bool
     */
    protected function checkWithSortedSet(string $key, int $maxAttempts, int $cost): bool
    {
        $now = microtime(true);
        $windowStart = $now - $this->windowSize;
        
        $script = '
            local key = KEYS[1]
            local window_start = tonumber(ARGV[1])
            local max_attempts = tonumber(ARGV[2])
            local cost = tonumber(ARGV[3])
            
            -- Remove old entries
            redis.call("ZREMRANGEBYSCORE", key, 0, window_start)
            
            -- Get current count
            local current = redis.call("ZCARD", key)
            
            -- Check if adding cost would exceed limit
            return (current + cost) > max_attempts
        ';
        
        if ($this->store instanceof RedisStore) {
            $redis = $this->getRedisConnection();
            return (bool) $redis->eval($script, [$key, $windowStart, $maxAttempts, $cost], 1);
        }
        
        return false;
    }

    /**
     * Get usage with sorted sets
     * 
     * @param string $key
     * @return array
     */
    protected function usageWithSortedSet(string $key): array
    {
        $now = microtime(true);
        $windowStart = $now - $this->windowSize;
        
        if ($this->store instanceof RedisStore) {
            $redis = $this->getRedisConnection();
            
            // Remove old entries and get count
            $redis->zRemRangeByScore($key, 0, $windowStart);
            $current = $redis->zCard($key);
            
            $metadata = $this->store->getMetadata($key);
            $limit = $metadata['limit'] ?? $this->getDefaultLimit($key);
            
            return [
                'current' => $current,
                'limit' => $limit,
                'remaining' => max(0, $limit - $current),
                'reset_at' => (int) ceil($now + $this->windowSize),
                'window_size' => $this->windowSize,
                'algorithm' => 'sliding_window_precise',
            ];
        }
        
        return parent::usage($key);
    }

    /**
     * Approximation for non-Redis stores
     * 
     * @param string $key
     * @param int $cost
     * @return int
     */
    protected function hitWithApproximation(string $key, int $cost): int
    {
        // Use two fixed windows to approximate sliding window
        $currentWindowKey = $key . ':sw:' . $this->getCurrentWindow();
        $previousWindowKey = $key . ':sw:' . $this->getPreviousWindow();
        
        // Increment current window
        $current = $this->store->increment($currentWindowKey, $this->windowSize, $cost);
        
        // Get previous window count with weight
        $previous = $this->store->get($previousWindowKey);
        $weight = $this->getWindowWeight();
        
        // Approximate total
        return $current + (int) round($previous * $weight);
    }

    /**
     * Check with approximation
     * 
     * @param string $key
     * @param int $maxAttempts
     * @param int $cost
     * @return bool
     */
    protected function checkWithApproximation(string $key, int $maxAttempts, int $cost): bool
    {
        $currentWindowKey = $key . ':sw:' . $this->getCurrentWindow();
        $previousWindowKey = $key . ':sw:' . $this->getPreviousWindow();
        
        $current = $this->store->get($currentWindowKey);
        $previous = $this->store->get($previousWindowKey);
        $weight = $this->getWindowWeight();
        
        $total = $current + (int) round($previous * $weight);
        
        return ($total + $cost) > $maxAttempts;
    }

    /**
     * Get current window number
     * 
     * @return int
     */
    protected function getCurrentWindow(): int
    {
        return floor(time() / $this->windowSize);
    }

    /**
     * Get previous window number
     * 
     * @return int
     */
    protected function getPreviousWindow(): int
    {
        return $this->getCurrentWindow() - 1;
    }

    /**
     * Get weight for previous window (0-1)
     * 
     * @return float
     */
    protected function getWindowWeight(): float
    {
        $elapsed = time() % $this->windowSize;
        return 1 - ($elapsed / $this->windowSize);
    }

    /**
     * Get Redis connection from store
     * 
     * @return \Redis|null
     */
    protected function getRedisConnection()
    {
        if ($this->store instanceof RedisStore) {
            // This would need a getter method in RedisStore
            // For now, return null
            return null;
        }
        
        return null;
    }

    /**
     * Get window size
     * 
     * @return int
     */
    public function getWindowSize(): int
    {
        return $this->windowSize;
    }
}