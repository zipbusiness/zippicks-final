<?php

namespace ZipPicks\Foundation\RateLimiting\Contracts;

/**
 * ThrottleableInterface - For entities that can be rate limited
 * 
 * Implements tier-based limiting for our three user types:
 * - Zippers (free/pro tiers)
 * - Critics (verified status multipliers)  
 * - Business Owners (subscription levels)
 */
interface ThrottleableInterface
{
    /**
     * Get the unique throttle key for this entity
     * 
     * @param string $action The action being limited (e.g., 'taste_graph', 'api_call')
     * @return string Unique key like "user:123:taste_graph"
     */
    public function getThrottleKey(string $action = ''): string;

    /**
     * Get rate limit multiplier based on tier/status
     * 
     * @return float Multiplier (e.g., 1.0 for free, 10.0 for pro, unlimited for enterprise)
     */
    public function getRateLimitMultiplier(): float;

    /**
     * Get custom rate limits for this entity
     * 
     * @return array ['api' => 1000, 'taste_graph' => 100, 'ai_scores' => 50]
     */
    public function getCustomRateLimits(): array;

    /**
     * Check if entity has unlimited access
     * 
     * @param string $resource The resource to check
     * @return bool True if unlimited
     */
    public function hasUnlimitedAccess(string $resource = ''): bool;

    /**
     * Get cost multiplier for operations (enterprise discounts)
     * 
     * @return float Cost multiplier (e.g., 0.5 for 50% discount)
     */
    public function getCostMultiplier(): float;

    /**
     * Handle rate limit exceeded event
     * 
     * @param string $action The action that was limited
     * @param array $context Additional context
     * @return void
     */
    public function onRateLimitExceeded(string $action, array $context = []): void;

    /**
     * Get tier identifier for analytics
     * 
     * @return string Tier name (free, pro, business, enterprise)
     */
    public function getRateLimitTier(): string;
}