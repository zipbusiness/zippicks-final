<?php
/**
 * Job Batch Implementation
 * 
 * Enables processing groups of related jobs with progress tracking
 * and completion callbacks for the ZipPicks platform.
 * 
 * @package ZipPicks\Foundation\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue;

use ZipPicks\Foundation\Contracts\Queue\JobBatchInterface;
use ZipPicks\Foundation\Contracts\Queue\JobInterface;
use ZipPicks\Foundation\Contracts\Queue\QueueManagerInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use Closure;
use DateTimeInterface;
use DateTime;

/**
 * Job Batch
 * 
 * Groups related jobs for coordinated processing
 */
class JobBatch implements JobBatchInterface
{
    /**
     * Batch ID
     */
    protected string $id;
    
    /**
     * Batch name
     */
    protected ?string $name = null;
    
    /**
     * Jobs in the batch
     */
    protected array $jobs = [];
    
    /**
     * Queue manager
     */
    protected QueueManagerInterface $queueManager;
    
    /**
     * Logger instance
     */
    protected ?LoggerInterface $logger;
    
    /**
     * Batch options
     */
    protected array $options = [
        'connection' => null,
        'queue' => null,
        'allowFailures' => false
    ];
    
    /**
     * Success callbacks
     */
    protected array $thenCallbacks = [];
    
    /**
     * Failure callbacks
     */
    protected array $catchCallbacks = [];
    
    /**
     * Finally callbacks
     */
    protected array $finallyCallbacks = [];
    
    /**
     * Batch metadata
     */
    protected array $metadata = [];
    
    /**
     * Batch repository
     */
    protected ?BatchRepository $repository = null;
    
    /**
     * Create a new job batch
     * 
     * @param QueueManagerInterface $queueManager Queue manager
     * @param array<JobInterface> $jobs Initial jobs
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(
        QueueManagerInterface $queueManager,
        array $jobs = [],
        ?LoggerInterface $logger = null
    ) {
        $this->queueManager = $queueManager;
        $this->logger = $logger;
        $this->id = $this->generateBatchId();
        $this->jobs = $jobs;
    }
    
    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return $this->id;
    }
    
    /**
     * {@inheritdoc}
     */
    public function name(): ?string
    {
        return $this->name;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function add(array $jobs): self
    {
        $this->jobs = array_merge($this->jobs, $jobs);
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function dispatch(): void
    {
        if (empty($this->jobs)) {
            return;
        }
        
        // Store batch in repository
        $this->storeBatch();
        
        $this->logger?->info('Dispatching job batch', [
            'batch_id' => $this->id,
            'batch_name' => $this->name,
            'total_jobs' => count($this->jobs)
        ]);
        
        // Prepare jobs with batch ID
        foreach ($this->jobs as $job) {
            if (method_exists($job, 'withBatchId')) {
                $job->withBatchId($this->id);
            }
            
            // Add batch callbacks as metadata
            if (method_exists($job, 'setMetadata')) {
                $metadata = $job->metadata();
                $metadata['batch_id'] = $this->id;
                $job->setMetadata($metadata);
            }
            
            // Dispatch the job
            $connection = $this->options['connection'] ?? $job->connection();
            $queue = $this->options['queue'] ?? $job->queue();
            
            $this->queueManager
                ->connection($connection)
                ->push($job, '', $queue);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function then($callback): self
    {
        $this->thenCallbacks[] = $callback;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function catch($callback): self
    {
        $this->catchCallbacks[] = $callback;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function finally($callback): self
    {
        $this->finallyCallbacks[] = $callback;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function allowFailures(): self
    {
        $this->options['allowFailures'] = true;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function onConnection(string $connection): self
    {
        $this->options['connection'] = $connection;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function onQueue(string $queue): self
    {
        $this->options['queue'] = $queue;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function totalJobs(): int
    {
        return $this->getRepository()->getTotalJobs($this->id);
    }
    
    /**
     * {@inheritdoc}
     */
    public function pendingJobs(): int
    {
        return $this->getRepository()->getPendingJobs($this->id);
    }
    
    /**
     * {@inheritdoc}
     */
    public function failedJobs(): int
    {
        return $this->getRepository()->getFailedJobs($this->id);
    }
    
    /**
     * {@inheritdoc}
     */
    public function progress(): float
    {
        $total = $this->totalJobs();
        if ($total === 0) {
            return 100.0;
        }
        
        $completed = $total - $this->pendingJobs();
        return round(($completed / $total) * 100, 2);
    }
    
    /**
     * {@inheritdoc}
     */
    public function finished(): bool
    {
        return $this->pendingJobs() === 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasFailed(): bool
    {
        return $this->failedJobs() > 0 && !$this->options['allowFailures'];
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelled(): bool
    {
        return $this->getRepository()->isCancelled($this->id);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(): void
    {
        $this->getRepository()->cancel($this->id);
        
        $this->logger?->info('Batch cancelled', [
            'batch_id' => $this->id,
            'batch_name' => $this->name
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(): void
    {
        $this->getRepository()->delete($this->id);
        
        $this->logger?->info('Batch deleted', [
            'batch_id' => $this->id,
            'batch_name' => $this->name
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function createdAt(): DateTimeInterface
    {
        $timestamp = $this->getRepository()->getCreatedAt($this->id);
        return new DateTime('@' . $timestamp);
    }
    
    /**
     * {@inheritdoc}
     */
    public function finishedAt(): ?DateTimeInterface
    {
        $timestamp = $this->getRepository()->getFinishedAt($this->id);
        return $timestamp ? new DateTime('@' . $timestamp) : null;
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
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function failedJobIds(): array
    {
        return $this->getRepository()->getFailedJobIds($this->id);
    }
    
    /**
     * {@inheritdoc}
     */
    public function options(): array
    {
        return $this->options;
    }
    
    /**
     * Generate a unique batch ID
     * 
     * @return string
     */
    protected function generateBatchId(): string
    {
        return 'batch_' . bin2hex(random_bytes(16));
    }
    
    /**
     * Store batch in repository
     * 
     * @return void
     */
    protected function storeBatch(): void
    {
        $this->getRepository()->store([
            'id' => $this->id,
            'name' => $this->name,
            'total_jobs' => count($this->jobs),
            'pending_jobs' => count($this->jobs),
            'failed_jobs' => 0,
            'failed_job_ids' => null,
            'options' => json_encode($this->options),
            'cancelled_at' => null,
            'created_at' => time(),
            'finished_at' => null,
            'metadata' => json_encode($this->metadata)
        ]);
        
        // Store callbacks
        $this->storeCallbacks();
    }
    
    /**
     * Store batch callbacks
     * 
     * @return void
     */
    protected function storeCallbacks(): void
    {
        // Store callbacks as serialized jobs to be dispatched later
        $callbacks = [
            'then' => $this->serializeCallbacks($this->thenCallbacks),
            'catch' => $this->serializeCallbacks($this->catchCallbacks),
            'finally' => $this->serializeCallbacks($this->finallyCallbacks)
        ];
        
        $this->getRepository()->storeCallbacks($this->id, $callbacks);
    }
    
    /**
     * Serialize callbacks
     * 
     * @param array $callbacks Callbacks to serialize
     * @return array
     */
    protected function serializeCallbacks(array $callbacks): array
    {
        $serialized = [];
        
        foreach ($callbacks as $callback) {
            if ($callback instanceof JobInterface) {
                $serialized[] = [
                    'type' => 'job',
                    'class' => get_class($callback),
                    'data' => serialize($callback)
                ];
            } elseif ($callback instanceof Closure) {
                // Closures can't be serialized directly
                $serialized[] = [
                    'type' => 'closure',
                    'data' => null // Would need a closure serializer
                ];
            }
        }
        
        return $serialized;
    }
    
    /**
     * Get batch repository
     * 
     * @return BatchRepository
     */
    protected function getRepository(): BatchRepository
    {
        if (!$this->repository) {
            global $wpdb;
            $this->repository = new BatchRepository($wpdb, $this->logger);
        }
        
        return $this->repository;
    }
    
    /**
     * Mark job as completed in batch
     * 
     * @param string $jobId Job ID
     * @return void
     */
    public function markJobCompleted(string $jobId): void
    {
        $this->getRepository()->decrementPendingJobs($this->id);
        
        // Check if batch is finished
        if ($this->finished()) {
            $this->processBatchCompletion();
        }
    }
    
    /**
     * Mark job as failed in batch
     * 
     * @param string $jobId Job ID
     * @return void
     */
    public function markJobFailed(string $jobId): void
    {
        $this->getRepository()->incrementFailedJobs($this->id, $jobId);
        
        // If not allowing failures, trigger catch callbacks
        if (!$this->options['allowFailures'] && count($this->catchCallbacks) > 0) {
            $this->processCatchCallbacks();
        }
    }
    
    /**
     * Process batch completion
     * 
     * @return void
     */
    protected function processBatchCompletion(): void
    {
        $this->getRepository()->markFinished($this->id);
        
        // Process then callbacks if no failures
        if (!$this->hasFailed()) {
            $this->processThenCallbacks();
        }
        
        // Always process finally callbacks
        $this->processFinallyCallbacks();
        
        $this->logger?->info('Batch completed', [
            'batch_id' => $this->id,
            'batch_name' => $this->name,
            'failed_jobs' => $this->failedJobs(),
            'duration' => time() - $this->createdAt()->getTimestamp()
        ]);
    }
    
    /**
     * Process then callbacks
     * 
     * @return void
     */
    protected function processThenCallbacks(): void
    {
        $callbacks = $this->getRepository()->getCallbacks($this->id, 'then');
        $this->dispatchCallbacks($callbacks);
    }
    
    /**
     * Process catch callbacks
     * 
     * @return void
     */
    protected function processCatchCallbacks(): void
    {
        $callbacks = $this->getRepository()->getCallbacks($this->id, 'catch');
        $this->dispatchCallbacks($callbacks);
    }
    
    /**
     * Process finally callbacks
     * 
     * @return void
     */
    protected function processFinallyCallbacks(): void
    {
        $callbacks = $this->getRepository()->getCallbacks($this->id, 'finally');
        $this->dispatchCallbacks($callbacks);
    }
    
    /**
     * Dispatch callbacks
     * 
     * @param array $callbacks Callbacks to dispatch
     * @return void
     */
    protected function dispatchCallbacks(array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            if ($callback['type'] === 'job' && isset($callback['data'])) {
                $job = unserialize($callback['data']);
                if ($job instanceof JobInterface) {
                    $this->queueManager->push($job);
                }
            }
        }
    }
}