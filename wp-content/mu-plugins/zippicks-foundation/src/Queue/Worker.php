<?php
/**
 * Queue Worker
 * 
 * Processes jobs from the queue with enterprise-grade reliability,
 * monitoring, and fault tolerance for the ZipPicks platform.
 * 
 * @package ZipPicks\Foundation\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue;

use ZipPicks\Foundation\Contracts\Queue\WorkerInterface;
use ZipPicks\Foundation\Contracts\Queue\WorkerOptions;
use ZipPicks\Foundation\Contracts\Queue\JobInterface;
use ZipPicks\Foundation\Contracts\Queue\QueueManagerInterface;
use ZipPicks\Foundation\Contracts\Queue\FailedJobProviderInterface;
use ZipPicks\Foundation\Contracts\Queue\QueueMonitorInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Core\PerformanceMonitor;
use Throwable;
use Exception;

/**
 * Queue Worker
 * 
 * Processes jobs from queues
 */
class Worker implements WorkerInterface
{
    /**
     * Queue manager
     */
    protected QueueManagerInterface $manager;
    
    /**
     * Failed job provider
     */
    protected FailedJobProviderInterface $failedJobProvider;
    
    /**
     * Queue monitor
     */
    protected ?QueueMonitorInterface $monitor;
    
    /**
     * Logger instance
     */
    protected ?LoggerInterface $logger;
    
    /**
     * Performance monitor
     */
    protected ?PerformanceMonitor $performanceMonitor;
    
    /**
     * Worker name
     */
    protected string $name;
    
    /**
     * Should quit flag
     */
    protected bool $shouldQuit = false;
    
    /**
     * Is paused flag
     */
    protected bool $paused = false;
    
    /**
     * Worker start time
     */
    protected float $startTime;
    
    /**
     * Jobs processed count
     */
    protected int $jobsProcessed = 0;
    
    /**
     * Jobs failed count
     */
    protected int $jobsFailed = 0;
    
    /**
     * Last job timestamp
     */
    protected ?int $lastJobAt = null;
    
    /**
     * Middleware pipeline
     */
    protected array $middleware = [];
    
    /**
     * Create a new worker instance
     * 
     * @param QueueManagerInterface $manager Queue manager
     * @param FailedJobProviderInterface $failedJobProvider Failed job provider
     * @param QueueMonitorInterface|null $monitor Queue monitor
     * @param LoggerInterface|null $logger Logger
     * @param PerformanceMonitor|null $performanceMonitor Performance monitor
     */
    public function __construct(
        QueueManagerInterface $manager,
        FailedJobProviderInterface $failedJobProvider,
        ?QueueMonitorInterface $monitor = null,
        ?LoggerInterface $logger = null,
        ?PerformanceMonitor $performanceMonitor = null
    ) {
        $this->manager = $manager;
        $this->failedJobProvider = $failedJobProvider;
        $this->monitor = $monitor;
        $this->logger = $logger;
        $this->performanceMonitor = $performanceMonitor;
        $this->name = 'worker_' . bin2hex(random_bytes(4));
        $this->startTime = microtime(true);
    }
    
    /**
     * {@inheritdoc}
     */
    public function daemon(string $connectionName, string $queue, WorkerOptions $options): void
    {
        if ($options->name) {
            $this->setName($options->name);
        }
        
        $this->registerSignalHandlers();
        $this->setMemoryLimit($options->memory);
        
        $this->logger?->info('Worker started', [
            'worker' => $this->name,
            'connection' => $connectionName,
            'queue' => $queue,
            'options' => (array) $options
        ]);
        
        $lastRestart = $this->getTimestampOfLastRestart();
        
        while (true) {
            // Check if we should quit
            if ($this->shouldQuit || $this->quitIfNecessary($options, $lastRestart)) {
                break;
            }
            
            // Process next job
            $this->runNextJob($connectionName, $queue, $options);
            
            // Check if we've hit limits
            if ($options->stopWhenEmpty && $this->isQueueEmpty($connectionName, $queue)) {
                break;
            }
            
            if ($options->maxJobs > 0 && $this->jobsProcessed >= $options->maxJobs) {
                break;
            }
            
            if ($options->maxTime > 0 && $this->getElapsedTime() >= $options->maxTime) {
                break;
            }
            
            if ($this->memoryExceeded($options->memory)) {
                $this->stop(12); // Out of memory
            }
        }
        
        $this->logger?->info('Worker stopping', [
            'worker' => $this->name,
            'jobs_processed' => $this->jobsProcessed,
            'jobs_failed' => $this->jobsFailed,
            'runtime' => $this->getElapsedTime()
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function runNextJob(string $connectionName, string $queue, WorkerOptions $options): void
    {
        try {
            $job = $this->getNextJob($connectionName, $queue);
            
            if (!$job) {
                $this->sleep($options->sleep);
                return;
            }
            
            $this->lastJobAt = time();
            
            $this->process($job, $connectionName, $options);
            
        } catch (Throwable $e) {
            $this->logger?->error('Worker error getting next job', [
                'worker' => $this->name,
                'error' => $e->getMessage()
            ]);
            
            $this->sleep($options->sleep);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function process(JobInterface $job, string $connectionName, WorkerOptions $options): void
    {
        try {
            // Record job processing started
            $this->monitor?->recordJobProcessing($job, $this->name);
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage();
            
            // Check if job has exceeded max attempts
            if ($job->hasExceededMaxAttempts()) {
                $this->markJobAsFailed($job, new Exception(
                    'Job has exceeded maximum attempts'
                ));
                return;
            }
            
            // Set up job timeout
            if ($options->timeout > 0 || $job->timeout() !== null) {
                $this->registerTimeoutHandler($job, $options);
            }
            
            // Process the job through middleware
            $this->runJobThroughMiddleware($job, function ($job) {
                $job->handle();
            });
            
            // Record success
            $runtime = microtime(true) - $startTime;
            $memoryUsage = memory_get_usage() - $startMemory;
            
            $this->monitor?->recordJobProcessed($job, $runtime, $memoryUsage);
            
            $this->jobsProcessed++;
            
            $this->logger?->info('Job processed', [
                'worker' => $this->name,
                'job' => $job->displayName(),
                'job_id' => $job->getJobId(),
                'runtime' => round($runtime * 1000, 2) . 'ms',
                'memory' => round($memoryUsage / 1024 / 1024, 2) . 'MB'
            ]);
            
            // Delete job from queue
            $job->delete();
            
        } catch (Throwable $e) {
            $this->handleJobException($job, $options, $e);
        } finally {
            // Clear timeout handler
            $this->clearTimeoutHandler();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function stop(int $status = 0): void
    {
        $this->shouldQuit = true;
        
        $this->logger?->info('Worker stop requested', [
            'worker' => $this->name,
            'status' => $status
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function kill(int $status = 0): void
    {
        $this->logger?->warning('Worker killed', [
            'worker' => $this->name,
            'status' => $status
        ]);
        
        exit($status);
    }
    
    /**
     * {@inheritdoc}
     */
    public function shouldQuit(): bool
    {
        return $this->shouldQuit;
    }
    
    /**
     * {@inheritdoc}
     */
    public function sleep($seconds): void
    {
        if ($seconds < 1) {
            usleep($seconds * 1000000);
        } else {
            sleep($seconds);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function setMemoryLimit(int $memoryLimit): void
    {
        ini_set('memory_limit', $memoryLimit . 'M');
    }
    
    /**
     * {@inheritdoc}
     */
    public function memoryExceeded(int $memoryLimit): bool
    {
        return $this->getMemoryUsage() >= $memoryLimit;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getMemoryUsage(): int
    {
        return (int) (memory_get_usage(true) / 1024 / 1024);
    }
    
    /**
     * {@inheritdoc}
     */
    public function registerSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }
        
        pcntl_async_signals(true);
        
        pcntl_signal(SIGTERM, function () {
            $this->shouldQuit = true;
        });
        
        pcntl_signal(SIGUSR2, function () {
            $this->paused = true;
        });
        
        pcntl_signal(SIGCONT, function () {
            $this->paused = false;
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function handleJobTimeout(JobInterface $job, WorkerOptions $options): void
    {
        $this->markJobAsFailed($job, new Exception(
            sprintf('Job timed out after %s seconds', $options->timeout)
        ));
        
        $this->kill(1);
    }
    
    /**
     * {@inheritdoc}
     */
    public function markJobAsFailed(JobInterface $job, Throwable $exception): void
    {
        $job->markAsFailed();
        $job->failed($exception);
        
        // Log to failed jobs
        $this->failedJobProvider->log(
            $job->connection() ?? 'default',
            $job->getQueue(),
            $job->getRawPayload(),
            $exception
        );
        
        // Record failure
        $runtime = microtime(true) - $this->startTime;
        $this->monitor?->recordJobFailed($job, $exception, $runtime);
        
        $this->jobsFailed++;
        
        $this->logger?->error('Job failed', [
            'worker' => $this->name,
            'job' => $job->displayName(),
            'job_id' => $job->getJobId(),
            'attempts' => $job->attempts(),
            'error' => $exception->getMessage()
        ]);
        
        // Handle batch/chain failures
        $this->handleBatchChainFailure($job);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getStatistics(): array
    {
        return [
            'processed' => $this->jobsProcessed,
            'failed' => $this->jobsFailed,
            'memory_usage' => $this->getMemoryUsage(),
            'uptime' => (int) $this->getElapsedTime(),
            'last_job_at' => $this->lastJobAt,
            'status' => $this->getStatus()
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function pause(): void
    {
        $this->paused = true;
        
        $this->logger?->info('Worker paused', [
            'worker' => $this->name
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function resume(): void
    {
        $this->paused = false;
        
        $this->logger?->info('Worker resumed', [
            'worker' => $this->name
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPaused(): bool
    {
        return $this->paused;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Get next job from queue
     * 
     * @param string $connectionName Connection name
     * @param string $queue Queue name
     * @return JobInterface|null
     */
    protected function getNextJob(string $connectionName, string $queue): ?JobInterface
    {
        $connection = $this->manager->connection($connectionName);
        
        return $connection->pop($queue);
    }
    
    /**
     * Check if queue is empty
     * 
     * @param string $connectionName Connection name
     * @param string $queue Queue name
     * @return bool
     */
    protected function isQueueEmpty(string $connectionName, string $queue): bool
    {
        $connection = $this->manager->connection($connectionName);
        
        return $connection->size($queue) === 0;
    }
    
    /**
     * Get timestamp of last restart
     * 
     * @return int
     */
    protected function getTimestampOfLastRestart(): int
    {
        // This would check a cache key set by deployment
        return 0;
    }
    
    /**
     * Check if should quit
     * 
     * @param WorkerOptions $options Worker options
     * @param int $lastRestart Last restart timestamp
     * @return bool
     */
    protected function quitIfNecessary(WorkerOptions $options, int $lastRestart): bool
    {
        // Check for restart signal
        if ($this->getTimestampOfLastRestart() != $lastRestart) {
            return true;
        }
        
        // Check if paused
        while ($this->paused) {
            $this->sleep(1);
        }
        
        return false;
    }
    
    /**
     * Register timeout handler
     * 
     * @param JobInterface $job The job
     * @param WorkerOptions $options Worker options
     * @return void
     */
    protected function registerTimeoutHandler(JobInterface $job, WorkerOptions $options): void
    {
        $timeout = $job->timeout() ?? $options->timeout;
        
        if (extension_loaded('pcntl') && $timeout > 0) {
            pcntl_signal(SIGALRM, function () use ($job, $options) {
                $this->handleJobTimeout($job, $options);
            });
            
            pcntl_alarm($timeout);
        }
    }
    
    /**
     * Clear timeout handler
     * 
     * @return void
     */
    protected function clearTimeoutHandler(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_alarm(0);
        }
    }
    
    /**
     * Run job through middleware
     * 
     * @param JobInterface $job The job
     * @param callable $callback The callback
     * @return mixed
     */
    protected function runJobThroughMiddleware(JobInterface $job, callable $callback)
    {
        $pipeline = array_reduce(
            array_reverse($job->middleware()),
            $this->createMiddlewareCallback(),
            $callback
        );
        
        return $pipeline($job);
    }
    
    /**
     * Create middleware callback
     * 
     * @return callable
     */
    protected function createMiddlewareCallback(): callable
    {
        return function ($stack, $middleware) {
            return function ($job) use ($stack, $middleware) {
                if (is_string($middleware)) {
                    $middleware = $this->container->get($middleware);
                }
                
                if ($middleware instanceof JobMiddlewareInterface) {
                    return $middleware->handle($job, $stack);
                }
                
                return $stack($job);
            };
        };
    }
    
    /**
     * Handle job exception
     * 
     * @param JobInterface $job The job
     * @param WorkerOptions $options Worker options
     * @param Throwable $e The exception
     * @return void
     */
    protected function handleJobException(JobInterface $job, WorkerOptions $options, Throwable $e): void
    {
        // Check if should retry
        if (!$job->hasExceededMaxAttempts()) {
            $delay = $this->calculateRetryDelay($job, $e);
            
            $job->release($delay);
            
            $this->logger?->warning('Job released for retry', [
                'worker' => $this->name,
                'job' => $job->displayName(),
                'job_id' => $job->getJobId(),
                'attempts' => $job->attempts(),
                'delay' => $delay,
                'error' => $e->getMessage()
            ]);
        } else {
            $this->markJobAsFailed($job, $e);
        }
    }
    
    /**
     * Calculate retry delay
     * 
     * @param JobInterface $job The job
     * @param Throwable $e The exception
     * @return int Delay in seconds
     */
    protected function calculateRetryDelay(JobInterface $job, Throwable $e): int
    {
        // Use job's retry after if specified
        $baseDelay = $job->retryAfter();
        
        // Apply exponential backoff if configured
        if (method_exists($job, 'exponentialBackoff')) {
            return $job->exponentialBackoff($job->attempts());
        }
        
        return $baseDelay;
    }
    
    /**
     * Handle batch/chain failure
     * 
     * @param JobInterface $job Failed job
     * @return void
     */
    protected function handleBatchChainFailure(JobInterface $job): void
    {
        $metadata = $job->metadata();
        
        // Handle batch failure
        if (isset($metadata['batch_id'])) {
            $batch = new JobBatch($this->manager, [], $this->logger);
            $batch->markJobFailed($job->getJobId());
        }
        
        // Handle chain failure
        if (isset($metadata['chain_id']) && isset($metadata['chain_position'])) {
            $chain = new JobChain($this->manager, [], $this->logger);
            $chain->markFailed((int) $metadata['chain_position']);
        }
    }
    
    /**
     * Get elapsed time
     * 
     * @return float Seconds
     */
    protected function getElapsedTime(): float
    {
        return microtime(true) - $this->startTime;
    }
    
    /**
     * Get worker status
     * 
     * @return string
     */
    protected function getStatus(): string
    {
        if ($this->shouldQuit) {
            return 'stopping';
        }
        
        if ($this->paused) {
            return 'paused';
        }
        
        return 'running';
    }
}