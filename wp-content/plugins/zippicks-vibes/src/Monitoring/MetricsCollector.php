<?php
/**
 * Metrics Collector for ZipPicks Vibes
 * 
 * Enhanced metrics collection with persistent tracking, system/product metric separation,
 * and comprehensive tracking methods with admin reporting hooks.
 * 
 * @package ZipPicksVibes
 * @subpackage Monitoring
 * @since 2.0.0
 * @version 2.1.0
 */

namespace ZipPicksVibes\Monitoring;

use ZipPicksVibes\Cache\CacheInterface;
use ZipPicksVibes\HealthCheck\HealthCheckManager;
use ZipPicksVibes\Audit\AuditRepository;

/**
 * MetricsCollector Class
 * 
 * Enhanced metrics aggregation with persistent storage and smart categorization
 */
class MetricsCollector {
    
    /**
     * Metric categories
     */
    const CATEGORY_SYSTEM = 'system';
    const CATEGORY_PRODUCT = 'product';
    const CATEGORY_BUSINESS = 'business';
    const CATEGORY_USER = 'user';
    
    /**
     * Time range constants
     */
    const RANGE_HOUR = '1h';
    const RANGE_DAY = '24h';
    const RANGE_WEEK = '7d';
    const RANGE_MONTH = '30d';
    
    /**
     * System metrics
     */
    const SYSTEM_METRICS = [
        'memory_usage',
        'cpu_usage',
        'database_queries',
        'cache_hits',
        'cache_misses',
        'api_response_time',
        'page_load_time',
        'error_rate',
        'uptime'
    ];
    
    /**
     * Product metrics (vibe-specific)
     */
    const PRODUCT_METRICS = [
        'vibe_searches',
        'vibe_views',
        'waitlist_signups',
        'popular_vibes',
        'search_results_clicked',
        'user_sessions',
        'bounce_rate',
        'conversion_rate'
    ];
    
    /**
     * Performance tracker
     * 
     * @var PerformanceTracker
     */
    private PerformanceTracker $performanceTracker;
    
    /**
     * Health check manager
     * 
     * @var HealthCheckManager|null
     */
    private ?HealthCheckManager $healthCheckManager;
    
    /**
     * Audit repository
     * 
     * @var AuditRepository|null
     */
    private ?AuditRepository $auditRepository;
    
    /**
     * Cache instance
     * 
     * @var CacheInterface|null
     */
    private ?CacheInterface $cache;
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger;
    
    /**
     * Persistent metrics storage table
     * 
     * @var string
     */
    private string $metricsTable;
    
    /**
     * Metric buffer for batch operations
     * 
     * @var array
     */
    private array $metricBuffer = [];
    
    /**
     * Buffer size for batch operations
     * 
     * @var int
     */
    private int $bufferSize = 50;
    
    /**
     * Constructor
     * 
     * @param PerformanceTracker $performanceTracker
     * @param HealthCheckManager|null $healthCheckManager
     * @param AuditRepository|null $auditRepository
     * @param CacheInterface|null $cache
     * @param mixed $logger
     */
    public function __construct(
        PerformanceTracker $performanceTracker,
        ?HealthCheckManager $healthCheckManager = null,
        ?AuditRepository $auditRepository = null,
        ?CacheInterface $cache = null,
        $logger = null
    ) {
        global $wpdb;
        
        $this->performanceTracker = $performanceTracker;
        $this->healthCheckManager = $healthCheckManager;
        $this->auditRepository = $auditRepository;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->metricsTable = $wpdb->prefix . 'zippicks_metrics';
        
        // Register shutdown function to flush buffer
        register_shutdown_function([$this, 'flushMetricBuffer']);
    }
    
    /**
     * Get all metrics
     * 
     * @param array $options Collection options
     * @return array
     */
    public function collect(array $options = []): array {
        $cacheKey = 'metrics_' . md5(serialize($options));
        
        // Check cache
        if ($this->cache && !($options['force'] ?? false)) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $metrics = [
            'timestamp' => current_time('timestamp'),
            'system' => $this->collectSystemMetrics(),
            'performance' => $this->collectPerformanceMetrics($options),
            'health' => $this->collectHealthMetrics(),
            'audit' => $this->collectAuditMetrics($options),
            'vibes' => $this->collectVibesMetrics(),
            'cache' => $this->collectCacheMetrics(),
            'database' => $this->collectDatabaseMetrics()
        ];
        
        // Cache results
        if ($this->cache) {
            $this->cache->set($cacheKey, $metrics, 60); // 1 minute cache
        }
        
        return $metrics;
    }
    
    /**
     * Collect system metrics
     * 
     * @return array
     */
    private function collectSystemMetrics(): array {
        return [
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => defined('ZIPPICKS_VIBES_VERSION') ? ZIPPICKS_VIBES_VERSION : 'unknown',
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => $this->getMemoryLimit(),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'server_load' => $this->getServerLoad(),
            'active_users' => $this->getActiveUsers()
        ];
    }
    
    /**
     * Collect performance metrics
     * 
     * @param array $options Options
     * @return array
     */
    private function collectPerformanceMetrics(array $options): array {
        $minutes = $options['performance_window'] ?? 60;
        $summary = $this->performanceTracker->getSummary('', $minutes);
        
        // Calculate aggregates
        $totals = [
            'total_requests' => 0,
            'avg_response_time' => 0,
            'slow_queries' => 0,
            'cache_hit_rate' => 0
        ];
        
        // Process API metrics
        if (!empty($summary[PerformanceTracker::TYPE_API])) {
            foreach ($summary[PerformanceTracker::TYPE_API] as $metric) {
                $totals['total_requests'] += $metric['count'];
                $totals['avg_response_time'] += $metric['avg'] * $metric['count'];
            }
            if ($totals['total_requests'] > 0) {
                $totals['avg_response_time'] /= $totals['total_requests'];
            }
        }
        
        // Count slow queries
        $slowQueries = $this->performanceTracker->getSlowQueries(1000, 1000);
        $totals['slow_queries'] = count($slowQueries);
        
        // Calculate cache hit rate
        if (!empty($summary[PerformanceTracker::TYPE_CACHE])) {
            $hits = 0;
            $total = 0;
            foreach ($summary[PerformanceTracker::TYPE_CACHE] as $name => $metric) {
                if (strpos($name, 'cache_get') !== false) {
                    $total += $metric['count'];
                    if (strpos($name, '_hit') !== false) {
                        $hits += $metric['count'];
                    }
                }
            }
            if ($total > 0) {
                $totals['cache_hit_rate'] = ($hits / $total) * 100;
            }
        }
        
        return [
            'summary' => $summary,
            'totals' => $totals,
            'slow_queries' => array_slice($slowQueries, 0, 10) // Top 10 slow queries
        ];
    }
    
    /**
     * Collect health metrics
     * 
     * @return array
     */
    private function collectHealthMetrics(): array {
        if (!$this->healthCheckManager) {
            return ['status' => 'unknown', 'checks' => []];
        }
        
        $results = $this->healthCheckManager->runAll();
        
        // Determine overall status
        $overallStatus = 'healthy';
        $statusCounts = ['healthy' => 0, 'warning' => 0, 'critical' => 0];
        
        foreach ($results as $result) {
            $statusCounts[$result->getStatus()]++;
            
            if ($result->getStatus() === 'critical') {
                $overallStatus = 'critical';
            } elseif ($result->getStatus() === 'warning' && $overallStatus !== 'critical') {
                $overallStatus = 'warning';
            }
        }
        
        return [
            'status' => $overallStatus,
            'counts' => $statusCounts,
            'checks' => array_map(function($result) {
                return [
                    'name' => $result->getName(),
                    'status' => $result->getStatus(),
                    'message' => $result->getMessage(),
                    'details' => $result->getDetails()
                ];
            }, $results)
        ];
    }
    
    /**
     * Collect audit metrics
     * 
     * @param array $options Options
     * @return array
     */
    private function collectAuditMetrics(array $options): array {
        if (!$this->auditRepository) {
            return ['total_events' => 0, 'by_type' => [], 'recent_security' => []];
        }
        
        $hours = $options['audit_window_hours'] ?? 24;
        $dateFrom = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $stats = $this->auditRepository->getStatistics(['date_from' => $dateFrom]);
        
        // Get recent security events
        $securityEvents = $this->auditRepository->find(
            [
                'event_category' => 'security',
                'date_from' => $dateFrom
            ],
            10,
            0,
            'created_at',
            'DESC'
        );
        
        return [
            'total_events' => $stats['total_events'],
            'by_type' => $stats['by_type'],
            'by_category' => $stats['by_category'],
            'by_severity' => $stats['by_severity'],
            'unique_users' => $stats['unique_users'],
            'unique_ips' => $stats['unique_ips'],
            'recent_security' => array_map(function($event) {
                return [
                    'id' => $event->getId(),
                    'action' => $event->getEventAction(),
                    'severity' => $event->getSeverity(),
                    'ip' => $event->getIpAddress(),
                    'created_at' => $event->getCreatedAt()
                ];
            }, $securityEvents)
        ];
    }
    
    /**
     * Collect vibes metrics
     * 
     * @return array
     */
    private function collectVibesMetrics(): array {
        global $wpdb;
        
        $vibesTable = $wpdb->prefix . 'zippicks_vibes';
        $waitlistTable = $wpdb->prefix . 'zippicks_waitlist';
        
        return [
            'total_vibes' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$vibesTable}"),
            'active_vibes' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$vibesTable} WHERE is_active = 1"),
            'waitlist_entries' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$waitlistTable}"),
            'waitlist_today' => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$waitlistTable} WHERE created_at >= %s",
                    date('Y-m-d 00:00:00')
                )
            ),
            'popular_vibes' => $wpdb->get_results(
                "SELECT v.name, v.slug, COUNT(w.id) as waitlist_count
                FROM {$vibesTable} v
                LEFT JOIN {$waitlistTable} w ON v.id = w.vibe_id
                GROUP BY v.id
                ORDER BY waitlist_count DESC
                LIMIT 5",
                ARRAY_A
            )
        ];
    }
    
    /**
     * Collect cache metrics
     * 
     * @return array
     */
    private function collectCacheMetrics(): array {
        $metrics = [
            'type' => 'unknown',
            'available' => false,
            'stats' => []
        ];
        
        // Check object cache
        if (wp_using_ext_object_cache()) {
            $metrics['type'] = 'external';
            $metrics['available'] = true;
            
            // Try to get cache stats
            global $wp_object_cache;
            if (is_object($wp_object_cache)) {
                if (method_exists($wp_object_cache, 'stats')) {
                    $metrics['stats'] = $wp_object_cache->stats();
                } elseif (method_exists($wp_object_cache, 'get_stats')) {
                    $metrics['stats'] = $wp_object_cache->get_stats();
                }
            }
        } else {
            $metrics['type'] = 'transient';
            $metrics['available'] = true;
            
            // Count transients
            $transientCount = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_%' 
                OR option_name LIKE '_site_transient_%'"
            );
            
            $metrics['stats'] = [
                'transient_count' => (int) $transientCount
            ];
        }
        
        return $metrics;
    }
    
    /**
     * Collect database metrics
     * 
     * @return array
     */
    private function collectDatabaseMetrics(): array {
        global $wpdb;
        
        $metrics = [
            'queries_count' => $wpdb->num_queries,
            'table_sizes' => []
        ];
        
        // Get ZipPicks table sizes
        $tables = [
            'zippicks_vibes',
            'zippicks_vibe_categories',
            'zippicks_vibe_category_assignments',
            'zippicks_waitlist',
            'zippicks_scrape_log',
            'zippicks_security_log',
            'zippicks_rate_limit_log',
            'zippicks_security_events',
            'zippicks_audit_log',
            'zippicks_performance_metrics'
        ];
        
        foreach ($tables as $table) {
            $fullTableName = $wpdb->prefix . $table;
            $result = $wpdb->get_row(
                "SELECT 
                    COUNT(*) as row_count,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE() 
                AND table_name = '{$fullTableName}'",
                ARRAY_A
            );
            
            if ($result) {
                $metrics['table_sizes'][$table] = [
                    'rows' => (int) $result['row_count'],
                    'size_mb' => (float) $result['size_mb']
                ];
            }
        }
        
        return $metrics;
    }
    
    /**
     * Get memory limit in bytes
     * 
     * @return int
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
     * Get server load average
     * 
     * @return array|null
     */
    private function getServerLoad(): ?array {
        if (!function_exists('sys_getloadavg')) {
            return null;
        }
        
        $load = sys_getloadavg();
        if ($load === false) {
            return null;
        }
        
        return [
            '1min' => round($load[0], 2),
            '5min' => round($load[1], 2),
            '15min' => round($load[2], 2)
        ];
    }
    
    /**
     * Get active users count
     * 
     * @return int
     */
    private function getActiveUsers(): int {
        // Count users active in last 15 minutes
        $transient_key = 'zippicks_active_users';
        $active_users = get_transient($transient_key);
        
        if ($active_users === false) {
            global $wpdb;
            
            $active_users = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT user_id) 
                    FROM {$wpdb->usermeta} 
                    WHERE meta_key = 'zippicks_last_activity' 
                    AND meta_value > %s",
                    date('Y-m-d H:i:s', strtotime('-15 minutes'))
                )
            );
            
            set_transient($transient_key, $active_users, 60);
        }
        
        return (int) $active_users;
    }
    
    /**
     * Track a metric with persistent storage
     * 
     * @param string $metricName Name of the metric
     * @param mixed $value Metric value
     * @param string $category Metric category
     * @param array $tags Additional tags/context
     * @return bool Success status
     */
    public function track(string $metricName, $value, string $category = self::CATEGORY_SYSTEM, array $tags = []): bool {
        try {
            $metric = [
                'metric_name' => $metricName,
                'metric_value' => is_numeric($value) ? (float) $value : 1,
                'metric_category' => $category,
                'tags' => json_encode($tags),
                'user_id' => get_current_user_id() ?: null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'created_at' => current_time('mysql'),
                'timestamp' => time()
            ];
            
            // Add to buffer for batch processing
            $this->metricBuffer[] = $metric;
            
            // Flush buffer if it's full
            if (count($this->metricBuffer) >= $this->bufferSize) {
                $this->flushMetricBuffer();
            }
            
            // Cache recent metrics for fast access
            $this->cacheRecentMetric($metricName, $value, $category);
            
            return true;
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to track metric', [
                    'metric' => $metricName,
                    'value' => $value,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Get metric data for specified range
     * 
     * @param string $metricName Name of the metric
     * @param string $range Time range (1h, 24h, 7d, 30d)
     * @param string $category Optional category filter
     * @return array Metric data
     */
    public function get(string $metricName, string $range = self::RANGE_DAY, string $category = ''): array {
        $cacheKey = "metric_{$metricName}_{$range}_{$category}";
        
        // Check cache first
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        global $wpdb;
        
        // Calculate time range
        $timespan = $this->getTimespan($range);
        $cutoff = date('Y-m-d H:i:s', time() - $timespan);
        
        // Build query
        $query = "SELECT 
                    metric_value,
                    tags,
                    created_at,
                    timestamp
                  FROM {$this->metricsTable} 
                  WHERE metric_name = %s 
                  AND created_at >= %s";
        
        $params = [$metricName, $cutoff];
        
        if (!empty($category)) {
            $query .= " AND metric_category = %s";
            $params[] = $category;
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $results = $wpdb->get_results(
            $wpdb->prepare($query, $params),
            ARRAY_A
        );
        
        // Process results
        $metrics = [
            'metric_name' => $metricName,
            'range' => $range,
            'category' => $category,
            'count' => count($results),
            'values' => [],
            'statistics' => []
        ];
        
        if (!empty($results)) {
            $values = array_column($results, 'metric_value');
            
            $metrics['values'] = $results;
            $metrics['statistics'] = [
                'total' => array_sum($values),
                'average' => array_sum($values) / count($values),
                'min' => min($values),
                'max' => max($values),
                'median' => $this->calculateMedian($values),
                'std_dev' => $this->calculateStandardDeviation($values)
            ];
        }
        
        // Cache results
        if ($this->cache) {
            $this->cache->set($cacheKey, $metrics, 300); // 5 minutes
        }
        
        return $metrics;
    }
    
    /**
     * Get system metrics summary
     * 
     * @param string $range Time range
     * @return array System metrics
     */
    public function getSystemMetrics(string $range = self::RANGE_DAY): array {
        $systemMetrics = [];
        
        foreach (self::SYSTEM_METRICS as $metric) {
            $data = $this->get($metric, $range, self::CATEGORY_SYSTEM);
            if (!empty($data['values'])) {
                $systemMetrics[$metric] = $data['statistics'];
            }
        }
        
        return [
            'category' => self::CATEGORY_SYSTEM,
            'range' => $range,
            'metrics' => $systemMetrics,
            'summary' => $this->generateSystemSummary($systemMetrics)
        ];
    }
    
    /**
     * Get product metrics summary
     * 
     * @param string $range Time range
     * @return array Product metrics
     */
    public function getProductMetrics(string $range = self::RANGE_DAY): array {
        $productMetrics = [];
        
        foreach (self::PRODUCT_METRICS as $metric) {
            $data = $this->get($metric, $range, self::CATEGORY_PRODUCT);
            if (!empty($data['values'])) {
                $productMetrics[$metric] = $data['statistics'];
            }
        }
        
        return [
            'category' => self::CATEGORY_PRODUCT,
            'range' => $range,
            'metrics' => $productMetrics,
            'summary' => $this->generateProductSummary($productMetrics)
        ];
    }
    
    /**
     * Flush metric buffer to database
     * 
     * @return bool Success status
     */
    public function flushMetricBuffer(): bool {
        if (empty($this->metricBuffer)) {
            return true;
        }
        
        try {
            global $wpdb;
            
            // Batch insert
            $values = [];
            $placeholders = [];
            
            foreach ($this->metricBuffer as $metric) {
                $values = array_merge($values, [
                    $metric['metric_name'],
                    $metric['metric_value'],
                    $metric['metric_category'],
                    $metric['tags'],
                    $metric['user_id'],
                    $metric['ip_address'],
                    $metric['created_at'],
                    $metric['timestamp']
                ]);
                
                $placeholders[] = '(%s, %f, %s, %s, %d, %s, %s, %d)';
            }
            
            if (!empty($placeholders)) {
                $query = "INSERT INTO {$this->metricsTable} 
                         (metric_name, metric_value, metric_category, tags, user_id, ip_address, created_at, timestamp) 
                         VALUES " . implode(', ', $placeholders);
                
                $result = $wpdb->query($wpdb->prepare($query, $values));
                
                if ($result !== false) {
                    $this->metricBuffer = [];
                    return true;
                }
            }
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to flush metric buffer', [
                    'buffer_size' => count($this->metricBuffer),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return false;
    }
    
    /**
     * Cache recent metric for fast access
     * 
     * @param string $metricName Metric name
     * @param mixed $value Metric value
     * @param string $category Metric category
     */
    private function cacheRecentMetric(string $metricName, $value, string $category): void {
        if (!$this->cache) {
            return;
        }
        
        $cacheKey = "recent_metric_{$metricName}";
        $recentMetrics = $this->cache->get($cacheKey) ?: [];
        
        // Add new metric
        $recentMetrics[] = [
            'value' => $value,
            'category' => $category,
            'timestamp' => time()
        ];
        
        // Keep only last 100 entries
        if (count($recentMetrics) > 100) {
            $recentMetrics = array_slice($recentMetrics, -100);
        }
        
        $this->cache->set($cacheKey, $recentMetrics, 3600); // 1 hour
    }
    
    /**
     * Get timespan in seconds for range
     * 
     * @param string $range Range string
     * @return int Timespan in seconds
     */
    private function getTimespan(string $range): int {
        switch ($range) {
            case self::RANGE_HOUR:
                return 3600;
            case self::RANGE_DAY:
                return 86400;
            case self::RANGE_WEEK:
                return 604800;
            case self::RANGE_MONTH:
                return 2592000;
            default:
                return 86400; // Default to 24 hours
        }
    }
    
    /**
     * Calculate median value
     * 
     * @param array $values Array of numeric values
     * @return float Median value
     */
    private function calculateMedian(array $values): float {
        sort($values);
        $count = count($values);
        
        if ($count === 0) {
            return 0;
        }
        
        $middle = floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }
        
        return $values[$middle];
    }
    
    /**
     * Calculate standard deviation
     * 
     * @param array $values Array of numeric values
     * @return float Standard deviation
     */
    private function calculateStandardDeviation(array $values): float {
        $count = count($values);
        
        if ($count === 0) {
            return 0;
        }
        
        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / $count;
        
        return sqrt($variance);
    }
    
    /**
     * Generate system metrics summary
     * 
     * @param array $metrics System metrics data
     * @return array Summary
     */
    private function generateSystemSummary(array $metrics): array {
        return [
            'health_score' => $this->calculateHealthScore($metrics),
            'performance_grade' => $this->calculatePerformanceGrade($metrics),
            'alerts' => $this->generateAlerts($metrics),
            'recommendations' => $this->generateRecommendations($metrics)
        ];
    }
    
    /**
     * Generate product metrics summary
     * 
     * @param array $metrics Product metrics data
     * @return array Summary
     */
    private function generateProductSummary(array $metrics): array {
        return [
            'engagement_score' => $this->calculateEngagementScore($metrics),
            'growth_rate' => $this->calculateGrowthRate($metrics),
            'conversion_metrics' => $this->calculateConversionMetrics($metrics),
            'trending_vibes' => $this->getTrendingVibes($metrics)
        ];
    }
    
    /**
     * Calculate health score from system metrics
     * 
     * @param array $metrics System metrics
     * @return int Health score (0-100)
     */
    private function calculateHealthScore(array $metrics): int {
        $score = 100;
        
        // Deduct points for high error rates
        if (isset($metrics['error_rate']['average']) && $metrics['error_rate']['average'] > 0.01) {
            $score -= min(30, $metrics['error_rate']['average'] * 3000);
        }
        
        // Deduct points for slow response times
        if (isset($metrics['api_response_time']['average']) && $metrics['api_response_time']['average'] > 1000) {
            $score -= min(20, ($metrics['api_response_time']['average'] - 1000) / 100);
        }
        
        // Deduct points for high memory usage
        if (isset($metrics['memory_usage']['average']) && $metrics['memory_usage']['average'] > 80) {
            $score -= min(25, $metrics['memory_usage']['average'] - 80);
        }
        
        return max(0, (int) $score);
    }
    
    /**
     * Calculate performance grade
     * 
     * @param array $metrics System metrics
     * @return string Performance grade (A-F)
     */
    private function calculatePerformanceGrade(array $metrics): string {
        $healthScore = $this->calculateHealthScore($metrics);
        
        if ($healthScore >= 90) return 'A';
        if ($healthScore >= 80) return 'B';
        if ($healthScore >= 70) return 'C';
        if ($healthScore >= 60) return 'D';
        return 'F';
    }
    
    /**
     * Generate alerts from metrics
     * 
     * @param array $metrics System metrics
     * @return array Alerts
     */
    private function generateAlerts(array $metrics): array {
        $alerts = [];
        
        // Check for high error rate
        if (isset($metrics['error_rate']['average']) && $metrics['error_rate']['average'] > 0.05) {
            $alerts[] = [
                'type' => 'error',
                'message' => 'High error rate detected',
                'value' => $metrics['error_rate']['average'],
                'threshold' => 0.05
            ];
        }
        
        // Check for high memory usage
        if (isset($metrics['memory_usage']['average']) && $metrics['memory_usage']['average'] > 85) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'High memory usage detected',
                'value' => $metrics['memory_usage']['average'],
                'threshold' => 85
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Generate recommendations from metrics
     * 
     * @param array $metrics System metrics
     * @return array Recommendations
     */
    private function generateRecommendations(array $metrics): array {
        $recommendations = [];
        
        if (isset($metrics['cache_hits']['average']) && $metrics['cache_hits']['average'] < 80) {
            $recommendations[] = 'Consider optimizing cache strategy to improve hit rate';
        }
        
        if (isset($metrics['database_queries']['average']) && $metrics['database_queries']['average'] > 100) {
            $recommendations[] = 'Review database queries for optimization opportunities';
        }
        
        return $recommendations;
    }
    
    /**
     * Calculate engagement score from product metrics
     * 
     * @param array $metrics Product metrics
     * @return int Engagement score (0-100)
     */
    private function calculateEngagementScore(array $metrics): int {
        $score = 0;
        
        // Factor in vibe searches
        if (isset($metrics['vibe_searches']['total'])) {
            $score += min(30, $metrics['vibe_searches']['total'] / 10);
        }
        
        // Factor in user sessions
        if (isset($metrics['user_sessions']['total'])) {
            $score += min(25, $metrics['user_sessions']['total'] / 5);
        }
        
        // Factor in conversion rate
        if (isset($metrics['conversion_rate']['average'])) {
            $score += min(25, $metrics['conversion_rate']['average'] * 250);
        }
        
        // Factor in bounce rate (inverse)
        if (isset($metrics['bounce_rate']['average'])) {
            $score += min(20, (100 - $metrics['bounce_rate']['average']) / 5);
        }
        
        return min(100, (int) $score);
    }
    
    /**
     * Calculate growth rate from metrics
     * 
     * @param array $metrics Product metrics
     * @return float Growth rate percentage
     */
    private function calculateGrowthRate(array $metrics): float {
        // Simplified growth calculation based on waitlist signups
        if (isset($metrics['waitlist_signups']['total'])) {
            return max(0, $metrics['waitlist_signups']['total'] * 0.1);
        }
        
        return 0.0;
    }
    
    /**
     * Calculate conversion metrics
     * 
     * @param array $metrics Product metrics
     * @return array Conversion data
     */
    private function calculateConversionMetrics(array $metrics): array {
        return [
            'search_to_view' => isset($metrics['vibe_views']['total'], $metrics['vibe_searches']['total']) && $metrics['vibe_searches']['total'] > 0
                ? ($metrics['vibe_views']['total'] / $metrics['vibe_searches']['total']) * 100 : 0,
            'view_to_signup' => isset($metrics['waitlist_signups']['total'], $metrics['vibe_views']['total']) && $metrics['vibe_views']['total'] > 0
                ? ($metrics['waitlist_signups']['total'] / $metrics['vibe_views']['total']) * 100 : 0
        ];
    }
    
    /**
     * Get trending vibes from metrics
     * 
     * @param array $metrics Product metrics
     * @return array Trending vibes data
     */
    private function getTrendingVibes(array $metrics): array {
        // This would require additional vibe-specific tracking
        // For now, return placeholder data
        return [
            'top_searched' => [],
            'fastest_growing' => [],
            'most_converted' => []
        ];
    }
}