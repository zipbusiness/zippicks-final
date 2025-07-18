<?php
/**
 * Monitoring Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Core\PerformanceMonitor;
use ZipPicks\Foundation\Health\HealthCheck;
use ZipPicks\Foundation\Testing\Performance\LoadTestRunner;
use ZipPicks\Foundation\Operations\RunbookManager;
use ZipPicks\Foundation\Monitoring\MonitoringDashboard;
use ZipPicks\Foundation\Monitoring\Metrics\MetricsCollector;
use ZipPicks\Foundation\Monitoring\Alerts\AlertManager;
use ZipPicks\Foundation\Admin\LoadTestingController;

/**
 * Provides monitoring and health check services
 */
class MonitoringServiceProvider extends ServiceProvider
{
    /**
     * Register monitoring services
     * 
     * @return void
     */
    public function register(): void
    {
        // Register performance monitor as singleton
        $this->singleton(PerformanceMonitor::class, function() {
            return PerformanceMonitor::getInstance();
        });
        
        // Register health check service
        $this->singleton(HealthCheck::class, function() {
            $healthCheck = new HealthCheck();
            
            // Register default checks
            foreach ($healthCheck->getDefaultChecks() as $name => $check) {
                $healthCheck->registerCheck($name, $check, [
                    'critical' => in_array($name, ['database', 'logging']),
                ]);
            }
            
            return $healthCheck;
        });

        // Register Phase 6 components
        $this->registerPhase6Components();
        
        // Create aliases
        $container = $this->foundation->getContainer();
        if (!$container->has('monitor')) {
            $container->alias('monitor', PerformanceMonitor::class);
        }
        if (!$container->has('health')) {
            $container->alias('health', HealthCheck::class);
        }
    }

    /**
     * Register Phase 6 enterprise components
     * 
     * @return void
     */
    private function registerPhase6Components(): void
    {
        // Register Load Test Runner
        $this->singleton(LoadTestRunner::class, function($container) {
            return new LoadTestRunner($container, $container->get('logger'));
        });

        // Register Runbook Manager
        $this->singleton(RunbookManager::class, function($container) {
            return new RunbookManager($container, $container->get('logger'));
        });

        // Register Monitoring Dashboard
        $this->singleton(MonitoringDashboard::class, function($container) {
            return new MonitoringDashboard(
                $container,
                $container->get('logger'),
                $container->get('cache'),
                $container->get(MetricsCollector::class),
                $container->get(AlertManager::class)
            );
        });

        // Register Metrics Collector
        $this->singleton(MetricsCollector::class, function($container) {
            return new MetricsCollector($container->get('logger'));
        });

        // Register Alert Manager
        $this->singleton(AlertManager::class, function($container) {
            return new AlertManager($container->get('logger'));
        });

        // Create convenient aliases
        $container = $this->foundation->getContainer();
        $container->alias('load.test.runner', LoadTestRunner::class);
        $container->alias('runbook.manager', RunbookManager::class);
        $container->alias('monitoring.dashboard', MonitoringDashboard::class);
        $container->alias('metrics.collector', MetricsCollector::class);
        $container->alias('alert.manager', AlertManager::class);
    }

    /**
     * Bootstrap monitoring services
     * 
     * @return void
     */
    public function boot(): void
    {
        // Get services
        $monitor = $this->get('monitor');
        $health = $this->get('health');
        
        // Register WordPress hooks for monitoring
        $monitor->registerWordPressHooks();
        
        // Register health check endpoint
        $health->registerEndpoint();
        
        // Add admin bar indicator
        $this->registerAdminBarIndicator();

        // Bootstrap Phase 6 components
        $this->bootPhase6Components();
        
        // Log monitoring service initialization
        if ($this->has('logger')) {
            $logger = $this->get('logger');
            $logger->info('Monitoring services initialized', [
                'performance_monitor' => true,
                'health_check' => true,
                'health_endpoint' => rest_url('zippicks/v1/health'),
                'load_testing' => true,
                'runbooks' => true,
                'enterprise_monitoring' => true
            ]);
        }
    }

    /**
     * Bootstrap Phase 6 enterprise components
     * 
     * @return void
     */
    private function bootPhase6Components(): void
    {
        // Initialize Load Testing Controller
        if (is_admin()) {
            $loadTestingController = new LoadTestingController(
                $this->foundation->getContainer(),
                $this->get('logger')
            );
        }

        // Register database migration
        add_action('init', function() {
            $migrationFile = dirname(__DIR__, 2) . '/database/migrations/create_load_testing_tables.php';
            if (file_exists($migrationFile)) {
                require_once $migrationFile;
            }
        });

        // Add WordPress admin menu integration
        add_action('admin_menu', function() {
            if (!current_user_can('manage_options')) {
                return;
            }

            // Add top-level monitoring menu if it doesn't exist
            $menu_exists = false;
            global $menu;
            foreach ($menu as $menu_item) {
                if (isset($menu_item[2]) && $menu_item[2] === 'zippicks-monitoring') {
                    $menu_exists = true;
                    break;
                }
            }

            if (!$menu_exists) {
                add_menu_page(
                    'ZipPicks Monitoring',
                    'Monitoring',
                    'manage_options',
                    'zippicks-monitoring',
                    [$this, 'renderMonitoringDashboard'],
                    'dashicons-chart-line',
                    30
                );
            }

            // Add runbooks submenu
            add_submenu_page(
                'zippicks-monitoring',
                'Runbooks',
                'Runbooks',
                'manage_options',
                'zippicks-runbooks',
                [$this, 'renderRunbooksPage']
            );
        });
    }

    /**
     * Render monitoring dashboard page
     * 
     * @return void
     */
    public function renderMonitoringDashboard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        echo '<div class="wrap">';
        echo '<h1>ZipPicks Enterprise Monitoring</h1>';
        echo '<p>Monitoring dashboard content will be rendered here.</p>';
        echo '</div>';
    }

    /**
     * Render runbooks page
     * 
     * @return void
     */
    public function renderRunbooksPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $runbookManager = $this->get('runbook.manager');
        $availableRunbooks = $runbookManager->getAvailableRunbooks();
        $executionHistory = $runbookManager->getExecutionHistory(['limit' => 10]);

        echo '<div class="wrap">';
        echo '<h1>Production Runbooks</h1>';
        echo '<div class="runbooks-interface">';
        echo '<h2>Available Runbooks</h2>';
        echo '<ul>';
        foreach ($availableRunbooks as $id => $runbook) {
            echo '<li><strong>' . esc_html($runbook['name']) . '</strong> - ' . esc_html($runbook['description']) . '</li>';
        }
        echo '</ul>';
        echo '<h2>Recent Executions</h2>';
        echo '<p>' . count($executionHistory) . ' recent executions found.</p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Register admin bar health indicator
     * 
     * @return void
     */
    private function registerAdminBarIndicator(): void
    {
        add_action('admin_bar_menu', function($wp_admin_bar) {
            if (!current_user_can('manage_options')) {
                return;
            }
            
            $health = $this->get('health');
            $results = $health->runChecks();
            
            $icon = match($results['status']) {
                'healthy' => '✅',
                'degraded' => '⚠️',
                'unhealthy' => '❌',
                default => '❓',
            };
            
            $wp_admin_bar->add_node([
                'id' => 'zippicks-health',
                'title' => sprintf(
                    '%s Health: %s (%d/%d)',
                    $icon,
                    ucfirst($results['status']),
                    $results['summary']['healthy'],
                    $results['summary']['total']
                ),
                'href' => admin_url('admin.php?page=zippicks-health'),
                'meta' => [
                    'class' => 'zippicks-health-' . $results['status'],
                    'title' => 'ZipPicks Platform Health',
                ],
            ]);
            
            // Add submenu with individual checks
            foreach ($results['checks'] as $name => $check) {
                $checkIcon = match($check['status']) {
                    'healthy' => '✅',
                    'degraded' => '⚠️',
                    'unhealthy' => '❌',
                    default => '❓',
                };
                
                $wp_admin_bar->add_node([
                    'parent' => 'zippicks-health',
                    'id' => 'zippicks-health-' . $name,
                    'title' => sprintf('%s %s: %s', $checkIcon, ucfirst($name), $check['message']),
                ]);
            }
        }, 100);
    }
}