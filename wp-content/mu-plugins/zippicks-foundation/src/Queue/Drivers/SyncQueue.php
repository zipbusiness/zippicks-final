<?php
/**
 * Sync Queue Driver
 * 
 * Executes jobs synchronously for development and testing.
 * Jobs are processed immediately without persistence.
 * 
 * @package ZipPicks\Foundation\Queue\Drivers
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue\Drivers;

use ZipPicks\Foundation\Contracts\Queue\QueueDriverInterface;
use ZipPicks\Foundation\Contracts\Queue\JobInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Queue\Job;
use DateTimeInterface;
use DateInterval;
use Throwable;

/**
 * Sync Queue Driver
 * 
 * Processes jobs immediately without queueing
 */
class SyncQueue implements QueueDriverInterface
{
    /**
     * Logger instance
     */
    protected ?LoggerInterface $logger;
    
    /**
     * Job creator callback
     */
    protected $jobCreator;
    
    /**
     * Connection name
     */
    protected string $connectionName = 'sync';
    
    /**
     * Processed jobs for testing
     */
    protected array $processedJobs = [];
    
    /**
     * Create a new sync queue instance
     * 
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }
    
    /**
     * {@inheritdoc}
     */
    public function size(?string $queue = null): int
    {
        return 0; // Sync queue is always empty
    }
    
    /**
     * {@inheritdoc}
     */
    public function push($job, $data = '', ?string $queue = null)
    {
        return $this->processJob($job, $data);
    }
    
    /**
     * {@inheritdoc}
     */
    public function pushRaw(string $payload, ?string $queue = null, array $options = [])
    {
        $decoded = json_decode($payload, true);
        
        if (isset($decoded['job']) && isset($decoded['data'])) {
            return $this->processJob($decoded['job'], $decoded['data']);
        }
        
        throw new \InvalidArgumentException('Invalid payload format');
    }
    
    /**
     * {@inheritdoc}
     */
    public function later($delay, $job, $data = '', ?string $queue = null)
    {
        // In sync mode, delay is ignored and job is processed immediately
        $this->logger?->debug('Sync queue ignoring delay, processing immediately', [
            'delay' => $delay,
            'job' => is_object($job) ? get_class($job) : $job
        ]);
        
        return $this->push($job, $data, $queue);
    }
    
    /**
     * {@inheritdoc}
     */
    public function laterRaw($delay, string $payload, ?string $queue = null, int $attempts = 0)
    {
        return $this->pushRaw($payload, $queue, ['attempts' => $attempts]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function bulk(array $jobs, $data = '', ?string $queue = null): void
    {
        foreach ($jobs as $job) {
            $this->push($job, $data, $queue);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function pop(?string $queue = null, int $timeout = 0): ?JobInterface
    {
        return null; // Sync queue doesn't support popping
    }
    
    /**
     * {@inheritdoc}
     */
    public function release(string $queue, JobInterface $job, int $delay = 0)
    {
        // In sync mode, release means re-process immediately
        return $this->processJobInstance($job);
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(string $queue, string $id): void
    {
        // No-op for sync queue
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear(string $queue): int
    {
        return 0; // Sync queue is always empty
    }
    
    /**
     * {@inheritdoc}
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setJobCreator(?callable $callback): void
    {
        $this->jobCreator = $callback;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getMetadata(?string $queue = null): array
    {
        return [
            'driver' => 'sync',
            'connection' => $this->connectionName,
            'queue' => $queue ?? 'default',
            'ready' => 0,
            'reserved' => 0,
            'delayed' => 0,
            'total' => 0
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function supports(string $feature): bool
    {
        $supported = [
            'priority' => false,
            'delay' => false, // Delay is ignored
            'batch' => true,
            'transaction' => false,
            'metadata' => false
        ];
        
        return $supported[$feature] ?? false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getJobsByStatus(string $status, ?string $queue = null, int $limit = 100): array
    {
        return []; // Sync queue doesn't store jobs
    }
    
    /**
     * {@inheritdoc}
     */
    public function migrate(string $from, string $to, int $limit = 1000): int
    {
        return 0; // No migration for sync queue
    }
    
    /**
     * {@inheritdoc}
     */
    public function health(): array
    {
        return [
            'healthy' => true,
            'message' => 'Sync queue is operational',
            'latency' => 0.0,
            'last_error' => null
        ];
    }
    
    /**
     * Process a job
     * 
     * @param mixed $job Job instance or class name
     * @param mixed $data Job data
     * @return string Job ID
     */
    protected function processJob($job, $data = ''): string
    {
        $jobId = $this->generateJobId();
        
        try {
            $instance = $this->resolveJob($job, $data);
            
            if ($instance instanceof JobInterface) {
                $instance->setJobId($jobId);
                $this->processJobInstance($instance);
            }
            
            return $jobId;
        } catch (Throwable $e) {
            $this->handleJobFailure($e, $job);
            throw $e;
        }
    }
    
    /**
     * Process a job instance
     * 
     * @param JobInterface $job Job instance
     * @return string Job ID
     */
    protected function processJobInstance(JobInterface $job): string
    {
        $jobId = $job->getJobId() ?? $this->generateJobId();
        $job->setJobId($jobId);
        
        $this->logger?->debug('Processing sync job', [
            'job_id' => $jobId,
            'job_class' => get_class($job),
            'attempts' => $job->attempts()
        ]);
        
        $startTime = microtime(true);
        
        try {
            // Call before hook if it's our base Job class
            if (method_exists($job, 'beforeHandle')) {
                $job->beforeHandle();
            }
            
            // Execute the job
            $job->handle();
            
            // Call after hook if it's our base Job class
            if (method_exists($job, 'afterHandle')) {
                $job->afterHandle();
            }
            
            $runtime = microtime(true) - $startTime;
            
            $this->logger?->info('Sync job completed', [
                'job_id' => $jobId,
                'job_class' => get_class($job),
                'runtime' => round($runtime * 1000, 2) . 'ms'
            ]);
            
            // Store for testing purposes
            $this->processedJobs[] = [
                'id' => $jobId,
                'class' => get_class($job),
                'runtime' => $runtime,
                'status' => 'completed'
            ];
            
        } catch (Throwable $e) {
            $runtime = microtime(true) - $startTime;
            
            $this->logger?->error('Sync job failed', [
                'job_id' => $jobId,
                'job_class' => get_class($job),
                'runtime' => round($runtime * 1000, 2) . 'ms',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Call failed method on job
            $job->failed($e);
            
            // Store failure for testing
            $this->processedJobs[] = [
                'id' => $jobId,
                'class' => get_class($job),
                'runtime' => $runtime,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
            
            throw $e;
        }
        
        return $jobId;
    }
    
    /**
     * Resolve job instance
     * 
     * @param mixed $job Job instance or class name
     * @param mixed $data Job data
     * @return JobInterface
     */
    protected function resolveJob($job, $data): JobInterface
    {
        if ($job instanceof JobInterface) {
            return $job;
        }
        
        if ($this->jobCreator) {
            $payload = [
                'job' => $job,
                'data' => $data,
                'id' => $this->generateJobId(),
                'attempts' => 0
            ];
            
            return call_user_func($this->jobCreator, $payload);
        }
        
        if (is_string($job) && class_exists($job)) {
            $instance = new $job($data);
            
            if ($instance instanceof JobInterface) {
                return $instance;
            }
        }
        
        throw new \InvalidArgumentException('Invalid job type provided');
    }
    
    /**
     * Handle job failure
     * 
     * @param Throwable $exception Exception that occurred
     * @param mixed $job Job that failed
     * @return void
     */
    protected function handleJobFailure(Throwable $exception, $job): void
    {
        $jobClass = is_object($job) ? get_class($job) : (string) $job;
        
        $this->logger?->error('Sync job processing failed', [
            'job_class' => $jobClass,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
    
    /**
     * Generate a unique job ID
     * 
     * @return string
     */
    protected function generateJobId(): string
    {
        return 'sync_' . bin2hex(random_bytes(8));
    }
    
    /**
     * Get processed jobs (for testing)
     * 
     * @return array
     */
    public function getProcessedJobs(): array
    {
        return $this->processedJobs;
    }
    
    /**
     * Clear processed jobs (for testing)
     * 
     * @return void
     */
    public function clearProcessedJobs(): void
    {
        $this->processedJobs = [];
    }
}