<?php
/**
 * Failed Job Provider Interface
 * 
 * Manages failed jobs for retry, analysis, and debugging.
 * Critical for maintaining reliability in the ZipPicks platform.
 * 
 * @package ZipPicks\Foundation\Contracts\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Queue;

use DateTimeInterface;

/**
 * Failed Job Provider Interface
 * 
 * Contract for managing failed queue jobs
 */
interface FailedJobProviderInterface
{
    /**
     * Log a failed job
     * 
     * @param string $connection Connection name
     * @param string $queue Queue name
     * @param string $payload Job payload
     * @param \Throwable $exception The exception that caused failure
     * @return string|int Failed job ID
     */
    public function log(string $connection, string $queue, string $payload, \Throwable $exception);

    /**
     * Get all failed jobs
     * 
     * @param int $limit Maximum number of jobs to return
     * @param int $offset Offset for pagination
     * @return array<FailedJob>
     */
    public function all(int $limit = 50, int $offset = 0): array;

    /**
     * Get a specific failed job
     * 
     * @param string|int $id Failed job ID
     * @return FailedJob|null
     */
    public function find($id): ?FailedJob;

    /**
     * Retry a failed job
     * 
     * @param string|int $id Failed job ID
     * @return bool Whether the retry was successful
     */
    public function retry($id): bool;

    /**
     * Retry all failed jobs
     * 
     * @param string|null $queue Retry only jobs from this queue
     * @return int Number of jobs retried
     */
    public function retryAll(?string $queue = null): int;

    /**
     * Delete a failed job
     * 
     * @param string|int $id Failed job ID
     * @return bool Whether deletion was successful
     */
    public function forget($id): bool;

    /**
     * Delete all failed jobs
     * 
     * @param string|null $queue Delete only jobs from this queue
     * @return void
     */
    public function flush(?string $queue = null): void;

    /**
     * Prune failed jobs older than the given date
     * 
     * @param DateTimeInterface $before Prune jobs failed before this date
     * @return int Number of jobs pruned
     */
    public function prune(DateTimeInterface $before): int;

    /**
     * Get failed job statistics
     * 
     * @return array{
     *     total: int,
     *     by_queue: array<string, int>,
     *     by_connection: array<string, int>,
     *     by_exception: array<string, int>,
     *     recent_failures: array<FailedJob>
     * }
     */
    public function statistics(): array;

    /**
     * Search failed jobs
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @param int $limit Maximum results
     * @return array<FailedJob>
     */
    public function search(array $criteria, int $limit = 50): array;

    /**
     * Get failed jobs by queue
     * 
     * @param string $queue Queue name
     * @param int $limit Maximum results
     * @param int $offset Offset for pagination
     * @return array<FailedJob>
     */
    public function byQueue(string $queue, int $limit = 50, int $offset = 0): array;

    /**
     * Get failed jobs by exception type
     * 
     * @param string $exceptionClass Exception class name
     * @param int $limit Maximum results
     * @return array<FailedJob>
     */
    public function byException(string $exceptionClass, int $limit = 50): array;

    /**
     * Count failed jobs
     * 
     * @param string|null $queue Count only jobs from this queue
     * @return int
     */
    public function count(?string $queue = null): int;

    /**
     * Check if a job exists in failed jobs
     * 
     * @param string|int $id Failed job ID
     * @return bool
     */
    public function exists($id): bool;

    /**
     * Get failure rate statistics
     * 
     * @param DateTimeInterface|null $since Calculate rate since this date
     * @return array{
     *     rate: float,
     *     total_jobs: int,
     *     failed_jobs: int,
     *     period_hours: int
     * }
     */
    public function failureRate(?DateTimeInterface $since = null): array;
}

/**
 * Failed Job Entity
 * 
 * Represents a failed job record
 */
class FailedJob
{
    public function __construct(
        public string|int $id,
        public string $connection,
        public string $queue,
        public string $payload,
        public string $exception,
        public DateTimeInterface $failedAt,
        public ?array $metadata = null
    ) {
    }

    /**
     * Get decoded payload
     * 
     * @return array<string, mixed>
     */
    public function getDecodedPayload(): array
    {
        return json_decode($this->payload, true) ?? [];
    }

    /**
     * Get job class name
     * 
     * @return string|null
     */
    public function getJobClass(): ?string
    {
        $payload = $this->getDecodedPayload();
        return $payload['job'] ?? null;
    }

    /**
     * Get job display name
     * 
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->getJobClass() ?? 'Unknown Job';
    }
}