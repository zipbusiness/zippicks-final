<?php
/**
 * ZipPicks Alert Manager
 * 
 * Enterprise-grade alerting system with intelligent escalation and notifications
 * Manages alerts, notifications, and incident response automation
 *
 * @package ZipPicks\Foundation\Monitoring\Alerts
 */

namespace ZipPicks\Foundation\Monitoring\Alerts;

use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Logging\EnterpriseLogger;
use ZipPicks\Foundation\Cache\CacheManager;
use ZipPicks\Foundation\Queue\QueueManager;

class AlertManager
{
    /**
     * Container instance
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Logger instance
     *
     * @var EnterpriseLogger
     */
    protected EnterpriseLogger $logger;

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected CacheManager $cache;

    /**
     * Queue manager
     *
     * @var QueueManager
     */
    protected QueueManager $queue;

    /**
     * Alert configuration
     *
     * @var array
     */
    protected array $config;

    /**
     * Alert severity levels
     *
     * @var array
     */
    protected array $severityLevels = [
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4,
        'emergency' => 5
    ];

    /**
     * Alert escalation rules
     *
     * @var array
     */
    protected array $escalationRules = [];

    /**
     * Active alerts cache
     *
     * @var array
     */
    protected array $activeAlerts = [];

    /**
     * Create alert manager
     *
     * @param Container $container
     * @param EnterpriseLogger $logger
     * @param CacheManager $cache
     * @param QueueManager $queue
     */
    public function __construct(
        Container $container,
        EnterpriseLogger $logger,
        CacheManager $cache,
        QueueManager $queue
    ) {
        $this->container = $container;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->queue = $queue;
        
        $this->loadConfiguration();
        $this->loadActiveAlerts();
    }

    /**
     * Create a new alert
     *
     * @param array $alert
     * @return array
     */
    public function create(array $alert): array
    {
        $alert = $this->normalizeAlert($alert);
        
        // Check for duplicate alerts
        if ($this->isDuplicateAlert($alert)) {
            $this->logger->debug('Duplicate alert suppressed', [
                'alert_id' => $alert['id'],
                'type' => $alert['type']
            ]);
            return $this->updateExistingAlert($alert);
        }

        // Validate alert
        $this->validateAlert($alert);

        // Store alert
        $this->storeAlert($alert);
        
        // Add to active alerts
        $this->activeAlerts[$alert['id']] = $alert;
        $this->updateActiveAlertsCache();

        // Process alert
        $this->processAlert($alert);

        $this->logger->info('Alert created', [
            'alert_id' => $alert['id'],
            'type' => $alert['type'],
            'severity' => $alert['severity']
        ]);

        return $alert;
    }

    /**
     * Acknowledge an alert
     *
     * @param string $alertId
     * @param string $acknowledgedBy
     * @param string $note
     * @return bool
     */
    public function acknowledge(string $alertId, string $acknowledgedBy, string $note = ''): bool
    {
        if (!isset($this->activeAlerts[$alertId])) {
            return false;
        }

        $alert = &$this->activeAlerts[$alertId];
        $alert['acknowledged'] = true;
        $alert['acknowledged_at'] = time();
        $alert['acknowledged_by'] = $acknowledgedBy;
        $alert['acknowledgment_note'] = $note;

        $this->updateAlert($alert);
        $this->updateActiveAlertsCache();

        $this->logger->info('Alert acknowledged', [
            'alert_id' => $alertId,
            'acknowledged_by' => $acknowledgedBy,
            'note' => $note
        ]);

        return true;
    }

    /**
     * Resolve an alert
     *
     * @param string $alertId
     * @param string $resolvedBy
     * @param string $resolution
     * @return bool
     */
    public function resolve(string $alertId, string $resolvedBy, string $resolution = ''): bool
    {
        if (!isset($this->activeAlerts[$alertId])) {
            return false;
        }

        $alert = $this->activeAlerts[$alertId];
        $alert['status'] = 'resolved';
        $alert['resolved_at'] = time();
        $alert['resolved_by'] = $resolvedBy;
        $alert['resolution'] = $resolution;

        $this->updateAlert($alert);
        
        // Remove from active alerts
        unset($this->activeAlerts[$alertId]);
        $this->updateActiveAlertsCache();

        $this->logger->info('Alert resolved', [
            'alert_id' => $alertId,
            'resolved_by' => $resolvedBy,
            'resolution' => $resolution,
            'duration' => time() - $alert['created_at']
        ]);

        return true;
    }

    /**
     * Get active alerts
     *
     * @param array $filters
     * @return array
     */
    public function getActiveAlerts(array $filters = []): array
    {
        $alerts = $this->activeAlerts;

        // Apply filters
        if (!empty($filters)) {
            $alerts = $this->filterAlerts($alerts, $filters);
        }

        // Sort by severity and creation time
        uasort($alerts, function($a, $b) {
            $severityDiff = $this->severityLevels[$b['severity']] - $this->severityLevels[$a['severity']];
            if ($severityDiff !== 0) {
                return $severityDiff;
            }
            return $b['created_at'] - $a['created_at'];
        });

        return array_values($alerts);
    }

    /**
     * Get alert history
     *
     * @param int $startTime
     * @param int $endTime
     * @param array $filters
     * @return array
     */
    public function getAlertHistory(int $startTime, int $endTime, array $filters = []): array
    {
        return $this->fetchAlertsFromStorage($startTime, $endTime, $filters);
    }

    /**
     * Get alert statistics
     *
     * @param int $startTime
     * @param int $endTime
     * @return array
     */
    public function getAlertStatistics(int $startTime, int $endTime): array
    {
        $alerts = $this->fetchAlertsFromStorage($startTime, $endTime);

        $stats = [
            'total_alerts' => count($alerts),
            'by_severity' => [],
            'by_type' => [],
            'by_status' => [],
            'avg_resolution_time' => 0,
            'escalation_rate' => 0,
            'acknowledgment_rate' => 0
        ];

        $resolutionTimes = [];
        $escalatedCount = 0;
        $acknowledgedCount = 0;

        foreach ($alerts as $alert) {
            // By severity
            $severity = $alert['severity'];
            $stats['by_severity'][$severity] = ($stats['by_severity'][$severity] ?? 0) + 1;

            // By type
            $type = $alert['type'];
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;

            // By status
            $status = $alert['status'];
            $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + 1;

            // Resolution time
            if ($alert['status'] === 'resolved' && isset($alert['resolved_at'])) {
                $resolutionTimes[] = $alert['resolved_at'] - $alert['created_at'];
            }

            // Escalation tracking
            if ($alert['escalated'] ?? false) {
                $escalatedCount++;
            }

            // Acknowledgment tracking
            if ($alert['acknowledged'] ?? false) {
                $acknowledgedCount++;
            }
        }

        if (!empty($resolutionTimes)) {
            $stats['avg_resolution_time'] = array_sum($resolutionTimes) / count($resolutionTimes);
        }

        $stats['escalation_rate'] = $stats['total_alerts'] > 0 ? 
            ($escalatedCount / $stats['total_alerts']) * 100 : 0;

        $stats['acknowledgment_rate'] = $stats['total_alerts'] > 0 ? 
            ($acknowledgedCount / $stats['total_alerts']) * 100 : 0;

        return $stats;
    }

    /**
     * Check alert thresholds
     *
     * @param string $metric
     * @param float $value
     * @param array $thresholds
     * @return void
     */
    public function checkThresholds(string $metric, float $value, array $thresholds): void
    {
        foreach ($thresholds as $threshold) {
            if ($this->evaluateThreshold($value, $threshold)) {
                $this->create([
                    'type' => "threshold_breach_{$metric}",
                    'severity' => $threshold['severity'],
                    'message' => "Threshold breached for {$metric}: {$value} {$threshold['operator']} {$threshold['value']}",
                    'context' => [
                        'metric' => $metric,
                        'current_value' => $value,
                        'threshold' => $threshold
                    ]
                ]);
            }
        }
    }

    /**
     * Send notification
     *
     * @param array $alert
     * @param string $channel
     * @return bool
     */
    public function sendNotification(array $alert, string $channel): bool
    {
        try {
            $notificationData = [
                'alert' => $alert,
                'channel' => $channel,
                'timestamp' => time()
            ];

            $this->queue->push('send_alert_notification', $notificationData);

            $this->logger->info('Alert notification queued', [
                'alert_id' => $alert['id'],
                'channel' => $channel
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to queue alert notification', [
                'alert_id' => $alert['id'],
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Process alert based on severity and rules
     *
     * @param array $alert
     * @return void
     */
    protected function processAlert(array $alert): void
    {
        $severity = $alert['severity'];
        $severityLevel = $this->severityLevels[$severity];

        // Immediate notifications for high severity alerts
        if ($severityLevel >= $this->severityLevels['error']) {
            $this->sendImmediateNotifications($alert);
        }

        // Schedule escalation if not acknowledged
        if ($severityLevel >= $this->severityLevels['warning']) {
            $this->scheduleEscalation($alert);
        }

        // Auto-remediation for known issues
        $this->attemptAutoRemediation($alert);
    }

    /**
     * Send immediate notifications
     *
     * @param array $alert
     * @return void
     */
    protected function sendImmediateNotifications(array $alert): void
    {
        $channels = $this->getNotificationChannels($alert['severity']);

        foreach ($channels as $channel) {
            $this->sendNotification($alert, $channel);
        }
    }

    /**
     * Schedule alert escalation
     *
     * @param array $alert
     * @return void
     */
    protected function scheduleEscalation(array $alert): void
    {
        $escalationDelay = $this->getEscalationDelay($alert['severity']);
        
        $this->queue->later($escalationDelay, 'escalate_alert', [
            'alert_id' => $alert['id'],
            'escalation_level' => 1
        ]);
    }

    /**
     * Attempt automatic remediation
     *
     * @param array $alert
     * @return bool
     */
    protected function attemptAutoRemediation(array $alert): bool
    {
        $remediationRules = $this->config['auto_remediation'] ?? [];
        
        foreach ($remediationRules as $rule) {
            if ($this->matchesRemediationRule($alert, $rule)) {
                return $this->executeRemediation($alert, $rule);
            }
        }

        return false;
    }

    /**
     * Normalize alert data
     *
     * @param array $alert
     * @return array
     */
    protected function normalizeAlert(array $alert): array
    {
        return array_merge([
            'id' => $alert['id'] ?? uniqid('alert_'),
            'type' => $alert['type'] ?? 'unknown',
            'severity' => $alert['severity'] ?? 'warning',
            'message' => $alert['message'] ?? '',
            'context' => $alert['context'] ?? [],
            'status' => 'active',
            'created_at' => time(),
            'acknowledged' => false,
            'escalated' => false,
            'source' => 'monitoring_system'
        ], $alert);
    }

    /**
     * Validate alert data
     *
     * @param array $alert
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function validateAlert(array $alert): void
    {
        $required = ['id', 'type', 'severity', 'message'];
        
        foreach ($required as $field) {
            if (empty($alert[$field])) {
                throw new \InvalidArgumentException("Alert field '{$field}' is required");
            }
        }

        if (!isset($this->severityLevels[$alert['severity']])) {
            throw new \InvalidArgumentException("Invalid severity level: {$alert['severity']}");
        }
    }

    /**
     * Check for duplicate alerts
     *
     * @param array $alert
     * @return bool
     */
    protected function isDuplicateAlert(array $alert): bool
    {
        $duplicateWindow = $this->config['duplicate_suppression_window'] ?? 300; // 5 minutes
        $cutoffTime = time() - $duplicateWindow;

        foreach ($this->activeAlerts as $existingAlert) {
            if ($existingAlert['type'] === $alert['type'] &&
                $existingAlert['created_at'] > $cutoffTime &&
                $this->alertsAreSimilar($existingAlert, $alert)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update existing alert
     *
     * @param array $alert
     * @return array
     */
    protected function updateExistingAlert(array $alert): array
    {
        foreach ($this->activeAlerts as &$existingAlert) {
            if ($existingAlert['type'] === $alert['type'] &&
                $this->alertsAreSimilar($existingAlert, $alert)) {
                
                $existingAlert['occurrence_count'] = ($existingAlert['occurrence_count'] ?? 1) + 1;
                $existingAlert['last_occurrence'] = time();
                
                $this->updateAlert($existingAlert);
                $this->updateActiveAlertsCache();
                
                return $existingAlert;
            }
        }

        return $alert;
    }

    /**
     * Check if alerts are similar
     *
     * @param array $alert1
     * @param array $alert2
     * @return bool
     */
    protected function alertsAreSimilar(array $alert1, array $alert2): bool
    {
        // Compare key context fields to determine similarity
        $contextKeys = ['metric', 'endpoint', 'component'];
        
        foreach ($contextKeys as $key) {
            $value1 = $alert1['context'][$key] ?? null;
            $value2 = $alert2['context'][$key] ?? null;
            
            if ($value1 !== null && $value2 !== null && $value1 !== $value2) {
                return false;
            }
        }

        return true;
    }

    /**
     * Store alert to database
     *
     * @param array $alert
     * @return void
     */
    protected function storeAlert(array $alert): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'zippicks_alerts';
        $this->ensureAlertsTable();

        $wpdb->insert($tableName, [
            'alert_id' => $alert['id'],
            'type' => $alert['type'],
            'severity' => $alert['severity'],
            'message' => $alert['message'],
            'context' => json_encode($alert['context']),
            'status' => $alert['status'],
            'created_at' => date('Y-m-d H:i:s', $alert['created_at']),
            'acknowledged' => $alert['acknowledged'] ? 1 : 0,
            'escalated' => $alert['escalated'] ? 1 : 0
        ]);
    }

    /**
     * Update alert in database
     *
     * @param array $alert
     * @return void
     */
    protected function updateAlert(array $alert): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'zippicks_alerts';
        
        $updateData = [
            'status' => $alert['status'],
            'acknowledged' => $alert['acknowledged'] ? 1 : 0,
            'escalated' => $alert['escalated'] ? 1 : 0
        ];

        if (isset($alert['acknowledged_at'])) {
            $updateData['acknowledged_at'] = date('Y-m-d H:i:s', $alert['acknowledged_at']);
        }

        if (isset($alert['resolved_at'])) {
            $updateData['resolved_at'] = date('Y-m-d H:i:s', $alert['resolved_at']);
        }

        $wpdb->update($tableName, $updateData, ['alert_id' => $alert['id']]);
    }

    /**
     * Load configuration
     *
     * @return void
     */
    protected function loadConfiguration(): void
    {
        $this->config = [
            'duplicate_suppression_window' => 300,
            'escalation_delays' => [
                'warning' => 900,    // 15 minutes
                'error' => 300,      // 5 minutes
                'critical' => 60,    // 1 minute
                'emergency' => 0     // Immediate
            ],
            'notification_channels' => [
                'warning' => ['email'],
                'error' => ['email', 'slack'],
                'critical' => ['email', 'slack', 'sms'],
                'emergency' => ['email', 'slack', 'sms', 'phone']
            ],
            'auto_remediation' => [
                [
                    'type' => 'high_memory_usage',
                    'action' => 'clear_cache',
                    'conditions' => ['memory_usage' => ['>', 90]]
                ],
                [
                    'type' => 'api_error_rate_high',
                    'action' => 'enable_circuit_breaker',
                    'conditions' => ['error_rate' => ['>', 10]]
                ]
            ]
        ];
    }

    /**
     * Load active alerts from cache
     *
     * @return void
     */
    protected function loadActiveAlerts(): void
    {
        $this->activeAlerts = $this->cache->get('active_alerts', []);
    }

    /**
     * Update active alerts cache
     *
     * @return void
     */
    protected function updateActiveAlertsCache(): void
    {
        $this->cache->put('active_alerts', $this->activeAlerts, 3600);
    }

    /**
     * Ensure alerts table exists
     *
     * @return void
     */
    protected function ensureAlertsTable(): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'zippicks_alerts';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            alert_id VARCHAR(255) UNIQUE NOT NULL,
            type VARCHAR(100) NOT NULL,
            severity VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            context JSON,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL,
            acknowledged TINYINT(1) DEFAULT 0,
            acknowledged_at TIMESTAMP NULL,
            acknowledged_by VARCHAR(100) NULL,
            escalated TINYINT(1) DEFAULT 0,
            resolved_at TIMESTAMP NULL,
            resolved_by VARCHAR(100) NULL,
            INDEX idx_type_status (type, status),
            INDEX idx_severity (severity),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $wpdb->query($sql);
    }

    // Helper methods - These would have full implementations in production
    protected function filterAlerts(array $alerts, array $filters): array { return $alerts; }
    protected function fetchAlertsFromStorage(int $start, int $end, array $filters = []): array { return []; }
    protected function evaluateThreshold(float $value, array $threshold): bool { return false; }
    protected function getNotificationChannels(string $severity): array { return $this->config['notification_channels'][$severity] ?? []; }
    protected function getEscalationDelay(string $severity): int { return $this->config['escalation_delays'][$severity] ?? 300; }
    protected function matchesRemediationRule(array $alert, array $rule): bool { return false; }
    protected function executeRemediation(array $alert, array $rule): bool { return false; }
}