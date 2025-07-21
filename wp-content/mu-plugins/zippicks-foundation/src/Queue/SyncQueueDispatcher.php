<?php
/**
 * Synchronous Queue Dispatcher
 * 
 * @package ZipPicks\Foundation\Queue
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue;

use ZipPicks\Foundation\Contracts\Queue\QueueableInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use Throwable;

/**
 * Synchronous queue dispatcher that executes jobs immediately
 */
class SyncQueueDispatcher
{
    /**
     * Logger instance
     * 
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger;

    /**
     * Job metadata storage for future async support
     * 
     * @var array<string, array<string, mixed>>
     */
    protected array $jobMetadata = [];

    /**
     * Job execution history
     * 
     * @var array<array<string, mixed>>
     */
    protected array $executionHistory = [];

    /**
     * Create a new sync queue dispatcher
     * 
     * @param LoggerInterface|null $logger Optional logger instance
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Dispatch a queueable job
     * 
     * @param QueueableInterface $job The job to dispatch
     * 
     * @return void
     * @throws Throwable If job execution fails and no logger is available
     */
    public function dispatch(QueueableInterface $job): void
    {
        $jobClass = get_class($job);
        $jobId = $this->generateJobId($job);
        $tags = $job->tags();
        $delay = $job->delay();

        // Store job metadata for future async support
        $this->jobMetadata[$jobId] = [
            'class' => $jobClass,
            'tags' => $tags,
            'delay' => $delay,
            'dispatched_at' => microtime(true),
            'status' => 'pending',
        ];

        // Log dispatch event
        $this->logDispatch($jobId, $jobClass, $tags, $delay);

        try {
            // Record start time
            $startTime = microtime(true);

            // Update status
            $this->jobMetadata[$jobId]['status'] = 'processing';
            $this->jobMetadata[$jobId]['started_at'] = $startTime;

            // Execute job immediately (sync behavior)
            $job->handle();

            // Record completion
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2); // milliseconds

            $this->jobMetadata[$jobId]['status'] = 'completed';
            $this->jobMetadata[$jobId]['completed_at'] = $endTime;
            $this->jobMetadata[$jobId]['duration_ms'] = $duration;

            // Add to execution history
            $this->executionHistory[] = [
                'job_id' => $jobId,
                'class' => $jobClass,
                'tags' => $tags,
                'duration_ms' => $duration,
                'completed_at' => $endTime,
            ];

            // Log success
            $this->logSuccess($jobId, $jobClass, $tags, $duration);

        } catch (Throwable $e) {
            // Update status
            $this->jobMetadata[$jobId]['status'] = 'failed';
            $this->jobMetadata[$jobId]['failed_at'] = microtime(true);
            $this->jobMetadata[$jobId]['error'] = $e->getMessage();

            // Log failure
            $this->logFailure($jobId, $jobClass, $tags, $e);

            // Re-throw if no logger available
            if (!$this->logger) {
                throw $e;
            }
        }
    }

    /**
     * Get metadata for a specific job
     * 
     * @param string $jobId The job ID
     * 
     * @return array<string, mixed>|null
     */
    public function getJobMetadata(string $jobId): ?array
    {
        return $this->jobMetadata[$jobId] ?? null;
    }

    /**
     * Get all job metadata
     * 
     * @return array<string, array<string, mixed>>
     */
    public function getAllJobMetadata(): array
    {
        return $this->jobMetadata;
    }

    /**
     * Get job execution history
     * 
     * @param int|null $limit Maximum number of records to return
     * 
     * @return array<array<string, mixed>>
     */
    public function getExecutionHistory(?int $limit = null): array
    {
        if ($limit === null) {
            return $this->executionHistory;
        }

        return array_slice($this->executionHistory, -$limit);
    }

    /**
     * Clear job metadata and history
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->jobMetadata = [];
        $this->executionHistory = [];
    }

    /**
     * Generate a unique job ID
     * 
     * @param QueueableInterface $job
     * 
     * @return string
     */
    protected function generateJobId(QueueableInterface $job): string
    {
        return sprintf(
            '%s_%s_%s',
            str_replace('\\', '_', get_class($job)),
            uniqid(),
            bin2hex(random_bytes(4))
        );
    }

    /**
     * Log job dispatch
     * 
     * @param string $jobId
     * @param string $jobClass
     * @param array<string> $tags
     * @param int|null $delay
     * 
     * @return void
     */
    protected function logDispatch(string $jobId, string $jobClass, array $tags, ?int $delay): void
    {
        if (!$this->logger) {
            return;
        }

        $context = [
            'job_id' => $jobId,
            'job_class' => $jobClass,
            'tags' => $tags,
        ];

        if ($delay !== null) {
            $context['delay_seconds'] = $delay;
        }

        $this->logger->channel('queue')->info(
            sprintf('Job dispatched: %s', $jobClass),
            $context
        );
    }

    /**
     * Log job success
     * 
     * @param string $jobId
     * @param string $jobClass
     * @param array<string> $tags
     * @param float $duration
     * 
     * @return void
     */
    protected function logSuccess(string $jobId, string $jobClass, array $tags, float $duration): void
    {
        if (!$this->logger) {
            return;
        }

        $this->logger->channel('queue')->info(
            sprintf('Job completed: %s', $jobClass),
            [
                'job_id' => $jobId,
                'job_class' => $jobClass,
                'tags' => $tags,
                'duration_ms' => $duration,
            ]
        );
    }

    /**
     * Log job failure
     * 
     * @param string $jobId
     * @param string $jobClass
     * @param array<string> $tags
     * @param Throwable $exception
     * 
     * @return void
     */
    protected function logFailure(string $jobId, string $jobClass, array $tags, Throwable $exception): void
    {
        if (!$this->logger) {
            return;
        }

        $this->logger->channel('queue')->error(
            sprintf('Job failed: %s', $jobClass),
            [
                'job_id' => $jobId,
                'job_class' => $jobClass,
                'tags' => $tags,
                'error' => $exception->getMessage(),
                'exception_class' => get_class($exception),
                'trace' => $exception->getTraceAsString(),
            ]
        );
    }

    /**
     * Set the logger instance
     * 
     * @param LoggerInterface $logger
     * 
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Get statistics about job execution
     * 
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $total = count($this->jobMetadata);
        $completed = 0;
        $failed = 0;
        $pending = 0;
        $processing = 0;
        $totalDuration = 0.0;

        foreach ($this->jobMetadata as $metadata) {
            switch ($metadata['status']) {
                case 'completed':
                    $completed++;
                    $totalDuration += $metadata['duration_ms'] ?? 0;
                    break;
                case 'failed':
                    $failed++;
                    break;
                case 'pending':
                    $pending++;
                    break;
                case 'processing':
                    $processing++;
                    break;
            }
        }

        return [
            'total_jobs' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'pending' => $pending,
            'processing' => $processing,
            'average_duration_ms' => $completed > 0 ? round($totalDuration / $completed, 2) : 0,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
        ];
    }
}