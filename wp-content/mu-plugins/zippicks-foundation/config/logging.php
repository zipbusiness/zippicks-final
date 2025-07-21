<?php
/**
 * Logging Configuration
 * 
 * @package ZipPicks\Foundation
 * @since 1.0.0
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    */
    'default' => env('LOG_CHANNEL', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'main' => [
            'driver' => 'file',
            'path' => ZIPPICKS_FOUNDATION_PATH . '/logs/foundation.log',
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 14,
        ],
        
        'error' => [
            'driver' => 'file',
            'path' => ZIPPICKS_FOUNDATION_PATH . '/logs/error.log',
            'level' => 'error',
            'days' => 30,
        ],
        
        'debug' => [
            'driver' => 'file',
            'path' => ZIPPICKS_FOUNDATION_PATH . '/logs/debug.log',
            'level' => 'debug',
            'days' => 7,
        ],
        
        'auth' => [
            'driver' => 'file',
            'path' => ZIPPICKS_FOUNDATION_PATH . '/logs/auth.log',
            'level' => 'info',
            'days' => 30,
        ],
        
        'validation' => [
            'driver' => 'file',
            'path' => ZIPPICKS_FOUNDATION_PATH . '/logs/validation.log',
            'level' => 'notice',
            'days' => 7,
        ],
        
        'performance' => [
            'driver' => 'file',
            'path' => ZIPPICKS_FOUNDATION_PATH . '/logs/performance.log',
            'level' => 'info',
            'days' => 7,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Formatting
    |--------------------------------------------------------------------------
    */
    'format' => [
        'date_format' => 'Y-m-d H:i:s',
        'output' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
    ],
];