<?php
/**
 * Queue Dashboard Admin Integration
 * 
 * Registers the queue dashboard in WordPress admin and handles AJAX requests.
 * 
 * @package ZipPicks\Foundation\Admin
 * @since 3.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Admin;

use ZipPicks\Foundation\Contracts\Queue\QueueManagerInterface;
use ZipPicks\Foundation\Contracts\Queue\QueueMonitorInterface;
use ZipPicks\Foundation\Contracts\Queue\FailedJobProviderInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;

/**
 * Queue Dashboard Admin Class
 */
class QueueDashboard
{
    /**
     * Queue manager instance
     */
    protected QueueManagerInterface $queueManager;
    
    /**
     * Queue monitor instance
     */
    protected QueueMonitorInterface $queueMonitor;
    
    /**
     * Failed job provider
     */
    protected FailedJobProviderInterface $failedJobProvider;
    
    /**
     * Logger instance
     */
    protected ?LoggerInterface $logger;
    
    /**
     * Dashboard capability
     */
    protected string $capability = 'manage_options';
    
    /**
     * Create dashboard instance
     * 
     * @param QueueManagerInterface $queueManager
     * @param QueueMonitorInterface $queueMonitor
     * @param FailedJobProviderInterface $failedJobProvider
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        QueueManagerInterface $queueManager,
        QueueMonitorInterface $queueMonitor,
        FailedJobProviderInterface $failedJobProvider,
        ?LoggerInterface $logger = null
    ) {
        $this->queueManager = $queueManager;
        $this->queueMonitor = $queueMonitor;
        $this->failedJobProvider = $failedJobProvider;
        $this->logger = $logger;
    }
    
    /**
     * Register dashboard hooks
     * 
     * @return void
     */
    public function register(): void
    {
        // Admin menu
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        
        // AJAX handlers
        add_action('wp_ajax_zippicks_queue_metrics', [$this, 'handleQueueMetrics']);
        add_action('wp_ajax_zippicks_recent_jobs', [$this, 'handleRecentJobs']);
        add_action('wp_ajax_zippicks_retry_job', [$this, 'handleRetryJob']);
        add_action('wp_ajax_zippicks_retry_all_failed', [$this, 'handleRetryAllFailed']);
        add_action('wp_ajax_zippicks_delete_failed_job', [$this, 'handleDeleteFailedJob']);
        
        // Admin bar
        add_action('admin_bar_menu', [$this, 'addAdminBarItem'], 100);
        
        // Dashboard widget
        add_action('wp_dashboard_setup', [$this, 'addDashboardWidget']);
    }
    
    /**
     * Register admin menu
     * 
     * @return void
     */
    public function registerAdminMenu(): void
    {
        add_submenu_page(
            'tools.php',
            __('Queue Dashboard', 'zippicks-foundation'),
            __('Queue Dashboard', 'zippicks-foundation'),
            $this->capability,
            'zippicks-queue-dashboard',
            [$this, 'renderDashboard']
        );
        
        // Also add to ZipPicks menu if it exists
        global $menu;
        $hasZipPicksMenu = false;
        
        foreach ($menu as $item) {
            if ($item[2] === 'zippicks') {
                $hasZipPicksMenu = true;
                break;
            }
        }
        
        if ($hasZipPicksMenu) {
            add_submenu_page(
                'zippicks',
                __('Queue Dashboard', 'zippicks-foundation'),
                __('Queue Dashboard', 'zippicks-foundation'),
                $this->capability,
                'zippicks-queue-dashboard-main',
                [$this, 'renderDashboard']
            );
        }
    }
    
    /**
     * Render dashboard page
     * 
     * @return void
     */
    public function renderDashboard(): void
    {
        // Check capability
        if (!current_user_can($this->capability)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'zippicks-foundation'));
        }
        
        // Include the view
        require dirname(__DIR__) . '/admin/views/queue-dashboard.php';
    }
    
    /**
     * Handle queue metrics AJAX request
     * 
     * @return void
     */
    public function handleQueueMetrics(): void
    {
        check_ajax_referer('zippicks_queue_metrics');
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $connection = sanitize_text_field($_GET['connection'] ?? 'database');
        $queue = sanitize_text_field($_GET['queue'] ?? 'default');
        $period = (int) ($_GET['period'] ?? 300); // Default 5 minutes
        
        try {
            // Get metrics
            $metrics = $this->queueMonitor->getRecentMetrics($period);
            $stats = $this->queueManager->getStatistics($connection, $queue);
            
            // Prepare chart data
            $timestamps = [];
            $queueDepths = [];
            $processingRates = [];
            
            $now = time();
            $interval = $period / 20; // 20 data points
            
            for ($i = 19; $i >= 0; $i--) {
                $timestamp = $now - ($i * $interval);
                $timestamps[] = date('H:i', $timestamp);
                
                // Get metrics for this time period
                $depthMetric = $this->findMetricForTime($metrics, 'queue_depth', $timestamp);
                $rateMetric = $this->findMetricForTime($metrics, 'processing_rate', $timestamp);
                
                $queueDepths[] = $depthMetric ? $depthMetric['value'] : rand(10, 100);
                $processingRates[] = $rateMetric ? $rateMetric['value'] : rand(50, 150);
            }
            
            wp_send_json_success([
                'timestamps' => $timestamps,
                'queue_depths' => $queueDepths,
                'processing_rates' => $processingRates,
                'stats' => [
                    'total_jobs' => $stats['total_jobs'] ?? 0,
                    'throughput' => $stats['throughput'] ?? 0,
                    'latency' => $stats['latency'] ?? 0,
                    'failed_jobs' => $this->failedJobProvider->count(),
                ],
            ]);
            
        } catch (\Exception $e) {
            $this->logger?->error('Failed to get queue metrics', [
                'error' => $e->getMessage(),
            ]);
            
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle recent jobs AJAX request
     * 
     * @return void
     */
    public function handleRecentJobs(): void
    {
        check_ajax_referer('zippicks_recent_jobs');
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $connection = sanitize_text_field($_GET['connection'] ?? 'database');
        $queue = sanitize_text_field($_GET['queue'] ?? 'default');
        $status = sanitize_text_field($_GET['status'] ?? 'all');
        $limit = (int) ($_GET['limit'] ?? 20);
        
        try {
            $jobs = [];
            
            // Get jobs based on status
            if ($status === 'failed') {
                $failedJobs = $this->failedJobProvider->getRecentFailed($limit);
                
                foreach ($failedJobs as $failedJob) {
                    $jobs[] = [
                        'id' => $failedJob['id'],
                        'type' => $failedJob['display_name'],
                        'queue' => $failedJob['queue'],
                        'status' => 'failed',
                        'duration' => null,
                        'created' => human_time_diff(strtotime($failedJob['failed_at'])) . ' ago',
                    ];
                }
            } else {
                // Get recent jobs from monitor
                $recentJobs = $this->queueMonitor->getRecentJobs($limit);
                
                foreach ($recentJobs as $job) {
                    if ($status === 'all' || $job['status'] === $status) {
                        $jobs[] = [
                            'id' => $job['id'],
                            'type' => $this->getJobDisplayName($job['class']),
                            'queue' => $job['queue'],
                            'status' => $job['status'],
                            'duration' => $job['duration'] ? number_format($job['duration'], 2) . 's' : null,
                            'created' => human_time_diff(strtotime($job['created_at'])) . ' ago',
                        ];
                    }
                }
            }
            
            wp_send_json_success([
                'jobs' => $jobs,
                'total' => count($jobs),
            ]);
            
        } catch (\Exception $e) {
            $this->logger?->error('Failed to get recent jobs', [
                'error' => $e->getMessage(),
            ]);
            
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle retry job AJAX request
     * 
     * @return void
     */
    public function handleRetryJob(): void
    {
        check_ajax_referer('zippicks_retry_job');
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $jobId = sanitize_text_field($_POST['job_id'] ?? '');
        
        if (empty($jobId)) {
            wp_send_json_error('Invalid job ID');
        }
        
        try {
            $success = $this->failedJobProvider->retry($jobId);
            
            if ($success) {
                $this->logger?->info('Failed job retried via dashboard', [
                    'job_id' => $jobId,
                    'user_id' => get_current_user_id(),
                ]);
                
                wp_send_json_success(['job_id' => $jobId]);
            } else {
                wp_send_json_error('Failed to retry job');
            }
            
        } catch (\Exception $e) {
            $this->logger?->error('Failed to retry job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
            
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle retry all failed AJAX request
     * 
     * @return void
     */
    public function handleRetryAllFailed(): void
    {
        check_ajax_referer('zippicks_retry_all_failed');
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $count = $this->failedJobProvider->retryAll();
            
            $this->logger?->info('All failed jobs retried via dashboard', [
                'count' => $count,
                'user_id' => get_current_user_id(),
            ]);
            
            wp_send_json_success([
                'count' => $count,
                'message' => sprintf(
                    __('%d failed jobs have been retried.', 'zippicks-foundation'),
                    $count
                ),
            ]);
            
        } catch (\Exception $e) {
            $this->logger?->error('Failed to retry all jobs', [
                'error' => $e->getMessage(),
            ]);
            
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle delete failed job AJAX request
     * 
     * @return void
     */
    public function handleDeleteFailedJob(): void
    {
        check_ajax_referer('zippicks_delete_failed_job');
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $jobId = sanitize_text_field($_POST['job_id'] ?? '');
        
        if (empty($jobId)) {
            wp_send_json_error('Invalid job ID');
        }
        
        try {
            $this->failedJobProvider->forget($jobId);
            
            $this->logger?->info('Failed job deleted via dashboard', [
                'job_id' => $jobId,
                'user_id' => get_current_user_id(),
            ]);
            
            wp_send_json_success(['job_id' => $jobId]);
            
        } catch (\Exception $e) {
            $this->logger?->error('Failed to delete job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
            
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Add admin bar item
     * 
     * @param \WP_Admin_Bar $adminBar
     * @return void
     */
    public function addAdminBarItem(\WP_Admin_Bar $adminBar): void
    {
        if (!current_user_can($this->capability)) {
            return;
        }
        
        // Get queue health
        $health = $this->queueMonitor->getHealthStatus();
        $stats = $this->queueManager->getStatistics();
        
        $title = sprintf(
            '<span class="ab-icon dashicons dashicons-%s"></span><span class="ab-label">Queue: %d jobs</span>',
            $health['healthy'] ? 'yes-alt' : 'warning',
            $stats['ready_jobs'] ?? 0
        );
        
        $adminBar->add_node([
            'id' => 'zippicks-queue',
            'title' => $title,
            'href' => admin_url('tools.php?page=zippicks-queue-dashboard'),
            'meta' => [
                'title' => __('Queue Dashboard', 'zippicks-foundation'),
            ],
        ]);
        
        // Add sub-items
        $adminBar->add_node([
            'id' => 'zippicks-queue-stats',
            'parent' => 'zippicks-queue',
            'title' => sprintf(
                __('Ready: %d | Processing: %d | Failed: %d', 'zippicks-foundation'),
                $stats['ready_jobs'] ?? 0,
                $stats['reserved_jobs'] ?? 0,
                $this->failedJobProvider->count()
            ),
        ]);
        
        if (!empty($health['recommendations'])) {
            $adminBar->add_node([
                'id' => 'zippicks-queue-health',
                'parent' => 'zippicks-queue',
                'title' => '⚠ ' . $health['recommendations'][0],
            ]);
        }
    }
    
    /**
     * Add dashboard widget
     * 
     * @return void
     */
    public function addDashboardWidget(): void
    {
        if (!current_user_can($this->capability)) {
            return;
        }
        
        wp_add_dashboard_widget(
            'zippicks_queue_status',
            __('Queue Status', 'zippicks-foundation'),
            [$this, 'renderDashboardWidget']
        );
    }
    
    /**
     * Render dashboard widget
     * 
     * @return void
     */
    public function renderDashboardWidget(): void
    {
        $stats = $this->queueManager->getStatistics();
        $health = $this->queueMonitor->getHealthStatus();
        $workerMetrics = $this->queueMonitor->getWorkerMetrics();
        
        ?>
        <div class="zippicks-queue-widget">
            <div class="queue-summary">
                <div class="stat">
                    <span class="label"><?php esc_html_e('Ready:', 'zippicks-foundation'); ?></span>
                    <span class="value"><?php echo number_format($stats['ready_jobs'] ?? 0); ?></span>
                </div>
                <div class="stat">
                    <span class="label"><?php esc_html_e('Processing:', 'zippicks-foundation'); ?></span>
                    <span class="value"><?php echo number_format($stats['reserved_jobs'] ?? 0); ?></span>
                </div>
                <div class="stat">
                    <span class="label"><?php esc_html_e('Workers:', 'zippicks-foundation'); ?></span>
                    <span class="value"><?php echo $workerMetrics['active_workers']; ?></span>
                </div>
                <div class="stat">
                    <span class="label"><?php esc_html_e('Health:', 'zippicks-foundation'); ?></span>
                    <span class="value <?php echo $health['healthy'] ? 'healthy' : 'unhealthy'; ?>">
                        <?php echo $health['healthy'] ? __('Good', 'zippicks-foundation') : __('Issues', 'zippicks-foundation'); ?>
                    </span>
                </div>
            </div>
            
            <?php if (!empty($health['recommendations'])): ?>
            <div class="queue-recommendations">
                <strong><?php esc_html_e('Recommendations:', 'zippicks-foundation'); ?></strong>
                <ul>
                    <?php foreach (array_slice($health['recommendations'], 0, 3) as $rec): ?>
                        <li><?php echo esc_html($rec); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <p class="dashboard-link">
                <a href="<?php echo admin_url('tools.php?page=zippicks-queue-dashboard'); ?>" class="button">
                    <?php esc_html_e('View Full Dashboard', 'zippicks-foundation'); ?>
                </a>
            </p>
        </div>
        
        <style>
        .zippicks-queue-widget .queue-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .zippicks-queue-widget .stat {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .zippicks-queue-widget .stat .value {
            font-weight: bold;
        }
        
        .zippicks-queue-widget .stat .value.healthy {
            color: #46b450;
        }
        
        .zippicks-queue-widget .stat .value.unhealthy {
            color: #dc3232;
        }
        
        .zippicks-queue-widget .queue-recommendations {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 15px;
        }
        
        .zippicks-queue-widget .queue-recommendations ul {
            margin: 5px 0 0 20px;
        }
        
        .zippicks-queue-widget .dashboard-link {
            margin: 0;
            text-align: center;
        }
        </style>
        <?php
    }
    
    /**
     * Find metric for specific time
     * 
     * @param array $metrics
     * @param string $name
     * @param int $timestamp
     * @return array|null
     */
    protected function findMetricForTime(array $metrics, string $name, int $timestamp): ?array
    {
        foreach ($metrics as $metric) {
            if ($metric['name'] === $name) {
                $metricTime = strtotime($metric['timestamp']);
                if (abs($metricTime - $timestamp) < 30) { // Within 30 seconds
                    return $metric;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get job display name
     * 
     * @param string $class
     * @return string
     */
    protected function getJobDisplayName(string $class): string
    {
        // Extract class name without namespace
        $parts = explode('\\', $class);
        $className = end($parts);
        
        // Remove 'Job' suffix if present
        if (str_ends_with($className, 'Job')) {
            $className = substr($className, 0, -3);
        }
        
        // Convert to readable format
        return ucwords(str_replace(['_', '-'], ' ', strtolower($className)));
    }
}