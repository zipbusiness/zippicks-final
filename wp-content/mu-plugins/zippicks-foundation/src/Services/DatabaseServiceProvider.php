<?php
/**
 * Database Service Provider
 *
 * @package ZipPicks\Foundation\Services
 */

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Database\ConnectionPool;
use ZipPicks\Foundation\Contracts\Database\ConnectionPoolInterface;

/**
 * Registers database services including connection pooling
 */
class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        // Register Connection Pool
        $this->app->singleton(ConnectionPool::class, function ($app) {
            return new ConnectionPool(
                $app->make('logger'),
                $app->make('telemetry'),
                $app->make('config')->get('database.pool', [])
            );
        });

        $this->app->alias(ConnectionPool::class, ConnectionPoolInterface::class);
        $this->app->alias(ConnectionPool::class, 'db.pool');

        // Register database configuration
        $this->registerDatabaseConfig();

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
        // Add REST endpoints for monitoring
        add_action('rest_api_init', [$this, 'registerEndpoints']);

        // Add periodic health checks
        if (!wp_next_scheduled('zippicks_db_pool_health_check')) {
            wp_schedule_event(time(), 'hourly', 'zippicks_db_pool_health_check');
        }
        add_action('zippicks_db_pool_health_check', [$this, 'performHealthCheck']);

        // Monitor database performance
        add_action('shutdown', [$this, 'logPerformanceMetrics']);
    }

    /**
     * Register database configuration
     *
     * @return void
     */
    protected function registerDatabaseConfig(): void
    {
        $config = [
            'pool' => [
                'max_connections' => 100,
                'min_connections' => [
                    'read' => 10,
                    'write' => 5,
                ],
                'connection_timeout' => 5,
                'wait_timeout' => 30,
                'idle_timeout' => 300,
                'health_check_interval' => 60,
                'persistent_connections' => false,
                'connections' => [
                    'write' => [
                        'host' => DB_HOST,
                        'port' => 3306,
                        'database' => DB_NAME,
                        'username' => DB_USER,
                        'password' => DB_PASSWORD,
                        'charset' => DB_CHARSET ?: 'utf8mb4',
                    ],
                    'read' => $this->getReadReplicas(),
                ],
            ],
        ];

        $this->app->make('config')->set('database', $config);
    }

    /**
     * Get read replica configurations
     *
     * @return array
     */
    protected function getReadReplicas(): array
    {
        $replicas = [];

        // Primary as fallback
        $primary = [
            'host' => DB_HOST,
            'port' => 3306,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'charset' => DB_CHARSET ?: 'utf8mb4',
        ];

        // Add configured read replicas
        if (defined('DB_READ_HOST_1') && DB_READ_HOST_1) {
            $replicas[] = array_merge($primary, ['host' => DB_READ_HOST_1]);
        }

        if (defined('DB_READ_HOST_2') && DB_READ_HOST_2) {
            $replicas[] = array_merge($primary, ['host' => DB_READ_HOST_2]);
        }

        // Fall back to primary if no replicas
        if (empty($replicas)) {
            $replicas[] = $primary;
        }

        return $replicas;
    }

    /**
     * Register REST API endpoints
     *
     * @return void
     */
    public function registerEndpoints(): void
    {
        register_rest_route('zippicks/v1', '/admin/database/pool/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'handlePoolStats'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Handle pool statistics endpoint
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handlePoolStats(\WP_REST_Request $request): \WP_REST_Response
    {
        $pool = $this->app->make('db.pool');
        $stats = $pool->getStatistics();
        
        return new \WP_REST_Response($stats);
    }

    /**
     * Register WP-CLI commands
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        \WP_CLI::add_command('zippicks db:pool:stats', function ($args, $assoc_args) {
            $pool = $this->app->make('db.pool');
            $stats = $pool->getStatistics();
            
            \WP_CLI::line('Database Connection Pool Statistics:');
            \WP_CLI::line('Total Connections: ' . $stats['total_connections']);
            \WP_CLI::line('Active Connections: ' . $stats['active_connections']);
            \WP_CLI::line('Wait Queue Size: ' . $stats['wait_queue_size']);
            
            foreach ($stats['pools'] as $type => $poolStats) {
                \WP_CLI::line("\n{$type} Pool:");
                \WP_CLI::line("  Size: " . $poolStats['size']);
                \WP_CLI::line("  Created: " . $poolStats['created']);
                \WP_CLI::line("  Reused: " . $poolStats['reused']);
                \WP_CLI::line("  Releases: " . $poolStats['releases']);
                \WP_CLI::line("  Failures: " . $poolStats['failures']);
            }
        });

        \WP_CLI::add_command('zippicks db:pool:reset', function ($args, $assoc_args) {
            $pool = $this->app->make('db.pool');
            
            \WP_CLI::line('Resetting database connection pool...');
            $pool->closeAll();
            \WP_CLI::success('Connection pool reset complete');
        });
    }

    /**
     * Perform health check on connection pool
     *
     * @return void
     */
    public function performHealthCheck(): void
    {
        try {
            $pool = $this->app->make('db.pool');
            $stats = $pool->getStatistics();
            
            // Log metrics
            $this->app->make('logger')->info('Database pool health check', $stats);
            
            // Check for issues
            if ($stats['wait_queue_size'] > 10) {
                $this->app->make('logger')->warning('High database wait queue', [
                    'size' => $stats['wait_queue_size'],
                ]);
            }
            
            if ($stats['active_connections'] >= ($stats['total_connections'] * 0.9)) {
                $this->app->make('logger')->warning('Database connection pool near capacity', [
                    'active' => $stats['active_connections'],
                    'total' => $stats['total_connections'],
                ]);
            }
            
        } catch (\Exception $e) {
            $this->app->make('logger')->error('Database pool health check failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log performance metrics on shutdown
     *
     * @return void
     */
    public function logPerformanceMetrics(): void
    {
        if (!$this->app->has('db.pool')) {
            return;
        }

        try {
            $pool = $this->app->make('db.pool');
            $stats = $pool->getStatistics();
            
            // Calculate reuse rate
            $totalRequests = 0;
            $totalReused = 0;
            
            foreach ($stats['pools'] as $poolStats) {
                $totalRequests += $poolStats['created'] + $poolStats['reused'];
                $totalReused += $poolStats['reused'];
            }
            
            $reuseRate = $totalRequests > 0 ? ($totalReused / $totalRequests) * 100 : 0;
            
            $this->app->make('logger')->debug('Database pool performance', [
                'reuse_rate' => round($reuseRate, 2) . '%',
                'total_requests' => $totalRequests,
                'stats' => $stats,
            ]);
            
        } catch (\Exception $e) {
            // Silently fail on shutdown
        }
    }
}