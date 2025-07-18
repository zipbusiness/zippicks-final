<?php
/**
 * Performance Monitoring for Master Critic
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

/**
 * Performance monitoring and optimization
 */
class ZipPicks_Master_Critic_Performance_Monitor {
    
    /**
     * Metrics storage key
     *
     * @var string
     */
    const METRICS_KEY = 'zippicks_performance_metrics';
    
    /**
     * Current request start time
     *
     * @var float
     */
    protected float $request_start;
    
    /**
     * Query count at start
     *
     * @var int
     */
    protected int $initial_queries;
    
    /**
     * Memory usage at start
     *
     * @var int
     */
    protected int $initial_memory;
    
    /**
     * Performance marks
     *
     * @var array
     */
    protected array $marks = [];
    
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
        $this->request_start = microtime(true);
        $this->initial_queries = get_num_queries();
        $this->initial_memory = memory_get_usage(true);
        
        // Load logger if available
        if (class_exists('ZipPicks_Master_Critic_Logger')) {
            $this->logger = new ZipPicks_Master_Critic_Logger();
        }
        
        // Initialize monitoring
        $this->init_monitoring();
    }
    
    /**
     * Initialize monitoring hooks
     */
    protected function init_monitoring(): void {
        // Track database queries
        add_filter('query', [$this, 'log_slow_query']);
        
        // Track API calls
        add_action('http_api_debug', [$this, 'log_api_call'], 10, 5);
        
        // Track shutdown performance
        add_action('shutdown', [$this, 'log_request_performance'], 0);
        
        // Add performance headers
        add_action('send_headers', [$this, 'add_performance_headers']);
    }
    
    /**
     * Mark a performance checkpoint
     *
     * @param string $label
     * @param array $data
     */
    public function mark(string $label, array $data = []): void {
        $this->marks[$label] = [
            'time' => microtime(true),
            'memory' => memory_get_usage(true),
            'queries' => get_num_queries(),
            'data' => $data
        ];
    }
    
    /**
     * Get time between marks
     *
     * @param string $start
     * @param string $end
     * @return float|null
     */
    public function get_mark_duration(string $start, string $end): ?float {
        if (!isset($this->marks[$start]) || !isset($this->marks[$end])) {
            return null;
        }
        
        return $this->marks[$end]['time'] - $this->marks[$start]['time'];
    }
    
    /**
     * Record custom metrics
     *
     * @param array $metrics
     */
    public function record_metrics(array $metrics): void {
        $stored_metrics = get_option(self::METRICS_KEY, []);
        
        $metrics['timestamp'] = current_time('c');
        $metrics['request_id'] = $this->get_request_id();
        
        // Keep only last 1000 entries
        $stored_metrics[] = $metrics;
        if (count($stored_metrics) > 1000) {
            $stored_metrics = array_slice($stored_metrics, -1000);
        }
        
        update_option(self::METRICS_KEY, $stored_metrics);
        
        // Also send to external monitoring if configured
        $this->send_to_monitoring($metrics);
    }
    
    /**
     * Get performance statistics
     *
     * @param string $period
     * @return array
     */
    public function get_stats(string $period = '24h'): array {
        $metrics = get_option(self::METRICS_KEY, []);
        
        if (empty($metrics)) {
            return $this->get_empty_stats();
        }
        
        // Filter by period
        $cutoff = $this->get_cutoff_time($period);
        $filtered_metrics = array_filter($metrics, function($metric) use ($cutoff) {
            return strtotime($metric['timestamp']) > $cutoff;
        });
        
        if (empty($filtered_metrics)) {
            return $this->get_empty_stats();
        }
        
        // Calculate statistics
        $execution_times = array_column($filtered_metrics, 'execution_time');
        $memory_peaks = array_column($filtered_metrics, 'memory_peak');
        $query_counts = array_column($filtered_metrics, 'queries');
        
        return [
            'period' => $period,
            'total_requests' => count($filtered_metrics),
            'execution_time' => [
                'avg' => array_sum($execution_times) / count($execution_times),
                'min' => min($execution_times),
                'max' => max($execution_times),
                'p50' => $this->calculate_percentile($execution_times, 0.5),
                'p95' => $this->calculate_percentile($execution_times, 0.95),
                'p99' => $this->calculate_percentile($execution_times, 0.99)
            ],
            'memory' => [
                'avg' => array_sum($memory_peaks) / count($memory_peaks),
                'min' => min($memory_peaks),
                'max' => max($memory_peaks)
            ],
            'queries' => [
                'avg' => array_sum($query_counts) / count($query_counts),
                'min' => min($query_counts),
                'max' => max($query_counts)
            ],
            'slow_requests' => $this->count_slow_requests($filtered_metrics),
            'error_rate' => $this->calculate_error_rate($filtered_metrics)
        ];
    }
    
    /**
     * Log slow database query
     *
     * @param string $query
     * @return string
     */
    public function log_slow_query(string $query): string {
        static $query_start = null;
        
        if ($query_start === null) {
            $query_start = microtime(true);
            add_filter('query_results', function($results) use (&$query_start, $query) {
                $duration = microtime(true) - $query_start;
                $query_start = null;
                
                // Log queries taking more than 0.1 seconds
                if ($duration > 0.1) {
                    $this->log_performance_issue('slow_query', [
                        'query' => $query,
                        'duration' => $duration,
                        'backtrace' => wp_debug_backtrace_summary()
                    ]);
                }
                
                return $results;
            });
        }
        
        return $query;
    }
    
    /**
     * Log API call performance
     *
     * @param mixed $response
     * @param string $context
     * @param string $class
     * @param array $parsed_args
     * @param string $url
     */
    public function log_api_call($response, string $context, string $class, array $parsed_args, string $url): void {
        $duration = 0;
        if (isset($parsed_args['_start_time'])) {
            $duration = microtime(true) - $parsed_args['_start_time'];
        }
        
        // Log slow API calls (> 2 seconds)
        if ($duration > 2.0) {
            $this->log_performance_issue('slow_api_call', [
                'url' => $url,
                'duration' => $duration,
                'method' => $parsed_args['method'] ?? 'GET',
                'response_code' => wp_remote_retrieve_response_code($response)
            ]);
        }
        
        // Track API metrics
        $this->track_api_metrics($url, $duration, $response);
    }
    
    /**
     * Log request performance at shutdown
     */
    public function log_request_performance(): void {
        $execution_time = microtime(true) - $this->request_start;
        $memory_peak = memory_get_peak_usage(true);
        $queries = get_num_queries();
        
        // Record metrics
        $metrics = [
            'execution_time' => $execution_time,
            'memory_peak' => $memory_peak,
            'memory_usage' => memory_get_usage(true),
            'queries' => $queries,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'marks' => $this->marks
        ];
        
        $this->record_metrics($metrics);
        
        // Update response time tracking
        $this->update_response_times($execution_time);
        
        // Log slow requests
        if ($execution_time > 2.0) {
            $this->log_performance_issue('slow_request', $metrics);
        }
        
        // Log high memory usage
        if ($memory_peak > 100 * MB_IN_BYTES) {
            $this->log_performance_issue('high_memory', $metrics);
        }
        
        // Log high query count
        if ($queries > 100) {
            $this->log_performance_issue('high_queries', $metrics);
        }
    }
    
    /**
     * Add performance headers
     */
    public function add_performance_headers(): void {
        if (!headers_sent()) {
            $execution_time = microtime(true) - $this->request_start;
            header('X-Response-Time: ' . round($execution_time * 1000) . 'ms');
            header('X-Query-Count: ' . get_num_queries());
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                header('X-Memory-Peak: ' . size_format(memory_get_peak_usage(true)));
            }
        }
    }
    
    /**
     * Get request ID
     *
     * @return string
     */
    protected function get_request_id(): string {
        static $request_id = null;
        
        if ($request_id === null) {
            $request_id = substr(md5(uniqid('', true)), 0, 8);
        }
        
        return $request_id;
    }
    
    /**
     * Get cutoff time for period
     *
     * @param string $period
     * @return int
     */
    protected function get_cutoff_time(string $period): int {
        switch ($period) {
            case '1h':
                return time() - HOUR_IN_SECONDS;
            case '24h':
                return time() - DAY_IN_SECONDS;
            case '7d':
                return time() - WEEK_IN_SECONDS;
            case '30d':
                return time() - MONTH_IN_SECONDS;
            default:
                return time() - DAY_IN_SECONDS;
        }
    }
    
    /**
     * Get empty stats structure
     *
     * @return array
     */
    protected function get_empty_stats(): array {
        return [
            'period' => '24h',
            'total_requests' => 0,
            'execution_time' => [
                'avg' => 0,
                'min' => 0,
                'max' => 0,
                'p50' => 0,
                'p95' => 0,
                'p99' => 0
            ],
            'memory' => [
                'avg' => 0,
                'min' => 0,
                'max' => 0
            ],
            'queries' => [
                'avg' => 0,
                'min' => 0,
                'max' => 0
            ],
            'slow_requests' => 0,
            'error_rate' => 0
        ];
    }
    
    /**
     * Calculate percentile
     *
     * @param array $values
     * @param float $percentile
     * @return float
     */
    protected function calculate_percentile(array $values, float $percentile): float {
        if (empty($values)) {
            return 0;
        }
        
        sort($values);
        $index = ceil(count($values) * $percentile) - 1;
        
        return $values[$index] ?? 0;
    }
    
    /**
     * Count slow requests
     *
     * @param array $metrics
     * @return int
     */
    protected function count_slow_requests(array $metrics): int {
        return count(array_filter($metrics, function($metric) {
            return ($metric['execution_time'] ?? 0) > 2.0;
        }));
    }
    
    /**
     * Calculate error rate
     *
     * @param array $metrics
     * @return float
     */
    protected function calculate_error_rate(array $metrics): float {
        if (empty($metrics)) {
            return 0;
        }
        
        $errors = count(array_filter($metrics, function($metric) {
            return ($metric['error_count'] ?? 0) > 0;
        }));
        
        return ($errors / count($metrics)) * 100;
    }
    
    /**
     * Log performance issue
     *
     * @param string $type
     * @param array $data
     */
    protected function log_performance_issue(string $type, array $data): void {
        if ($this->logger) {
            $this->logger->warning("Performance issue: $type", $data);
        }
        
        // Trigger action for external monitoring
        do_action('zippicks_performance_issue', $type, $data);
    }
    
    /**
     * Track API metrics
     *
     * @param string $url
     * @param float $duration
     * @param mixed $response
     */
    protected function track_api_metrics(string $url, float $duration, $response): void {
        $api_metrics = get_transient('zippicks_api_metrics') ?: [];
        
        $domain = parse_url($url, PHP_URL_HOST);
        $status_code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
        
        if (!isset($api_metrics[$domain])) {
            $api_metrics[$domain] = [
                'calls' => 0,
                'total_time' => 0,
                'errors' => 0,
                'status_codes' => []
            ];
        }
        
        $api_metrics[$domain]['calls']++;
        $api_metrics[$domain]['total_time'] += $duration;
        
        if (is_wp_error($response) || $status_code >= 400) {
            $api_metrics[$domain]['errors']++;
        }
        
        $api_metrics[$domain]['status_codes'][$status_code] = 
            ($api_metrics[$domain]['status_codes'][$status_code] ?? 0) + 1;
        
        set_transient('zippicks_api_metrics', $api_metrics, HOUR_IN_SECONDS);
    }
    
    /**
     * Update response times tracking
     *
     * @param float $time
     */
    protected function update_response_times(float $time): void {
        $response_times = get_transient('zippicks_response_times') ?: [];
        
        $response_times[] = $time;
        
        // Keep only last 100 entries
        if (count($response_times) > 100) {
            $response_times = array_slice($response_times, -100);
        }
        
        set_transient('zippicks_response_times', $response_times, HOUR_IN_SECONDS);
    }
    
    /**
     * Send metrics to external monitoring
     *
     * @param array $metrics
     */
    protected function send_to_monitoring(array $metrics): void {
        $monitoring_endpoint = get_option('zippicks_monitoring_endpoint');
        
        if (!$monitoring_endpoint) {
            return;
        }
        
        // Send asynchronously
        wp_schedule_single_event(time() + 1, 'zippicks_send_metrics', [$metrics]);
    }
    
    /**
     * Get optimization recommendations
     *
     * @return array
     */
    public function get_recommendations(): array {
        $stats = $this->get_stats('24h');
        $recommendations = [];
        
        // Check execution time
        if ($stats['execution_time']['avg'] > 1.0) {
            $recommendations[] = [
                'type' => 'performance',
                'severity' => 'warning',
                'message' => 'Average execution time is high',
                'suggestion' => 'Consider implementing additional caching or optimizing database queries'
            ];
        }
        
        // Check memory usage
        if ($stats['memory']['avg'] > 50 * MB_IN_BYTES) {
            $recommendations[] = [
                'type' => 'memory',
                'severity' => 'warning',
                'message' => 'Average memory usage is high',
                'suggestion' => 'Review code for memory leaks or increase PHP memory limit'
            ];
        }
        
        // Check query count
        if ($stats['queries']['avg'] > 50) {
            $recommendations[] = [
                'type' => 'database',
                'severity' => 'warning',
                'message' => 'High number of database queries',
                'suggestion' => 'Implement query caching or optimize database access patterns'
            ];
        }
        
        // Check slow requests
        if ($stats['slow_requests'] > $stats['total_requests'] * 0.1) {
            $recommendations[] = [
                'type' => 'performance',
                'severity' => 'critical',
                'message' => 'More than 10% of requests are slow',
                'suggestion' => 'Investigate slow request patterns and optimize critical paths'
            ];
        }
        
        // Check error rate
        if ($stats['error_rate'] > 5) {
            $recommendations[] = [
                'type' => 'reliability',
                'severity' => 'critical',
                'message' => 'High error rate detected',
                'suggestion' => 'Review error logs and fix underlying issues'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Generate performance report
     *
     * @param string $period
     * @return string
     */
    public function generate_report(string $period = '24h'): string {
        $stats = $this->get_stats($period);
        $recommendations = $this->get_recommendations();
        
        $report = "# Performance Report\n\n";
        $report .= "Period: $period\n";
        $report .= "Generated: " . current_time('Y-m-d H:i:s') . "\n\n";
        
        $report .= "## Summary\n";
        $report .= "- Total Requests: {$stats['total_requests']}\n";
        $report .= "- Slow Requests: {$stats['slow_requests']}\n";
        $report .= "- Error Rate: {$stats['error_rate']}%\n\n";
        
        $report .= "## Execution Time\n";
        $report .= "- Average: " . round($stats['execution_time']['avg'], 3) . "s\n";
        $report .= "- P50: " . round($stats['execution_time']['p50'], 3) . "s\n";
        $report .= "- P95: " . round($stats['execution_time']['p95'], 3) . "s\n";
        $report .= "- P99: " . round($stats['execution_time']['p99'], 3) . "s\n\n";
        
        $report .= "## Memory Usage\n";
        $report .= "- Average: " . size_format($stats['memory']['avg']) . "\n";
        $report .= "- Peak: " . size_format($stats['memory']['max']) . "\n\n";
        
        $report .= "## Database Queries\n";
        $report .= "- Average: " . round($stats['queries']['avg']) . "\n";
        $report .= "- Maximum: {$stats['queries']['max']}\n\n";
        
        if (!empty($recommendations)) {
            $report .= "## Recommendations\n";
            foreach ($recommendations as $rec) {
                $emoji = $rec['severity'] === 'critical' ? '🔴' : '⚠️';
                $report .= "$emoji **{$rec['message']}**\n";
                $report .= "   {$rec['suggestion']}\n\n";
            }
        }
        
        return $report;
    }
}