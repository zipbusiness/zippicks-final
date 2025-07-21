<?php
/**
 * Error Handler for Master Critic Plugin
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Error_Handler {
    
    /**
     * Error log file path
     */
    private static $log_file = null;
    
    /**
     * Maximum log file size (5MB)
     */
    const MAX_LOG_SIZE = 5242880;
    
    /**
     * Initialize error handling
     */
    public static function init() {
        // Set up log file path
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/zippicks-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Protect log directory with .htaccess
            $htaccess = $log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }
        }
        
        self::$log_file = $log_dir . '/master-critic-' . date('Y-m') . '.log';
        
        // Set custom error handler for production
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            set_error_handler(array(__CLASS__, 'handle_error'));
            register_shutdown_function(array(__CLASS__, 'handle_shutdown'));
        }
        
        // Add admin notice for critical errors
        add_action('admin_notices', array(__CLASS__, 'display_critical_errors'));
    }
    
    /**
     * Handle PHP errors
     *
     * @param int $errno Error number
     * @param string $errstr Error string
     * @param string $errfile Error file
     * @param int $errline Error line
     * @return bool
     */
    public static function handle_error($errno, $errstr, $errfile, $errline) {
        // Only handle errors from our plugin
        if (strpos($errfile, 'zippicks-master-critic') === false) {
            return false;
        }
        
        // Sanitize error message to prevent sensitive data leaks
        if (file_exists(ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php')) {
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php';
            $sanitized_error = class_exists('ZipPicks_Master_Critic_Security') 
                ? ZipPicks_Master_Critic_Security::sanitize_error_message($errstr)
                : preg_replace('/([\'"])[^\'"]*\1/', '$1[REDACTED]$1', $errstr);
        } else {
            $sanitized_error = preg_replace('/([\'"])[^\'"]*\1/', '$1[REDACTED]$1', $errstr);
        }
        
        // Create structured log entry
        $log_entry = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => self::get_error_level($errno),
            'message' => $sanitized_error,
            'file' => basename($errfile),
            'line' => $errline,
            'trace' => WP_DEBUG ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) : null
        );
        
        // Log to custom file
        self::write_log($log_entry);
        
        // Also log to standard error log
        error_log(sprintf(
            '[Master Critic %s] %s in %s on line %d',
            $log_entry['level'],
            $sanitized_error,
            basename($errfile),
            $errline
        ));
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Handle fatal errors on shutdown
     */
    public static function handle_shutdown() {
        $error = error_get_last();
        
        if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
            // Check if it's from our plugin
            if (strpos($error['file'], 'zippicks-master-critic') !== false) {
                // Sanitize and log the fatal error
                if (file_exists(ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php')) {
                    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php';
                    $sanitized_message = class_exists('ZipPicks_Master_Critic_Security')
                        ? ZipPicks_Master_Critic_Security::sanitize_error_message($error['message'])
                        : preg_replace('/([\'"])[^\'"]*\1/', '$1[REDACTED]$1', $error['message']);
                } else {
                    $sanitized_message = preg_replace('/([\'"])[^\'"]*\1/', '$1[REDACTED]$1', $error['message']);
                }
                
                // Create structured log entry
                $log_entry = array(
                    'timestamp' => date('Y-m-d H:i:s'),
                    'level' => 'FATAL',
                    'message' => $sanitized_message,
                    'file' => basename($error['file']),
                    'line' => $error['line'],
                    'trace' => null
                );
                
                // Log to custom file
                self::write_log($log_entry);
                
                // Store critical error for admin display
                $critical_errors = get_transient('zippicks_master_critic_critical_errors');
                if (!is_array($critical_errors)) {
                    $critical_errors = array();
                }
                
                $critical_errors[] = array(
                    'message' => $sanitized_message,
                    'file' => basename($error['file']),
                    'line' => $error['line'],
                    'timestamp' => time()
                );
                
                // Keep only last 5 critical errors
                $critical_errors = array_slice($critical_errors, -5);
                set_transient('zippicks_master_critic_critical_errors', $critical_errors, HOUR_IN_SECONDS);
                
                // Also log to standard error log
                error_log(sprintf(
                    '[Master Critic FATAL] %s in %s on line %d',
                    $sanitized_message,
                    basename($error['file']),
                    $error['line']
                ));
                
                // If we're in an AJAX request, send error response
                if (defined('DOING_AJAX') && DOING_AJAX) {
                    wp_send_json_error(array(
                        'message' => 'A critical error occurred. Please check the error logs.',
                        'debug' => WP_DEBUG ? array(
                            'message' => $sanitized_message,
                            'file' => basename($error['file']),
                            'line' => $error['line']
                        ) : null
                    ));
                }
            }
        }
    }
    
    /**
     * Display admin error page
     *
     * @param string $message Error message
     * @param string $title Page title
     */
    public static function display_error_page($message, $title = 'Error') {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php echo esc_html($title); ?> - ZipPicks Master Critic</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: #f1f1f1;
                    margin: 0;
                    padding: 0;
                }
                .error-container {
                    max-width: 600px;
                    margin: 50px auto;
                    background: white;
                    padding: 30px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.13);
                }
                h1 {
                    color: #dc3232;
                    margin-top: 0;
                }
                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    background: #0073aa;
                    color: white;
                    text-decoration: none;
                    border-radius: 3px;
                    margin-top: 20px;
                }
                .button:hover {
                    background: #005a87;
                }
                .debug-info {
                    background: #f9f9f9;
                    border: 1px solid #e1e1e1;
                    padding: 15px;
                    margin-top: 20px;
                    font-family: monospace;
                    font-size: 12px;
                    overflow-x: auto;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1><?php echo esc_html($title); ?></h1>
                <p><?php echo wp_kses_post($message); ?></p>
                
                <?php if (defined('WP_DEBUG') && WP_DEBUG && !empty($_GET)): ?>
                <div class="debug-info">
                    <strong>Debug Information:</strong><br>
                    URL Parameters: <?php echo esc_html(print_r($_GET, true)); ?>
                </div>
                <?php endif; ?>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=zippicks-master-critic')); ?>" class="button">
                    ← Back to Master Critic
                </a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Log an error with context
     *
     * @param string $message Error message
     * @param array $context Additional context
     */
    public static function log_error($message, $context = array()) {
        // Sanitize message to prevent sensitive data leaks
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php';
        $sanitized_message = ZipPicks_Master_Critic_Security::sanitize_error_message($message);
        
        // Sanitize context data
        $sanitized_context = array();
        if (!empty($context)) {
            foreach ($context as $key => $value) {
                if (is_string($value)) {
                    $sanitized_context[$key] = ZipPicks_Master_Critic_Security::sanitize_error_message($value);
                } elseif (is_array($value)) {
                    // Don't log potentially sensitive arrays
                    $sanitized_context[$key] = '[ARRAY_REDACTED]';
                } else {
                    $sanitized_context[$key] = $value;
                }
            }
        }
        
        $log_message = '[Master Critic] ' . $sanitized_message;
        
        if (!empty($sanitized_context)) {
            $log_message .= ' | Context: ' . json_encode($sanitized_context);
        }
        
        error_log($log_message);
        
        // Also log to database if available
        if (class_exists('ZipPicks_Master_Critic_Database')) {
            ZipPicks_Master_Critic_Database::log_error($sanitized_message, $sanitized_context);
        }
    }
    
    /**
     * Get error level string from error number
     *
     * @param int $errno Error number
     * @return string
     */
    private static function get_error_level($errno) {
        switch ($errno) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'ERROR';
                
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'WARNING';
                
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
                return 'NOTICE';
                
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'DEPRECATED';
                
            default:
                return 'UNKNOWN';
        }
    }
    
    /**
     * Write log entry to file
     *
     * @param array $log_entry
     */
    private static function write_log($log_entry) {
        if (!self::$log_file) {
            return;
        }
        
        // Rotate log if too large
        if (file_exists(self::$log_file) && filesize(self::$log_file) > self::MAX_LOG_SIZE) {
            $backup = self::$log_file . '.1';
            if (file_exists($backup)) {
                unlink($backup);
            }
            rename(self::$log_file, $backup);
        }
        
        // Format log entry
        $formatted = sprintf(
            "[%s] %s: %s in %s:%d\n",
            $log_entry['timestamp'],
            $log_entry['level'],
            $log_entry['message'],
            $log_entry['file'],
            $log_entry['line']
        );
        
        // Write to log file
        file_put_contents(self::$log_file, $formatted, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Display critical errors in admin
     */
    public static function display_critical_errors() {
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check for recent critical errors
        $critical_errors = get_transient('zippicks_master_critic_critical_errors');
        
        if ($critical_errors && is_array($critical_errors)) {
            foreach ($critical_errors as $error) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        <strong>ZipPicks Master Critic Critical Error:</strong> 
                        <?php echo esc_html($error['message']); ?>
                        <?php if (WP_DEBUG): ?>
                            <br><small>File: <?php echo esc_html($error['file']); ?> Line: <?php echo esc_html($error['line']); ?></small>
                        <?php endif; ?>
                    </p>
                </div>
                <?php
            }
            
            // Clear after displaying
            delete_transient('zippicks_master_critic_critical_errors');
        }
    }
}