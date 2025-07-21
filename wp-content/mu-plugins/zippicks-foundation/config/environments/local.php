<?php
/**
 * Local Environment Configuration (Quick Fix)
 * 
 * Simplified configuration without external dependencies
 * 
 * @package ZipPicks\Foundation\Config\Environments
 */

return [
    'app' => [
        'debug' => true,
        'url' => 'http://localhost',
        'env' => 'local',
        'timezone' => 'UTC',
    ],

    'database' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', 'localhost'),
                'database' => env('DB_DATABASE', 'wordpress'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => 'wp_',
            ]
        ],
    ],

    'cache' => [
        'default' => 'wordpress',
        'stores' => [
            'wordpress' => [
                'driver' => 'wordpress',
                'prefix' => 'zippicks_',
            ],
            'array' => [
                'driver' => 'array',
            ],
        ]
    ],

    'queue' => [
        'default' => 'sync',
        'connections' => [
            'sync' => [
                'driver' => 'sync',
            ],
        ],
    ],

    'logging' => [
        'default' => 'file',
        'channels' => [
            'file' => [
                'driver' => 'file',
                'path' => ZIPPICKS_FOUNDATION_PATH . '/logs/foundation.log',
                'level' => 'debug',
            ],
        ]
    ],

    'security' => [
        'encryption' => [
            'key' => 'base64:verybasiclocalkeyfordevonly1234',
            'cipher' => 'AES-256-CBC'
        ]
    ],
];