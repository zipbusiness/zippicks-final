<?php
/**
 * Queue Driver Interface
 * 
 * Defines the contract for all queue drivers in the ZipPicks platform.
 * Each driver (database, redis, sqs, etc.) must implement this interface.
 * 
 * @package ZipPicks\Foundation\Contracts\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Queue;

use DateTimeInterface;
use DateInterval;

/**
 * Queue Driver Interface
 * 
 * Common interface for all queue backend implementations
 */
interface QueueDriverInterface
{
    /**
     * Get the size of the queue
     * 
     * @param string|null $queue Queue name
     * @return int Number of jobs in queue
     */
    public function size(?string $queue = null): int;

    /**
     * Push a new job onto the queue
     * 
     * @param string|JobInterface $job Job instance or class name
     * @param mixed $data Job data/payload
     * @param string|null $queue Queue name
     * @return mixed Job identifier
     */
    public function push($job, $data = '', ?string $queue = null);

    /**
     * Push a raw payload onto the queue
     * 
     * @param string $payload Raw job payload
     * @param string|null $queue Queue name
     * @param array<string, mixed> $options Additional options
     * @return mixed Job identifier
     */
    public function pushRaw(string $payload, ?string $queue = null, array $options = []);

    /**
     * Push a job onto the queue after a delay
     * 
     * @param DateTimeInterface|DateInterval|int $delay Delay before job is available
     * @param string|JobInterface $job Job instance or class name
     * @param mixed $data Job data/payload
     * @param string|null $queue Queue name
     * @return mixed Job identifier
     */
    public function later($delay, $job, $data = '', ?string $queue = null);

    /**
     * Push a raw job onto the queue after a delay
     * 
     * @param DateTimeInterface|DateInterval|int $delay Delay before job is available
     * @param string $payload Raw job payload
     * @param string|null $queue Queue name
     * @param int $attempts Number of attempts
     * @return mixed Job identifier
     */
    public function laterRaw($delay, string $payload, ?string $queue = null, int $attempts = 0);

    /**
     * Pop the next job off of the queue
     * 
     * @param string|null $queue Queue name
     * @param int $timeout Visibility timeout in seconds
     * @return JobInterface|null
     */
    public function pop(?string $queue = null, int $timeout = 0): ?JobInterface;

    /**
     * Push multiple jobs onto the queue
     * 
     * @param array<string|JobInterface> $jobs Array of jobs
     * @param mixed $data Shared data for all jobs
     * @param string|null $queue Queue name
     * @return void
     */
    public function bulk(array $jobs, $data = '', ?string $queue = null): void;

    /**
     * Release a job back onto the queue
     * 
     * @param string $queue Queue name
     * @param JobInterface $job Job to release
     * @param int $delay Delay before job is available again
     * @return mixed Job identifier
     */
    public function release(string $queue, JobInterface $job, int $delay = 0);

    /**
     * Delete a job from the queue
     * 
     * @param string $queue Queue name
     * @param string $id Job identifier
     * @return void
     */
    public function delete(string $queue, string $id): void;

    /**
     * Clear all jobs from the queue
     * 
     * @param string $queue Queue name
     * @return int Number of jobs cleared
     */
    public function clear(string $queue): int;

    /**
     * Get the queue connection name
     * 
     * @return string
     */
    public function getConnectionName(): string;

    /**
     * Set the job creator callback
     * 
     * @param callable|null $callback Job creator callback
     * @return void
     */
    public function setJobCreator(?callable $callback): void;

    /**
     * Get queue metadata
     * 
     * @param string|null $queue Queue name
     * @return array{
     *     driver: string,
     *     connection: string,
     *     queue: string,
     *     ready: int,
     *     reserved: int,
     *     delayed: int,
     *     total: int
     * }
     */
    public function getMetadata(?string $queue = null): array;

    /**
     * Check if the driver supports a feature
     * 
     * @param string $feature Feature name (e.g., 'priority', 'delay', 'batch')
     * @return bool
     */
    public function supports(string $feature): bool;

    /**
     * Get jobs by status
     * 
     * @param string $status Job status (ready, reserved, delayed)
     * @param string|null $queue Queue name
     * @param int $limit Maximum number of jobs to return
     * @return array<JobInterface>
     */
    public function getJobsByStatus(string $status, ?string $queue = null, int $limit = 100): array;

    /**
     * Migrate jobs from one queue to another
     * 
     * @param string $from Source queue
     * @param string $to Destination queue
     * @param int $limit Maximum jobs to migrate
     * @return int Number of jobs migrated
     */
    public function migrate(string $from, string $to, int $limit = 1000): int;

    /**
     * Get health status of the queue driver
     * 
     * @return array{
     *     healthy: bool,
     *     message: string,
     *     latency: float,
     *     last_error: string|null
     * }
     */
    public function health(): array;
}