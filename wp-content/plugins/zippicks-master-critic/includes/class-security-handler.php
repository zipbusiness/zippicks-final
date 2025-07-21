<?php
/**
 * Enterprise Security Handler for Master Critic
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

/**
 * Security handler implementing defense in depth
 */
class ZipPicks_Master_Critic_Security_Handler {
    
    /**
     * Nonce action prefix
     *
     * @var string
     */
    const NONCE_PREFIX = 'zippicks_mc_';
    
    /**
     * Rate limit window (seconds)
     *
     * @var int
     */
    const RATE_LIMIT_WINDOW = 60;
    
    /**
     * Maximum requests per window
     *
     * @var int
     */
    const MAX_REQUESTS = 30;
    
    /**
     * Security headers
     *
     * @var array
     */
    protected array $security_headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';"
    ];
    
    /**
     * Blocked IP addresses
     *
     * @var array
     */
    protected array $blocked_ips = [];
    
    /**
     * Suspicious patterns
     *
     * @var array
     */
    protected array $suspicious_patterns = [
        '/\.\.[\/\\\\]/',              // Directory traversal
        '/<script[^>]*>.*?<\/script>/i', // Script injection
        '/union.*select/i',             // SQL injection
        '/exec\s*\(/i',                 // Command execution
        '/eval\s*\(/i',                 // Code evaluation
        '/base64_decode/i',             // Base64 decode attempts
        '/phpinfo\s*\(/i',              // PHP info disclosure
        '/system\s*\(/i',               // System calls
        '/shell_exec/i',                // Shell execution
        '/\${.*}/i'                     // Variable injection
    ];
    
    /**
     * Logger instance
     *
     * @var ZipPicks_Master_Critic_Logger|null
     */
    protected ?ZipPicks_Master_Critic_Logger $logger = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load logger if available
        if (class_exists('ZipPicks_Master_Critic_Logger')) {
            $this->logger = new ZipPicks_Master_Critic_Logger();
        }
        
        // Load blocked IPs
        $this->blocked_ips = get_option('zippicks_blocked_ips', []);
        
        // Initialize security measures
        $this->init_security();
    }
    
    /**
     * Initialize security measures
     */
    protected function init_security(): void {
        // Add security headers
        add_action('send_headers', [$this, 'add_security_headers']);
        
        // Check for blocked IPs
        add_action('init', [$this, 'check_blocked_ip'], 1);
        
        // Validate requests
        add_action('init', [$this, 'validate_request'], 2);
        
        // Monitor failed login attempts
        add_action('wp_login_failed', [$this, 'track_failed_login']);
        
        // Clean up old security data
        add_action('zippicks_security_cleanup', [$this, 'cleanup_security_data']);
        
        // Schedule cleanup if not scheduled
        if (!wp_next_scheduled('zippicks_security_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'zippicks_security_cleanup');
        }
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers(): void {
        foreach ($this->security_headers as $header => $value) {
            header("$header: $value");
        }
        
        // Add HSTS if SSL
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Check if current IP is blocked
     */
    public function check_blocked_ip(): void {
        $ip = $this->get_client_ip();
        
        if (in_array($ip, $this->blocked_ips)) {
            $this->log_security_event('blocked_ip_access', ['ip' => $ip]);
            wp_die('Access Denied', 'Forbidden', ['response' => 403]);
        }
    }
    
    /**
     * Validate incoming request
     */
    public function validate_request(): void {
        // Check rate limiting
        if (!$this->check_rate_limit()) {
            $this->handle_rate_limit_exceeded();
        }
        
        // Validate request data
        $this->validate_request_data();
        
        // Check for suspicious patterns
        $this->check_suspicious_patterns();
    }
    
    /**
     * Verify nonce with enhanced security
     *
     * @param string $nonce
     * @param string $action
     * @return bool
     */
    public function verify_nonce(string $nonce, string $action): bool {
        $action = self::NONCE_PREFIX . $action;
        
        // Standard WordPress nonce verification
        if (!wp_verify_nonce($nonce, $action)) {
            $this->log_security_event('invalid_nonce', [
                'action' => $action,
                'ip' => $this->get_client_ip()
            ]);
            return false;
        }
        
        // Additional timestamp validation
        $nonce_age = $this->get_nonce_age($nonce);
        if ($nonce_age > 12 * HOUR_IN_SECONDS) {
            $this->log_security_event('expired_nonce', [
                'action' => $action,
                'age' => $nonce_age
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Create enhanced nonce
     *
     * @param string $action
     * @return string
     */
    public function create_nonce(string $action): string {
        $action = self::NONCE_PREFIX . $action;
        return wp_create_nonce($action);
    }
    
    /**
     * Sanitize input data
     *
     * @param mixed $data
     * @param string $type
     * @return mixed
     */
    public function sanitize_input($data, string $type = 'text') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return $this->sanitize_input($item, $type);
            }, $data);
        }
        
        switch ($type) {
            case 'email':
                return sanitize_email($data);
                
            case 'url':
                return esc_url_raw($data);
                
            case 'int':
                return intval($data);
                
            case 'float':
                return floatval($data);
                
            case 'bool':
                return filter_var($data, FILTER_VALIDATE_BOOLEAN);
                
            case 'html':
                return wp_kses_post($data);
                
            case 'textarea':
                return sanitize_textarea_field($data);
                
            case 'sql':
                global $wpdb;
                return $wpdb->prepare('%s', $data);
                
            case 'key':
                return sanitize_key($data);
                
            case 'filename':
                return sanitize_file_name($data);
                
            case 'json':
                $decoded = json_decode($data, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
                
            case 'text':
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * Validate capability with logging
     *
     * @param string $capability
     * @param int|null $user_id
     * @return bool
     */
    public function check_capability(string $capability, ?int $user_id = null): bool {
        $user_id = $user_id ?: get_current_user_id();
        
        if (!user_can($user_id, $capability)) {
            $this->log_security_event('capability_check_failed', [
                'capability' => $capability,
                'user_id' => $user_id,
                'ip' => $this->get_client_ip()
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate AJAX request
     *
     * @param string $action
     * @param string $capability
     * @return bool
     */
    public function validate_ajax_request(string $action, string $capability = 'manage_options'): bool {
        // Check if AJAX request
        if (!wp_doing_ajax()) {
            return false;
        }
        
        // Verify nonce
        $nonce = $_REQUEST['nonce'] ?? $_REQUEST['_ajax_nonce'] ?? '';
        if (!$this->verify_nonce($nonce, $action)) {
            wp_send_json_error('Invalid security token');
            return false;
        }
        
        // Check capability
        if (!$this->check_capability($capability)) {
            wp_send_json_error('Insufficient permissions');
            return false;
        }
        
        // Check rate limit
        if (!$this->check_rate_limit('ajax_' . $action)) {
            wp_send_json_error('Rate limit exceeded');
            return false;
        }
        
        return true;
    }
    
    /**
     * Check rate limiting
     *
     * @param string $action
     * @return bool
     */
    public function check_rate_limit(string $action = 'general'): bool {
        $ip = $this->get_client_ip();
        $key = 'rate_limit_' . md5($ip . '_' . $action);
        
        $attempts = get_transient($key) ?: 0;
        
        if ($attempts >= self::MAX_REQUESTS) {
            $this->log_security_event('rate_limit_exceeded', [
                'action' => $action,
                'ip' => $ip,
                'attempts' => $attempts
            ]);
            return false;
        }
        
        set_transient($key, $attempts + 1, self::RATE_LIMIT_WINDOW);
        
        return true;
    }
    
    /**
     * Block IP address
     *
     * @param string $ip
     * @param string $reason
     * @param int $duration
     */
    public function block_ip(string $ip, string $reason = '', int $duration = 0): void {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return;
        }
        
        $this->blocked_ips[] = $ip;
        $this->blocked_ips = array_unique($this->blocked_ips);
        
        update_option('zippicks_blocked_ips', $this->blocked_ips);
        
        // Log the block
        $this->log_security_event('ip_blocked', [
            'ip' => $ip,
            'reason' => $reason,
            'duration' => $duration
        ]);
        
        // Schedule unblock if duration specified
        if ($duration > 0) {
            wp_schedule_single_event(
                time() + $duration,
                'zippicks_unblock_ip',
                [$ip]
            );
        }
    }
    
    /**
     * Unblock IP address
     *
     * @param string $ip
     */
    public function unblock_ip(string $ip): void {
        $this->blocked_ips = array_diff($this->blocked_ips, [$ip]);
        update_option('zippicks_blocked_ips', $this->blocked_ips);
        
        $this->log_security_event('ip_unblocked', ['ip' => $ip]);
    }
    
    /**
     * Track failed login attempt
     *
     * @param string $username
     */
    public function track_failed_login(string $username): void {
        $ip = $this->get_client_ip();
        $key = 'failed_login_' . md5($ip);
        
        $attempts = get_transient($key) ?: 0;
        $attempts++;
        
        set_transient($key, $attempts, HOUR_IN_SECONDS);
        
        // Block after 5 failed attempts
        if ($attempts >= 5) {
            $this->block_ip($ip, 'Too many failed login attempts', HOUR_IN_SECONDS);
        }
        
        $this->log_security_event('failed_login', [
            'username' => $username,
            'ip' => $ip,
            'attempts' => $attempts
        ]);
    }
    
    /**
     * Generate secure token
     *
     * @param string $purpose
     * @return string
     */
    public function generate_token(string $purpose = 'general'): string {
        $data = [
            'purpose' => $purpose,
            'time' => time(),
            'user' => get_current_user_id(),
            'salt' => wp_generate_password(32, false)
        ];
        
        $token = base64_encode(json_encode($data));
        $hash = hash_hmac('sha256', $token, wp_salt('auth'));
        
        return $token . '.' . $hash;
    }
    
    /**
     * Verify secure token
     *
     * @param string $token
     * @param string $purpose
     * @param int $max_age
     * @return bool
     */
    public function verify_token(string $token, string $purpose = 'general', int $max_age = 3600): bool {
        $parts = explode('.', $token);
        
        if (count($parts) !== 2) {
            return false;
        }
        
        [$data_part, $hash_part] = $parts;
        
        // Verify hash
        $expected_hash = hash_hmac('sha256', $data_part, wp_salt('auth'));
        if (!hash_equals($expected_hash, $hash_part)) {
            $this->log_security_event('invalid_token_hash', ['purpose' => $purpose]);
            return false;
        }
        
        // Decode data
        $data = json_decode(base64_decode($data_part), true);
        
        if (!$data || $data['purpose'] !== $purpose) {
            return false;
        }
        
        // Check age
        if (time() - $data['time'] > $max_age) {
            $this->log_security_event('expired_token', ['purpose' => $purpose]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Encrypt sensitive data
     *
     * @param string $data
     * @return string
     */
    public function encrypt(string $data): string {
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     *
     * @param string $encrypted
     * @return string|false
     */
    public function decrypt(string $encrypted) {
        $key = $this->get_encryption_key();
        $data = base64_decode($encrypted);
        
        if (strlen($data) < 16) {
            return false;
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
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
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Validate request data
     */
    protected function validate_request_data(): void {
        // Check request size
        if (!empty($_SERVER['CONTENT_LENGTH'])) {
            $max_size = wp_max_upload_size();
            if ($_SERVER['CONTENT_LENGTH'] > $max_size) {
                $this->log_security_event('oversized_request', [
                    'size' => $_SERVER['CONTENT_LENGTH'],
                    'max' => $max_size
                ]);
                wp_die('Request too large', 'Bad Request', ['response' => 413]);
            }
        }
        
        // Validate HTTP methods
        $allowed_methods = ['GET', 'POST', 'HEAD'];
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if (!in_array($method, $allowed_methods)) {
            $this->log_security_event('invalid_http_method', ['method' => $method]);
            wp_die('Method not allowed', 'Method Not Allowed', ['response' => 405]);
        }
    }
    
    /**
     * Check for suspicious patterns
     */
    protected function check_suspicious_patterns(): void {
        $check_data = [
            $_GET,
            $_POST,
            $_COOKIE,
            $_SERVER['REQUEST_URI'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? ''
        ];
        
        foreach ($check_data as $data) {
            if (is_array($data)) {
                $data = json_encode($data);
            }
            
            foreach ($this->suspicious_patterns as $pattern) {
                if (preg_match($pattern, $data)) {
                    $this->log_security_event('suspicious_pattern_detected', [
                        'pattern' => $pattern,
                        'data' => substr($data, 0, 100) . '...'
                    ]);
                    
                    // Don't block immediately, but track
                    $this->increment_threat_score();
                    break;
                }
            }
        }
    }
    
    /**
     * Handle rate limit exceeded
     */
    protected function handle_rate_limit_exceeded(): void {
        header('Retry-After: ' . self::RATE_LIMIT_WINDOW);
        wp_die(
            'Too many requests. Please try again later.',
            'Rate Limit Exceeded',
            ['response' => 429]
        );
    }
    
    /**
     * Get nonce age
     *
     * @param string $nonce
     * @return int
     */
    protected function get_nonce_age(string $nonce): int {
        $tick = wp_nonce_tick();
        $expected = substr(wp_hash($tick . '|' . self::NONCE_PREFIX, 'nonce'), -12, 10);
        
        if (hash_equals($expected, $nonce)) {
            return 0;
        }
        
        // Check previous tick
        $expected = substr(wp_hash(($tick - 1) . '|' . self::NONCE_PREFIX, 'nonce'), -12, 10);
        
        if (hash_equals($expected, $nonce)) {
            return 12 * HOUR_IN_SECONDS;
        }
        
        return 24 * HOUR_IN_SECONDS; // Too old
    }
    
    /**
     * Get encryption key
     *
     * @return string
     */
    protected function get_encryption_key(): string {
        $key = get_option('zippicks_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, false);
            update_option('zippicks_encryption_key', $key);
        }
        
        return hash('sha256', $key . wp_salt('auth'));
    }
    
    /**
     * Increment threat score for IP
     */
    protected function increment_threat_score(): void {
        $ip = $this->get_client_ip();
        $key = 'threat_score_' . md5($ip);
        
        $score = get_transient($key) ?: 0;
        $score++;
        
        set_transient($key, $score, DAY_IN_SECONDS);
        
        // Auto-block at threshold
        if ($score >= 10) {
            $this->block_ip($ip, 'High threat score', DAY_IN_SECONDS);
        }
    }
    
    /**
     * Log security event
     *
     * @param string $event
     * @param array $data
     */
    protected function log_security_event(string $event, array $data = []): void {
        $data['event'] = $event;
        $data['timestamp'] = current_time('mysql');
        $data['ip'] = $data['ip'] ?? $this->get_client_ip();
        $data['user_id'] = get_current_user_id();
        $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Log to database
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_security_log';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $wpdb->insert(
                $table,
                [
                    'event_type' => $event,
                    'event_data' => json_encode($data),
                    'ip_address' => $data['ip'],
                    'user_id' => $data['user_id'],
                    'created_at' => $data['timestamp']
                ],
                ['%s', '%s', '%s', '%d', '%s']
            );
        }
        
        // Also log to file
        if ($this->logger) {
            $level = $this->get_event_log_level($event);
            $this->logger->log($level, "Security Event: $event", $data);
        }
    }
    
    /**
     * Get log level for security event
     *
     * @param string $event
     * @return string
     */
    protected function get_event_log_level(string $event): string {
        $critical_events = [
            'blocked_ip_access',
            'ip_blocked',
            'suspicious_pattern_detected',
            'invalid_token_hash'
        ];
        
        if (in_array($event, $critical_events)) {
            return 'warning';
        }
        
        return 'info';
    }
    
    /**
     * Clean up old security data
     */
    public function cleanup_security_data(): void {
        global $wpdb;
        
        // Clean old security logs (keep 30 days)
        $table = $wpdb->prefix . 'zippicks_security_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $table WHERE created_at < %s",
                    date('Y-m-d H:i:s', strtotime('-30 days'))
                )
            );
        }
        
        // Clean expired IP blocks
        $blocked_ips = get_option('zippicks_blocked_ips', []);
        $temp_blocks = get_option('zippicks_temp_ip_blocks', []);
        
        foreach ($temp_blocks as $ip => $expiry) {
            if ($expiry < time()) {
                unset($temp_blocks[$ip]);
                $blocked_ips = array_diff($blocked_ips, [$ip]);
            }
        }
        
        update_option('zippicks_blocked_ips', $blocked_ips);
        update_option('zippicks_temp_ip_blocks', $temp_blocks);
    }
    
    /**
     * Get security status
     *
     * @return array
     */
    public function get_security_status(): array {
        global $wpdb;
        
        $status = [
            'blocked_ips' => count($this->blocked_ips),
            'recent_threats' => 0,
            'failed_logins' => 0,
            'rate_limits' => 0
        ];
        
        // Count recent security events
        $table = $wpdb->prefix . 'zippicks_security_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $status['recent_threats'] = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table 
                     WHERE event_type IN ('suspicious_pattern_detected', 'rate_limit_exceeded')
                     AND created_at > %s",
                    date('Y-m-d H:i:s', strtotime('-24 hours'))
                )
            );
            
            $status['failed_logins'] = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table 
                     WHERE event_type = 'failed_login'
                     AND created_at > %s",
                    date('Y-m-d H:i:s', strtotime('-24 hours'))
                )
            );
        }
        
        return $status;
    }
}