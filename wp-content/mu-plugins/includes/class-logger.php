<?php
/**
 * Logger
 * 
 * Handles logging for debugging and analytics across the platform.
 * 
 * @package ZipPicks\Foundation
 */

namespace ZipPicks\Foundation;

if (!defined('ABSPATH')) {
    exit;
}

class Logger {
    
    /**
     * Log levels
     * 
     * @var array
     */
    private $levels = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5
    ];
    
    /**
     * Current log level
     * 
     * @var string
     */
    private $log_level;
    
    /**
     * Log directory
     * 
     * @var string
     */
    private $log_dir;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->log_level = $this->get_log_level();
        $this->log_dir = $this->get_log_directory();
        $this->ensure_log_directory();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Log important events
        add_action('zippicks_error', [$this, 'log_error'], 10, 2);
        add_action('zippicks_warning', [$this, 'log_warning'], 10, 2);
        add_action('zippicks_info', [$this, 'log_info'], 10, 2);
        
        // Log performance metrics
        add_action('zippicks_performance', [$this, 'log_performance'], 10, 2);
        
        // Clean old logs
        add_action('zippicks_daily_maintenance', [$this, 'clean_old_logs']);
    }
    
    /**
     * Get log level
     * 
     * @return string Log level
     */
    private function get_log_level() {
        if (defined('ZIPPICKS_LOG_LEVEL')) {
            return ZIPPICKS_LOG_LEVEL;
        }
        
        return ZIPPICKS_ENABLE_DEBUG ? 'debug' : 'warning';
    }
    
    /**
     * Get log directory
     * 
     * @return string Directory path
     */
    private function get_log_directory() {
        if (defined('ZIPPICKS_LOG_DIR')) {
            return ZIPPICKS_LOG_DIR;
        }
        
        return WP_CONTENT_DIR . '/uploads/zippicks-logs/';
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensure_log_directory() {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            
            // Add .htaccess to prevent direct access
            $htaccess = $this->log_dir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Deny from all');
            }
            
            // Add index.php for extra security
            $index = $this->log_dir . 'index.php';
            if (!file_exists($index)) {
                file_put_contents($index, '<?php // Silence is golden');
            }
        }
    }
    
    /**
     * Log message
     * 
     * @param string $level Log level
     * @param string $message Message
     * @param array $context Context data
     */
    public function log($level, $message, $context = []) {
        // Check if we should log this level
        if (!$this->should_log($level)) {
            return;
        }
        
        // Prepare log entry
        $entry = $this->prepare_log_entry($level, $message, $context);
        
        // Write to file
        $this->write_to_file($level, $entry);
        
        // Write to database for critical errors
        if (in_array($level, ['error', 'critical'])) {
            $this->write_to_database($level, $message, $context);
        }
        
        // Trigger action for external logging
        do_action('zippicks_log_entry', $level, $message, $context);
    }
    
    /**
     * Check if should log this level
     * 
     * @param string $level Log level
     * @return bool Should log
     */
    private function should_log($level) {
        if (!isset($this->levels[$level]) || !isset($this->levels[$this->log_level])) {
            return false;
        }
        
        return $this->levels[$level] >= $this->levels[$this->log_level];
    }
    
    /**
     * Prepare log entry
     * 
     * @param string $level Log level
     * @param string $message Message
     * @param array $context Context
     * @return array Log entry
     */
    private function prepare_log_entry($level, $message, $context) {
        return [
            'timestamp' => current_time('mysql'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'ip' => $this->get_user_ip(),
            'url' => $this->get_current_url(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
    
    /**
     * Write to file
     * 
     * @param string $level Log level
     * @param array $entry Log entry
     */
    private function write_to_file($level, $entry) {
        $filename = $this->get_log_filename($level);
        $filepath = $this->log_dir . $filename;
        
        $log_line = sprintf(
            "[%s] %s: %s %s\n",
            $entry['timestamp'],
            $entry['level'],
            $entry['message'],
            !empty($entry['context']) ? json_encode($entry['context']) : ''
        );
        
        error_log($log_line, 3, $filepath);
    }
    
    /**
     * Get log filename
     * 
     * @param string $level Log level
     * @return string Filename
     */
    private function get_log_filename($level) {
        $date = current_time('Y-m-d');
        
        if (in_array($level, ['error', 'critical'])) {
            return "error-{$date}.log";
        }
        
        return "zippicks-{$date}.log";
    }
    
    /**
     * Write to database
     * 
     * @param string $level Log level
     * @param string $message Message
     * @param array $context Context
     */
    private function write_to_database($level, $message, $context) {
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'error_logs';
        
        $wpdb->insert($table, [
            'level' => $level,
            'message' => $message,
            'context' => json_encode($context),
            'user_id' => get_current_user_id() ?: null,
            'ip_address' => $this->get_user_ip(),
            'url' => $this->get_current_url(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Get user IP
     * 
     * @return string IP address
     */
    private function get_user_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }
        
        return '';
    }
    
    /**
     * Get current URL
     * 
     * @return string URL
     */
    private function get_current_url() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Message
     * @param array $context Context
     */
    public function debug($message, $context = []) {
        $this->log('debug', $message, $context);
    }
    
    /**
     * Log info message
     * 
     * @param string $message Message
     * @param array $context Context
     */
    public function info($message, $context = []) {
        $this->log('info', $message, $context);
    }
    
    /**
     * Log notice
     * 
     * @param string $message Message
     * @param array $context Context
     */
    public function notice($message, $context = []) {
        $this->log('notice', $message, $context);
    }
    
    /**
     * Log warning
     * 
     * @param string $message Message
     * @param array $context Context
     */
    public function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }
    
    /**
     * Log error
     * 
     * @param string $message Message
     * @param array $context Context
     */
    public function error($message, $context = []) {
        $this->log('error', $message, $context);
    }
    
    /**
     * Log critical error
     * 
     * @param string $message Message
     * @param array $context Context
     */
    public function critical($message, $context = []) {
        $this->log('critical', $message, $context);
    }
    
    /**
     * Log performance metrics
     * 
     * @param string $operation Operation name
     * @param array $metrics Performance metrics
     */
    public function log_performance($operation, $metrics) {
        $this->info('Performance: ' . $operation, $metrics);
        
        // Store in performance table for analysis
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'performance_logs';
        
        $wpdb->insert($table, [
            'operation' => $operation,
            'duration' => $metrics['duration'] ?? 0,
            'memory_used' => $metrics['memory'] ?? 0,
            'queries' => $metrics['queries'] ?? 0,
            'context' => json_encode($metrics),
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Log error (action callback)
     * 
     * @param string $message Error message
     * @param array $data Error data
     */
    public function log_error($message, $data = []) {
        $this->error($message, $data);
    }
    
    /**
     * Log warning (action callback)
     * 
     * @param string $message Warning message
     * @param array $data Warning data
     */
    public function log_warning($message, $data = []) {
        $this->warning($message, $data);
    }
    
    /**
     * Log info (action callback)
     * 
     * @param string $message Info message
     * @param array $data Info data
     */
    public function log_info($message, $data = []) {
        $this->info($message, $data);
    }
    
    /**
     * Clean old logs
     */
    public function clean_old_logs() {
        // Clean old log files (keep 30 days)
        $files = glob($this->log_dir . '*.log');
        $now = time();
        
        foreach ($files as $file) {
            if ($now - filemtime($file) > 30 * DAY_IN_SECONDS) {
                unlink($file);
            }
        }
        
        // Clean old database logs (keep 90 days for errors)
        global $wpdb;
        $error_table = ZIPPICKS_TABLE_PREFIX . 'error_logs';
        $perf_table = ZIPPICKS_TABLE_PREFIX . 'performance_logs';
        
        $wpdb->query("
            DELETE FROM {$error_table}
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        
        $wpdb->query("
            DELETE FROM {$perf_table}
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
    }
    
    /**
     * Get recent errors
     * 
     * @param int $limit Number of errors
     * @return array Recent errors
     */
    public function get_recent_errors($limit = 50) {
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'error_logs';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table}
            ORDER BY created_at DESC
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Get error statistics
     * 
     * @param int $days Number of days
     * @return array Error stats
     */
    public function get_error_stats($days = 7) {
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'error_logs';
        
        $stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                level,
                COUNT(*) as count,
                DATE(created_at) as date
            FROM {$table}
            WHERE created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY level, DATE(created_at)
            ORDER BY date DESC, level
        ", $days));
        
        $formatted = [];
        foreach ($stats as $stat) {
            $formatted[$stat->date][$stat->level] = intval($stat->count);
        }
        
        return $formatted;
    }
    
    /**
     * Get performance statistics
     * 
     * @param string $operation Operation name
     * @param int $days Number of days
     * @return array Performance stats
     */
    public function get_performance_stats($operation = null, $days = 7) {
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'performance_logs';
        
        $where = $operation ? $wpdb->prepare(' AND operation = %s', $operation) : '';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                operation,
                AVG(duration) as avg_duration,
                MAX(duration) as max_duration,
                MIN(duration) as min_duration,
                COUNT(*) as count
            FROM {$table}
            WHERE created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
            {$where}
            GROUP BY operation
            ORDER BY avg_duration DESC
        ", $days));
    }
    
    /**
     * Export logs
     * 
     * @param string $type Log type
     * @param array $filters Filters
     * @return string File path
     */
    public function export_logs($type = 'all', $filters = []) {
        $export_dir = $this->log_dir . 'exports/';
        wp_mkdir_p($export_dir);
        
        $filename = sprintf(
            'zippicks-logs-%s-%s.csv',
            $type,
            date('Y-m-d-His')
        );
        
        $filepath = $export_dir . $filename;
        $handle = fopen($filepath, 'w');
        
        // Write headers
        fputcsv($handle, ['Timestamp', 'Level', 'Message', 'User', 'URL', 'Context']);
        
        // Get logs based on type
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'error_logs';
        
        $query = "SELECT * FROM {$table} WHERE 1=1";
        
        if ($type !== 'all' && isset($this->levels[$type])) {
            $query .= $wpdb->prepare(" AND level = %s", $type);
        }
        
        if (!empty($filters['start_date'])) {
            $query .= $wpdb->prepare(" AND created_at >= %s", $filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $query .= $wpdb->prepare(" AND created_at <= %s", $filters['end_date']);
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $logs = $wpdb->get_results($query);
        
        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->created_at,
                $log->level,
                $log->message,
                $log->user_id ?: 'Guest',
                $log->url,
                $log->context
            ]);
        }
        
        fclose($handle);
        
        return $filepath;
    }
}