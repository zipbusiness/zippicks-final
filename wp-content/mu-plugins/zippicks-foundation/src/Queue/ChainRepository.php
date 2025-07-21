<?php
/**
 * Chain Repository
 * 
 * Manages chain persistence and state in the database.
 * 
 * @package ZipPicks\Foundation\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue;

use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;

/**
 * Chain Repository
 * 
 * Handles chain data persistence
 */
class ChainRepository
{
    /**
     * WordPress database instance
     */
    protected \wpdb $database;
    
    /**
     * Chains table name
     */
    protected string $table;
    
    /**
     * Logger instance
     */
    protected ?LoggerInterface $logger;
    
    /**
     * Create a new chain repository
     * 
     * @param \wpdb $database WordPress database
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(\wpdb $database, ?LoggerInterface $logger = null)
    {
        $this->database = $database;
        $this->table = $database->prefix . 'zippicks_job_chains';
        $this->logger = $logger;
    }
    
    /**
     * Store a chain
     * 
     * @param array $chain Chain data
     * @return void
     */
    public function store(array $chain): void
    {
        $this->database->replace(
            $this->table,
            $chain,
            [
                '%s', // id
                '%s', // jobs
                '%d', // processed_jobs
                '%d', // failed_at_job
                '%s', // options
                '%d', // created_at
                '%d', // finished_at
                '%s'  // metadata
            ]
        );
    }
    
    /**
     * Get chain by ID
     * 
     * @param string $chainId Chain ID
     * @return object|null
     */
    public function find(string $chainId): ?object
    {
        return $this->database->get_row(
            $this->database->prepare(
                "SELECT * FROM `{$this->table}` WHERE `id` = %s",
                $chainId
            )
        );
    }
    
    /**
     * Get processed jobs count
     * 
     * @param string $chainId Chain ID
     * @return int
     */
    public function getProcessedJobs(string $chainId): int
    {
        $chain = $this->find($chainId);
        return $chain ? (int) $chain->processed_jobs : 0;
    }
    
    /**
     * Increment processed jobs
     * 
     * @param string $chainId Chain ID
     * @return void
     */
    public function incrementProcessedJobs(string $chainId): void
    {
        $this->database->query(
            $this->database->prepare(
                "UPDATE `{$this->table}` 
                 SET `processed_jobs` = `processed_jobs` + 1
                 WHERE `id` = %s",
                $chainId
            )
        );
    }
    
    /**
     * Mark chain as failed
     * 
     * @param string $chainId Chain ID
     * @param int $failedAtJob Job position that failed
     * @return void
     */
    public function markFailed(string $chainId, int $failedAtJob): void
    {
        $this->database->update(
            $this->table,
            ['failed_at_job' => $failedAtJob],
            ['id' => $chainId],
            ['%d'],
            ['%s']
        );
    }
    
    /**
     * Mark chain as finished
     * 
     * @param string $chainId Chain ID
     * @return void
     */
    public function markFinished(string $chainId): void
    {
        $this->database->update(
            $this->table,
            ['finished_at' => time()],
            ['id' => $chainId],
            ['%d'],
            ['%s']
        );
    }
    
    /**
     * Cancel a chain
     * 
     * @param string $chainId Chain ID
     * @return void
     */
    public function cancel(string $chainId): void
    {
        $this->database->update(
            $this->table,
            ['failed_at_job' => -1], // Special value for cancelled
            ['id' => $chainId],
            ['%d'],
            ['%s']
        );
    }
    
    /**
     * Delete a chain
     * 
     * @param string $chainId Chain ID
     * @return void
     */
    public function delete(string $chainId): void
    {
        $this->database->delete(
            $this->table,
            ['id' => $chainId],
            ['%s']
        );
    }
    
    /**
     * Store chain callbacks
     * 
     * @param string $chainId Chain ID
     * @param array $callbacks Callbacks by type
     * @return void
     */
    public function storeCallbacks(string $chainId, array $callbacks): void
    {
        $chain = $this->find($chainId);
        if (!$chain) {
            return;
        }
        
        $options = json_decode($chain->options, true) ?: [];
        $options['callbacks'] = $callbacks;
        
        $this->database->update(
            $this->table,
            ['options' => json_encode($options)],
            ['id' => $chainId],
            ['%s'],
            ['%s']
        );
    }
    
    /**
     * Get chain callbacks
     * 
     * @param string $chainId Chain ID
     * @param string $type Callback type (then, catch)
     * @return array
     */
    public function getCallbacks(string $chainId, string $type): array
    {
        $chain = $this->find($chainId);
        if (!$chain || !$chain->options) {
            return [];
        }
        
        $options = json_decode($chain->options, true) ?: [];
        
        return $options['callbacks'][$type] ?? [];
    }
    
    /**
     * Get chains by status
     * 
     * @param string $status Status (pending, processing, completed, failed)
     * @param int $limit Limit
     * @return array
     */
    public function getByStatus(string $status, int $limit = 50): array
    {
        $where = match($status) {
            'pending' => 'processed_jobs = 0 AND failed_at_job IS NULL',
            'processing' => 'processed_jobs > 0 AND finished_at IS NULL AND failed_at_job IS NULL',
            'completed' => 'finished_at IS NOT NULL AND failed_at_job IS NULL',
            'failed' => 'failed_at_job IS NOT NULL',
            default => '1=0'
        };
        
        return $this->database->get_results(
            $this->database->prepare(
                "SELECT * FROM `{$this->table}` 
                 WHERE {$where}
                 ORDER BY created_at DESC
                 LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Prune old finished chains
     * 
     * @param int $days Days to keep
     * @return int Number of chains pruned
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