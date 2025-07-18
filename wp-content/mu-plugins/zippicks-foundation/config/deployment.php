<?php
/**
 * Deployment Configuration
 *
 * @package ZipPicks\Foundation
 */

return [
    /**
     * Deployment strategy
     * Options: 'blue-green', 'rolling', 'canary'
     */
    'strategy' => env('DEPLOYMENT_STRATEGY', 'blue-green'),

    /**
     * Kubernetes namespace
     */
    'namespace' => env('K8S_NAMESPACE', 'zippicks-prod'),

    /**
     * Docker registry URL
     */
    'docker_registry' => env('DOCKER_REGISTRY', '123456789.dkr.ecr.us-east-1.amazonaws.com'),

    /**
     * Maximum time to wait for deployment (seconds)
     */
    'max_wait_time' => env('DEPLOYMENT_MAX_WAIT_TIME', 600),

    /**
     * Interval between deployment checks (seconds)
     */
    'check_interval' => env('DEPLOYMENT_CHECK_INTERVAL', 10),

    /**
     * Health check configuration
     */
    'health_check' => [
        'attempts' => env('HEALTH_CHECK_ATTEMPTS', 30),
        'interval' => env('HEALTH_CHECK_INTERVAL', 5),
        'token' => env('HEALTH_CHECK_TOKEN', null),
    ],

    /**
     * Monitoring configuration
     */
    'monitoring' => [
        'interval' => env('MONITOR_INTERVAL', 10),
        'duration' => env('MONITOR_DURATION', 300),
    ],

    /**
     * Error thresholds
     */
    'thresholds' => [
        'error_rate' => env('ERROR_RATE_THRESHOLD', 5.0),
        'response_time' => env('RESPONSE_TIME_THRESHOLD', 1000),
        'cpu_usage' => env('CPU_USAGE_THRESHOLD', 80),
        'memory_usage' => env('MEMORY_USAGE_THRESHOLD', 85),
    ],

    /**
     * Canary deployment configuration
     */
    'canary' => [
        'initial_percentage' => env('CANARY_INITIAL_PERCENTAGE', 10),
        'increment' => env('CANARY_INCREMENT', 10),
        'monitor_duration' => env('CANARY_MONITOR_DURATION', 120),
        'error_threshold' => env('CANARY_ERROR_THRESHOLD', 2.0),
    ],

    /**
     * Rollback configuration
     */
    'rollback' => [
        'auto_rollback' => env('AUTO_ROLLBACK', true),
        'keep_previous' => env('KEEP_PREVIOUS_DEPLOYMENT', true),
        'cleanup_delay' => env('CLEANUP_DELAY', 3600),
    ],

    /**
     * Smoke tests to run after deployment
     */
    'smoke_tests' => [
        [
            'name' => 'Health Check',
            'endpoint' => '/health',
            'method' => 'GET',
            'expected_status' => 200,
            'max_response_time' => 1000,
        ],
        [
            'name' => 'API Health',
            'endpoint' => '/wp-json/zippicks/v1/health',
            'method' => 'GET',
            'expected_status' => 200,
            'max_response_time' => 2000,
        ],
        [
            'name' => 'Businesses Endpoint',
            'endpoint' => '/wp-json/zippicks/v1/businesses',
            'method' => 'GET',
            'expected_status' => 200,
            'max_response_time' => 3000,
        ],
        [
            'name' => 'Reviews Endpoint',
            'endpoint' => '/wp-json/zippicks/v1/reviews',
            'method' => 'GET',
            'expected_status' => 200,
            'max_response_time' => 3000,
        ],
        [
            'name' => 'Metrics Endpoint',
            'endpoint' => '/metrics',
            'method' => 'GET',
            'expected_status' => 200,
            'max_response_time' => 500,
            'options' => [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('PROMETHEUS_TOKEN', ''),
                ],
            ],
        ],
    ],

    /**
     * Notification configuration
     */
    'notifications' => [
        'slack' => [
            'enabled' => env('SLACK_NOTIFICATIONS', true),
            'webhook' => env('SLACK_WEBHOOK'),
            'channel' => env('SLACK_CHANNEL', '#deployments'),
        ],
        'email' => [
            'enabled' => env('EMAIL_NOTIFICATIONS', false),
            'recipients' => env('DEPLOYMENT_EMAIL_RECIPIENTS', 'ops@zippicks.com'),
        ],
    ],

    /**
     * Feature flags for deployment
     */
    'features' => [
        'progressive_rollout' => env('ENABLE_PROGRESSIVE_ROLLOUT', true),
        'traffic_shadowing' => env('ENABLE_TRAFFIC_SHADOWING', false),
        'automatic_scaling' => env('ENABLE_AUTO_SCALING', true),
    ],
];