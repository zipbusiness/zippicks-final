<?php
/**
 * Security Service Provider
 *
 * @package ZipPicks\Foundation\Services
 */

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Webhooks\WebhookSigner;
use ZipPicks\Foundation\Audit\AuditLogger;
use ZipPicks\Foundation\Contracts\Webhooks\WebhookSignerInterface;
use ZipPicks\Foundation\Contracts\Audit\AuditLoggerInterface;

/**
 * Registers security-related services
 */
class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        // Register Webhook Signer
        $this->app->singleton(WebhookSigner::class, function ($app) {
            return new WebhookSigner(
                $app->make('logger'),
                $app->make('config')->get('webhooks', [])
            );
        });

        $this->app->alias(WebhookSigner::class, WebhookSignerInterface::class);
        $this->app->alias(WebhookSigner::class, 'webhook.signer');

        // Register Audit Logger
        $this->app->singleton(AuditLogger::class, function ($app) {
            return new AuditLogger(
                $app->make('logger'),
                $app->make('telemetry'),
                $app->make('config')->get('audit', [])
            );
        });

        $this->app->alias(AuditLogger::class, AuditLoggerInterface::class);
        $this->app->alias(AuditLogger::class, 'audit');

        // Register WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            $this->registerCommands();
        }
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        // Add webhook verification middleware
        add_action('rest_api_init', [$this, 'registerWebhookMiddleware'], 5);

        // Add audit logging hooks
        $this->registerAuditHooks();

        // Register REST endpoints
        add_action('rest_api_init', [$this, 'registerEndpoints']);

        // Schedule cleanup
        if (!wp_next_scheduled('zippicks_audit_cleanup')) {
            wp_schedule_event(time(), 'daily', 'zippicks_audit_cleanup');
        }
        add_action('zippicks_audit_cleanup', [$this, 'runAuditCleanup']);

        // Add security headers
        add_action('send_headers', [$this, 'addSecurityHeaders']);
    }

    /**
     * Register webhook verification middleware
     *
     * @return void
     */
    public function registerWebhookMiddleware(): void
    {
        // Add pre-serve filter for webhook endpoints
        add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
            $route = $request->get_route();
            
            // Check if this is a webhook endpoint
            if (strpos($route, '/webhook/') !== false || strpos($route, '/webhooks/') !== false) {
                $signature = $request->get_header('X-ZipPicks-Signature');
                
                if (!$signature) {
                    return new \WP_Error(
                        'missing_signature',
                        'Webhook signature required',
                        ['status' => 401]
                    );
                }

                $secret = $this->app->make('config')->get('webhooks.secret');
                $payload = $request->get_body();
                
                $signer = $this->app->make('webhook.signer');
                
                if (!$signer->verifyWithRotation($payload, $signature, $secret)) {
                    // Log failed verification
                    $this->app->make('audit')->log(AuditLogger::EVENT_SECURITY_ALERT, [
                        'alert_type' => 'invalid_webhook_signature',
                        'route' => $route,
                        'signature' => substr($signature, 0, 20) . '...',
                    ]);
                    
                    return new \WP_Error(
                        'invalid_signature',
                        'Invalid webhook signature',
                        ['status' => 401]
                    );
                }
            }
            
            return $served;
        }, 10, 4);
    }

    /**
     * Register audit hooks
     *
     * @return void
     */
    protected function registerAuditHooks(): void
    {
        $audit = $this->app->make('audit');

        // Authentication events
        add_action('wp_login', function ($user_login, $user) use ($audit) {
            $audit->logAuth($user_login, true, [
                'user_id' => $user->ID,
                'roles' => $user->roles,
            ]);
        }, 10, 2);

        add_action('wp_login_failed', function ($username) use ($audit) {
            $audit->logAuth($username, false);
        });

        // User management
        add_action('user_register', function ($user_id) use ($audit) {
            $user = get_user_by('id', $user_id);
            $audit->log('user.created', [
                'new_user_id' => $user_id,
                'username' => $user->user_login,
                'email' => $user->user_email,
            ]);
        });

        add_action('delete_user', function ($user_id) use ($audit) {
            $user = get_user_by('id', $user_id);
            $audit->log('user.deleted', [
                'deleted_user_id' => $user_id,
                'username' => $user->user_login,
            ]);
        });

        // Role changes
        add_action('set_user_role', function ($user_id, $role, $old_roles) use ($audit) {
            $audit->log('user.role_changed', [
                'target_user_id' => $user_id,
                'new_role' => $role,
                'old_roles' => $old_roles,
            ]);
        }, 10, 3);

        // Plugin activation/deactivation
        add_action('activated_plugin', function ($plugin) use ($audit) {
            $audit->log('plugin.activated', [
                'plugin' => $plugin,
            ]);
        });

        add_action('deactivated_plugin', function ($plugin) use ($audit) {
            $audit->log('plugin.deactivated', [
                'plugin' => $plugin,
            ]);
        });

        // Theme changes
        add_action('switch_theme', function ($new_name, $new_theme, $old_theme) use ($audit) {
            $audit->log('theme.changed', [
                'new_theme' => $new_name,
                'old_theme' => $old_theme->get('Name'),
            ]);
        }, 10, 3);

        // Option changes (for critical settings)
        $criticalOptions = [
            'users_can_register',
            'default_role',
            'admin_email',
            'siteurl',
            'home',
        ];

        foreach ($criticalOptions as $option) {
            add_action("update_option_{$option}", function ($old_value, $value) use ($audit, $option) {
                $audit->log(AuditLogger::EVENT_CONFIG_CHANGE, [
                    'option' => $option,
                    'old_value' => $old_value,
                    'new_value' => $value,
                ]);
            }, 10, 2);
        }

        // API key events (custom)
        add_action('zippicks_api_key_created', function ($keyId, $userId) use ($audit) {
            $audit->logApiKey('create', $keyId, ['user_id' => $userId]);
        }, 10, 2);

        add_action('zippicks_api_key_deleted', function ($keyId) use ($audit) {
            $audit->logApiKey('delete', $keyId);
        });

        add_action('zippicks_api_key_rotated', function ($oldKeyId, $newKeyId) use ($audit) {
            $audit->logApiKey('rotate', $newKeyId, ['old_key_id' => $oldKeyId]);
        }, 10, 2);

        // Rate limit events
        add_action('zippicks_rate_limit_exceeded', function ($identifier, $limit) use ($audit) {
            $audit->log(AuditLogger::EVENT_RATE_LIMIT_EXCEEDED, [
                'identifier' => $identifier,
                'limit' => $limit,
            ]);
        }, 10, 2);

        // Data export events
        add_action('zippicks_data_exported', function ($dataType, $count, $userId) use ($audit) {
            $audit->logDataOperation('export', $dataType, [
                'count' => $count,
                'user_id' => $userId,
            ]);
        }, 10, 3);
    }

    /**
     * Register REST API endpoints
     *
     * @return void
     */
    public function registerEndpoints(): void
    {
        // Audit log query endpoint
        register_rest_route('zippicks/v1', '/admin/audit', [
            'methods' => 'GET',
            'callback' => [$this, 'handleAuditQuery'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'event_type' => [
                    'type' => 'string',
                ],
                'user_id' => [
                    'type' => 'integer',
                ],
                'date_from' => [
                    'type' => 'string',
                    'format' => 'date-time',
                ],
                'date_to' => [
                    'type' => 'string',
                    'format' => 'date-time',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 100,
                    'minimum' => 1,
                    'maximum' => 1000,
                ],
                'offset' => [
                    'type' => 'integer',
                    'default' => 0,
                    'minimum' => 0,
                ],
            ],
        ]);

        // Audit statistics endpoint
        register_rest_route('zippicks/v1', '/admin/audit/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'handleAuditStats'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'period' => [
                    'type' => 'string',
                    'enum' => ['1h', '24h', '7d', '30d', '90d'],
                    'default' => '24h',
                ],
            ],
        ]);

        // Webhook signature verification endpoint (for testing)
        register_rest_route('zippicks/v1', '/admin/webhook/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'handleWebhookVerify'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Handle audit query endpoint
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleAuditQuery(\WP_REST_Request $request): \WP_REST_Response
    {
        $audit = $this->app->make('audit');
        
        $filters = [
            'event_type' => $request->get_param('event_type'),
            'user_id' => $request->get_param('user_id'),
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
        ];
        
        $results = $audit->query(
            array_filter($filters),
            $request->get_param('limit'),
            $request->get_param('offset')
        );
        
        return new \WP_REST_Response([
            'entries' => $results,
            'count' => count($results),
        ]);
    }

    /**
     * Handle audit statistics endpoint
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleAuditStats(\WP_REST_Request $request): \WP_REST_Response
    {
        $audit = $this->app->make('audit');
        
        $stats = $audit->getStatistics([
            'period' => $request->get_param('period'),
        ]);
        
        return new \WP_REST_Response($stats);
    }

    /**
     * Handle webhook verification test
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleWebhookVerify(\WP_REST_Request $request): \WP_REST_Response
    {
        $signer = $this->app->make('webhook.signer');
        $secret = $this->app->make('config')->get('webhooks.secret');
        
        $payload = $request->get_body();
        $signature = $request->get_header('X-ZipPicks-Signature');
        
        $isValid = $signer->verify($payload, $signature, $secret);
        
        return new \WP_REST_Response([
            'valid' => $isValid,
            'signature_received' => !empty($signature),
        ]);
    }

    /**
     * Register WP-CLI commands
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        \WP_CLI::add_command('zippicks audit:query', function ($args, $assoc_args) {
            $audit = $this->app->make('audit');
            
            $filters = [
                'event_type' => \WP_CLI\Utils\get_flag_value($assoc_args, 'event'),
                'user_id' => \WP_CLI\Utils\get_flag_value($assoc_args, 'user'),
                'date_from' => \WP_CLI\Utils\get_flag_value($assoc_args, 'from'),
                'date_to' => \WP_CLI\Utils\get_flag_value($assoc_args, 'to'),
            ];
            
            $results = $audit->query(
                array_filter($filters),
                \WP_CLI\Utils\get_flag_value($assoc_args, 'limit', 100)
            );
            
            if (empty($results)) {
                \WP_CLI::line('No audit entries found.');
                return;
            }
            
            $format = \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
            \WP_CLI\Utils\format_items($format, $results, ['id', 'event_type', 'user_id', 'created_at']);
        });

        \WP_CLI::add_command('zippicks audit:stats', function ($args, $assoc_args) {
            $audit = $this->app->make('audit');
            
            $stats = $audit->getStatistics([
                'period' => \WP_CLI\Utils\get_flag_value($assoc_args, 'period', '24h'),
            ]);
            
            \WP_CLI::line('Audit Statistics:');
            \WP_CLI::line('Period: ' . $stats['period']);
            \WP_CLI::line('Total Events: ' . $stats['total_events']);
            \WP_CLI::line('Failed Auths: ' . $stats['failed_auths']);
            
            if (!empty($stats['event_counts'])) {
                \WP_CLI::line("\nEvent Counts:");
                foreach ($stats['event_counts'] as $event) {
                    \WP_CLI::line(sprintf('  %s: %d', $event['event_type'], $event['count']));
                }
            }
        });

        \WP_CLI::add_command('zippicks audit:cleanup', function ($args, $assoc_args) {
            $audit = $this->app->make('audit');
            
            $days = \WP_CLI\Utils\get_flag_value($assoc_args, 'days', 90);
            
            \WP_CLI::line("Cleaning up audit logs older than {$days} days...");
            
            $deleted = $audit->cleanup($days);
            
            \WP_CLI::success("Deleted {$deleted} audit log entries.");
        });

        \WP_CLI::add_command('zippicks webhook:sign', function ($args, $assoc_args) {
            $signer = $this->app->make('webhook.signer');
            $secret = \WP_CLI\Utils\get_flag_value($assoc_args, 'secret', $this->app->make('config')->get('webhooks.secret'));
            
            if (empty($args[0])) {
                \WP_CLI::error('Payload required');
            }
            
            $payload = $args[0];
            $signature = $signer->sign($payload, $secret);
            
            \WP_CLI::line('Signature: ' . $signature['signature']);
            \WP_CLI::line('Header: ' . $signature['header']);
            \WP_CLI::line('Header Name: ' . $signature['header_name']);
        });
    }

    /**
     * Run audit cleanup
     *
     * @return void
     */
    public function runAuditCleanup(): void
    {
        $audit = $this->app->make('audit');
        $days = $this->app->make('config')->get('audit.retention_days', 90);
        
        $audit->cleanup($days);
    }

    /**
     * Add security headers
     *
     * @return void
     */
    public function addSecurityHeaders(): void
    {
        if (!is_admin()) {
            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
            
            if (is_ssl()) {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
            }
        }
    }
}