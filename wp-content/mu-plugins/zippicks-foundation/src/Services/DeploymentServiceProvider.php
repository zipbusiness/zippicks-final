<?php
/**
 * Deployment Service Provider
 *
 * @package ZipPicks\Foundation\Services
 */

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Deployment\BlueGreenDeployer;
use ZipPicks\Foundation\Contracts\Deployment\DeployerInterface;

/**
 * Registers deployment services
 */
class DeploymentServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        // Register Blue-Green Deployer
        $this->app->singleton(BlueGreenDeployer::class, function ($app) {
            return new BlueGreenDeployer(
                $app->make('health'),
                $app->make('telemetry'),
                $app->make('logger'),
                $app->make('config')->get('deployment', [])
            );
        });

        // Bind to interface
        $this->app->alias(BlueGreenDeployer::class, DeployerInterface::class);
        $this->app->alias(BlueGreenDeployer::class, 'deployer');

        // Register WP-CLI commands if available
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
        // Register deployment cleanup cron
        add_action('zippicks_cleanup_deployment', [$this, 'cleanupDeployment']);

        // Add deployment endpoints
        add_action('rest_api_init', [$this, 'registerEndpoints']);
    }

    /**
     * Register WP-CLI commands
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        \WP_CLI::add_command('zippicks deploy', function ($args, $assoc_args) {
            $deployer = $this->app->make('deployer');
            $version = $args[0] ?? 'latest';
            
            try {
                \WP_CLI::line('Starting blue-green deployment...');
                
                $result = $deployer->deploy($version, [
                    'canary' => \WP_CLI\Utils\get_flag_value($assoc_args, 'canary', false),
                    'canary_percentage' => \WP_CLI\Utils\get_flag_value($assoc_args, 'canary-percentage', 10),
                    'monitor_duration' => \WP_CLI\Utils\get_flag_value($assoc_args, 'monitor-duration', 300),
                    'auto_rollback' => \WP_CLI\Utils\get_flag_value($assoc_args, 'auto-rollback', true),
                ]);
                
                \WP_CLI::success('Deployment completed successfully');
                \WP_CLI::line('Deployment ID: ' . $result['deployment_id']);
                \WP_CLI::line('Environment: ' . $result['environment']);
                
            } catch (\Exception $e) {
                \WP_CLI::error('Deployment failed: ' . $e->getMessage());
            }
        });

        \WP_CLI::add_command('zippicks rollback', function ($args, $assoc_args) {
            $deployer = $this->app->make('deployer');
            
            try {
                \WP_CLI::line('Starting rollback...');
                
                if ($deployer->rollback()) {
                    \WP_CLI::success('Rollback completed successfully');
                } else {
                    \WP_CLI::error('Rollback failed');
                }
                
            } catch (\Exception $e) {
                \WP_CLI::error('Rollback failed: ' . $e->getMessage());
            }
        });

        \WP_CLI::add_command('zippicks deployment:status', function ($args, $assoc_args) {
            $deployer = $this->app->make('deployer');
            $deploymentId = $args[0] ?? null;
            
            if (!$deploymentId) {
                \WP_CLI::error('Deployment ID required');
            }
            
            $status = $deployer->getStatus($deploymentId);
            
            \WP_CLI::line('Deployment Status:');
            \WP_CLI::line('ID: ' . $status['id']);
            \WP_CLI::line('Status: ' . $status['status']);
            \WP_CLI::line('Environment: ' . $status['environment']);
            
            if (isset($status['metrics'])) {
                \WP_CLI::line("\nMetrics:");
                foreach ($status['metrics'] as $key => $value) {
                    \WP_CLI::line("  {$key}: {$value}");
                }
            }
        });
    }

    /**
     * Register REST API endpoints
     *
     * @return void
     */
    public function registerEndpoints(): void
    {
        register_rest_route('zippicks/v1', '/deployment/deploy', [
            'methods' => 'POST',
            'callback' => [$this, 'handleDeploy'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'version' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'canary' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'canary_percentage' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 50,
                ],
            ],
        ]);

        register_rest_route('zippicks/v1', '/deployment/rollback', [
            'methods' => 'POST',
            'callback' => [$this, 'handleRollback'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('zippicks/v1', '/deployment/status/(?P<id>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'handleStatus'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Handle deploy endpoint
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleDeploy(\WP_REST_Request $request)
    {
        try {
            $deployer = $this->app->make('deployer');
            
            $result = $deployer->deploy(
                $request->get_param('version'),
                [
                    'canary' => $request->get_param('canary'),
                    'canary_percentage' => $request->get_param('canary_percentage'),
                ]
            );
            
            return new \WP_REST_Response($result, 200);
            
        } catch (\Exception $e) {
            return new \WP_Error(
                'deployment_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Handle rollback endpoint
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleRollback(\WP_REST_Request $request)
    {
        try {
            $deployer = $this->app->make('deployer');
            
            $success = $deployer->rollback();
            
            return new \WP_REST_Response([
                'success' => $success,
                'message' => $success ? 'Rollback completed' : 'Rollback failed',
            ], $success ? 200 : 500);
            
        } catch (\Exception $e) {
            return new \WP_Error(
                'rollback_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Handle status endpoint
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleStatus(\WP_REST_Request $request)
    {
        $deployer = $this->app->make('deployer');
        $status = $deployer->getStatus($request->get_param('id'));
        
        return new \WP_REST_Response($status, 200);
    }

    /**
     * Cleanup old deployment
     *
     * @param string $environment
     * @return void
     */
    public function cleanupDeployment(string $environment): void
    {
        $this->app->make('logger')->info('Cleaning up deployment', [
            'environment' => $environment,
        ]);

        // Scale down old environment
        $command = sprintf(
            'kubectl scale deployment zippicks-api-%s --replicas=0 -n %s',
            $environment,
            $this->app->make('config')->get('deployment.namespace', 'zippicks-prod')
        );

        exec($command);
    }
}