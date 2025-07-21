<?php
/**
 * Queue Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 * @version 2.0.0 Added enterprise queue system
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Queue\QueueManager;
use ZipPicks\Foundation\Queue\Worker;
use ZipPicks\Foundation\Queue\QueueMonitor;
use ZipPicks\Foundation\Queue\Failed\DatabaseFailedJobProvider;
use ZipPicks\Foundation\Database\MigrationRunner;
use ZipPicks\Foundation\Contracts\Queue\QueueManagerInterface;
use ZipPicks\Foundation\Contracts\Queue\WorkerInterface;
use ZipPicks\Foundation\Contracts\Queue\QueueMonitorInterface;
use ZipPicks\Foundation\Contracts\Queue\FailedJobProviderInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheInterface;

/**
 * Provides queue services to the foundation
 */
class QueueServiceProvider extends ServiceProvider
{
    /**
     * Register the queue services
     * 
     * @return void
     */
    public function register(): void
    {
        $this->registerQueueManager();
        $this->registerFailedJobProvider();
        $this->registerQueueMonitor();
        $this->registerWorker();
        $this->registerCommands();
    }

    /**
     * Bootstrap the queue services
     * 
     * @return void
     */
    public function boot(): void
    {
        $this->runMigrations();
        $this->registerHealthChecks();
        $this->registerEventListeners();
        $this->registerAdminDashboard();
        
        // Log queue service initialization
        if ($this->has('logger')) {
            $logger = $this->get('logger');
            $config = $this->get('config')->get('queue', []);
            
            $logger->channel('queue')->info('Queue service initialized', [
                'default_driver' => $config['default'] ?? 'sync',
                'connections' => array_keys($config['connections'] ?? [])
            ]);
        }
    }

    /**
     * Register the queue manager
     * 
     * @return void
     */
    protected function registerQueueManager(): void
    {
        $this->singleton(QueueManagerInterface::class, function() {
            $config = $this->get('config')->get('queue', []);
            $logger = $this->has(LoggerInterface::class) 
                ? $this->get(LoggerInterface::class) 
                : null;
            
            return new QueueManager(
                $this->foundation->getContainer(),
                $config,
                $logger
            );
        });

        // Register alias
        $this->alias('queue', QueueManagerInterface::class);
        $this->alias(QueueManager::class, QueueManagerInterface::class);
    }

    /**
     * Register failed job provider
     * 
     * @return void
     */
    protected function registerFailedJobProvider(): void
    {
        $this->singleton(FailedJobProviderInterface::class, function() {
            global $wpdb;
            
            $config = $this->get('config')->get('queue.failed', []);
            $table = $wpdb->prefix . ($config['table'] ?? 'zippicks_failed_jobs');
            $logger = $this->has(LoggerInterface::class) 
                ? $this->get(LoggerInterface::class) 
                : null;
            
            $provider = new DatabaseFailedJobProvider($wpdb, $table, $logger);
            
            // Set queue manager if available
            if ($this->has(QueueManagerInterface::class)) {
                $provider->setQueueManager($this->get(QueueManagerInterface::class));
            }
            
            return $provider;
        });

        // Register alias
        $this->alias('queue.failed', FailedJobProviderInterface::class);
    }

    /**
     * Register queue monitor
     * 
     * @return void
     */
    protected function registerQueueMonitor(): void
    {
        $this->singleton(QueueMonitorInterface::class, function() {
            global $wpdb;
            
            $cache = $this->has(CacheInterface::class) 
                ? $this->get(CacheInterface::class) 
                : null;
            $logger = $this->has(LoggerInterface::class) 
                ? $this->get(LoggerInterface::class) 
                : null;
            
            return new QueueMonitor($wpdb, $cache, $logger);
        });

        // Register alias
        $this->alias('queue.monitor', QueueMonitorInterface::class);
    }

    /**
     * Register worker
     * 
     * @return void
     */
    protected function registerWorker(): void
    {
        $this->bind(WorkerInterface::class, function() {
            $manager = $this->get(QueueManagerInterface::class);
            $failedProvider = $this->get(FailedJobProviderInterface::class);
            $monitor = $this->has(QueueMonitorInterface::class) 
                ? $this->get(QueueMonitorInterface::class) 
                : null;
            $logger = $this->has(LoggerInterface::class) 
                ? $this->get(LoggerInterface::class) 
                : null;
            $performanceMonitor = $this->has('performance_monitor') 
                ? $this->get('performance_monitor') 
                : null;
            
            return new Worker(
                $manager,
                $failedProvider,
                $monitor,
                $logger,
                $performanceMonitor
            );
        });

        // Register alias
        $this->alias('queue.worker', WorkerInterface::class);
    }

    /**
     * Register WP-CLI commands
     * 
     * @return void
     */
    protected function registerCommands(): void
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        // Register queue worker command
        \WP_CLI::add_command('queue:work', function($args, $assoc_args) {
            $worker = $this->get(WorkerInterface::class);
            $options = new \ZipPicks\Foundation\Contracts\Queue\WorkerOptions(
                sleep: (int) ($assoc_args['sleep'] ?? 3),
                tries: (int) ($assoc_args['tries'] ?? 3),
                timeout: (int) ($assoc_args['timeout'] ?? 60),
                maxJobs: (int) ($assoc_args['max-jobs'] ?? 0),
                maxTime: (int) ($assoc_args['max-time'] ?? 0),
                memory: (int) ($assoc_args['memory'] ?? 128),
                force: isset($assoc_args['force']),
                stopWhenEmpty: isset($assoc_args['stop-when-empty']),
                name: $assoc_args['name'] ?? null
            );

            $connection = $assoc_args['connection'] ?? 'default';
            $queue = $assoc_args['queue'] ?? 'default';

            \WP_CLI::log("Starting queue worker...");
            $worker->daemon($connection, $queue, $options);
        });

        // Register retry command
        \WP_CLI::add_command('queue:retry', function($args, $assoc_args) {
            $failedProvider = $this->get(FailedJobProviderInterface::class);
            
            if (isset($args[0])) {
                // Retry specific job
                $success = $failedProvider->retry($args[0]);
                if ($success) {
                    \WP_CLI::success("Job {$args[0]} has been pushed back onto the queue.");
                } else {
                    \WP_CLI::error("Failed to retry job {$args[0]}");
                }
            } else {
                // Retry all failed jobs
                $count = $failedProvider->retryAll();
                \WP_CLI::success("Pushed {$count} jobs back onto the queue.");
            }
        });

        // Register table migration command
        \WP_CLI::add_command('queue:table', function() {
            $this->runMigrations(true);
            \WP_CLI::success("Queue tables created successfully.");
        });
    }

    /**
     * Run queue migrations
     * 
     * @param bool $force Force migration
     * @return void
     */
    protected function runMigrations(bool $force = false): void
    {
        $migrationsPath = dirname(__DIR__, 2) . '/database/migrations';
        $logger = $this->has(LoggerInterface::class) 
            ? $this->get(LoggerInterface::class) 
            : null;
        
        $runner = new MigrationRunner($migrationsPath, $logger);
        
        // Check if migrations have been run
        if (!$force && $runner->isMigrationExecuted('create_queue_tables')) {
            return;
        }
        
        try {
            $executed = $runner->run();
            
            if (!empty($executed)) {
                $logger?->info('Queue migrations executed', [
                    'migrations' => $executed
                ]);
            }
        } catch (\Throwable $e) {
            $logger?->error('Queue migration failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Register health checks
     * 
     * @return void
     */
    protected function registerHealthChecks(): void
    {
        if (!$this->has('health')) {
            return;
        }

        $health = $this->get('health');
        
        // Register queue health check
        $health->addCheck('queue', function() {
            $monitor = $this->get(QueueMonitorInterface::class);
            $status = $monitor->getHealthStatus();
            
            return [
                'status' => $status['healthy'] ? 'healthy' : 'unhealthy',
                'message' => $status['status'],
                'data' => [
                    'checks' => $status['checks'],
                    'recommendations' => $status['recommendations']
                ]
            ];
        });

        // Register worker health check
        $health->addCheck('queue_workers', function() {
            $monitor = $this->get(QueueMonitorInterface::class);
            $metrics = $monitor->getWorkerMetrics();
            
            $healthy = $metrics['active_workers'] > 0;
            
            return [
                'status' => $healthy ? 'healthy' : 'unhealthy',
                'message' => $healthy 
                    ? "{$metrics['active_workers']} workers active" 
                    : 'No active workers',
                'data' => $metrics
            ];
        });
    }

    /**
     * Register event listeners
     * 
     * @return void
     */
    protected function registerEventListeners(): void
    {
        // Register alert handlers
        if ($this->has(QueueMonitorInterface::class) && $this->has('events')) {
            $monitor = $this->get(QueueMonitorInterface::class);
            $events = $this->get('events');
            
            // Register alert handler that dispatches events
            $monitor->registerAlertHandler(function($alert) use ($events) {
                $events->dispatch('queue.alert', $alert);
            });
        }
    }
    
    /**
     * Register admin dashboard
     * 
     * @return void
     */
    protected function registerAdminDashboard(): void
    {
        if (!is_admin()) {
            return;
        }
        
        // Only register if we have the required services
        if (!$this->has(QueueManagerInterface::class) || 
            !$this->has(QueueMonitorInterface::class) ||
            !$this->has(FailedJobProviderInterface::class)) {
            return;
        }
        
        // Create and register dashboard
        $dashboard = new \ZipPicks\Foundation\Admin\QueueDashboard(
            $this->get(QueueManagerInterface::class),
            $this->get(QueueMonitorInterface::class),
            $this->get(FailedJobProviderInterface::class),
            $this->has(LoggerInterface::class) ? $this->get(LoggerInterface::class) : null
        );
        
        $dashboard->register();
    }
}