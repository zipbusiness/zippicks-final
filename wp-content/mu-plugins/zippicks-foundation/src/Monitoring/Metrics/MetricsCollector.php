<?php
/**
 * ZipPicks Metrics Collector
 * 
 * High-performance metrics collection system for enterprise monitoring
 * Collects, aggregates, and stores operational metrics
 *
 * @package ZipPicks\Foundation\Monitoring\Metrics
 */

namespace ZipPicks\Foundation\Monitoring\Metrics;

use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Logging\EnterpriseLogger;
use ZipPicks\Foundation\Cache\CacheManager;

class MetricsCollector
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
     * Cache manager
     *
     * @var CacheManager
     */
    protected CacheManager $cache;

    /**
     * In-memory metrics buffer
     *
     * @var array
     */
    protected array $metricsBuffer = [];

    /**
     * Buffer size limit
     *
     * @var int
     */
    protected int $bufferLimit = 1000;

    /**
     * Metric aggregation windows
     *
     * @var array
     */
    protected array $aggregationWindows = [
        '1m' => 60,
        '5m' => 300,
        '15m' => 900,
        '1h' => 3600,
        '6h' => 21600,
        '24h' => 86400
    ];

    /**
     * Create metrics collector
     *
     * @param Container $container
     * @param EnterpriseLogger $logger
     * @param CacheManager $cache
     */
    public function __construct(Container $container, EnterpriseLogger $logger, CacheManager $cache)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * Record a metric
     *
     * @param string $name
     * @param mixed $value
     * @param array $tags
     * @param int|null $timestamp
     * @return void
     */
    public function record(string $name, $value, array $tags = [], ?int $timestamp = null): void
    {
        $timestamp = $timestamp ?? time();
        
        $metric = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => $timestamp,
            'type' => $this->determineMetricType($value)
        ];

        $this->addToBuffer($metric);
        
        // Auto-flush if buffer is full
        if (count($this->metricsBuffer) >= $this->bufferLimit) {
            $this->flush();
        }
    }

    /**
     * Record a counter metric
     *
     * @param string $name
     * @param int $value
     * @param array $tags
     * @return void
     */
    public function counter(string $name, int $value = 1, array $tags = []): void
    {
        $this->record($name, $value, array_merge($tags, ['type' => 'counter']));
    }

    /**
     * Record a gauge metric
     *
     * @param string $name
     * @param float $value
     * @param array $tags
     * @return void
     */
    public function gauge(string $name, float $value, array $tags = []): void
    {
        $this->record($name, $value, array_merge($tags, ['type' => 'gauge']));
    }

    /**
     * Record a histogram metric
     *
     * @param string $name
     * @param float $value
     * @param array $tags
     * @return void
     */
    public function histogram(string $name, float $value, array $tags = []): void
    {
        $this->record($name, $value, array_merge($tags, ['type' => 'histogram']));
    }

    /**
     * Record a timer metric
     *
     * @param string $name
     * @param float $duration
     * @param array $tags
     * @return void
     */
    public function timer(string $name, float $duration, array $tags = []): void
    {
        $this->histogram("{$name}.duration", $duration, array_merge($tags, ['unit' => 'ms']));
    }

    /**
     * Time a function execution
     *
     * @param string $name
     * @param callable $callback
     * @param array $tags
     * @return mixed
     */
    public function time(string $name, callable $callback, array $tags = [])
    {
        $start = microtime(true);
        
        try {
            $result = $callback();
            $duration = (microtime(true) - $start) * 1000;
            
            $this->timer($name, $duration, array_merge($tags, ['status' => 'success']));
            
            return $result;
            
        } catch (\Exception $e) {
            $duration = (microtime(true) - $start) * 1000;
            
            $this->timer($name, $duration, array_merge($tags, ['status' => 'error']));
            $this->counter("{$name}.errors", 1, $tags);
            
            throw $e;
        }
    }

    /**
     * Record API request metrics
     *
     * @param string $endpoint
     * @param string $method
     * @param int $statusCode
     * @param float $responseTime
     * @param array $additionalTags
     * @return void
     */
    public function recordApiRequest(
        string $endpoint,
        string $method,
        int $statusCode,
        float $responseTime,
        array $additionalTags = []
    ): void {
        $tags = array_merge([
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode,
            'status_class' => $this->getStatusClass($statusCode)
        ], $additionalTags);

        $this->counter('api.requests.total', 1, $tags);
        $this->histogram('api.response_time', $responseTime, $tags);
        
        if ($statusCode >= 400) {
            $this->counter('api.errors.total', 1, $tags);
        }
    }

    /**
     * Record business metrics
     *
     * @param string $metric
     * @param mixed $value
     * @param array $tags
     * @return void
     */
    public function recordBusinessMetric(string $metric, $value, array $tags = []): void
    {
        $businessTags = array_merge(['category' => 'business'], $tags);
        $this->record("business.{$metric}", $value, $businessTags);
    }

    /**
     * Record performance metrics
     *
     * @param array $metrics
     * @return void
     */
    public function recordPerformanceMetrics(array $metrics): void
    {
        foreach ($metrics as $name => $value) {
            $this->gauge("performance.{$name}", $value, ['category' => 'performance']);
        }
    }

    /**
     * Get metrics for timeframe
     *
     * @param string $name
     * @param int $startTime
     * @param int $endTime
     * @param array $tags
     * @return array
     */
    public function getMetrics(string $name, int $startTime, int $endTime, array $tags = []): array
    {
        $cacheKey = $this->buildCacheKey($name, $startTime, $endTime, $tags);
        
        return $this->cache->remember($cacheKey, 300, function() use ($name, $startTime, $endTime, $tags) {
            return $this->fetchMetricsFromStorage($name, $startTime, $endTime, $tags);
        });
    }

    /**
     * Get aggregated metrics
     *
     * @param string $name
     * @param string $aggregation
     * @param string $window
     * @param int $startTime
     * @param int $endTime
     * @param array $tags
     * @return array
     */
    public function getAggregatedMetrics(
        string $name,
        string $aggregation,
        string $window,
        int $startTime,
        int $endTime,
        array $tags = []
    ): array {
        $windowSize = $this->aggregationWindows[$window] ?? 300;
        $intervals = [];
        
        for ($time = $startTime; $time <= $endTime; $time += $windowSize) {
            $intervalEnd = min($time + $windowSize, $endTime);
            $metrics = $this->getMetrics($name, $time, $intervalEnd, $tags);
            
            $intervals[] = [
                'timestamp' => $time,
                'value' => $this->calculateAggregation($metrics, $aggregation)
            ];
        }
        
        return $intervals;
    }

    /**
     * Flush metrics buffer
     *
     * @return void
     */
    public function flush(): void
    {
        if (empty($this->metricsBuffer)) {
            return;
        }

        try {
            $this->storeMetrics($this->metricsBuffer);
            $this->logger->debug('Flushed metrics buffer', [
                'metrics_count' => count($this->metricsBuffer)
            ]);
            
            $this->metricsBuffer = [];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to flush metrics buffer', [
                'error' => $e->getMessage(),
                'metrics_count' => count($this->metricsBuffer)
            ]);
        }
    }

    /**
     * Get current buffer size
     *
     * @return int
     */
    public function getBufferSize(): int
    {
        return count($this->metricsBuffer);
    }

    /**
     * Get system metrics
     *
     * @return array
     */
    public function getSystemMetrics(): array
    {
        return [
            'memory_usage' => $this->getMemoryUsage(),
            'cpu_usage' => $this->getCpuUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'network_io' => $this->getNetworkIO(),
            'load_average' => $this->getLoadAverage()
        ];
    }

    /**
     * Add metric to buffer
     *
     * @param array $metric
     * @return void
     */
    protected function addToBuffer(array $metric): void
    {
        $this->metricsBuffer[] = $metric;
    }

    /**
     * Determine metric type
     *
     * @param mixed $value
     * @return string
     */
    protected function determineMetricType($value): string
    {
        if (is_int($value)) {
            return 'counter';
        } elseif (is_float($value)) {
            return 'gauge';
        } elseif (is_string($value)) {
            return 'label';
        } else {
            return 'unknown';
        }
    }

    /**
     * Get status class for HTTP status code
     *
     * @param int $statusCode
     * @return string
     */
    protected function getStatusClass(int $statusCode): string
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return '2xx';
        } elseif ($statusCode >= 300 && $statusCode < 400) {
            return '3xx';
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            return '4xx';
        } elseif ($statusCode >= 500) {
            return '5xx';
        } else {
            return '1xx';
        }
    }

    /**
     * Build cache key
     *
     * @param string $name
     * @param int $startTime
     * @param int $endTime
     * @param array $tags
     * @return string
     */
    protected function buildCacheKey(string $name, int $startTime, int $endTime, array $tags): string
    {
        $tagsString = empty($tags) ? '' : '_' . md5(serialize($tags));
        return "metrics_{$name}_{$startTime}_{$endTime}{$tagsString}";
    }

    /**
     * Store metrics to persistent storage
     *
     * @param array $metrics
     * @return void
     */
    protected function storeMetrics(array $metrics): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'zippicks_metrics';
        
        // Create table if it doesn't exist
        $this->ensureMetricsTable();

        $values = [];
        foreach ($metrics as $metric) {
            $values[] = $wpdb->prepare(
                "(%s, %f, %s, %d, %s)",
                $metric['name'],
                $metric['value'],
                json_encode($metric['tags']),
                $metric['timestamp'],
                $metric['type']
            );
        }

        if (!empty($values)) {
            $sql = "INSERT INTO {$tableName} (name, value, tags, timestamp, type) VALUES " . implode(', ', $values);
            $wpdb->query($sql);
        }
    }

    /**
     * Fetch metrics from storage
     *
     * @param string $name
     * @param int $startTime
     * @param int $endTime
     * @param array $tags
     * @return array
     */
    protected function fetchMetricsFromStorage(string $name, int $startTime, int $endTime, array $tags): array
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'zippicks_metrics';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$tableName} WHERE name = %s AND timestamp BETWEEN %d AND %d ORDER BY timestamp ASC",
            $name,
            $startTime,
            $endTime
        );

        $results = $wpdb->get_results($sql, ARRAY_A);
        
        // Filter by tags if specified
        if (!empty($tags)) {
            $results = array_filter($results, function($row) use ($tags) {
                $rowTags = json_decode($row['tags'], true) ?? [];
                foreach ($tags as $key => $value) {
                    if (!isset($rowTags[$key]) || $rowTags[$key] !== $value) {
                        return false;
                    }
                }
                return true;
            });
        }

        return array_values($results);
    }

    /**
     * Calculate aggregation for metrics
     *
     * @param array $metrics
     * @param string $aggregation
     * @return float
     */
    protected function calculateAggregation(array $metrics, string $aggregation): float
    {
        if (empty($metrics)) {
            return 0.0;
        }

        $values = array_column($metrics, 'value');

        switch ($aggregation) {
            case 'sum':
                return array_sum($values);
            
            case 'avg':
                return array_sum($values) / count($values);
            
            case 'min':
                return min($values);
            
            case 'max':
                return max($values);
            
            case 'count':
                return count($values);
            
            case 'p95':
                sort($values);
                $index = (int) ceil(0.95 * count($values)) - 1;
                return $values[$index] ?? 0.0;
            
            case 'p99':
                sort($values);
                $index = (int) ceil(0.99 * count($values)) - 1;
                return $values[$index] ?? 0.0;
            
            default:
                return array_sum($values) / count($values);
        }
    }

    /**
     * Ensure metrics table exists
     *
     * @return void
     */
    protected function ensureMetricsTable(): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'zippicks_metrics';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            value DOUBLE NOT NULL,
            tags JSON,
            timestamp INT UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_name_timestamp (name, timestamp),
            INDEX idx_timestamp (timestamp),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $wpdb->query($sql);
    }

    /**
     * Get memory usage
     *
     * @return float
     */
    protected function getMemoryUsage(): float
    {
        return round((memory_get_usage() / memory_get_peak_usage()) * 100, 2);
    }

    /**
     * Get CPU usage (approximation)
     *
     * @return float
     */
    protected function getCpuUsage(): float
    {
        // This is a simplified CPU usage calculation
        // In production, you'd use actual system monitoring tools
        $load = sys_getloadavg();
        return isset($load[0]) ? round($load[0] * 25, 2) : 0.0;
    }

    /**
     * Get disk usage
     *
     * @return float
     */
    protected function getDiskUsage(): float
    {
        $totalBytes = disk_total_space('.');
        $freeBytes = disk_free_space('.');
        
        if ($totalBytes === false || $freeBytes === false) {
            return 0.0;
        }
        
        return round((($totalBytes - $freeBytes) / $totalBytes) * 100, 2);
    }

    /**
     * Get network I/O
     *
     * @return array
     */
    protected function getNetworkIO(): array
    {
        // This would integrate with actual network monitoring in production
        return [
            'bytes_in' => rand(1000000, 10000000),
            'bytes_out' => rand(1000000, 10000000),
            'packets_in' => rand(10000, 100000),
            'packets_out' => rand(10000, 100000)
        ];
    }

    /**
     * Get load average
     *
     * @return array
     */
    protected function getLoadAverage(): array
    {
        $load = sys_getloadavg();
        return [
            '1min' => $load[0] ?? 0.0,
            '5min' => $load[1] ?? 0.0,
            '15min' => $load[2] ?? 0.0
        ];
    }
}