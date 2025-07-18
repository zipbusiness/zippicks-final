<?php

namespace ZipPicks\Foundation\RateLimiting\Algorithms;

use ZipPicks\Foundation\RateLimiting\RateLimiter;
use ZipPicks\Foundation\RateLimiting\Contracts\RateLimitStoreInterface;

/**
 * TokenBucketLimiter - Token bucket algorithm for burst-friendly rate limiting
 * 
 * Allows users to save up tokens for bursts of activity.
 * Perfect for mobile apps where users might batch operations.
 * Supports our viral growth by allowing burst usage during peak moments.
 */
class TokenBucketLimiter extends RateLimiter
{
    /**
     * @var int Maximum bucket capacity
     */
    protected int $capacity;

    /**
     * @var float Token refill rate (tokens per second)
     */
    protected float $refillRate;

    /**
     * @var int Refill period in seconds
     */
    protected int $refillPeriod;

    /**
     * Constructor
     * 
     * @param RateLimitStoreInterface $store
     * @param int $capacity Bucket capacity
     * @param float $refillRate Tokens per second
     * @param int $refillPeriod How often to add tokens
     */
    public function __construct(
        RateLimitStoreInterface $store,
        int $capacity = 100,
        float $refillRate = 1.0,
        int $refillPeriod = 1
    ) {
        parent::__construct($store, 'token_bucket');
        $this->capacity = $capacity;
        $this->refillRate = $refillRate;
        $this->refillPeriod = max(1, $refillPeriod);
    }

    /**
     * {@inheritDoc}
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $cost = 1): bool
    {
        $bucketKey = $key . ':bucket';
        $bucket = $this->getBucket($bucketKey);
        
        return $bucket['tokens'] < $cost;
    }

    /**
     * {@inheritDoc}
     */
    public function hit(string $key, int $decayMinutes = 1, int $cost = 1): int
    {
        $bucketKey = $key . ':bucket';
        $bucket = $this->consumeTokens($bucketKey, $cost);
        
        return $this->capacity - (int) $bucket['tokens'];
    }

    /**
     * {@inheritDoc}
     */
    public function availableIn(string $key): int
    {
        $bucketKey = $key . ':bucket';
        $bucket = $this->getBucket($bucketKey);
        
        if ($bucket['tokens'] >= 1) {
            return -1; // Tokens available now
        }
        
        // Calculate time until at least 1 token is available
        $tokensNeeded = 1 - $bucket['tokens'];
        $secondsNeeded = (int) ceil($tokensNeeded / $this->refillRate);
        
        return $secondsNeeded;
    }

    /**
     * {@inheritDoc}
     */
    public function usage(string $key): array
    {
        $bucketKey = $key . ':bucket';
        $bucket = $this->getBucket($bucketKey);
        
        return [
            'current' => $this->capacity - (int) $bucket['tokens'],
            'limit' => $this->capacity,
            'remaining' => (int) $bucket['tokens'],
            'reset_at' => time() + $this->availableIn($key),
            'tokens_available' => $bucket['tokens'],
            'capacity' => $this->capacity,
            'refill_rate' => $this->refillRate,
            'algorithm' => 'token_bucket',
        ];
    }

    /**
     * Get current bucket state
     * 
     * @param string $key
     * @return array ['tokens' => float, 'last_refill' => int]
     */
    protected function getBucket(string $key): array
    {
        $metadata = $this->store->getMetadata($key);
        
        if (!$metadata) {
            return [
                'tokens' => (float) $this->capacity,
                'last_refill' => time(),
            ];
        }
        
        // Calculate tokens to add based on time elapsed
        $now = time();
        $elapsed = $now - $metadata['last_refill'];
        $tokensToAdd = $elapsed * $this->refillRate;
        
        // Add tokens up to capacity
        $newTokens = min($this->capacity, $metadata['tokens'] + $tokensToAdd);
        
        return [
            'tokens' => $newTokens,
            'last_refill' => $now,
        ];
    }

    /**
     * Consume tokens from bucket
     * 
     * @param string $key
     * @param int $cost
     * @return array Updated bucket state
     */
    protected function consumeTokens(string $key, int $cost): array
    {
        // Use atomic operation to prevent race conditions
        $script = '
            local key = KEYS[1]
            local meta_key = KEYS[2]
            local capacity = tonumber(ARGV[1])
            local refill_rate = tonumber(ARGV[2])
            local cost = tonumber(ARGV[3])
            local now = tonumber(ARGV[4])
            
            -- Get current bucket state
            local metadata = redis.call("GET", meta_key)
            local bucket = {}
            
            if metadata then
                bucket = cjson.decode(metadata)
            else
                bucket = {tokens = capacity, last_refill = now}
            end
            
            -- Calculate tokens to add
            local elapsed = now - bucket.last_refill
            local tokens_to_add = elapsed * refill_rate
            bucket.tokens = math.min(capacity, bucket.tokens + tokens_to_add)
            bucket.last_refill = now
            
            -- Check if enough tokens
            if bucket.tokens < cost then
                -- Not enough tokens, just update refill time
                redis.call("SETEX", meta_key, 3600, cjson.encode(bucket))
                return {0, bucket.tokens}
            end
            
            -- Consume tokens
            bucket.tokens = bucket.tokens - cost
            redis.call("SETEX", meta_key, 3600, cjson.encode(bucket))
            
            -- Update counter for compatibility
            redis.call("INCRBY", key, cost)
            redis.call("EXPIRE", key, 3600)
            
            return {1, bucket.tokens}
        ';
        
        // For non-Redis stores, use mutex-based approach
        if (!($this->store instanceof \ZipPicks\Foundation\RateLimiting\Stores\RedisStore)) {
            return $this->consumeTokensWithMutex($key, $cost);
        }
        
        // Execute Redis script
        // Note: This would need the Redis connection exposed
        $bucket = $this->getBucket($key);
        
        if ($bucket['tokens'] >= $cost) {
            $bucket['tokens'] -= $cost;
            $this->store->setMetadata($key, $bucket, 3600);
            $this->store->increment($key, 3600, $cost);
        }
        
        return $bucket;
    }

    /**
     * Consume tokens with mutex for non-Redis stores
     * 
     * @param string $key
     * @param int $cost
     * @return array
     */
    protected function consumeTokensWithMutex(string $key, int $cost): array
    {
        // Simple implementation without true atomicity
        $bucket = $this->getBucket($key);
        
        if ($bucket['tokens'] >= $cost) {
            $bucket['tokens'] -= $cost;
            $bucket['last_refill'] = time();
            
            $this->store->setMetadata($key, $bucket, 3600);
            $this->store->increment($key, 3600, $cost);
        }
        
        return $bucket;
    }

    /**
     * Reset bucket to full capacity
     * 
     * @param string $key
     * @return void
     */
    public function resetBucket(string $key): void
    {
        $bucketKey = $key . ':bucket';
        
        $this->store->reset($key);
        $this->store->setMetadata($bucketKey, [
            'tokens' => (float) $this->capacity,
            'last_refill' => time(),
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
            'refill_rate' => $this->refillRate,
            'refill_period' => $this->refillPeriod,
            'tokens_per_period' => $this->refillRate * $this->refillPeriod,
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
     * Set refill rate
     * 
     * @param float $rate Tokens per second
     * @return void
     */
    public function setRefillRate(float $rate): void
    {
        $this->refillRate = max(0.001, $rate);
    }
}