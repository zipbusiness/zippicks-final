<?php
/**
 * Webhook Configuration
 *
 * @package ZipPicks\Foundation
 */

return [
    /**
     * Default webhook secret
     * In production, use a strong random string
     */
    'secret' => env('WEBHOOK_SECRET', wp_salt('auth')),

    /**
     * Webhook signature header name
     */
    'header_name' => env('WEBHOOK_HEADER_NAME', 'X-ZipPicks-Signature'),

    /**
     * Signature algorithm
     */
    'algorithm' => env('WEBHOOK_ALGORITHM', 'sha256'),

    /**
     * Timestamp tolerance in seconds
     * Prevents replay attacks
     */
    'timestamp_tolerance' => env('WEBHOOK_TIMESTAMP_TOLERANCE', 300),

    /**
     * Retry configuration
     */
    'retry' => [
        'attempts' => env('WEBHOOK_RETRY_ATTEMPTS', 3),
        'delay' => env('WEBHOOK_RETRY_DELAY', 1000), // milliseconds
        'backoff' => env('WEBHOOK_RETRY_BACKOFF', 'exponential'),
    ],

    /**
     * Webhook endpoints
     * Define your webhook consumers here
     */
    'endpoints' => [
        'order.created' => [
            'url' => env('WEBHOOK_ORDER_CREATED_URL'),
            'events' => ['order.created', 'order.updated'],
            'secret' => env('WEBHOOK_ORDER_SECRET'),
        ],
        'review.posted' => [
            'url' => env('WEBHOOK_REVIEW_POSTED_URL'),
            'events' => ['review.created', 'review.updated', 'review.deleted'],
            'secret' => env('WEBHOOK_REVIEW_SECRET'),
        ],
        'business.updated' => [
            'url' => env('WEBHOOK_BUSINESS_UPDATED_URL'),
            'events' => ['business.created', 'business.updated', 'business.verified'],
            'secret' => env('WEBHOOK_BUSINESS_SECRET'),
        ],
    ],

    /**
     * Webhook queue configuration
     */
    'queue' => [
        'enabled' => env('WEBHOOK_QUEUE_ENABLED', true),
        'queue_name' => env('WEBHOOK_QUEUE_NAME', 'webhooks'),
        'priority' => env('WEBHOOK_QUEUE_PRIORITY', 5),
    ],

    /**
     * SSL verification
     */
    'ssl_verify' => env('WEBHOOK_SSL_VERIFY', true),

    /**
     * Request timeout
     */
    'timeout' => env('WEBHOOK_TIMEOUT', 30),

    /**
     * Rate limiting for webhook sending
     */
    'rate_limit' => [
        'enabled' => env('WEBHOOK_RATE_LIMIT_ENABLED', true),
        'max_per_minute' => env('WEBHOOK_RATE_LIMIT_MAX', 60),
        'max_per_endpoint' => env('WEBHOOK_RATE_LIMIT_PER_ENDPOINT', 20),
    ],

    /**
     * Webhook event TTL (time to live)
     * Events older than this won't be sent
     */
    'event_ttl' => env('WEBHOOK_EVENT_TTL', 86400), // 24 hours

    /**
     * Webhook logging
     */
    'logging' => [
        'enabled' => env('WEBHOOK_LOGGING_ENABLED', true),
        'log_payload' => env('WEBHOOK_LOG_PAYLOAD', false),
        'log_response' => env('WEBHOOK_LOG_RESPONSE', true),
    ],
];