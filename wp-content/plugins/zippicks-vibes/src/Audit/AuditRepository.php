<?php
/**
 * Audit Repository for ZipPicks Vibes
 * 
 * Enhanced repository for audit log database operations with input sanitization,
 * file fallback logging, and comprehensive indexing strategy.
 * 
 * @package ZipPicksVibes
 * @subpackage Audit
 * @since 2.0.0
 * @version 2.1.0
 */

namespace ZipPicksVibes\Audit;

/**
 * AuditRepository Class
 * 
 * Enhanced repository with sanitization, fallback logging, and optimized queries
 * 
 * Database Indexing Strategy:
 * - Primary: id (auto-increment)
 * - INDEX: event_type, event_category (for filtering)
 * - INDEX: user_id (for user-specific queries)
 * - INDEX: created_at (for time-based queries and cleanup)
 * - INDEX: severity (for priority filtering)
 * - INDEX: ip_address (for security analysis)
 * - COMPOSITE: event_type, created_at (for efficient event timeline queries)
 * - COMPOSITE: event_category, severity (for security monitoring)
 */
class AuditRepository {
    
    /**
     * Table name
     * 
     * @var string
     */
    private string $table_name;
    
    /**
     * Logger instance for fallback logging
     * 
     * @var mixed|null
     */
    private $logger;
    
    /**
     * Enable file fallback logging
     * 
     * @var bool
     */
    private bool $enable_file_fallback;
    
    /**
     * Constructor
     * 
     * @param mixed $logger Optional logger instance
     * @param bool $enable_file_fallback Enable file fallback on DB failures
     */
    public function __construct($logger = null, bool $enable_file_fallback = true) {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'zippicks_audit_log';
        $this->logger = $logger;
        $this->enable_file_fallback = $enable_file_fallback;
    }
    
    /**
     * Save audit event with input sanitization and fallback logging
     * 
     * @param AuditEvent $event Event to save
     * @return bool Success status
     */
    public function save(AuditEvent $event): bool {
        try {
            global $wpdb;
            
            // Validate event before saving
            $event->validate();
            
            $data = $event->toArray();
            unset($data['id']); // Remove ID for insert
            
            // Sanitize all input data
            $data = $this->sanitizeEventData($data);
            
            // Encode JSON fields
            if (isset($data['changes']) && is_array($data['changes'])) {
                $data['changes'] = wp_json_encode($data['changes']);
            }
            
            if (isset($data['metadata']) && is_array($data['metadata'])) {
                $data['metadata'] = wp_json_encode($data['metadata']);
            }
            
            // Insert into database
            $result = $wpdb->insert(
                $this->table_name,
                $data,
                $this->getFormats($data)
            );
            
            if ($result !== false) {
                $event->setId($wpdb->insert_id);
                return true;
            }
            
            // Database insert failed
            $this->handleDatabaseFailure('insert', $data, $wpdb->last_error);
            return false;
            
        } catch (\Exception $e) {
            // Handle any exceptions (validation, database errors, etc.)
            $this->handleSaveFailure($event, $e);
            return false;
        }
    }
    
    /**
     * Find audit event by ID
     * 
     * @param int $id Event ID
     * @return AuditEvent|null
     */
    public function findById(int $id): ?AuditEvent {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        );
        
        $row = $wpdb->get_row($query, ARRAY_A);
        
        if (!$row) {
            return null;
        }
        
        return $this->hydrate($row);
    }
    
    /**
     * Find audit events
     * 
     * @param array $criteria Search criteria
     * @param int $limit Result limit
     * @param int $offset Result offset
     * @param string $order_by Order by field
     * @param string $order Order direction
     * @return array
     */
    public function find(
        array $criteria = [],
        int $limit = 100,
        int $offset = 0,
        string $order_by = 'created_at',
        string $order = 'DESC'
    ): array {
        global $wpdb;
        
        // Build WHERE clause
        $where = $this->buildWhereClause($criteria);
        
        // Build query
        $query = "SELECT * FROM {$this->table_name}";
        if ($where['sql']) {
            $query .= " WHERE " . $where['sql'];
        }
        $query .= " ORDER BY {$order_by} {$order}";
        $query .= " LIMIT %d OFFSET %d";
        
        // Add limit and offset to values
        $where['values'][] = $limit;
        $where['values'][] = $offset;
        
        // Execute query
        $results = empty($where['values']) 
            ? $wpdb->get_results($query, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($query, $where['values']), ARRAY_A);
        
        // Hydrate results
        return array_map([$this, 'hydrate'], $results);
    }
    
    /**
     * Count audit events
     * 
     * @param array $criteria Search criteria
     * @return int
     */
    public function count(array $criteria = []): int {
        global $wpdb;
        
        // Build WHERE clause
        $where = $this->buildWhereClause($criteria);
        
        // Build query
        $query = "SELECT COUNT(*) FROM {$this->table_name}";
        if ($where['sql']) {
            $query .= " WHERE " . $where['sql'];
        }
        
        // Execute query
        return (int) (empty($where['values'])
            ? $wpdb->get_var($query)
            : $wpdb->get_var($wpdb->prepare($query, $where['values'])));
    }
    
    /**
     * Get statistics
     * 
     * @param array $criteria Optional criteria
     * @return array
     */
    public function getStatistics(array $criteria = []): array {
        global $wpdb;
        
        // Build WHERE clause
        $where = $this->buildWhereClause($criteria);
        $where_sql = $where['sql'] ? " WHERE " . $where['sql'] : "";
        
        $stats = [];
        
        // Total events
        $query = "SELECT COUNT(*) FROM {$this->table_name}" . $where_sql;
        $stats['total_events'] = (int) (empty($where['values'])
            ? $wpdb->get_var($query)
            : $wpdb->get_var($wpdb->prepare($query, $where['values'])));
        
        // Events by type
        $query = "SELECT event_type, COUNT(*) as count 
                 FROM {$this->table_name}" . $where_sql . " 
                 GROUP BY event_type";
        $results = empty($where['values'])
            ? $wpdb->get_results($query)
            : $wpdb->get_results($wpdb->prepare($query, $where['values']));
        
        $stats['by_type'] = [];
        foreach ($results as $row) {
            $stats['by_type'][$row->event_type] = (int) $row->count;
        }
        
        // Events by category
        $query = "SELECT event_category, COUNT(*) as count 
                 FROM {$this->table_name}" . $where_sql . " 
                 GROUP BY event_category";
        $results = empty($where['values'])
            ? $wpdb->get_results($query)
            : $wpdb->get_results($wpdb->prepare($query, $where['values']));
        
        $stats['by_category'] = [];
        foreach ($results as $row) {
            $stats['by_category'][$row->event_category] = (int) $row->count;
        }
        
        // Events by severity
        $query = "SELECT severity, COUNT(*) as count 
                 FROM {$this->table_name}" . $where_sql . " 
                 GROUP BY severity";
        $results = empty($where['values'])
            ? $wpdb->get_results($query)
            : $wpdb->get_results($wpdb->prepare($query, $where['values']));
        
        $stats['by_severity'] = [];
        foreach ($results as $row) {
            $stats['by_severity'][$row->severity] = (int) $row->count;
        }
        
        // Events by status
        $query = "SELECT status, COUNT(*) as count 
                 FROM {$this->table_name}" . $where_sql . " 
                 GROUP BY status";
        $results = empty($where['values'])
            ? $wpdb->get_results($query)
            : $wpdb->get_results($wpdb->prepare($query, $where['values']));
        
        $stats['by_status'] = [];
        foreach ($results as $row) {
            $stats['by_status'][$row->status] = (int) $row->count;
        }
        
        // Unique users
        $query = "SELECT COUNT(DISTINCT user_id) 
                 FROM {$this->table_name}" . $where_sql . " 
                 WHERE user_id IS NOT NULL";
        $stats['unique_users'] = (int) (empty($where['values'])
            ? $wpdb->get_var($query)
            : $wpdb->get_var($wpdb->prepare($query, $where['values'])));
        
        // Unique IPs
        $query = "SELECT COUNT(DISTINCT ip_address) 
                 FROM {$this->table_name}" . $where_sql;
        $stats['unique_ips'] = (int) (empty($where['values'])
            ? $wpdb->get_var($query)
            : $wpdb->get_var($wpdb->prepare($query, $where['values'])));
        
        // Average duration for performance events
        $perf_where = $where_sql ? $where_sql . " AND " : " WHERE ";
        $perf_where .= "event_type = 'performance' AND duration_ms IS NOT NULL";
        $query = "SELECT AVG(duration_ms) FROM {$this->table_name}" . $perf_where;
        $values = $where['values'];
        if ($where_sql) {
            $values[] = 'performance';
        }
        $stats['avg_duration_ms'] = (float) (empty($values)
            ? $wpdb->get_var($query)
            : $wpdb->get_var($wpdb->prepare($query, $values)));
        
        return $stats;
    }
    
    /**
     * Delete old logs
     * 
     * @param \DateTime $before Delete logs before this date
     * @return int Number of deleted records
     */
    public function deleteOldLogs(\DateTime $before): int {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            ['created_at <' => $before->format('Y-m-d H:i:s')],
            ['%s']
        );
    }
    
    /**
     * Build WHERE clause from criteria
     * 
     * @param array $criteria Search criteria
     * @return array ['sql' => string, 'values' => array]
     */
    private function buildWhereClause(array $criteria): array {
        $where_clauses = [];
        $where_values = [];
        
        // Event type
        if (!empty($criteria['event_type'])) {
            if (is_array($criteria['event_type'])) {
                $placeholders = array_fill(0, count($criteria['event_type']), '%s');
                $where_clauses[] = 'event_type IN (' . implode(',', $placeholders) . ')';
                $where_values = array_merge($where_values, $criteria['event_type']);
            } else {
                $where_clauses[] = 'event_type = %s';
                $where_values[] = $criteria['event_type'];
            }
        }
        
        // Event action
        if (!empty($criteria['event_action'])) {
            $where_clauses[] = 'event_action = %s';
            $where_values[] = $criteria['event_action'];
        }
        
        // Event category
        if (!empty($criteria['event_category'])) {
            if (is_array($criteria['event_category'])) {
                $placeholders = array_fill(0, count($criteria['event_category']), '%s');
                $where_clauses[] = 'event_category IN (' . implode(',', $placeholders) . ')';
                $where_values = array_merge($where_values, $criteria['event_category']);
            } else {
                $where_clauses[] = 'event_category = %s';
                $where_values[] = $criteria['event_category'];
            }
        }
        
        // User ID
        if (isset($criteria['user_id'])) {
            if ($criteria['user_id'] === null) {
                $where_clauses[] = 'user_id IS NULL';
            } else {
                $where_clauses[] = 'user_id = %d';
                $where_values[] = $criteria['user_id'];
            }
        }
        
        // IP address
        if (!empty($criteria['ip_address'])) {
            $where_clauses[] = 'ip_address = %s';
            $where_values[] = $criteria['ip_address'];
        }
        
        // Object type
        if (!empty($criteria['object_type'])) {
            $where_clauses[] = 'object_type = %s';
            $where_values[] = $criteria['object_type'];
        }
        
        // Object ID
        if (!empty($criteria['object_id'])) {
            $where_clauses[] = 'object_id = %d';
            $where_values[] = $criteria['object_id'];
        }
        
        // Severity
        if (!empty($criteria['severity'])) {
            if (is_array($criteria['severity'])) {
                $placeholders = array_fill(0, count($criteria['severity']), '%s');
                $where_clauses[] = 'severity IN (' . implode(',', $placeholders) . ')';
                $where_values = array_merge($where_values, $criteria['severity']);
            } else {
                $where_clauses[] = 'severity = %s';
                $where_values[] = $criteria['severity'];
            }
        }
        
        // Status
        if (!empty($criteria['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $criteria['status'];
        }
        
        // Date range
        if (!empty($criteria['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $criteria['date_from'];
        }
        
        if (!empty($criteria['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $criteria['date_to'];
        }
        
        return [
            'sql' => implode(' AND ', $where_clauses),
            'values' => $where_values
        ];
    }
    
    /**
     * Hydrate audit event from database row
     * 
     * @param array $row Database row
     * @return AuditEvent
     */
    private function hydrate(array $row): AuditEvent {
        // Decode JSON fields
        if (!empty($row['changes'])) {
            $row['changes'] = json_decode($row['changes'], true);
        }
        if (!empty($row['metadata'])) {
            $row['metadata'] = json_decode($row['metadata'], true);
        }
        
        // Convert numeric fields
        $row['id'] = (int) $row['id'];
        $row['user_id'] = $row['user_id'] !== null ? (int) $row['user_id'] : null;
        $row['object_id'] = $row['object_id'] !== null ? (int) $row['object_id'] : null;
        $row['duration_ms'] = $row['duration_ms'] !== null ? (float) $row['duration_ms'] : null;
        
        return AuditEvent::fromArray($row);
    }
    
    /**
     * Get format specifiers for data
     * 
     * @param array $data Data array
     * @return array Format specifiers
     */
    private function getFormats(array $data): array {
        $formats = [];
        
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'user_id':
                case 'object_id':
                    $formats[] = $value === null ? '%s' : '%d';
                    break;
                case 'duration_ms':
                    $formats[] = $value === null ? '%s' : '%f';
                    break;
                default:
                    $formats[] = '%s';
            }
        }
        
        return $formats;
    }
    
    /**
     * Sanitize event data before database insertion
     * 
     * @param array $data Event data
     * @return array Sanitized data
     */
    private function sanitizeEventData(array $data): array {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if ($value === null) {
                $sanitized[$key] = null;
                continue;
            }
            
            switch ($key) {
                case 'event_type':
                case 'event_action':
                case 'event_category':
                case 'object_type':
                case 'status':
                case 'severity':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                    
                case 'user_agent':
                case 'request_uri':
                case 'session_id':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;
                    
                case 'ip_address':
                    $sanitized[$key] = filter_var($value, FILTER_VALIDATE_IP) ? $value : '127.0.0.1';
                    break;
                    
                case 'user_id':
                case 'object_id':
                    $sanitized[$key] = $value !== null ? absint($value) : null;
                    break;
                    
                case 'duration_ms':
                    $sanitized[$key] = $value !== null ? floatval($value) : null;
                    break;
                    
                case 'changes':
                case 'metadata':
                    // Arrays will be JSON encoded later
                    $sanitized[$key] = is_array($value) ? $this->sanitizeArrayRecursive($value) : $value;
                    break;
                    
                case 'created_at':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                    
                case 'timestamp':
                    $sanitized[$key] = absint($value);
                    break;
                    
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Recursively sanitize array data
     * 
     * @param array $array Array to sanitize
     * @return array Sanitized array
     */
    private function sanitizeArrayRecursive(array $array): array {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            $sanitized_key = sanitize_text_field($key);
            
            if (is_array($value)) {
                $sanitized[$sanitized_key] = $this->sanitizeArrayRecursive($value);
            } elseif (is_string($value)) {
                $sanitized[$sanitized_key] = sanitize_textarea_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$sanitized_key] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$sanitized_key] = $value;
            } else {
                $sanitized[$sanitized_key] = sanitize_text_field((string) $value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Handle database operation failures
     * 
     * @param string $operation Database operation type
     * @param array $data Operation data
     * @param string $error Database error message
     */
    private function handleDatabaseFailure(string $operation, array $data, string $error): void {
        // Log to PSR-3 logger if available
        if ($this->logger) {
            $this->logger->error('Audit repository database failure', [
                'operation' => $operation,
                'table' => $this->table_name,
                'error' => $error,
                'data_keys' => array_keys($data)
            ]);
        }
        
        // Fallback to file logging if enabled
        if ($this->enable_file_fallback) {
            $this->logToFile('database_failure', [
                'operation' => $operation,
                'error' => $error,
                'data' => $data,
                'timestamp' => current_time('mysql')
            ]);
        }
        
        // Last resort: error_log
        error_log("AuditRepository DB Failure - {$operation}: {$error}");
    }
    
    /**
     * Handle save operation failures
     * 
     * @param AuditEvent $event Event that failed to save
     * @param \Exception $exception Exception that occurred
     */
    private function handleSaveFailure(AuditEvent $event, \Exception $exception): void {
        $failure_data = [
            'event_data' => $event->toArray(),
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'timestamp' => current_time('mysql')
        ];
        
        // Log to PSR-3 logger if available
        if ($this->logger) {
            $this->logger->error('Audit event save failure', $failure_data);
        }
        
        // Fallback to file logging if enabled
        if ($this->enable_file_fallback) {
            $this->logToFile('save_failure', $failure_data);
        }
        
        // Last resort: error_log
        error_log('AuditRepository Save Failure: ' . $exception->getMessage());
    }
    
    /**
     * Log data to file as fallback
     * 
     * @param string $type Log entry type
     * @param array $data Data to log
     */
    private function logToFile(string $type, array $data): void {
        try {
            $log_dir = WP_CONTENT_DIR . '/uploads/zippicks-logs/audit-repository/';
            
            // Create directory if it doesn't exist
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            
            $log_file = $log_dir . 'failures-' . date('Y-m-d') . '.log';
            
            $log_entry = [
                'type' => $type,
                'data' => $data,
                'logged_at' => current_time('mysql'),
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'memory_usage' => memory_get_usage(true),
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
                ]
            ];
            
            $log_line = json_encode($log_entry, JSON_PRETTY_PRINT) . PHP_EOL . str_repeat('-', 80) . PHP_EOL;
            
            file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
            
        } catch (\Exception $e) {
            // Silent failure to prevent infinite loops
            error_log('AuditRepository file logging failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Filter events by security criteria
     * 
     * @param array $criteria Filter criteria
     * @param int $limit Result limit
     * @param int $offset Result offset
     * @return array Filtered security events
     */
    public function findSecurityEvents(array $criteria = [], int $limit = 100, int $offset = 0): array {
        $security_criteria = array_merge($criteria, [
            'event_category' => AuditLogger::CATEGORY_SECURITY
        ]);
        
        return $this->find($security_criteria, $limit, $offset, 'created_at', 'DESC');
    }
    
    /**
     * Filter events by user
     * 
     * @param int $user_id User ID
     * @param array $additional_criteria Additional filter criteria
     * @param int $limit Result limit
     * @param int $offset Result offset
     * @return array User events
     */
    public function findByUser(int $user_id, array $additional_criteria = [], int $limit = 100, int $offset = 0): array {
        $criteria = array_merge($additional_criteria, [
            'user_id' => $user_id
        ]);
        
        return $this->find($criteria, $limit, $offset, 'created_at', 'DESC');
    }
    
    /**
     * Filter events by date range
     * 
     * @param string $date_from Start date (Y-m-d H:i:s format)
     * @param string $date_to End date (Y-m-d H:i:s format)
     * @param array $additional_criteria Additional filter criteria
     * @param int $limit Result limit
     * @param int $offset Result offset
     * @return array Events in date range
     */
    public function findByDateRange(string $date_from, string $date_to, array $additional_criteria = [], int $limit = 100, int $offset = 0): array {
        $criteria = array_merge($additional_criteria, [
            'date_from' => $date_from,
            'date_to' => $date_to
        ]);
        
        return $this->find($criteria, $limit, $offset, 'created_at', 'DESC');
    }
    
    /**
     * Filter high-severity events
     * 
     * @param array $additional_criteria Additional filter criteria
     * @param int $limit Result limit
     * @param int $offset Result offset
     * @return array High-severity events
     */
    public function findHighSeverityEvents(array $additional_criteria = [], int $limit = 100, int $offset = 0): array {
        $criteria = array_merge($additional_criteria, [
            'severity' => [AuditLogger::SEVERITY_ERROR, AuditLogger::SEVERITY_CRITICAL]
        ]);
        
        return $this->find($criteria, $limit, $offset, 'created_at', 'DESC');
    }
    
    /**
     * Get database indexing recommendations
     * 
     * @return array Indexing strategy and status
     */
    public function getIndexingStrategy(): array {
        global $wpdb;
        
        return [
            'recommended_indexes' => [
                'idx_event_type' => "CREATE INDEX idx_event_type ON {$this->table_name} (event_type)",
                'idx_event_category' => "CREATE INDEX idx_event_category ON {$this->table_name} (event_category)",
                'idx_user_id' => "CREATE INDEX idx_user_id ON {$this->table_name} (user_id)",
                'idx_created_at' => "CREATE INDEX idx_created_at ON {$this->table_name} (created_at)",
                'idx_severity' => "CREATE INDEX idx_severity ON {$this->table_name} (severity)",
                'idx_ip_address' => "CREATE INDEX idx_ip_address ON {$this->table_name} (ip_address)",
                'idx_event_type_created' => "CREATE INDEX idx_event_type_created ON {$this->table_name} (event_type, created_at)",
                'idx_category_severity' => "CREATE INDEX idx_category_severity ON {$this->table_name} (event_category, severity)"
            ],
            'performance_notes' => [
                'Primary key (id) provides fast lookups by ID',
                'event_type and created_at composite index optimizes timeline queries',
                'event_category and severity composite index optimizes security monitoring',
                'Individual indexes on frequently filtered columns improve query performance',
                'Consider partitioning by created_at for very large datasets'
            ]
        ];
    }
}