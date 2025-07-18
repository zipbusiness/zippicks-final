<?php
/**
 * Queue Dashboard View
 * 
 * Real-time monitoring interface for the ZipPicks queue system.
 * Provides comprehensive visibility into job processing at scale.
 * 
 * @package ZipPicks\Foundation\Admin
 * @since 3.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get queue manager and monitor
$queueManager = zippicks_foundation()->queue();
$queueMonitor = zippicks_foundation()->get('queue.monitor');

// Get current statistics
$stats = $queueManager->getStatistics();
$health = $queueMonitor->getHealthStatus();
$recentMetrics = $queueMonitor->getRecentMetrics(300); // Last 5 minutes
$workerMetrics = $queueMonitor->getWorkerMetrics();

// Get queue connections
$connections = array_keys(zippicks_foundation()->get('config')->get('queue.connections', []));
$selectedConnection = $_GET['connection'] ?? 'database';
$selectedQueue = $_GET['queue'] ?? 'default';

// Get failed jobs summary
$failedProvider = zippicks_foundation()->get('queue.failed');
$failedJobs = $failedProvider->getRecentFailed(10);
$failedCount = $failedProvider->count();

?>

<div class="wrap zippicks-queue-dashboard">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Queue Dashboard', 'zippicks-foundation'); ?>
        <span class="queue-status <?php echo $health['healthy'] ? 'healthy' : 'unhealthy'; ?>">
            <?php echo $health['healthy'] ? '✓ Healthy' : '⚠ Issues Detected'; ?>
        </span>
    </h1>
    
    <?php if (!empty($health['recommendations'])): ?>
    <div class="notice notice-<?php echo $health['healthy'] ? 'warning' : 'error'; ?>">
        <p><strong><?php esc_html_e('Recommendations:', 'zippicks-foundation'); ?></strong></p>
        <ul>
            <?php foreach ($health['recommendations'] as $recommendation): ?>
                <li><?php echo esc_html($recommendation); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Connection Selector -->
    <div class="queue-controls">
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="zippicks-queue-dashboard" />
            
            <label for="connection-select"><?php esc_html_e('Connection:', 'zippicks-foundation'); ?></label>
            <select name="connection" id="connection-select" onchange="this.form.submit()">
                <?php foreach ($connections as $connection): ?>
                    <option value="<?php echo esc_attr($connection); ?>" <?php selected($selectedConnection, $connection); ?>>
                        <?php echo esc_html(ucfirst($connection)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label for="queue-select"><?php esc_html_e('Queue:', 'zippicks-foundation'); ?></label>
            <select name="queue" id="queue-select" onchange="this.form.submit()">
                <option value="default" <?php selected($selectedQueue, 'default'); ?>>Default</option>
                <option value="emails" <?php selected($selectedQueue, 'emails'); ?>>Emails</option>
                <option value="analytics" <?php selected($selectedQueue, 'analytics'); ?>>Analytics</option>
                <option value="high" <?php selected($selectedQueue, 'high'); ?>>High Priority</option>
                <option value="low" <?php selected($selectedQueue, 'low'); ?>>Low Priority</option>
            </select>
            
            <button type="button" class="button" id="refresh-dashboard">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh', 'zippicks-foundation'); ?>
            </button>
        </form>
    </div>
    
    <!-- Overview Stats -->
    <div class="queue-stats-grid">
        <div class="stat-card">
            <h3><?php esc_html_e('Total Jobs', 'zippicks-foundation'); ?></h3>
            <div class="stat-value" id="stat-total-jobs"><?php echo number_format($stats['total_jobs']); ?></div>
            <div class="stat-detail">
                <span class="stat-ready"><?php echo number_format($stats['ready_jobs']); ?> ready</span> | 
                <span class="stat-reserved"><?php echo number_format($stats['reserved_jobs']); ?> processing</span>
            </div>
        </div>
        
        <div class="stat-card">
            <h3><?php esc_html_e('Throughput', 'zippicks-foundation'); ?></h3>
            <div class="stat-value" id="stat-throughput"><?php echo number_format($stats['throughput'], 1); ?></div>
            <div class="stat-detail">jobs/minute</div>
        </div>
        
        <div class="stat-card">
            <h3><?php esc_html_e('Avg Latency', 'zippicks-foundation'); ?></h3>
            <div class="stat-value" id="stat-latency"><?php echo number_format($stats['latency'], 1); ?>ms</div>
            <div class="stat-detail">dispatch to process</div>
        </div>
        
        <div class="stat-card <?php echo $failedCount > 0 ? 'has-failed' : ''; ?>">
            <h3><?php esc_html_e('Failed Jobs', 'zippicks-foundation'); ?></h3>
            <div class="stat-value" id="stat-failed"><?php echo number_format($failedCount); ?></div>
            <div class="stat-detail">
                <?php if ($failedCount > 0): ?>
                    <a href="#failed-jobs" class="view-failed"><?php esc_html_e('View failed jobs', 'zippicks-foundation'); ?></a>
                <?php else: ?>
                    <?php esc_html_e('No failures', 'zippicks-foundation'); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Performance Charts -->
    <div class="queue-charts">
        <div class="chart-container">
            <h3><?php esc_html_e('Queue Depth Over Time', 'zippicks-foundation'); ?></h3>
            <canvas id="queue-depth-chart" width="400" height="200"></canvas>
        </div>
        
        <div class="chart-container">
            <h3><?php esc_html_e('Processing Rate', 'zippicks-foundation'); ?></h3>
            <canvas id="processing-rate-chart" width="400" height="200"></canvas>
        </div>
    </div>
    
    <!-- Workers Status -->
    <div class="workers-section">
        <h2><?php esc_html_e('Workers', 'zippicks-foundation'); ?></h2>
        
        <div class="workers-grid">
            <div class="worker-stat">
                <span class="worker-label"><?php esc_html_e('Active Workers:', 'zippicks-foundation'); ?></span>
                <span class="worker-value" id="active-workers"><?php echo $workerMetrics['active_workers']; ?></span>
            </div>
            
            <div class="worker-stat">
                <span class="worker-label"><?php esc_html_e('Jobs Processed:', 'zippicks-foundation'); ?></span>
                <span class="worker-value" id="jobs-processed"><?php echo number_format($workerMetrics['total_processed']); ?></span>
            </div>
            
            <div class="worker-stat">
                <span class="worker-label"><?php esc_html_e('Avg Memory:', 'zippicks-foundation'); ?></span>
                <span class="worker-value" id="avg-memory"><?php echo $workerMetrics['avg_memory']; ?>MB</span>
            </div>
            
            <div class="worker-stat">
                <span class="worker-label"><?php esc_html_e('Uptime:', 'zippicks-foundation'); ?></span>
                <span class="worker-value" id="worker-uptime"><?php echo $workerMetrics['uptime']; ?></span>
            </div>
        </div>
        
        <?php if (empty($workerMetrics['workers'])): ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('No active workers detected.', 'zippicks-foundation'); ?>
                <?php esc_html_e('Start a worker with:', 'zippicks-foundation'); ?>
                <code>wp queue:work --queue=<?php echo esc_html($selectedQueue); ?></code>
            </p>
        </div>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Worker ID', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Status', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Current Job', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Jobs Processed', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Memory', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Started', 'zippicks-foundation'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($workerMetrics['workers'] as $worker): ?>
                <tr>
                    <td><?php echo esc_html($worker['id']); ?></td>
                    <td>
                        <span class="worker-status status-<?php echo esc_attr($worker['status']); ?>">
                            <?php echo esc_html($worker['status']); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($worker['current_job'] ?? '-'); ?></td>
                    <td><?php echo number_format($worker['jobs_processed']); ?></td>
                    <td><?php echo esc_html($worker['memory']); ?>MB</td>
                    <td><?php echo esc_html(human_time_diff(strtotime($worker['started_at']))); ?> ago</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Recent Jobs -->
    <div class="recent-jobs-section">
        <h2><?php esc_html_e('Recent Jobs', 'zippicks-foundation'); ?></h2>
        
        <div class="job-filters">
            <button class="button job-filter active" data-status="all"><?php esc_html_e('All', 'zippicks-foundation'); ?></button>
            <button class="button job-filter" data-status="completed"><?php esc_html_e('Completed', 'zippicks-foundation'); ?></button>
            <button class="button job-filter" data-status="processing"><?php esc_html_e('Processing', 'zippicks-foundation'); ?></button>
            <button class="button job-filter" data-status="failed"><?php esc_html_e('Failed', 'zippicks-foundation'); ?></button>
        </div>
        
        <table class="wp-list-table widefat fixed striped" id="recent-jobs-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Job ID', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Type', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Queue', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Status', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Duration', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Created', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Actions', 'zippicks-foundation'); ?></th>
                </tr>
            </thead>
            <tbody id="recent-jobs-tbody">
                <!-- Jobs will be loaded via AJAX -->
                <tr>
                    <td colspan="7" class="loading"><?php esc_html_e('Loading recent jobs...', 'zippicks-foundation'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Failed Jobs -->
    <?php if ($failedCount > 0): ?>
    <div class="failed-jobs-section" id="failed-jobs">
        <h2>
            <?php esc_html_e('Failed Jobs', 'zippicks-foundation'); ?>
            <button class="button button-primary retry-all-failed" data-count="<?php echo esc_attr($failedCount); ?>">
                <?php esc_html_e('Retry All Failed', 'zippicks-foundation'); ?>
            </button>
        </h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Job ID', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Type', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Queue', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Exception', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Failed At', 'zippicks-foundation'); ?></th>
                    <th><?php esc_html_e('Actions', 'zippicks-foundation'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($failedJobs as $failedJob): ?>
                <tr>
                    <td><?php echo esc_html($failedJob['id']); ?></td>
                    <td><?php echo esc_html($failedJob['display_name']); ?></td>
                    <td><?php echo esc_html($failedJob['queue']); ?></td>
                    <td>
                        <span class="exception-message" title="<?php echo esc_attr($failedJob['exception']); ?>">
                            <?php echo esc_html(substr($failedJob['exception'], 0, 50) . '...'); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(human_time_diff(strtotime($failedJob['failed_at']))); ?> ago</td>
                    <td>
                        <button class="button button-small retry-job" data-job-id="<?php echo esc_attr($failedJob['id']); ?>">
                            <?php esc_html_e('Retry', 'zippicks-foundation'); ?>
                        </button>
                        <button class="button button-small delete-failed" data-job-id="<?php echo esc_attr($failedJob['id']); ?>">
                            <?php esc_html_e('Delete', 'zippicks-foundation'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($failedCount > 10): ?>
        <p class="description">
            <?php echo sprintf(
                esc_html__('Showing 10 of %s failed jobs. Use WP-CLI to manage all failed jobs.', 'zippicks-foundation'),
                number_format($failedCount)
            ); ?>
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Queue Metrics -->
    <div class="queue-metrics-section">
        <h2><?php esc_html_e('Performance Metrics', 'zippicks-foundation'); ?></h2>
        
        <div class="metrics-grid">
            <div class="metric-card">
                <h4><?php esc_html_e('Job Performance', 'zippicks-foundation'); ?></h4>
                <table class="metric-table">
                    <tr>
                        <td><?php esc_html_e('Success Rate:', 'zippicks-foundation'); ?></td>
                        <td class="metric-value"><?php echo number_format($stats['success_rate'] * 100, 1); ?>%</td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Avg Duration:', 'zippicks-foundation'); ?></td>
                        <td class="metric-value"><?php echo number_format($stats['avg_duration'], 2); ?>s</td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('P95 Duration:', 'zippicks-foundation'); ?></td>
                        <td class="metric-value"><?php echo number_format($stats['p95_duration'], 2); ?>s</td>
                    </tr>
                </table>
            </div>
            
            <div class="metric-card">
                <h4><?php esc_html_e('Queue Health', 'zippicks-foundation'); ?></h4>
                <table class="metric-table">
                    <tr>
                        <td><?php esc_html_e('Queue Depth:', 'zippicks-foundation'); ?></td>
                        <td class="metric-value"><?php echo number_format($stats['queue_depth']); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Oldest Job:', 'zippicks-foundation'); ?></td>
                        <td class="metric-value"><?php echo esc_html($stats['oldest_job_age']); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Retry Rate:', 'zippicks-foundation'); ?></td>
                        <td class="metric-value"><?php echo number_format($stats['retry_rate'] * 100, 1); ?>%</td>
                    </tr>
                </table>
            </div>
            
            <div class="metric-card">
                <h4><?php esc_html_e('System Resources', 'zippicks-foundation'); ?></h4>
                <table class="metric-table">
                    <tr>
                        <td><?php esc_html_e('CPU Usage:', 'zippicks-foundation'); ?></td>
                        <td class="metric-value"><?php echo number_format($stats['cpu_usage'], 1); ?>%</td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Memory Usage:', 'zippicks-foundation'); ?></td>
                        <td class="metric-value"><?php echo $stats['memory_usage']; ?>MB</td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('DB Connections:', 'zippicks-foundation'); ?></td>
                        <td class="metric-value"><?php echo $stats['db_connections']; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- CLI Commands Reference -->
    <div class="cli-reference">
        <h3><?php esc_html_e('WP-CLI Commands', 'zippicks-foundation'); ?></h3>
        <div class="cli-commands">
            <code>wp queue:work --queue=<?php echo esc_html($selectedQueue); ?> --max-jobs=1000</code>
            <span class="cli-desc"><?php esc_html_e('Start a queue worker', 'zippicks-foundation'); ?></span>
            
            <code>wp queue:retry all</code>
            <span class="cli-desc"><?php esc_html_e('Retry all failed jobs', 'zippicks-foundation'); ?></span>
            
            <code>wp queue:table</code>
            <span class="cli-desc"><?php esc_html_e('Create/update queue tables', 'zippicks-foundation'); ?></span>
        </div>
    </div>
</div>

<style>
.zippicks-queue-dashboard {
    max-width: 1400px;
    margin: 0 auto;
}

.queue-status {
    display: inline-block;
    margin-left: 10px;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: normal;
}

.queue-status.healthy {
    background: #d4edda;
    color: #155724;
}

.queue-status.unhealthy {
    background: #f8d7da;
    color: #721c24;
}

.queue-controls {
    margin: 20px 0;
    padding: 15px;
    background: #f1f1f1;
    border-radius: 4px;
}

.queue-controls form {
    display: flex;
    align-items: center;
    gap: 15px;
}

.queue-controls label {
    font-weight: 600;
}

.queue-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.stat-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
    color: #666;
}

.stat-value {
    font-size: 36px;
    font-weight: bold;
    color: #2271b1;
    margin: 10px 0;
}

.stat-detail {
    font-size: 14px;
    color: #666;
}

.stat-ready {
    color: #46b450;
}

.stat-reserved {
    color: #f0b849;
}

.stat-card.has-failed .stat-value {
    color: #dc3232;
}

.queue-charts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin: 30px 0;
}

.chart-container {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
}

.chart-container h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
}

.workers-section, 
.recent-jobs-section, 
.failed-jobs-section, 
.queue-metrics-section {
    margin: 40px 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
}

.workers-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin: 20px 0;
}

.worker-stat {
    text-align: center;
}

.worker-label {
    display: block;
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.worker-value {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #2271b1;
}

.worker-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
}

.worker-status.status-idle {
    background: #f0f0f0;
    color: #666;
}

.worker-status.status-processing {
    background: #e6f3ff;
    color: #0073aa;
}

.job-filters {
    margin: 15px 0;
}

.job-filter {
    margin-right: 5px;
}

.job-filter.active {
    background: #2271b1;
    color: white;
}

.exception-message {
    font-family: monospace;
    font-size: 12px;
    color: #dc3232;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.metric-card {
    background: #f8f9fa;
    border-radius: 4px;
    padding: 15px;
}

.metric-card h4 {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: #333;
}

.metric-table {
    width: 100%;
}

.metric-table td {
    padding: 5px 0;
}

.metric-value {
    text-align: right;
    font-weight: bold;
    color: #2271b1;
}

.cli-reference {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-top: 40px;
}

.cli-commands {
    display: grid;
    gap: 10px;
    margin-top: 15px;
}

.cli-commands code {
    display: block;
    padding: 8px 12px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.cli-desc {
    display: block;
    font-size: 13px;
    color: #666;
    margin-top: 3px;
    margin-bottom: 10px;
}

.loading {
    text-align: center;
    color: #666;
    font-style: italic;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
jQuery(document).ready(function($) {
    // Chart configuration
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                }
            },
            y: {
                beginAtZero: true
            }
        }
    };
    
    // Initialize Queue Depth Chart
    const queueDepthCtx = document.getElementById('queue-depth-chart').getContext('2d');
    const queueDepthChart = new Chart(queueDepthCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Queue Depth',
                data: [],
                borderColor: '#2271b1',
                backgroundColor: 'rgba(34, 113, 177, 0.1)',
                tension: 0.4
            }]
        },
        options: chartOptions
    });
    
    // Initialize Processing Rate Chart
    const processingRateCtx = document.getElementById('processing-rate-chart').getContext('2d');
    const processingRateChart = new Chart(processingRateCtx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Jobs/min',
                data: [],
                backgroundColor: '#46b450'
            }]
        },
        options: chartOptions
    });
    
    // Update charts with real data
    function updateCharts() {
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'zippicks_queue_metrics',
                connection: '<?php echo esc_js($selectedConnection); ?>',
                queue: '<?php echo esc_js($selectedQueue); ?>',
                _ajax_nonce: '<?php echo wp_create_nonce('zippicks_queue_metrics'); ?>'
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Update queue depth chart
                    queueDepthChart.data.labels = response.data.timestamps;
                    queueDepthChart.data.datasets[0].data = response.data.queue_depths;
                    queueDepthChart.update();
                    
                    // Update processing rate chart
                    processingRateChart.data.labels = response.data.timestamps;
                    processingRateChart.data.datasets[0].data = response.data.processing_rates;
                    processingRateChart.update();
                    
                    // Update statistics
                    $('#stat-total-jobs').text(response.data.stats.total_jobs.toLocaleString());
                    $('#stat-throughput').text(response.data.stats.throughput.toFixed(1));
                    $('#stat-latency').text(response.data.stats.latency.toFixed(1) + 'ms');
                    $('#stat-failed').text(response.data.stats.failed_jobs.toLocaleString());
                }
            }
        });
    }
    
    // Load recent jobs
    function loadRecentJobs(status = 'all') {
        $('#recent-jobs-tbody').html('<tr><td colspan="7" class="loading">Loading...</td></tr>');
        
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'zippicks_recent_jobs',
                connection: '<?php echo esc_js($selectedConnection); ?>',
                queue: '<?php echo esc_js($selectedQueue); ?>',
                status: status,
                _ajax_nonce: '<?php echo wp_create_nonce('zippicks_recent_jobs'); ?>'
            },
            success: function(response) {
                if (response.success && response.data) {
                    let html = '';
                    
                    if (response.data.jobs.length === 0) {
                        html = '<tr><td colspan="7" class="no-items">No jobs found</td></tr>';
                    } else {
                        response.data.jobs.forEach(function(job) {
                            html += `
                                <tr>
                                    <td>${job.id}</td>
                                    <td>${job.type}</td>
                                    <td>${job.queue}</td>
                                    <td><span class="job-status status-${job.status}">${job.status}</span></td>
                                    <td>${job.duration || '-'}</td>
                                    <td>${job.created}</td>
                                    <td>
                                        ${job.status === 'failed' ? 
                                            `<button class="button button-small retry-job" data-job-id="${job.id}">Retry</button>` : 
                                            '-'
                                        }
                                    </td>
                                </tr>
                            `;
                        });
                    }
                    
                    $('#recent-jobs-tbody').html(html);
                }
            }
        });
    }
    
    // Job filter buttons
    $('.job-filter').on('click', function() {
        $('.job-filter').removeClass('active');
        $(this).addClass('active');
        loadRecentJobs($(this).data('status'));
    });
    
    // Retry job
    $(document).on('click', '.retry-job', function() {
        const $button = $(this);
        const jobId = $button.data('job-id');
        
        $button.prop('disabled', true).text('Retrying...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'zippicks_retry_job',
                job_id: jobId,
                _ajax_nonce: '<?php echo wp_create_nonce('zippicks_retry_job'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $button.text('Retried');
                    setTimeout(function() {
                        loadRecentJobs();
                        updateCharts();
                    }, 1000);
                } else {
                    $button.prop('disabled', false).text('Retry');
                    alert('Failed to retry job: ' + response.data);
                }
            }
        });
    });
    
    // Retry all failed jobs
    $('.retry-all-failed').on('click', function() {
        if (!confirm('Are you sure you want to retry all failed jobs?')) {
            return;
        }
        
        const $button = $(this);
        $button.prop('disabled', true).text('Retrying...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'zippicks_retry_all_failed',
                _ajax_nonce: '<?php echo wp_create_nonce('zippicks_retry_all_failed'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $button.text('All Retried');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $button.prop('disabled', false).text('Retry All Failed');
                    alert('Failed to retry jobs: ' + response.data);
                }
            }
        });
    });
    
    // Delete failed job
    $(document).on('click', '.delete-failed', function() {
        if (!confirm('Are you sure you want to delete this failed job?')) {
            return;
        }
        
        const $button = $(this);
        const jobId = $button.data('job-id');
        
        $button.prop('disabled', true).text('Deleting...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'zippicks_delete_failed_job',
                job_id: jobId,
                _ajax_nonce: '<?php echo wp_create_nonce('zippicks_delete_failed_job'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $button.closest('tr').fadeOut();
                } else {
                    $button.prop('disabled', false).text('Delete');
                    alert('Failed to delete job: ' + response.data);
                }
            }
        });
    });
    
    // Refresh dashboard
    $('#refresh-dashboard').on('click', function() {
        const $button = $(this);
        const $icon = $button.find('.dashicons');
        
        $icon.addClass('spin');
        
        updateCharts();
        loadRecentJobs();
        
        setTimeout(function() {
            $icon.removeClass('spin');
        }, 1000);
    });
    
    // Auto-refresh
    let autoRefreshInterval;
    
    function startAutoRefresh() {
        autoRefreshInterval = setInterval(function() {
            updateCharts();
            loadRecentJobs($('.job-filter.active').data('status'));
        }, 10000); // Every 10 seconds
    }
    
    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    }
    
    // Initial load
    updateCharts();
    loadRecentJobs();
    startAutoRefresh();
    
    // Stop auto-refresh when page is hidden
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
        }
    });
});
</script>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.dashicons.spin {
    animation: spin 1s linear infinite;
}

.job-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.job-status.status-completed {
    background: #d4edda;
    color: #155724;
}

.job-status.status-processing {
    background: #cce5ff;
    color: #004085;
}

.job-status.status-failed {
    background: #f8d7da;
    color: #721c24;
}

.job-status.status-pending {
    background: #fff3cd;
    color: #856404;
}

.no-items {
    text-align: center;
    color: #666;
    font-style: italic;
}
</style>