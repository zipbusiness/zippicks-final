<?php
/**
 * Monitoring Dashboard for ZipPicks Vibes
 * 
 * Provides real-time monitoring and analytics dashboard
 * 
 * @package ZipPicksVibes
 * @subpackage Admin
 * @since 2.0.0
 */

namespace ZipPicksVibes\Admin;

use ZipPicksVibes\Monitoring\MetricsCollector;
use ZipPicksVibes\Monitoring\PerformanceTracker;
use ZipPicksVibes\HealthCheck\HealthCheckManager;
use ZipPicksVibes\Audit\AuditRepository;

/**
 * MonitoringDashboard Class
 * 
 * Admin dashboard for monitoring system health and performance
 */
class MonitoringDashboard {
    
    /**
     * Metrics collector
     * 
     * @var MetricsCollector
     */
    private MetricsCollector $metricsCollector;
    
    /**
     * Page slug
     * 
     * @var string
     */
    private string $pageSlug = 'zippicks-vibes-monitoring';
    
    /**
     * Constructor
     * 
     * @param MetricsCollector $metricsCollector
     */
    public function __construct(MetricsCollector $metricsCollector) {
        $this->metricsCollector = $metricsCollector;
    }
    
    /**
     * Initialize dashboard
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'addMenuPage'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // AJAX handlers
        add_action('wp_ajax_zippicks_vibes_get_metrics', [$this, 'ajaxGetMetrics']);
        add_action('wp_ajax_zippicks_vibes_run_health_check', [$this, 'ajaxRunHealthCheck']);
        add_action('wp_ajax_zippicks_vibes_get_audit_logs', [$this, 'ajaxGetAuditLogs']);
    }
    
    /**
     * Add menu page
     */
    public function addMenuPage(): void {
        add_submenu_page(
            'zippicks-vibes',
            __('Monitoring Dashboard', 'zippicks-vibes'),
            __('Monitoring', 'zippicks-vibes'),
            'manage_options',
            $this->pageSlug,
            [$this, 'renderPage']
        );
    }
    
    /**
     * Enqueue assets
     * 
     * @param string $hook Current admin page
     */
    public function enqueueAssets(string $hook): void {
        if (strpos($hook, $this->pageSlug) === false) {
            return;
        }
        
        // Enqueue Chart.js for visualizations
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );
        
        // Enqueue monitoring dashboard script
        wp_enqueue_script(
            'zippicks-vibes-monitoring',
            ZIPPICKS_VIBES_URL . 'assets/js/monitoring-dashboard.js',
            ['jquery', 'chartjs'],
            ZIPPICKS_VIBES_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('zippicks-vibes-monitoring', 'zippicksMonitoring', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_vibes_monitoring'),
            'refreshInterval' => 30000, // 30 seconds
            'strings' => [
                'loading' => __('Loading...', 'zippicks-vibes'),
                'error' => __('Error loading data', 'zippicks-vibes'),
                'healthy' => __('Healthy', 'zippicks-vibes'),
                'warning' => __('Warning', 'zippicks-vibes'),
                'critical' => __('Critical', 'zippicks-vibes')
            ]
        ]);
        
        // Enqueue styles
        wp_enqueue_style(
            'zippicks-vibes-monitoring',
            ZIPPICKS_VIBES_URL . 'assets/css/monitoring-dashboard.css',
            [],
            ZIPPICKS_VIBES_VERSION
        );
    }
    
    /**
     * Render dashboard page
     */
    public function renderPage(): void {
        // Get initial metrics
        $metrics = $this->metricsCollector->collect();
        
        ?>
        <div class="wrap zippicks-monitoring-dashboard">
            <h1><?php _e('ZipPicks Vibes Monitoring Dashboard', 'zippicks-vibes'); ?></h1>
            
            <!-- Overview Cards -->
            <div class="monitoring-cards">
                <div class="monitoring-card health-status">
                    <h3><?php _e('System Health', 'zippicks-vibes'); ?></h3>
                    <div class="health-indicator" data-status="<?php echo esc_attr($metrics['health']['status']); ?>">
                        <span class="status-icon"></span>
                        <span class="status-text"><?php echo esc_html(ucfirst($metrics['health']['status'])); ?></span>
                    </div>
                    <div class="health-details">
                        <div class="health-count">
                            <span class="label"><?php _e('Healthy:', 'zippicks-vibes'); ?></span>
                            <span class="value"><?php echo esc_html($metrics['health']['counts']['healthy'] ?? 0); ?></span>
                        </div>
                        <div class="health-count">
                            <span class="label"><?php _e('Warnings:', 'zippicks-vibes'); ?></span>
                            <span class="value"><?php echo esc_html($metrics['health']['counts']['warning'] ?? 0); ?></span>
                        </div>
                        <div class="health-count">
                            <span class="label"><?php _e('Critical:', 'zippicks-vibes'); ?></span>
                            <span class="value"><?php echo esc_html($metrics['health']['counts']['critical'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="monitoring-card performance-summary">
                    <h3><?php _e('Performance', 'zippicks-vibes'); ?></h3>
                    <div class="metric">
                        <span class="label"><?php _e('Avg Response Time:', 'zippicks-vibes'); ?></span>
                        <span class="value"><?php echo number_format($metrics['performance']['totals']['avg_response_time'] ?? 0, 2); ?>ms</span>
                    </div>
                    <div class="metric">
                        <span class="label"><?php _e('Total Requests:', 'zippicks-vibes'); ?></span>
                        <span class="value"><?php echo number_format($metrics['performance']['totals']['total_requests'] ?? 0); ?></span>
                    </div>
                    <div class="metric">
                        <span class="label"><?php _e('Cache Hit Rate:', 'zippicks-vibes'); ?></span>
                        <span class="value"><?php echo number_format($metrics['performance']['totals']['cache_hit_rate'] ?? 0, 1); ?>%</span>
                    </div>
                </div>
                
                <div class="monitoring-card system-info">
                    <h3><?php _e('System Info', 'zippicks-vibes'); ?></h3>
                    <div class="metric">
                        <span class="label"><?php _e('Memory Usage:', 'zippicks-vibes'); ?></span>
                        <span class="value"><?php echo $this->formatBytes($metrics['system']['memory_usage'] ?? 0); ?></span>
                    </div>
                    <div class="metric">
                        <span class="label"><?php _e('Active Users:', 'zippicks-vibes'); ?></span>
                        <span class="value"><?php echo number_format($metrics['system']['active_users'] ?? 0); ?></span>
                    </div>
                    <div class="metric">
                        <span class="label"><?php _e('Plugin Version:', 'zippicks-vibes'); ?></span>
                        <span class="value"><?php echo esc_html($metrics['system']['plugin_version'] ?? 'Unknown'); ?></span>
                    </div>
                </div>
                
                <div class="monitoring-card vibes-stats">
                    <h3><?php _e('Vibes Statistics', 'zippicks-vibes'); ?></h3>
                    <div class="metric">
                        <span class="label"><?php _e('Total Vibes:', 'zippicks-vibes'); ?></span>
                        <span class="value"><?php echo number_format($metrics['vibes']['total_vibes'] ?? 0); ?></span>
                    </div>
                    <div class="metric">
                        <span class="label"><?php _e('Active Vibes:', 'zippicks-vibes'); ?></span>
                        <span class="value"><?php echo number_format($metrics['vibes']['active_vibes'] ?? 0); ?></span>
                    </div>
                    <div class="metric">
                        <span class="label"><?php _e('Waitlist Today:', 'zippicks-vibes'); ?></span>
                        <span class="value"><?php echo number_format($metrics['vibes']['waitlist_today'] ?? 0); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="monitoring-charts">
                <div class="chart-container">
                    <h3><?php _e('Response Time Trend', 'zippicks-vibes'); ?></h3>
                    <canvas id="responseTimeChart"></canvas>
                </div>
                
                <div class="chart-container">
                    <h3><?php _e('Request Volume', 'zippicks-vibes'); ?></h3>
                    <canvas id="requestVolumeChart"></canvas>
                </div>
            </div>
            
            <!-- Health Checks Detail -->
            <div class="monitoring-section health-checks">
                <h2><?php _e('Health Checks', 'zippicks-vibes'); ?></h2>
                <button class="button button-secondary" id="run-health-checks">
                    <?php _e('Run Health Checks', 'zippicks-vibes'); ?>
                </button>
                
                <div class="health-checks-list">
                    <?php foreach ($metrics['health']['checks'] as $check): ?>
                        <div class="health-check-item" data-status="<?php echo esc_attr($check['status']); ?>">
                            <div class="check-header">
                                <span class="check-name"><?php echo esc_html($check['name']); ?></span>
                                <span class="check-status"><?php echo esc_html(ucfirst($check['status'])); ?></span>
                            </div>
                            <div class="check-message"><?php echo esc_html($check['message']); ?></div>
                            <?php if (!empty($check['details'])): ?>
                                <div class="check-details">
                                    <pre><?php echo esc_html(json_encode($check['details'], JSON_PRETTY_PRINT)); ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Recent Audit Events -->
            <div class="monitoring-section audit-events">
                <h2><?php _e('Recent Security Events', 'zippicks-vibes'); ?></h2>
                
                <div class="audit-events-list">
                    <?php if (!empty($metrics['audit']['recent_security'])): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Time', 'zippicks-vibes'); ?></th>
                                    <th><?php _e('Action', 'zippicks-vibes'); ?></th>
                                    <th><?php _e('Severity', 'zippicks-vibes'); ?></th>
                                    <th><?php _e('IP Address', 'zippicks-vibes'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($metrics['audit']['recent_security'] as $event): ?>
                                    <tr>
                                        <td><?php echo esc_html(human_time_diff(strtotime($event['created_at']), current_time('timestamp')) . ' ago'); ?></td>
                                        <td><?php echo esc_html($event['action']); ?></td>
                                        <td><span class="severity-badge severity-<?php echo esc_attr($event['severity']); ?>"><?php echo esc_html($event['severity']); ?></span></td>
                                        <td><?php echo esc_html($event['ip']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php _e('No recent security events.', 'zippicks-vibes'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Slow Queries -->
            <?php if (!empty($metrics['performance']['slow_queries'])): ?>
                <div class="monitoring-section slow-queries">
                    <h2><?php _e('Slow Queries', 'zippicks-vibes'); ?></h2>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Query', 'zippicks-vibes'); ?></th>
                                <th><?php _e('Duration', 'zippicks-vibes'); ?></th>
                                <th><?php _e('Time', 'zippicks-vibes'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($metrics['performance']['slow_queries'] as $query): ?>
                                <tr>
                                    <td><?php echo esc_html($query['metric_name']); ?></td>
                                    <td><?php echo number_format($query['metric_value'], 2); ?>ms</td>
                                    <td><?php echo esc_html(human_time_diff(strtotime($query['created_at']), current_time('timestamp')) . ' ago'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Database Info -->
            <div class="monitoring-section database-info">
                <h2><?php _e('Database Information', 'zippicks-vibes'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Table', 'zippicks-vibes'); ?></th>
                            <th><?php _e('Rows', 'zippicks-vibes'); ?></th>
                            <th><?php _e('Size', 'zippicks-vibes'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metrics['database']['table_sizes'] as $table => $info): ?>
                            <tr>
                                <td><?php echo esc_html($table); ?></td>
                                <td><?php echo number_format($info['rows']); ?></td>
                                <td><?php echo number_format($info['size_mb'], 2); ?> MB</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for getting metrics
     */
    public function ajaxGetMetrics(): void {
        // Check nonce
        if (!check_ajax_referer('zippicks_vibes_monitoring', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Get options from request
        $options = [
            'performance_window' => intval($_POST['performance_window'] ?? 60),
            'audit_window_hours' => intval($_POST['audit_window_hours'] ?? 24),
            'force' => !empty($_POST['force'])
        ];
        
        // Collect metrics
        $metrics = $this->metricsCollector->collect($options);
        
        wp_send_json_success($metrics);
    }
    
    /**
     * AJAX handler for running health checks
     */
    public function ajaxRunHealthCheck(): void {
        // Check nonce
        if (!check_ajax_referer('zippicks_vibes_monitoring', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Force fresh health check
        $metrics = $this->metricsCollector->collect(['force' => true]);
        
        wp_send_json_success($metrics['health']);
    }
    
    /**
     * AJAX handler for getting audit logs
     */
    public function ajaxGetAuditLogs(): void {
        // Check nonce
        if (!check_ajax_referer('zippicks_vibes_monitoring', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Get filters from request
        $filters = [
            'event_type' => sanitize_text_field($_POST['event_type'] ?? ''),
            'event_category' => sanitize_text_field($_POST['event_category'] ?? ''),
            'severity' => sanitize_text_field($_POST['severity'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        ];
        
        // Remove empty filters
        $filters = array_filter($filters);
        
        // Get pagination
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = max(10, min(100, intval($_POST['per_page'] ?? 50)));
        $offset = ($page - 1) * $per_page;
        
        // Get audit repository
        $auditRepo = null;
        if (function_exists('zippicks') && zippicks()->has('vibes.audit_repository')) {
            $auditRepo = zippicks()->get('vibes.audit_repository');
        }
        
        if (!$auditRepo) {
            wp_send_json_error('Audit repository not available');
            return;
        }
        
        // Get logs
        $logs = $auditRepo->find($filters, $per_page, $offset);
        $total = $auditRepo->count($filters);
        
        // Format logs for response
        $formatted = array_map(function($log) {
            return [
                'id' => $log->getId(),
                'event_type' => $log->getEventType(),
                'event_action' => $log->getEventAction(),
                'event_category' => $log->getEventCategory(),
                'user_id' => $log->getUserId(),
                'ip_address' => $log->getIpAddress(),
                'severity' => $log->getSeverity(),
                'status' => $log->getStatus(),
                'message' => $log->getMessage(),
                'created_at' => $log->getCreatedAt()
            ];
        }, $logs);
        
        wp_send_json_success([
            'logs' => $formatted,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ]);
    }
    
    /**
     * Format bytes to human readable
     * 
     * @param int $bytes Bytes
     * @param int $precision Decimal precision
     * @return string
     */
    private function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}