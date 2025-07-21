<?php
/**
 * Job Interface
 * 
 * Defines the contract for all jobs in the ZipPicks queue system.
 * Jobs are the units of work that power our taste graph calculations,
 * email campaigns, and data processing pipelines.
 * 
 * @package ZipPicks\Foundation\Contracts\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Queue;

use Throwable;

/**
 * Job Interface
 * 
 * Contract for queueable jobs with enterprise features
 */
interface JobInterface
{
    /**
     * Execute the job
     * 
     * This is where the actual work happens. For ZipPicks, this could be:
     * - Calculating taste graph connections
     * - Sending personalized emails
     * - Processing ML model updates
     * - Syncing with external APIs
     * 
     * @return void
     */
    public function handle(): void;

    /**
     * Handle a job failure
     * 
     * @param Throwable $exception The exception that caused the failure
     * @return void
     */
    public function failed(Throwable $exception): void;

    /**
     * Get the job identifier
     * 
     * @return string|null
     */
    public function getJobId(): ?string;

    /**
     * Set the job identifier
     * 
     * @param string $id Job ID
     * @return void
     */
    public function setJobId(string $id): void;

    /**
     * Get the number of times the job has been attempted
     * 
     * @return int
     */
    public function attempts(): int;

    /**
     * Set the number of attempts
     * 
     * @param int $attempts Number of attempts
     * @return void
     */
    public function setAttempts(int $attempts): void;

    /**
     * Get the maximum number of attempts
     * 
     * @return int
     */
    public function maxTries(): int;

    /**
     * Get the number of seconds the job can run
     * 
     * @return int|null Null for no timeout
     */
    public function timeout(): ?int;

    /**
     * Get the number of seconds to wait before retrying
     * 
     * @return int
     */
    public function retryAfter(): int;

    /**
     * Get the job's priority (0-9, 0 being highest)
     * 
     * @return int
     */
    public function priority(): int;

    /**
     * Get the queue the job should be dispatched to
     * 
     * @return string|null
     */
    public function queue(): ?string;

    /**
     * Get the connection the job should be dispatched to
     * 
     * @return string|null
     */
    public function connection(): ?string;

    /**
     * Get the delay before the job should be made available
     * 
     * @return int|null Delay in seconds
     */
    public function delay(): ?int;

    /**
     * Get the middleware the job should pass through
     * 
     * @return array<object|string>
     */
    public function middleware(): array;

    /**
     * Get the tags that should be assigned to the job
     * 
     * Used for monitoring, filtering, and grouping jobs
     * 
     * @return array<string>
     */
    public function tags(): array;

    /**
     * Determine if the job should be encrypted
     * 
     * @return bool
     */
    public function shouldBeEncrypted(): bool;

    /**
     * Get the job's metadata
     * 
     * Can include user_id, business_id, request_id, etc.
     * 
     * @return array<string, mixed>
     */
    public function metadata(): array;

    /**
     * Set job metadata
     * 
     * @param array<string, mixed> $metadata
     * @return void
     */
    public function setMetadata(array $metadata): void;

    /**
     * Get the unique identifier for the job
     * 
     * Used for preventing duplicate jobs
     * 
     * @return string|null
     */
    public function uniqueId(): ?string;

    /**
     * Get the number of seconds a job should remain unique
     * 
     * @return int|null
     */
    public function uniqueFor(): ?int;

    /**
     * Determine if the job should be deleted when all retries fail
     * 
     * @return bool
     */
    public function deleteWhenMissingModels(): bool;

    /**
     * Get the raw job payload
     * 
     * @return string
     */
    public function getRawPayload(): string;

    /**
     * Set the raw job payload
     * 
     * @param string $payload
     * @return void
     */
    public function setRawPayload(string $payload): void;

    /**
     * Get the name of the queue the job belongs to
     * 
     * @return string
     */
    public function getQueue(): string;

    /**
     * Set the name of the queue the job belongs to
     * 
     * @param string $queue
     * @return void
     */
    public function setQueue(string $queue): void;

    /**
     * Get the job batch ID if part of a batch
     * 
     * @return string|null
     */
    public function batchId(): ?string;

    /**
     * Check if the job is part of a batch
     * 
     * @return bool
     */
    public function isPartOfBatch(): bool;

    /**
     * Get the job chain if part of a chain
     * 
     * @return array<JobInterface>|null
     */
    public function chain(): ?array;

    /**
     * Check if the job is part of a chain
     * 
     * @return bool
     */
    public function isPartOfChain(): bool;

    /**
     * Get job display name for monitoring
     * 
     * @return string
     */
    public function displayName(): string;

    /**
     * Determine if the job has exceeded the maximum attempts
     * 
     * @return bool
     */
    public function hasExceededMaxAttempts(): bool;

    /**
     * Mark the job as failed and perform cleanup
     * 
     * @return void
     */
    public function markAsFailed(): void;

    /**
     * Delete the job from the queue
     * 
     * @return void
     */
    public function delete(): void;

    /**
     * Release the job back to the queue
     * 
     * @param int $delay Delay in seconds
     * @return void
     */
    public function release(int $delay = 0): void;
}