<?php
/**
 * Load Testing WordPress Admin Controller
 * 
 * Enterprise-grade load testing interface for WordPress admin
 * Provides real-time test execution, monitoring, and reporting
 *
 * @package ZipPicks\Foundation\Admin
 */

namespace ZipPicks\Foundation\Admin;

use ZipPicks\Foundation\Testing\Performance\LoadTestRunner;
use ZipPicks\Foundation\Logging\EnterpriseLogger;
use ZipPicks\Foundation\Core\Container;

class LoadTestingController
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
     * Load test runner
     *
     * @var LoadTestRunner
     */
    protected LoadTestRunner $loadTestRunner;

    /**
     * Menu slug
     *
     * @var string
     */
    protected string $menuSlug = 'zippicks-load-testing';

    /**
     * Create load testing controller
     *
     * @param Container $container
     * @param EnterpriseLogger $logger
     */
    public function __construct(Container $container, EnterpriseLogger $logger)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->loadTestRunner = $container->get('load.test.runner');
        
        $this->registerHooks();
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    protected function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_zippicks_run_load_test', [$this, 'handleRunLoadTest']);
        add_action('wp_ajax_zippicks_stop_load_test', [$this, 'handleStopLoadTest']);
        add_action('wp_ajax_zippicks_get_test_status', [$this, 'handleGetTestStatus']);
        add_action('wp_ajax_zippicks_get_test_history', [$this, 'handleGetTestHistory']);
        add_action('wp_ajax_zippicks_delete_test_result', [$this, 'handleDeleteTestResult']);
    }

    /**
     * Add admin menu
     *
     * @return void
     */
    public function addAdminMenu(): void
    {
        add_submenu_page(
            'zippicks-monitoring',
            'Load Testing',
            'Load Testing',
            'manage_options',
            $this->menuSlug,
            [$this, 'renderLoadTestingPage']
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook
     * @return void
     */
    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, $this->menuSlug) === false) {
            return;
        }

        // Enqueue JavaScript
        wp_enqueue_script(
            'zippicks-load-testing',
            plugin_dir_url(__FILE__) . 'assets/js/load-testing.js',
            ['jquery', 'chart-js'],
            '1.0.0',
            true
        );

        // Enqueue CSS
        wp_enqueue_style(
            'zippicks-load-testing',
            plugin_dir_url(__FILE__) . 'assets/css/load-testing.css',
            [],
            '1.0.0'
        );

        // Localize script with AJAX data
        wp_localize_script('zippicks-load-testing', 'zippicksLoadTesting', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_load_testing'),
            'refreshInterval' => 5000, // 5 seconds
            'strings' => [
                'runTest' => __('Run Test', 'zippicks'),
                'stopTest' => __('Stop Test', 'zippicks'),
                'testRunning' => __('Test Running...', 'zippicks'),
                'testCompleted' => __('Test Completed', 'zippicks'),
                'testFailed' => __('Test Failed', 'zippicks'),
                'confirmStop' => __('Are you sure you want to stop the running test?', 'zippicks'),
                'confirmDelete' => __('Are you sure you want to delete this test result?', 'zippicks')
            ]
        ]);
    }

    /**
     * Render load testing page
     *
     * @return void
     */
    public function renderLoadTestingPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'zippicks'));
        }

        $activeSessions = $this->loadTestRunner->getActiveSessions();
        $testHistory = $this->loadTestRunner->getTestHistory(['limit' => 10]);
        $availableScenarios = $this->loadTestRunner->getAvailableScenarios();

        include __DIR__ . '/views/load-testing-dashboard.php';
    }

    /**
     * Handle run load test AJAX request
     *
     * @return void
     */
    public function handleRunLoadTest(): void
    {
        check_ajax_referer('zippicks_load_testing', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $testSuite = sanitize_text_field($_POST['test_suite'] ?? 'api_endpoints');
            $config = $this->sanitizeTestConfig($_POST['config'] ?? []);

            // Start test in background
            $this->startBackgroundTest($testSuite, $config);

            wp_send_json_success([
                'message' => 'Load test started successfully',
                'test_suite' => $testSuite,
                'config' => $config
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to start load test', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            wp_send_json_error(['message' => 'Failed to start load test: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle stop load test AJAX request
     *
     * @return void
     */
    public function handleStopLoadTest(): void
    {
        check_ajax_referer('zippicks_load_testing', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $testId = sanitize_text_field($_POST['test_id'] ?? '');
            
            if (empty($testId)) {
                wp_send_json_error(['message' => 'Test ID is required']);
                return;
            }

            $success = $this->loadTestRunner->stopTest($testId);

            if ($success) {
                wp_send_json_success(['message' => 'Load test stopped successfully']);
            } else {
                wp_send_json_error(['message' => 'Failed to stop load test']);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to stop load test', [
                'test_id' => $_POST['test_id'] ?? '',
                'error' => $e->getMessage()
            ]);

            wp_send_json_error(['message' => 'Failed to stop load test: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle get test status AJAX request
     *
     * @return void
     */
    public function handleGetTestStatus(): void
    {
        check_ajax_referer('zippicks_load_testing', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $activeSessions = $this->loadTestRunner->getActiveSessions();
            $performanceTrends = $this->loadTestRunner->getPerformanceTrends('1h');

            wp_send_json_success([
                'active_sessions' => $activeSessions,
                'performance_trends' => $performanceTrends,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get test status', [
                'error' => $e->getMessage()
            ]);

            wp_send_json_error(['message' => 'Failed to get test status']);
        }
    }

    /**
     * Handle get test history AJAX request
     *
     * @return void
     */
    public function handleGetTestHistory(): void
    {
        check_ajax_referer('zippicks_load_testing', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $page = (int)($_POST['page'] ?? 1);
            $limit = (int)($_POST['limit'] ?? 20);
            $filters = $_POST['filters'] ?? [];

            $history = $this->loadTestRunner->getTestHistory(array_merge($filters, [
                'page' => $page,
                'limit' => $limit
            ]));

            wp_send_json_success($history);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get test history', [
                'error' => $e->getMessage()
            ]);

            wp_send_json_error(['message' => 'Failed to get test history']);
        }
    }

    /**
     * Handle delete test result AJAX request
     *
     * @return void
     */
    public function handleDeleteTestResult(): void
    {
        check_ajax_referer('zippicks_load_testing', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $testId = sanitize_text_field($_POST['test_id'] ?? '');
            
            if (empty($testId)) {
                wp_send_json_error(['message' => 'Test ID is required']);
                return;
            }

            $success = $this->deleteTestResult($testId);

            if ($success) {
                wp_send_json_success(['message' => 'Test result deleted successfully']);
            } else {
                wp_send_json_error(['message' => 'Failed to delete test result']);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete test result', [
                'test_id' => $_POST['test_id'] ?? '',
                'error' => $e->getMessage()
            ]);

            wp_send_json_error(['message' => 'Failed to delete test result']);
        }
    }

    /**
     * Sanitize test configuration
     *
     * @param array $config
     * @return array
     */
    protected function sanitizeTestConfig(array $config): array
    {
        $sanitized = [];

        // Numeric fields
        $numericFields = [
            'duration', 'concurrent_users', 'ramp_up_time', 'ramp_down_time',
            'target_rps', 'timeout', 'retry_attempts'
        ];

        foreach ($numericFields as $field) {
            if (isset($config[$field])) {
                $sanitized[$field] = (int)$config[$field];
            }
        }

        // Float fields
        $floatFields = ['peak_load_factor'];
        foreach ($floatFields as $field) {
            if (isset($config[$field])) {
                $sanitized[$field] = (float)$config[$field];
            }
        }

        // String fields
        $stringFields = ['base_url', 'auth_token'];
        foreach ($stringFields as $field) {
            if (isset($config[$field])) {
                $sanitized[$field] = sanitize_text_field($config[$field]);
            }
        }

        // Array fields
        if (isset($config['scenarios']) && is_array($config['scenarios'])) {
            $sanitized['scenarios'] = array_map('sanitize_text_field', $config['scenarios']);
        }

        if (isset($config['think_time']) && is_array($config['think_time'])) {
            $sanitized['think_time'] = [(float)$config['think_time'][0], (float)$config['think_time'][1]];
        }

        // Boolean fields
        $booleanFields = ['follow_redirects', 'verify_ssl'];
        foreach ($booleanFields as $field) {
            if (isset($config[$field])) {
                $sanitized[$field] = (bool)$config[$field];
            }
        }

        return $sanitized;
    }

    /**
     * Start background test
     *
     * @param string $testSuite
     * @param array $config
     * @return void
     */
    protected function startBackgroundTest(string $testSuite, array $config): void
    {
        // In production, this would use WordPress Cron or a proper queue system
        // For now, we'll simulate the background execution
        
        wp_schedule_single_event(time() + 5, 'zippicks_execute_load_test', [
            'test_suite' => $testSuite,
            'config' => $config,
            'user_id' => get_current_user_id()
        ]);

        add_action('zippicks_execute_load_test', [$this, 'executeLoadTestBackground'], 10, 3);
    }

    /**
     * Execute load test in background
     *
     * @param string $testSuite
     * @param array $config
     * @param int $userId
     * @return void
     */
    public function executeLoadTestBackground(string $testSuite, array $config, int $userId): void
    {
        try {
            $this->logger->info('Starting background load test', [
                'test_suite' => $testSuite,
                'user_id' => $userId
            ]);

            $results = $this->loadTestRunner->runLoadTest($testSuite, $config);

            $this->logger->info('Background load test completed', [
                'test_id' => $results['test_id'],
                'status' => $results['status'],
                'duration' => $results['duration']
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Background load test failed', [
                'test_suite' => $testSuite,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete test result
     *
     * @param string $testId
     * @return bool
     */
    protected function deleteTestResult(string $testId): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $wpdb->prefix . 'zippicks_load_tests',
            ['test_id' => $testId],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Get test result by ID
     *
     * @param string $testId
     * @return array|null
     */
    protected function getTestResult(string $testId): ?array
    {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zippicks_load_tests WHERE test_id = %s",
                $testId
            ),
            ARRAY_A
        );

        if ($result) {
            $result['config'] = json_decode($result['config'], true);
            $result['results'] = json_decode($result['results'], true);
        }

        return $result;
    }

    /**
     * Format test duration for display
     *
     * @param int $seconds
     * @return string
     */
    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        }
    }

    /**
     * Format number with appropriate suffix
     *
     * @param int $number
     * @return string
     */
    protected function formatNumber(int $number): string
    {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        } else {
            return (string)$number;
        }
    }

    /**
     * Get status badge HTML
     *
     * @param string $status
     * @return string
     */
    protected function getStatusBadge(string $status): string
    {
        $badges = [
            'running' => '<span class="badge badge-primary">Running</span>',
            'completed' => '<span class="badge badge-success">Completed</span>',
            'failed' => '<span class="badge badge-danger">Failed</span>',
            'stopped' => '<span class="badge badge-warning">Stopped</span>'
        ];

        return $badges[$status] ?? '<span class="badge badge-secondary">Unknown</span>';
    }
}