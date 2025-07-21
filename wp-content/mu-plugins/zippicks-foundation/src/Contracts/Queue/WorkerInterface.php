<?php
/**
 * Worker Interface
 * 
 * Defines the contract for queue workers that process jobs.
 * Workers are the engines that power ZipPicks' asynchronous processing.
 * 
 * @package ZipPicks\Foundation\Contracts\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Queue;

/**
 * Worker Interface
 * 
 * Contract for queue workers that process jobs
 */
interface WorkerInterface
{
    /**
     * Process jobs from the queue
     * 
     * @param string $connectionName The queue connection to use
     * @param string $queue The queue to process
     * @param WorkerOptions $options Worker options
     * @return void
     */
    public function daemon(string $connectionName, string $queue, WorkerOptions $options): void;

    /**
     * Process the next job on the queue
     * 
     * @param string $connectionName The queue connection
     * @param string $queue The queue name
     * @param WorkerOptions $options Worker options
     * @return void
     */
    public function runNextJob(string $connectionName, string $queue, WorkerOptions $options): void;

    /**
     * Process a single job
     * 
     * @param JobInterface $job The job to process
     * @param string $connectionName The connection name
     * @param WorkerOptions $options Worker options
     * @return void
     */
    public function process(JobInterface $job, string $connectionName, WorkerOptions $options): void;

    /**
     * Stop processing jobs
     * 
     * @param int $status Exit status code
     * @return void
     */
    public function stop(int $status = 0): void;

    /**
     * Kill the worker process
     * 
     * @param int $status Exit status code
     * @return void
     */
    public function kill(int $status = 0): void;

    /**
     * Check if the worker should quit
     * 
     * @return bool
     */
    public function shouldQuit(): bool;

    /**
     * Sleep for the specified number of seconds
     * 
     * @param int|float $seconds Number of seconds
     * @return void
     */
    public function sleep($seconds): void;

    /**
     * Set the memory limit for the worker
     * 
     * @param int $memoryLimit Memory limit in MB
     * @return void
     */
    public function setMemoryLimit(int $memoryLimit): void;

    /**
     * Check if memory limit has been exceeded
     * 
     * @param int $memoryLimit Memory limit in MB
     * @return bool
     */
    public function memoryExceeded(int $memoryLimit): bool;

    /**
     * Get the current memory usage
     * 
     * @return int Memory usage in MB
     */
    public function getMemoryUsage(): int;

    /**
     * Register signal handlers for the worker
     * 
     * @return void
     */
    public function registerSignalHandlers(): void;

    /**
     * Handle worker timeout
     * 
     * @param JobInterface $job The job that timed out
     * @param WorkerOptions $options Worker options
     * @return void
     */
    public function handleJobTimeout(JobInterface $job, WorkerOptions $options): void;

    /**
     * Mark a job as failed
     * 
     * @param JobInterface $job The failed job
     * @param \Throwable $exception The exception that caused failure
     * @return void
     */
    public function markJobAsFailed(JobInterface $job, \Throwable $exception): void;

    /**
     * Get worker statistics
     * 
     * @return array{
     *     processed: int,
     *     failed: int,
     *     memory_usage: int,
     *     uptime: int,
     *     last_job_at: int|null,
     *     status: string
     * }
     */
    public function getStatistics(): array;

    /**
     * Pause the worker
     * 
     * @return void
     */
    public function pause(): void;

    /**
     * Resume the worker
     * 
     * @return void
     */
    public function resume(): void;

    /**
     * Check if the worker is paused
     * 
     * @return bool
     */
    public function isPaused(): bool;

    /**
     * Set the worker name
     * 
     * @param string $name Worker name
     * @return void
     */
    public function setName(string $name): void;

    /**
     * Get the worker name
     * 
     * @return string
     */
    public function getName(): string;
}

/**
 * Worker Options
 * 
 * Configuration options for queue workers
 */
class WorkerOptions
{
    public function __construct(
        public int $sleep = 3,
        public int $tries = 3,
        public int $timeout = 60,
        public int $maxJobs = 1000,
        public int $maxTime = 3600,
        public int $memory = 128,
        public bool $force = false,
        public bool $stopWhenEmpty = false,
        public ?string $name = null,
        public int $backoff = 0,
        public ?int $rest = null
    ) {
    }
}