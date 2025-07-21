<?php

namespace ZipPicks\Foundation\RateLimiting;

use Closure;
use ZipPicks\Foundation\RateLimiting\Contracts\RateLimiterInterface;
use ZipPicks\Foundation\RateLimiting\Contracts\RateLimitStoreInterface;
use ZipPicks\Foundation\RateLimiting\Exceptions\RateLimitExceededException;
use ZipPicks\Foundation\Core\CircuitBreaker;

/**
 * RateLimiter - Core implementation for ZipPicks' enterprise rate limiting
 * 
 * Protects our Taste Graph calculations, Master Critic AI, and viral features
 * while enabling tiered monetization driving $100M ARR
 */
class RateLimiter implements RateLimiterInterface
{
    /**
     * @var RateLimitStoreInterface The storage backend
     */
    protected RateLimitStoreInterface $store;

    /**
     * @var string Algorithm name
     */
    protected string $algorithm;

    /**
     * @var CircuitBreaker Circuit breaker for store failures
     */
    protected CircuitBreaker $circuitBreaker;

    /**
     * @var array Default limits by resource type
     */
    protected array $defaultLimits = [
        'api' => 100,
        'taste_graph' => 10,
        'ai_scores' => 5,
        'vibe_matching' => 20,
        'email' => 50,
        'search' => 100,
    ];

    /**
     * @var array Cost units per operation type
     */
    protected array $operationCosts = [
        'api_read' => 1,
        'api_write' => 2,
        'taste_graph_calculation' => 10,
        'ai_critic_score' => 25,
        'vibe_matching' => 5,
        'email_personalized' => 3,
        'batch_operation' => 20,
    ];

    /**
     * @var bool Enable cost-based limiting
     */
    protected bool $enableCostBased = true;

    /**
     * Constructor
     * 
     * @param RateLimitStoreInterface $store
     * @param string $algorithm
     * @param CircuitBreaker|null $circuitBreaker
     */
    public function __construct(
        RateLimitStoreInterface $store,
        string $algorithm = 'fixed_window',
        ?CircuitBreaker $circuitBreaker = null
    ) {
        $this->store = $store;
        $this->algorithm = $algorithm;
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreaker('rate_limiter', 5, 60);
    }

    /**
     * {@inheritDoc}
     */
    public function attempt(
        string $key,
        int $maxAttempts,
        Closure $callback,
        int $decayMinutes = 1,
        int $cost = 1
    ) {
        if ($this->tooManyAttempts($key, $maxAttempts, $cost)) {
            $this->throwRateLimitException($key, $maxAttempts);
        }

        $result = $callback();
        
        $this->hit($key, $decayMinutes, $cost);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $cost = 1): bool
    {
        // Use circuit breaker to handle store failures gracefully
        return $this->circuitBreaker->call(function () use ($key, $maxAttempts, $cost) {
            $current = $this->store->get($key);
            
            // Apply cost-based calculation if enabled
            if ($this->enableCostBased) {
                return ($current + $cost) > $maxAttempts;
            }
            
            return $current >= $maxAttempts;
        }, function () {
            // Fail open during outages to maintain availability
            return false;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function hit(string $key, int $decayMinutes = 1, int $cost = 1): int
    {
        return $this->circuitBreaker->call(function () use ($key, $decayMinutes, $cost) {
            $decaySeconds = $decayMinutes * 60;
            return $this->store->increment($key, $decaySeconds, $cost);
        }, function () use ($cost) {
            // Return estimated count during failures
            return $cost;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function availableIn(string $key): int
    {
        return $this->circuitBreaker->call(function () use ($key) {
            $ttl = $this->store->ttl($key);
            return $ttl > 0 ? $ttl : -1;
        }, function () {
            return -1; // Available during failures
        });
    }

    /**
     * {@inheritDoc}
     */
    public function clear(string $key): void
    {
        $this->circuitBreaker->call(function () use ($key) {
            $this->store->reset($key);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function usage(string $key): array
    {
        return $this->circuitBreaker->call(function () use ($key) {
            $current = $this->store->get($key);
            $ttl = $this->store->ttl($key);
            $metadata = $this->store->getMetadata($key);
            
            $limit = $metadata['limit'] ?? $this->getDefaultLimit($key);
            $remaining = max(0, $limit - $current);
            $resetAt = $ttl > 0 ? time() + $ttl : time();
            
            return [
                'current' => $current,
                'limit' => $limit,
                'remaining' => $remaining,
                'reset_at' => $resetAt,
                'tier' => $metadata['tier'] ?? 'free',
                'cost_multiplier' => $metadata['cost_multiplier'] ?? 1.0,
            ];
        }, function () use ($key) {
            // Return safe defaults during failures
            return [
                'current' => 0,
                'limit' => $this->getDefaultLimit($key),
                'remaining' => $this->getDefaultLimit($key),
                'reset_at' => time() + 60,
                'tier' => 'free',
                'cost_multiplier' => 1.0,
            ];
        });
    }

    /**
     * {@inheritDoc}
     */
    public function setLimit(string $key, int $limit, int $decayMinutes = 1): void
    {
        $this->circuitBreaker->call(function () use ($key, $limit, $decayMinutes) {
            $metadata = [
                'limit' => $limit,
                'custom' => true,
                'updated_at' => time(),
            ];
            $this->store->setMetadata($key, $metadata, $decayMinutes * 60);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function tooManyAttemptsBatch(array $keys, int $maxAttempts): array
    {
        return $this->circuitBreaker->call(function () use ($keys, $maxAttempts) {
            $counts = $this->store->getBatch($keys);
            $results = [];
            
            foreach ($keys as $key) {
                $current = $counts[$key] ?? 0;
                $results[$key] = $current >= $maxAttempts;
            }
            
            return $results;
        }, function () use ($keys) {
            // Fail open - all keys allowed during failures
            return array_fill_keys($keys, false);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * Set operation costs for cost-based limiting
     * 
     * @param array $costs Operation => cost mapping
     * @return void
     */
    public function setOperationCosts(array $costs): void
    {
        $this->operationCosts = array_merge($this->operationCosts, $costs);
    }

    /**
     * Get cost for an operation type
     * 
     * @param string $operation
     * @return int
     */
    public function getOperationCost(string $operation): int
    {
        return $this->operationCosts[$operation] ?? 1;
    }

    /**
     * Enable or disable cost-based limiting
     * 
     * @param bool $enable
     * @return void
     */
    public function enableCostBasedLimiting(bool $enable): void
    {
        $this->enableCostBased = $enable;
    }

    /**
     * Apply tier multiplier to limits
     * 
     * @param string $key
     * @param float $multiplier
     * @param int $baseLimit
     * @return int Adjusted limit
     */
    public function applyTierMultiplier(string $key, float $multiplier, int $baseLimit): int
    {
        // Unlimited tier
        if ($multiplier === PHP_FLOAT_MAX) {
            return PHP_INT_MAX;
        }
        
        $adjusted = (int) round($baseLimit * $multiplier);
        
        // Store tier info in metadata
        $this->store->setMetadata($key, [
            'multiplier' => $multiplier,
            'base_limit' => $baseLimit,
            'adjusted_limit' => $adjusted,
        ], 3600); // 1 hour cache
        
        return $adjusted;
    }

    /**
     * Get default limit for a resource type
     * 
     * @param string $key
     * @return int
     */
    protected function getDefaultLimit(string $key): int
    {
        // Extract resource type from key (e.g., "user:123:taste_graph" => "taste_graph")
        $parts = explode(':', $key);
        $resource = end($parts);
        
        return $this->defaultLimits[$resource] ?? 100;
    }

    /**
     * Throw rate limit exceeded exception with context
     * 
     * @param string $key
     * @param int $maxAttempts
     * @throws RateLimitExceededException
     */
    protected function throwRateLimitException(string $key, int $maxAttempts): void
    {
        $usage = $this->usage($key);
        $retryAfter = $this->availableIn($key);
        
        // Determine upgrade path based on tier
        $upgradePath = $this->suggestUpgradePath($usage['tier'], $key);
        
        // Extract context from key
        $parts = explode(':', $key);
        $context = [
            'tier' => $usage['tier'],
            'resource' => end($parts),
            'cost' => $usage['cost_multiplier'] ?? 1,
            'algorithm' => $this->algorithm,
        ];
        
        throw new RateLimitExceededException(
            $key,
            $retryAfter,
            $usage,
            $upgradePath,
            $context
        );
    }

    /**
     * Suggest upgrade path based on current tier
     * 
     * @param string $currentTier
     * @param string $key
     * @return string|null
     */
    protected function suggestUpgradePath(string $currentTier, string $key): ?string
    {
        $upgradePaths = [
            'free' => 'ZipPicks Pro',
            'pro' => 'ZipPicks Business',
            'business' => 'ZipPicks Enterprise',
            'enterprise' => null, // Already at highest tier
        ];
        
        return $upgradePaths[$currentTier] ?? 'ZipPicks Pro';
    }
}