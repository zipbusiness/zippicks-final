<?php
/**
 * Default Settings Configuration
 * 
 * @package ZipPicks\Foundation
 * @since 1.0.0
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'enable_logging' => true,
    'log_level' => 'debug',
    'log_channel' => 'default',
    
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'driver' => 'file',
        'ttl' => 3600,
        'prefix' => 'zippicks_',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api' => [
        'timeout' => 30,
        'retry_attempts' => 3,
        'rate_limit' => 100,
        'version' => 'v1',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'taste_graph' => true,
        'ai_recommendations' => false,
        'social_sharing' => true,
        'advanced_search' => false,
        'real_time_updates' => false,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'query_cache_enabled' => true,
        'lazy_loading' => true,
        'minify_assets' => false,
        'cdn_enabled' => false,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        'csrf_protection' => true,
        'rate_limiting' => true,
        'encryption_key' => null,
        'allowed_origins' => ['*'],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Database Settings
    |--------------------------------------------------------------------------
    */
    'database' => [
        'connection_timeout' => 10,
        'query_logging' => false,
        'slow_query_threshold' => 1000, // milliseconds
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Email Configuration
    |--------------------------------------------------------------------------
    */
    'email' => [
        'enabled' => true,
        'from_address' => 'noreply@zippicks.com',
        'from_name' => 'ZipPicks',
        'queue_emails' => false,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Taste Graph Settings
    |--------------------------------------------------------------------------
    */
    'taste_graph' => [
        'min_interactions' => 5,
        'decay_factor' => 0.9,
        'similarity_threshold' => 0.7,
        'max_recommendations' => 20,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Business Settings
    |--------------------------------------------------------------------------
    */
    'business' => [
        'auto_approve' => false,
        'require_verification' => true,
        'max_photos' => 10,
        'review_moderation' => true,
    ],
];