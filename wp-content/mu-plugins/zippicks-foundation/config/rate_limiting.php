<?php

/**
 * Rate Limiting Configuration
 * 
 * Configure rate limiting for the ZipPicks $100B platform.
 * Protects resources while enabling tier-based monetization.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Rate Limiter
    |--------------------------------------------------------------------------
    |
    | This option controls the default rate limiter that will be used by the
    | framework when no specific limiter is specified. This should match one
    | of the limiters defined in the "limiters" configuration array below.
    |
    */
    'default' => env('RATE_LIMITER', 'api'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiters
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the rate limiters for your application.
    | Each limiter can use different algorithms and stores based on your
    | specific requirements for that type of rate limiting.
    |
    */
    'limiters' => [
        // API rate limiting - sliding window for accuracy
        'api' => [
            'algorithm' => 'sliding_window',
            'store' => 'redis',
            'window' => 60, // 1 minute
        ],

        // Taste Graph calculations - token bucket for burst support
        'taste_graph' => [
            'algorithm' => 'token_bucket',
            'store' => 'redis',
            'capacity' => 100,
            'refill_rate' => 1.67, // 100 tokens per minute
            'refill_period' => 1,
        ],

        // AI scoring - fixed window with high cost
        'ai_scores' => [
            'algorithm' => 'fixed_window',
            'store' => 'redis',
            'window' => 3600, // 1 hour
        ],

        // Email sending - leaky bucket for smooth delivery
        'email' => [
            'algorithm' => 'leaky_bucket',
            'store' => 'redis',
            'capacity' => 1000,
            'leak_rate' => 16.67, // 1000 per minute
        ],

        // Search queries - sliding window
        'search' => [
            'algorithm' => 'sliding_window',
            'store' => 'redis',
            'window' => 60,
        ],

        // Mobile app - token bucket for burst traffic
        'mobile' => [
            'algorithm' => 'token_bucket',
            'store' => 'redis',
            'capacity' => 500,
            'refill_rate' => 8.33, // 500 per minute
            'refill_period' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the storage backends for rate limiting.
    | Redis is recommended for production due to its atomic operations and
    | performance. Database and memory stores are available as fallbacks.
    |
    */
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => env('RATE_LIMIT_REDIS_CONNECTION', 'default'),
            'prefix' => 'rate_limit:',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'wp_zippicks_rate_limits',
            'prefix' => '',
        ],

        'memory' => [
            'driver' => 'memory',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Tier Configuration
    |--------------------------------------------------------------------------
    |
    | Define the rate limit multipliers and custom limits for each user tier.
    | This drives our monetization strategy by providing clear value
    | propositions for upgrading to higher tiers.
    |
    */
    'tiers' => [
        'free' => [
            'multiplier' => 1.0,
            'cost_multiplier' => 1.0,
            'limits' => [
                'api' => 100,         // per minute
                'taste_graph' => 10,  // per hour
                'ai_scores' => 5,     // per hour
                'email' => 50,        // per hour
                'search' => 100,      // per minute
            ],
        ],

        'pro' => [
            'multiplier' => 10.0,
            'cost_multiplier' => 0.8, // 20% discount on costs
            'limits' => [
                'api' => 10000,
                'taste_graph' => 1000,
                'ai_scores' => 500,
                'email' => 5000,
                'search' => 1000,
            ],
        ],

        'business' => [
            'multiplier' => 50.0,
            'cost_multiplier' => 0.5, // 50% discount on costs
            'limits' => [
                'api' => 50000,
                'taste_graph' => 5000,
                'ai_scores' => 2000,
                'email' => 25000,
                'search' => 5000,
            ],
        ],

        'enterprise' => [
            'multiplier' => PHP_FLOAT_MAX, // Unlimited
            'cost_multiplier' => 0.1, // 90% discount on costs
            'limits' => [], // All unlimited
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Operation Costs
    |--------------------------------------------------------------------------
    |
    | Define the cost in "units" for different operations. This enables
    | cost-based rate limiting where expensive operations consume more
    | of the user's quota than simple operations.
    |
    */
    'costs' => [
        'api_read' => 1,
        'api_write' => 2,
        'taste_graph_calculation' => 10,
        'ai_critic_score' => 25,
        'ai_batch_score' => 100,
        'vibe_matching' => 5,
        'email_personalized' => 3,
        'email_bulk' => 1,
        'search_simple' => 1,
        'search_complex' => 5,
        'batch_operation' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Headers
    |--------------------------------------------------------------------------
    |
    | Control which rate limit information is exposed in HTTP headers.
    | These headers help developers understand their usage and limits.
    |
    */
    'headers' => [
        'enabled' => true,
        'include_tier' => true,
        'include_cost' => true,
        'include_upgrade_path' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the circuit breaker that protects the rate limiting system
    | itself from failures. This ensures the application remains available
    | even if the rate limit store becomes unavailable.
    |
    */
    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 5,
        'recovery_time' => 60, // seconds
        'fail_open' => true, // Allow requests when circuit is open
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic cleanup of expired rate limit entries to prevent
    | the storage from growing indefinitely.
    |
    */
    'cleanup' => [
        'enabled' => true,
        'probability' => 100, // 1 in X requests triggers cleanup
        'batch_size' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tracking of rate limit events for analytics and monitoring.
    | This data helps identify upgrade opportunities and usage patterns.
    |
    */
    'analytics' => [
        'track_exceeded' => true,
        'track_near_limit' => true,
        'near_limit_threshold' => 0.8, // 80% of limit
        'track_tier_changes' => true,
    ],
];