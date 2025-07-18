<?php
/**
 * Instrumented Database Wrapper for Automatic Query Tracing
 * 
 * @package ZipPicks\Foundation\Observability
 * @since 2.0.0
 */

namespace ZipPicks\Foundation\Observability;

use ZipPicks\Foundation\Core\Foundation;

class InstrumentedDatabase
{
    /**
     * @var OpenTelemetryService
     */
    protected OpenTelemetryService $telemetry;
    
    /**
     * @var \wpdb Original WordPress database object
     */
    protected \wpdb $wpdb;
    
    /**
     * @var bool Instrumentation enabled
     */
    protected bool $enabled = false;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        $container = Foundation::getInstance()->getContainer();
        
        if ($container->has('telemetry')) {
            $this->telemetry = $container->get('telemetry');
            $this->enabled = $this->telemetry->isEnabled();
            
            if ($this->enabled) {
                $this->instrument();
            }
        }
    }
    
    /**
     * Instrument WordPress database
     */
    protected function instrument(): void
    {
        // Hook into query filter
        add_filter('query', [$this, 'traceQuery'], 10, 1);
        
        // Hook into query results
        add_filter('query_results', [$this, 'recordQueryResults'], 10, 2);
        
        // Hook into query errors
        add_action('wp_db_error', [$this, 'recordQueryError'], 10, 2);
    }
    
    /**
     * Trace database query
     * 
     * @param string $query
     * @return string
     */
    public function traceQuery(string $query): string
    {
        if (!$this->enabled) {
            return $query;
        }
        
        // Extract table name
        $table = $this->extractTableName($query);
        
        // Extract operation
        $operation = $this->extractOperation($query);
        
        // Start span
        $spanName = sprintf('DB %s %s', $operation, $table ?: 'unknown');
        
        $attributes = [
            'db.system' => 'mysql',
            'db.operation' => $operation,
            'db.statement' => $this->sanitizeQuery($query),
            'db.wordpress.prefix' => $this->wpdb->prefix
        ];
        
        if ($table) {
            $attributes['db.table'] = $table;
        }
        
        // Add connection info
        $attributes['db.connection_id'] = $this->wpdb->dbh ? mysqli_thread_id($this->wpdb->dbh) : null;
        $attributes['db.name'] = $this->wpdb->dbname;
        
        $this->telemetry->startSpan($spanName, $attributes);
        
        // Store query start time
        $this->wpdb->query_start_time = microtime(true);
        
        return $query;
    }
    
    /**
     * Record query results
     * 
     * @param mixed $results
     * @param string $query
     * @return mixed
     */
    public function recordQueryResults($results, string $query)
    {
        if (!$this->enabled) {
            return $results;
        }
        
        $operation = $this->extractOperation($query);
        $table = $this->extractTableName($query);
        $spanName = sprintf('DB %s %s', $operation, $table ?: 'unknown');
        
        // Calculate duration
        $duration = isset($this->wpdb->query_start_time) 
            ? (microtime(true) - $this->wpdb->query_start_time) * 1000 
            : 0;
        
        // Add result attributes
        $span = $this->telemetry->getCurrentSpan();
        if ($span) {
            $span->setAttribute('db.duration_ms', $duration);
            
            // Add row count based on operation
            switch (strtoupper($operation)) {
                case 'SELECT':
                    $rowCount = is_array($results) ? count($results) : 0;
                    $span->setAttribute('db.rows_returned', $rowCount);
                    break;
                case 'INSERT':
                    $span->setAttribute('db.insert_id', $this->wpdb->insert_id);
                    $span->setAttribute('db.rows_affected', 1);
                    break;
                case 'UPDATE':
                case 'DELETE':
                    $span->setAttribute('db.rows_affected', $this->wpdb->rows_affected);
                    break;
            }
            
            // Add query stats
            $span->setAttribute('db.num_queries', $this->wpdb->num_queries);
        }
        
        // End span
        $this->telemetry->endSpan($spanName);
        
        return $results;
    }
    
    /**
     * Record query error
     * 
     * @param string $error
     * @param string $query
     */
    public function recordQueryError(string $error, string $query): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $operation = $this->extractOperation($query);
        $table = $this->extractTableName($query);
        $spanName = sprintf('DB %s %s', $operation, $table ?: 'unknown');
        
        // Record error
        $this->telemetry->recordException(new \Exception($error), [
            'db.error_code' => $this->wpdb->dbh ? mysqli_errno($this->wpdb->dbh) : null,
            'db.error' => $error,
            'db.statement' => $this->sanitizeQuery($query)
        ]);
        
        // End span with error status
        $this->telemetry->endSpan($spanName, \OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $error);
    }
    
    /**
     * Extract operation from query
     * 
     * @param string $query
     * @return string
     */
    protected function extractOperation(string $query): string
    {
        $query = trim($query);
        $firstWord = strtoupper(strtok($query, ' '));
        
        // Map common operations
        $operations = [
            'SELECT' => 'SELECT',
            'INSERT' => 'INSERT',
            'UPDATE' => 'UPDATE',
            'DELETE' => 'DELETE',
            'REPLACE' => 'REPLACE',
            'CREATE' => 'CREATE',
            'DROP' => 'DROP',
            'ALTER' => 'ALTER',
            'TRUNCATE' => 'TRUNCATE',
            'SHOW' => 'SHOW',
            'DESCRIBE' => 'DESCRIBE',
            'EXPLAIN' => 'EXPLAIN',
            'SET' => 'SET'
        ];
        
        return $operations[$firstWord] ?? 'OTHER';
    }
    
    /**
     * Extract table name from query
     * 
     * @param string $query
     * @return string|null
     */
    protected function extractTableName(string $query): ?string
    {
        $query = trim($query);
        $operation = $this->extractOperation($query);
        
        switch ($operation) {
            case 'SELECT':
                if (preg_match('/FROM\s+`?(\w+)`?/i', $query, $matches)) {
                    return $matches[1];
                }
                break;
            case 'INSERT':
                if (preg_match('/INTO\s+`?(\w+)`?/i', $query, $matches)) {
                    return $matches[1];
                }
                break;
            case 'UPDATE':
                if (preg_match('/UPDATE\s+`?(\w+)`?/i', $query, $matches)) {
                    return $matches[1];
                }
                break;
            case 'DELETE':
                if (preg_match('/FROM\s+`?(\w+)`?/i', $query, $matches)) {
                    return $matches[1];
                }
                break;
        }
        
        return null;
    }
    
    /**
     * Sanitize query for telemetry
     * 
     * @param string $query
     * @return string
     */
    protected function sanitizeQuery(string $query): string
    {
        // Remove specific values but keep structure
        $query = preg_replace('/\b\d+\b/', '?', $query);
        $query = preg_replace("/'[^']*'/", '?', $query);
        $query = preg_replace('/"[^"]*"/', '?', $query);
        
        // Remove excessive whitespace
        $query = preg_replace('/\s+/', ' ', $query);
        
        // Limit length
        if (strlen($query) > 1000) {
            $query = substr($query, 0, 1000) . '...';
        }
        
        return trim($query);
    }
    
    /**
     * Get instrumentation statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'total_queries' => $this->wpdb->num_queries,
            'last_error' => $this->wpdb->last_error,
            'connection_id' => $this->wpdb->dbh ? mysqli_thread_id($this->wpdb->dbh) : null,
            'database' => $this->wpdb->dbname
        ];
    }
}