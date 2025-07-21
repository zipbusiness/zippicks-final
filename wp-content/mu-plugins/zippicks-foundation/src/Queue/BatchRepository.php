<?php
/**
 * Batch Repository
 * 
 * Manages batch persistence and state in the database.
 * 
 * @package ZipPicks\Foundation\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue;

use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;

/**
 * Batch Repository
 * 
 * Handles batch data persistence
 */
class BatchRepository
{
    /**
     * WordPress database instance
     */
    protected \wpdb $database;
    
    /**
     * Batches table name
     */
    protected string $table;
    
    /**
     * Logger instance
     */
    protected ?LoggerInterface $logger;
    
    /**
     * Create a new batch repository
     * 
     * @param \wpdb $database WordPress database
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(\wpdb $database, ?LoggerInterface $logger = null)
    {
        $this->database = $database;
        $this->table = $database->prefix . 'zippicks_job_batches';
        $this->logger = $logger;
    }
    
    /**
     * Store a batch
     * 
     * @param array $batch Batch data
     * @return void
     */
    public function store(array $batch): void
    {
        $this->database->replace(
            $this->table,
            $batch,
            [
                '%s', // id
                '%s', // name
                '%d', // total_jobs
                '%d', // pending_jobs
                '%d', // failed_jobs
                '%s', // failed_job_ids
                '%s', // options
                '%d', // cancelled_at
                '%d', // created_at
                '%d', // finished_at
                '%s'  // metadata
            ]
        );
    }
    
    /**
     * Get batch by ID
     * 
     * @param string $batchId Batch ID
     * @return object|null
     */
    public function find(string $batchId): ?object
    {
        return $this->database->get_row(
            $this->database->prepare(
                "SELECT * FROM `{$this->table}` WHERE `id` = %s",
                $batchId
            )
        );
    }
    
    /**
     * Get total jobs count
     * 
     * @param string $batchId Batch ID
     * @return int
     */
    public function getTotalJobs(string $batchId): int
    {
        $batch = $this->find($batchId);
        return $batch ? (int) $batch->total_jobs : 0;
    }
    
    /**
     * Get pending jobs count
     * 
     * @param string $batchId Batch ID
     * @return int
     */
    public function getPendingJobs(string $batchId): int
    {
        $batch = $this->find($batchId);
        return $batch ? (int) $batch->pending_jobs : 0;
    }
    
    /**
     * Get failed jobs count
     * 
     * @param string $batchId Batch ID
     * @return int
     */
    public function getFailedJobs(string $batchId): int
    {
        $batch = $this->find($batchId);
        return $batch ? (int) $batch->failed_jobs : 0;
    }
    
    /**
     * Get failed job IDs
     * 
     * @param string $batchId Batch ID
     * @return array
     */
    public function getFailedJobIds(string $batchId): array
    {
        $batch = $this->find($batchId);
        if (!$batch || !$batch->failed_job_ids) {
            return [];
        }
        
        return json_decode($batch->failed_job_ids, true) ?: [];
    }
    
    /**
     * Check if batch is cancelled
     * 
     * @param string $batchId Batch ID
     * @return bool
     */
    public function isCancelled(string $batchId): bool
    {
        $batch = $this->find($batchId);
        return $batch && $batch->cancelled_at !== null;
    }
    
    /**
     * Cancel a batch
     * 
     * @param string $batchId Batch ID
     * @return void
     */
    public function cancel(string $batchId): void
    {
        $this->database->update(
            $this->table,
            ['cancelled_at' => time()],
            ['id' => $batchId],
            ['%d'],
            ['%s']
        );
    }
    
    /**
     * Delete a batch
     * 
     * @param string $batchId Batch ID
     * @return void
     */
    public function delete(string $batchId): void
    {
        $this->database->delete(
            $this->table,
            ['id' => $batchId],
            ['%s']
        );
    }
    
    /**
     * Get created at timestamp
     * 
     * @param string $batchId Batch ID
     * @return int|null
     */
    public function getCreatedAt(string $batchId): ?int
    {
        $batch = $this->find($batchId);
        return $batch ? (int) $batch->created_at : null;
    }
    
    /**
     * Get finished at timestamp
     * 
     * @param string $batchId Batch ID
     * @return int|null
     */
    public function getFinishedAt(string $batchId): ?int
    {
        $batch = $this->find($batchId);
        return $batch && $batch->finished_at ? (int) $batch->finished_at : null;
    }
    
    /**
     * Decrement pending jobs
     * 
     * @param string $batchId Batch ID
     * @return void
     */
    public function decrementPendingJobs(string $batchId): void
    {
        $this->database->query(
            $this->database->prepare(
                "UPDATE `{$this->table}` 
                 SET `pending_jobs` = GREATEST(0, `pending_jobs` - 1)
                 WHERE `id` = %s",
                $batchId
            )
        );
    }
    
    /**
     * Increment failed jobs
     * 
     * @param string $batchId Batch ID
     * @param string $jobId Failed job ID
     * @return void
     */
    public function incrementFailedJobs(string $batchId, string $jobId): void
    {
        $batch = $this->find($batchId);
        if (!$batch) {
            return;
        }
        
        $failedJobIds = $batch->failed_job_ids 
            ? json_decode($batch->failed_job_ids, true) 
            : [];
        
        $failedJobIds[] = $jobId;
        
        $this->database->update(
            $this->table,
            [
                'failed_jobs' => $batch->failed_jobs + 1,
                'failed_job_ids' => json_encode($failedJobIds),
                'pending_jobs' => max(0, $batch->pending_jobs - 1)
            ],
            ['id' => $batchId],
            ['%d', '%s', '%d'],
            ['%s']
        );
    }
    
    /**
     * Mark batch as finished
     * 
     * @param string $batchId Batch ID
     * @return void
     */
    public function markFinished(string $batchId): void
    {
        $this->database->update(
            $this->table,
            ['finished_at' => time()],
            ['id' => $batchId],
            ['%d'],
            ['%s']
        );
    }
    
    /**
     * Store batch callbacks
     * 
     * @param string $batchId Batch ID
     * @param array $callbacks Callbacks by type
     * @return void
     */
    public function storeCallbacks(string $batchId, array $callbacks): void
    {
        $batch = $this->find($batchId);
        if (!$batch) {
            return;
        }
        
        $options = json_decode($batch->options, true) ?: [];
        $options['callbacks'] = $callbacks;
        
        $this->database->update(
            $this->table,
            ['options' => json_encode($options)],
            ['id' => $batchId],
            ['%s'],
            ['%s']
        );
    }
    
    /**
     * Get batch callbacks
     * 
     * @param string $batchId Batch ID
     * @param string $type Callback type (then, catch, finally)
     * @return array
     */
    public function getCallbacks(string $batchId, string $type): array
    {
        $batch = $this->find($batchId);
        if (!$batch || !$batch->options) {
            return [];
        }
        
        $options = json_decode($batch->options, true) ?: [];
        
        return $options['callbacks'][$type] ?? [];
    }
    
    /**
     * Prune old finished batches
     * 
     * @param int $days Days to keep
     * @return int Number of batches pruned
     */
    public function prune(int $days = 7): int
    {
        $cutoff = time() - ($days * 86400);
        
        return $this->database->delete(
            $this->table,
            ['finished_at <' => $cutoff],
            ['%d']
        );
    }
}