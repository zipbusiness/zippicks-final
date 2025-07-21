<?php
/**
 * Authentication Configuration
 * 
 * @package ZipPicks\Foundation
 * @since 1.0.0
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'guard' => 'wordpress',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */
    'guards' => [
        'wordpress' => [
            'driver' => 'wordpress',
            'provider' => 'wordpress',
        ],
        
        'api' => [
            'driver' => 'token',
            'provider' => 'wordpress',
            'storage_key' => 'api_token',
        ],
        
        'session' => [
            'driver' => 'session',
            'provider' => 'wordpress',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'wordpress' => [
            'driver' => 'wordpress',
            'model' => \ZipPicks\Foundation\Models\User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset Configuration
    |--------------------------------------------------------------------------
    */
    'passwords' => [
        'users' => [
            'provider' => 'wordpress',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */
    'password_timeout' => 10800,
];