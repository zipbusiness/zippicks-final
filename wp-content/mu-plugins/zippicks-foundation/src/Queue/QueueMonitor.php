<?php
/**
 * Queue Monitor
 * 
 * Provides comprehensive monitoring and observability for the queue system.
 * Tracks metrics, performance, and health for the ZipPicks platform.
 * 
 * @package ZipPicks\Foundation\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue;

use ZipPicks\Foundation\Contracts\Queue\QueueMonitorInterface;
use ZipPicks\Foundation\Contracts\Queue\JobInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheInterface;
use DateTimeInterface;
use DateTime;
use Throwable;

/**
 * Queue Monitor
 * 
 * Monitors queue performance and health
 */
class QueueMonitor implements QueueMonitorInterface
{
    /**
     * WordPress database instance
     */
    protected \wpdb $database;
    
    /**
     * Metrics table name
     */
    protected string $metricsTable;
    
    /**
     * Cache instance
     */
    protected ?CacheInterface $cache;
    
    /**
     * Logger instance
     */
    protected ?LoggerInterface $logger;
    
    /**
     * Alert handlers
     */
    protected array $alertHandlers = [];
    
    /**
     * Metric aggregation intervals
     */
    protected array $intervals = [
        'minute' => 60,
        'hour' => 3600,
        'day' => 86400
    ];
    
    /**
     * Create a new queue monitor
     * 
     * @param \wpdb $database WordPress database
     * @param CacheInterface|null $cache Cache instance
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(
        \wpdb $database,
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null
    ) {
        $this->database = $database;
        $this->metricsTable = $database->prefix . 'zippicks_queue_metrics';
        $this->cache = $cache;
        $this->logger = $logger;
    }
    
    /**
     * {@inheritdoc}
     */
    public function recordJobDispatched(JobInterface $job, string $queue, string $connection): void
    {
        $this->recordMetric([
            'queue' => $queue,
            'job_class' => get_class($job),
            'job_id' => $job->getJobId(),
            'worker' => null,
            'status' => 'dispatched',
            'runtime' => null,
            'memory_usage' => null,
            'exception_class' => null,
            'metadata' => json_encode($job->metadata())
        ]);
        
        // Update cache counters
        $this->incrementCounter("dispatched:{$queue}", 3600);
        $this->incrementCounter("dispatched:{$connection}:{$queue}", 3600);
    }
    
    /**
     * {@inheritdoc}
     */
    public function recordJobProcessing(JobInterface $job, string $worker): void
    {
        $this->recordMetric([
            'queue' => $job->getQueue(),
            'job_class' => get_class($job),
            'job_id' => $job->getJobId(),
            'worker' => $worker,
            'status' => 'processing',
            'runtime' => null,
            'memory_usage' => null,
            'exception_class' => null,
            'metadata' => json_encode($job->metadata())
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function recordJobProcessed(JobInterface $job, float $runtime, int $memoryUsage): void
    {
        $this->recordMetric([
            'queue' => $job->getQueue(),
            'job_class' => get_class($job),
            'job_id' => $job->getJobId(),
            'worker' => null,
            'status' => 'processed',
            'runtime' => $runtime,
            'memory_usage' => $memoryUsage,
            'exception_class' => null,
            'metadata' => json_encode($job->metadata())
        ]);
        
        // Update cache counters and aggregates
        $queue = $job->getQueue();
        $this->incrementCounter("processed:{$queue}", 3600);
        $this->recordRuntime($queue, $runtime);
        $this->recordMemoryUsage($queue, $memoryUsage);
        
        // Check for slow jobs
        if ($runtime > 60) {
            $this->checkSlowJob($job, $runtime);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function recordJobFailed(JobInterface $job, Throwable $exception, float $runtime): void
    {
        $this->recordMetric([
            'queue' => $job->getQueue(),
            'job_class' => get_class($job),
            'job_id' => $job->getJobId(),
            'worker' => null,
            'status' => 'failed',
            'runtime' => $runtime,
            'memory_usage' => null,
            'exception_class' => get_class($exception),
            'metadata' => json_encode(array_merge(
                $job->metadata(),
                ['error' => $exception->getMessage()]
            ))
        ]);
        
        // Update cache counters
        $queue = $job->getQueue();
        $this->incrementCounter("failed:{$queue}", 3600);
        $this->incrementCounter("failed:{$queue}:" . get_class($exception), 3600);
        
        // Check failure rate
        $this->checkFailureRate($queue);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getMetrics(?string $queue = null, ?DateTimeInterface $since = null): array
    {
        $cacheKey = 'queue_metrics:' . ($queue ?? 'all') . ':' . ($since ? $since->getTimestamp() : 'all');
        
        // Try cache first
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $metrics = $this->calculateMetrics($queue, $since);
        
        // Cache for 1 minute
        if ($this->cache) {
            $this->cache->put($cacheKey, $metrics, 60);
        }
        
        return $metrics;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getWorkerMetrics(?string $worker = null): array
    {
        $where = $worker ? $this->database->prepare('AND worker = %s', $worker) : '';
        
        // Get active workers (processed job in last 5 minutes)
        $activeWorkers = (int) $this->database->get_var(
            "SELECT COUNT(DISTINCT worker) 
             FROM `{$this->metricsTable}` 
             WHERE worker IS NOT NULL 
             AND recorded_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             {$where}"
        );
        
        // Get jobs processed by workers
        $jobsProcessed = (int) $this->database->get_var(
            "SELECT COUNT(*) 
             FROM `{$this->metricsTable}` 
             WHERE status = 'processed' 
             AND worker IS NOT NULL
             {$where}"
        );
        
        // Get average memory usage
        $avgMemory = (int) $this->database->get_var(
            "SELECT AVG(memory_usage) 
             FROM `{$this->metricsTable}` 
             WHERE memory_usage IS NOT NULL 
             AND worker IS NOT NULL
             {$where}"
        );
        
        return [
            'active_workers' => $activeWorkers,
            'idle_workers' => 0, // Would need worker heartbeat to determine
            'jobs_processed' => $jobsProcessed,
            'average_memory' => $avgMemory,
            'uptime' => 0 // Would need worker start time tracking
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getJobMetrics(string $jobClass, ?DateTimeInterface $since = null): array
    {
        $sinceClause = $since 
            ? $this->database->prepare('AND recorded_at > %s', $since->format('Y-m-d H:i:s'))
            : '';
        
        $stats = $this->database->get_row($this->database->prepare(
            "SELECT 
                COUNT(CASE WHEN status = 'dispatched' THEN 1 END) as total_dispatched,
                COUNT(CASE WHEN status = 'processed' THEN 1 END) as total_processed,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as total_failed,
                AVG(CASE WHEN status = 'processed' THEN runtime END) as average_runtime,
                COUNT(DISTINCT job_id) as unique_jobs
             FROM `{$this->metricsTable}`
             WHERE job_class = %s
             {$sinceClause}",
            $jobClass
        ));
        
        $totalAttempted = $stats->total_processed + $stats->total_failed;
        $successRate = $totalAttempted > 0 
            ? ($stats->total_processed / $totalAttempted) * 100 
            : 0;
        
        // Calculate retry rate (jobs with multiple attempts)
        $retryCount = $this->database->get_var($this->database->prepare(
            "SELECT COUNT(DISTINCT job_id) 
             FROM `{$this->metricsTable}`
             WHERE job_class = %s
             {$sinceClause}
             GROUP BY job_id
             HAVING COUNT(*) > 1",
            $jobClass
        ));
        
        $retryRate = $stats->unique_jobs > 0 
            ? ($retryCount / $stats->unique_jobs) * 100 
            : 0;
        
        return [
            'total_dispatched' => (int) $stats->total_dispatched,
            'total_processed' => (int) $stats->total_processed,
            'total_failed' => (int) $stats->total_failed,
            'average_runtime' => (float) $stats->average_runtime,
            'success_rate' => round($successRate, 2),
            'retry_rate' => round($retryRate, 2)
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getHealthStatus(?string $queue = null): array
    {
        $checks = [];
        $recommendations = [];
        $healthy = true;
        
        // Check queue depth
        $queueDepth = $this->getQueueDepth($queue);
        if ($queueDepth > 10000) {
            $checks['queue_depth'] = [
                'passed' => false,
                'message' => "Queue depth is high: {$queueDepth} jobs"
            ];
            $recommendations[] = 'Scale up workers to process backlog';
            $healthy = false;
        } else {
            $checks['queue_depth'] = [
                'passed' => true,
                'message' => "Queue depth is healthy: {$queueDepth} jobs"
            ];
        }
        
        // Check failure rate
        $failureRate = $this->getRecentFailureRate($queue);
        if ($failureRate > 5) {
            $checks['failure_rate'] = [
                'passed' => false,
                'message' => "High failure rate: {$failureRate}%"
            ];
            $recommendations[] = 'Investigate failing jobs and fix root causes';
            $healthy = false;
        } else {
            $checks['failure_rate'] = [
                'passed' => true,
                'message' => "Failure rate is acceptable: {$failureRate}%"
            ];
        }
        
        // Check processing time
        $avgProcessingTime = $this->getAverageProcessingTime($queue);
        if ($avgProcessingTime > 30) {
            $checks['processing_time'] = [
                'passed' => false,
                'message' => "Slow processing time: {$avgProcessingTime}s average"
            ];
            $recommendations[] = 'Optimize job processing or split into smaller jobs';
            $healthy = false;
        } else {
            $checks['processing_time'] = [
                'passed' => true,
                'message' => "Processing time is good: {$avgProcessingTime}s average"
            ];
        }
        
        // Check worker health
        $activeWorkers = $this->getActiveWorkerCount();
        if ($activeWorkers === 0) {
            $checks['workers'] = [
                'passed' => false,
                'message' => 'No active workers detected'
            ];
            $recommendations[] = 'Start queue workers to process jobs';
            $healthy = false;
        } else {
            $checks['workers'] = [
                'passed' => true,
                'message' => "{$activeWorkers} active workers"
            ];
        }
        
        return [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'healthy' => $healthy,
            'checks' => $checks,
            'recommendations' => $recommendations
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSlowJobs(float $threshold = 60.0, int $limit = 100): array
    {
        $results = $this->database->get_results($this->database->prepare(
            "SELECT 
                job_id,
                job_class,
                runtime,
                recorded_at as processed_at,
                metadata
             FROM `{$this->metricsTable}`
             WHERE status = 'processed'
             AND runtime > %f
             ORDER BY runtime DESC
             LIMIT %d",
            $threshold,
            $limit
        ));
        
        return array_map(function ($row) {
            return [
                'job_id' => $row->job_id,
                'job_class' => $row->job_class,
                'runtime' => (float) $row->runtime,
                'processed_at' => new DateTime($row->processed_at)
            ];
        }, $results ?: []);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getFrequentlyFailingJobs(int $limit = 50, ?DateTimeInterface $since = null): array
    {
        $since = $since ?? new DateTime('-7 days');
        
        $results = $this->database->get_results($this->database->prepare(
            "SELECT 
                job_class,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failure_count,
                COUNT(CASE WHEN status = 'processed' THEN 1 END) as success_count,
                MAX(exception_class) as common_exception
             FROM `{$this->metricsTable}`
             WHERE recorded_at > %s
             AND status IN ('processed', 'failed')
             GROUP BY job_class
             HAVING failure_count > 0
             ORDER BY failure_count DESC
             LIMIT %d",
            $since->format('Y-m-d H:i:s'),
            $limit
        ));
        
        return array_map(function ($row) {
            $total = $row->failure_count + $row->success_count;
            $failureRate = $total > 0 ? ($row->failure_count / $total) * 100 : 0;
            
            return [
                'job_class' => $row->job_class,
                'failure_count' => (int) $row->failure_count,
                'success_count' => (int) $row->success_count,
                'failure_rate' => round($failureRate, 2),
                'common_exception' => $row->common_exception
            ];
        }, $results ?: []);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getTrends(?string $queue = null, string $interval = 'hour', int $periods = 24): array
    {
        $intervalSeconds = $this->intervals[$interval] ?? 3600;
        $since = new DateTime('-' . ($periods * $intervalSeconds) . ' seconds');
        
        $queueClause = $queue 
            ? $this->database->prepare('AND queue = %s', $queue)
            : '';
        
        $results = $this->database->get_results($this->database->prepare(
            "SELECT 
                DATE_FORMAT(recorded_at, %s) as period,
                COUNT(CASE WHEN status = 'dispatched' THEN 1 END) as jobs_dispatched,
                COUNT(CASE WHEN status = 'processed' THEN 1 END) as jobs_processed,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as jobs_failed,
                AVG(CASE WHEN status = 'processed' THEN runtime END) as average_runtime
             FROM `{$this->metricsTable}`
             WHERE recorded_at > %s
             {$queueClause}
             GROUP BY period
             ORDER BY period ASC",
            $this->getDateFormat($interval),
            $since->format('Y-m-d H:i:s')
        ));
        
        return array_map(function ($row) {
            return [
                'period' => $row->period,
                'jobs_dispatched' => (int) $row->jobs_dispatched,
                'jobs_processed' => (int) $row->jobs_processed,
                'jobs_failed' => (int) $row->jobs_failed,
                'average_runtime' => round((float) $row->average_runtime, 2)
            ];
        }, $results ?: []);
    }
    
    /**
     * {@inheritdoc}
     */
    public function alert(array $alert): void
    {
        foreach ($this->alertHandlers as $handler) {
            try {
                call_user_func($handler, $alert);
            } catch (Throwable $e) {
                $this->logger?->error('Alert handler failed', [
                    'alert' => $alert,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Log alert
        $this->logger?->warning('Queue alert triggered', $alert);
    }
    
    /**
     * {@inheritdoc}
     */
    public function pruneMetrics(DateTimeInterface $before): int
    {
        return $this->database->delete(
            $this->metricsTable,
            ['recorded_at <' => $before->format('Y-m-d H:i:s')],
            ['%s']
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function exportMetrics(string $format, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): string
    {
        $from = $from ?? new DateTime('-7 days');
        $to = $to ?? new DateTime();
        
        $metrics = $this->database->get_results($this->database->prepare(
            "SELECT * FROM `{$this->metricsTable}`
             WHERE recorded_at BETWEEN %s AND %s
             ORDER BY recorded_at ASC",
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s')
        ), ARRAY_A);
        
        return match($format) {
            'json' => json_encode($metrics),
            'csv' => $this->exportToCsv($metrics),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}")
        };
    }
    
    /**
     * Register an alert handler
     * 
     * @param callable $handler Alert handler
     * @return void
     */
    public function registerAlertHandler(callable $handler): void
    {
        $this->alertHandlers[] = $handler;
    }
    
    /**
     * Record a metric
     * 
     * @param array $data Metric data
     * @return void
     */
    protected function recordMetric(array $data): void
    {
        $this->database->insert(
            $this->metricsTable,
            array_merge($data, [
                'recorded_at' => current_time('mysql')
            ]),
            ['%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s']
        );
    }
    
    /**
     * Increment a counter in cache
     * 
     * @param string $key Counter key
     * @param int $ttl TTL in seconds
     * @return void
     */
    protected function incrementCounter(string $key, int $ttl): void
    {
        if (!$this->cache) {
            return;
        }
        
        $current = (int) $this->cache->get($key, 0);
        $this->cache->put($key, $current + 1, $ttl);
    }
    
    /**
     * Record runtime metric
     * 
     * @param string $queue Queue name
     * @param float $runtime Runtime in seconds
     * @return void
     */
    protected function recordRuntime(string $queue, float $runtime): void
    {
        if (!$this->cache) {
            return;
        }
        
        $key = "runtime:{$queue}";
        $data = $this->cache->get($key, ['count' => 0, 'total' => 0]);
        
        $data['count']++;
        $data['total'] += $runtime;
        
        $this->cache->put($key, $data, 3600);
    }
    
    /**
     * Record memory usage metric
     * 
     * @param string $queue Queue name
     * @param int $memory Memory usage in bytes
     * @return void
     */
    protected function recordMemoryUsage(string $queue, int $memory): void
    {
        if (!$this->cache) {
            return;
        }
        
        $key = "memory:{$queue}";
        $data = $this->cache->get($key, ['count' => 0, 'total' => 0]);
        
        $data['count']++;
        $data['total'] += $memory;
        
        $this->cache->put($key, $data, 3600);
    }
    
    /**
     * Calculate metrics
     * 
     * @param string|null $queue Queue name
     * @param DateTimeInterface|null $since Since date
     * @return array
     */
    protected function calculateMetrics(?string $queue, ?DateTimeInterface $since): array
    {
        $queueClause = $queue 
            ? $this->database->prepare('AND queue = %s', $queue)
            : '';
        
        $sinceClause = $since 
            ? $this->database->prepare('AND recorded_at > %s', $since->format('Y-m-d H:i:s'))
            : '';
        
        // Get basic counts
        $stats = $this->database->get_row(
            "SELECT 
                COUNT(CASE WHEN status = 'dispatched' THEN 1 END) as dispatched,
                COUNT(CASE WHEN status = 'processed' THEN 1 END) as processed,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                AVG(CASE WHEN status = 'processed' THEN runtime END) as avg_runtime,
                PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY runtime) as p50_runtime,
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY runtime) as p95_runtime,
                PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY runtime) as p99_runtime
             FROM `{$this->metricsTable}`
             WHERE 1=1
             {$queueClause}
             {$sinceClause}"
        );
        
        // Calculate rates
        $total = $stats->processed + $stats->failed;
        $successRate = $total > 0 ? ($stats->processed / $total) * 100 : 100;
        $failureRate = $total > 0 ? ($stats->failed / $total) * 100 : 0;
        
        // Get queue depth from jobs table
        $queueDepth = $this->getQueueDepth($queue);
        
        // Calculate throughput (jobs per minute)
        $timeRange = $since ? (time() - $since->getTimestamp()) / 60 : 1440; // Default 24 hours
        $throughput = $timeRange > 0 ? $stats->processed / $timeRange : 0;
        
        return [
            'throughput' => round($throughput, 2),
            'average_runtime' => round((float) $stats->avg_runtime, 2),
            'success_rate' => round($successRate, 2),
            'failure_rate' => round($failureRate, 2),
            'jobs_per_minute' => round($throughput, 2),
            'queue_depth' => $queueDepth,
            'processing_time_p50' => round((float) $stats->p50_runtime, 2),
            'processing_time_p95' => round((float) $stats->p95_runtime, 2),
            'processing_time_p99' => round((float) $stats->p99_runtime, 2)
        ];
    }
    
    /**
     * Get queue depth
     * 
     * @param string|null $queue Queue name
     * @return int
     */
    protected function getQueueDepth(?string $queue): int
    {
        global $wpdb;
        $jobsTable = $wpdb->prefix . 'zippicks_jobs';
        
        $queueClause = $queue 
            ? $wpdb->prepare('AND queue = %s', $queue)
            : '';
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$jobsTable}`
             WHERE reserved_at IS NULL
             {$queueClause}"
        );
    }
    
    /**
     * Get recent failure rate
     * 
     * @param string|null $queue Queue name
     * @return float
     */
    protected function getRecentFailureRate(?string $queue): float
    {
        if ($this->cache) {
            $processed = (int) $this->cache->get("processed:" . ($queue ?? 'all'), 0);
            $failed = (int) $this->cache->get("failed:" . ($queue ?? 'all'), 0);
            
            $total = $processed + $failed;
            return $total > 0 ? ($failed / $total) * 100 : 0;
        }
        
        return 0;
    }
    
    /**
     * Get average processing time
     * 
     * @param string|null $queue Queue name
     * @return float
     */
    protected function getAverageProcessingTime(?string $queue): float
    {
        if ($this->cache) {
            $data = $this->cache->get("runtime:" . ($queue ?? 'all'), ['count' => 0, 'total' => 0]);
            return $data['count'] > 0 ? $data['total'] / $data['count'] : 0;
        }
        
        return 0;
    }
    
    /**
     * Get active worker count
     * 
     * @return int
     */
    protected function getActiveWorkerCount(): int
    {
        return (int) $this->database->get_var(
            "SELECT COUNT(DISTINCT worker) 
             FROM `{$this->metricsTable}`
             WHERE worker IS NOT NULL
             AND recorded_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );
    }
    
    /**
     * Check for slow job alert
     * 
     * @param JobInterface $job The job
     * @param float $runtime Runtime in seconds
     * @return void
     */
    protected function checkSlowJob(JobInterface $job, float $runtime): void
    {
        $this->alert([
            'type' => 'slow_job',
            'severity' => 'warning',
            'job_class' => get_class($job),
            'job_id' => $job->getJobId(),
            'runtime' => $runtime,
            'threshold' => 60,
            'message' => sprintf(
                'Job %s took %.2f seconds to process',
                get_class($job),
                $runtime
            )
        ]);
    }
    
    /**
     * Check failure rate alert
     * 
     * @param string $queue Queue name
     * @return void
     */
    protected function checkFailureRate(string $queue): void
    {
        $failureRate = $this->getRecentFailureRate($queue);
        
        if ($failureRate > 10) {
            $this->alert([
                'type' => 'high_failure_rate',
                'severity' => 'critical',
                'queue' => $queue,
                'failure_rate' => $failureRate,
                'threshold' => 10,
                'message' => sprintf(
                    'Queue %s has %.2f%% failure rate',
                    $queue,
                    $failureRate
                )
            ]);
        }
    }
    
    /**
     * Get date format for interval
     * 
     * @param string $interval Interval name
     * @return string
     */
    protected function getDateFormat(string $interval): string
    {
        return match($interval) {
            'minute' => '%Y-%m-%d %H:%i:00',
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d %H:00:00'
        };
    }
    
    /**
     * Export metrics to CSV
     * 
     * @param array $metrics Metrics data
     * @return string
     */
    protected function exportToCsv(array $metrics): string
    {
        if (empty($metrics)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, array_keys($metrics[0]));
        
        // Write data
        foreach ($metrics as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}