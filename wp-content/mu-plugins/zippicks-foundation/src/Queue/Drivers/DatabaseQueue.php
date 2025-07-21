<?php
/**
 * Database Queue Driver
 * 
 * High-performance database-backed queue driver with transaction support.
 * Designed to handle millions of jobs per day for the ZipPicks platform.
 * 
 * @package ZipPicks\Foundation\Queue\Drivers
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue\Drivers;

use ZipPicks\Foundation\Contracts\Queue\QueueDriverInterface;
use ZipPicks\Foundation\Contracts\Queue\JobInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Core\CircuitBreaker;
use DateTimeInterface;
use DateInterval;
use DateTime;

/**
 * Database Queue Driver
 * 
 * Implements queue operations using WordPress database
 */
class DatabaseQueue implements QueueDriverInterface
{
    /**
     * WordPress database instance
     */
    protected \wpdb $database;
    
    /**
     * Queue connection name
     */
    protected string $connectionName;
    
    /**
     * Jobs table name
     */
    protected string $table;
    
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
     * Retry after timeout (seconds)
     */
    protected int $retryAfter;
    
    /**
     * Transaction depth counter
     */
    protected int $transactionLevel = 0;
    
    /**
     * Create a new database queue instance
     * 
     * @param \wpdb $database WordPress database instance
     * @param string $table Table name
     * @param string $defaultQueue Default queue name
     * @param int $retryAfter Retry timeout in seconds
     * @param string $connectionName Connection name
     * @param LoggerInterface|null $logger Logger instance
     * @param CircuitBreaker|null $circuitBreaker Circuit breaker
     */
    public function __construct(
        \wpdb $database,
        string $table,
        string $defaultQueue = 'default',
        int $retryAfter = 90,
        string $connectionName = 'database',
        ?LoggerInterface $logger = null,
        ?CircuitBreaker $circuitBreaker = null
    ) {
        $this->database = $database;
        $this->table = $table;
        $this->defaultQueue = $defaultQueue;
        $this->retryAfter = $retryAfter;
        $this->connectionName = $connectionName;
        $this->logger = $logger;
        $this->circuitBreaker = $circuitBreaker;
    }
    
    /**
     * {@inheritdoc}
     */
    public function size(?string $queue = null): int
    {
        $queue = $this->getQueue($queue);
        
        return (int) $this->database->get_var($this->database->prepare(
            "SELECT COUNT(*) FROM `{$this->table}` 
             WHERE `queue` = %s 
             AND (`reserved_at` IS NULL OR `reserved_at` <= %d)",
            $queue,
            $this->currentTime() - $this->retryAfter
        ));
    }
    
    /**
     * {@inheritdoc}
     */
    public function push($job, $data = '', ?string $queue = null)
    {
        return $this->pushToDatabase($queue, $this->createPayload($job, $data), 0);
    }
    
    /**
     * {@inheritdoc}
     */
    public function pushRaw(string $payload, ?string $queue = null, array $options = [])
    {
        return $this->pushToDatabase(
            $queue,
            $payload,
            $options['attempts'] ?? 0,
            $options['priority'] ?? 5
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function later($delay, $job, $data = '', ?string $queue = null)
    {
        return $this->pushToDatabase(
            $queue,
            $this->createPayload($job, $data),
            0,
            5,
            $this->availableAt($delay)
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function laterRaw($delay, string $payload, ?string $queue = null, int $attempts = 0)
    {
        return $this->pushToDatabase(
            $queue,
            $payload,
            $attempts,
            5,
            $this->availableAt($delay)
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function bulk(array $jobs, $data = '', ?string $queue = null): void
    {
        $this->beginTransaction();
        
        try {
            foreach ($jobs as $job) {
                $this->push($job, $data, $queue);
            }
            
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function pop(?string $queue = null, int $timeout = 0): ?JobInterface
    {
        $queue = $this->getQueue($queue);
        
        return $this->circuitBreaker 
            ? $this->circuitBreaker->call(fn() => $this->popJob($queue, $timeout))
            : $this->popJob($queue, $timeout);
    }
    
    /**
     * Pop a job from the queue
     * 
     * @param string $queue Queue name
     * @param int $timeout Timeout in seconds
     * @return JobInterface|null
     */
    protected function popJob(string $queue, int $timeout): ?JobInterface
    {
        $this->beginTransaction();
        
        try {
            if ($job = $this->getNextAvailableJob($queue)) {
                $job = $this->markJobAsReserved($job);
                $this->commit();
                
                return $this->marshalJob($job);
            }
            
            $this->commit();
            return null;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Get the next available job
     * 
     * @param string $queue Queue name
     * @return object|null
     */
    protected function getNextAvailableJob(string $queue): ?object
    {
        $sql = $this->database->prepare(
            "SELECT * FROM `{$this->table}` 
             WHERE `queue` = %s 
             AND ((`reserved_at` IS NULL AND `available_at` <= %d) 
                  OR (`reserved_at` <= %d))
             ORDER BY `priority` ASC, `available_at` ASC, `id` ASC 
             LIMIT 1 
             FOR UPDATE",
            $queue,
            $this->currentTime(),
            $this->currentTime() - $this->retryAfter
        );
        
        return $this->database->get_row($sql);
    }
    
    /**
     * Mark job as reserved
     * 
     * @param object $job Database job record
     * @return object Updated job record
     */
    protected function markJobAsReserved(object $job): object
    {
        $this->database->update(
            $this->table,
            [
                'reserved_at' => $this->currentTime(),
                'attempts' => $job->attempts + 1
            ],
            ['id' => $job->id],
            ['%d', '%d'],
            ['%d']
        );
        
        $job->reserved_at = $this->currentTime();
        $job->attempts = $job->attempts + 1;
        
        return $job;
    }
    
    /**
     * {@inheritdoc}
     */
    public function release(string $queue, JobInterface $job, int $delay = 0)
    {
        return $this->pushToDatabase(
            $queue,
            $job->getRawPayload(),
            $job->attempts(),
            $job->priority(),
            $this->availableAt($delay)
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(string $queue, string $id): void
    {
        $this->database->delete(
            $this->table,
            ['id' => $id, 'queue' => $queue],
            ['%d', '%s']
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear(string $queue): int
    {
        return $this->database->delete(
            $this->table,
            ['queue' => $queue],
            ['%s']
        );
    }
    
    /**
     * Push a job to the database
     * 
     * @param string|null $queue Queue name
     * @param string $payload Job payload
     * @param int $attempts Number of attempts
     * @param int $priority Job priority
     * @param int|null $availableAt When job is available
     * @return string Job ID
     */
    protected function pushToDatabase(
        ?string $queue,
        string $payload,
        int $attempts = 0,
        int $priority = 5,
        ?int $availableAt = null
    ): string {
        $queue = $this->getQueue($queue);
        $availableAt = $availableAt ?? $this->currentTime();
        
        $result = $this->database->insert(
            $this->table,
            [
                'queue' => $queue,
                'payload' => $payload,
                'attempts' => $attempts,
                'priority' => $priority,
                'available_at' => $availableAt,
                'created_at' => $this->currentTime(),
                'reserved_at' => null
            ],
            ['%s', '%s', '%d', '%d', '%d', '%d', null]
        );
        
        if (!$result) {
            throw new \RuntimeException('Failed to insert job into queue');
        }
        
        $jobId = (string) $this->database->insert_id;
        
        $this->logger?->debug('Job pushed to database queue', [
            'job_id' => $jobId,
            'queue' => $queue,
            'priority' => $priority,
            'available_at' => $availableAt
        ]);
        
        return $jobId;
    }
    
    /**
     * Create job payload
     * 
     * @param mixed $job Job instance or class name
     * @param mixed $data Job data
     * @return string JSON payload
     */
    protected function createPayload($job, $data = ''): string
    {
        if ($job instanceof JobInterface) {
            $payload = [
                'job' => get_class($job),
                'data' => serialize($job),
                'id' => $job->getJobId() ?? $this->generateJobId(),
                'attempts' => $job->attempts()
            ];
        } else {
            $payload = [
                'job' => $job,
                'data' => $data,
                'id' => $this->generateJobId(),
                'attempts' => 0
            ];
        }
        
        return json_encode($payload);
    }
    
    /**
     * Marshal a job from database record
     * 
     * @param object $job Database job record
     * @return JobInterface
     */
    protected function marshalJob(object $job): JobInterface
    {
        $payload = json_decode($job->payload, true);
        
        if ($this->jobCreator) {
            return call_user_func($this->jobCreator, $payload, $job);
        }
        
        // Default job creation logic
        if (isset($payload['data']) && is_string($payload['data'])) {
            $instance = unserialize($payload['data']);
            
            if ($instance instanceof JobInterface) {
                $instance->setJobId((string) $job->id);
                $instance->setAttempts((int) $job->attempts);
                $instance->setQueue($job->queue);
                $instance->setRawPayload($job->payload);
                
                return $instance;
            }
        }
        
        throw new \RuntimeException('Unable to marshal job from payload');
    }
    
    /**
     * Get the queue name
     * 
     * @param string|null $queue Queue name
     * @return string
     */
    protected function getQueue(?string $queue): string
    {
        return $queue ?: $this->defaultQueue;
    }
    
    /**
     * Get available at timestamp
     * 
     * @param DateTimeInterface|DateInterval|int $delay Delay
     * @return int Unix timestamp
     */
    protected function availableAt($delay = 0): int
    {
        if ($delay instanceof DateTimeInterface) {
            return $delay->getTimestamp();
        }
        
        if ($delay instanceof DateInterval) {
            return (new DateTime())->add($delay)->getTimestamp();
        }
        
        return $this->currentTime() + (int) $delay;
    }
    
    /**
     * Get current timestamp
     * 
     * @return int
     */
    protected function currentTime(): int
    {
        return time();
    }
    
    /**
     * Generate a unique job ID
     * 
     * @return string
     */
    protected function generateJobId(): string
    {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Begin a database transaction
     * 
     * @return void
     */
    protected function beginTransaction(): void
    {
        $this->transactionLevel++;
        
        if ($this->transactionLevel === 1) {
            $this->database->query('START TRANSACTION');
        }
    }
    
    /**
     * Commit a database transaction
     * 
     * @return void
     */
    protected function commit(): void
    {
        if ($this->transactionLevel === 1) {
            $this->database->query('COMMIT');
        }
        
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
    }
    
    /**
     * Rollback a database transaction
     * 
     * @return void
     */
    protected function rollback(): void
    {
        if ($this->transactionLevel === 1) {
            $this->database->query('ROLLBACK');
        }
        
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
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
        
        $stats = $this->database->get_row($this->database->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN reserved_at IS NULL AND available_at <= %d THEN 1 ELSE 0 END) as ready,
                SUM(CASE WHEN reserved_at IS NOT NULL AND reserved_at > %d THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN available_at > %d THEN 1 ELSE 0 END) as delayed
             FROM `{$this->table}`
             WHERE queue = %s",
            $this->currentTime(),
            $this->currentTime() - $this->retryAfter,
            $this->currentTime(),
            $queue
        ), ARRAY_A);
        
        return [
            'driver' => 'database',
            'connection' => $this->connectionName,
            'queue' => $queue,
            'ready' => (int) ($stats['ready'] ?? 0),
            'reserved' => (int) ($stats['reserved'] ?? 0),
            'delayed' => (int) ($stats['delayed'] ?? 0),
            'total' => (int) ($stats['total'] ?? 0)
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function supports(string $feature): bool
    {
        $supported = [
            'priority' => true,
            'delay' => true,
            'batch' => true,
            'transaction' => true,
            'metadata' => true
        ];
        
        return $supported[$feature] ?? false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getJobsByStatus(string $status, ?string $queue = null, int $limit = 100): array
    {
        $queue = $this->getQueue($queue);
        $currentTime = $this->currentTime();
        
        $conditions = match($status) {
            'ready' => "reserved_at IS NULL AND available_at <= {$currentTime}",
            'reserved' => "reserved_at IS NOT NULL AND reserved_at > " . ($currentTime - $this->retryAfter),
            'delayed' => "available_at > {$currentTime}",
            default => '1=0'
        };
        
        $results = $this->database->get_results($this->database->prepare(
            "SELECT * FROM `{$this->table}` 
             WHERE queue = %s AND {$conditions}
             ORDER BY priority ASC, available_at ASC 
             LIMIT %d",
            $queue,
            $limit
        ));
        
        return array_map([$this, 'marshalJob'], $results ?: []);
    }
    
    /**
     * {@inheritdoc}
     */
    public function migrate(string $from, string $to, int $limit = 1000): int
    {
        return $this->database->update(
            $this->table,
            ['queue' => $to],
            ['queue' => $from],
            ['%s'],
            ['%s']
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function health(): array
    {
        $startTime = microtime(true);
        
        try {
            // Test database connection with a simple query
            $this->database->get_var("SELECT 1");
            
            $latency = (microtime(true) - $startTime) * 1000;
            
            return [
                'healthy' => true,
                'message' => 'Database queue is operational',
                'latency' => round($latency, 2),
                'last_error' => null
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'message' => 'Database queue error',
                'latency' => 0,
                'last_error' => $e->getMessage()
            ];
        }
    }
}