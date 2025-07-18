<?php

namespace ZipPicks\Foundation\RateLimiting;

use Closure;
use ZipPicks\Foundation\RateLimiting\Contracts\RateLimiterInterface;

/**
 * TierAwareRateLimiter - Wrapper that applies tier-based multipliers
 * 
 * Enables our $100M monetization strategy by dynamically adjusting
 * rate limits based on user tiers (Free, Pro, Business, Enterprise)
 */
class TierAwareRateLimiter implements RateLimiterInterface
{
    /**
     * @var RateLimiterInterface The base rate limiter
     */
    protected RateLimiterInterface $baseLimiter;

    /**
     * @var array Tier configuration
     */
    protected array $tierConfig;

    /**
     * Constructor
     * 
     * @param RateLimiterInterface $baseLimiter
     * @param array $tierConfig
     */
    public function __construct(RateLimiterInterface $baseLimiter, array $tierConfig)
    {
        $this->baseLimiter = $baseLimiter;
        $this->tierConfig = $tierConfig;
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
        $adjustedLimit = $this->applyTierLimit($key, $maxAttempts);
        $adjustedCost = $this->applyTierCost($cost);
        
        return $this->baseLimiter->attempt(
            $key,
            $adjustedLimit,
            $callback,
            $decayMinutes,
            $adjustedCost
        );
    }

    /**
     * {@inheritDoc}
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $cost = 1): bool
    {
        $adjustedLimit = $this->applyTierLimit($key, $maxAttempts);
        $adjustedCost = $this->applyTierCost($cost);
        
        return $this->baseLimiter->tooManyAttempts($key, $adjustedLimit, $adjustedCost);
    }

    /**
     * {@inheritDoc}
     */
    public function hit(string $key, int $decayMinutes = 1, int $cost = 1): int
    {
        $adjustedCost = $this->applyTierCost($cost);
        return $this->baseLimiter->hit($key, $decayMinutes, $adjustedCost);
    }

    /**
     * {@inheritDoc}
     */
    public function availableIn(string $key): int
    {
        return $this->baseLimiter->availableIn($key);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(string $key): void
    {
        $this->baseLimiter->clear($key);
    }

    /**
     * {@inheritDoc}
     */
    public function usage(string $key): array
    {
        $usage = $this->baseLimiter->usage($key);
        
        // Adjust usage stats based on tier
        if (isset($usage['limit'])) {
            $usage['limit'] = $this->applyTierLimit($key, $usage['limit']);
            $usage['remaining'] = max(0, $usage['limit'] - $usage['current']);
        }
        
        // Add tier information
        $usage['tier'] = $this->tierConfig['name'] ?? 'custom';
        $usage['multiplier'] = $this->tierConfig['multiplier'] ?? 1.0;
        $usage['cost_multiplier'] = $this->tierConfig['cost_multiplier'] ?? 1.0;
        
        return $usage;
    }

    /**
     * {@inheritDoc}
     */
    public function setLimit(string $key, int $limit, int $decayMinutes = 1): void
    {
        // Apply tier multiplier to custom limits too
        $adjustedLimit = $this->applyTierLimit($key, $limit);
        $this->baseLimiter->setLimit($key, $adjustedLimit, $decayMinutes);
    }

    /**
     * {@inheritDoc}
     */
    public function tooManyAttemptsBatch(array $keys, int $maxAttempts): array
    {
        $adjustedLimit = $this->applyTierLimit('', $maxAttempts);
        return $this->baseLimiter->tooManyAttemptsBatch($keys, $adjustedLimit);
    }

    /**
     * {@inheritDoc}
     */
    public function getAlgorithm(): string
    {
        return $this->baseLimiter->getAlgorithm() . '_tier_aware';
    }

    /**
     * Apply tier multiplier to limit
     * 
     * @param string $key
     * @param int $baseLimit
     * @return int
     */
    protected function applyTierLimit(string $key, int $baseLimit): int
    {
        $multiplier = $this->tierConfig['multiplier'] ?? 1.0;
        
        // Unlimited for enterprise tier
        if ($multiplier === PHP_FLOAT_MAX) {
            return PHP_INT_MAX;
        }
        
        // Check for resource-specific limits
        $resource = $this->extractResource($key);
        if ($resource && isset($this->tierConfig['limits'][$resource])) {
            return $this->tierConfig['limits'][$resource];
        }
        
        return (int) round($baseLimit * $multiplier);
    }

    /**
     * Apply tier cost multiplier
     * 
     * @param int $baseCost
     * @return int
     */
    protected function applyTierCost(int $baseCost): int
    {
        $costMultiplier = $this->tierConfig['cost_multiplier'] ?? 1.0;
        return max(1, (int) round($baseCost * $costMultiplier));
    }

    /**
     * Extract resource type from key
     * 
     * @param string $key
     * @return string|null
     */
    protected function extractResource(string $key): ?string
    {
        if (empty($key)) {
            return null;
        }
        
        $parts = explode(':', $key);
        return end($parts) ?: null;
    }

    /**
     * Get tier configuration
     * 
     * @return array
     */
    public function getTierConfig(): array
    {
        return $this->tierConfig;
    }

    /**
     * Check if this tier has unlimited access
     * 
     * @param string $resource Optional specific resource
     * @return bool
     */
    public function hasUnlimitedAccess(string $resource = ''): bool
    {
        if ($this->tierConfig['multiplier'] === PHP_FLOAT_MAX) {
            return true;
        }
        
        if ($resource && isset($this->tierConfig['limits'][$resource])) {
            return $this->tierConfig['limits'][$resource] === PHP_INT_MAX;
        }
        
        return false;
    }
}