<?php

/**
 * Rate Limiting Helper Functions
 * 
 * Convenient functions for working with rate limits throughout
 * the ZipPicks $100B platform.
 */

use ZipPicks\Foundation\RateLimiting\RateLimiterManager;
use ZipPicks\Foundation\RateLimiting\Exceptions\RateLimitExceededException;

if (!function_exists('rate_limiter')) {
    /**
     * Get the rate limiter manager instance
     * 
     * @param string|null $name
     * @return \ZipPicks\Foundation\RateLimiting\Contracts\RateLimiterInterface
     */
    function rate_limiter(?string $name = null)
    {
        $manager = app(RateLimiterManager::class);
        
        return $name ? $manager->limiter($name) : $manager->limiter();
    }
}

if (!function_exists('rate_limit')) {
    /**
     * Apply rate limiting to a callback
     * 
     * @param string $key
     * @param int $maxAttempts
     * @param callable $callback
     * @param int $decayMinutes
     * @param string|null $limiter
     * @return mixed
     * @throws RateLimitExceededException
     */
    function rate_limit(
        string $key,
        int $maxAttempts,
        callable $callback,
        int $decayMinutes = 1,
        ?string $limiter = null
    ) {
        return rate_limiter($limiter)->attempt(
            $key,
            $maxAttempts,
            $callback,
            $decayMinutes
        );
    }
}

if (!function_exists('rate_limit_for_user')) {
    /**
     * Apply rate limiting for a specific user
     * 
     * @param int $userId
     * @param string $action
     * @param int $maxAttempts
     * @param callable $callback
     * @param int $decayMinutes
     * @return mixed
     * @throws RateLimitExceededException
     */
    function rate_limit_for_user(
        int $userId,
        string $action,
        int $maxAttempts,
        callable $callback,
        int $decayMinutes = 1
    ) {
        $key = "user:{$userId}:{$action}";
        
        // Get user tier if available
        $user = get_user_by('id', $userId);
        $tier = 'free';
        
        if ($user) {
            $tier = get_user_meta($userId, 'zippicks_tier', true) ?: 'free';
        }
        
        $manager = app(RateLimiterManager::class);
        $limiter = $manager->forTier($tier, $action);
        
        return $limiter->attempt($key, $maxAttempts, $callback, $decayMinutes);
    }
}

if (!function_exists('check_rate_limit')) {
    /**
     * Check if rate limit would be exceeded
     * 
     * @param string $key
     * @param int $maxAttempts
     * @param string|null $limiter
     * @return bool
     */
    function check_rate_limit(string $key, int $maxAttempts, ?string $limiter = null): bool
    {
        return rate_limiter($limiter)->tooManyAttempts($key, $maxAttempts);
    }
}

if (!function_exists('rate_limit_usage')) {
    /**
     * Get rate limit usage statistics
     * 
     * @param string $key
     * @param string|null $limiter
     * @return array
     */
    function rate_limit_usage(string $key, ?string $limiter = null): array
    {
        return rate_limiter($limiter)->usage($key);
    }
}

if (!function_exists('clear_rate_limit')) {
    /**
     * Clear rate limit for a key
     * 
     * @param string $key
     * @param string|null $limiter
     * @return void
     */
    function clear_rate_limit(string $key, ?string $limiter = null): void
    {
        rate_limiter($limiter)->clear($key);
    }
}

if (!function_exists('rate_limit_api')) {
    /**
     * Rate limit API calls with tier support
     * 
     * @param callable $callback
     * @param int $cost
     * @return mixed
     * @throws RateLimitExceededException
     */
    function rate_limit_api(callable $callback, int $cost = 1)
    {
        $user = wp_get_current_user();
        
        if ($user && $user->ID > 0) {
            return rate_limit_for_user(
                $user->ID,
                'api',
                100, // Base limit
                $callback,
                1
            );
        }
        
        // IP-based limiting for anonymous users
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return rate_limit(
            "ip:{$ip}:api",
            60, // Lower limit for anonymous
            $callback,
            1,
            'api'
        );
    }
}

if (!function_exists('rate_limit_taste_graph')) {
    /**
     * Rate limit Taste Graph calculations
     * 
     * @param int $userId
     * @param callable $callback
     * @return mixed
     * @throws RateLimitExceededException
     */
    function rate_limit_taste_graph(int $userId, callable $callback)
    {
        return rate_limit_for_user(
            $userId,
            'taste_graph',
            10, // Base limit per hour
            $callback,
            60 // 1 hour window
        );
    }
}

if (!function_exists('rate_limit_ai_score')) {
    /**
     * Rate limit AI scoring operations
     * 
     * @param int $userId
     * @param callable $callback
     * @param int $cost
     * @return mixed
     * @throws RateLimitExceededException
     */
    function rate_limit_ai_score(int $userId, callable $callback, int $cost = 25)
    {
        $key = "user:{$userId}:ai_scores";
        
        $manager = app(RateLimiterManager::class);
        $user = get_user_by('id', $userId);
        $tier = get_user_meta($userId, 'zippicks_tier', true) ?: 'free';
        
        $limiter = $manager->forTier($tier, 'ai_scores');
        
        return $limiter->attempt(
            $key,
            5, // Base limit per hour
            $callback,
            60, // 1 hour window
            $cost
        );
    }
}

if (!function_exists('get_user_rate_limits')) {
    /**
     * Get all rate limits for a user
     * 
     * @param int $userId
     * @return array
     */
    function get_user_rate_limits(int $userId): array
    {
        $tier = get_user_meta($userId, 'zippicks_tier', true) ?: 'free';
        $manager = app(RateLimiterManager::class);
        
        $tierConfig = $manager->getTierConfig($tier);
        $limits = [];
        
        foreach (['api', 'taste_graph', 'ai_scores', 'email', 'search'] as $resource) {
            $key = "user:{$userId}:{$resource}";
            $limiter = $manager->forTier($tier, $resource);
            
            $usage = $limiter->usage($key);
            $limits[$resource] = [
                'limit' => $tierConfig['limits'][$resource] ?? 100,
                'used' => $usage['current'] ?? 0,
                'remaining' => $usage['remaining'] ?? 0,
                'reset_at' => $usage['reset_at'] ?? time(),
                'tier' => $tier,
            ];
        }
        
        return $limits;
    }
}