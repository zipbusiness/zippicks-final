<?php
/**
 * Job Chain Interface
 * 
 * Enables sequential processing of dependent jobs where each
 * job's output can be passed to the next job in the chain.
 * 
 * @package ZipPicks\Foundation\Contracts\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Queue;

use Closure;

/**
 * Job Chain Interface
 * 
 * Contract for job chains
 */
interface JobChainInterface
{
    /**
     * Get the chain ID
     * 
     * @return string
     */
    public function id(): string;

    /**
     * Add a job to the chain
     * 
     * @param JobInterface $job Job to add
     * @return self
     */
    public function add(JobInterface $job): self;

    /**
     * Add multiple jobs to the chain
     * 
     * @param array<JobInterface> $jobs Jobs to add
     * @return self
     */
    public function addMany(array $jobs): self;

    /**
     * Set the connection for chain jobs
     * 
     * @param string $connection Connection name
     * @return self
     */
    public function onConnection(string $connection): self;

    /**
     * Set the queue for chain jobs
     * 
     * @param string $queue Queue name
     * @return self
     */
    public function onQueue(string $queue): self;

    /**
     * Set delay between jobs
     * 
     * @param int $seconds Delay in seconds
     * @return self
     */
    public function withDelay(int $seconds): self;

    /**
     * Register a callback for when the chain completes
     * 
     * @param Closure|JobInterface $callback Callback or job
     * @return self
     */
    public function then($callback): self;

    /**
     * Register a callback for when any job in the chain fails
     * 
     * @param Closure|JobInterface $callback Callback or job
     * @return self
     */
    public function catch($callback): self;

    /**
     * Dispatch the chain
     * 
     * @return void
     */
    public function dispatch(): void;

    /**
     * Get all jobs in the chain
     * 
     * @return array<JobInterface>
     */
    public function jobs(): array;

    /**
     * Get the current job being processed
     * 
     * @return JobInterface|null
     */
    public function currentJob(): ?JobInterface;

    /**
     * Get the next job in the chain
     * 
     * @return JobInterface|null
     */
    public function nextJob(): ?JobInterface;

    /**
     * Get chain progress
     * 
     * @return array{
     *     total: int,
     *     completed: int,
     *     failed: int,
     *     pending: int,
     *     progress: float
     * }
     */
    public function progress(): array;

    /**
     * Check if chain is complete
     * 
     * @return bool
     */
    public function isComplete(): bool;

    /**
     * Check if chain has failed
     * 
     * @return bool
     */
    public function hasFailed(): bool;

    /**
     * Cancel the chain
     * 
     * @return void
     */
    public function cancel(): void;

    /**
     * Get chain metadata
     * 
     * @return array<string, mixed>
     */
    public function metadata(): array;

    /**
     * Set chain metadata
     * 
     * @param array<string, mixed> $metadata
     * @return self
     */
    public function setMetadata(array $metadata): self;

    /**
     * Allow the chain to continue on failure
     * 
     * @return self
     */
    public function continueOnFailure(): self;

    /**
     * Set data to pass between chain jobs
     * 
     * @param array<string, mixed> $data Shared data
     * @return self
     */
    public function withSharedData(array $data): self;

    /**
     * Get shared data
     * 
     * @return array<string, mixed>
     */
    public function getSharedData(): array;
}