<?php
/**
 * Prometheus Metrics Exporter
 * 
 * Enterprise-grade metrics export for the $100B ZipPicks platform
 * 
 * @package ZipPicks\Foundation\Observability\Metrics
 * @since 2.0.0
 */

namespace ZipPicks\Foundation\Observability\Metrics;

use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Core\EnvironmentManager;

class PrometheusExporter
{
    /**
     * @var MetricsCollector
     */
    protected MetricsCollector $collector;
    
    /**
     * @var EnvironmentManager
     */
    protected EnvironmentManager $env;
    
    /**
     * @var string Namespace for all metrics
     */
    protected string $namespace;
    
    /**
     * @var array Default labels for all metrics
     */
    protected array $defaultLabels = [];
    
    /**
     * @var bool Whether to include help text
     */
    protected bool $includeHelp = true;
    
    /**
     * @var array Metric buckets for histograms
     */
    protected array $defaultBuckets = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $container = Foundation::getInstance()->getContainer();
        $this->env = $container->get('env');
        
        $this->namespace = $this->env->get('monitoring.prometheus.namespace', 'zippicks');
        $this->defaultBuckets = $this->env->get('monitoring.prometheus.buckets', $this->defaultBuckets);
        
        $this->collector = new MetricsCollector();
        
        // Set default labels
        $this->defaultLabels = [
            'environment' => $this->env->getEnvironment(),
            'version' => ZIPPICKS_FOUNDATION_VERSION ?? '2.0.0',
            'instance' => gethostname() ?: 'unknown'
        ];
    }
    
    /**
     * Export all metrics in Prometheus format
     * 
     * @return string
     */
    public function export(): string
    {
        $output = [];
        
        // Add header
        $output[] = $this->generateHeader();
        
        // Collect all metrics
        $metrics = $this->collector->collect();
        
        // Export each metric
        foreach ($metrics as $metric) {
            $output[] = $this->exportMetric($metric);
        }
        
        // Add custom metrics
        $output[] = $this->exportSystemMetrics();
        $output[] = $this->exportBusinessMetrics();
        $output[] = $this->exportApiMetrics();
        $output[] = $this->exportDatabaseMetrics();
        $output[] = $this->exportCacheMetrics();
        $output[] = $this->exportQueueMetrics();
        
        return implode("\n", array_filter($output));
    }
    
    /**
     * Generate header
     * 
     * @return string
     */
    protected function generateHeader(): string
    {
        $timestamp = time();
        return "# ZipPicks Foundation Metrics Export\n" .
               "# Generated at: " . date('Y-m-d H:i:s', $timestamp) . "\n" .
               "# Environment: " . $this->env->getEnvironment() . "\n";
    }
    
    /**
     * Export a single metric
     * 
     * @param array $metric
     * @return string
     */
    protected function exportMetric(array $metric): string
    {
        $lines = [];
        
        $name = $this->formatMetricName($metric['name']);
        
        // Add help text
        if ($this->includeHelp && !empty($metric['help'])) {
            $lines[] = "# HELP {$name} {$metric['help']}";
        }
        
        // Add type
        if (!empty($metric['type'])) {
            $lines[] = "# TYPE {$name} {$metric['type']}";
        }
        
        // Add metric values
        if ($metric['type'] === 'histogram') {
            $lines[] = $this->exportHistogram($name, $metric);
        } else {
            foreach ($metric['samples'] as $sample) {
                $labels = $this->formatLabels(array_merge($this->defaultLabels, $sample['labels'] ?? []));
                $lines[] = "{$name}{$labels} {$sample['value']}";
            }
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Export histogram metric
     * 
     * @param string $name
     * @param array $metric
     * @return string
     */
    protected function exportHistogram(string $name, array $metric): string
    {
        $lines = [];
        
        foreach ($metric['samples'] as $sample) {
            $labels = array_merge($this->defaultLabels, $sample['labels'] ?? []);
            $baseLabels = $this->formatLabels($labels);
            
            // Export buckets
            foreach ($sample['buckets'] as $bucket => $count) {
                $bucketLabels = $this->formatLabels(array_merge($labels, ['le' => $bucket]));
                $lines[] = "{$name}_bucket{$bucketLabels} {$count}";
            }
            
            // Export sum and count
            $lines[] = "{$name}_sum{$baseLabels} {$sample['sum']}";
            $lines[] = "{$name}_count{$baseLabels} {$sample['count']}";
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Export system metrics
     * 
     * @return string
     */
    protected function exportSystemMetrics(): string
    {
        $metrics = [];
        
        // PHP metrics
        $metrics[] = $this->createMetric('php_info', 'gauge', 'PHP version info', [
            ['value' => 1, 'labels' => ['version' => PHP_VERSION, 'sapi' => PHP_SAPI]]
        ]);
        
        // Memory metrics
        $metrics[] = $this->createMetric('php_memory_usage_bytes', 'gauge', 'Current memory usage', [
            ['value' => memory_get_usage(true)]
        ]);
        
        $metrics[] = $this->createMetric('php_memory_peak_bytes', 'gauge', 'Peak memory usage', [
            ['value' => memory_get_peak_usage(true)]
        ]);
        
        $limit = $this->parseBytes(ini_get('memory_limit'));
        $metrics[] = $this->createMetric('php_memory_limit_bytes', 'gauge', 'Memory limit', [
            ['value' => $limit]
        ]);
        
        // Request metrics
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            $duration = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            $metrics[] = $this->createMetric('http_request_duration_seconds', 'histogram', 'Request duration', 
                $this->createHistogramSample($duration)
            );
        }
        
        // WordPress metrics
        $metrics[] = $this->createMetric('wordpress_version', 'gauge', 'WordPress version', [
            ['value' => 1, 'labels' => ['version' => get_bloginfo('version')]]
        ]);
        
        $metrics[] = $this->createMetric('wordpress_plugins_active', 'gauge', 'Number of active plugins', [
            ['value' => count(get_option('active_plugins', []))]
        ]);
        
        return $this->formatMetrics($metrics);
    }
    
    /**
     * Export business metrics
     * 
     * @return string
     */
    protected function exportBusinessMetrics(): string
    {
        global $wpdb;
        $metrics = [];
        
        // User metrics
        $userCounts = $wpdb->get_results("
            SELECT meta_value as role, COUNT(*) as count 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '{$wpdb->prefix}capabilities' 
            GROUP BY meta_value
        ");
        
        $totalUsers = 0;
        foreach ($userCounts as $userCount) {
            $role = $this->extractRole($userCount->role);
            $count = (int) $userCount->count;
            $totalUsers += $count;
            
            $metrics[] = $this->createMetric('users_total', 'gauge', 'Total users by role', [
                ['value' => $count, 'labels' => ['role' => $role]]
            ]);
        }
        
        $metrics[] = $this->createMetric('users_total', 'gauge', 'Total users', [
            ['value' => $totalUsers, 'labels' => ['role' => 'all']]
        ]);
        
        // Content metrics
        $postTypes = ['post', 'page', 'zippicks_business', 'zippicks_review'];
        foreach ($postTypes as $postType) {
            $count = wp_count_posts($postType);
            $metrics[] = $this->createMetric('content_total', 'gauge', 'Total content by type and status', [
                ['value' => $count->publish ?? 0, 'labels' => ['type' => $postType, 'status' => 'publish']],
                ['value' => $count->draft ?? 0, 'labels' => ['type' => $postType, 'status' => 'draft']],
                ['value' => $count->trash ?? 0, 'labels' => ['type' => $postType, 'status' => 'trash']]
            ]);
        }
        
        // API key metrics
        if ($this->tableExists('zippicks_api_keys')) {
            $apiKeyStats = $wpdb->get_results("
                SELECT tier, COUNT(*) as count 
                FROM {$wpdb->prefix}zippicks_api_keys 
                WHERE expires_at IS NULL OR expires_at > NOW()
                GROUP BY tier
            ");
            
            foreach ($apiKeyStats as $stat) {
                $metrics[] = $this->createMetric('api_keys_active', 'gauge', 'Active API keys by tier', [
                    ['value' => (int) $stat->count, 'labels' => ['tier' => $stat->tier]]
                ]);
            }
        }
        
        // Revenue metrics (placeholder - integrate with actual revenue data)
        $metrics[] = $this->createMetric('revenue_mrr_dollars', 'gauge', 'Monthly recurring revenue', [
            ['value' => $this->calculateMRR()]
        ]);
        
        return $this->formatMetrics($metrics);
    }
    
    /**
     * Export API metrics
     * 
     * @return string
     */
    protected function exportApiMetrics(): string
    {
        global $wpdb;
        $metrics = [];
        
        // API request metrics
        if ($this->tableExists('zippicks_api_key_usage')) {
            $apiStats = $wpdb->get_results("
                SELECT 
                    endpoint,
                    SUM(requests) as total_requests,
                    SUM(errors) as total_errors,
                    AVG(latency_sum / NULLIF(requests, 0)) as avg_latency
                FROM {$wpdb->prefix}zippicks_api_key_usage
                WHERE date = CURDATE()
                GROUP BY endpoint
            ");
            
            foreach ($apiStats as $stat) {
                $endpoint = $this->normalizeEndpoint($stat->endpoint);
                
                $metrics[] = $this->createMetric('api_requests_total', 'counter', 'Total API requests', [
                    ['value' => (int) $stat->total_requests, 'labels' => ['endpoint' => $endpoint]]
                ]);
                
                $metrics[] = $this->createMetric('api_errors_total', 'counter', 'Total API errors', [
                    ['value' => (int) $stat->total_errors, 'labels' => ['endpoint' => $endpoint]]
                ]);
                
                if ($stat->avg_latency) {
                    $metrics[] = $this->createMetric('api_request_duration_seconds', 'gauge', 'Average API request duration', [
                        ['value' => $stat->avg_latency / 1000, 'labels' => ['endpoint' => $endpoint]]
                    ]);
                }
            }
        }
        
        // Rate limit metrics
        if ($container = Foundation::getInstance()->getContainer()) {
            if ($container->has('rate_limiter')) {
                $rateLimiter = $container->get('rate_limiter');
                
                // This would need actual implementation in the rate limiter
                if (method_exists($rateLimiter, 'getMetrics')) {
                    $rateLimitMetrics = $rateLimiter->getMetrics();
                    
                    foreach ($rateLimitMetrics as $metric) {
                        $metrics[] = $this->createMetric('rate_limit_hits_total', 'counter', 'Rate limit hits', [
                            ['value' => $metric['hits'], 'labels' => ['key' => $metric['key']]]
                        ]);
                    }
                }
            }
        }
        
        return $this->formatMetrics($metrics);
    }
    
    /**
     * Export database metrics
     * 
     * @return string
     */
    protected function exportDatabaseMetrics(): string
    {
        global $wpdb;
        $metrics = [];
        
        // Query metrics
        $metrics[] = $this->createMetric('database_queries_total', 'counter', 'Total database queries', [
            ['value' => $wpdb->num_queries]
        ]);
        
        // Connection metrics
        if ($wpdb->dbh) {
            $threadId = mysqli_thread_id($wpdb->dbh);
            $metrics[] = $this->createMetric('database_connection_id', 'gauge', 'Current connection ID', [
                ['value' => $threadId]
            ]);
            
            // Get connection stats
            $stats = $wpdb->get_results("SHOW STATUS LIKE 'Threads%'");
            foreach ($stats as $stat) {
                $name = strtolower(str_replace('Threads_', 'database_threads_', $stat->Variable_name));
                $metrics[] = $this->createMetric($name, 'gauge', 'MySQL ' . $stat->Variable_name, [
                    ['value' => (int) $stat->Value]
                ]);
            }
        }
        
        // Table sizes
        $tables = $wpdb->get_results("
            SELECT 
                TABLE_NAME as table_name,
                DATA_LENGTH + INDEX_LENGTH as size
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME LIKE '{$wpdb->prefix}%'
        ");
        
        foreach ($tables as $table) {
            $tableName = str_replace($wpdb->prefix, '', $table->table_name);
            $metrics[] = $this->createMetric('database_table_size_bytes', 'gauge', 'Database table size', [
                ['value' => (int) $table->size, 'labels' => ['table' => $tableName]]
            ]);
        }
        
        return $this->formatMetrics($metrics);
    }
    
    /**
     * Export cache metrics
     * 
     * @return string
     */
    protected function exportCacheMetrics(): string
    {
        $metrics = [];
        $container = Foundation::getInstance()->getContainer();
        
        if ($container->has('cache')) {
            $cache = $container->get('cache');
            
            // Get cache stats if available
            if (method_exists($cache, 'getStats')) {
                $stats = $cache->getStats();
                
                $metrics[] = $this->createMetric('cache_hits_total', 'counter', 'Cache hits', [
                    ['value' => $stats['hits'] ?? 0]
                ]);
                
                $metrics[] = $this->createMetric('cache_misses_total', 'counter', 'Cache misses', [
                    ['value' => $stats['misses'] ?? 0]
                ]);
                
                $metrics[] = $this->createMetric('cache_sets_total', 'counter', 'Cache sets', [
                    ['value' => $stats['sets'] ?? 0]
                ]);
                
                $metrics[] = $this->createMetric('cache_deletes_total', 'counter', 'Cache deletes', [
                    ['value' => $stats['deletes'] ?? 0]
                ]);
                
                if (isset($stats['hit_rate'])) {
                    $metrics[] = $this->createMetric('cache_hit_rate', 'gauge', 'Cache hit rate', [
                        ['value' => $stats['hit_rate']]
                    ]);
                }
            }
        }
        
        // Redis specific metrics
        if (class_exists('Redis')) {
            try {
                $redis = new \Redis();
                if ($redis->connect($this->env->get('cache.stores.redis.connection.host', '127.0.0.1'))) {
                    $info = $redis->info();
                    
                    $metrics[] = $this->createMetric('redis_connected_clients', 'gauge', 'Redis connected clients', [
                        ['value' => $info['connected_clients'] ?? 0]
                    ]);
                    
                    $metrics[] = $this->createMetric('redis_used_memory_bytes', 'gauge', 'Redis used memory', [
                        ['value' => $info['used_memory'] ?? 0]
                    ]);
                    
                    $metrics[] = $this->createMetric('redis_ops_per_sec', 'gauge', 'Redis operations per second', [
                        ['value' => $info['instantaneous_ops_per_sec'] ?? 0]
                    ]);
                    
                    $redis->close();
                }
            } catch (\Exception $e) {
                // Redis not available
            }
        }
        
        return $this->formatMetrics($metrics);
    }
    
    /**
     * Export queue metrics
     * 
     * @return string
     */
    protected function exportQueueMetrics(): string
    {
        global $wpdb;
        $metrics = [];
        
        // Queue job metrics
        if ($this->tableExists('zippicks_jobs')) {
            $jobStats = $wpdb->get_results("
                SELECT 
                    queue,
                    status,
                    COUNT(*) as count
                FROM {$wpdb->prefix}zippicks_jobs
                GROUP BY queue, status
            ");
            
            foreach ($jobStats as $stat) {
                $metrics[] = $this->createMetric('queue_jobs_total', 'gauge', 'Total queue jobs', [
                    ['value' => (int) $stat->count, 'labels' => [
                        'queue' => $stat->queue,
                        'status' => $stat->status
                    ]]
                ]);
            }
            
            // Failed jobs
            $failedCount = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}zippicks_failed_jobs
            ");
            
            $metrics[] = $this->createMetric('queue_failed_jobs_total', 'gauge', 'Total failed jobs', [
                ['value' => (int) $failedCount]
            ]);
        }
        
        // Worker metrics
        if ($this->tableExists('zippicks_queue_workers')) {
            $activeWorkers = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}zippicks_queue_workers
                WHERE last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            
            $metrics[] = $this->createMetric('queue_workers_active', 'gauge', 'Active queue workers', [
                ['value' => (int) $activeWorkers]
            ]);
        }
        
        return $this->formatMetrics($metrics);
    }
    
    /**
     * Create a metric array
     * 
     * @param string $name
     * @param string $type
     * @param string $help
     * @param array $samples
     * @return array
     */
    protected function createMetric(string $name, string $type, string $help, array $samples): array
    {
        return [
            'name' => $name,
            'type' => $type,
            'help' => $help,
            'samples' => $samples
        ];
    }
    
    /**
     * Create histogram sample
     * 
     * @param float $value
     * @param array $labels
     * @return array
     */
    protected function createHistogramSample(float $value, array $labels = []): array
    {
        $buckets = [];
        $count = 1;
        $sum = $value;
        
        foreach ($this->defaultBuckets as $bucket) {
            $buckets[(string)$bucket] = $value <= $bucket ? 1 : 0;
        }
        $buckets['+Inf'] = 1;
        
        return [[
            'labels' => $labels,
            'buckets' => $buckets,
            'count' => $count,
            'sum' => $sum
        ]];
    }
    
    /**
     * Format metric name
     * 
     * @param string $name
     * @return string
     */
    protected function formatMetricName(string $name): string
    {
        return $this->namespace . '_' . $name;
    }
    
    /**
     * Format labels
     * 
     * @param array $labels
     * @return string
     */
    protected function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }
        
        $formatted = [];
        foreach ($labels as $key => $value) {
            $value = str_replace('"', '\\"', (string) $value);
            $formatted[] = $key . '="' . $value . '"';
        }
        
        return '{' . implode(',', $formatted) . '}';
    }
    
    /**
     * Format metrics for output
     * 
     * @param array $metrics
     * @return string
     */
    protected function formatMetrics(array $metrics): string
    {
        $output = [];
        foreach ($metrics as $metric) {
            $output[] = $this->exportMetric($metric);
        }
        return implode("\n", $output);
    }
    
    /**
     * Check if table exists
     * 
     * @param string $table
     * @return bool
     */
    protected function tableExists(string $table): bool
    {
        global $wpdb;
        $fullTable = $wpdb->prefix . $table;
        return $wpdb->get_var("SHOW TABLES LIKE '{$fullTable}'") === $fullTable;
    }
    
    /**
     * Parse bytes from string
     * 
     * @param string $val
     * @return int
     */
    protected function parseBytes(string $val): int
    {
        $val = trim($val);
        if (empty($val)) {
            return 0;
        }
        
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
    
    /**
     * Extract role from serialized capabilities
     * 
     * @param string $serialized
     * @return string
     */
    protected function extractRole(string $serialized): string
    {
        $capabilities = @unserialize($serialized);
        if (is_array($capabilities)) {
            return key($capabilities) ?: 'unknown';
        }
        return 'unknown';
    }
    
    /**
     * Normalize endpoint for metrics
     * 
     * @param string $endpoint
     * @return string
     */
    protected function normalizeEndpoint(string $endpoint): string
    {
        // Remove IDs from endpoints
        $endpoint = preg_replace('/\/\d+/', '/{id}', $endpoint);
        
        // Remove query strings
        $endpoint = strtok($endpoint, '?');
        
        return $endpoint ?: 'unknown';
    }
    
    /**
     * Calculate MRR (placeholder)
     * 
     * @return float
     */
    protected function calculateMRR(): float
    {
        // This would integrate with actual billing/subscription data
        // For now, return a placeholder
        return 0.0;
    }
}