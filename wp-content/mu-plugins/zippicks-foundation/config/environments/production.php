<?php
/**
 * Production Environment Configuration
 * 
 * Enterprise-grade settings for the $100B ZipPicks platform
 * Optimized for: Performance, Security, Scalability, Reliability
 * 
 * @package ZipPicks\Foundation\Config\Environments
 * @since 2.0.0
 */

return [
    /**
     * Application Configuration
     */
    'app' => [
        'debug' => false,
        'url' => env('APP_URL', 'https://api.zippicks.com'),
        'env' => 'production',
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
     * Database Configuration - Read/Write Splitting
     */
    'database' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'read' => [
                    'hosts' => [
                        env('DB_READ_HOST_1', env('DB_HOST')),
                        env('DB_READ_HOST_2', env('DB_HOST')),
                        env('DB_READ_HOST_3', env('DB_HOST'))
                    ],
                    'sticky' => true,
                    'weight' => [1, 2, 3]
                ],
                'write' => [
                    'host' => env('DB_WRITE_HOST', env('DB_HOST'))
                ],
                'driver' => 'mysql',
                'database' => env('DB_DATABASE'),
                'username' => env('DB_USERNAME'),
                'password' => env('DB_PASSWORD'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => 'wp_',
                'strict' => true,
                'engine' => 'InnoDB',
                'options' => [
                    PDO::ATTR_PERSISTENT => true,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'"
                ]
            ]
        ],
        'pool' => [
            'min_connections' => 10,
            'max_connections' => 100,
            'idle_timeout' => 60,
            'max_lifetime' => 3600,
            'validation_interval' => 30
        ]
    ],

    /**
     * Cache Configuration - Redis Cluster
     */
    'cache' => [
        'default' => 'redis',
        'prefix' => 'zippicks_prod',
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
                        ],
                        [
                            'host' => env('REDIS_CLUSTER_3', '127.0.0.1'),
                            'port' => env('REDIS_PORT', 6379),
                            'database' => 0,
                            'password' => env('REDIS_PASSWORD')
                        ]
                    ],
                    'cache' => [
                        'host' => env('REDIS_CACHE_CLUSTER', '127.0.0.1'),
                        'port' => env('REDIS_PORT', 6379),
                        'database' => 1,
                        'password' => env('REDIS_PASSWORD')
                    ],
                    'sessions' => [
                        'host' => env('REDIS_SESSION_CLUSTER', '127.0.0.1'),
                        'port' => env('REDIS_PORT', 6379),
                        'database' => 2,
                        'password' => env('REDIS_PASSWORD')
                    ]
                ],
                'options' => [
                    'cluster' => 'redis',
                    'parameters' => [
                        'password' => env('REDIS_PASSWORD', null)
                    ],
                    'persistent' => true,
                    'read_timeout' => 60,
                    'retry_interval' => 100,
                    'timeout' => 5.0
                ]
            ],
            // Memcached disabled until extension is installed
            /*
            'memcached' => [
                'driver' => 'memcached',
                'persistent_id' => 'zippicks_prod',
                'servers' => [
                    [
                        'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                        'port' => env('MEMCACHED_PORT', 11211),
                        'weight' => 100
                    ]
                ],
                'options' => [
                    // These constants require Memcached extension
                    // Memcached::OPT_COMPRESSION => true,
                    // Memcached::OPT_SERIALIZER => Memcached::SERIALIZER_PHP,
                    // Memcached::OPT_PREFIX_KEY => 'zippicks_prod'
                ]
            ]
            */
        ]
    ],

    /**
     * Queue Configuration - High Performance
     */
    'queue' => [
        'default' => 'redis',
        'connections' => [
            'redis' => [
                'driver' => 'redis',
                'connection' => 'queue',
                'queue' => 'default',
                'retry_after' => 90,
                'block_for' => 5,
                'after_commit' => true
            ],
            'high_priority' => [
                'driver' => 'redis',
                'connection' => 'queue',
                'queue' => 'high',
                'retry_after' => 60,
                'block_for' => 5
            ],
            'low_priority' => [
                'driver' => 'redis',
                'connection' => 'queue',
                'queue' => 'low',
                'retry_after' => 120,
                'block_for' => 5
            ],
            'analytics' => [
                'driver' => 'redis',
                'connection' => 'queue',
                'queue' => 'analytics',
                'retry_after' => 300,
                'block_for' => 10
            ]
        ],
        'failed' => [
            'driver' => 'database',
            'database' => 'mysql',
            'table' => 'wp_zippicks_failed_jobs'
        ],
        'batching' => [
            'database' => 'mysql',
            'table' => 'wp_zippicks_job_batches'
        ]
    ],

    /**
     * Logging Configuration - Production Grade
     */
    'logging' => [
        'default' => 'stack',
        'channels' => [
            'stack' => [
                'driver' => 'stack',
                'channels' => ['daily', 'sentry', 'datadog'],
                'ignore_exceptions' => false
            ],
            'daily' => [
                'driver' => 'daily',
                'path' => env('LOG_PATH', ZIPPICKS_FOUNDATION_PATH . '/logs/production.log'),
                'level' => 'warning',
                'days' => 30,
                'permission' => 0644
            ],
            'sentry' => [
                'driver' => 'sentry',
                'dsn' => env('SENTRY_DSN'),
                'level' => 'error',
                'bubble' => true,
                'environment' => 'production'
            ],
            'datadog' => [
                'driver' => 'monolog',
                'handler' => 'datadog',
                'level' => 'info',
                'api_key' => env('DATADOG_API_KEY')
            ],
            'slack' => [
                'driver' => 'slack',
                'url' => env('LOG_SLACK_WEBHOOK_URL'),
                'username' => 'ZipPicks Production',
                'emoji' => ':boom:',
                'level' => 'critical'
            ]
        ]
    ],

    /**
     * Security Configuration
     */
    'security' => [
        'encryption' => [
            'key' => env('APP_KEY'),
            'cipher' => 'AES-256-GCM'
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_host' => true,
            'protocol' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
        ],
        'headers' => [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Content-Security-Policy' => "default-src 'self'",
            'Referrer-Policy' => 'strict-origin-when-cross-origin'
        ],
        'cors' => [
            'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '')),
            'allowed_headers' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
            'max_age' => 86400,
            'credentials' => true
        ]
    ],

    /**
     * API Configuration
     */
    'api' => [
        'rate_limits' => [
            'default' => 1000, // per minute
            'auth' => 5000,
            'tiers' => [
                'free' => 10000, // per day
                'starter' => 100000,
                'growth' => 500000,
                'scale' => 2000000,
                'enterprise' => PHP_INT_MAX
            ]
        ],
        'versioning' => [
            'default' => 'v1',
            'supported' => ['v1'],
            'deprecated' => [],
            'sunset_warning_days' => 90
        ],
        'response' => [
            'compression' => true,
            'compression_threshold' => 1024,
            'cache_ttl' => 300,
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
        'url' => env('CDN_URL', 'https://cdn.zippicks.com'),
        'assets' => [
            'css' => env('CDN_ASSETS_CSS', 'https://assets.zippicks.com/css'),
            'js' => env('CDN_ASSETS_JS', 'https://assets.zippicks.com/js'),
            'images' => env('CDN_ASSETS_IMAGES', 'https://assets.zippicks.com/images')
        ],
        'headers' => [
            'Cache-Control' => 'public, max-age=31536000',
            'X-Content-Type-Options' => 'nosniff'
        ]
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
                'sample_rate' => 0.1, // 10% sampling in production
                'batch_size' => 512,
                'export_timeout' => 30000
            ],
            'metrics' => [
                'export_interval' => 60000,
                'timeout' => 30000
            ]
        ],
        'prometheus' => [
            'enabled' => true,
            'endpoint' => '/metrics',
            'namespace' => 'zippicks',
            'buckets' => [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
        ],
        'health_check' => [
            'endpoint' => '/health',
            'checks' => ['database', 'cache', 'queue', 'storage', 'api'],
            'cache_ttl' => 10
        ]
    ],

    /**
     * Auto-scaling Configuration
     */
    'scaling' => [
        'horizontal' => [
            'min_replicas' => 3,
            'max_replicas' => 100,
            'cpu_threshold' => 70,
            'memory_threshold' => 80,
            'requests_per_second_threshold' => 1000
        ],
        'vertical' => [
            'cpu_request' => '500m',
            'cpu_limit' => '2000m',
            'memory_request' => '1Gi',
            'memory_limit' => '4Gi'
        ]
    ],

    /**
     * Feature Flags
     */
    'features' => [
        'api_v2' => env('FEATURE_API_V2', false),
        'advanced_analytics' => env('FEATURE_ADVANCED_ANALYTICS', true),
        'webhook_system' => env('FEATURE_WEBHOOKS', true),
        'graphql_api' => env('FEATURE_GRAPHQL', false),
        'real_time_updates' => env('FEATURE_REALTIME', true)
    ],

    /**
     * External Services
     */
    'services' => [
        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET')
        ],
        'sendgrid' => [
            'api_key' => env('SENDGRID_API_KEY'),
            'from_email' => env('MAIL_FROM_ADDRESS', 'api@zippicks.com')
        ],
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from_number' => env('TWILIO_FROM_NUMBER')
        ],
        'aws' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            's3_bucket' => env('AWS_S3_BUCKET', 'zippicks-production')
        ]
    ]
];