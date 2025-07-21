<?php
/**
 * Cache Configuration
 * 
 * @package ZipPicks\Foundation
 * @since 1.0.0
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    */
    'default' => env('CACHE_DRIVER', 'wordpress'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    */
    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
            'max_size' => 1000,
        ],
        
        'wordpress' => [
            'driver' => 'wordpress',
            'prefix' => 'zippicks_cache_',
        ],
        
        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_CACHE_DB', 0),
            'prefix' => 'zippicks_cache_',
        ],
        
        'database' => [
            'driver' => 'database',
            'table' => 'zippicks_cache',
            'prefix' => 'zippicks_',
        ],
        
        'file' => [
            'driver' => 'file',
            'path' => ZIPPICKS_FOUNDATION_PATH . '/storage/cache',
            'prefix' => 'zippicks',
        ],
        
        'null' => [
            'driver' => 'null',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tier Cache Configuration
    |--------------------------------------------------------------------------
    */
    'multi_tier' => [
        'enabled' => env('CACHE_MULTI_TIER', true),
        'tiers' => ['array', 'wordpress', 'redis', 'database'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => env('CACHE_PREFIX', 'zippicks_foundation'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    */
    'ttl' => [
        'default' => 3600,
        'short' => 300,
        'medium' => 1800,
        'long' => 7200,
        'day' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Warming
    |--------------------------------------------------------------------------
    */
    'warm' => [
        'enabled' => env('CACHE_WARMING', false),
        'keys' => [
            // Add keys to warm on boot
        ],
    ],
];