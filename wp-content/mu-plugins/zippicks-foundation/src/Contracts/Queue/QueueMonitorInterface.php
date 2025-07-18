<?php
/**
 * Queue Monitor Interface
 * 
 * Provides monitoring and observability for the queue system.
 * Essential for maintaining SLAs and identifying bottlenecks.
 * 
 * @package ZipPicks\Foundation\Contracts\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Queue;

use DateTimeInterface;

/**
 * Queue Monitor Interface
 * 
 * Contract for queue monitoring and metrics
 */
interface QueueMonitorInterface
{
    /**
     * Record job dispatched event
     * 
     * @param JobInterface $job The dispatched job
     * @param string $queue Queue name
     * @param string $connection Connection name
     * @return void
     */
    public function recordJobDispatched(JobInterface $job, string $queue, string $connection): void;

    /**
     * Record job processing started
     * 
     * @param JobInterface $job The job being processed
     * @param string $worker Worker name
     * @return void
     */
    public function recordJobProcessing(JobInterface $job, string $worker): void;

    /**
     * Record job processed successfully
     * 
     * @param JobInterface $job The processed job
     * @param float $runtime Runtime in seconds
     * @param int $memoryUsage Memory usage in bytes
     * @return void
     */
    public function recordJobProcessed(JobInterface $job, float $runtime, int $memoryUsage): void;

    /**
     * Record job failed
     * 
     * @param JobInterface $job The failed job
     * @param \Throwable $exception The exception that caused failure
     * @param float $runtime Runtime before failure
     * @return void
     */
    public function recordJobFailed(JobInterface $job, \Throwable $exception, float $runtime): void;

    /**
     * Get queue metrics
     * 
     * @param string|null $queue Queue name (null for all queues)
     * @param DateTimeInterface|null $since Metrics since this time
     * @return array{
     *     throughput: float,
     *     average_runtime: float,
     *     success_rate: float,
     *     failure_rate: float,
     *     jobs_per_minute: float,
     *     queue_depth: int,
     *     processing_time_p50: float,
     *     processing_time_p95: float,
     *     processing_time_p99: float
     * }
     */
    public function getMetrics(?string $queue = null, ?DateTimeInterface $since = null): array;

    /**
     * Get worker metrics
     * 
     * @param string|null $worker Worker name (null for all workers)
     * @return array{
     *     active_workers: int,
     *     idle_workers: int,
     *     jobs_processed: int,
     *     average_memory: int,
     *     uptime: int
     * }
     */
    public function getWorkerMetrics(?string $worker = null): array;

    /**
     * Get job metrics by class
     * 
     * @param string $jobClass Job class name
     * @param DateTimeInterface|null $since Metrics since this time
     * @return array{
     *     total_dispatched: int,
     *     total_processed: int,
     *     total_failed: int,
     *     average_runtime: float,
     *     success_rate: float,
     *     retry_rate: float
     * }
     */
    public function getJobMetrics(string $jobClass, ?DateTimeInterface $since = null): array;

    /**
     * Get queue health status
     * 
     * @param string|null $queue Queue name (null for overall health)
     * @return array{
     *     status: string,
     *     healthy: bool,
     *     checks: array<string, array{passed: bool, message: string}>,
     *     recommendations: array<string>
     * }
     */
    public function getHealthStatus(?string $queue = null): array;

    /**
     * Get slow jobs
     * 
     * @param float $threshold Runtime threshold in seconds
     * @param int $limit Maximum results
     * @return array<array{
     *     job_id: string,
     *     job_class: string,
     *     runtime: float,
     *     processed_at: DateTimeInterface
     * }>
     */
    public function getSlowJobs(float $threshold = 60.0, int $limit = 100): array;

    /**
     * Get frequently failing jobs
     * 
     * @param int $limit Maximum results
     * @param DateTimeInterface|null $since Since this time
     * @return array<array{
     *     job_class: string,
     *     failure_count: int,
     *     success_count: int,
     *     failure_rate: float,
     *     common_exception: string
     * }>
     */
    public function getFrequentlyFailingJobs(int $limit = 50, ?DateTimeInterface $since = null): array;

    /**
     * Get queue trends
     * 
     * @param string|null $queue Queue name
     * @param string $interval Interval (hour, day, week)
     * @param int $periods Number of periods
     * @return array<array{
     *     period: string,
     *     jobs_dispatched: int,
     *     jobs_processed: int,
     *     jobs_failed: int,
     *     average_runtime: float
     * }>
     */
    public function getTrends(?string $queue = null, string $interval = 'hour', int $periods = 24): array;

    /**
     * Alert on queue issues
     * 
     * @param array<string, mixed> $alert Alert configuration
     * @return void
     */
    public function alert(array $alert): void;

    /**
     * Clear old metrics data
     * 
     * @param DateTimeInterface $before Clear data before this date
     * @return int Number of records cleared
     */
    public function pruneMetrics(DateTimeInterface $before): int;

    /**
     * Export metrics for analysis
     * 
     * @param string $format Export format (json, csv, etc.)
     * @param DateTimeInterface|null $from Start date
     * @param DateTimeInterface|null $to End date
     * @return string Exported data
     */
    public function exportMetrics(string $format, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): string;
}