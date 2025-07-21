<?php
/**
 * Queue Helper Functions
 * 
 * Global helper functions for queue operations in the ZipPicks platform.
 * 
 * @package ZipPicks\Foundation\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

use ZipPicks\Foundation\Contracts\Queue\QueueManagerInterface;
use ZipPicks\Foundation\Contracts\Queue\JobInterface;

if (!function_exists('dispatch')) {
    /**
     * Dispatch a job to the queue
     * 
     * @param JobInterface|string $job Job instance or class name
     * @param mixed $data Job data (if job is a class name)
     * @return mixed Job ID
     */
    function dispatch($job, $data = '')
    {
        $manager = app(QueueManagerInterface::class);
        
        if ($job instanceof JobInterface) {
            return $manager->dispatch($job);
        }
        
        return $manager->push($job, $data);
    }
}

if (!function_exists('dispatch_now')) {
    /**
     * Dispatch a job immediately (sync)
     * 
     * @param JobInterface|string $job Job instance or class name
     * @param mixed $data Job data (if job is a class name)
     * @return mixed
     */
    function dispatch_now($job, $data = '')
    {
        $manager = app(QueueManagerInterface::class);
        
        if ($job instanceof JobInterface) {
            // Force sync processing
            return $manager->connection('sync')->push($job);
        }
        
        return $manager->connection('sync')->push($job, $data);
    }
}

if (!function_exists('dispatch_after')) {
    /**
     * Dispatch a job with delay
     * 
     * @param int|\DateTimeInterface|\DateInterval $delay Delay before job is available
     * @param JobInterface|string $job Job instance or class name
     * @param mixed $data Job data (if job is a class name)
     * @return mixed Job ID
     */
    function dispatch_after($delay, $job, $data = '')
    {
        $manager = app(QueueManagerInterface::class);
        
        return $manager->later($delay, $job, $data);
    }
}

if (!function_exists('dispatch_on')) {
    /**
     * Dispatch a job to a specific queue
     * 
     * @param string $queue Queue name
     * @param JobInterface|string $job Job instance or class name
     * @param mixed $data Job data (if job is a class name)
     * @return mixed Job ID
     */
    function dispatch_on(string $queue, $job, $data = '')
    {
        $manager = app(QueueManagerInterface::class);
        
        return $manager->pushOn($queue, $job, $data);
    }
}

if (!function_exists('queue_size')) {
    /**
     * Get the size of a queue
     * 
     * @param string|null $queue Queue name
     * @param string|null $connection Connection name
     * @return int Number of jobs in queue
     */
    function queue_size(?string $queue = null, ?string $connection = null): int
    {
        $manager = app(QueueManagerInterface::class);
        
        return $manager->connection($connection)->size($queue);
    }
}

if (!function_exists('queue_health')) {
    /**
     * Get queue health status
     * 
     * @param string|null $queue Queue name
     * @return array Health status
     */
    function queue_health(?string $queue = null): array
    {
        $monitor = app(\ZipPicks\Foundation\Contracts\Queue\QueueMonitorInterface::class);
        
        return $monitor->getHealthStatus($queue);
    }
}

if (!function_exists('queue_metrics')) {
    /**
     * Get queue metrics
     * 
     * @param string|null $queue Queue name
     * @param \DateTimeInterface|null $since Since date
     * @return array Metrics
     */
    function queue_metrics(?string $queue = null, ?\DateTimeInterface $since = null): array
    {
        $monitor = app(\ZipPicks\Foundation\Contracts\Queue\QueueMonitorInterface::class);
        
        return $monitor->getMetrics($queue, $since);
    }
}

if (!function_exists('retry_job')) {
    /**
     * Retry a failed job
     * 
     * @param string|int $jobId Failed job ID
     * @return bool Success
     */
    function retry_job($jobId): bool
    {
        $failedProvider = app(\ZipPicks\Foundation\Contracts\Queue\FailedJobProviderInterface::class);
        
        return $failedProvider->retry($jobId);
    }
}

if (!function_exists('batch')) {
    /**
     * Create a job batch
     * 
     * @param array<JobInterface> $jobs Jobs to batch
     * @return \ZipPicks\Foundation\Contracts\Queue\JobBatchInterface
     */
    function batch(array $jobs): \ZipPicks\Foundation\Contracts\Queue\JobBatchInterface
    {
        $manager = app(QueueManagerInterface::class);
        
        return $manager->batch($jobs);
    }
}

if (!function_exists('chain')) {
    /**
     * Create a job chain
     * 
     * @param array<JobInterface> $jobs Jobs to chain
     * @return \ZipPicks\Foundation\Contracts\Queue\JobChainInterface
     */
    function chain(array $jobs): \ZipPicks\Foundation\Contracts\Queue\JobChainInterface
    {
        $manager = app(QueueManagerInterface::class);
        
        return $manager->chain($jobs);
    }
}

if (!function_exists('queue_worker_status')) {
    /**
     * Get worker status
     * 
     * @param string|null $worker Worker name
     * @return array Worker metrics
     */
    function queue_worker_status(?string $worker = null): array
    {
        $monitor = app(\ZipPicks\Foundation\Contracts\Queue\QueueMonitorInterface::class);
        
        return $monitor->getWorkerMetrics($worker);
    }
}

if (!function_exists('queue_failed_jobs')) {
    /**
     * Get failed jobs
     * 
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array<\ZipPicks\Foundation\Contracts\Queue\FailedJob>
     */
    function queue_failed_jobs(int $limit = 50, int $offset = 0): array
    {
        $failedProvider = app(\ZipPicks\Foundation\Contracts\Queue\FailedJobProviderInterface::class);
        
        return $failedProvider->all($limit, $offset);
    }
}

if (!function_exists('queue_clear')) {
    /**
     * Clear all jobs from a queue
     * 
     * @param string $queue Queue name
     * @param string|null $connection Connection name
     * @return int Number of jobs cleared
     */
    function queue_clear(string $queue, ?string $connection = null): int
    {
        $manager = app(QueueManagerInterface::class);
        
        return $manager->clear($queue, $connection);
    }
}