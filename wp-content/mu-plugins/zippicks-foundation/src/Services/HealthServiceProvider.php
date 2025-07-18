<?php
/**
 * Health Service Provider
 * 
 * Registers health check services and endpoints
 * 
 * @package ZipPicks\Foundation\Services
 * @since 2.0.0
 */

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Health\HealthCheck;
use ZipPicks\Foundation\Health\HealthCheckService;

class HealthServiceProvider extends ServiceProvider
{
    /**
     * Register services
     * 
     * @return void
     */
    public function register(): void
    {
        // Register the legacy health check (for backwards compatibility)
        $this->container->singleton('health', function($container) {
            return new HealthCheck();
        });
        
        // Register the new comprehensive health check service
        $this->container->singleton('health.service', function($container) {
            return new HealthCheckService();
        });
        
        // Alias for convenience
        $this->container->alias('health.service', HealthCheckService::class);
    }
    
    /**
     * Boot services
     * 
     * @return void
     */
    public function boot(): void
    {
        // Register health check endpoint
        add_action('rest_api_init', [$this, 'registerHealthEndpoints']);
        
        // Register admin notice for unhealthy status
        if (is_admin()) {
            add_action('admin_notices', [$this, 'displayHealthWarnings']);
        }
        
        // Register WP-CLI command if available
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('zippicks health', [$this, 'cliHealthCommand']);
        }
    }
    
    /**
     * Register REST API endpoints
     * 
     * @return void
     */
    public function registerHealthEndpoints(): void
    {
        // Public health endpoint (basic info only)
        register_rest_route('zippicks/v1', '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'handlePublicHealthEndpoint'],
            'permission_callback' => '__return_true', // Public access
            'args' => [
                'detailed' => [
                    'default' => false,
                    'validate_callback' => function($param) {
                        return is_bool($param) || $param === 'true' || $param === 'false';
                    },
                    'sanitize_callback' => function($param) {
                        return filter_var($param, FILTER_VALIDATE_BOOLEAN);
                    }
                ]
            ]
        ]);
        
        // Admin health endpoint (full details)
        register_rest_route('zippicks/v1', '/admin/health', [
            'methods' => 'GET',
            'callback' => [$this, 'handleAdminHealthEndpoint'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        // Individual check endpoint
        register_rest_route('zippicks/v1', '/health/(?P<check>[a-z_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'handleIndividualCheckEndpoint'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'args' => [
                'check' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-z_]+$/', $param);
                    }
                ]
            ]
        ]);
    }
    
    /**
     * Handle public health endpoint
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handlePublicHealthEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $healthService = $this->container->get('health.service');
        $detailed = $request->get_param('detailed');
        
        // Check if detailed view is allowed
        if ($detailed && !current_user_can('manage_options')) {
            $detailed = false;
        }
        
        $results = $healthService->getEndpointResponse($detailed);
        
        // Determine HTTP status code
        $httpStatus = match($results['status']) {
            'healthy' => 200,
            'degraded' => 200, // Still return 200 for degraded to not trigger alerts
            'unhealthy' => 503,
            default => 500
        };
        
        // Add cache headers
        $response = new \WP_REST_Response($results, $httpStatus);
        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->header('X-Health-Status', $results['status']);
        
        return $response;
    }
    
    /**
     * Handle admin health endpoint
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleAdminHealthEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $healthService = $this->container->get('health.service');
        $results = $healthService->check(false); // Don't use cache for admin
        
        $response = new \WP_REST_Response($results, 200);
        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        
        return $response;
    }
    
    /**
     * Handle individual check endpoint
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleIndividualCheckEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $checkName = $request->get_param('check');
        $healthService = $this->container->get('health.service');
        
        // Run full health check to get individual result
        $results = $healthService->check(false);
        
        if (!isset($results['checks'][$checkName])) {
            return new \WP_REST_Response([
                'error' => 'Check not found',
                'available_checks' => array_keys($results['checks'])
            ], 404);
        }
        
        $checkResult = $results['checks'][$checkName];
        $checkResult['check_name'] = $checkName;
        $checkResult['timestamp'] = time();
        
        return new \WP_REST_Response($checkResult, 200);
    }
    
    /**
     * Display health warnings in admin
     * 
     * @return void
     */
    public function displayHealthWarnings(): void
    {
        // Only show on dashboard and system pages
        $screen = get_current_screen();
        if (!in_array($screen->id, ['dashboard', 'tools', 'options-general', 'zippicks_page_health'])) {
            return;
        }
        
        // Check if user has dismissed the notice recently
        $dismissed = get_user_meta(get_current_user_id(), 'zippicks_health_notice_dismissed', true);
        if ($dismissed && $dismissed > (time() - DAY_IN_SECONDS)) {
            return;
        }
        
        $healthService = $this->container->get('health.service');
        $results = $healthService->check();
        
        if ($results['status'] === 'unhealthy') {
            $this->showHealthNotice('error', 'System Health Critical', $results);
        } elseif ($results['status'] === 'degraded') {
            $this->showHealthNotice('warning', 'System Health Degraded', $results);
        }
    }
    
    /**
     * Show health notice
     * 
     * @param string $type Notice type (error, warning)
     * @param string $title Notice title
     * @param array $results Health check results
     * @return void
     */
    protected function showHealthNotice(string $type, string $title, array $results): void
    {
        $unhealthyChecks = array_filter($results['checks'], function($check) {
            return $check['status'] === 'unhealthy';
        });
        
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible zippicks-health-notice" data-notice-id="health">
            <h3><?php echo esc_html($title); ?></h3>
            <p>
                <strong>Status:</strong> <?php echo esc_html(ucfirst($results['status'])); ?> | 
                <strong>Healthy:</strong> <?php echo esc_html($results['summary']['healthy']); ?>/<?php echo esc_html($results['summary']['total']); ?> checks
            </p>
            
            <?php if (!empty($unhealthyChecks)): ?>
                <p><strong>Failed Checks:</strong></p>
                <ul>
                    <?php foreach ($unhealthyChecks as $name => $check): ?>
                        <li>
                            <strong><?php echo esc_html(str_replace('_', ' ', ucfirst($name))); ?>:</strong> 
                            <?php echo esc_html($check['message']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <p>
                <a href="<?php echo esc_url(rest_url('zippicks/v1/admin/health')); ?>" class="button button-primary" target="_blank">
                    View Full Health Report
                </a>
                <a href="#" class="button dismiss-health-notice">
                    Dismiss for 24 hours
                </a>
            </p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.dismiss-health-notice').on('click', function(e) {
                e.preventDefault();
                $.post(ajaxurl, {
                    action: 'zippicks_dismiss_health_notice',
                    nonce: '<?php echo wp_create_nonce('dismiss_health_notice'); ?>'
                });
                $(this).closest('.notice').fadeOut();
            });
        });
        </script>
        <?php
    }
    
    /**
     * WP-CLI health command
     * 
     * @param array $args
     * @param array $assoc_args
     * @return void
     */
    public function cliHealthCommand($args, $assoc_args): void
    {
        $healthService = $this->container->get('health.service');
        $results = $healthService->check(false);
        
        // Display overall status
        $statusColor = match($results['status']) {
            'healthy' => '%g',
            'degraded' => '%y',
            'unhealthy' => '%r',
            default => '%w'
        };
        
        \WP_CLI::log(\WP_CLI::colorize("{$statusColor}System Status: " . strtoupper($results['status']) . "%n"));
        \WP_CLI::log("Environment: " . $results['environment']);
        \WP_CLI::log("Version: " . $results['version']);
        \WP_CLI::log("");
        
        // Display summary
        \WP_CLI::log("Health Check Summary:");
        \WP_CLI::log(sprintf(
            "  Total: %d | Healthy: %d | Degraded: %d | Unhealthy: %d",
            $results['summary']['total'],
            $results['summary']['healthy'],
            $results['summary']['degraded'],
            $results['summary']['unhealthy']
        ));
        \WP_CLI::log("");
        
        // Display individual checks
        if (isset($assoc_args['detailed'])) {
            \WP_CLI::log("Individual Checks:");
            foreach ($results['checks'] as $name => $check) {
                $checkColor = match($check['status']) {
                    'healthy' => '%g',
                    'degraded' => '%y',
                    'unhealthy' => '%r',
                    default => '%w'
                };
                
                \WP_CLI::log(sprintf(
                    "  %s: %s[%s]%s - %s (%.1fms)",
                    str_pad($name, 20),
                    \WP_CLI::colorize($checkColor),
                    strtoupper($check['status']),
                    \WP_CLI::colorize('%n'),
                    $check['message'],
                    $check['latency_ms'] ?? 0
                ));
                
                // Show metadata if verbose
                if (isset($assoc_args['verbose']) && !empty($check['metadata'])) {
                    foreach ($check['metadata'] as $key => $value) {
                        \WP_CLI::log(sprintf(
                            "    %s: %s",
                            $key,
                            is_array($value) ? json_encode($value) : $value
                        ));
                    }
                }
            }
        }
        
        // Exit with appropriate code
        if ($results['status'] === 'unhealthy') {
            \WP_CLI::error('System is unhealthy', false);
            exit(1);
        } elseif ($results['status'] === 'degraded') {
            \WP_CLI::warning('System is degraded');
        } else {
            \WP_CLI::success('System is healthy');
        }
    }
}