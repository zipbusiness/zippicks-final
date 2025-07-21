<?php
/**
 * Health Check System for Master Critic
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

/**
 * Health check monitoring and diagnostics
 */
class ZipPicks_Master_Critic_Health_Check {
    
    /**
     * Health status constants
     */
    const STATUS_HEALTHY = 'healthy';
    const STATUS_WARNING = 'warning';
    const STATUS_CRITICAL = 'critical';
    
    /**
     * Check results cache
     *
     * @var array
     */
    protected array $check_results = [];
    
    /**
     * Logger instance
     *
     * @var ZipPicks_Master_Critic_Logger|null
     */
    protected ?ZipPicks_Master_Critic_Logger $logger = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load logger if available
        if (class_exists('ZipPicks_Master_Critic_Logger')) {
            $this->logger = new ZipPicks_Master_Critic_Logger();
        }
        
        // Schedule regular health checks
        $this->schedule_health_checks();
    }
    
    /**
     * Schedule health checks
     */
    protected function schedule_health_checks(): void {
        if (!wp_next_scheduled('zippicks_health_check')) {
            wp_schedule_event(time(), 'hourly', 'zippicks_health_check');
        }
        
        add_action('zippicks_health_check', [$this, 'run_scheduled_checks']);
    }
    
    /**
     * Get overall health status
     *
     * @return array
     */
    public function get_status(): array {
        $checks = $this->run_all_checks();
        
        $status = self::STATUS_HEALTHY;
        $issues = [];
        $warnings = [];
        
        foreach ($checks as $check => $result) {
            if ($result['status'] === self::STATUS_CRITICAL) {
                $status = self::STATUS_CRITICAL;
                $issues[] = $result['message'];
            } elseif ($result['status'] === self::STATUS_WARNING && $status !== self::STATUS_CRITICAL) {
                $status = self::STATUS_WARNING;
                $warnings[] = $result['message'];
            }
        }
        
        return [
            'status' => $status,
            'timestamp' => current_time('c'),
            'checks' => $checks,
            'issues' => $issues,
            'warnings' => $warnings,
            'metrics' => $this->get_metrics()
        ];
    }
    
    /**
     * Run all health checks
     *
     * @return array
     */
    public function run_all_checks(): array {
        $this->check_results = [
            'database' => $this->check_database(),
            'tables' => $this->check_tables(),
            'api_keys' => $this->check_api_keys(),
            'permissions' => $this->check_permissions(),
            'dependencies' => $this->check_dependencies(),
            'cache' => $this->check_cache(),
            'disk_space' => $this->check_disk_space(),
            'memory' => $this->check_memory(),
            'error_rate' => $this->check_error_rate(),
            'response_time' => $this->check_response_time(),
            'queue_health' => $this->check_queue_health(),
            'cron_health' => $this->check_cron_health()
        ];
        
        // Cache results
        set_transient('zippicks_health_check_results', $this->check_results, 5 * MINUTE_IN_SECONDS);
        
        return $this->check_results;
    }
    
    /**
     * Check database connectivity
     *
     * @return array
     */
    protected function check_database(): array {
        global $wpdb;
        
        try {
            $start = microtime(true);
            $result = $wpdb->get_var("SELECT 1");
            $response_time = microtime(true) - $start;
            
            if ($result != 1) {
                return [
                    'status' => self::STATUS_CRITICAL,
                    'message' => 'Database connection failed',
                    'details' => ['error' => $wpdb->last_error]
                ];
            }
            
            if ($response_time > 1.0) {
                return [
                    'status' => self::STATUS_WARNING,
                    'message' => 'Database response slow',
                    'details' => ['response_time' => $response_time]
                ];
            }
            
            return [
                'status' => self::STATUS_HEALTHY,
                'message' => 'Database connection healthy',
                'details' => ['response_time' => $response_time]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => self::STATUS_CRITICAL,
                'message' => 'Database check failed',
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Check required tables
     *
     * @return array
     */
    protected function check_tables(): array {
        global $wpdb;
        
        $required_tables = [
            'zippicks_generations',
            'zippicks_prompt_templates',
            'zippicks_api_usage_log',
            'zippicks_audit_log'
        ];
        
        $missing_tables = [];
        
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            return [
                'status' => self::STATUS_CRITICAL,
                'message' => 'Required database tables missing',
                'details' => ['missing_tables' => $missing_tables]
            ];
        }
        
        return [
            'status' => self::STATUS_HEALTHY,
            'message' => 'All required tables present',
            'details' => ['tables' => $required_tables]
        ];
    }
    
    /**
     * Check API keys configuration
     *
     * @return array
     */
    protected function check_api_keys(): array {
        $anthropic_key = get_option('zippicks_anthropic_api_key');
        $openai_key = get_option('zippicks_openai_api_key');
        
        if (empty($anthropic_key) && empty($openai_key)) {
            return [
                'status' => self::STATUS_CRITICAL,
                'message' => 'No AI API keys configured',
                'details' => []
            ];
        }
        
        $configured = [];
        if (!empty($anthropic_key)) {
            $configured[] = 'Anthropic';
        }
        if (!empty($openai_key)) {
            $configured[] = 'OpenAI';
        }
        
        return [
            'status' => self::STATUS_HEALTHY,
            'message' => 'API keys configured',
            'details' => ['providers' => $configured]
        ];
    }
    
    /**
     * Check file permissions
     *
     * @return array
     */
    protected function check_permissions(): array {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/zippicks-logs';
        
        $issues = [];
        
        // Check log directory
        if (!is_writable($log_dir)) {
            $issues[] = 'Log directory not writable';
        }
        
        // Check plugin directory
        if (!is_readable(ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR)) {
            $issues[] = 'Plugin directory not readable';
        }
        
        if (!empty($issues)) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Permission issues detected',
                'details' => ['issues' => $issues]
            ];
        }
        
        return [
            'status' => self::STATUS_HEALTHY,
            'message' => 'File permissions correct',
            'details' => []
        ];
    }
    
    /**
     * Check dependencies
     *
     * @return array
     */
    protected function check_dependencies(): array {
        $issues = [];
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $issues[] = sprintf('PHP %s is below required 8.0', PHP_VERSION);
        }
        
        // Check required PHP extensions
        $required_extensions = ['curl', 'json', 'mbstring', 'openssl'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $issues[] = "PHP extension '$ext' not loaded";
            }
        }
        
        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '6.0', '<')) {
            $issues[] = sprintf('WordPress %s is below required 6.0', $wp_version);
        }
        
        // Check Foundation
        if (!function_exists('zippicks')) {
            $issues[] = 'ZipPicks Foundation not available';
        }
        
        if (!empty($issues)) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Dependency issues found',
                'details' => ['issues' => $issues]
            ];
        }
        
        return [
            'status' => self::STATUS_HEALTHY,
            'message' => 'All dependencies satisfied',
            'details' => [
                'php_version' => PHP_VERSION,
                'wp_version' => $wp_version
            ]
        ];
    }
    
    /**
     * Check cache health
     *
     * @return array
     */
    protected function check_cache(): array {
        $test_key = 'health_check_test_' . time();
        $test_value = 'test_' . wp_generate_password(10);
        
        // Test cache operations
        $cache_manager = new ZipPicks_Master_Critic_Cache_Manager();
        
        // Test write
        if (!$cache_manager->set($test_key, $test_value, 60)) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Cache write failed',
                'details' => ['backend' => $cache_manager->get_stats()['backend']]
            ];
        }
        
        // Test read
        $retrieved = $cache_manager->get($test_key);
        if ($retrieved !== $test_value) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Cache read failed',
                'details' => ['backend' => $cache_manager->get_stats()['backend']]
            ];
        }
        
        // Test delete
        $cache_manager->delete($test_key);
        
        $stats = $cache_manager->get_stats();
        
        return [
            'status' => self::STATUS_HEALTHY,
            'message' => 'Cache operational',
            'details' => [
                'backend' => $stats['backend'],
                'hit_rate' => $stats['hit_rate'] . '%'
            ]
        ];
    }
    
    /**
     * Check disk space
     *
     * @return array
     */
    protected function check_disk_space(): array {
        // Check if disk space functions are available (may be disabled on some hosts)
        if (!function_exists('disk_free_space') || !function_exists('disk_total_space')) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Disk space functions not available',
                'details' => ['note' => 'Functions disabled by hosting provider']
            ];
        }
        
        $free_space = @disk_free_space(ABSPATH);
        $total_space = @disk_total_space(ABSPATH);
        
        if ($free_space === false || $total_space === false) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Unable to check disk space',
                'details' => []
            ];
        }
        
        $free_percentage = ($free_space / $total_space) * 100;
        
        if ($free_percentage < 5) {
            return [
                'status' => self::STATUS_CRITICAL,
                'message' => 'Critical disk space shortage',
                'details' => [
                    'free_space' => size_format($free_space),
                    'percentage' => round($free_percentage, 2) . '%'
                ]
            ];
        }
        
        if ($free_percentage < 10) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Low disk space',
                'details' => [
                    'free_space' => size_format($free_space),
                    'percentage' => round($free_percentage, 2) . '%'
                ]
            ];
        }
        
        return [
            'status' => self::STATUS_HEALTHY,
            'message' => 'Adequate disk space',
            'details' => [
                'free_space' => size_format($free_space),
                'percentage' => round($free_percentage, 2) . '%'
            ]
        ];
    }
    
    /**
     * Check memory usage
     *
     * @return array
     */
    protected function check_memory(): array {
        // Check if ini_get is available
        if (!function_exists('ini_get')) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Memory check functions not available',
                'details' => ['note' => 'ini_get function disabled by hosting provider']
            ];
        }
        
        $memory_limit = $this->convert_to_bytes(@ini_get('memory_limit'));
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        $usage_percentage = ($memory_peak / $memory_limit) * 100;
        
        if ($usage_percentage > 90) {
            return [
                'status' => self::STATUS_CRITICAL,
                'message' => 'Critical memory usage',
                'details' => [
                    'current' => size_format($memory_usage),
                    'peak' => size_format($memory_peak),
                    'limit' => size_format($memory_limit),
                    'percentage' => round($usage_percentage, 2) . '%'
                ]
            ];
        }
        
        if ($usage_percentage > 75) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'High memory usage',
                'details' => [
                    'current' => size_format($memory_usage),
                    'peak' => size_format($memory_peak),
                    'limit' => size_format($memory_limit),
                    'percentage' => round($usage_percentage, 2) . '%'
                ]
            ];
        }
        
        return [
            'status' => self::STATUS_HEALTHY,
            'message' => 'Memory usage normal',
            'details' => [
                'current' => size_format($memory_usage),
                'peak' => size_format($memory_peak),
                'limit' => size_format($memory_limit),
                'percentage' => round($usage_percentage, 2) . '%'
            ]
        ];
    }
    
    /**
     * Check error rate
     *
     * @return array
     */
    protected function check_error_rate(): array {
        $error_count = get_transient('zippicks_error_count') ?: 0;
        $request_count = get_transient('zippicks_request_count') ?: 1;
        
        $error_rate = ($error_count / $request_count) * 100;
        
        if ($error_rate > 10) {
            return [
                'status' => self::STATUS_CRITICAL,
                'message' => 'High error rate',
                'details' => [
                    'error_count' => $error_count,
                    'request_count' => $request_count,
                    'error_rate' => round($error_rate, 2) . '%'
                ]
            ];
        }
        
        if ($error_rate > 5) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Elevated error rate',
                'details' => [
                    'error_count' => $error_count,
                    'request_count' => $request_count,
                    'error_rate' => round($error_rate, 2) . '%'
                ]
            ];
        }
        
        return [
            'status' => self::STATUS_HEALTHY,
            'message' => 'Error rate acceptable',
            'details' => [
                'error_count' => $error_count,
                'request_count' => $request_count,
                'error_rate' => round($error_rate, 2) . '%'
            ]
        ];
    }
    
    /**
     * Check response time
     *
     * @return array
     */
    protected function check_response_time(): array {
        $response_times = get_transient('zippicks_response_times') ?: [];
        
        if (empty($response_times)) {
            return [
                'status' => self::STATUS_HEALTHY,
                'message' => 'No response time data yet',
                'details' => []
            ];
        }
        
        $avg_response = array_sum($response_times) / count($response_times);
        $max_response = max($response_times);
        
        if ($avg_response > 3.0) {
            return [
                'status' => self::STATUS_CRITICAL,
                'message' => 'Very slow response times',
                'details' => [
                    'average' => round($avg_response, 3) . 's',
                    'max' => round($max_response, 3) . 's'
                ]
            ];
        }
        
        if ($avg_response > 1.0) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Slow response times',
                'details' => [
                    'average' => round($avg_response, 3) . 's',
                    'max' => round($max_response, 3) . 's'
                ]
            ];
        }
        
        return [
            'status' => self::STATUS_HEALTHY,
            'message' => 'Response times normal',
            'details' => [
                'average' => round($avg_response, 3) . 's',
                'max' => round($max_response, 3) . 's'
            ]
        ];
    }
    
    /**
     * Check queue health
     *
     * @return array
     */
    protected function check_queue_health(): array {
        global $wpdb;
        
        // Check generation queue
        $pending_generations = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_generations 
             WHERE status = 'pending'"
        );
        
        if ($pending_generations > 100) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Large generation queue',
                'details' => ['pending' => $pending_generations]
            ];
        }
        
        return [
            'status' => self::STATUS_HEALTHY,
            'message' => 'Queue size normal',
            'details' => ['pending' => $pending_generations]
        ];
    }
    
    /**
     * Check cron health
     *
     * @return array
     */
    protected function check_cron_health(): array {
        $cron_array = _get_cron_array();
        $our_crons = [];
        
        foreach ($cron_array as $timestamp => $cron) {
            foreach ($cron as $hook => $tasks) {
                if (strpos($hook, 'zippicks_') === 0) {
                    $our_crons[$hook] = $timestamp;
                }
            }
        }
        
        // Check if our crons are scheduled
        $required_crons = [
            'zippicks_health_check',
            'zippicks_maintenance',
            'zippicks_security_cleanup'
        ];
        
        $missing_crons = [];
        foreach ($required_crons as $cron) {
            if (!isset($our_crons[$cron])) {
                $missing_crons[] = $cron;
            }
        }
        
        if (!empty($missing_crons)) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Missing scheduled tasks',
                'details' => ['missing' => $missing_crons]
            ];
        }
        
        return [
            'status' => self::STATUS_HEALTHY,
            'message' => 'All scheduled tasks present',
            'details' => ['crons' => array_keys($our_crons)]
        ];
    }
    
    /**
     * Get performance metrics
     *
     * @return array
     */
    protected function get_metrics(): array {
        global $wpdb;
        
        $metrics = [
            'uptime' => $this->get_uptime(),
            'total_generations' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_generations"
            ),
            'active_templates' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_prompt_templates"
            ),
            'api_calls_today' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_api_usage_log 
                     WHERE created_at > %s",
                    date('Y-m-d 00:00:00')
                )
            ),
            'cache_hit_rate' => $this->get_cache_hit_rate(),
            'error_count_24h' => get_transient('zippicks_error_count') ?: 0
        ];
        
        return $metrics;
    }
    
    /**
     * Get uptime
     *
     * @return string
     */
    protected function get_uptime(): string {
        $install_date = get_option('zippicks_install_date');
        if (!$install_date) {
            return 'Unknown';
        }
        
        $diff = time() - strtotime($install_date);
        $days = floor($diff / DAY_IN_SECONDS);
        $hours = floor(($diff % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
        
        return "{$days}d {$hours}h";
    }
    
    /**
     * Get cache hit rate
     *
     * @return float
     */
    protected function get_cache_hit_rate(): float {
        $cache_manager = new ZipPicks_Master_Critic_Cache_Manager();
        $stats = $cache_manager->get_stats();
        
        return $stats['hit_rate'] ?? 0.0;
    }
    
    /**
     * Convert size to bytes
     *
     * @param string $size
     * @return int
     */
    protected function convert_to_bytes(string $size): int {
        $unit = strtolower(substr($size, -1));
        $value = (int) $size;
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Run scheduled health checks
     */
    public function run_scheduled_checks(): void {
        $status = $this->get_status();
        
        // Log results
        if ($this->logger) {
            $this->logger->info('Health check completed', $status);
        }
        
        // Send alerts if critical
        if ($status['status'] === self::STATUS_CRITICAL) {
            $this->send_critical_alert($status);
        }
        
        // Update monitoring service
        $this->update_monitoring_service($status);
    }
    
    /**
     * Send critical alert
     *
     * @param array $status
     */
    protected function send_critical_alert(array $status): void {
        $admin_email = get_option('admin_email');
        $subject = sprintf('[%s] Master Critic Critical Health Alert', get_bloginfo('name'));
        
        $message = "Critical health issues detected:\n\n";
        foreach ($status['issues'] as $issue) {
            $message .= "- $issue\n";
        }
        
        $message .= "\n\nPlease check the health status page for details.";
        
        wp_mail($admin_email, $subject, $message);
        
        // Trigger action for external monitoring
        do_action('zippicks_health_critical', $status);
    }
    
    /**
     * Update external monitoring service
     *
     * @param array $status
     */
    protected function update_monitoring_service(array $status): void {
        $monitoring_url = get_option('zippicks_monitoring_url');
        
        if (!$monitoring_url) {
            return;
        }
        
        wp_remote_post($monitoring_url, [
            'body' => json_encode($status),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => get_option('zippicks_monitoring_key')
            ],
            'timeout' => 5
        ]);
    }
    
    /**
     * Get health report
     *
     * @return string
     */
    public function get_health_report(): string {
        $status = $this->get_status();
        
        $report = "# ZipPicks Master Critic Health Report\n\n";
        $report .= "Generated: " . current_time('Y-m-d H:i:s') . "\n";
        $report .= "Status: " . strtoupper($status['status']) . "\n\n";
        
        $report .= "## Checks\n";
        foreach ($status['checks'] as $check => $result) {
            $emoji = $result['status'] === self::STATUS_HEALTHY ? '✅' : 
                    ($result['status'] === self::STATUS_WARNING ? '⚠️' : '❌');
            
            $report .= "$emoji **$check**: {$result['message']}\n";
            
            if (!empty($result['details'])) {
                foreach ($result['details'] as $key => $value) {
                    $report .= "   - $key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
                }
            }
        }
        
        $report .= "\n## Metrics\n";
        foreach ($status['metrics'] as $metric => $value) {
            $report .= "- $metric: $value\n";
        }
        
        return $report;
    }
}