<?php
/**
 * Job Middleware Interface
 * 
 * Allows jobs to pass through middleware for cross-cutting concerns
 * like rate limiting, logging, and authentication.
 * 
 * @package ZipPicks\Foundation\Contracts\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Queue;

use Closure;

/**
 * Job Middleware Interface
 * 
 * Contract for job processing middleware
 */
interface JobMiddlewareInterface
{
    /**
     * Process the job through the middleware
     * 
     * @param JobInterface $job The job being processed
     * @param Closure $next The next middleware in the pipeline
     * @return mixed
     */
    public function handle(JobInterface $job, Closure $next);

    /**
     * Get middleware priority (lower numbers run first)
     * 
     * @return int
     */
    public function priority(): int;

    /**
     * Determine if middleware should run for this job
     * 
     * @param JobInterface $job The job to check
     * @return bool
     */
    public function shouldRun(JobInterface $job): bool;
}