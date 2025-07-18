<?php
/**
 * Rate Limiter Middleware
 * 
 * Production-ready rate limiting with persistent storage and configurable limits
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Api\Middleware;

use WP_REST_Request;

/**
 * Class RateLimiter
 */
class RateLimiter {
    
    /**
     * Cache instance
     * 
     * @var mixed
     */
    private $cache;
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger;
    
    /**
     * Default rate limits
     * 
     * @var array
     */
    private array $limits = [
        'default' => [
            'requests' => 60,
            'window' => 3600 // 1 hour
        ],
        'authenticated' => [
            'requests' => 200,
            'window' => 3600
        ],
        'admin' => [
            'requests' => 1000,
            'window' => 3600
        ]
    ];
    
    /**
     * Endpoint-specific limits
     * 
     * @var array
     */
    private array $endpoint_limits = [];
    
    /**
     * Option name for configuration
     * 
     * @var string
     */
    private string $config_option = 'zippicks_rate_limits';
    
    /**
     * Option name for whitelist
     * 
     * @var string
     */
    private string $whitelist_option = 'zippicks_rate_limit_whitelist';
    
    /**
     * Constructor
     * 
     * @param mixed $cache
     * @param mixed $logger
     */
    public function __construct($cache = null, $logger = null) {
        $this->cache = $cache;
        $this->logger = $logger;
        
        // Load configuration from options
        $this->loadConfiguration();
    }
    
    /**
     * Check rate limit
     * 
     * @param \WP_REST_Request $request
     * @return bool True if within limit, false if exceeded
     */
    public function check(\WP_REST_Request $request): bool {
        // Check if IP is whitelisted
        $ip = $this->getClientIP($request);
        if ($this->isWhitelisted($ip)) {
            return true;
        }
        
        // Check if IP is banned
        if ($this->isIPBanned($ip)) {
            $this->logScrapeAttempt($request, 'banned_ip');
            return false;
        }
        
        // Validate User-Agent (anti-scraping policy)
        if (!$this->validateUserAgent($request)) {
            $this->logScrapeAttempt($request, 'invalid_user_agent');
            $this->banIP($ip, 3600, 'Invalid or missing User-Agent');
            return false;
        }
        
        // Get identifier for rate limiting
        $identifier = $this->getIdentifier($request);
        $limit_config = $this->getLimitConfig($request);
        
        // Use transients if cache not available
        if (!$this->cache) {
            return $this->checkUsingTransients($identifier, $limit_config);
        }
        
        return $this->checkUsingCache($identifier, $limit_config);
    }
    
    /**
     * Check rate limit using cache
     * 
     * @param string $identifier
     * @param array $limit_config
     * @return bool
     */
    private function checkUsingCache(string $identifier, array $limit_config): bool {
        $key = 'zippicks_rate_' . md5($identifier);
        $window_key = $key . '_window';
        
        // Get current count and window start
        $count = (int) $this->cache->get($key);
        $window_start = (int) $this->cache->get($window_key);
        $current_time = time();
        
        // Check if we're in a new window
        if ($current_time - $window_start > $limit_config['window']) {
            // Reset window
            $this->cache->set($window_key, $current_time, $limit_config['window']);
            $this->cache->set($key, 1, $limit_config['window']);
            return true;
        }
        
        // Check if limit exceeded
        if ($count >= $limit_config['requests']) {
            $this->logRateLimitExceeded($identifier, null);
            return false;
        }
        
        // Increment counter
        $this->cache->incr($key);
        
        return true;
    }
    
    /**
     * Check rate limit using WordPress transients
     * 
     * @param string $identifier
     * @param array $limit_config
     * @return bool
     */
    private function checkUsingTransients(string $identifier, array $limit_config): bool {
        $key = 'zippicks_rate_' . md5($identifier);
        $data = get_transient($key);
        $current_time = time();
        
        if ($data === false) {
            // No data, start new window
            set_transient($key, [
                'count' => 1,
                'window_start' => $current_time
            ], $limit_config['window']);
            return true;
        }
        
        // Check if we're in a new window
        if ($current_time - $data['window_start'] > $limit_config['window']) {
            // Reset window
            set_transient($key, [
                'count' => 1,
                'window_start' => $current_time
            ], $limit_config['window']);
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $limit_config['requests']) {
            $this->logRateLimitExceeded($identifier, null);
            return false;
        }
        
        // Increment counter
        $data['count']++;
        set_transient($key, $data, $limit_config['window'] - ($current_time - $data['window_start']));
        
        return true;
    }
    
    /**
     * Get rate limit headers
     * 
     * @param \WP_REST_Request $request
     * @return array
     */
    public function getHeaders(\WP_REST_Request $request): array {
        $identifier = $this->getIdentifier($request);
        $limit_config = $this->getLimitConfig($request);
        
        if (!$this->cache) {
            return $this->getHeadersFromTransients($identifier, $limit_config);
        }
        
        return $this->getHeadersFromCache($identifier, $limit_config);
    }
    
    /**
     * Get headers from cache
     * 
     * @param string $identifier
     * @param array $limit_config
     * @return array
     */
    private function getHeadersFromCache(string $identifier, array $limit_config): array {
        $key = 'zippicks_rate_' . md5($identifier);
        $window_key = $key . '_window';
        
        $count = (int) $this->cache->get($key);
        $window_start = (int) $this->cache->get($window_key);
        
        $remaining = max(0, $limit_config['requests'] - $count);
        $reset = $window_start + $limit_config['window'];
        
        return [
            'X-RateLimit-Limit' => $limit_config['requests'],
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $reset,
            'X-RateLimit-Window' => $limit_config['window']
        ];
    }
    
    /**
     * Get headers from transients
     * 
     * @param string $identifier
     * @param array $limit_config
     * @return array
     */
    private function getHeadersFromTransients(string $identifier, array $limit_config): array {
        $key = 'zippicks_rate_' . md5($identifier);
        $data = get_transient($key);
        
        if ($data === false) {
            return [
                'X-RateLimit-Limit' => $limit_config['requests'],
                'X-RateLimit-Remaining' => $limit_config['requests'],
                'X-RateLimit-Reset' => time() + $limit_config['window'],
                'X-RateLimit-Window' => $limit_config['window']
            ];
        }
        
        $remaining = max(0, $limit_config['requests'] - $data['count']);
        $reset = $data['window_start'] + $limit_config['window'];
        
        return [
            'X-RateLimit-Limit' => $limit_config['requests'],
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $reset,
            'X-RateLimit-Window' => $limit_config['window']
        ];
    }
    
    /**
     * Get retry after seconds
     * 
     * @param \WP_REST_Request $request
     * @return int
     */
    public function getRetryAfter(\WP_REST_Request $request): int {
        $headers = $this->getHeaders($request);
        $reset_time = isset($headers['X-RateLimit-Reset']) ? $headers['X-RateLimit-Reset'] : time() + 60;
        return max(1, $reset_time - time());
    }
    
    /**
     * Get rate limit error response
     * 
     * @param \WP_REST_Request $request
     * @return \WP_Error
     */
    public function getErrorResponse(\WP_REST_Request $request): \WP_Error {
        $retry_after = $this->getRetryAfter($request);
        
        return new \WP_Error(
            'rate_limit_exceeded',
            sprintf(
                __('Rate limit exceeded. Please wait %d seconds before making another request.', 'zippicks-vibes'),
                $retry_after
            ),
            [
                'status' => 429,
                'retry_after' => $retry_after
            ]
        );
    }
    
    /**
     * Set custom limit for specific endpoint
     * 
     * @param string $endpoint
     * @param int $requests
     * @param int $window
     */
    public function setLimit(string $endpoint, int $requests, int $window): void {
        $this->endpoint_limits[$endpoint] = [
            'requests' => $requests,
            'window' => $window
        ];
        
        // Save to database
        $this->saveConfiguration();
    }
    
    /**
     * Get identifier for rate limiting
     * 
     * @param \WP_REST_Request $request
     * @return string
     */
    private function getIdentifier(\WP_REST_Request $request): string {
        // For authenticated users, use user ID
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        
        // For anonymous users, use IP address
        return 'ip_' . $this->getClientIP($request);
    }
    
    /**
     * Get limit configuration
     * 
     * @param \WP_REST_Request $request
     * @return array
     */
    private function getLimitConfig(\WP_REST_Request $request): array {
        // Check for admin users
        if (current_user_can('manage_options')) {
            return $this->limits['admin'];
        }
        
        // Check for authenticated users
        if (is_user_logged_in()) {
            return $this->limits['authenticated'];
        }
        
        // Check for endpoint-specific limits
        $route = $request->get_route();
        foreach ($this->endpoint_limits as $endpoint => $config) {
            if (strpos($route, $endpoint) !== false) {
                return $config;
            }
        }
        
        // Default limits
        return $this->limits['default'];
    }
    
    /**
     * Get client IP from request
     * 
     * @param \WP_REST_Request $request
     * @return string
     */
    private function getClientIP(\WP_REST_Request $request): string {
        // Check headers in order of preference
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',             // Proxy
            'HTTP_X_FORWARDED_FOR',       // Load balancer
            'HTTP_X_FORWARDED',           // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',   // Cluster
            'HTTP_FORWARDED_FOR',         // Proxy
            'HTTP_FORWARDED',             // Proxy
            'REMOTE_ADDR'                 // Direct connection
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to direct connection or unknown
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if IP is whitelisted
     * 
     * @param string $ip
     * @return bool
     */
    private function isWhitelisted(string $ip): bool {
        $whitelist = get_option($this->whitelist_option, []);
        
        // Check exact IP match
        if (in_array($ip, $whitelist['ips'] ?? [])) {
            return true;
        }
        
        // Check IP ranges (CIDR notation)
        foreach ($whitelist['ranges'] ?? [] as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }
        
        // Check roles
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            foreach ($whitelist['roles'] ?? [] as $role) {
                if (in_array($role, $user->roles)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is in CIDR range
     * 
     * @param string $ip
     * @param string $range
     * @return bool
     */
    private function ipInRange(string $ip, string $range): bool {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $mask) = explode('/', $range);
        $subnet = ip2long($subnet);
        $ip = ip2long($ip);
        $mask = -1 << (32 - $mask);
        $subnet &= $mask;
        
        return ($ip & $mask) == $subnet;
    }
    
    /**
     * Log rate limit exceeded
     * 
     * @param string $identifier
     * @param \WP_REST_Request|null $request
     */
    private function logRateLimitExceeded(string $identifier, ?\WP_REST_Request $request = null): void {
        if ($this->logger) {
            $this->logger->warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'route' => $request ? $request->get_route() : 'unknown',
                'method' => $request ? $request->get_method() : 'unknown',
                'ip' => $this->getClientIP($request),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        }
        
        // Also log to database for analysis
        $this->logToDatabase($identifier, $request);
    }
    
    /**
     * Log to database
     * 
     * @param string $identifier
     * @param \WP_REST_Request|null $request
     */
    private function logToDatabase(string $identifier, ?\WP_REST_Request $request): void {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_rate_limit_log';
        
        // Create table if it doesn't exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                identifier VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                route VARCHAR(255) NOT NULL,
                method VARCHAR(10) NOT NULL,
                user_agent TEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_identifier_time (identifier, timestamp),
                INDEX idx_ip_time (ip_address, timestamp)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        $wpdb->insert($table, [
            'identifier' => $identifier,
            'ip_address' => $request ? $this->getClientIP($request) : $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'route' => $request ? $request->get_route() : 'unknown',
            'method' => $request ? $request->get_method() : 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * Clean up old rate limit data
     * 
     * This should be called periodically via cron
     */
    public function cleanup(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_rate_limit_log';
        
        // Delete logs older than 7 days
        $wpdb->query(
            "DELETE FROM $table WHERE timestamp < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        if ($this->logger) {
            $this->logger->info('Rate limit log cleanup completed', [
                'rows_deleted' => $wpdb->rows_affected
            ]);
        }
    }
    
    /**
     * Get rate limit statistics
     * 
     * @param string $identifier
     * @param int $hours
     * @return array
     */
    public function getStatistics(string $identifier = '', int $hours = 24): array {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_rate_limit_log';
        
        if ($identifier) {
            $query = $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_hits,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    COUNT(DISTINCT route) as unique_routes,
                    MIN(timestamp) as first_hit,
                    MAX(timestamp) as last_hit
                FROM $table 
                WHERE identifier = %s 
                AND timestamp > DATE_SUB(NOW(), INTERVAL %d HOUR)",
                $identifier,
                $hours
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_hits,
                    COUNT(DISTINCT identifier) as unique_identifiers,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    COUNT(DISTINCT route) as unique_routes,
                    MIN(timestamp) as first_hit,
                    MAX(timestamp) as last_hit
                FROM $table 
                WHERE timestamp > DATE_SUB(NOW(), INTERVAL %d HOUR)",
                $hours
            );
        }
        
        $stats = $wpdb->get_row($query, ARRAY_A) ?: [];
        
        // Get top offenders
        $top_offenders = $wpdb->get_results($wpdb->prepare(
            "SELECT identifier, COUNT(*) as hit_count
            FROM $table
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL %d HOUR)
            GROUP BY identifier
            ORDER BY hit_count DESC
            LIMIT 10",
            $hours
        ), ARRAY_A);
        
        $stats['top_offenders'] = $top_offenders;
        $stats['time_period_hours'] = $hours;
        
        return $stats;
    }
    
    /**
     * Check if IP is temporarily banned
     * 
     * @param string $ip
     * @return bool
     */
    public function isIPBanned(string $ip): bool {
        $ban_key = 'zippicks_ip_ban_' . md5($ip);
        
        if ($this->cache) {
            return (bool) $this->cache->get($ban_key);
        }
        
        return get_transient($ban_key) !== false;
    }
    
    /**
     * Ban IP temporarily
     * 
     * @param string $ip
     * @param int $duration Seconds
     * @param string $reason
     */
    public function banIP(string $ip, int $duration = 3600, string $reason = 'Rate limit violations'): void {
        $ban_key = 'zippicks_ip_ban_' . md5($ip);
        
        if ($this->cache) {
            $this->cache->set($ban_key, true, $duration);
        } else {
            set_transient($ban_key, true, $duration);
        }
        
        // Log the ban
        if ($this->logger) {
            $this->logger->warning('IP banned for rate limit violations', [
                'ip' => $ip,
                'duration' => $duration,
                'reason' => $reason
            ]);
        }
        
        // Store ban record
        $this->storeBanRecord($ip, $duration, $reason);
    }
    
    /**
     * Store ban record in database
     * 
     * @param string $ip
     * @param int $duration
     * @param string $reason
     */
    private function storeBanRecord(string $ip, int $duration, string $reason): void {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_ip_bans';
        
        // Create table if it doesn't exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                ban_start DATETIME NOT NULL,
                ban_end DATETIME NOT NULL,
                reason VARCHAR(255) NOT NULL,
                PRIMARY KEY (id),
                INDEX idx_ip (ip_address),
                INDEX idx_ban_period (ban_start, ban_end)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        $wpdb->insert($table, [
            'ip_address' => $ip,
            'ban_start' => current_time('mysql'),
            'ban_end' => date('Y-m-d H:i:s', time() + $duration),
            'reason' => $reason
        ]);
    }
    
    /**
     * Load configuration from options
     */
    private function loadConfiguration(): void {
        $config = get_option($this->config_option, []);
        
        if (!empty($config['limits'])) {
            $this->limits = array_merge($this->limits, $config['limits']);
        }
        
        if (!empty($config['endpoint_limits'])) {
            $this->endpoint_limits = $config['endpoint_limits'];
        }
    }
    
    /**
     * Save configuration to options
     */
    private function saveConfiguration(): void {
        update_option($this->config_option, [
            'limits' => $this->limits,
            'endpoint_limits' => $this->endpoint_limits
        ]);
    }
    
    /**
     * Add IP to whitelist
     * 
     * @param string $ip
     */
    public function addToWhitelist(string $ip): void {
        $whitelist = get_option($this->whitelist_option, ['ips' => [], 'ranges' => [], 'roles' => []]);
        
        if (!in_array($ip, $whitelist['ips'])) {
            $whitelist['ips'][] = $ip;
            update_option($this->whitelist_option, $whitelist);
        }
    }
    
    /**
     * Remove IP from whitelist
     * 
     * @param string $ip
     */
    public function removeFromWhitelist(string $ip): void {
        $whitelist = get_option($this->whitelist_option, ['ips' => [], 'ranges' => [], 'roles' => []]);
        
        $whitelist['ips'] = array_diff($whitelist['ips'], [$ip]);
        update_option($this->whitelist_option, $whitelist);
    }
    
    /**
     * Add role to whitelist
     * 
     * @param string $role
     */
    public function addRoleToWhitelist(string $role): void {
        $whitelist = get_option($this->whitelist_option, ['ips' => [], 'ranges' => [], 'roles' => []]);
        
        if (!in_array($role, $whitelist['roles'])) {
            $whitelist['roles'][] = $role;
            update_option($this->whitelist_option, $whitelist);
        }
    }
    
    /**
     * Update global limits
     * 
     * @param array $limits
     */
    public function updateGlobalLimits(array $limits): void {
        $this->limits = array_merge($this->limits, $limits);
        $this->saveConfiguration();
    }
    
    /**
     * Validate User-Agent header
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    private function validateUserAgent(\WP_REST_Request $request): bool {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Reject empty User-Agent
        if (empty($user_agent)) {
            return false;
        }
        
        // Reject known CLI/bot agents per anti-scraping policy
        $blocked_agents = [
            'curl',
            'wget',
            'python',
            'scrapy',
            'go-http-client',
            'java',
            'libwww-perl',
            'httpclient',
            'okhttp',
            'postman',
            'insomnia',
            'axios',
            'node-fetch'
        ];
        
        $user_agent_lower = strtolower($user_agent);
        foreach ($blocked_agents as $blocked) {
            if (strpos($user_agent_lower, $blocked) !== false) {
                return false;
            }
        }
        
        return true;
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
            'X-Frame-Options' => 'SAMEORIGIN'
        ];
    }
    
    /**
     * Log scrape attempt to dedicated table
     * 
     * @param \WP_REST_Request $request
     * @param string $reason
     */
    private function logScrapeAttempt(\WP_REST_Request $request, string $reason): void {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_scrape_log';
        
        // Create table if it doesn't exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                request_path VARCHAR(255) NOT NULL,
                user_agent TEXT,
                referrer VARCHAR(255),
                reason VARCHAR(100) NOT NULL,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_ip_time (ip_address, timestamp),
                INDEX idx_reason (reason)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        $wpdb->insert($table, [
            'ip_address' => $this->getClientIP($request),
            'request_path' => $request->get_route(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'reason' => $reason,
            'timestamp' => current_time('mysql')
        ]);
        
        // Check for scraping patterns and auto-ban
        $this->checkScrapingPatterns($this->getClientIP($request));
    }
    
    /**
     * Check for scraping patterns
     * 
     * @param string $ip
     */
    private function checkScrapingPatterns(string $ip): void {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_scrape_log';
        
        // Check for more than 10 requests/minute to list endpoints
        $recent_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
            WHERE ip_address = %s 
            AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            AND request_path LIKE '%/list%'",
            $ip
        ));
        
        if ($recent_count > 10) {
            $this->banIP($ip, 86400, 'Excessive list endpoint requests'); // 24 hour ban
        }
        
        // Check for sequential access patterns
        $sequential_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT request_path) FROM $table 
            WHERE ip_address = %s 
            AND timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            AND (request_path LIKE '%/vibes/%' OR request_path LIKE '%/businesses/%')",
            $ip
        ));
        
        if ($sequential_count > 20) {
            $this->banIP($ip, 86400, 'Sequential scraping pattern detected');
        }
    }
    
    /**
     * Detect abnormal access patterns
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function detectAbnormalPatterns(\WP_REST_Request $request): bool {
        $ip = $this->getClientIP($request);
        $route = $request->get_route();
        
        // Check for rapid-fire requests
        $key = 'zippicks_rapid_' . md5($ip . $route);
        $rapid_count = get_transient($key) ?: 0;
        
        if ($rapid_count > 5) {
            $this->logScrapeAttempt($request, 'rapid_fire_requests');
            return true;
        }
        
        set_transient($key, $rapid_count + 1, 10); // 10 second window
        
        return false;
    }
}