<?php
/**
 * Job Batch Interface
 * 
 * Enables processing groups of related jobs together with
 * completion callbacks and progress tracking.
 * 
 * @package ZipPicks\Foundation\Contracts\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Queue;

use Closure;
use DateTimeInterface;

/**
 * Job Batch Interface
 * 
 * Contract for job batches
 */
interface JobBatchInterface
{
    /**
     * Get the batch ID
     * 
     * @return string
     */
    public function id(): string;

    /**
     * Get the batch name
     * 
     * @return string|null
     */
    public function name(): ?string;

    /**
     * Set the batch name
     * 
     * @param string $name Batch name
     * @return self
     */
    public function setName(string $name): self;

    /**
     * Add jobs to the batch
     * 
     * @param array<JobInterface> $jobs Jobs to add
     * @return self
     */
    public function add(array $jobs): self;

    /**
     * Dispatch the batch
     * 
     * @return void
     */
    public function dispatch(): void;

    /**
     * Register a callback for when all jobs finish successfully
     * 
     * @param Closure|JobInterface $callback Callback or job
     * @return self
     */
    public function then($callback): self;

    /**
     * Register a callback for when the first job fails
     * 
     * @param Closure|JobInterface $callback Callback or job
     * @return self
     */
    public function catch($callback): self;

    /**
     * Register a callback for when the batch is finished
     * 
     * @param Closure|JobInterface $callback Callback or job
     * @return self
     */
    public function finally($callback): self;

    /**
     * Allow failures without cancelling the batch
     * 
     * @return self
     */
    public function allowFailures(): self;

    /**
     * Set the connection for batch jobs
     * 
     * @param string $connection Connection name
     * @return self
     */
    public function onConnection(string $connection): self;

    /**
     * Set the queue for batch jobs
     * 
     * @param string $queue Queue name
     * @return self
     */
    public function onQueue(string $queue): self;

    /**
     * Get total number of jobs in the batch
     * 
     * @return int
     */
    public function totalJobs(): int;

    /**
     * Get number of pending jobs
     * 
     * @return int
     */
    public function pendingJobs(): int;

    /**
     * Get number of failed jobs
     * 
     * @return int
     */
    public function failedJobs(): int;

    /**
     * Get batch progress percentage
     * 
     * @return float
     */
    public function progress(): float;

    /**
     * Check if batch is finished
     * 
     * @return bool
     */
    public function finished(): bool;

    /**
     * Check if batch has failures
     * 
     * @return bool
     */
    public function hasFailed(): bool;

    /**
     * Check if batch was cancelled
     * 
     * @return bool
     */
    public function cancelled(): bool;

    /**
     * Cancel the batch
     * 
     * @return void
     */
    public function cancel(): void;

    /**
     * Delete the batch
     * 
     * @return void
     */
    public function delete(): void;

    /**
     * Get batch creation time
     * 
     * @return DateTimeInterface
     */
    public function createdAt(): DateTimeInterface;

    /**
     * Get batch finish time
     * 
     * @return DateTimeInterface|null
     */
    public function finishedAt(): ?DateTimeInterface;

    /**
     * Get batch metadata
     * 
     * @return array<string, mixed>
     */
    public function metadata(): array;

    /**
     * Set batch metadata
     * 
     * @param array<string, mixed> $metadata
     * @return self
     */
    public function setMetadata(array $metadata): self;

    /**
     * Get failed job IDs
     * 
     * @return array<string>
     */
    public function failedJobIds(): array;

    /**
     * Get batch options
     * 
     * @return array<string, mixed>
     */
    public function options(): array;
}