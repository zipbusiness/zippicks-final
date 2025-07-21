<?php
/**
 * PSR-3 compliant logger for Master Critic
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

/**
 * Logger class implementing PSR-3 interface concepts
 */
class ZipPicks_Master_Critic_Logger {
    
    /**
     * Log levels
     */
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';
    
    /**
     * Log file path
     *
     * @var string
     */
    protected string $log_file;
    
    /**
     * Log directory path
     *
     * @var string
     */
    protected string $log_dir;
    
    /**
     * Maximum log file size (10MB)
     *
     * @var int
     */
    protected int $max_file_size = 10485760;
    
    /**
     * Date format for log entries
     *
     * @var string
     */
    protected string $date_format = 'Y-m-d H:i:s';
    
    /**
     * Whether to log debug messages
     *
     * @var bool
     */
    protected bool $debug_enabled;
    
    /**
     * Log level priorities
     *
     * @var array
     */
    protected array $priorities = [
        self::EMERGENCY => 800,
        self::ALERT     => 700,
        self::CRITICAL  => 600,
        self::ERROR     => 500,
        self::WARNING   => 400,
        self::NOTICE    => 300,
        self::INFO      => 200,
        self::DEBUG     => 100
    ];
    
    /**
     * Current minimum log level
     *
     * @var string
     */
    protected string $min_level;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->setup_log_directory();
        $this->debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
        $this->min_level = get_option('zippicks_log_level', self::INFO);
    }
    
    /**
     * Set up log directory
     */
    protected function setup_log_directory(): void {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/zippicks-logs/master-critic';
        
        // Create directory if it doesn't exist
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            
            // Protect directory with .htaccess
            $htaccess = $this->log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }
            
            // Add index.php for extra protection
            $index = $this->log_dir . '/index.php';
            if (!file_exists($index)) {
                file_put_contents($index, "<?php // Silence is golden\n");
            }
        }
        
        $this->log_file = $this->log_dir . '/master-critic-' . date('Y-m') . '.log';
    }
    
    /**
     * System is unusable
     *
     * @param string $message
     * @param array $context
     */
    public function emergency(string $message, array $context = []): void {
        $this->log(self::EMERGENCY, $message, $context);
    }
    
    /**
     * Action must be taken immediately
     *
     * @param string $message
     * @param array $context
     */
    public function alert(string $message, array $context = []): void {
        $this->log(self::ALERT, $message, $context);
    }
    
    /**
     * Critical conditions
     *
     * @param string $message
     * @param array $context
     */
    public function critical(string $message, array $context = []): void {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Runtime errors
     *
     * @param string $message
     * @param array $context
     */
    public function error(string $message, array $context = []): void {
        $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Exceptional occurrences that are not errors
     *
     * @param string $message
     * @param array $context
     */
    public function warning(string $message, array $context = []): void {
        $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Normal but significant events
     *
     * @param string $message
     * @param array $context
     */
    public function notice(string $message, array $context = []): void {
        $this->log(self::NOTICE, $message, $context);
    }
    
    /**
     * Interesting events
     *
     * @param string $message
     * @param array $context
     */
    public function info(string $message, array $context = []): void {
        $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Detailed debug information
     *
     * @param string $message
     * @param array $context
     */
    public function debug(string $message, array $context = []): void {
        if ($this->debug_enabled) {
            $this->log(self::DEBUG, $message, $context);
        }
    }
    
    /**
     * Logs with an arbitrary level
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log(string $level, string $message, array $context = []): void {
        // Check if we should log this level
        if (!$this->should_log($level)) {
            return;
        }
        
        // Interpolate context values into message
        $message = $this->interpolate($message, $context);
        
        // Sanitize sensitive data
        $context = $this->sanitize_context($context);
        
        // Create log entry
        $entry = $this->format_entry($level, $message, $context);
        
        // Write to file
        $this->write_to_file($entry);
        
        // Also log to error_log if critical
        if ($this->is_critical_level($level)) {
            error_log($entry);
        }
        
        // Trigger alerts for emergency/alert levels
        if (in_array($level, [self::EMERGENCY, self::ALERT])) {
            $this->trigger_alert($level, $message, $context);
        }
    }
    
    /**
     * Check if should log based on level
     *
     * @param string $level
     * @return bool
     */
    protected function should_log(string $level): bool {
        $level_priority = $this->priorities[$level] ?? 0;
        $min_priority = $this->priorities[$this->min_level] ?? 0;
        
        return $level_priority >= $min_priority;
    }
    
    /**
     * Interpolate context values into message
     *
     * @param string $message
     * @param array $context
     * @return string
     */
    protected function interpolate(string $message, array $context): string {
        $replace = [];
        
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        
        return strtr($message, $replace);
    }
    
    /**
     * Sanitize sensitive data from context
     *
     * @param array $context
     * @return array
     */
    protected function sanitize_context(array $context): array {
        $sensitive_keys = [
            'password', 'pwd', 'pass', 'secret', 'key', 'token',
            'api_key', 'apikey', 'auth', 'authorization', 'credential'
        ];
        
        foreach ($context as $key => $value) {
            // Check if key contains sensitive data
            foreach ($sensitive_keys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $context[$key] = '[REDACTED]';
                    break;
                }
            }
            
            // Recursively sanitize arrays
            if (is_array($value)) {
                $context[$key] = $this->sanitize_context($value);
            }
        }
        
        return $context;
    }
    
    /**
     * Format log entry
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return string
     */
    protected function format_entry(string $level, string $message, array $context): string {
        $entry = [
            'timestamp' => date($this->date_format),
            'level' => strtoupper($level),
            'message' => $message,
            'user_id' => get_current_user_id() ?: 'guest',
            'ip' => $this->get_client_ip(),
            'url' => $this->get_current_url()
        ];
        
        if (!empty($context)) {
            $entry['context'] = $context;
        }
        
        // Add memory usage for performance monitoring
        if ($level === self::DEBUG || $this->debug_enabled) {
            $entry['memory'] = memory_get_usage(true);
            $entry['peak_memory'] = memory_get_peak_usage(true);
        }
        
        return json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    /**
     * Write entry to log file
     *
     * @param string $entry
     */
    protected function write_to_file(string $entry): void {
        // Check if we need to rotate the log
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_file_size) {
            $this->rotate_log();
        }
        
        // Write to file with lock
        $fp = fopen($this->log_file, 'a');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $entry);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }
    
    /**
     * Rotate log file
     */
    protected function rotate_log(): void {
        $backup_file = $this->log_file . '.' . time() . '.backup';
        rename($this->log_file, $backup_file);
        
        // Keep only last 5 backup files
        $backup_files = glob($this->log_dir . '/*.backup');
        if (count($backup_files) > 5) {
            usort($backup_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            $files_to_delete = array_slice($backup_files, 0, count($backup_files) - 5);
            foreach ($files_to_delete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Check if level is critical
     *
     * @param string $level
     * @return bool
     */
    protected function is_critical_level(string $level): bool {
        return in_array($level, [self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR]);
    }
    
    /**
     * Trigger alert for critical issues
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    protected function trigger_alert(string $level, string $message, array $context): void {
        // Send email to admin
        $admin_email = get_option('admin_email');
        $subject = sprintf('[%s] Master Critic %s Alert', get_bloginfo('name'), strtoupper($level));
        $body = sprintf(
            "Level: %s\nMessage: %s\nTime: %s\nContext: %s\n\nURL: %s",
            strtoupper($level),
            $message,
            date($this->date_format),
            json_encode($context, JSON_PRETTY_PRINT),
            $this->get_current_url()
        );
        
        wp_mail($admin_email, $subject, $body);
        
        // Trigger action hook for external monitoring
        do_action('zippicks_master_critic_alert', $level, $message, $context);
    }
    
    /**
     * Get client IP address
     *
     * @return string
     */
    protected function get_client_ip(): string {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Get current URL
     *
     * @return string
     */
    protected function get_current_url(): string {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $protocol = is_ssl() ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            return $protocol . $host . $_SERVER['REQUEST_URI'];
        }
        
        return 'cli';
    }
    
    /**
     * Clean old log files
     *
     * @param int $days Number of days to keep
     */
    public function clean_old_logs(int $days = 30): void {
        $cutoff_time = time() - ($days * DAY_IN_SECONDS);
        $log_files = glob($this->log_dir . '/*.log*');
        
        foreach ($log_files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
                $this->info('Deleted old log file', ['file' => basename($file)]);
            }
        }
    }
    
    /**
     * Get recent log entries
     *
     * @param int $limit
     * @param string|null $level
     * @return array
     */
    public function get_recent_entries(int $limit = 100, ?string $level = null): array {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $entries = [];
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Read from end of file
        for ($i = count($lines) - 1; $i >= 0 && count($entries) < $limit; $i--) {
            $entry = json_decode($lines[$i], true);
            
            if ($entry && (!$level || strtolower($entry['level'] ?? '') === $level)) {
                $entries[] = $entry;
            }
        }
        
        return array_reverse($entries);
    }
    
    /**
     * Get log statistics
     *
     * @return array
     */
    public function get_stats(): array {
        $stats = [
            'total_entries' => 0,
            'by_level' => [],
            'file_size' => 0,
            'oldest_entry' => null,
            'newest_entry' => null
        ];
        
        if (!file_exists($this->log_file)) {
            return $stats;
        }
        
        $stats['file_size'] = filesize($this->log_file);
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stats['total_entries'] = count($lines);
        
        foreach ($lines as $index => $line) {
            $entry = json_decode($line, true);
            if ($entry) {
                $level = strtolower($entry['level'] ?? 'unknown');
                $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;
                
                if ($index === 0) {
                    $stats['oldest_entry'] = $entry['timestamp'] ?? null;
                }
                if ($index === count($lines) - 1) {
                    $stats['newest_entry'] = $entry['timestamp'] ?? null;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Export logs
     *
     * @param string $format
     * @param array $filters
     * @return string
     */
    public function export_logs(string $format = 'json', array $filters = []): string {
        $entries = $this->get_recent_entries($filters['limit'] ?? 1000, $filters['level'] ?? null);
        
        switch ($format) {
            case 'csv':
                return $this->export_as_csv($entries);
            
            case 'json':
            default:
                return json_encode($entries, JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * Export entries as CSV
     *
     * @param array $entries
     * @return string
     */
    protected function export_as_csv(array $entries): string {
        if (empty($entries)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Headers
        fputcsv($output, ['Timestamp', 'Level', 'Message', 'User ID', 'IP', 'URL']);
        
        // Data
        foreach ($entries as $entry) {
            fputcsv($output, [
                $entry['timestamp'] ?? '',
                $entry['level'] ?? '',
                $entry['message'] ?? '',
                $entry['user_id'] ?? '',
                $entry['ip'] ?? '',
                $entry['url'] ?? ''
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}