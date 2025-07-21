<?php
/**
 * Development Environment Configuration
 * 
 * Local development settings with debugging enabled
 * 
 * @package ZipPicks\Foundation\Config\Environments
 * @since 2.0.0
 */

return [
    /**
     * Application Configuration
     */
    'app' => [
        'debug' => true,
        'url' => env('APP_URL', 'http://localhost:8000'),
        'env' => 'development',
        'timezone' => 'UTC',
        'maintenance' => [
            'enabled' => false,
            'allowed_ips' => ['127.0.0.1', '::1'],
            'secret' => 'dev-secret'
        ],
        'performance' => [
            'opcache' => false,
            'preload' => false,
            'jit' => false
        ]
    ],

    /**
     * Database Configuration - Single Instance
     */
    'database' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'zippicks_dev'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => 'wp_',
                'strict' => true,
                'engine' => null
            ]
        ],
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'idle_timeout' => 60,
            'max_lifetime' => 3600,
            'validation_interval' => 60
        ]
    ],

    /**
     * Cache Configuration - Simple Redis
     */
    'cache' => [
        'default' => 'redis',
        'prefix' => 'zippicks_dev',
        'stores' => [
            'redis' => [
                'driver' => 'redis',
                'connection' => [
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'password' => env('REDIS_PASSWORD', null),
                    'port' => env('REDIS_PORT', 6379),
                    'database' => env('REDIS_DB', 0)
                ]
            ],
            'array' => [
                'driver' => 'array',
                'serialize' => false
            ],
            'file' => [
                'driver' => 'file',
                'path' => ZIPPICKS_FOUNDATION_PATH . '/cache'
            ]
        ]
    ],

    /**
     * Queue Configuration - Sync for Development
     */
    'queue' => [
        'default' => env('QUEUE_CONNECTION', 'sync'),
        'connections' => [
            'sync' => [
                'driver' => 'sync'
            ],
            'redis' => [
                'driver' => 'redis',
                'connection' => 'default',
                'queue' => 'dev_queue',
                'retry_after' => 90,
                'block_for' => null,
                'after_commit' => false
            ]
        ],
        'failed' => [
            'driver' => 'database',
            'database' => 'mysql',
            'table' => 'wp_zippicks_failed_jobs'
        ]
    ],

    /**
     * Logging Configuration - Verbose for Development
     */
    'logging' => [
        'default' => 'stack',
        'channels' => [
            'stack' => [
                'driver' => 'stack',
                'channels' => ['single', 'console'],
                'ignore_exceptions' => false
            ],
            'single' => [
                'driver' => 'single',
                'path' => ZIPPICKS_FOUNDATION_PATH . '/logs/development.log',
                'level' => 'debug',
                'permission' => 0666
            ],
            'console' => [
                'driver' => 'monolog',
                'handler' => 'stream',
                'formatter' => 'line',
                'with' => [
                    'stream' => 'php://stdout'
                ],
                'level' => 'debug'
            ]
        ]
    ],

    /**
     * Security Configuration - Relaxed for Development
     */
    'security' => [
        'encryption' => [
            'key' => env('APP_KEY', 'base64:'.base64_encode('dev-key-32-characters-long!!!!!!!')),
            'cipher' => 'AES-256-GCM'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_host' => false
        ],
        'headers' => [
            'X-Frame-Options' => 'SAMEORIGIN'
        ],
        'cors' => [
            'allowed_origins' => ['*'],
            'allowed_headers' => ['*'],
            'allowed_methods' => ['*'],
            'exposed_headers' => ['*'],
            'max_age' => 0,
            'credentials' => true
        ]
    ],

    /**
     * API Configuration - Generous Limits
     */
    'api' => [
        'rate_limits' => [
            'default' => 10000,
            'auth' => 50000,
            'tiers' => [
                'free' => PHP_INT_MAX,
                'starter' => PHP_INT_MAX,
                'growth' => PHP_INT_MAX,
                'scale' => PHP_INT_MAX,
                'enterprise' => PHP_INT_MAX
            ]
        ],
        'versioning' => [
            'default' => 'v1',
            'supported' => ['v1'],
            'deprecated' => [],
            'sunset_warning_days' => 365
        ],
        'response' => [
            'compression' => false,
            'cache_ttl' => 0,
            'pagination' => [
                'default_limit' => 20,
                'max_limit' => 1000
            ]
        ]
    ],

    /**
     * CDN Configuration - Disabled
     */
    'cdn' => [
        'enabled' => false,
        'url' => env('APP_URL', 'http://localhost:8000')
    ],

    /**
     * Monitoring & Observability
     */
    'monitoring' => [
        'opentelemetry' => [
            'enabled' => false,
            'traces' => [
                'sample_rate' => 1.0, // 100% sampling in dev
                'batch_size' => 1,
                'export_timeout' => 5000
            ]
        ],
        'prometheus' => [
            'enabled' => true,
            'endpoint' => '/metrics',
            'namespace' => 'zippicks_dev'
        ],
        'health_check' => [
            'endpoint' => '/health',
            'checks' => ['database', 'cache'],
            'cache_ttl' => 0
        ]
    ],

    /**
     * Feature Flags - All Enabled
     */
    'features' => [
        'api_v2' => true,
        'advanced_analytics' => true,
        'webhook_system' => true,
        'graphql_api' => true,
        'real_time_updates' => true
    ],

    /**
     * External Services - Test Credentials
     */
    'services' => [
        'stripe' => [
            'key' => env('STRIPE_KEY', 'pk_test_xxx'),
            'secret' => env('STRIPE_SECRET', 'sk_test_xxx'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', 'whsec_test_xxx')
        ],
        'sendgrid' => [
            'api_key' => env('SENDGRID_API_KEY', 'test-key'),
            'from_email' => 'test@localhost'
        ]
    ]
];