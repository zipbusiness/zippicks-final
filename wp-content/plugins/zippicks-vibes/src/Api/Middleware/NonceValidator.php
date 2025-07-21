<?php
/**
 * Nonce Validator Middleware
 * 
 * Enhanced nonce validation with multiple header support and structured logging
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Api\Middleware;

use WP_REST_Request;

/**
 * Class NonceValidator
 */
class NonceValidator {
    
    /**
     * Nonce action name
     * 
     * @var string
     */
    private string $action = 'wp_rest';
    
    /**
     * Supported headers for nonce
     * 
     * @var array
     */
    private array $supported_headers = [
        'X-WP-Nonce',
        'Authorization'
    ];
    
    /**
     * Routes that skip nonce validation
     * 
     * @var array
     */
    private array $public_routes = [];
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger = null;
    
    /**
     * Audit logger instance
     * 
     * @var mixed
     */
    private $auditLogger = null;
    
    /**
     * Constructor
     * 
     * @param string $action Nonce action name
     * @param mixed $logger Logger instance
     * @param mixed $auditLogger Audit logger instance
     */
    public function __construct(string $action = 'wp_rest', $logger = null, $auditLogger = null) {
        $this->action = $action;
        $this->logger = $logger;
        $this->auditLogger = $auditLogger;
        
        // Define public routes that don't require nonce
        $this->public_routes = [
            'GET' => [
                '/zippicks/v1/vibes',
                '/zippicks/v1/vibes/search',
                '/zippicks/v1/vibes/popular',
                '/zippicks/v1/vibes/categories',
                '/zippicks/v1/vibes/autocomplete',
                '/zippicks/v1/vibes/\d+'
            ],
            'POST' => [
                '/zippicks/v1/vibes/track',
                '/zippicks/v1/vibes/waitlist'
            ]
        ];
    }
    
    /**
     * Validate nonce from request
     * 
     * @param \WP_REST_Request $request
     * @return array Structured result with success status and reason
     */
    public function validate(\WP_REST_Request $request): array {
        // Check if this is a public endpoint
        if ($this->isPublicEndpoint($request)) {
            return [
                'success' => true,
                'reason' => 'Public endpoint - no nonce required'
            ];
        }
        
        // Get nonce from various sources
        $nonce = $this->getNonce($request);
        
        if (empty($nonce)) {
            $result = [
                'success' => false,
                'reason' => 'Missing security token'
            ];
            $this->logValidationFailure('missing_nonce', $request, $result['reason']);
            return $result;
        }
        
        // Verify the nonce
        if (!wp_verify_nonce($nonce, $this->action)) {
            $result = [
                'success' => false,
                'reason' => 'Invalid or expired security token'
            ];
            $this->logValidationFailure('invalid_nonce', $request, $result['reason']);
            return $result;
        }
        
        // Verify session binding (anti-scraping requirement)
        if (!$this->verifySessionBinding($nonce, $request)) {
            $result = [
                'success' => false,
                'reason' => 'Session binding validation failed'
            ];
            $this->logValidationFailure('session_binding_failed', $request, $result['reason']);
            return $result;
        }
        
        // Additional security checks
        $additional_checks = $this->performAdditionalChecks($request);
        if (!$additional_checks['success']) {
            $this->logValidationFailure('additional_check_failed', $request, $additional_checks['reason']);
            return $additional_checks;
        }
        
        // Log successful validation
        $this->logValidationSuccess($request);
        
        return [
            'success' => true,
            'reason' => 'Validation successful'
        ];
    }
    
    /**
     * Get nonce from request using multiple sources
     * 
     * @param \WP_REST_Request $request
     * @return string|null
     */
    private function getNonce(\WP_REST_Request $request): ?string {
        // Check supported headers
        foreach ($this->supported_headers as $header) {
            $value = $request->get_header($header);
            if (!empty($value)) {
                // Handle Authorization header with Bearer token
                if ($header === 'Authorization' && stripos($value, 'Bearer ') === 0) {
                    return substr($value, 7); // Remove 'Bearer ' prefix
                }
                return $value;
            }
        }
        
        // Check _wpnonce parameter
        $nonce = $request->get_param('_wpnonce');
        if (!empty($nonce)) {
            return $nonce;
        }
        
        // Check nonce parameter
        $nonce = $request->get_param('nonce');
        if (!empty($nonce)) {
            return $nonce;
        }
        
        // Check in JSON body for POST/PUT requests
        if (in_array($request->get_method(), ['POST', 'PUT', 'PATCH'])) {
            $body = $request->get_json_params();
            if (is_array($body)) {
                if (isset($body['_wpnonce'])) {
                    return $body['_wpnonce'];
                }
                if (isset($body['nonce'])) {
                    return $body['nonce'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Check if endpoint is public
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    private function isPublicEndpoint(\WP_REST_Request $request): bool {
        $route = $request->get_route();
        $method = $request->get_method();
        
        if (isset($this->public_routes[$method])) {
            foreach ($this->public_routes[$method] as $pattern) {
                // Convert pattern to regex
                $regex = '#^' . str_replace('\d+', '\d+', preg_quote($pattern, '#')) . '$#';
                if (preg_match($regex, $route)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Add route to skip nonce validation
     * 
     * @param string $method HTTP method
     * @param string $route Route pattern
     */
    public function addPublicRoute(string $method, string $route): void {
        if (!isset($this->public_routes[$method])) {
            $this->public_routes[$method] = [];
        }
        $this->public_routes[$method][] = $route;
    }
    
    /**
     * Perform additional security checks
     * 
     * @param \WP_REST_Request $request
     * @return array
     */
    private function performAdditionalChecks(\WP_REST_Request $request): array {
        // Check referer for same-origin
        $referer_check = $this->checkReferer($request);
        if (!$referer_check['success']) {
            return $referer_check;
        }
        
        // Check user capabilities for protected endpoints
        $capability_check = $this->checkCapabilities($request);
        if (!$capability_check['success']) {
            return $capability_check;
        }
        
        // Check for suspicious patterns
        $suspicious_check = $this->detectSuspiciousActivity($request);
        if (!$suspicious_check['success']) {
            return $suspicious_check;
        }
        
        return [
            'success' => true,
            'reason' => 'All checks passed'
        ];
    }
    
    /**
     * Check referer
     * 
     * @param \WP_REST_Request $request
     * @return array
     */
    private function checkReferer(\WP_REST_Request $request): array {
        // Skip referer check for GET requests
        if ($request->get_method() === 'GET') {
            return ['success' => true, 'reason' => 'GET request - referer check skipped'];
        }
        
        $referer = $request->get_header('referer');
        if (empty($referer)) {
            // No referer might be legitimate (direct API access)
            return ['success' => true, 'reason' => 'No referer - allowed for API access'];
        }
        
        $site_url = parse_url(home_url(), PHP_URL_HOST);
        $referer_host = parse_url($referer, PHP_URL_HOST);
        
        // Allow same-origin requests
        if ($referer_host === $site_url) {
            return ['success' => true, 'reason' => 'Same-origin request'];
        }
        
        // Allow configured external domains
        $allowed_origins = apply_filters('zippicks_vibes_allowed_origins', []);
        foreach ($allowed_origins as $origin) {
            if (strpos($referer_host, $origin) !== false) {
                return ['success' => true, 'reason' => 'Allowed external origin'];
            }
        }
        
        return [
            'success' => false,
            'reason' => 'Cross-origin request from unauthorized domain'
        ];
    }
    
    /**
     * Check user capabilities
     * 
     * @param \WP_REST_Request $request
     * @return array
     */
    private function checkCapabilities(\WP_REST_Request $request): array {
        $route = $request->get_route();
        $method = $request->get_method();
        
        // Define capability requirements
        $capability_map = [
            'POST' => [
                '/zippicks/v1/vibes' => 'manage_options',
                '/zippicks/v1/vibes/reorder' => 'manage_options',
            ],
            'PUT' => [
                '/zippicks/v1/vibes/\d+' => 'manage_options',
            ],
            'DELETE' => [
                '/zippicks/v1/vibes/\d+' => 'manage_options',
            ]
        ];
        
        if (isset($capability_map[$method])) {
            foreach ($capability_map[$method] as $pattern => $capability) {
                $regex = '#^' . str_replace('\d+', '\d+', preg_quote($pattern, '#')) . '$#';
                if (preg_match($regex, $route)) {
                    if (!current_user_can($capability)) {
                        return [
                            'success' => false,
                            'reason' => 'Insufficient permissions for this action'
                        ];
                    }
                }
            }
        }
        
        return ['success' => true, 'reason' => 'Capability check passed'];
    }
    
    /**
     * Detect suspicious activity
     * 
     * @param \WP_REST_Request $request
     * @return array
     */
    private function detectSuspiciousActivity(\WP_REST_Request $request): array {
        $params = $request->get_params();
        
        // Check for SQL injection patterns
        foreach ($params as $key => $value) {
            if (is_string($value) && $this->containsSQLInjectionPattern($value)) {
                return [
                    'success' => false,
                    'reason' => 'Suspicious SQL pattern detected'
                ];
            }
        }
        
        // Check for XSS patterns
        foreach ($params as $key => $value) {
            if (is_string($value) && $this->containsXSSPattern($value)) {
                return [
                    'success' => false,
                    'reason' => 'Suspicious XSS pattern detected'
                ];
            }
        }
        
        // Check for path traversal
        foreach ($params as $key => $value) {
            if (is_string($value) && $this->containsPathTraversal($value)) {
                return [
                    'success' => false,
                    'reason' => 'Path traversal attempt detected'
                ];
            }
        }
        
        return ['success' => true, 'reason' => 'No suspicious activity detected'];
    }
    
    /**
     * Check for SQL injection patterns
     * 
     * @param string $value
     * @return bool
     */
    private function containsSQLInjectionPattern(string $value): bool {
        $patterns = [
            '/\bunion\b.*\bselect\b/i',
            '/\bselect\b.*\bfrom\b.*\bwhere\b/i',
            '/\binsert\b.*\binto\b.*\bvalues\b/i',
            '/\bupdate\b.*\bset\b.*\bwhere\b/i',
            '/\bdelete\b.*\bfrom\b.*\bwhere\b/i',
            '/\bdrop\b.*\btable\b/i',
            '/\bexec\b.*\(/i',
            '/\bscript\b.*\>/i',
            '/[\'";].*\bor\b.*[\'"].*=/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for XSS patterns
     * 
     * @param string $value
     * @return bool
     */
    private function containsXSSPattern(string $value): bool {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<embed[^>]*>/i',
            '/<object[^>]*>/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for path traversal
     * 
     * @param string $value
     * @return bool
     */
    private function containsPathTraversal(string $value): bool {
        $patterns = [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/%2e%2e%2f/i',
            '/%2e%2e%5c/i',
            '/\.\.[\/\\\\]/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log validation failure
     * 
     * @param string $event_type
     * @param \WP_REST_Request $request
     * @param string $reason
     */
    private function logValidationFailure(string $event_type, \WP_REST_Request $request, string $reason): void {
        // Log to audit logger if available
        if ($this->auditLogger) {
            $this->auditLogger->log('security', 'Nonce validation failed', [
                'event_type' => $event_type,
                'reason' => $reason,
                'route' => $request->get_route(),
                'method' => $request->get_method(),
                'ip' => $this->getClientIP(),
                'user_id' => get_current_user_id(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        }
        
        // Log to general logger if available
        if ($this->logger) {
            $this->logger->warning('Nonce validation failed', [
                'event_type' => $event_type,
                'reason' => $reason,
                'route' => $request->get_route(),
                'method' => $request->get_method(),
                'ip' => $this->getClientIP()
            ]);
        }
        
        // Log to error_log as fallback
        error_log(sprintf(
            '[ZipPicks Security] Nonce validation failed: %s - %s on %s %s from IP %s',
            $event_type,
            $reason,
            $request->get_method(),
            $request->get_route(),
            $this->getClientIP()
        ));
        
        // Log to database table
        $this->logToDatabase($event_type, $request, $reason);
    }
    
    /**
     * Log validation success
     * 
     * @param \WP_REST_Request $request
     */
    private function logValidationSuccess(\WP_REST_Request $request): void {
        // Only log successes for admin endpoints in debug mode
        if (defined('ZIPPICKS_DEBUG') && ZIPPICKS_DEBUG && current_user_can('manage_options')) {
            if ($this->logger) {
                $this->logger->debug('Nonce validation successful', [
                    'route' => $request->get_route(),
                    'method' => $request->get_method(),
                    'user_id' => get_current_user_id()
                ]);
            }
        }
    }
    
    /**
     * Log security event to database
     * 
     * @param string $event_type
     * @param \WP_REST_Request $request
     * @param string $reason
     */
    private function logToDatabase(string $event_type, \WP_REST_Request $request, string $reason): void {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_security_log';
        
        // Create table if it doesn't exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                event_type VARCHAR(50) NOT NULL,
                reason VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_id BIGINT(20) UNSIGNED NULL,
                route VARCHAR(255) NOT NULL,
                method VARCHAR(10) NOT NULL,
                user_agent TEXT,
                request_data LONGTEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_event_time (event_type, timestamp),
                INDEX idx_ip_time (ip_address, timestamp),
                INDEX idx_user_time (user_id, timestamp)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        $wpdb->insert($table, [
            'event_type' => $event_type,
            'reason' => $reason,
            'ip_address' => $this->getClientIP(),
            'user_id' => get_current_user_id() ?: null,
            'route' => $request->get_route(),
            'method' => $request->get_method(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_data' => wp_json_encode($request->get_params()),
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * Get client IP
     * 
     * @return string
     */
    private function getClientIP(): string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Create nonce for response
     * 
     * @return string
     */
    public function createNonce(): string {
        $nonce = wp_create_nonce($this->action);
        
        // Store session binding
        $this->storeSessionBinding($nonce);
        
        return $nonce;
    }
    
    /**
     * Get nonce field for forms
     * 
     * @param bool $referer Include referer field
     * @return string
     */
    public function getNonceField(bool $referer = true): string {
        return wp_nonce_field($this->action, '_wpnonce', $referer, false);
    }
    
    /**
     * Clean up old security logs
     * 
     * This should be called periodically via cron
     * 
     * @param int $days Number of days to keep logs
     */
    public function cleanupLogs(int $days = 30): void {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_security_log';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));
            
            if ($this->logger) {
                $this->logger->info('Security log cleanup completed', [
                    'days_kept' => $days,
                    'rows_deleted' => $wpdb->rows_affected
                ]);
            }
        }
    }
    
    /**
     * Get security statistics
     * 
     * @param int $hours Number of hours to analyze
     * @return array
     */
    public function getStatistics(int $hours = 24): array {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_security_log';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_failures,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT route) as unique_routes,
                SUM(CASE WHEN event_type = 'missing_nonce' THEN 1 ELSE 0 END) as missing_nonce_count,
                SUM(CASE WHEN event_type = 'invalid_nonce' THEN 1 ELSE 0 END) as invalid_nonce_count,
                SUM(CASE WHEN event_type = 'additional_check_failed' THEN 1 ELSE 0 END) as additional_check_count
            FROM $table 
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $hours
        ), ARRAY_A);
        
        // Get top failing IPs
        $top_ips = $wpdb->get_results($wpdb->prepare(
            "SELECT ip_address, COUNT(*) as failure_count
            FROM $table
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL %d HOUR)
            GROUP BY ip_address
            ORDER BY failure_count DESC
            LIMIT 10",
            $hours
        ), ARRAY_A);
        
        $stats['top_failing_ips'] = $top_ips;
        $stats['time_period_hours'] = $hours;
        
        return $stats;
    }
    
    /**
     * Verify session binding for nonce
     * 
     * @param string $nonce
     * @param \WP_REST_Request $request
     * @return bool
     */
    private function verifySessionBinding(string $nonce, \WP_REST_Request $request): bool {
        // Get session ID
        $session_id = $this->getSessionId();
        if (empty($session_id)) {
            return false; // No session = potential scraper
        }
        
        // Check if nonce is bound to this session
        $binding_key = 'zippicks_nonce_session_' . md5($nonce);
        $stored_session = get_transient($binding_key);
        
        if ($stored_session === false) {
            // No binding found - could be old nonce or scraping attempt
            return false;
        }
        
        // Verify session matches
        if ($stored_session !== $session_id) {
            // Session mismatch - potential token theft
            $this->logSecurityEvent('session_mismatch', $request, 'Nonce used with different session');
            return false;
        }
        
        // Verify IP hasn't changed (additional protection)
        $ip_key = 'zippicks_nonce_ip_' . md5($nonce);
        $stored_ip = get_transient($ip_key);
        $current_ip = $this->getClientIP();
        
        if ($stored_ip && $stored_ip !== $current_ip) {
            $this->logSecurityEvent('ip_mismatch', $request, 'Nonce used from different IP');
            return false;
        }
        
        return true;
    }
    
    /**
     * Store session binding for nonce
     * 
     * @param string $nonce
     */
    private function storeSessionBinding(string $nonce): void {
        $session_id = $this->getSessionId();
        if (empty($session_id)) {
            // Create session if it doesn't exist
            if (!session_id()) {
                session_start();
            }
            $session_id = session_id();
        }
        
        // Store session binding
        $binding_key = 'zippicks_nonce_session_' . md5($nonce);
        set_transient($binding_key, $session_id, HOUR_IN_SECONDS * 12); // 12 hour expiry
        
        // Store IP binding
        $ip_key = 'zippicks_nonce_ip_' . md5($nonce);
        set_transient($ip_key, $this->getClientIP(), HOUR_IN_SECONDS * 12);
    }
    
    /**
     * Get session ID
     * 
     * @return string|null
     */
    private function getSessionId(): ?string {
        // Check if session is started
        if (session_status() === PHP_SESSION_NONE) {
            // Try to get session from cookie
            $session_name = session_name();
            if (isset($_COOKIE[$session_name])) {
                return $_COOKIE[$session_name];
            }
            return null;
        }
        
        return session_id() ?: null;
    }
    
    /**
     * Log security event
     * 
     * @param string $event_type
     * @param \WP_REST_Request $request
     * @param string $details
     */
    private function logSecurityEvent(string $event_type, \WP_REST_Request $request, string $details): void {
        if ($this->auditLogger) {
            $this->auditLogger->log('security', 'Security event detected', [
                'event_type' => $event_type,
                'details' => $details,
                'route' => $request->get_route(),
                'method' => $request->get_method(),
                'ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        }
    }
    
    /**
     * Get anti-scraping headers
     * 
     * @return array
     */
    public function getAntiScrapingHeaders(): array {
        return [
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, max-age=0',
            'X-ZipPicks-Source' => 'frontend-only',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin'
        ];
    }
    
    /**
     * Generate rotating nonce
     * 
     * @return string
     */
    public function generateRotatingNonce(): string {
        // Create time-based nonce that rotates
        $time_slice = floor(time() / 300); // 5 minute windows
        $user_id = get_current_user_id();
        $session_id = $this->getSessionId();
        
        $nonce_data = $this->action . '_' . $time_slice . '_' . $user_id . '_' . $session_id;
        $nonce = wp_hash($nonce_data, 'nonce');
        
        // Store for validation
        $this->storeSessionBinding($nonce);
        
        return $nonce;
    }
}