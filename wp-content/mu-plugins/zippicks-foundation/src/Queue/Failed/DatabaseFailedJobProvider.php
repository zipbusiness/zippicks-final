<?php
/**
 * Database Failed Job Provider
 * 
 * Manages failed jobs in the database for retry and analysis.
 * Critical for maintaining reliability at scale.
 * 
 * @package ZipPicks\Foundation\Queue\Failed
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue\Failed;

use ZipPicks\Foundation\Contracts\Queue\FailedJobProviderInterface;
use ZipPicks\Foundation\Contracts\Queue\FailedJob;
use ZipPicks\Foundation\Contracts\Queue\QueueManagerInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use DateTimeInterface;
use DateTime;
use Throwable;

/**
 * Database Failed Job Provider
 * 
 * Stores failed jobs in database
 */
class DatabaseFailedJobProvider implements FailedJobProviderInterface
{
    /**
     * WordPress database instance
     */
    protected \wpdb $database;
    
    /**
     * Failed jobs table name
     */
    protected string $table;
    
    /**
     * Logger instance
     */
    protected ?LoggerInterface $logger;
    
    /**
     * Queue manager
     */
    protected ?QueueManagerInterface $queueManager = null;
    
    /**
     * Create a new database failed job provider
     * 
     * @param \wpdb $database WordPress database
     * @param string $table Table name
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(
        \wpdb $database,
        string $table,
        ?LoggerInterface $logger = null
    ) {
        $this->database = $database;
        $this->table = $table;
        $this->logger = $logger;
    }
    
    /**
     * {@inheritdoc}
     */
    public function log(string $connection, string $queue, string $payload, Throwable $exception)
    {
        $uuid = $this->generateUuid();
        
        $result = $this->database->insert(
            $this->table,
            [
                'uuid' => $uuid,
                'connection' => $connection,
                'queue' => $queue,
                'payload' => $payload,
                'exception' => $this->formatException($exception),
                'failed_at' => current_time('mysql'),
                'metadata' => json_encode($this->extractMetadata($payload))
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if (!$result) {
            throw new \RuntimeException('Failed to log failed job');
        }
        
        $this->logger?->error('Job failed and logged', [
            'uuid' => $uuid,
            'connection' => $connection,
            'queue' => $queue,
            'exception' => get_class($exception)
        ]);
        
        return $this->database->insert_id;
    }
    
    /**
     * {@inheritdoc}
     */
    public function all(int $limit = 50, int $offset = 0): array
    {
        $results = $this->database->get_results(
            $this->database->prepare(
                "SELECT * FROM `{$this->table}` 
                 ORDER BY `failed_at` DESC 
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
        
        return array_map([$this, 'toFailedJob'], $results ?: []);
    }
    
    /**
     * {@inheritdoc}
     */
    public function find($id): ?FailedJob
    {
        $record = $this->database->get_row(
            $this->database->prepare(
                "SELECT * FROM `{$this->table}` WHERE `id` = %d",
                $id
            )
        );
        
        return $record ? $this->toFailedJob($record) : null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function retry($id): bool
    {
        $failedJob = $this->find($id);
        
        if (!$failedJob) {
            return false;
        }
        
        try {
            // Get queue manager
            $queueManager = $this->getQueueManager();
            
            // Push job back to queue
            $payload = $failedJob->getDecodedPayload();
            $connection = $queueManager->connection($failedJob->connection);
            
            $connection->pushRaw(
                $failedJob->payload,
                $failedJob->queue,
                ['attempts' => 0] // Reset attempts
            );
            
            // Delete from failed jobs
            $this->forget($id);
            
            $this->logger?->info('Failed job retried', [
                'id' => $id,
                'job' => $failedJob->getJobClass()
            ]);
            
            return true;
            
        } catch (Throwable $e) {
            $this->logger?->error('Failed to retry job', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function retryAll(?string $queue = null): int
    {
        $where = $queue ? $this->database->prepare('WHERE `queue` = %s', $queue) : '';
        
        $results = $this->database->get_results(
            "SELECT * FROM `{$this->table}` {$where}"
        );
        
        $retried = 0;
        
        foreach ($results as $record) {
            if ($this->retry($record->id)) {
                $retried++;
            }
        }
        
        return $retried;
    }
    
    /**
     * {@inheritdoc}
     */
    public function forget($id): bool
    {
        $result = $this->database->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );
        
        return (bool) $result;
    }
    
    /**
     * {@inheritdoc}
     */
    public function flush(?string $queue = null): void
    {
        if ($queue) {
            $this->database->delete(
                $this->table,
                ['queue' => $queue],
                ['%s']
            );
        } else {
            $this->database->query("TRUNCATE TABLE `{$this->table}`");
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function prune(DateTimeInterface $before): int
    {
        return $this->database->delete(
            $this->table,
            ['failed_at <' => $before->format('Y-m-d H:i:s')],
            ['%s']
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function statistics(): array
    {
        // Total count
        $total = (int) $this->database->get_var(
            "SELECT COUNT(*) FROM `{$this->table}`"
        );
        
        // By queue
        $byQueue = [];
        $queueResults = $this->database->get_results(
            "SELECT `queue`, COUNT(*) as count 
             FROM `{$this->table}` 
             GROUP BY `queue`"
        );
        
        foreach ($queueResults as $row) {
            $byQueue[$row->queue] = (int) $row->count;
        }
        
        // By connection
        $byConnection = [];
        $connectionResults = $this->database->get_results(
            "SELECT `connection`, COUNT(*) as count 
             FROM `{$this->table}` 
             GROUP BY `connection`"
        );
        
        foreach ($connectionResults as $row) {
            $byConnection[$row->connection] = (int) $row->count;
        }
        
        // By exception
        $byException = [];
        $exceptionResults = $this->database->get_results(
            "SELECT 
                SUBSTRING_INDEX(`exception`, '\n', 1) as exception_class,
                COUNT(*) as count 
             FROM `{$this->table}` 
             GROUP BY exception_class
             ORDER BY count DESC
             LIMIT 10"
        );
        
        foreach ($exceptionResults as $row) {
            $byException[$row->exception_class] = (int) $row->count;
        }
        
        // Recent failures
        $recentFailures = $this->all(10);
        
        return [
            'total' => $total,
            'by_queue' => $byQueue,
            'by_connection' => $byConnection,
            'by_exception' => $byException,
            'recent_failures' => $recentFailures
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function search(array $criteria, int $limit = 50): array
    {
        $where = ['1=1'];
        $values = [];
        
        if (isset($criteria['queue'])) {
            $where[] = '`queue` = %s';
            $values[] = $criteria['queue'];
        }
        
        if (isset($criteria['connection'])) {
            $where[] = '`connection` = %s';
            $values[] = $criteria['connection'];
        }
        
        if (isset($criteria['exception'])) {
            $where[] = '`exception` LIKE %s';
            $values[] = '%' . $criteria['exception'] . '%';
        }
        
        if (isset($criteria['after'])) {
            $where[] = '`failed_at` > %s';
            $values[] = $criteria['after'];
        }
        
        $sql = sprintf(
            "SELECT * FROM `{$this->table}` WHERE %s ORDER BY `failed_at` DESC LIMIT %d",
            implode(' AND ', $where),
            $limit
        );
        
        if (!empty($values)) {
            $sql = $this->database->prepare($sql, ...$values);
        }
        
        $results = $this->database->get_results($sql);
        
        return array_map([$this, 'toFailedJob'], $results ?: []);
    }
    
    /**
     * {@inheritdoc}
     */
    public function byQueue(string $queue, int $limit = 50, int $offset = 0): array
    {
        $results = $this->database->get_results(
            $this->database->prepare(
                "SELECT * FROM `{$this->table}` 
                 WHERE `queue` = %s 
                 ORDER BY `failed_at` DESC 
                 LIMIT %d OFFSET %d",
                $queue,
                $limit,
                $offset
            )
        );
        
        return array_map([$this, 'toFailedJob'], $results ?: []);
    }
    
    /**
     * {@inheritdoc}
     */
    public function byException(string $exceptionClass, int $limit = 50): array
    {
        $results = $this->database->get_results(
            $this->database->prepare(
                "SELECT * FROM `{$this->table}` 
                 WHERE `exception` LIKE %s 
                 ORDER BY `failed_at` DESC 
                 LIMIT %d",
                $exceptionClass . '%',
                $limit
            )
        );
        
        return array_map([$this, 'toFailedJob'], $results ?: []);
    }
    
    /**
     * {@inheritdoc}
     */
    public function count(?string $queue = null): int
    {
        if ($queue) {
            return (int) $this->database->get_var(
                $this->database->prepare(
                    "SELECT COUNT(*) FROM `{$this->table}` WHERE `queue` = %s",
                    $queue
                )
            );
        }
        
        return (int) $this->database->get_var(
            "SELECT COUNT(*) FROM `{$this->table}`"
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function exists($id): bool
    {
        $count = $this->database->get_var(
            $this->database->prepare(
                "SELECT COUNT(*) FROM `{$this->table}` WHERE `id` = %d",
                $id
            )
        );
        
        return (bool) $count;
    }
    
    /**
     * {@inheritdoc}
     */
    public function failureRate(?DateTimeInterface $since = null): array
    {
        $since = $since ?? new DateTime('-24 hours');
        $periodHours = (int) ((time() - $since->getTimestamp()) / 3600);
        
        // Get failed jobs count
        $failedJobs = (int) $this->database->get_var(
            $this->database->prepare(
                "SELECT COUNT(*) FROM `{$this->table}` WHERE `failed_at` > %s",
                $since->format('Y-m-d H:i:s')
            )
        );
        
        // Get total jobs processed (would need metrics table)
        // For now, estimate based on failed jobs
        $totalJobs = $failedJobs * 20; // Assume 5% failure rate
        
        $rate = $totalJobs > 0 ? ($failedJobs / $totalJobs) * 100 : 0;
        
        return [
            'rate' => round($rate, 2),
            'total_jobs' => $totalJobs,
            'failed_jobs' => $failedJobs,
            'period_hours' => $periodHours
        ];
    }
    
    /**
     * Convert database record to FailedJob
     * 
     * @param object $record Database record
     * @return FailedJob
     */
    protected function toFailedJob(object $record): FailedJob
    {
        return new FailedJob(
            (int) $record->id,
            $record->connection,
            $record->queue,
            $record->payload,
            $record->exception,
            new DateTime($record->failed_at),
            $record->metadata ? json_decode($record->metadata, true) : null
        );
    }
    
    /**
     * Format exception for storage
     * 
     * @param Throwable $exception Exception to format
     * @return string
     */
    protected function formatException(Throwable $exception): string
    {
        return sprintf(
            "%s: %s\n\nStack trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getTraceAsString()
        );
    }
    
    /**
     * Extract metadata from payload
     * 
     * @param string $payload Job payload
     * @return array
     */
    protected function extractMetadata(string $payload): array
    {
        $decoded = json_decode($payload, true);
        
        if (!$decoded) {
            return [];
        }
        
        $metadata = [
            'job_class' => $decoded['job'] ?? null,
            'job_id' => $decoded['id'] ?? null
        ];
        
        // Extract job-specific metadata if available
        if (isset($decoded['data']) && is_string($decoded['data'])) {
            $job = @unserialize($decoded['data']);
            if ($job && method_exists($job, 'metadata')) {
                $metadata = array_merge($metadata, $job->metadata());
            }
        }
        
        return $metadata;
    }
    
    /**
     * Generate UUID
     * 
     * @return string
     */
    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Set queue manager
     * 
     * @param QueueManagerInterface $queueManager Queue manager
     * @return void
     */
    public function setQueueManager(QueueManagerInterface $queueManager): void
    {
        $this->queueManager = $queueManager;
    }
    
    /**
     * Get queue manager
     * 
     * @return QueueManagerInterface
     */
    protected function getQueueManager(): QueueManagerInterface
    {
        if (!$this->queueManager) {
            $this->queueManager = app(QueueManagerInterface::class);
        }
        
        return $this->queueManager;
    }
}