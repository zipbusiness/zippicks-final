<?php
/**
 * Audit Logger for Security and Compliance
 *
 * @package ZipPicks\Foundation\Audit
 */

namespace ZipPicks\Foundation\Audit;

use ZipPicks\Foundation\Contracts\Audit\AuditLoggerInterface;
use ZipPicks\Foundation\Logging\LoggerInterface;
use ZipPicks\Foundation\Observability\OpenTelemetryService;

/**
 * Comprehensive audit logging for security events and compliance
 */
class AuditLogger implements AuditLoggerInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OpenTelemetryService
     */
    protected $telemetry;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $tableName = 'zippicks_audit_log';

    /**
     * Event types
     */
    const EVENT_AUTH_SUCCESS = 'auth.success';
    const EVENT_AUTH_FAILURE = 'auth.failure';
    const EVENT_API_KEY_CREATED = 'api_key.created';
    const EVENT_API_KEY_DELETED = 'api_key.deleted';
    const EVENT_API_KEY_ROTATED = 'api_key.rotated';
    const EVENT_PERMISSION_GRANTED = 'permission.granted';
    const EVENT_PERMISSION_REVOKED = 'permission.revoked';
    const EVENT_DATA_EXPORT = 'data.export';
    const EVENT_DATA_IMPORT = 'data.import';
    const EVENT_CONFIG_CHANGE = 'config.change';
    const EVENT_RATE_LIMIT_EXCEEDED = 'rate_limit.exceeded';
    const EVENT_SECURITY_ALERT = 'security.alert';
    const EVENT_WEBHOOK_SENT = 'webhook.sent';
    const EVENT_WEBHOOK_FAILED = 'webhook.failed';
    const EVENT_DEPLOYMENT_STARTED = 'deployment.started';
    const EVENT_DEPLOYMENT_COMPLETED = 'deployment.completed';
    const EVENT_DEPLOYMENT_FAILED = 'deployment.failed';

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param OpenTelemetryService $telemetry
     * @param array $config
     */
    public function __construct(
        LoggerInterface $logger,
        OpenTelemetryService $telemetry,
        array $config = []
    ) {
        $this->logger = $logger;
        $this->telemetry = $telemetry;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        $this->ensureTableExists();
    }

    /**
     * Log audit event
     *
     * @param string $event
     * @param array $context
     * @param string|null $userId
     * @return bool
     */
    public function log(string $event, array $context = [], ?string $userId = null): bool
    {
        $span = $this->telemetry->startSpan('audit_log', [
            'audit.event' => $event,
            'audit.user_id' => $userId,
        ]);

        try {
            global $wpdb;

            // Get current user if not provided
            if ($userId === null && is_user_logged_in()) {
                $userId = get_current_user_id();
            }

            // Prepare audit entry
            $entry = [
                'event_type' => $event,
                'user_id' => $userId,
                'user_ip' => $this->getUserIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_id' => $this->getRequestId(),
                'session_id' => $this->getSessionId(),
                'context' => json_encode($context),
                'metadata' => json_encode($this->getMetadata()),
                'created_at' => current_time('mysql'),
                'environment' => wp_get_environment_type(),
            ];

            // Insert into database
            $result = $wpdb->insert(
                $wpdb->prefix . $this->tableName,
                $entry,
                [
                    '%s', // event_type
                    '%d', // user_id
                    '%s', // user_ip
                    '%s', // user_agent
                    '%s', // request_id
                    '%s', // session_id
                    '%s', // context
                    '%s', // metadata
                    '%s', // created_at
                    '%s', // environment
                ]
            );

            if ($result === false) {
                throw new \Exception('Failed to insert audit log entry');
            }

            // Log to standard logger as well
            $this->logger->info('Audit event', array_merge($entry, [
                'audit_id' => $wpdb->insert_id,
            ]));

            // Send to SIEM if configured
            if ($this->config['siem']['enabled']) {
                $this->sendToSiem($event, $entry);
            }

            // Trigger alerts for critical events
            $this->checkAlerts($event, $context);

            return true;

        } catch (\Exception $e) {
            $this->telemetry->recordException($e);
            $this->logger->error('Failed to log audit event', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return false;
        } finally {
            $this->telemetry->endSpan('audit_log');
        }
    }

    /**
     * Log authentication attempt
     *
     * @param string $username
     * @param bool $success
     * @param array $context
     * @return bool
     */
    public function logAuth(string $username, bool $success, array $context = []): bool
    {
        $event = $success ? self::EVENT_AUTH_SUCCESS : self::EVENT_AUTH_FAILURE;
        
        return $this->log($event, array_merge($context, [
            'username' => $username,
            'auth_method' => $context['method'] ?? 'password',
            'two_factor' => $context['two_factor'] ?? false,
        ]));
    }

    /**
     * Log API key operation
     *
     * @param string $operation
     * @param string $keyId
     * @param array $context
     * @return bool
     */
    public function logApiKey(string $operation, string $keyId, array $context = []): bool
    {
        $eventMap = [
            'create' => self::EVENT_API_KEY_CREATED,
            'delete' => self::EVENT_API_KEY_DELETED,
            'rotate' => self::EVENT_API_KEY_ROTATED,
        ];

        $event = $eventMap[$operation] ?? 'api_key.' . $operation;
        
        return $this->log($event, array_merge($context, [
            'key_id' => $keyId,
            'key_prefix' => substr($keyId, 0, 8) . '...',
        ]));
    }

    /**
     * Log permission change
     *
     * @param string $action
     * @param string $permission
     * @param string $targetUserId
     * @param array $context
     * @return bool
     */
    public function logPermission(string $action, string $permission, string $targetUserId, array $context = []): bool
    {
        $event = $action === 'grant' ? self::EVENT_PERMISSION_GRANTED : self::EVENT_PERMISSION_REVOKED;
        
        return $this->log($event, array_merge($context, [
            'permission' => $permission,
            'target_user_id' => $targetUserId,
        ]));
    }

    /**
     * Log data operation
     *
     * @param string $operation
     * @param string $dataType
     * @param array $context
     * @return bool
     */
    public function logDataOperation(string $operation, string $dataType, array $context = []): bool
    {
        $event = $operation === 'export' ? self::EVENT_DATA_EXPORT : self::EVENT_DATA_IMPORT;
        
        return $this->log($event, array_merge($context, [
            'data_type' => $dataType,
            'record_count' => $context['count'] ?? 0,
            'format' => $context['format'] ?? 'json',
        ]));
    }

    /**
     * Query audit log
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function query(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        // Build WHERE clause
        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = %s';
            $params[] = $filters['event_type'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['user_ip'])) {
            $where[] = 'user_ip = %s';
            $params[] = $filters['user_ip'];
        }

        // Build query
        $sql = sprintf(
            "SELECT * FROM %s WHERE %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $wpdb->prefix . $this->tableName,
            implode(' AND ', $where),
            $limit,
            $offset
        );

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $results = $wpdb->get_results($sql, ARRAY_A);

        // Decode JSON fields
        foreach ($results as &$result) {
            $result['context'] = json_decode($result['context'], true);
            $result['metadata'] = json_decode($result['metadata'], true);
        }

        return $results;
    }

    /**
     * Get audit statistics
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array
    {
        global $wpdb;

        $period = $filters['period'] ?? '24h';
        $dateFrom = $this->getPeriodStart($period);

        // Event counts by type
        $eventCounts = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as count 
             FROM {$wpdb->prefix}{$this->tableName} 
             WHERE created_at >= %s 
             GROUP BY event_type 
             ORDER BY count DESC",
            $dateFrom
        ), ARRAY_A);

        // Failed auth attempts
        $failedAuths = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}{$this->tableName} 
             WHERE event_type = %s AND created_at >= %s",
            self::EVENT_AUTH_FAILURE,
            $dateFrom
        ));

        // Top users by activity
        $topUsers = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, COUNT(*) as count 
             FROM {$wpdb->prefix}{$this->tableName} 
             WHERE created_at >= %s AND user_id IS NOT NULL
             GROUP BY user_id 
             ORDER BY count DESC 
             LIMIT 10",
            $dateFrom
        ), ARRAY_A);

        // Suspicious IPs
        $suspiciousIps = $wpdb->get_results($wpdb->prepare(
            "SELECT user_ip, COUNT(*) as count 
             FROM {$wpdb->prefix}{$this->tableName} 
             WHERE event_type IN (%s, %s, %s) AND created_at >= %s 
             GROUP BY user_ip 
             HAVING count > %d 
             ORDER BY count DESC",
            self::EVENT_AUTH_FAILURE,
            self::EVENT_RATE_LIMIT_EXCEEDED,
            self::EVENT_SECURITY_ALERT,
            $dateFrom,
            10
        ), ARRAY_A);

        return [
            'period' => $period,
            'event_counts' => $eventCounts,
            'failed_auths' => $failedAuths,
            'top_users' => $topUsers,
            'suspicious_ips' => $suspiciousIps,
            'total_events' => array_sum(array_column($eventCounts, 'count')),
        ];
    }

    /**
     * Cleanup old audit logs
     *
     * @param int $daysToKeep
     * @return int Number of records deleted
     */
    public function cleanup(int $daysToKeep = 90): int
    {
        global $wpdb;

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        // Archive before deletion if configured
        if ($this->config['archive']['enabled']) {
            $this->archiveOldLogs($cutoffDate);
        }

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}{$this->tableName} WHERE created_at < %s",
            $cutoffDate
        ));

        $this->logger->info('Audit log cleanup completed', [
            'days_kept' => $daysToKeep,
            'records_deleted' => $deleted,
        ]);

        return $deleted;
    }

    /**
     * Get user IP address
     *
     * @return string|null
     */
    protected function getUserIp(): ?string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated list
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * Get request ID
     *
     * @return string
     */
    protected function getRequestId(): string
    {
        if (!empty($_SERVER['HTTP_X_REQUEST_ID'])) {
            return $_SERVER['HTTP_X_REQUEST_ID'];
        }
        
        // Generate if not present
        return uniqid('req_', true);
    }

    /**
     * Get session ID
     *
     * @return string|null
     */
    protected function getSessionId(): ?string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return session_id();
        }
        
        return null;
    }

    /**
     * Get additional metadata
     *
     * @return array
     */
    protected function getMetadata(): array
    {
        return [
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
            'server_name' => $_SERVER['SERVER_NAME'] ?? null,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
        ];
    }

    /**
     * Send audit event to SIEM
     *
     * @param string $event
     * @param array $entry
     * @return void
     */
    protected function sendToSiem(string $event, array $entry): void
    {
        if (empty($this->config['siem']['endpoint'])) {
            return;
        }

        // Format for SIEM ingestion
        $siemEvent = [
            'timestamp' => time(),
            'severity' => $this->getEventSeverity($event),
            'event_type' => $event,
            'source_ip' => $entry['user_ip'],
            'user_id' => $entry['user_id'],
            'data' => $entry,
        ];

        wp_remote_post($this->config['siem']['endpoint'], [
            'body' => json_encode($siemEvent),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->config['siem']['api_key'],
            ],
            'timeout' => 5,
            'blocking' => false, // Don't block on SIEM delivery
        ]);
    }

    /**
     * Check if event should trigger alerts
     *
     * @param string $event
     * @param array $context
     * @return void
     */
    protected function checkAlerts(string $event, array $context): void
    {
        $alertEvents = [
            self::EVENT_SECURITY_ALERT,
            self::EVENT_DATA_EXPORT,
            self::EVENT_PERMISSION_GRANTED,
            self::EVENT_API_KEY_CREATED,
        ];

        if (in_array($event, $alertEvents)) {
            do_action('zippicks_audit_alert', $event, $context);
        }

        // Check for repeated failed auth attempts
        if ($event === self::EVENT_AUTH_FAILURE) {
            $this->checkFailedAuthThreshold($context);
        }
    }

    /**
     * Check failed authentication threshold
     *
     * @param array $context
     * @return void
     */
    protected function checkFailedAuthThreshold(array $context): void
    {
        global $wpdb;

        $threshold = $this->config['alerts']['failed_auth_threshold'];
        $window = $this->config['alerts']['failed_auth_window']; // minutes

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}{$this->tableName} 
             WHERE event_type = %s 
             AND user_ip = %s 
             AND created_at >= %s",
            self::EVENT_AUTH_FAILURE,
            $this->getUserIp(),
            date('Y-m-d H:i:s', strtotime("-{$window} minutes"))
        ));

        if ($count >= $threshold) {
            $this->log(self::EVENT_SECURITY_ALERT, [
                'alert_type' => 'excessive_failed_auth',
                'failed_attempts' => $count,
                'threshold' => $threshold,
                'window_minutes' => $window,
            ]);
        }
    }

    /**
     * Get event severity
     *
     * @param string $event
     * @return string
     */
    protected function getEventSeverity(string $event): string
    {
        $severityMap = [
            self::EVENT_SECURITY_ALERT => 'critical',
            self::EVENT_AUTH_FAILURE => 'warning',
            self::EVENT_RATE_LIMIT_EXCEEDED => 'warning',
            self::EVENT_DATA_EXPORT => 'info',
            self::EVENT_API_KEY_CREATED => 'info',
            self::EVENT_CONFIG_CHANGE => 'notice',
        ];

        return $severityMap[$event] ?? 'info';
    }

    /**
     * Get period start date
     *
     * @param string $period
     * @return string
     */
    protected function getPeriodStart(string $period): string
    {
        $intervals = [
            '1h' => '-1 hour',
            '24h' => '-24 hours',
            '7d' => '-7 days',
            '30d' => '-30 days',
            '90d' => '-90 days',
        ];

        $interval = $intervals[$period] ?? '-24 hours';
        return date('Y-m-d H:i:s', strtotime($interval));
    }

    /**
     * Archive old logs
     *
     * @param string $cutoffDate
     * @return void
     */
    protected function archiveOldLogs(string $cutoffDate): void
    {
        // Implementation would export to S3, cold storage, etc.
        $this->logger->info('Archiving old audit logs', [
            'cutoff_date' => $cutoffDate,
        ]);
    }

    /**
     * Ensure audit table exists
     *
     * @return void
     */
    protected function ensureTableExists(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}{$this->tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            request_id varchar(50) DEFAULT NULL,
            session_id varchar(50) DEFAULT NULL,
            context longtext DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            environment varchar(20) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_user_ip (user_ip),
            KEY idx_request_id (request_id)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'siem' => [
                'enabled' => false,
                'endpoint' => null,
                'api_key' => null,
            ],
            'alerts' => [
                'failed_auth_threshold' => 5,
                'failed_auth_window' => 15, // minutes
            ],
            'archive' => [
                'enabled' => false,
                'storage' => 's3',
            ],
            'retention_days' => 90,
        ];
    }
}