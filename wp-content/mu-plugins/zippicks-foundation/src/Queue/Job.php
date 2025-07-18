<?php
/**
 * Base Job Class
 * 
 * Foundation for all queueable jobs in the ZipPicks platform.
 * Provides common functionality for job processing, retries, and monitoring.
 * 
 * @package ZipPicks\Foundation\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue;

use ZipPicks\Foundation\Contracts\Queue\JobInterface;
use ZipPicks\Foundation\Contracts\Queue\QueueManagerInterface;
use Throwable;

/**
 * Abstract Job Class
 * 
 * Base implementation for queueable jobs
 */
abstract class Job implements JobInterface
{
    /**
     * The job ID
     */
    protected ?string $jobId = null;
    
    /**
     * Number of attempts
     */
    protected int $attempts = 0;
    
    /**
     * The queue name
     */
    protected string $queue = 'default';
    
    /**
     * The connection name
     */
    protected ?string $connection = null;
    
    /**
     * Job metadata
     */
    protected array $metadata = [];
    
    /**
     * Raw job payload
     */
    protected string $rawPayload = '';
    
    /**
     * Job batch ID
     */
    protected ?string $batchId = null;
    
    /**
     * Job chain
     */
    protected ?array $chain = null;
    
    /**
     * Queue manager instance
     */
    protected ?QueueManagerInterface $queueManager = null;
    
    /**
     * {@inheritdoc}
     */
    abstract public function handle(): void;
    
    /**
     * {@inheritdoc}
     */
    public function failed(Throwable $exception): void
    {
        // Override in child classes to handle failures
        // By default, log the failure
        if ($logger = $this->getLogger()) {
            $logger->error('Job failed', [
                'job' => $this->displayName(),
                'job_id' => $this->getJobId(),
                'attempts' => $this->attempts(),
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function getJobId(): ?string
    {
        return $this->jobId;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setJobId(string $id): void
    {
        $this->jobId = $id;
    }
    
    /**
     * {@inheritdoc}
     */
    public function attempts(): int
    {
        return $this->attempts;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setAttempts(int $attempts): void
    {
        $this->attempts = $attempts;
    }
    
    /**
     * {@inheritdoc}
     */
    public function maxTries(): int
    {
        return 3;
    }
    
    /**
     * {@inheritdoc}
     */
    public function timeout(): ?int
    {
        return 60;
    }
    
    /**
     * {@inheritdoc}
     */
    public function retryAfter(): int
    {
        return 90;
    }
    
    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 5;
    }
    
    /**
     * {@inheritdoc}
     */
    public function queue(): ?string
    {
        return $this->queue;
    }
    
    /**
     * {@inheritdoc}
     */
    public function connection(): ?string
    {
        return $this->connection;
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay(): ?int
    {
        return null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function middleware(): array
    {
        return [];
    }
    
    /**
     * {@inheritdoc}
     */
    public function tags(): array
    {
        return [];
    }
    
    /**
     * {@inheritdoc}
     */
    public function shouldBeEncrypted(): bool
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }
    
    /**
     * {@inheritdoc}
     */
    public function uniqueId(): ?string
    {
        return null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function uniqueFor(): ?int
    {
        return null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function deleteWhenMissingModels(): bool
    {
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getRawPayload(): string
    {
        return $this->rawPayload;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setRawPayload(string $payload): void
    {
        $this->rawPayload = $payload;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getQueue(): string
    {
        return $this->queue;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setQueue(string $queue): void
    {
        $this->queue = $queue;
    }
    
    /**
     * {@inheritdoc}
     */
    public function batchId(): ?string
    {
        return $this->batchId;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPartOfBatch(): bool
    {
        return $this->batchId !== null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function chain(): ?array
    {
        return $this->chain;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPartOfChain(): bool
    {
        return $this->chain !== null && count($this->chain) > 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function displayName(): string
    {
        return static::class;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasExceededMaxAttempts(): bool
    {
        return $this->attempts() >= $this->maxTries();
    }
    
    /**
     * {@inheritdoc}
     */
    public function markAsFailed(): void
    {
        // This will be handled by the worker/queue system
        $this->metadata['failed_at'] = time();
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(): void
    {
        if ($this->queueManager && $this->jobId) {
            $this->queueManager
                ->connection($this->connection)
                ->delete($this->queue, $this->jobId);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function release(int $delay = 0): void
    {
        if ($this->queueManager) {
            $this->queueManager
                ->connection($this->connection)
                ->release($this->queue, $this, $delay);
        }
    }
    
    /**
     * Set the queue manager
     * 
     * @param QueueManagerInterface $manager Queue manager
     * @return void
     */
    public function setQueueManager(QueueManagerInterface $manager): void
    {
        $this->queueManager = $manager;
    }
    
    /**
     * Dispatch the job
     * 
     * @return mixed Job ID
     */
    public function dispatch()
    {
        if (!$this->queueManager) {
            $this->queueManager = app(QueueManagerInterface::class);
        }
        
        return $this->queueManager->push($this);
    }
    
    /**
     * Dispatch the job with delay
     * 
     * @param int $delay Delay in seconds
     * @return mixed Job ID
     */
    public function dispatchAfter(int $delay)
    {
        if (!$this->queueManager) {
            $this->queueManager = app(QueueManagerInterface::class);
        }
        
        return $this->queueManager->later($delay, $this);
    }
    
    /**
     * Dispatch the job on a specific queue
     * 
     * @param string $queue Queue name
     * @return mixed Job ID
     */
    public function dispatchOn(string $queue)
    {
        $this->queue = $queue;
        return $this->dispatch();
    }
    
    /**
     * Set the job batch ID
     * 
     * @param string $batchId Batch ID
     * @return self
     */
    public function withBatchId(string $batchId): self
    {
        $this->batchId = $batchId;
        return $this;
    }
    
    /**
     * Set the job chain
     * 
     * @param array<JobInterface> $chain Job chain
     * @return self
     */
    public function withChain(array $chain): self
    {
        $this->chain = $chain;
        return $this;
    }
    
    /**
     * Get logger instance
     * 
     * @return \ZipPicks\Foundation\Contracts\Logging\LoggerInterface|null
     */
    protected function getLogger()
    {
        return app('logger');
    }
    
    /**
     * Get cache instance
     * 
     * @return \ZipPicks\Foundation\Contracts\Cache\CacheInterface|null
     */
    protected function getCache()
    {
        return app('cache');
    }
    
    /**
     * Before job processing hook
     * 
     * @return void
     */
    public function beforeHandle(): void
    {
        // Override in child classes
    }
    
    /**
     * After job processing hook
     * 
     * @return void
     */
    public function afterHandle(): void
    {
        // Override in child classes
    }
    
    /**
     * Get exponential backoff delay
     * 
     * @param int $attempt Current attempt number
     * @return int Delay in seconds
     */
    protected function exponentialBackoff(int $attempt): int
    {
        return min(
            (int) (pow(2, $attempt - 1) * 10),
            3600 // Max 1 hour
        );
    }
}