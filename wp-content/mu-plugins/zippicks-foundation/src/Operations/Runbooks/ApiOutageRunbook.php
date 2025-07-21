<?php
/**
 * API Outage Response Runbook
 * 
 * Enterprise incident response procedure for API outages
 * Automated recovery and escalation for $100B platform
 *
 * @package ZipPicks\Foundation\Operations\Runbooks
 */

namespace ZipPicks\Foundation\Operations\Runbooks;

use ZipPicks\Foundation\Operations\Runbooks\RunbookInterface;

class ApiOutageRunbook implements RunbookInterface
{
    /**
     * Get runbook name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'API Outage Response';
    }

    /**
     * Get runbook description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Comprehensive incident response procedure for API outages including diagnosis, recovery, and escalation';
    }

    /**
     * Get runbook category
     *
     * @return string
     */
    public function getCategory(): string
    {
        return 'incident_response';
    }

    /**
     * Get runbook criticality level
     *
     * @return string
     */
    public function getCriticality(): string
    {
        return 'critical';
    }

    /**
     * Get estimated duration in minutes
     *
     * @return int
     */
    public function getEstimatedDuration(): int
    {
        return 15; // 15 minutes for initial response
    }

    /**
     * Get runbook steps
     *
     * @return array
     */
    public function getSteps(): array
    {
        return [
            [
                'name' => 'Immediate Assessment',
                'type' => 'api_call',
                'description' => 'Check API health endpoints and gather initial status',
                'critical' => true,
                'timeout' => 30,
                'api' => [
                    'endpoints' => [
                        '/wp-json/zippicks/v1/health',
                        '/wp-json/zippicks/v1/status',
                        '/wp-json/zippicks/v1/businesses?limit=1'
                    ],
                    'expected_status' => 200,
                    'timeout' => 10
                ],
                'verify' => true
            ],
            [
                'name' => 'Check System Resources',
                'type' => 'command',
                'description' => 'Verify server resources and load',
                'critical' => false,
                'command' => [
                    'commands' => [
                        'df -h',
                        'free -m',
                        'top -bn1 | head -20',
                        'ps aux | grep php-fpm | wc -l'
                    ],
                    'timeout' => 30
                ]
            ],
            [
                'name' => 'Database Connectivity Check',
                'type' => 'database',
                'description' => 'Verify database connections and performance',
                'critical' => true,
                'database' => [
                    'operations' => [
                        ['type' => 'ping', 'timeout' => 5],
                        ['type' => 'simple_query', 'query' => 'SELECT 1', 'timeout' => 10],
                        ['type' => 'check_connections', 'max_connections' => 100]
                    ]
                ],
                'verify' => true
            ],
            [
                'name' => 'Cache System Check',
                'type' => 'api_call',
                'description' => 'Verify Redis/cache connectivity and performance',
                'critical' => true,
                'api' => [
                    'internal_endpoint' => '/internal/cache/health',
                    'operations' => ['ping', 'set_test_key', 'get_test_key'],
                    'timeout' => 10
                ],
                'verify' => true
            ],
            [
                'name' => 'Create Incident Channel',
                'type' => 'notification',
                'description' => 'Create dedicated Slack channel for incident coordination',
                'critical' => false,
                'notification' => [
                    'type' => 'slack_channel',
                    'channel_name' => 'incident-api-outage-{timestamp}',
                    'invite_users' => ['oncall-primary', 'oncall-secondary', 'eng-manager'],
                    'initial_message' => 'API outage detected. Runbook execution in progress.'
                ]
            ],
            [
                'name' => 'Restart PHP-FPM Services',
                'type' => 'command',
                'description' => 'Restart PHP-FPM to clear potential memory issues',
                'critical' => false,
                'command' => [
                    'commands' => [
                        'sudo systemctl reload php8.1-fpm',
                        'sleep 10',
                        'sudo systemctl status php8.1-fpm'
                    ],
                    'timeout' => 60
                ]
            ],
            [
                'name' => 'Clear Application Caches',
                'type' => 'command',
                'description' => 'Clear WordPress and application caches',
                'critical' => false,
                'command' => [
                    'commands' => [
                        'wp cache flush --allow-root',
                        'wp transient delete --all --allow-root',
                        'redis-cli FLUSHDB'
                    ],
                    'timeout' => 30
                ]
            ],
            [
                'name' => 'Re-test API Endpoints',
                'type' => 'api_call',
                'description' => 'Verify API functionality after recovery attempts',
                'critical' => true,
                'api' => [
                    'endpoints' => [
                        '/wp-json/zippicks/v1/health',
                        '/wp-json/zippicks/v1/businesses?limit=5',
                        '/wp-json/zippicks/v1/search?q=test&zip=10001'
                    ],
                    'expected_status' => 200,
                    'timeout' => 15
                ],
                'verify' => true
            ],
            [
                'name' => 'Update Status Page',
                'type' => 'notification',
                'description' => 'Update external status page with incident information',
                'critical' => false,
                'notification' => [
                    'type' => 'status_page',
                    'status' => 'investigating',
                    'message' => 'We are investigating reports of API connectivity issues and are working on a resolution.',
                    'components' => ['api', 'search', 'business-listings']
                ]
            ],
            [
                'name' => 'Escalate if Unresolved',
                'type' => 'notification',
                'description' => 'Escalate to senior engineers if issue persists',
                'critical' => false,
                'condition' => 'api_still_down',
                'notification' => [
                    'type' => 'pagerduty',
                    'severity' => 'critical',
                    'summary' => 'API outage persists after initial recovery attempts',
                    'escalation_policy' => 'api-outage-escalation'
                ]
            ]
        ];
    }

    /**
     * Get rollback steps
     *
     * @return array
     */
    public function getRollbackSteps(): array
    {
        return [
            [
                'name' => 'Revert Cache Flush',
                'type' => 'command',
                'description' => 'Restore cache warming if cache flush caused issues',
                'command' => [
                    'commands' => [
                        'wp cache warm --allow-root',
                        'redis-cli PING'
                    ]
                ]
            ],
            [
                'name' => 'Restart Services to Previous State',
                'type' => 'command',
                'description' => 'Ensure services are in known good state',
                'command' => [
                    'commands' => [
                        'sudo systemctl restart php8.1-fpm',
                        'sudo systemctl restart nginx'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get prerequisites
     *
     * @return array
     */
    public function getPrerequisites(): array
    {
        return [
            'sudo_access',
            'wp_cli_available',
            'redis_cli_available',
            'slack_integration_configured',
            'pagerduty_integration_configured'
        ];
    }

    /**
     * Get expected outcomes
     *
     * @return array
     */
    public function getExpectedOutcomes(): array
    {
        return [
            'api_endpoints_responding',
            'response_times_under_500ms',
            'error_rate_under_1_percent',
            'incident_documented',
            'team_notified',
            'status_page_updated'
        ];
    }

    /**
     * Validate context for execution
     *
     * @param array $context
     * @return array
     */
    public function validateContext(array $context): array
    {
        $errors = [];

        // Check for required context
        if (empty($context['severity'])) {
            $errors[] = 'Severity level is required';
        }

        if (!empty($context['severity']) && !in_array($context['severity'], ['low', 'medium', 'high', 'critical'])) {
            $errors[] = 'Invalid severity level';
        }

        // Validate escalation contacts if provided
        if (!empty($context['escalation_contacts'])) {
            if (!is_array($context['escalation_contacts'])) {
                $errors[] = 'Escalation contacts must be an array';
            }
        }

        return $errors;
    }
}