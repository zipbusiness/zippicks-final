<?php
/**
 * Job Chain Implementation
 * 
 * Enables sequential processing of dependent jobs for complex workflows
 * in the ZipPicks platform.
 * 
 * @package ZipPicks\Foundation\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue;

use ZipPicks\Foundation\Contracts\Queue\JobChainInterface;
use ZipPicks\Foundation\Contracts\Queue\JobInterface;
use ZipPicks\Foundation\Contracts\Queue\QueueManagerInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use Closure;

/**
 * Job Chain
 * 
 * Manages sequential job processing
 */
class JobChain implements JobChainInterface
{
    /**
     * Chain ID
     */
    protected string $id;
    
    /**
     * Jobs in the chain
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
     * Chain options
     */
    protected array $options = [
        'connection' => null,
        'queue' => null,
        'delay' => 0,
        'continueOnFailure' => false
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
     * Chain metadata
     */
    protected array $metadata = [];
    
    /**
     * Shared data between jobs
     */
    protected array $sharedData = [];
    
    /**
     * Chain repository
     */
    protected ?ChainRepository $repository = null;
    
    /**
     * Create a new job chain
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
        $this->id = $this->generateChainId();
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
    public function add(JobInterface $job): self
    {
        $this->jobs[] = $job;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function addMany(array $jobs): self
    {
        $this->jobs = array_merge($this->jobs, $jobs);
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
    public function withDelay(int $seconds): self
    {
        $this->options['delay'] = $seconds;
        return $this;
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
    public function dispatch(): void
    {
        if (empty($this->jobs)) {
            return;
        }
        
        // Store chain in repository
        $this->storeChain();
        
        $this->logger?->info('Dispatching job chain', [
            'chain_id' => $this->id,
            'total_jobs' => count($this->jobs)
        ]);
        
        // Prepare jobs with chain information
        $remainingJobs = $this->jobs;
        $currentJob = array_shift($remainingJobs);
        
        if ($currentJob) {
            // Set up the chain on the first job
            if (method_exists($currentJob, 'withChain')) {
                $currentJob->withChain($remainingJobs);
            }
            
            // Add chain metadata
            if (method_exists($currentJob, 'setMetadata')) {
                $metadata = $currentJob->metadata();
                $metadata['chain_id'] = $this->id;
                $metadata['chain_position'] = 0;
                $metadata['chain_total'] = count($this->jobs);
                $metadata['chain_shared_data'] = $this->sharedData;
                $currentJob->setMetadata($metadata);
            }
            
            // Dispatch the first job
            $connection = $this->options['connection'] ?? $currentJob->connection();
            $queue = $this->options['queue'] ?? $currentJob->queue();
            
            $this->queueManager
                ->connection($connection)
                ->push($currentJob, '', $queue);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function jobs(): array
    {
        return $this->jobs;
    }
    
    /**
     * {@inheritdoc}
     */
    public function currentJob(): ?JobInterface
    {
        $repository = $this->getRepository();
        $processedCount = $repository->getProcessedJobs($this->id);
        
        return $this->jobs[$processedCount] ?? null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function nextJob(): ?JobInterface
    {
        $repository = $this->getRepository();
        $processedCount = $repository->getProcessedJobs($this->id);
        
        return $this->jobs[$processedCount + 1] ?? null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function progress(): array
    {
        $repository = $this->getRepository();
        $chain = $repository->find($this->id);
        
        if (!$chain) {
            return [
                'total' => count($this->jobs),
                'completed' => 0,
                'failed' => 0,
                'pending' => count($this->jobs),
                'progress' => 0.0
            ];
        }
        
        $total = count($this->jobs);
        $processed = (int) $chain->processed_jobs;
        $failed = $chain->failed_at_job !== null ? 1 : 0;
        $pending = $total - $processed;
        $progress = $total > 0 ? round(($processed / $total) * 100, 2) : 0.0;
        
        return [
            'total' => $total,
            'completed' => $processed - $failed,
            'failed' => $failed,
            'pending' => $pending,
            'progress' => $progress
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function isComplete(): bool
    {
        $progress = $this->progress();
        return $progress['pending'] === 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasFailed(): bool
    {
        $repository = $this->getRepository();
        $chain = $repository->find($this->id);
        
        return $chain && $chain->failed_at_job !== null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(): void
    {
        $repository = $this->getRepository();
        $repository->cancel($this->id);
        
        $this->logger?->info('Chain cancelled', [
            'chain_id' => $this->id
        ]);
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
    public function continueOnFailure(): self
    {
        $this->options['continueOnFailure'] = true;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function withSharedData(array $data): self
    {
        $this->sharedData = $data;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSharedData(): array
    {
        return $this->sharedData;
    }
    
    /**
     * Generate a unique chain ID
     * 
     * @return string
     */
    protected function generateChainId(): string
    {
        return 'chain_' . bin2hex(random_bytes(16));
    }
    
    /**
     * Store chain in repository
     * 
     * @return void
     */
    protected function storeChain(): void
    {
        $this->getRepository()->store([
            'id' => $this->id,
            'jobs' => json_encode(array_map(function ($job) {
                return [
                    'class' => get_class($job),
                    'data' => serialize($job)
                ];
            }, $this->jobs)),
            'processed_jobs' => 0,
            'failed_at_job' => null,
            'options' => json_encode($this->options),
            'created_at' => time(),
            'finished_at' => null,
            'metadata' => json_encode($this->metadata)
        ]);
        
        // Store callbacks
        $this->storeCallbacks();
    }
    
    /**
     * Store chain callbacks
     * 
     * @return void
     */
    protected function storeCallbacks(): void
    {
        $callbacks = [
            'then' => $this->serializeCallbacks($this->thenCallbacks),
            'catch' => $this->serializeCallbacks($this->catchCallbacks)
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
                $serialized[] = [
                    'type' => 'closure',
                    'data' => null
                ];
            }
        }
        
        return $serialized;
    }
    
    /**
     * Get chain repository
     * 
     * @return ChainRepository
     */
    protected function getRepository(): ChainRepository
    {
        if (!$this->repository) {
            global $wpdb;
            $this->repository = new ChainRepository($wpdb, $this->logger);
        }
        
        return $this->repository;
    }
    
    /**
     * Process next job in chain
     * 
     * @param array $sharedData Updated shared data from previous job
     * @return void
     */
    public function processNext(array $sharedData = []): void
    {
        $repository = $this->getRepository();
        $chain = $repository->find($this->id);
        
        if (!$chain) {
            return;
        }
        
        $processedJobs = (int) $chain->processed_jobs;
        $jobs = json_decode($chain->jobs, true) ?: [];
        
        // Update processed count
        $repository->incrementProcessedJobs($this->id);
        
        // Check if there's a next job
        if (isset($jobs[$processedJobs + 1])) {
            $nextJobData = $jobs[$processedJobs + 1];
            $nextJob = unserialize($nextJobData['data']);
            
            if ($nextJob instanceof JobInterface) {
                // Set up chain metadata
                if (method_exists($nextJob, 'setMetadata')) {
                    $metadata = $nextJob->metadata();
                    $metadata['chain_id'] = $this->id;
                    $metadata['chain_position'] = $processedJobs + 1;
                    $metadata['chain_total'] = count($jobs);
                    $metadata['chain_shared_data'] = array_merge($this->sharedData, $sharedData);
                    $nextJob->setMetadata($metadata);
                }
                
                // Dispatch with delay if configured
                $delay = $this->options['delay'] ?? 0;
                $connection = $this->options['connection'] ?? $nextJob->connection();
                $queue = $this->options['queue'] ?? $nextJob->queue();
                
                if ($delay > 0) {
                    $this->queueManager
                        ->connection($connection)
                        ->later($delay, $nextJob, '', $queue);
                } else {
                    $this->queueManager
                        ->connection($connection)
                        ->push($nextJob, '', $queue);
                }
            }
        } else {
            // Chain is complete
            $this->processChainCompletion();
        }
    }
    
    /**
     * Mark chain as failed
     * 
     * @param int $jobPosition Position of failed job
     * @return void
     */
    public function markFailed(int $jobPosition): void
    {
        $repository = $this->getRepository();
        $repository->markFailed($this->id, $jobPosition);
        
        // Process catch callbacks
        $this->processCatchCallbacks();
        
        $this->logger?->warning('Chain failed', [
            'chain_id' => $this->id,
            'failed_at_position' => $jobPosition
        ]);
    }
    
    /**
     * Process chain completion
     * 
     * @return void
     */
    protected function processChainCompletion(): void
    {
        $repository = $this->getRepository();
        $repository->markFinished($this->id);
        
        // Process then callbacks
        $this->processThenCallbacks();
        
        $this->logger?->info('Chain completed', [
            'chain_id' => $this->id,
            'total_jobs' => count($this->jobs)
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