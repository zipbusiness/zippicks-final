<?php
/**
 * Performance Tracker for ZipPicks Vibes
 * 
 * Enhanced performance tracking with static usage patterns, memory tracking,
 * and automatic slow operation logging.
 * 
 * @package ZipPicksVibes
 * @subpackage Monitoring
 * @since 2.0.0
 * @version 2.1.0
 */

namespace ZipPicksVibes\Monitoring;

use ZipPicksVibes\Cache\CacheInterface;
use ZipPicksVibes\Audit\AuditLogger;

/**
 * PerformanceTracker Class
 * 
 * Enhanced monitoring with static usage, memory tracking, and auto-alerting
 */
class PerformanceTracker {
    
    /**
     * Metric types
     */
    const TYPE_QUERY = 'query';
    const TYPE_API = 'api';
    const TYPE_CACHE = 'cache';
    const TYPE_RENDER = 'render';
    const TYPE_SYSTEM = 'system';
    const TYPE_MEMORY = 'memory';
    
    /**
     * Performance thresholds for auto-logging (in milliseconds)
     */
    const SLOW_QUERY_THRESHOLD = 1000;
    const SLOW_API_THRESHOLD = 2000;
    const SLOW_RENDER_THRESHOLD = 500;
    const SLOW_SYSTEM_THRESHOLD = 1000;
    
    /**
     * Memory usage thresholds (in bytes)
     */
    const HIGH_MEMORY_THRESHOLD = 128 * 1024 * 1024; // 128MB
    const CRITICAL_MEMORY_THRESHOLD = 256 * 1024 * 1024; // 256MB
    
    /**
     * Static instance for global access
     * 
     * @var self|null
     */
    private static ?self $instance = null;
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger;
    
    /**
     * Cache instance
     * 
     * @var CacheInterface|null
     */
    private ?CacheInterface $cache;
    
    /**
     * Audit logger for slow operations
     * 
     * @var AuditLogger|null
     */
    private ?AuditLogger $auditLogger;
    
    /**
     * Active timers
     * 
     * @var array
     */
    private array $timers = [];
    
    /**
     * Metrics buffer
     * 
     * @var array
     */
    private array $buffer = [];
    
    /**
     * Buffer size before flush
     * 
     * @var int
     */
    private int $bufferSize = 10;
    
    /**
     * Memory tracking data
     * 
     * @var array
     */
    private array $memoryTracking = [
        'start_memory' => 0,
        'peak_memory' => 0,
        'deltas' => []
    ];
    
    /**
     * Static timer storage for global access
     * 
     * @var array
     */
    private static array $staticTimers = [];
    
    /**
     * Constructor
     * 
     * @param mixed $logger Logger instance
     * @param CacheInterface|null $cache Cache instance
     * @param AuditLogger|null $auditLogger Audit logger for slow operations
     */
    public function __construct($logger = null, ?CacheInterface $cache = null, ?AuditLogger $auditLogger = null) {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->auditLogger = $auditLogger;
        
        // Initialize memory tracking
        $this->memoryTracking['start_memory'] = memory_get_usage(true);
        $this->memoryTracking['peak_memory'] = memory_get_peak_usage(true);
        
        // Register shutdown function to flush buffer
        register_shutdown_function([$this, 'flush']);
        
        // Set static instance if not already set
        if (self::$instance === null) {
            self::$instance = $this;
        }
    }
    
    /**
     * Start a timer
     * 
     * @param string $name Timer name
     * @param string $type Metric type
     * @param array $context Additional context
     * @return string Timer ID
     */
    public function startTimer(string $name, string $type = self::TYPE_SYSTEM, array $context = []): string {
        $timerId = uniqid($name . '_', true);
        
        $this->timers[$timerId] = [
            'name' => $name,
            'type' => $type,
            'start' => microtime(true),
            'context' => $context
        ];
        
        return $timerId;
    }
    
    /**
     * End a timer and record metric with auto-logging of slow operations
     * 
     * @param string $timerId Timer ID
     * @param array $additionalContext Additional context to merge
     * @return float Duration in milliseconds
     */
    public function endTimer(string $timerId, array $additionalContext = []): float {
        if (!isset($this->timers[$timerId])) {
            if ($this->logger) {
                $this->logger->warning('Attempted to end non-existent timer', ['timer_id' => $timerId]);
            }
            return 0;
        }
        
        $timer = $this->timers[$timerId];
        $duration = (microtime(true) - $timer['start']) * 1000; // Convert to milliseconds
        
        // Merge context and add memory tracking
        $context = array_merge($timer['context'], $additionalContext);
        $context = $this->addMemoryContext($context);
        
        // Record metric
        $this->recordMetric(
            $timer['type'],
            $timer['name'],
            $duration,
            $context
        );
        
        // Auto-log slow operations
        $this->checkSlowOperation($timer['type'], $timer['name'], $duration, $context);
        
        // Clean up timer
        unset($this->timers[$timerId]);
        
        return $duration;
    }
    
    /**
     * Static method to start a timer (global access)
     * 
     * @param string $name Timer name
     * @param string $type Metric type
     * @param array $context Additional context
     * @return string Timer ID
     */
    public static function start(string $name, string $type = self::TYPE_SYSTEM, array $context = []): string {
        $instance = self::getInstance();
        
        if ($instance) {
            return $instance->startTimer($name, $type, $context);
        }
        
        // Fallback for static usage without instance
        $timerId = uniqid($name . '_', true);
        self::$staticTimers[$timerId] = [
            'name' => $name,
            'type' => $type,
            'start' => microtime(true),
            'context' => $context
        ];
        
        return $timerId;
    }
    
    /**
     * Static method to stop a timer (global access)
     * 
     * @param string $timerId Timer ID
     * @param array $additionalContext Additional context to merge
     * @return float Duration in milliseconds
     */
    public static function stop(string $timerId, array $additionalContext = []): float {
        $instance = self::getInstance();
        
        if ($instance) {
            return $instance->endTimer($timerId, $additionalContext);
        }
        
        // Fallback for static usage without instance
        if (!isset(self::$staticTimers[$timerId])) {
            error_log("Attempted to end non-existent static timer: {$timerId}");
            return 0;
        }
        
        $timer = self::$staticTimers[$timerId];
        $duration = (microtime(true) - $timer['start']) * 1000;
        
        // Clean up timer
        unset(self::$staticTimers[$timerId]);
        
        // Log to error_log as fallback
        if ($duration > self::SLOW_SYSTEM_THRESHOLD) {
            error_log("SLOW OPERATION: {$timer['name']} took {$duration}ms");
        }
        
        return $duration;
    }
    
    /**
     * Get or create static instance
     * 
     * @return self|null
     */
    private static function getInstance(): ?self {
        return self::$instance;
    }
    
    /**
     * Record a metric
     * 
     * @param string $type Metric type
     * @param string $name Metric name
     * @param float $value Metric value
     * @param array $context Additional context
     */
    public function recordMetric(string $type, string $name, float $value, array $context = []): void {
        $metric = [
            'metric_type' => $type,
            'metric_name' => $name,
            'metric_value' => round($value, 4),
            'endpoint' => $context['endpoint'] ?? $this->getCurrentEndpoint(),
            'user_id' => $context['user_id'] ?? get_current_user_id(),
            'context' => array_diff_key($context, array_flip(['endpoint', 'user_id'])),
            'created_at' => current_time('mysql')
        ];
        
        // Add to buffer
        $this->buffer[] = $metric;
        
        // Flush if buffer is full
        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }
        
        // Log if logger available
        if ($this->logger) {
            $this->logger->debug('Performance metric recorded', $metric);
        }
    }
    
    /**
     * Record a query metric
     * 
     * @param string $query Query name/description
     * @param float $duration Duration in milliseconds
     * @param array $context Additional context
     */
    public function recordQuery(string $query, float $duration, array $context = []): void {
        $context['query_type'] = $context['query_type'] ?? 'select';
        $context['table'] = $context['table'] ?? 'unknown';
        
        $this->recordMetric(self::TYPE_QUERY, $query, $duration, $context);
    }
    
    /**
     * Record an API metric
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param float $duration Duration in milliseconds
     * @param int $statusCode Response status code
     * @param array $context Additional context
     */
    public function recordApi(string $endpoint, string $method, float $duration, int $statusCode = 200, array $context = []): void {
        $context['method'] = $method;
        $context['status_code'] = $statusCode;
        $context['endpoint'] = $endpoint;
        
        $this->recordMetric(self::TYPE_API, "api_{$method}_{$endpoint}", $duration, $context);
    }
    
    /**
     * Record a cache metric
     * 
     * @param string $operation Cache operation (get, set, delete)
     * @param bool $hit Whether it was a cache hit
     * @param float $duration Duration in milliseconds
     * @param array $context Additional context
     */
    public function recordCache(string $operation, bool $hit, float $duration, array $context = []): void {
        $context['operation'] = $operation;
        $context['hit'] = $hit;
        
        $metricName = "cache_{$operation}" . ($hit ? '_hit' : '_miss');
        $this->recordMetric(self::TYPE_CACHE, $metricName, $duration, $context);
    }
    
    /**
     * Record a render metric
     * 
     * @param string $template Template name
     * @param float $duration Duration in milliseconds
     * @param array $context Additional context
     */
    public function recordRender(string $template, float $duration, array $context = []): void {
        $context['template'] = $template;
        
        $this->recordMetric(self::TYPE_RENDER, "render_{$template}", $duration, $context);
    }
    
    /**
     * Get current metrics summary
     * 
     * @param string $type Optional type filter
     * @param int $minutes Time window in minutes
     * @return array
     */
    public function getSummary(string $type = '', int $minutes = 60): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_performance_metrics';
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        
        // Build query
        $where = "created_at >= %s";
        $params = [$cutoff];
        
        if ($type) {
            $where .= " AND metric_type = %s";
            $params[] = $type;
        }
        
        // Get summary statistics
        $query = $wpdb->prepare(
            "SELECT 
                metric_type,
                metric_name,
                COUNT(*) as count,
                AVG(metric_value) as avg_value,
                MIN(metric_value) as min_value,
                MAX(metric_value) as max_value,
                STDDEV(metric_value) as std_dev,
                PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY metric_value) as median,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY metric_value) as p95,
                PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY metric_value) as p99
            FROM {$table}
            WHERE {$where}
            GROUP BY metric_type, metric_name
            ORDER BY metric_type, count DESC",
            $params
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Process results
        $summary = [];
        foreach ($results as $row) {
            $type = $row['metric_type'];
            if (!isset($summary[$type])) {
                $summary[$type] = [];
            }
            
            $summary[$type][$row['metric_name']] = [
                'count' => (int) $row['count'],
                'avg' => round((float) $row['avg_value'], 2),
                'min' => round((float) $row['min_value'], 2),
                'max' => round((float) $row['max_value'], 2),
                'std_dev' => round((float) $row['std_dev'], 2),
                'median' => round((float) $row['median'], 2),
                'p95' => round((float) $row['p95'], 2),
                'p99' => round((float) $row['p99'], 2)
            ];
        }
        
        return $summary;
    }
    
    /**
     * Get slow queries
     * 
     * @param float $threshold Threshold in milliseconds
     * @param int $limit Result limit
     * @return array
     */
    public function getSlowQueries(float $threshold = 1000, int $limit = 100): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_performance_metrics';
        
        $query = $wpdb->prepare(
            "SELECT *
            FROM {$table}
            WHERE metric_type = %s
            AND metric_value > %f
            ORDER BY metric_value DESC, created_at DESC
            LIMIT %d",
            self::TYPE_QUERY,
            $threshold,
            $limit
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Decode context JSON
        foreach ($results as &$row) {
            if (!empty($row['context'])) {
                $row['context'] = json_decode($row['context'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Flush metrics buffer to database
     */
    public function flush(): void {
        if (empty($this->buffer)) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_performance_metrics';
        
        // Batch insert
        $values = [];
        $placeholders = [];
        
        foreach ($this->buffer as $metric) {
            // Encode context to JSON
            if (!empty($metric['context'])) {
                $metric['context'] = wp_json_encode($metric['context']);
            } else {
                $metric['context'] = null;
            }
            
            $values = array_merge($values, [
                $metric['metric_type'],
                $metric['metric_name'],
                $metric['metric_value'],
                $metric['endpoint'],
                $metric['user_id'] ?: null,
                $metric['context'],
                $metric['created_at']
            ]);
            
            $placeholders[] = '(%s, %s, %f, %s, %d, %s, %s)';
        }
        
        if (!empty($placeholders)) {
            $query = "INSERT INTO {$table} 
                     (metric_type, metric_name, metric_value, endpoint, user_id, context, created_at) 
                     VALUES " . implode(', ', $placeholders);
            
            $wpdb->query($wpdb->prepare($query, $values));
        }
        
        // Clear buffer
        $this->buffer = [];
    }
    
    /**
     * Get current endpoint
     * 
     * @return string
     */
    private function getCurrentEndpoint(): string {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return $_SERVER['REQUEST_URI'] ?? 'unknown';
        }
        
        if (is_admin()) {
            global $pagenow;
            return 'admin:' . ($pagenow ?? 'unknown');
        }
        
        return 'frontend:' . ($_SERVER['REQUEST_URI'] ?? 'unknown');
    }
    
    /**
     * Clean old metrics
     * 
     * @param int $days Days to keep
     * @return int Number of deleted records
     */
    public function cleanOldMetrics(int $days = 7): int {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_performance_metrics';
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->delete(
            $table,
            ['created_at <' => $cutoff],
            ['%s']
        );
    }
    
    /**
     * Add memory usage context to metrics
     * 
     * @param array $context Existing context
     * @return array Context with memory information
     */
    private function addMemoryContext(array $context): array {
        $current_memory = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        
        // Calculate memory delta since last measurement
        $memory_delta = $current_memory - $this->memoryTracking['start_memory'];
        
        $context['memory_current'] = $current_memory;
        $context['memory_peak'] = $peak_memory;
        $context['memory_delta'] = $memory_delta;
        $context['memory_limit'] = $this->getMemoryLimit();
        
        // Update tracking
        $this->memoryTracking['peak_memory'] = max($this->memoryTracking['peak_memory'], $peak_memory);
        $this->memoryTracking['deltas'][] = $memory_delta;
        
        // Check for high memory usage
        if ($current_memory > self::HIGH_MEMORY_THRESHOLD) {
            $context['memory_warning'] = 'high';
            
            if ($current_memory > self::CRITICAL_MEMORY_THRESHOLD) {
                $context['memory_warning'] = 'critical';
            }
        }
        
        return $context;
    }
    
    /**
     * Check if operation is slow and auto-log it
     * 
     * @param string $type Operation type
     * @param string $name Operation name
     * @param float $duration Duration in milliseconds
     * @param array $context Operation context
     */
    private function checkSlowOperation(string $type, string $name, float $duration, array $context): void {
        $threshold = $this->getThresholdForType($type);
        
        if ($duration > $threshold) {
            // Log to audit logger if available
            if ($this->auditLogger) {
                $this->auditLogger->logWarning('slow_operation_detected', [
                    'operation_type' => $type,
                    'operation_name' => $name,
                    'duration_ms' => $duration,
                    'threshold_ms' => $threshold,
                    'severity_level' => $this->getSeverityLevel($duration, $threshold),
                    'context' => $context,
                    'memory_info' => [
                        'current' => $context['memory_current'] ?? null,
                        'peak' => $context['memory_peak'] ?? null,
                        'delta' => $context['memory_delta'] ?? null
                    ]
                ]);
            }
            
            // Log to standard logger
            if ($this->logger) {
                $this->logger->warning('Slow operation detected', [
                    'type' => $type,
                    'name' => $name,
                    'duration' => $duration,
                    'threshold' => $threshold,
                    'context' => $context
                ]);
            }
            
            // Record as a system metric
            $this->recordMetric(
                self::TYPE_SYSTEM,
                'slow_operation',
                $duration,
                array_merge($context, [
                    'slow_operation_type' => $type,
                    'slow_operation_name' => $name,
                    'threshold_exceeded' => $duration - $threshold
                ])
            );
        }
    }
    
    /**
     * Get performance threshold for operation type
     * 
     * @param string $type Operation type
     * @return float Threshold in milliseconds
     */
    private function getThresholdForType(string $type): float {
        switch ($type) {
            case self::TYPE_QUERY:
                return self::SLOW_QUERY_THRESHOLD;
            case self::TYPE_API:
                return self::SLOW_API_THRESHOLD;
            case self::TYPE_RENDER:
                return self::SLOW_RENDER_THRESHOLD;
            case self::TYPE_CACHE:
                return 100; // Cache operations should be very fast
            case self::TYPE_MEMORY:
                return 50; // Memory operations should be instant
            default:
                return self::SLOW_SYSTEM_THRESHOLD;
        }
    }
    
    /**
     * Get severity level based on how much threshold was exceeded
     * 
     * @param float $duration Actual duration
     * @param float $threshold Threshold value
     * @return string Severity level
     */
    private function getSeverityLevel(float $duration, float $threshold): string {
        $ratio = $duration / $threshold;
        
        if ($ratio >= 5.0) {
            return 'critical';
        } elseif ($ratio >= 3.0) {
            return 'high';
        } elseif ($ratio >= 2.0) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Track memory usage delta
     * 
     * @param string $operation_name Name of the operation
     * @param callable $callback Operation to track
     * @param array $context Additional context
     * @return mixed Operation result
     */
    public function trackMemoryUsage(string $operation_name, callable $callback, array $context = []) {
        $memory_before = memory_get_usage(true);
        $peak_before = memory_get_peak_usage(true);
        
        $start_time = microtime(true);
        $result = $callback();
        $duration = (microtime(true) - $start_time) * 1000;
        
        $memory_after = memory_get_usage(true);
        $peak_after = memory_get_peak_usage(true);
        
        $memory_delta = $memory_after - $memory_before;
        $peak_delta = $peak_after - $peak_before;
        
        // Record memory metric
        $this->recordMetric(
            self::TYPE_MEMORY,
            $operation_name,
            $duration,
            array_merge($context, [
                'memory_before' => $memory_before,
                'memory_after' => $memory_after,
                'memory_delta' => $memory_delta,
                'peak_before' => $peak_before,
                'peak_after' => $peak_after,
                'peak_delta' => $peak_delta,
                'memory_efficiency' => $memory_delta / max(1, $duration) // bytes per ms
            ])
        );
        
        // Log high memory usage
        if (abs($memory_delta) > 1024 * 1024) { // 1MB threshold
            if ($this->auditLogger) {
                $this->auditLogger->logWarning('high_memory_usage_operation', [
                    'operation' => $operation_name,
                    'memory_delta_mb' => round($memory_delta / 1024 / 1024, 2),
                    'duration_ms' => $duration,
                    'context' => $context
                ]);
            }
        }
        
        return $result;
    }
    
    /**
     * Get memory limit in bytes
     * 
     * @return int Memory limit in bytes
     */
    private function getMemoryLimit(): int {
        $limit = ini_get('memory_limit');
        
        if (preg_match('/^(\d+)(.)$/', $limit, $matches)) {
            $value = (int) $matches[1];
            switch (strtoupper($matches[2])) {
                case 'G':
                    $value *= 1024;
                case 'M':
                    $value *= 1024;
                case 'K':
                    $value *= 1024;
            }
            return $value;
        }
        
        return -1;
    }
    
    /**
     * Get memory usage summary
     * 
     * @return array Memory usage statistics
     */
    public function getMemoryUsageSummary(): array {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->getMemoryLimit();
        
        return [
            'current_usage' => $current,
            'current_usage_mb' => round($current / 1024 / 1024, 2),
            'peak_usage' => $peak,
            'peak_usage_mb' => round($peak / 1024 / 1024, 2),
            'memory_limit' => $limit,
            'memory_limit_mb' => $limit > 0 ? round($limit / 1024 / 1024, 2) : 'unlimited',
            'usage_percentage' => $limit > 0 ? round(($current / $limit) * 100, 2) : 0,
            'start_memory' => $this->memoryTracking['start_memory'],
            'memory_increase' => $current - $this->memoryTracking['start_memory'],
            'memory_increase_mb' => round(($current - $this->memoryTracking['start_memory']) / 1024 / 1024, 2),
            'average_delta' => count($this->memoryTracking['deltas']) > 0 ? 
                array_sum($this->memoryTracking['deltas']) / count($this->memoryTracking['deltas']) : 0
        ];
    }
    
    /**
     * Static method to track memory usage globally
     * 
     * @param string $operation_name Name of the operation
     * @param callable $callback Operation to track
     * @param array $context Additional context
     * @return mixed Operation result
     */
    public static function trackMemory(string $operation_name, callable $callback, array $context = []) {
        $instance = self::getInstance();
        
        if ($instance) {
            return $instance->trackMemoryUsage($operation_name, $callback, $context);
        }
        
        // Fallback without tracking
        return $callback();
    }
}