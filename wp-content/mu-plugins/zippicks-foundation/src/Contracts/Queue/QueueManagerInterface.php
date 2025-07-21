<?php
/**
 * Queue Manager Interface
 * 
 * The orchestrator for all queue operations in the ZipPicks enterprise platform.
 * This manager handles multi-driver support, job routing, and queue health.
 * 
 * @package ZipPicks\Foundation\Contracts\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Queue;

use Closure;

/**
 * Queue Manager Interface
 * 
 * Manages all queue operations across different drivers and connections
 */
interface QueueManagerInterface
{
    /**
     * Get a queue connection instance
     * 
     * @param string|null $name The connection name, null for default
     * @return QueueDriverInterface
     */
    public function connection(?string $name = null): QueueDriverInterface;

    /**
     * Push a job onto the queue
     * 
     * @param string|JobInterface $job The job class or instance
     * @param mixed $data The job data
     * @param string|null $queue The queue name
     * @return mixed Job identifier
     */
    public function push($job, $data = '', ?string $queue = null);

    /**
     * Push a job with delay
     * 
     * @param \DateTimeInterface|\DateInterval|int $delay Delay in seconds or datetime
     * @param string|JobInterface $job The job class or instance
     * @param mixed $data The job data
     * @param string|null $queue The queue name
     * @return mixed Job identifier
     */
    public function later($delay, $job, $data = '', ?string $queue = null);

    /**
     * Push a job onto a specific queue
     * 
     * @param string $queue The queue name
     * @param string|JobInterface $job The job class or instance
     * @param mixed $data The job data
     * @return mixed Job identifier
     */
    public function pushOn(string $queue, $job, $data = '');

    /**
     * Push a job with delay onto a specific queue
     * 
     * @param string $queue The queue name
     * @param \DateTimeInterface|\DateInterval|int $delay Delay
     * @param string|JobInterface $job The job class or instance
     * @param mixed $data The job data
     * @return mixed Job identifier
     */
    public function laterOn(string $queue, $delay, $job, $data = '');

    /**
     * Push multiple jobs onto the queue
     * 
     * @param array<string|JobInterface> $jobs Array of jobs
     * @param mixed $data Shared data for all jobs
     * @param string|null $queue The queue name
     * @return void
     */
    public function bulk(array $jobs, $data = '', ?string $queue = null): void;

    /**
     * Create a job batch
     * 
     * @param array<JobInterface> $jobs Jobs to batch
     * @return JobBatchInterface
     */
    public function batch(array $jobs): JobBatchInterface;

    /**
     * Create a job chain
     * 
     * @param array<JobInterface> $jobs Jobs to chain
     * @return JobChainInterface
     */
    public function chain(array $jobs): JobChainInterface;

    /**
     * Get the connection name for the queue
     * 
     * @param string|null $queue Queue name
     * @return string Connection name
     */
    public function getConnectionName(?string $queue = null): string;

    /**
     * Register a queue connection resolver
     * 
     * @param string $driver Driver name
     * @param Closure $resolver Resolver closure
     * @return void
     */
    public function addConnector(string $driver, Closure $resolver): void;

    /**
     * Get all registered queue connections
     * 
     * @return array<string, QueueDriverInterface>
     */
    public function getConnections(): array;

    /**
     * Set the default queue connection name
     * 
     * @param string $name Connection name
     * @return void
     */
    public function setDefaultConnection(string $name): void;

    /**
     * Get the default queue connection name
     * 
     * @return string
     */
    public function getDefaultConnection(): string;

    /**
     * Get queue statistics for monitoring
     * 
     * @param string|null $connection Connection name
     * @param string|null $queue Queue name
     * @return array{
     *     size: int,
     *     ready: int,
     *     reserved: int,
     *     delayed: int,
     *     failed: int,
     *     throughput: float,
     *     latency: float
     * }
     */
    public function getStatistics(?string $connection = null, ?string $queue = null): array;

    /**
     * Clear all jobs from a queue
     * 
     * @param string $queue Queue name
     * @param string|null $connection Connection name
     * @return int Number of jobs cleared
     */
    public function clear(string $queue, ?string $connection = null): int;

    /**
     * Get the failed job repository
     * 
     * @return FailedJobProviderInterface
     */
    public function getFailedJobRepository(): FailedJobProviderInterface;

    /**
     * Check if a queue connection exists
     * 
     * @param string $name Connection name
     * @return bool
     */
    public function connected(string $name): bool;

    /**
     * Disconnect from a queue connection
     * 
     * @param string|null $name Connection name
     * @return void
     */
    public function disconnect(?string $name = null): void;
}