<?php
/**
 * Queueable Job Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Queue
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Queue;

/**
 * Interface for queueable jobs
 */
interface QueueableInterface
{
    /**
     * Execute the job
     * 
     * @return void
     */
    public function handle(): void;

    /**
     * Get tags associated with this job
     * 
     * @return array<string>
     */
    public function tags(): array;

    /**
     * Get the delay in seconds before the job should be processed
     * 
     * @return int|null Number of seconds to delay, or null for immediate processing
     */
    public function delay(): ?int;
}