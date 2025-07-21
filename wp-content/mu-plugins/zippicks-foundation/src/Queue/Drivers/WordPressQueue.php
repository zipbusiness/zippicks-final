<?php
/**
 * WordPress Queue Driver
 * 
 * Native WordPress queue implementation using Action Scheduler for seamless
 * integration with the WordPress ecosystem. Provides enterprise-grade job
 * processing with automatic retries, scheduling, and WP-Admin visibility.
 * 
 * @package ZipPicks\Foundation\Queue\Drivers
 * @since 3.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue\Drivers;

use ZipPicks\Foundation\Contracts\Queue\QueueDriverInterface;
use ZipPicks\Foundation\Contracts\Queue\JobInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Queue\Job;
use ZipPicks\Foundation\Core\CircuitBreaker;
use DateTimeInterface;
use DateInterval;
use DateTime;
use Exception;
use ActionScheduler;
use ActionScheduler_Store;

/**
 * WordPress Queue Driver
 * 
 * Leverages Action Scheduler for robust job processing within WordPress
 */
class WordPressQueue implements QueueDriverInterface
{
    /**
     * Queue connection name
     */
    protected string $connectionName = 'wordpress';
    
    /**
     * Default queue name
     */
    protected string $defaultQueue;
    
    /**
     * Job creator callback
     */
    protected $jobCreator;
    
    /**
     * Logger instance
     */
    protected ?LoggerInterface $logger;
    
    /**
     * Circuit breaker for fault tolerance
     */
    protected ?CircuitBreaker $circuitBreaker;
    
    /**
     * Action Scheduler group prefix
     */
    protected string $groupPrefix = 'zippicks_queue_';
    
    /**
     * Job data transient prefix
     */
    protected string $transientPrefix = 'zippicks_job_';
    
    /**
     * Maximum payload size (WordPress option limit)
     */
    protected int $maxPayloadSize = 1000000; // ~1MB
    
    /**
     * Supported features
     */
    protected array $supportedFeatures = [
        'delay' => true,
        'priority' => false, // Action Scheduler doesn't support priority
        'batch' => true,
        'schedule' => true,
        'unique' => true,
    ];
    
    /**
     * Create a new WordPress queue instance
     * 
     * @param string $defaultQueue Default queue name
     * @param LoggerInterface|null $logger Logger instance
     * @param CircuitBreaker|null $circuitBreaker Circuit breaker
     */
    public function __construct(
        string $defaultQueue = 'default',
        ?LoggerInterface $logger = null,
        ?CircuitBreaker $circuitBreaker = null
    ) {
        $this->defaultQueue = $defaultQueue;
        $this->logger = $logger;
        $this->circuitBreaker = $circuitBreaker;
        
        // Ensure Action Scheduler is available
        $this->ensureActionScheduler();
        
        // Register our job processor hook
        add_action('zippicks_process_queued_job', [$this, 'processQueuedJob'], 10, 3);
    }
    
    /**
     * Ensure Action Scheduler is available
     * 
     * @throws Exception if Action Scheduler is not available
     */
    protected function ensureActionScheduler(): void
    {
        if (!class_exists('ActionScheduler')) {
            throw new Exception(
                'Action Scheduler is required for WordPress Queue Driver. ' .
                'Please install the Action Scheduler library or use a different queue driver.'
            );
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function size(?string $queue = null): int
    {
        $queue = $this->getQueue($queue);
        
        return $this->withCircuitBreaker(function () use ($queue) {
            $store = ActionScheduler::store();
            
            // Count pending and in-progress actions
            $args = [
                'group' => $this->groupPrefix . $queue,
                'status' => [
                    ActionScheduler_Store::STATUS_PENDING,
                    ActionScheduler_Store::STATUS_RUNNING,
                ],
                'per_page' => -1,
            ];
            
            $actions = $store->query_actions($args);
            
            return count($actions);
        }, 0);
    }
    
    /**
     * {@inheritdoc}
     */
    public function push($job, $data = '', ?string $queue = null)
    {
        return $this->pushToActionScheduler($job, $data, $queue, 0);
    }
    
    /**
     * {@inheritdoc}
     */
    public function pushRaw(string $payload, ?string $queue = null, array $options = [])
    {
        $queue = $this->getQueue($queue);
        
        return $this->withCircuitBreaker(function () use ($payload, $queue, $options) {
            // Validate payload size
            if (strlen($payload) > $this->maxPayloadSize) {
                throw new Exception('Payload too large for WordPress Queue Driver');
            }
            
            // Store payload in transient for large data
            $jobId = $this->generateJobId();
            $transientKey = $this->transientPrefix . $jobId;
            
            set_transient($transientKey, $payload, DAY_IN_SECONDS);
            
            // Schedule the action
            $actionId = as_enqueue_async_action(
                'zippicks_process_queued_job',
                [$jobId, $queue, 'raw'],
                $this->groupPrefix . $queue
            );
            
            $this->logJobPushed($jobId, $queue, 'raw');
            
            return $jobId;
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function later($delay, $job, $data = '', ?string $queue = null)
    {
        $delayInSeconds = $this->secondsUntil($delay);
        return $this->pushToActionScheduler($job, $data, $queue, $delayInSeconds);
    }
    
    /**
     * {@inheritdoc}
     */
    public function laterRaw($delay, string $payload, ?string $queue = null, int $attempts = 0)
    {
        $queue = $this->getQueue($queue);
        $delayInSeconds = $this->secondsUntil($delay);
        
        return $this->withCircuitBreaker(function () use ($delayInSeconds, $payload, $queue) {
            // Store payload
            $jobId = $this->generateJobId();
            $transientKey = $this->transientPrefix . $jobId;
            
            set_transient($transientKey, $payload, DAY_IN_SECONDS);
            
            // Schedule for later
            $timestamp = time() + $delayInSeconds;
            $actionId = as_schedule_single_action(
                $timestamp,
                'zippicks_process_queued_job',
                [$jobId, $queue, 'raw'],
                $this->groupPrefix . $queue
            );
            
            $this->logJobPushed($jobId, $queue, 'raw', $delayInSeconds);
            
            return $jobId;
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function pop(?string $queue = null, int $timeout = 0): ?JobInterface
    {
        // Action Scheduler handles job popping internally
        // This method is not typically used with Action Scheduler
        return null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function bulk(array $jobs, $data = '', ?string $queue = null): void
    {
        $queue = $this->getQueue($queue);
        
        $this->withCircuitBreaker(function () use ($jobs, $data, $queue) {
            foreach ($jobs as $job) {
                $this->push($job, $data, $queue);
            }
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function release(string $queue, JobInterface $job, int $delay = 0)
    {
        // Re-schedule the job
        return $this->later($delay, $job, $job->payload(), $queue);
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(string $queue, string $id): void
    {
        $this->withCircuitBreaker(function () use ($id) {
            // Try to find and cancel the action
            $store = ActionScheduler::store();
            $actionId = $this->findActionByJobId($id);
            
            if ($actionId) {
                $store->cancel_action($actionId);
                
                // Clean up transient
                delete_transient($this->transientPrefix . $id);
                
                $this->logger?->info('Job deleted from queue', [
                    'job_id' => $id,
                    'action_id' => $actionId,
                ]);
            }
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear(string $queue): int
    {
        return $this->withCircuitBreaker(function () use ($queue) {
            $store = ActionScheduler::store();
            
            // Get all actions for this queue
            $args = [
                'group' => $this->groupPrefix . $queue,
                'status' => [
                    ActionScheduler_Store::STATUS_PENDING,
                    ActionScheduler_Store::STATUS_RUNNING,
                ],
                'per_page' => -1,
            ];
            
            $actions = $store->query_actions($args);
            $count = 0;
            
            foreach ($actions as $actionId) {
                $store->cancel_action($actionId);
                $count++;
            }
            
            // Clean up all transients for this queue
            $this->cleanupQueueTransients($queue);
            
            $this->logger?->warning('Queue cleared', [
                'queue' => $queue,
                'jobs_cleared' => $count,
            ]);
            
            return $count;
        }, 0);
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
        $queue = $this->getQueue($queue);
        
        return $this->withCircuitBreaker(function () use ($queue) {
            $store = ActionScheduler::store();
            
            // Count jobs by status
            $pending = count($store->query_actions([
                'group' => $this->groupPrefix . $queue,
                'status' => ActionScheduler_Store::STATUS_PENDING,
                'per_page' => -1,
            ]));
            
            $running = count($store->query_actions([
                'group' => $this->groupPrefix . $queue,
                'status' => ActionScheduler_Store::STATUS_RUNNING,
                'per_page' => -1,
            ]));
            
            $failed = count($store->query_actions([
                'group' => $this->groupPrefix . $queue,
                'status' => ActionScheduler_Store::STATUS_FAILED,
                'per_page' => -1,
                'date' => date('Y-m-d H:i:s', strtotime('-24 hours')),
                'date_compare' => '>=',
            ]));
            
            return [
                'driver' => 'wordpress',
                'connection' => $this->connectionName,
                'queue' => $queue,
                'ready' => $pending,
                'reserved' => $running,
                'delayed' => 0, // Included in pending
                'failed' => $failed,
                'total' => $pending + $running,
            ];
        }, [
            'driver' => 'wordpress',
            'connection' => $this->connectionName,
            'queue' => $queue,
            'ready' => 0,
            'reserved' => 0,
            'delayed' => 0,
            'failed' => 0,
            'total' => 0,
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function supports(string $feature): bool
    {
        return $this->supportedFeatures[$feature] ?? false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getJobsByStatus(string $status, ?string $queue = null, int $limit = 100): array
    {
        $queue = $this->getQueue($queue);
        
        return $this->withCircuitBreaker(function () use ($status, $queue, $limit) {
            $store = ActionScheduler::store();
            
            // Map our status to Action Scheduler status
            $asStatus = $this->mapStatusToActionScheduler($status);
            if (!$asStatus) {
                return [];
            }
            
            $args = [
                'group' => $this->groupPrefix . $queue,
                'status' => $asStatus,
                'per_page' => $limit,
            ];
            
            $actions = $store->query_actions($args);
            $jobs = [];
            
            foreach ($actions as $actionId) {
                $action = $store->fetch_action($actionId);
                $jobData = $this->extractJobFromAction($action);
                
                if ($jobData) {
                    $jobs[] = $jobData;
                }
            }
            
            return $jobs;
        }, []);
    }
    
    /**
     * {@inheritdoc}
     */
    public function migrate(string $from, string $to, int $limit = 1000): int
    {
        return $this->withCircuitBreaker(function () use ($from, $to, $limit) {
            $store = ActionScheduler::store();
            
            $args = [
                'group' => $this->groupPrefix . $from,
                'status' => ActionScheduler_Store::STATUS_PENDING,
                'per_page' => $limit,
            ];
            
            $actions = $store->query_actions($args);
            $count = 0;
            
            foreach ($actions as $actionId) {
                $action = $store->fetch_action($actionId);
                
                // Cancel old action
                $store->cancel_action($actionId);
                
                // Create new action in target queue
                as_enqueue_async_action(
                    $action->get_hook(),
                    $action->get_args(),
                    $this->groupPrefix . $to
                );
                
                $count++;
            }
            
            $this->logger?->info('Jobs migrated between queues', [
                'from' => $from,
                'to' => $to,
                'count' => $count,
            ]);
            
            return $count;
        }, 0);
    }
    
    /**
     * {@inheritdoc}
     */
    public function health(): array
    {
        try {
            // Check if Action Scheduler tables exist
            $store = ActionScheduler::store();
            
            // Try a simple query
            $start = microtime(true);
            $store->query_actions(['per_page' => 1]);
            $latency = (microtime(true) - $start) * 1000;
            
            return [
                'healthy' => true,
                'message' => 'WordPress Queue Driver is operational',
                'latency' => $latency,
                'last_error' => null,
            ];
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'message' => 'WordPress Queue Driver error',
                'latency' => 0,
                'last_error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Process a queued job
     * 
     * This is the hook callback for Action Scheduler
     * 
     * @param string $jobId Job identifier
     * @param string $queue Queue name
     * @param string $type Job type (job or raw)
     */
    public function processQueuedJob(string $jobId, string $queue, string $type): void
    {
        try {
            $transientKey = $this->transientPrefix . $jobId;
            $payload = get_transient($transientKey);
            
            if ($payload === false) {
                throw new Exception("Job payload not found for ID: {$jobId}");
            }
            
            // Delete transient immediately to prevent reprocessing
            delete_transient($transientKey);
            
            if ($type === 'raw') {
                // For raw payloads, we need to reconstruct the job
                $jobData = json_decode($payload, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid job payload JSON');
                }
                
                $job = $this->createJobInstance($jobData);
            } else {
                // Regular job processing
                $job = unserialize($payload);
                if (!$job instanceof JobInterface) {
                    throw new Exception('Invalid job instance');
                }
            }
            
            // Execute the job
            $job->fire();
            
            $this->logger?->info('Job processed successfully', [
                'job_id' => $jobId,
                'queue' => $queue,
                'job_class' => get_class($job),
            ]);
            
        } catch (Exception $e) {
            $this->logger?->error('Job processing failed', [
                'job_id' => $jobId,
                'queue' => $queue,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Re-throw to let Action Scheduler handle retries
            throw $e;
        }
    }
    
    /**
     * Push a job to Action Scheduler
     * 
     * @param string|JobInterface $job
     * @param mixed $data
     * @param string|null $queue
     * @param int $delay
     * @return string Job ID
     */
    protected function pushToActionScheduler($job, $data, ?string $queue, int $delay)
    {
        $queue = $this->getQueue($queue);
        
        return $this->withCircuitBreaker(function () use ($job, $data, $queue, $delay) {
            // Create job instance if needed
            if (!$job instanceof JobInterface) {
                $job = $this->createJobInstance([
                    'job' => $job,
                    'data' => $data,
                    'queue' => $queue,
                ]);
            }
            
            // Serialize job for storage
            $payload = serialize($job);
            if (strlen($payload) > $this->maxPayloadSize) {
                throw new Exception('Job payload too large for WordPress Queue Driver');
            }
            
            // Store job data
            $jobId = $this->generateJobId();
            $transientKey = $this->transientPrefix . $jobId;
            set_transient($transientKey, $payload, DAY_IN_SECONDS);
            
            // Schedule the job
            if ($delay > 0) {
                $timestamp = time() + $delay;
                $actionId = as_schedule_single_action(
                    $timestamp,
                    'zippicks_process_queued_job',
                    [$jobId, $queue, 'job'],
                    $this->groupPrefix . $queue
                );
            } else {
                $actionId = as_enqueue_async_action(
                    'zippicks_process_queued_job',
                    [$jobId, $queue, 'job'],
                    $this->groupPrefix . $queue
                );
            }
            
            $this->logJobPushed($jobId, $queue, get_class($job), $delay);
            
            return $jobId;
        });
    }
    
    /**
     * Create a job instance from data
     * 
     * @param array $data
     * @return JobInterface
     */
    protected function createJobInstance(array $data): JobInterface
    {
        if ($this->jobCreator) {
            return call_user_func($this->jobCreator, $data);
        }
        
        // Default job creation
        $jobClass = $data['job'] ?? Job::class;
        
        if (!class_exists($jobClass)) {
            throw new Exception("Job class not found: {$jobClass}");
        }
        
        $job = new $jobClass();
        
        if (method_exists($job, 'setPayload')) {
            $job->setPayload($data['data'] ?? '');
        }
        
        if (method_exists($job, 'setQueue')) {
            $job->setQueue($data['queue'] ?? $this->defaultQueue);
        }
        
        return $job;
    }
    
    /**
     * Execute with circuit breaker protection
     * 
     * @param callable $callback
     * @param mixed $default
     * @return mixed
     */
    protected function withCircuitBreaker(callable $callback, $default = null)
    {
        if (!$this->circuitBreaker) {
            return $callback();
        }
        
        return $this->circuitBreaker->execute(
            'wordpress_queue',
            $callback,
            $default
        );
    }
    
    /**
     * Get queue name
     * 
     * @param string|null $queue
     * @return string
     */
    protected function getQueue(?string $queue): string
    {
        return $queue ?: $this->defaultQueue;
    }
    
    /**
     * Generate unique job ID
     * 
     * @return string
     */
    protected function generateJobId(): string
    {
        return uniqid('wpq_', true);
    }
    
    /**
     * Convert delay to seconds
     * 
     * @param DateTimeInterface|DateInterval|int $delay
     * @return int
     */
    protected function secondsUntil($delay): int
    {
        if ($delay instanceof DateTimeInterface) {
            return max(0, $delay->getTimestamp() - time());
        }
        
        if ($delay instanceof DateInterval) {
            $now = new DateTime();
            $future = clone $now;
            $future->add($delay);
            return max(0, $future->getTimestamp() - $now->getTimestamp());
        }
        
        return max(0, (int) $delay);
    }
    
    /**
     * Find Action Scheduler action by job ID
     * 
     * @param string $jobId
     * @return int|null
     */
    protected function findActionByJobId(string $jobId): ?int
    {
        $store = ActionScheduler::store();
        
        // Search through recent actions
        $args = [
            'hook' => 'zippicks_process_queued_job',
            'args' => [$jobId],
            'per_page' => 1,
        ];
        
        $actions = $store->query_actions($args);
        
        return !empty($actions) ? reset($actions) : null;
    }
    
    /**
     * Map status to Action Scheduler status
     * 
     * @param string $status
     * @return string|null
     */
    protected function mapStatusToActionScheduler(string $status): ?string
    {
        $map = [
            'ready' => ActionScheduler_Store::STATUS_PENDING,
            'reserved' => ActionScheduler_Store::STATUS_RUNNING,
            'failed' => ActionScheduler_Store::STATUS_FAILED,
            'complete' => ActionScheduler_Store::STATUS_COMPLETE,
        ];
        
        return $map[$status] ?? null;
    }
    
    /**
     * Extract job from Action Scheduler action
     * 
     * @param object $action
     * @return JobInterface|null
     */
    protected function extractJobFromAction($action): ?JobInterface
    {
        try {
            $args = $action->get_args();
            if (empty($args[0])) {
                return null;
            }
            
            $jobId = $args[0];
            $transientKey = $this->transientPrefix . $jobId;
            $payload = get_transient($transientKey);
            
            if ($payload === false) {
                return null;
            }
            
            $job = unserialize($payload);
            return $job instanceof JobInterface ? $job : null;
            
        } catch (Exception $e) {
            $this->logger?->error('Failed to extract job from action', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Clean up transients for a queue
     * 
     * @param string $queue
     */
    protected function cleanupQueueTransients(string $queue): void
    {
        global $wpdb;
        
        // Delete all transients for this queue
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE %s",
            '_transient_' . $this->transientPrefix . '%',
            '%' . $queue . '%'
        ));
        
        // Also delete timeout records
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE %s",
            '_transient_timeout_' . $this->transientPrefix . '%',
            '%' . $queue . '%'
        ));
    }
    
    /**
     * Log job pushed event
     * 
     * @param string $jobId
     * @param string $queue
     * @param string $jobClass
     * @param int $delay
     */
    protected function logJobPushed(string $jobId, string $queue, string $jobClass, int $delay = 0): void
    {
        $this->logger?->info('Job pushed to queue', [
            'job_id' => $jobId,
            'queue' => $queue,
            'job_class' => $jobClass,
            'delay' => $delay,
            'driver' => 'wordpress',
        ]);
    }
}