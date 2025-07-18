<?php
/**
 * Staging Environment Configuration
 * 
 * Pre-production testing environment that mirrors production
 * 
 * @package ZipPicks\Foundation\Config\Environments
 * @since 2.0.0
 */

return [
    /**
     * Application Configuration
     */
    'app' => [
        'debug' => true, // Enable for testing
        'url' => env('APP_URL', 'https://staging-api.zippicks.com'),
        'env' => 'staging',
        'timezone' => 'UTC',
        'maintenance' => [
            'enabled' => false,
            'allowed_ips' => explode(',', env('MAINTENANCE_ALLOWED_IPS', '')),
            'secret' => env('MAINTENANCE_SECRET')
        ],
        'performance' => [
            'opcache' => true,
            'preload' => true,
            'jit' => true
        ]
    ],

    /**
     * Database Configuration - Smaller Cluster
     */
    'database' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'read' => [
                    'hosts' => [
                        env('DB_READ_HOST_1', env('DB_HOST')),
                        env('DB_READ_HOST_2', env('DB_HOST'))
                    ],
                    'sticky' => true,
                    'weight' => [1, 1]
                ],
                'write' => [
                    'host' => env('DB_WRITE_HOST', env('DB_HOST'))
                ],
                'driver' => 'mysql',
                'database' => env('DB_DATABASE', 'zippicks_staging'),
                'username' => env('DB_USERNAME'),
                'password' => env('DB_PASSWORD'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => 'wp_',
                'strict' => true,
                'engine' => 'InnoDB'
            ]
        ],
        'pool' => [
            'min_connections' => 5,
            'max_connections' => 50,
            'idle_timeout' => 60,
            'max_lifetime' => 3600,
            'validation_interval' => 30
        ]
    ],

    /**
     * Cache Configuration - Smaller Redis Cluster
     */
    'cache' => [
        'default' => 'redis',
        'prefix' => 'zippicks_staging',
        'stores' => [
            'redis' => [
                'driver' => 'redis',
                'cluster' => true,
                'clusters' => [
                    'default' => [
                        [
                            'host' => env('REDIS_CLUSTER_1', '127.0.0.1'),
                            'port' => env('REDIS_PORT', 6379),
                            'database' => 0,
                            'password' => env('REDIS_PASSWORD')
                        ],
                        [
                            'host' => env('REDIS_CLUSTER_2', '127.0.0.1'),
                            'port' => env('REDIS_PORT', 6379),
                            'database' => 0,
                            'password' => env('REDIS_PASSWORD')
                        ]
                    ]
                ],
                'options' => [
                    'cluster' => 'redis',
                    'parameters' => [
                        'password' => env('REDIS_PASSWORD', null)
                    ]
                ]
            ]
        ]
    ],

    /**
     * Queue Configuration
     */
    'queue' => [
        'default' => 'redis',
        'connections' => [
            'redis' => [
                'driver' => 'redis',
                'connection' => 'default',
                'queue' => 'staging',
                'retry_after' => 90,
                'block_for' => 5,
                'after_commit' => true
            ]
        ],
        'failed' => [
            'driver' => 'database',
            'database' => 'mysql',
            'table' => 'wp_zippicks_failed_jobs'
        ]
    ],

    /**
     * Logging Configuration
     */
    'logging' => [
        'default' => 'stack',
        'channels' => [
            'stack' => [
                'driver' => 'stack',
                'channels' => ['daily', 'slack'],
                'ignore_exceptions' => false
            ],
            'daily' => [
                'driver' => 'daily',
                'path' => env('LOG_PATH', ZIPPICKS_FOUNDATION_PATH . '/logs/staging.log'),
                'level' => 'info',
                'days' => 14
            ],
            'slack' => [
                'driver' => 'slack',
                'url' => env('LOG_SLACK_WEBHOOK_URL'),
                'username' => 'ZipPicks Staging',
                'emoji' => ':construction:',
                'level' => 'error'
            ]
        ]
    ],

    /**
     * Security Configuration - Production-like
     */
    'security' => [
        'encryption' => [
            'key' => env('APP_KEY'),
            'cipher' => 'AES-256-GCM'
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_host' => true
        ],
        'headers' => [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'
        ],
        'cors' => [
            'allowed_origins' => [
                'https://staging.zippicks.com',
                'https://staging-app.zippicks.com'
            ],
            'allowed_headers' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
            'max_age' => 3600,
            'credentials' => true
        ]
    ],

    /**
     * API Configuration - Moderate Limits
     */
    'api' => [
        'rate_limits' => [
            'default' => 500,
            'auth' => 2000,
            'tiers' => [
                'free' => 5000,
                'starter' => 50000,
                'growth' => 250000,
                'scale' => 1000000,
                'enterprise' => PHP_INT_MAX
            ]
        ],
        'versioning' => [
            'default' => 'v1',
            'supported' => ['v1'],
            'deprecated' => [],
            'sunset_warning_days' => 30
        ],
        'response' => [
            'compression' => true,
            'compression_threshold' => 1024,
            'cache_ttl' => 60,
            'pagination' => [
                'default_limit' => 20,
                'max_limit' => 100
            ]
        ]
    ],

    /**
     * CDN Configuration
     */
    'cdn' => [
        'enabled' => true,
        'url' => env('CDN_URL', 'https://staging-cdn.zippicks.com')
    ],

    /**
     * Monitoring & Observability
     */
    'monitoring' => [
        'opentelemetry' => [
            'enabled' => true,
            'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT'),
            'headers' => ['api-key' => env('OTEL_API_KEY')],
            'traces' => [
                'sample_rate' => 0.5, // 50% sampling in staging
                'batch_size' => 256,
                'export_timeout' => 15000
            ]
        ],
        'prometheus' => [
            'enabled' => true,
            'endpoint' => '/metrics',
            'namespace' => 'zippicks_staging'
        ],
        'health_check' => [
            'endpoint' => '/health',
            'checks' => ['database', 'cache', 'queue', 'storage', 'api'],
            'cache_ttl' => 30
        ]
    ],

    /**
     * Auto-scaling Configuration
     */
    'scaling' => [
        'horizontal' => [
            'min_replicas' => 2,
            'max_replicas' => 10,
            'cpu_threshold' => 70,
            'memory_threshold' => 80,
            'requests_per_second_threshold' => 500
        ]
    ],

    /**
     * Feature Flags
     */
    'features' => [
        'api_v2' => true,
        'advanced_analytics' => true,
        'webhook_system' => true,
        'graphql_api' => false,
        'real_time_updates' => true
    ],

    /**
     * External Services - Test Mode
     */
    'services' => [
        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET')
        ],
        'sendgrid' => [
            'api_key' => env('SENDGRID_API_KEY'),
            'from_email' => 'staging@zippicks.com'
        ]
    ]
];