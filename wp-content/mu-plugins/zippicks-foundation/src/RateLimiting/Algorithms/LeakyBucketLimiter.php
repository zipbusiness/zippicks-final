<?php

namespace ZipPicks\Foundation\RateLimiting\Algorithms;

use ZipPicks\Foundation\RateLimiting\RateLimiter;
use ZipPicks\Foundation\RateLimiting\Contracts\RateLimitStoreInterface;

/**
 * LeakyBucketLimiter - Leaky bucket algorithm for smooth rate limiting
 * 
 * Ensures a constant, smooth rate of operations.
 * Perfect for email campaigns and notifications where
 * we need to avoid overwhelming external services.
 */
class LeakyBucketLimiter extends RateLimiter
{
    /**
     * @var int Bucket capacity
     */
    protected int $capacity;

    /**
     * @var float Leak rate (requests per second)
     */
    protected float $leakRate;

    /**
     * Constructor
     * 
     * @param RateLimitStoreInterface $store
     * @param int $capacity Bucket capacity
     * @param float $leakRate Requests per second
     */
    public function __construct(
        RateLimitStoreInterface $store,
        int $capacity = 100,
        float $leakRate = 1.0
    ) {
        parent::__construct($store, 'leaky_bucket');
        $this->capacity = $capacity;
        $this->leakRate = max(0.001, $leakRate);
    }

    /**
     * {@inheritDoc}
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $cost = 1): bool
    {
        $bucketKey = $key . ':leaky';
        $bucket = $this->getBucket($bucketKey);
        
        // Check if adding this request would overflow
        return ($bucket['level'] + $cost) > $this->capacity;
    }

    /**
     * {@inheritDoc}
     */
    public function hit(string $key, int $decayMinutes = 1, int $cost = 1): int
    {
        $bucketKey = $key . ':leaky';
        $bucket = $this->addToBucket($bucketKey, $cost);
        
        return (int) $bucket['level'];
    }

    /**
     * {@inheritDoc}
     */
    public function availableIn(string $key): int
    {
        $bucketKey = $key . ':leaky';
        $bucket = $this->getBucket($bucketKey);
        
        if ($bucket['level'] < $this->capacity) {
            return -1; // Space available now
        }
        
        // Calculate time until bucket has space for 1 request
        $overflow = $bucket['level'] - ($this->capacity - 1);
        $secondsNeeded = (int) ceil($overflow / $this->leakRate);
        
        return max(0, $secondsNeeded);
    }

    /**
     * {@inheritDoc}
     */
    public function usage(string $key): array
    {
        $bucketKey = $key . ':leaky';
        $bucket = $this->getBucket($bucketKey);
        
        return [
            'current' => (int) $bucket['level'],
            'limit' => $this->capacity,
            'remaining' => max(0, $this->capacity - (int) $bucket['level']),
            'reset_at' => time() + $this->availableIn($key),
            'leak_rate' => $this->leakRate,
            'capacity' => $this->capacity,
            'algorithm' => 'leaky_bucket',
            'queue_time' => $this->getQueueTime($bucket['level']),
        ];
    }

    /**
     * Get current bucket state with leak calculation
     * 
     * @param string $key
     * @return array ['level' => float, 'last_leak' => int]
     */
    protected function getBucket(string $key): array
    {
        $metadata = $this->store->getMetadata($key);
        
        if (!$metadata) {
            return [
                'level' => 0.0,
                'last_leak' => time(),
            ];
        }
        
        // Calculate how much has leaked since last check
        $now = time();
        $elapsed = $now - $metadata['last_leak'];
        $leaked = $elapsed * $this->leakRate;
        
        // Update level (can't go below 0)
        $newLevel = max(0, $metadata['level'] - $leaked);
        
        return [
            'level' => $newLevel,
            'last_leak' => $now,
        ];
    }

    /**
     * Add to bucket with leak calculation
     * 
     * @param string $key
     * @param int $cost
     * @return array Updated bucket state
     */
    protected function addToBucket(string $key, int $cost): array
    {
        // For Redis, use Lua script for atomicity
        if ($this->store instanceof \ZipPicks\Foundation\RateLimiting\Stores\RedisStore) {
            return $this->addToBucketAtomic($key, $cost);
        }
        
        // Non-atomic implementation for other stores
        $bucket = $this->getBucket($key);
        
        // Check if request would overflow
        if ($bucket['level'] + $cost > $this->capacity) {
            // Don't add - bucket is full
            return $bucket;
        }
        
        // Add to bucket
        $bucket['level'] += $cost;
        $bucket['last_leak'] = time();
        
        // Save state
        $this->store->setMetadata($key, $bucket, 3600);
        $this->store->increment($key, 3600, $cost);
        
        return $bucket;
    }

    /**
     * Atomic add to bucket for Redis
     * 
     * @param string $key
     * @param int $cost
     * @return array
     */
    protected function addToBucketAtomic(string $key, int $cost): array
    {
        $script = '
            local key = KEYS[1]
            local meta_key = KEYS[2]
            local capacity = tonumber(ARGV[1])
            local leak_rate = tonumber(ARGV[2])
            local cost = tonumber(ARGV[3])
            local now = tonumber(ARGV[4])
            
            -- Get current bucket state
            local metadata = redis.call("GET", meta_key)
            local bucket = {}
            
            if metadata then
                bucket = cjson.decode(metadata)
            else
                bucket = {level = 0, last_leak = now}
            end
            
            -- Calculate leak
            local elapsed = now - bucket.last_leak
            local leaked = elapsed * leak_rate
            bucket.level = math.max(0, bucket.level - leaked)
            bucket.last_leak = now
            
            -- Check capacity
            if bucket.level + cost > capacity then
                -- Overflow - just update leak calculation
                redis.call("SETEX", meta_key, 3600, cjson.encode(bucket))
                return {0, bucket.level}
            end
            
            -- Add to bucket
            bucket.level = bucket.level + cost
            redis.call("SETEX", meta_key, 3600, cjson.encode(bucket))
            
            -- Update counter
            redis.call("INCRBY", key, cost)
            redis.call("EXPIRE", key, 3600)
            
            return {1, bucket.level}
        ';
        
        // Simplified implementation without direct Redis access
        $bucket = $this->getBucket($key);
        
        if ($bucket['level'] + $cost <= $this->capacity) {
            $bucket['level'] += $cost;
            $bucket['last_leak'] = time();
            $this->store->setMetadata($key, $bucket, 3600);
            $this->store->increment($key, 3600, $cost);
        }
        
        return $bucket;
    }

    /**
     * Calculate queue time for a given level
     * 
     * @param float $level Current bucket level
     * @return float Estimated seconds until empty
     */
    protected function getQueueTime(float $level): float
    {
        if ($level <= 0 || $this->leakRate <= 0) {
            return 0;
        }
        
        return $level / $this->leakRate;
    }

    /**
     * Reset bucket to empty
     * 
     * @param string $key
     * @return void
     */
    public function resetBucket(string $key): void
    {
        $bucketKey = $key . ':leaky';
        
        $this->store->reset($key);
        $this->store->setMetadata($bucketKey, [
            'level' => 0.0,
            'last_leak' => time(),
        ], 3600);
    }

    /**
     * Get bucket configuration
     * 
     * @return array
     */
    public function getConfiguration(): array
    {
        return [
            'capacity' => $this->capacity,
            'leak_rate' => $this->leakRate,
            'max_queue_time' => $this->capacity / $this->leakRate,
            'requests_per_minute' => $this->leakRate * 60,
        ];
    }

    /**
     * Set bucket capacity
     * 
     * @param int $capacity
     * @return void
     */
    public function setCapacity(int $capacity): void
    {
        $this->capacity = max(1, $capacity);
    }

    /**
     * Set leak rate
     * 
     * @param float $rate Requests per second
     * @return void
     */
    public function setLeakRate(float $rate): void
    {
        $this->leakRate = max(0.001, $rate);
    }

    /**
     * Check if bucket is currently accepting requests
     * 
     * @param string $key
     * @return bool
     */
    public function isAccepting(string $key): bool
    {
        $bucketKey = $key . ':leaky';
        $bucket = $this->getBucket($bucketKey);
        
        return $bucket['level'] < $this->capacity;
    }
}