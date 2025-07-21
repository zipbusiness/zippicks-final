<?php
/**
 * Queue Configuration
 * 
 * @package ZipPicks\Foundation
 * @since 1.0.0
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection
    |--------------------------------------------------------------------------
    */
    'default' => env('QUEUE_CONNECTION', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        
        'database' => [
            'driver' => 'database',
            'table' => 'zippicks_jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ],
        
        'wordpress' => [
            'driver' => 'wordpress',
            'queue' => 'default',
            'retry_after' => 90,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    */
    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database'),
        'database' => 'wordpress',
        'table' => 'zippicks_failed_jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    */
    'worker' => [
        'sleep' => 3,
        'tries' => 3,
        'timeout' => 60,
        'max_jobs' => 1000,
        'max_time' => 3600,
    ],
];