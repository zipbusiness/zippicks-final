<?php
/**
 * Error Tracker for ZipPicks Smart Search
 * 
 * Handles error logging, monitoring, and external error service integration
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Error_Tracker {
    
    /**
     * Error log table name
     * @var string
     */
    const ERROR_LOG_OPTION = 'zippicks_search_error_log';
    
    /**
     * Maximum errors to keep in log
     * @var int
     */
    const MAX_ERROR_LOG_SIZE = 100;
    
    /**
     * Instance
     * @var Error_Tracker
     */
    private static $instance = null;
    
    /**
     * Previous error handler
     * @var callable|null
     */
    private $previous_error_handler = null;
    
    /**
     * Get instance
     * @return Error_Tracker
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // WordPress error handling
        add_action('wp_ajax_zippicks_report_error', [$this, 'handle_frontend_error']);
        add_action('wp_ajax_nopriv_zippicks_report_error', [$this, 'handle_frontend_error']);
        
        // PHP error handling - save previous handler for chaining
        $this->previous_error_handler = set_error_handler([$this, 'handle_php_error'], E_ERROR | E_WARNING | E_PARSE);
        register_shutdown_function([$this, 'handle_fatal_error']);
        
        // API error tracking
        add_action('zippicks_api_error', [$this, 'track_api_error'], 10, 3);
        
        // Search error tracking
        add_action('zippicks_search_error', [$this, 'track_search_error'], 10, 2);
        
        // Admin notifications
        add_action('admin_notices', [$this, 'show_critical_errors']);
        
        // Monitoring hooks
        add_filter('zippicks_monitor_health_check', [$this, 'add_error_metrics']);
        
        // Daily cleanup
        add_action('zippicks_daily_cleanup', [$this, 'cleanup_old_errors']);
    }
    
    /**
     * Track an error
     * 
     * @param string $type Error type
     * @param string $message Error message
     * @param array $context Additional context
     * @param string $severity Error severity (error, warning, notice)
     */
    public function track_error($type, $message, $context = [], $severity = 'error') {
        $error_data = [
            'timestamp' => current_time('timestamp'),
            'type' => sanitize_key($type),
            'message' => sanitize_text_field($message),
            'severity' => $severity,
            'context' => $this->sanitize_context($context),
            'user_id' => get_current_user_id(),
            'ip' => $this->get_client_ip(),
            'url' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'stack_trace' => $this->get_stack_trace()
        ];
        
        // Store in database
        $this->store_error($error_data);
        
        // Send to external services if configured
        $this->send_to_external_services($error_data);
        
        // Log to WordPress error log
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log(sprintf(
                '[ZipPicks Search] %s: %s - %s',
                strtoupper($severity),
                $type,
                $message
            ));
        }
        
        // Trigger action for custom handling
        do_action('zippicks_error_tracked', $error_data);
    }
    
    /**
     * Handle frontend JavaScript errors
     */
    public function handle_frontend_error() {
        // Verify nonce
        if (!check_ajax_referer('zippicks_search_nonce', 'nonce', false)) {
            wp_die('Security check failed', 403);
        }
        
        $error_data = [
            'message' => sanitize_text_field($_POST['message'] ?? 'Unknown error'),
            'source' => sanitize_text_field($_POST['source'] ?? ''),
            'lineno' => intval($_POST['lineno'] ?? 0),
            'colno' => intval($_POST['colno'] ?? 0),
            'stack' => sanitize_textarea_field($_POST['stack'] ?? '')
        ];
        
        $this->track_error('javascript', $error_data['message'], $error_data, 'error');
        
        wp_send_json_success(['tracked' => true]);
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
    public function handle_php_error($errno, $errstr, $errfile, $errline) {
        // Only track our plugin errors
        if (strpos($errfile, 'zippicks-smart-search') !== false) {
            $severity = 'warning';
            switch ($errno) {
                case E_ERROR:
                case E_PARSE:
                    $severity = 'error';
                    break;
                case E_WARNING:
                    $severity = 'warning';
                    break;
                case E_NOTICE:
                    $severity = 'notice';
                    break;
            }
            
            $this->track_error('php', $errstr, [
                'file' => $errfile,
                'line' => $errline,
                'errno' => $errno
            ], $severity);
        }
        
        // Chain to previous error handler if it exists
        if ($this->previous_error_handler && is_callable($this->previous_error_handler)) {
            return call_user_func($this->previous_error_handler, $errno, $errstr, $errfile, $errline);
        }
        
        // If no previous handler, let PHP handle it normally
        return false;
    }
    
    /**
     * Handle fatal errors
     */
    public function handle_fatal_error() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Only track our plugin errors
            if (strpos($error['file'], 'zippicks-smart-search') !== false) {
                $this->track_error('fatal', $error['message'], [
                    'file' => $error['file'],
                    'line' => $error['line'],
                    'type' => $error['type']
                ], 'error');
            }
        }
    }
    
    /**
     * Track API errors
     * 
     * @param string $endpoint API endpoint
     * @param string $error_message Error message
     * @param array $request_data Request data
     */
    public function track_api_error($endpoint, $error_message, $request_data = []) {
        $this->track_error('api', $error_message, [
            'endpoint' => $endpoint,
            'request' => $request_data,
            'response_time' => isset($request_data['response_time']) ? $request_data['response_time'] : null
        ], 'error');
    }
    
    /**
     * Track search errors
     * 
     * @param string $query Search query
     * @param string $error_message Error message
     */
    public function track_search_error($query, $error_message) {
        $this->track_error('search', $error_message, [
            'query' => $query,
            'location' => isset($_REQUEST['lat']) ? [
                'lat' => floatval($_REQUEST['lat']),
                'lng' => floatval($_REQUEST['lng'])
            ] : null
        ], 'warning');
    }
    
    /**
     * Store error in database
     * 
     * @param array $error_data Error data
     */
    private function store_error($error_data) {
        $errors = get_option(self::ERROR_LOG_OPTION, []);
        
        // Add new error
        array_unshift($errors, $error_data);
        
        // Keep only recent errors
        if (count($errors) > self::MAX_ERROR_LOG_SIZE) {
            $errors = array_slice($errors, 0, self::MAX_ERROR_LOG_SIZE);
        }
        
        update_option(self::ERROR_LOG_OPTION, $errors, false);
    }
    
    /**
     * Send error to external services
     * 
     * @param array $error_data Error data
     */
    private function send_to_external_services($error_data) {
        // Sentry integration
        if (defined('ZIPPICKS_SENTRY_DSN') && ZIPPICKS_SENTRY_DSN) {
            try {
                do_action('zippicks_send_to_sentry', $error_data);
            } catch (\Exception $e) {
                // Silently ignore to prevent cascading errors
                $this->log_failed_error_report('Sentry', $e->getMessage());
            }
        }
        
        // Rollbar integration
        if (defined('ZIPPICKS_ROLLBAR_TOKEN') && ZIPPICKS_ROLLBAR_TOKEN) {
            try {
                do_action('zippicks_send_to_rollbar', $error_data);
            } catch (\Exception $e) {
                // Silently ignore to prevent cascading errors
                $this->log_failed_error_report('Rollbar', $e->getMessage());
            }
        }
        
        // Custom error service
        $custom_endpoint = get_option('zippicks_error_service_endpoint');
        if ($custom_endpoint) {
            try {
                // Validate endpoint URL
                if (!filter_var($custom_endpoint, FILTER_VALIDATE_URL)) {
                    $this->log_failed_error_report('Custom Service', 'Invalid endpoint URL');
                    return;
                }
                
                $response = wp_remote_post($custom_endpoint, [
                    'body' => json_encode($error_data),
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Site-URL' => site_url()
                    ],
                    'timeout' => 5,
                    'blocking' => false,
                    'sslverify' => apply_filters('zippicks_error_service_ssl_verify', true)
                ]);
                
                // Check for WP_Error even with non-blocking requests
                if (is_wp_error($response)) {
                    $this->log_failed_error_report('Custom Service', $response->get_error_message());
                }
            } catch (\Exception $e) {
                // Catch any exceptions to prevent cascading errors
                $this->log_failed_error_report('Custom Service', $e->getMessage());
            }
        }
    }
    
    /**
     * Log failed error report attempts
     * 
     * @param string $service Service name
     * @param string $reason Failure reason
     */
    private function log_failed_error_report($service, $reason) {
        // Only log to file, don't store in database to prevent infinite loops
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log(sprintf(
                '[ZipPicks Search] Failed to send error to %s: %s',
                $service,
                $reason
            ));
        }
        
        // Store minimal failure info in transient for monitoring
        $failures = get_transient('zippicks_error_service_failures') ?: [];
        $failures[] = [
            'service' => $service,
            'time' => time(),
            'reason' => substr($reason, 0, 100) // Limit reason length
        ];
        
        // Keep only last 10 failures
        if (count($failures) > 10) {
            $failures = array_slice($failures, -10);
        }
        
        set_transient('zippicks_error_service_failures', $failures, HOUR_IN_SECONDS);
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                return sanitize_text_field($ip);
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Get stack trace
     * 
     * @return array
     */
    private function get_stack_trace() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        // Remove error handler frames
        while (!empty($trace) && isset($trace[0]['class']) && 
               $trace[0]['class'] === __CLASS__) {
            array_shift($trace);
        }
        
        return array_map(function($frame) {
            return [
                'file' => isset($frame['file']) ? str_replace(ABSPATH, '', $frame['file']) : '',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? '',
                'class' => $frame['class'] ?? ''
            ];
        }, $trace);
    }
    
    /**
     * Sanitize context data
     * 
     * @param array $context
     * @return array
     */
    private function sanitize_context($context) {
        $sanitized = [];
        
        foreach ($context as $key => $value) {
            $key = sanitize_key($key);
            
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_context($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Show critical errors in admin
     */
    public function show_critical_errors() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $errors = get_option(self::ERROR_LOG_OPTION, []);
        $critical_errors = array_filter($errors, function($error) {
            return $error['severity'] === 'error' && 
                   $error['timestamp'] > (time() - 3600); // Last hour
        });
        
        if (!empty($critical_errors)) {
            $count = count($critical_errors);
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('ZipPicks Smart Search:', 'zippicks-smart-search'); ?></strong>
                    <?php printf(
                        _n(
                            '%d critical error in the last hour.',
                            '%d critical errors in the last hour.',
                            $count,
                            'zippicks-smart-search'
                        ),
                        $count
                    ); ?>
                    <a href="<?php echo admin_url('admin.php?page=zippicks-search&tab=errors'); ?>">
                        <?php _e('View error log', 'zippicks-smart-search'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Add error metrics for health check
     * 
     * @param array $metrics
     * @return array
     */
    public function add_error_metrics($metrics) {
        $errors = get_option(self::ERROR_LOG_OPTION, []);
        
        $metrics['errors'] = [
            'total' => count($errors),
            'last_24h' => count(array_filter($errors, function($error) {
                return $error['timestamp'] > (time() - 86400);
            })),
            'by_type' => array_count_values(array_column($errors, 'type')),
            'by_severity' => array_count_values(array_column($errors, 'severity'))
        ];
        
        return $metrics;
    }
    
    /**
     * Cleanup old errors
     */
    public function cleanup_old_errors() {
        $errors = get_option(self::ERROR_LOG_OPTION, []);
        
        // Keep only last 7 days
        $cutoff = time() - (7 * 86400);
        $errors = array_filter($errors, function($error) use ($cutoff) {
            return $error['timestamp'] > $cutoff;
        });
        
        update_option(self::ERROR_LOG_OPTION, $errors, false);
    }
    
    /**
     * Get error summary
     * 
     * @param int $hours Number of hours to look back
     * @return array
     */
    public function get_error_summary($hours = 24) {
        $errors = get_option(self::ERROR_LOG_OPTION, []);
        $cutoff = time() - ($hours * 3600);
        
        $recent_errors = array_filter($errors, function($error) use ($cutoff) {
            return $error['timestamp'] > $cutoff;
        });
        
        return [
            'total' => count($recent_errors),
            'by_type' => array_count_values(array_column($recent_errors, 'type')),
            'by_severity' => array_count_values(array_column($recent_errors, 'severity')),
            'most_recent' => array_slice($recent_errors, 0, 5)
        ];
    }
}