<?php
/**
 * Audit Logging Configuration
 *
 * @package ZipPicks\Foundation
 */

return [
    /**
     * Enable audit logging
     */
    'enabled' => env('AUDIT_ENABLED', true),

    /**
     * Audit log retention period in days
     */
    'retention_days' => env('AUDIT_RETENTION_DAYS', 90),

    /**
     * SIEM (Security Information and Event Management) integration
     */
    'siem' => [
        'enabled' => env('SIEM_ENABLED', false),
        'endpoint' => env('SIEM_ENDPOINT'),
        'api_key' => env('SIEM_API_KEY'),
        'timeout' => env('SIEM_TIMEOUT', 5),
    ],

    /**
     * Alert thresholds
     */
    'alerts' => [
        'failed_auth_threshold' => env('ALERT_FAILED_AUTH_THRESHOLD', 5),
        'failed_auth_window' => env('ALERT_FAILED_AUTH_WINDOW', 15), // minutes
        'rate_limit_threshold' => env('ALERT_RATE_LIMIT_THRESHOLD', 10),
        'rate_limit_window' => env('ALERT_RATE_LIMIT_WINDOW', 5), // minutes
    ],

    /**
     * Archive configuration
     */
    'archive' => [
        'enabled' => env('AUDIT_ARCHIVE_ENABLED', false),
        'storage' => env('AUDIT_ARCHIVE_STORAGE', 's3'),
        'bucket' => env('AUDIT_ARCHIVE_BUCKET', 'zippicks-audit-archive'),
        'path' => env('AUDIT_ARCHIVE_PATH', 'audit-logs/'),
        'compress' => env('AUDIT_ARCHIVE_COMPRESS', true),
    ],

    /**
     * Events to audit
     * Set to false to disable specific event types
     */
    'events' => [
        'authentication' => env('AUDIT_AUTH_EVENTS', true),
        'authorization' => env('AUDIT_AUTHZ_EVENTS', true),
        'user_management' => env('AUDIT_USER_EVENTS', true),
        'api_keys' => env('AUDIT_API_KEY_EVENTS', true),
        'configuration' => env('AUDIT_CONFIG_EVENTS', true),
        'data_access' => env('AUDIT_DATA_EVENTS', true),
        'security' => env('AUDIT_SECURITY_EVENTS', true),
        'deployment' => env('AUDIT_DEPLOYMENT_EVENTS', true),
    ],

    /**
     * Sensitive data masking
     */
    'masking' => [
        'enabled' => env('AUDIT_MASKING_ENABLED', true),
        'fields' => [
            'password',
            'api_key',
            'secret',
            'token',
            'credit_card',
            'ssn',
            'bank_account',
        ],
    ],

    /**
     * IP address anonymization
     */
    'ip_anonymization' => [
        'enabled' => env('AUDIT_IP_ANONYMIZE', false),
        'method' => env('AUDIT_IP_ANONYMIZE_METHOD', 'hash'), // hash or mask
        'mask_octets' => env('AUDIT_IP_MASK_OCTETS', 1), // Number of octets to mask
    ],

    /**
     * Performance settings
     */
    'performance' => [
        'batch_size' => env('AUDIT_BATCH_SIZE', 100),
        'async' => env('AUDIT_ASYNC', true),
        'queue' => env('AUDIT_QUEUE', 'audit'),
    ],

    /**
     * Compliance settings
     */
    'compliance' => [
        'gdpr' => env('AUDIT_GDPR_MODE', false),
        'hipaa' => env('AUDIT_HIPAA_MODE', false),
        'pci_dss' => env('AUDIT_PCI_DSS_MODE', false),
        'sox' => env('AUDIT_SOX_MODE', false),
    ],

    /**
     * Notification settings
     */
    'notifications' => [
        'email' => [
            'enabled' => env('AUDIT_EMAIL_ALERTS', false),
            'recipients' => env('AUDIT_EMAIL_RECIPIENTS', 'security@zippicks.com'),
            'critical_only' => env('AUDIT_EMAIL_CRITICAL_ONLY', true),
        ],
        'slack' => [
            'enabled' => env('AUDIT_SLACK_ALERTS', true),
            'webhook' => env('AUDIT_SLACK_WEBHOOK'),
            'channel' => env('AUDIT_SLACK_CHANNEL', '#security-alerts'),
            'critical_only' => env('AUDIT_SLACK_CRITICAL_ONLY', false),
        ],
    ],

    /**
     * Database settings
     */
    'database' => [
        'table_name' => env('AUDIT_TABLE_NAME', 'zippicks_audit_log'),
        'connection' => env('AUDIT_DB_CONNECTION', 'default'),
        'indexes' => [
            'event_type',
            'user_id',
            'created_at',
            'user_ip',
            'request_id',
        ],
    ],
];