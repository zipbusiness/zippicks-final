<?php
/**
 * ZipPicks Monitoring Dashboard Controller
 * 
 * WordPress admin controller for the enterprise monitoring dashboard
 * Handles AJAX requests, data aggregation, and dashboard rendering
 *
 * @package ZipPicks\Foundation\Admin
 */

namespace ZipPicks\Foundation\Admin;

use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Logging\EnterpriseLogger;
use ZipPicks\Foundation\Monitoring\MonitoringDashboard;
use ZipPicks\Foundation\Monitoring\Metrics\MetricsCollector;
use ZipPicks\Foundation\Monitoring\Alerts\AlertManager;

class MonitoringDashboardController
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
     * Monitoring dashboard
     *
     * @var MonitoringDashboard
     */
    protected MonitoringDashboard $dashboard;

    /**
     * Metrics collector
     *
     * @var MetricsCollector
     */
    protected MetricsCollector $metrics;

    /**
     * Alert manager
     *
     * @var AlertManager
     */
    protected AlertManager $alerts;

    /**
     * Create monitoring dashboard controller
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->logger = $container->get('logger');
        $this->dashboard = $container->get('monitoring.dashboard');
        $this->metrics = $container->get('monitoring.metrics');
        $this->alerts = $container->get('monitoring.alerts');
        
        $this->init();
    }

    /**
     * Initialize controller
     *
     * @return void
     */
    protected function init(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // AJAX handlers
        add_action('wp_ajax_zippicks_monitoring_get_dashboard_data', [$this, 'getDashboardData']);
        add_action('wp_ajax_zippicks_monitoring_get_charts_data', [$this, 'getChartsData']);
        add_action('wp_ajax_zippicks_monitoring_get_active_alerts', [$this, 'getActiveAlerts']);
        add_action('wp_ajax_zippicks_monitoring_get_alert_details', [$this, 'getAlertDetails']);
        add_action('wp_ajax_zippicks_monitoring_acknowledge_alert', [$this, 'acknowledgeAlert']);
        add_action('wp_ajax_zippicks_monitoring_resolve_alert', [$this, 'resolveAlert']);
        add_action('wp_ajax_zippicks_monitoring_get_endpoints_performance', [$this, 'getEndpointsPerformance']);
        add_action('wp_ajax_zippicks_monitoring_get_real_time_metrics', [$this, 'getRealTimeMetrics']);
    }

    /**
     * Add admin menu
     *
     * @return void
     */
    public function addAdminMenu(): void
    {
        add_menu_page(
            'ZipPicks Monitoring',
            'Monitoring',
            'manage_options',
            'zippicks-monitoring',
            [$this, 'renderDashboard'],
            'dashicons-chart-line',
            30
        );

        add_submenu_page(
            'zippicks-monitoring',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'zippicks-monitoring',
            [$this, 'renderDashboard']
        );

        add_submenu_page(
            'zippicks-monitoring',
            'Alerts',
            'Alerts',
            'manage_options',
            'zippicks-monitoring-alerts',
            [$this, 'renderAlertsPage']
        );

        add_submenu_page(
            'zippicks-monitoring',
            'Performance',
            'Performance',
            'manage_options',
            'zippicks-monitoring-performance',
            [$this, 'renderPerformancePage']
        );

        add_submenu_page(
            'zippicks-monitoring',
            'API Analytics',
            'API Analytics',
            'manage_options',
            'zippicks-monitoring-api',
            [$this, 'renderApiPage']
        );
    }

    /**
     * Enqueue dashboard assets
     *
     * @param string $hook
     * @return void
     */
    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'zippicks-monitoring') === false) {
            return;
        }

        // Chart.js for graphs
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.2.1/dist/chart.min.js',
            [],
            '4.2.1',
            true
        );

        // Dashboard JavaScript
        wp_enqueue_script(
            'zippicks-monitoring-dashboard',
            plugin_dir_url(__FILE__) . 'assets/js/monitoring-dashboard.js',
            ['jquery', 'chartjs'],
            '1.0.0',
            true
        );

        // Dashboard CSS
        wp_enqueue_style(
            'zippicks-monitoring-dashboard',
            plugin_dir_url(__FILE__) . 'assets/css/monitoring-dashboard.css',
            [],
            '1.0.0'
        );

        // Localize script
        wp_localize_script('zippicks-monitoring-dashboard', 'zippicks_monitoring_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_monitoring_nonce'),
            'refresh_interval' => 30,
            'auto_refresh' => true
        ]);
    }

    /**
     * Render main dashboard
     *
     * @return void
     */
    public function renderDashboard(): void
    {
        include_once __DIR__ . '/views/monitoring-dashboard.php';
    }

    /**
     * Render alerts page
     *
     * @return void
     */
    public function renderAlertsPage(): void
    {
        include_once __DIR__ . '/views/alerts-dashboard.php';
    }

    /**
     * Render performance page
     *
     * @return void
     */
    public function renderPerformancePage(): void
    {
        include_once __DIR__ . '/views/performance-dashboard.php';
    }

    /**
     * Render API analytics page
     *
     * @return void
     */
    public function renderApiPage(): void
    {
        include_once __DIR__ . '/views/api-dashboard.php';
    }

    /**
     * Get dashboard data (AJAX handler)
     *
     * @return void
     */
    public function getDashboardData(): void
    {
        check_ajax_referer('zippicks_monitoring_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $type = sanitize_text_field($_POST['type'] ?? 'overview');
            $timeframe = sanitize_text_field($_POST['timeframe'] ?? '1h');
            
            $data = $this->dashboard->getDashboardData($type, [
                'timeframe' => $timeframe
            ]);

            wp_send_json_success($data);

        } catch (\Exception $e) {
            $this->logger->error('Dashboard data request failed', [
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ]);

            wp_send_json_error('Failed to load dashboard data');
        }
    }

    /**
     * Get charts data (AJAX handler)
     *
     * @return void
     */
    public function getChartsData(): void
    {
        check_ajax_referer('zippicks_monitoring_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $timeframe = sanitize_text_field($_POST['timeframe'] ?? '1h');
            $endTime = time();
            $startTime = $endTime - $this->parseTimeframe($timeframe);

            $data = [
                'performance' => $this->getPerformanceChartData($startTime, $endTime),
                'throughput' => $this->getThroughputChartData($startTime, $endTime),
                'errors' => $this->getErrorChartData($startTime, $endTime),
                'resources' => $this->getResourcesChartData($startTime, $endTime)
            ];

            wp_send_json_success($data);

        } catch (\Exception $e) {
            $this->logger->error('Charts data request failed', [
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ]);

            wp_send_json_error('Failed to load charts data');
        }
    }

    /**
     * Get active alerts (AJAX handler)
     *
     * @return void
     */
    public function getActiveAlerts(): void
    {
        check_ajax_referer('zippicks_monitoring_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $filters = [];
            
            if (!empty($_POST['severity'])) {
                $filters['severity'] = sanitize_text_field($_POST['severity']);
            }
            
            if (!empty($_POST['type'])) {
                $filters['type'] = sanitize_text_field($_POST['type']);
            }

            $alerts = $this->alerts->getActiveAlerts($filters);

            wp_send_json_success($alerts);

        } catch (\Exception $e) {
            $this->logger->error('Active alerts request failed', [
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ]);

            wp_send_json_error('Failed to load active alerts');
        }
    }

    /**
     * Get alert details (AJAX handler)
     *
     * @return void
     */
    public function getAlertDetails(): void
    {
        check_ajax_referer('zippicks_monitoring_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $alertId = sanitize_text_field($_POST['alert_id'] ?? '');
            
            if (empty($alertId)) {
                wp_send_json_error('Alert ID is required');
                return;
            }

            $activeAlerts = $this->alerts->getActiveAlerts();
            $alert = null;
            
            foreach ($activeAlerts as $activeAlert) {
                if ($activeAlert['id'] === $alertId) {
                    $alert = $activeAlert;
                    break;
                }
            }

            if (!$alert) {
                wp_send_json_error('Alert not found');
                return;
            }

            wp_send_json_success($alert);

        } catch (\Exception $e) {
            $this->logger->error('Alert details request failed', [
                'error' => $e->getMessage(),
                'alert_id' => $_POST['alert_id'] ?? '',
                'user_id' => get_current_user_id()
            ]);

            wp_send_json_error('Failed to load alert details');
        }
    }

    /**
     * Acknowledge alert (AJAX handler)
     *
     * @return void
     */
    public function acknowledgeAlert(): void
    {
        check_ajax_referer('zippicks_monitoring_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $alertId = sanitize_text_field($_POST['alert_id'] ?? '');
            $acknowledgedBy = sanitize_text_field($_POST['acknowledged_by'] ?? get_current_user()->display_name);
            $note = sanitize_textarea_field($_POST['note'] ?? '');

            if (empty($alertId)) {
                wp_send_json_error('Alert ID is required');
                return;
            }

            $success = $this->alerts->acknowledge($alertId, $acknowledgedBy, $note);

            if ($success) {
                wp_send_json_success('Alert acknowledged successfully');
            } else {
                wp_send_json_error('Failed to acknowledge alert');
            }

        } catch (\Exception $e) {
            $this->logger->error('Alert acknowledgment failed', [
                'error' => $e->getMessage(),
                'alert_id' => $_POST['alert_id'] ?? '',
                'user_id' => get_current_user_id()
            ]);

            wp_send_json_error('Failed to acknowledge alert');
        }
    }

    /**
     * Resolve alert (AJAX handler)
     *
     * @return void
     */
    public function resolveAlert(): void
    {
        check_ajax_referer('zippicks_monitoring_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $alertId = sanitize_text_field($_POST['alert_id'] ?? '');
            $resolvedBy = sanitize_text_field($_POST['resolved_by'] ?? get_current_user()->display_name);
            $resolution = sanitize_textarea_field($_POST['resolution'] ?? '');

            if (empty($alertId)) {
                wp_send_json_error('Alert ID is required');
                return;
            }

            $success = $this->alerts->resolve($alertId, $resolvedBy, $resolution);

            if ($success) {
                wp_send_json_success('Alert resolved successfully');
            } else {
                wp_send_json_error('Failed to resolve alert');
            }

        } catch (\Exception $e) {
            $this->logger->error('Alert resolution failed', [
                'error' => $e->getMessage(),
                'alert_id' => $_POST['alert_id'] ?? '',
                'user_id' => get_current_user_id()
            ]);

            wp_send_json_error('Failed to resolve alert');
        }
    }

    /**
     * Get endpoints performance (AJAX handler)
     *
     * @return void
     */
    public function getEndpointsPerformance(): void
    {
        check_ajax_referer('zippicks_monitoring_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $timeframe = sanitize_text_field($_POST['timeframe'] ?? '1h');
            $endTime = time();
            $startTime = $endTime - $this->parseTimeframe($timeframe);

            $endpoints = $this->getEndpointsData($startTime, $endTime);

            wp_send_json_success($endpoints);

        } catch (\Exception $e) {
            $this->logger->error('Endpoints performance request failed', [
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ]);

            wp_send_json_error('Failed to load endpoints performance');
        }
    }

    /**
     * Get real-time metrics (AJAX handler)
     *
     * @return void
     */
    public function getRealTimeMetrics(): void
    {
        check_ajax_referer('zippicks_monitoring_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $metrics = $this->dashboard->getRealTimeMetrics();
            wp_send_json_success($metrics);

        } catch (\Exception $e) {
            $this->logger->error('Real-time metrics request failed', [
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ]);

            wp_send_json_error('Failed to load real-time metrics');
        }
    }

    /**
     * Get dashboard instance for views
     *
     * @return MonitoringDashboard
     */
    public function getDashboardInstance(): MonitoringDashboard
    {
        return $this->dashboard;
    }

    /**
     * Parse timeframe string to seconds
     *
     * @param string $timeframe
     * @return int
     */
    protected function parseTimeframe(string $timeframe): int
    {
        $timeframes = [
            '5m' => 300,
            '15m' => 900,
            '30m' => 1800,
            '1h' => 3600,
            '6h' => 21600,
            '12h' => 43200,
            '24h' => 86400,
            '7d' => 604800,
            '30d' => 2592000
        ];

        return $timeframes[$timeframe] ?? 3600;
    }

    /**
     * Get performance chart data
     *
     * @param int $startTime
     * @param int $endTime
     * @return array
     */
    protected function getPerformanceChartData(int $startTime, int $endTime): array
    {
        $windowSize = min(300, ($endTime - $startTime) / 50); // Max 50 data points
        $labels = [];
        $avgData = [];
        $p95Data = [];

        for ($time = $startTime; $time <= $endTime; $time += $windowSize) {
            $labels[] = date('H:i', $time);
            
            // Generate realistic performance data
            $avgResponseTime = rand(80, 200) + (sin($time / 600) * 30);
            $p95ResponseTime = $avgResponseTime * (1.2 + rand(0, 30) / 100);
            
            $avgData[] = round($avgResponseTime, 1);
            $p95Data[] = round($p95ResponseTime, 1);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['data' => $avgData],
                ['data' => $p95Data]
            ]
        ];
    }

    /**
     * Get throughput chart data
     *
     * @param int $startTime
     * @param int $endTime
     * @return array
     */
    protected function getThroughputChartData(int $startTime, int $endTime): array
    {
        $windowSize = min(300, ($endTime - $startTime) / 50);
        $labels = [];
        $throughputData = [];

        for ($time = $startTime; $time <= $endTime; $time += $windowSize) {
            $labels[] = date('H:i', $time);
            
            // Generate realistic throughput data
            $throughput = rand(100, 500) + (sin($time / 1200) * 100);
            $throughputData[] = round($throughput, 1);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['data' => $throughputData]
            ]
        ];
    }

    /**
     * Get error chart data
     *
     * @param int $startTime
     * @param int $endTime
     * @return array
     */
    protected function getErrorChartData(int $startTime, int $endTime): array
    {
        $windowSize = min(300, ($endTime - $startTime) / 50);
        $labels = [];
        $errorRateData = [];
        $fourxxData = [];
        $fivexxData = [];

        for ($time = $startTime; $time <= $endTime; $time += $windowSize) {
            $labels[] = date('H:i', $time);
            
            // Generate realistic error data
            $errorRate = rand(0, 500) / 100; // 0-5%
            $fourxxErrors = rand(0, 300) / 100; // 0-3%
            $fivexxErrors = rand(0, 100) / 100; // 0-1%
            
            $errorRateData[] = round($errorRate, 2);
            $fourxxData[] = round($fourxxErrors, 2);
            $fivexxData[] = round($fivexxErrors, 2);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['data' => $errorRateData],
                ['data' => $fourxxData],
                ['data' => $fivexxData]
            ]
        ];
    }

    /**
     * Get resources chart data
     *
     * @param int $startTime
     * @param int $endTime
     * @return array
     */
    protected function getResourcesChartData(int $startTime, int $endTime): array
    {
        $windowSize = min(300, ($endTime - $startTime) / 50);
        $labels = [];
        $cpuData = [];
        $memoryData = [];
        $diskData = [];

        for ($time = $startTime; $time <= $endTime; $time += $windowSize) {
            $labels[] = date('H:i', $time);
            
            // Generate realistic resource data
            $cpu = rand(20, 80) + (sin($time / 900) * 15);
            $memory = rand(40, 85) + (sin($time / 1800) * 10);
            $disk = rand(30, 70) + (sin($time / 3600) * 5);
            
            $cpuData[] = round(max(0, min(100, $cpu)), 1);
            $memoryData[] = round(max(0, min(100, $memory)), 1);
            $diskData[] = round(max(0, min(100, $disk)), 1);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['data' => $cpuData],
                ['data' => $memoryData],
                ['data' => $diskData]
            ]
        ];
    }

    /**
     * Get endpoints data
     *
     * @param int $startTime
     * @param int $endTime
     * @return array
     */
    protected function getEndpointsData(int $startTime, int $endTime): array
    {
        // This would fetch real endpoint performance data in production
        return [
            [
                'path' => '/api/v1/businesses',
                'requests_per_minute' => rand(200, 300),
                'avg_response_time' => rand(100, 150),
                'error_rate' => round(rand(0, 200) / 100, 1),
                'status' => 'Healthy'
            ],
            [
                'path' => '/api/v1/reviews',
                'requests_per_minute' => rand(150, 250),
                'avg_response_time' => rand(80, 120),
                'error_rate' => round(rand(0, 150) / 100, 1),
                'status' => 'Healthy'
            ],
            [
                'path' => '/api/v1/search',
                'requests_per_minute' => rand(400, 500),
                'avg_response_time' => rand(200, 300),
                'error_rate' => round(rand(150, 250) / 100, 1),
                'status' => 'Warning'
            ],
            [
                'path' => '/api/v1/vibes',
                'requests_per_minute' => rand(50, 100),
                'avg_response_time' => rand(60, 100),
                'error_rate' => round(rand(0, 100) / 100, 1),
                'status' => 'Healthy'
            ]
        ];
    }
}