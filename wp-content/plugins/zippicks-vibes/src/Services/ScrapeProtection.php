<?php
/**
 * Scrape Protection Service
 * 
 * Implements comprehensive anti-scraping measures
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Services;

use Exception;

/**
 * Class ScrapeProtection
 */
class ScrapeProtection {
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger;
    
    /**
     * Cache instance
     * 
     * @var mixed
     */
    private $cache;
    
    /**
     * Database table name for scrape logs
     * 
     * @var string
     */
    private string $logTable;
    
    /**
     * Database table name for blocked IPs
     * 
     * @var string
     */
    private string $blockedTable;
    
    /**
     * Suspicious user agents patterns
     * 
     * @var array
     */
    private array $suspiciousAgentPatterns = [
        '/curl/i', '/wget/i', '/python/i', '/scrapy/i', '/bot/i', '/spider/i',
        '/crawl/i', '/scraper/i', '/harvest/i', '/httpclient/i', '/java/i',
        '/libwww/i', '/mechanize/i', '/nodejs/i', '/okhttp/i', '/perl/i',
        '/php/i', '/ruby/i', '/winhttp/i', '/httpunit/i', '/nutch/i',
        '/go-http-client/i', '/postman/i', '/insomnia/i', '/axios/i'
    ];
    
    /**
     * Whitelisted user agents
     * 
     * @var array
     */
    private array $whitelistedAgents = [
        'Googlebot', 'bingbot', 'Slurp', 'DuckDuckBot', 'facebookexternalhit'
    ];
    
    /**
     * Rate limit settings
     * 
     * @var array
     */
    private array $rateLimits = [
        'requests_per_minute' => 20,
        'requests_per_hour' => 300,
        'requests_per_day' => 2000
    ];
    
    /**
     * Tables checked flag
     * 
     * @var bool
     */
    private static $tablesChecked = false;
    
    /**
     * Constructor
     * 
     * @param mixed $logger
     * @param mixed $cache
     */
    public function __construct($logger = null, $cache = null) {
        global $wpdb;
        
        $this->logger = $logger;
        $this->cache = $cache;
        $this->logTable = $wpdb->prefix . 'zippicks_scrape_log';
        $this->blockedTable = $wpdb->prefix . 'zippicks_blocked_ips';
        
        // Tables should be created during plugin activation, not here
        // This prevents fatal errors and performance issues
    }
    
    /**
     * Validate request for security
     * 
     * @return bool
     */
    public function validateRequest(): bool {
        try {
            $ip = $this->getClientIP();
            
            // Check if IP is whitelisted
            if ($this->isWhitelisted($ip)) {
                return true;
            }
            
            // Check if IP is blocked
            if ($this->isBlocked($ip)) {
                $this->logSecurityEvent('blocked_ip_attempt', ['ip' => $ip]);
                return false;
            }
            
            // Check nonce for AJAX/REST requests
            if (!$this->validateNonce()) {
                $this->logSecurityEvent('invalid_nonce', ['ip' => $ip]);
                return false;
            }
            
            // Check user agent
            if ($this->isSuspiciousUserAgent()) {
                $this->logSecurityEvent('suspicious_user_agent', [
                    'ip' => $ip,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                return false;
            }
            
            // Check rate limit
            if ($this->isRateLimited($ip)) {
                $this->logSecurityEvent('rate_limited', ['ip' => $ip]);
                return false;
            }
            
            // Check referrer
            if (!$this->isValidReferrer()) {
                $this->logSecurityEvent('invalid_referrer', [
                    'ip' => $ip,
                    'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
                ]);
                return false;
            }
            
            // Increment request count
            $this->incrementRequestCount($ip);
            
            return true;
        } catch (Exception $e) {
            $this->logError('Security validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Fail closed - reject on error
            return false;
        }
    }
    
    /**
     * Monitor request for scraping patterns
     * 
     * @return void
     */
    public function monitor_request(): void {
        try {
            $ip = $this->getClientIP();
            $path = $_SERVER['REQUEST_URI'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Track request
            $this->trackRequest($ip, $path);
            
            // Check for scraping patterns
            if ($this->detectScrapingPattern($ip)) {
                $this->handleSuspiciousActivity($ip);
            }
            
            // Log request details for monitoring
            $this->logDebug('Request monitored', [
                'ip' => $ip,
                'path' => $path,
                'user_agent' => $user_agent,
                'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct'
            ]);
        } catch (Exception $e) {
            $this->logError('Failed to monitor request', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Generate watermarks for content
     * 
     * @return string
     */
    public function generate_watermarks(): string {
        try {
            $user_id = \get_current_user_id();
            $session_id = $this->getWordPressSessionId();
            $timestamp = time();
            $ip = $this->getClientIP();
            
            // Generate unique fingerprint
            $fingerprint = $this->generateFingerprint([
                'user_id' => $user_id,
                'session_id' => $session_id,
                'timestamp' => $timestamp,
                'ip' => $ip
            ]);
            
            // Create invisible watermarks
            $watermarks = [];
            
            // HTML comment watermark
            $watermarks[] = sprintf(
                '<!-- ZP:%s:%d:%s -->',
                $fingerprint,
                $timestamp,
                base64_encode($ip)
            );
            
            // Invisible span watermark
            $watermarks[] = sprintf(
                '<span class="zp-fp" data-hash="%s" style="display:none;position:absolute;left:-9999px;">%s</span>',
                $fingerprint,
                $this->generateNoiseContent()
            );
            
            // Zero-width character watermark
            $watermarks[] = $this->insertZeroWidthWatermark($fingerprint);
            
            // 1px transparent image watermark
            $watermarks[] = sprintf(
                '<img src="data:image/svg+xml;base64,%s" width="1" height="1" style="position:absolute;opacity:0;" alt="" />',
                base64_encode($this->generateSVGWatermark($fingerprint))
            );
            
            // Store fingerprint for tracking
            $this->storeFingerprint($fingerprint, [
                'user_id' => $user_id,
                'ip' => $ip,
                'timestamp' => $timestamp
            ]);
            
            return implode("\n", $watermarks);
        } catch (Exception $e) {
            $this->logError('Failed to generate watermarks', [
                'error' => $e->getMessage()
            ]);
            // Return minimal watermark on error
            return '<!-- ZP:' . time() . ' -->';
        }
    }
    
    /**
     * Log unauthorized access attempt
     * 
     * @return void
     */
    public function log_unauthorized_attempt(): void {
        try {
            $ip = $this->getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $path = $_SERVER['REQUEST_URI'] ?? '';
            
            // Log to database
            $this->logScrapingAttempt([
                'ip_address' => $ip,
                'user_agent' => $user_agent,
                'request_path' => $path,
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'timestamp' => \current_time('mysql'),
                'type' => 'unauthorized_access'
            ]);
            
            // Increment suspicious activity counter
            $this->incrementSuspiciousActivity($ip);
            
            $this->logWarning('Unauthorized access attempt', [
                'ip' => $ip,
                'user_agent' => $user_agent,
                'path' => $path
            ]);
        } catch (Exception $e) {
            $this->logError('Failed to log unauthorized attempt', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check if IP is rate limited
     * 
     * @param string $ip IP address
     * @return bool
     */
    public function isRateLimited(string $ip): bool {
        // Check per-minute limit
        $minuteKey = 'rate_minute_' . md5($ip) . '_' . date('YmdHi');
        $minuteCount = $this->getTransientCount($minuteKey);
        
        if ($minuteCount >= $this->rateLimits['requests_per_minute']) {
            return true;
        }
        
        // Check per-hour limit
        $hourKey = 'rate_hour_' . md5($ip) . '_' . date('YmdH');
        $hourCount = $this->getTransientCount($hourKey);
        
        if ($hourCount >= $this->rateLimits['requests_per_hour']) {
            return true;
        }
        
        // Check per-day limit
        $dayKey = 'rate_day_' . md5($ip) . '_' . date('Ymd');
        $dayCount = $this->getTransientCount($dayKey);
        
        if ($dayCount >= $this->rateLimits['requests_per_day']) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Increment request count for IP
     * 
     * @param string $ip IP address
     * @return void
     */
    public function incrementRequestCount(string $ip): void {
        // Increment per-minute counter
        $minuteKey = 'rate_minute_' . md5($ip) . '_' . date('YmdHi');
        $this->incrementTransientCount($minuteKey, 60);
        
        // Increment per-hour counter
        $hourKey = 'rate_hour_' . md5($ip) . '_' . date('YmdH');
        $this->incrementTransientCount($hourKey, 3600);
        
        // Increment per-day counter
        $dayKey = 'rate_day_' . md5($ip) . '_' . date('Ymd');
        $this->incrementTransientCount($dayKey, 86400);
    }
    
    /**
     * Check if user agent matches pattern
     * 
     * @param string $pattern Pattern to match
     * @return bool
     */
    public function isUserAgentMatching(string $pattern): bool {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (empty($userAgent)) {
            return false;
        }
        
        return (bool) \preg_match($pattern, $userAgent);
    }
    
    /**
     * Add IP to whitelist
     * 
     * @param string $ip IP address
     * @param string $reason Reason for whitelisting
     * @param int $duration Duration in seconds (0 = permanent)
     * @return bool
     */
    public function whitelistIP(string $ip, string $reason = '', int $duration = 0): bool {
        try {
            if ($duration > 0) {
                // Temporary whitelist using transient
                $key = 'whitelist_' . md5($ip);
                \set_transient($key, [
                    'reason' => $reason,
                    'added' => time()
                ], $duration);
            } else {
                // Permanent whitelist in options
                $whitelist = \get_option('zippicks_whitelist_ips', []);
                $whitelist[$ip] = [
                    'reason' => $reason,
                    'added' => \current_time('mysql')
                ];
                \update_option('zippicks_whitelist_ips', $whitelist);
            }
            
            $this->logInfo('IP whitelisted', [
                'ip' => $ip,
                'reason' => $reason,
                'duration' => $duration
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->logError('Failed to whitelist IP', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Remove IP from whitelist
     * 
     * @param string $ip IP address
     * @return bool
     */
    public function unwhitelistIP(string $ip): bool {
        try {
            // Remove from transient
            \delete_transient('whitelist_' . md5($ip));
            
            // Remove from permanent whitelist
            $whitelist = \get_option('zippicks_whitelist_ips', []);
            unset($whitelist[$ip]);
            \update_option('zippicks_whitelist_ips', $whitelist);
            
            $this->logInfo('IP removed from whitelist', ['ip' => $ip]);
            
            return true;
        } catch (Exception $e) {
            $this->logError('Failed to unwhitelist IP', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Block IP address
     * 
     * @param string $ip IP address
     * @param string $reason Reason for blocking
     * @param int $duration Duration in seconds (0 = permanent)
     * @return bool
     */
    public function blockIP(string $ip, string $reason = '', int $duration = 3600): bool {
        try {
            global $wpdb;
            
            // Check if tables exist before attempting to insert
            if (!$this->tablesExist()) {
                $this->logError('Cannot block IP - tables do not exist', [
                    'ip' => $ip,
                    'reason' => $reason
                ]);
                // Still set transient for temporary blocking
                if ($duration > 0) {
                    \set_transient('blocked_ip_' . md5($ip), true, $duration);
                }
                return false;
            }
            
            // Insert into blocked IPs table
            $result = $wpdb->insert(
                $this->blockedTable,
                [
                    'ip_address' => $ip,
                    'reason' => $reason,
                    'blocked_at' => \current_time('mysql'),
                    'expires_at' => $duration > 0 ? date('Y-m-d H:i:s', time() + $duration) : null,
                    'blocked_by' => \get_current_user_id()
                ],
                ['%s', '%s', '%s', '%s', '%d']
            );
            
            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }
            
            // Also set transient for quick lookup
            if ($duration > 0) {
                \set_transient('blocked_ip_' . md5($ip), true, $duration);
            }
            
            $this->logWarning('IP blocked', [
                'ip' => $ip,
                'reason' => $reason,
                'duration' => $duration
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->logError('Failed to block IP', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Unblock IP address
     * 
     * @param string $ip IP address
     * @return bool
     */
    public function unblockIP(string $ip): bool {
        try {
            global $wpdb;
            
            // Remove from database
            $result = $wpdb->delete(
                $this->blockedTable,
                ['ip_address' => $ip],
                ['%s']
            );
            
            // Remove transient
            \delete_transient('blocked_ip_' . md5($ip));
            
            $this->logInfo('IP unblocked', ['ip' => $ip]);
            
            return $result !== false;
        } catch (Exception $e) {
            $this->logError('Failed to unblock IP', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get security statistics
     * 
     * @return array
     */
    public function get_security_stats(): array {
        global $wpdb;
        
        $stats = [
            'total_requests_today' => 0,
            'blocked_requests_today' => 0,
            'suspicious_activity_today' => 0,
            'unique_ips_today' => 0,
            'blocked_ips_active' => 0,
            'whitelisted_ips' => 0
        ];
        
        try {
            $today = \current_time('Y-m-d');
            
            // Get today's stats from log table
            $stats['total_requests_today'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->logTable} WHERE DATE(timestamp) = %s",
                $today
            ));
            
            $stats['blocked_requests_today'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->logTable} 
                WHERE DATE(timestamp) = %s AND type IN ('blocked', 'rate_limited')",
                $today
            ));
            
            $stats['suspicious_activity_today'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->logTable} 
                WHERE DATE(timestamp) = %s AND type LIKE %s",
                $today,
                '%suspicious%'
            ));
            
            $stats['unique_ips_today'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT ip_address) FROM {$this->logTable} WHERE DATE(timestamp) = %s",
                $today
            ));
            
            // Get active blocked IPs
            $stats['blocked_ips_active'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->blockedTable} 
                WHERE expires_at IS NULL OR expires_at > NOW()"
            );
            
            // Get whitelisted IPs count
            $whitelist = \get_option('zippicks_whitelist_ips', []);
            $stats['whitelisted_ips'] = count($whitelist);
            
        } catch (Exception $e) {
            $this->logError('Failed to get security stats', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $stats;
    }
    
    /**
     * Get recent scraping attempts
     * 
     * @param int $limit Number of records to retrieve
     * @param array $filters Optional filters
     * @return array
     */
    public function get_recent_scraping_attempts(int $limit = 50, array $filters = []): array {
        global $wpdb;
        
        try {
            $where = ['1=1'];
            $values = [];
            
            // Apply filters
            if (!empty($filters['type'])) {
                $where[] = 'type = %s';
                $values[] = $filters['type'];
            }
            
            if (!empty($filters['ip'])) {
                $where[] = 'ip_address = %s';
                $values[] = $filters['ip'];
            }
            
            if (!empty($filters['date'])) {
                $where[] = 'DATE(timestamp) = %s';
                $values[] = $filters['date'];
            }
            
            $where_clause = implode(' AND ', $where);
            
            $query = "SELECT * FROM {$this->logTable} WHERE {$where_clause} ORDER BY timestamp DESC LIMIT %d";
            $values[] = $limit;
            
            $results = $wpdb->get_results(
                $wpdb->prepare($query, ...$values),
                ARRAY_A
            );
            
            return $results ?: [];
            
        } catch (Exception $e) {
            $this->logError('Failed to get recent scraping attempts', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Check rate limit
     * 
     * @param string $context Context for rate limiting
     * @param int $limit Custom limit (uses default if not specified)
     * @return bool True if within limits, false if rate limited
     */
    public function check_rate_limit(string $context, int $limit = 0): bool {
        $ip = $this->getClientIP();
        
        if ($limit <= 0) {
            $limit = $this->rateLimits['requests_per_minute'];
        }
        
        $key = 'rate_' . $context . '_' . md5($ip) . '_' . date('YmdHi');
        $count = $this->getTransientCount($key);
        
        if ($count >= $limit) {
            $this->logScrapingAttempt([
                'ip_address' => $ip,
                'type' => 'rate_limit_exceeded',
                'context' => $context,
                'request_limit' => $limit,
                'requests_count' => $count
            ]);
            
            return false;
        }
        
        // Increment counter
        $this->incrementTransientCount($key, 60);
        
        return true;
    }
    
    /**
     * Check if tables exist (lazy check with caching)
     * 
     * @return bool
     */
    private function tablesExist(): bool {
        // Use static flag to prevent repeated checks
        if (self::$tablesChecked) {
            return true;
        }
        
        // Use transient cache to prevent repeated database queries
        $cache_key = 'zippicks_scrape_tables_exist';
        $cached = \get_transient($cache_key);
        
        if ($cached !== false) {
            self::$tablesChecked = true;
            return (bool) $cached;
        }
        
        global $wpdb;
        
        // Check both tables
        $log_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->logTable}'") === $this->logTable;
        $blocked_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->blockedTable}'") === $this->blockedTable;
        
        $exists = $log_exists && $blocked_exists;
        
        // Cache the result for 1 hour
        \set_transient($cache_key, $exists, HOUR_IN_SECONDS);
        self::$tablesChecked = $exists;
        
        return $exists;
    }
    
    /**
     * Log scraping attempt (with table existence check)
     * 
     * @param array $data
     * @return void
     */
    private function logScrapingAttempt(array $data): void {
        try {
            // Check if tables exist before attempting to log
            if (!$this->tablesExist()) {
                $this->logError('Scrape protection tables do not exist', [
                    'attempted_action' => 'log_scraping_attempt',
                    'data' => $data
                ]);
                return;
            }
            
            global $wpdb;
            
            // Ensure we have required fields
            $defaults = [
                'ip_address' => '',
                'user_agent' => '',
                'request_path' => '',
                'referrer' => '',
                'timestamp' => \current_time('mysql'),
                'type' => 'unknown',
                'user_id' => null,
                'data' => null,
                'error_message' => null,
                'context' => null,
                'request_limit' => null,
                'requests_count' => null
            ];
            
            $data = array_merge($defaults, $data);
            
            // Serialize complex data
            if (is_array($data['data']) || is_object($data['data'])) {
                $data['data'] = \wp_json_encode($data['data']);
            }
            
            $result = $wpdb->insert(
                $this->logTable,
                $data,
                [
                    '%s', // ip_address
                    '%s', // user_agent
                    '%s', // request_path
                    '%s', // referrer
                    '%s', // timestamp
                    '%s', // type
                    '%d', // user_id
                    '%s', // data
                    '%s', // error_message
                    '%s', // context
                    '%d', // request_limit
                    '%d'  // requests_count
                ]
            );
            
            if ($result === false) {
                $this->logError('Failed to insert scraping log', [
                    'error' => $wpdb->last_error,
                    'data' => $data
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('Exception in logScrapingAttempt', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Validate nonce
     * 
     * @return bool
     */
    private function validateNonce(): bool {
        // For AJAX requests
        if (\wp_doing_ajax()) {
            $nonce = $_REQUEST['nonce'] ?? $_REQUEST['_wpnonce'] ?? $_SERVER['HTTP_X_WP_NONCE'] ?? '';
            return \wp_verify_nonce($nonce, 'zippicks_vibes_admin') || 
                   \wp_verify_nonce($nonce, 'wp_rest') ||
                   \wp_verify_nonce($nonce, 'vibes_render');
        }
        
        // For REST API requests
        if (\defined('REST_REQUEST') && \REST_REQUEST) {
            $nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? '';
            return \wp_verify_nonce($nonce, 'wp_rest');
        }
        
        return true; // Non-AJAX/REST requests don't require nonce
    }
    
    /**
     * Check if user agent is suspicious
     * 
     * @return bool
     */
    private function isSuspiciousUserAgent(): bool {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Empty user agent is suspicious
        if (empty($user_agent)) {
            return true;
        }
        
        // Check whitelist first
        foreach ($this->whitelistedAgents as $whitelisted) {
            if (stripos($user_agent, $whitelisted) !== false) {
                return false;
            }
        }
        
        // Check against suspicious patterns
        foreach ($this->suspiciousAgentPatterns as $pattern) {
            if (\preg_match($pattern, $user_agent)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is whitelisted
     * 
     * @param string $ip
     * @return bool
     */
    private function isWhitelisted(string $ip): bool {
        // Check temporary whitelist
        $tempWhitelist = \get_transient('whitelist_' . md5($ip));
        if ($tempWhitelist !== false) {
            return true;
        }
        
        // Check permanent whitelist
        $whitelist = \get_option('zippicks_whitelist_ips', []);
        return isset($whitelist[$ip]);
    }
    
    /**
     * Check if IP is blocked
     * 
     * @param string $ip
     * @return bool
     */
    private function isBlocked(string $ip): bool {
        // Quick check using transient
        if (\get_transient('blocked_ip_' . md5($ip)) !== false) {
            return true;
        }
        
        // Check database
        global $wpdb;
        $blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->blockedTable} 
             WHERE ip_address = %s 
             AND (expires_at IS NULL OR expires_at > NOW())",
            $ip
        ));
        
        return $blocked > 0;
    }
    
    /**
     * Check if referrer is valid
     * 
     * @return bool
     */
    private function isValidReferrer(): bool {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // Allow direct access for authenticated users
        if (empty($referrer) && \is_user_logged_in()) {
            return true;
        }
        
        // Allow empty referrer for whitelisted IPs
        if (empty($referrer) && $this->isWhitelisted($this->getClientIP())) {
            return true;
        }
        
        // Check referrer domain
        if (!empty($referrer)) {
            $referrer_host = \parse_url($referrer, \PHP_URL_HOST);
            $site_host = \parse_url(\home_url(), \PHP_URL_HOST);
            
            // Allow same domain
            if ($referrer_host === $site_host) {
                return true;
            }
            
            // Check allowed referrers
            $allowed_referrers = \apply_filters('zippicks_vibes_allowed_referrers', [$site_host]);
            
            foreach ($allowed_referrers as $allowed) {
                if ($this->matchReferrerPattern($referrer_host, $allowed)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Match referrer against pattern
     * 
     * @param string $referrer_host
     * @param string $pattern
     * @return bool
     */
    private function matchReferrerPattern(string $referrer_host, string $pattern): bool {
        // Exact match
        if ($referrer_host === $pattern) {
            return true;
        }
        
        // Subdomain wildcard (*.example.com)
        if (strpos($pattern, '*.') === 0) {
            $base_domain = substr($pattern, 2);
            return substr($referrer_host, -strlen($base_domain)) === $base_domain;
        }
        
        return false;
    }
    
    /**
     * Track request
     * 
     * @param string $ip
     * @param string $path
     * @return void
     */
    private function trackRequest(string $ip, string $path): void {
        // Store request pattern for analysis
        $pattern_key = 'access_pattern_' . md5($ip);
        $pattern = \get_transient($pattern_key) ?: [];
        
        $pattern[] = [
            'path' => $path,
            'time' => time()
        ];
        
        // Keep last 20 requests
        if (count($pattern) > 20) {
            array_shift($pattern);
        }
        
        \set_transient($pattern_key, $pattern, 300); // 5 minutes
    }
    
    /**
     * Detect scraping pattern
     * 
     * @param string $ip
     * @return bool
     */
    private function detectScrapingPattern(string $ip): bool {
        $pattern_key = 'access_pattern_' . md5($ip);
        $pattern = \get_transient($pattern_key) ?: [];
        
        if (count($pattern) >= 10) {
            // Check for sequential access to vibe pages
            $vibe_accesses = 0;
            $time_span = 0;
            
            foreach ($pattern as $access) {
                if (strpos($access['path'], '/vibes') !== false || 
                    strpos($access['path'], 'zippicks-vibes') !== false) {
                    $vibe_accesses++;
                }
            }
            
            if (count($pattern) > 0) {
                $time_span = end($pattern)['time'] - reset($pattern)['time'];
            }
            
            // More than 5 vibe pages in 60 seconds is suspicious
            if ($vibe_accesses > 5 && $time_span < 60) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Handle suspicious activity
     * 
     * @param string $ip
     * @return void
     */
    private function handleSuspiciousActivity(string $ip): void {
        // Log the activity
        $this->logScrapingAttempt([
            'ip_address' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_path' => $_SERVER['REQUEST_URI'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'type' => 'scraping_pattern_detected'
        ]);
        
        // Temporarily block IP
        $this->blockIP($ip, 'Automated scraping pattern detected', 3600);
        
        // Send alert if threshold reached
        $this->checkAlertThreshold($ip);
    }
    
    /**
     * Log security event
     * 
     * @param string $event_type
     * @param array $data
     * @return void
     */
    private function logSecurityEvent(string $event_type, array $data = []): void {
        $this->logWarning('Security event: ' . $event_type, array_merge([
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'path' => $_SERVER['REQUEST_URI'] ?? ''
        ], $data));
        
        // Also log to database
        $this->logScrapingAttempt([
            'type' => $event_type,
            'data' => \wp_json_encode($data)
        ]);
    }
    
    /**
     * Generate fingerprint
     * 
     * @param array $data
     * @return string
     */
    private function generateFingerprint(array $data): string {
        $string = implode('|', $data) . '|' . \wp_salt('auth');
        return 'ZP' . substr(hash('sha256', $string), 0, 10);
    }
    
    /**
     * Generate noise content
     * 
     * @return string
     */
    private function generateNoiseContent(): string {
        $words = ['zippicks', 'protected', 'content', 'fingerprint', 'tracking'];
        shuffle($words);
        return implode('-', $words) . '-' . uniqid();
    }
    
    /**
     * Insert zero-width watermark
     * 
     * @param string $fingerprint
     * @return string
     */
    private function insertZeroWidthWatermark(string $fingerprint): string {
        $zero_width_chars = [
            "\u{200B}", // Zero-width space
            "\u{200C}", // Zero-width non-joiner
            "\u{200D}", // Zero-width joiner
            "\u{FEFF}"  // Zero-width no-break space
        ];
        
        $encoded = '';
        foreach (str_split($fingerprint) as $char) {
            // Only add zero-width characters, not the visible fingerprint
            $encoded .= $zero_width_chars[ord($char) % 4];
        }
        
        return '<span class="zp-zw" data-fp="' . esc_attr($fingerprint) . '">' . $encoded . '</span>';
    }
    
    /**
     * Generate SVG watermark
     * 
     * @param string $fingerprint
     * @return string
     */
    private function generateSVGWatermark(string $fingerprint): string {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1">
            <metadata>' . $fingerprint . '</metadata>
            <rect width="1" height="1" fill="transparent"/>
        </svg>';
    }
    
    /**
     * Store fingerprint
     * 
     * @param string $fingerprint
     * @param array $data
     * @return void
     */
    private function storeFingerprint(string $fingerprint, array $data): void {
        \set_transient('fingerprint_' . $fingerprint, $data, 86400); // 24 hours
    }
    
    /**
     * Increment suspicious activity counter
     * 
     * @param string $ip
     * @return void
     */
    private function incrementSuspiciousActivity(string $ip): void {
        $key = 'suspicious_' . md5($ip);
        $count = (int) \get_transient($key);
        \set_transient($key, $count + 1, 3600); // Reset hourly
        
        // Auto-block after threshold
        if ($count + 1 >= 10) {
            $this->blockIP($ip, 'Excessive suspicious activity', 86400); // 24 hour block
        }
    }
    
    /**
     * Check alert threshold
     * 
     * @param string $ip
     * @return void
     */
    private function checkAlertThreshold(string $ip): void {
        $key = 'suspicious_' . md5($ip);
        $count = (int) \get_transient($key);
        
        if ($count >= 10) {
            // Send admin alert
            $admin_email = \get_option('admin_email');
            $subject = 'ZipPicks Security Alert: Potential Scraping Detected';
            $message = sprintf(
                "Suspicious activity detected from IP: %s\n\nActivity count: %d in the last hour\n\nPlease review the scrape logs in the admin panel.",
                $ip,
                $count
            );
            
            \wp_mail($admin_email, $subject, $message);
            
            // Reset counter to avoid spam
            \set_transient($key, 0, 3600);
        }
    }
    
    /**
     * Get WordPress-compatible session ID
     * 
     * @return string
     */
    private function getWordPressSessionId(): string {
        $user_id = \get_current_user_id();
        
        if ($user_id > 0) {
            // For logged-in users, use user meta
            $session_token = \get_user_meta($user_id, 'zippicks_session_token', true);
            
            if (empty($session_token)) {
                $session_token = \wp_generate_password(32, false);
                \update_user_meta($user_id, 'zippicks_session_token', $session_token);
            }
            
            return $session_token;
        } else {
            // For guests, use cookie-based token
            $cookie_name = 'zippicks_guest_session';
            
            if (isset($_COOKIE[$cookie_name])) {
                $session_token = \sanitize_text_field($_COOKIE[$cookie_name]);
            } else {
                $session_token = \wp_generate_password(32, false);
                
                // Set cookie for 24 hours
                \setcookie(
                    $cookie_name,
                    $session_token,
                    time() + \DAY_IN_SECONDS,
                    \COOKIEPATH,
                    \COOKIE_DOMAIN,
                    \is_ssl(),
                    true // httponly
                );
            }
            
            // Store in transient for server-side validation
            \set_transient('guest_session_' . $session_token, true, \DAY_IN_SECONDS);
            
            return $session_token;
        }
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function getClientIP(): string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (\filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Get transient count
     * 
     * @param string $key
     * @return int
     */
    private function getTransientCount(string $key): int {
        return (int) \get_transient($key);
    }
    
    /**
     * Increment transient count
     * 
     * @param string $key
     * @param int $expiration
     * @return void
     */
    private function incrementTransientCount(string $key, int $expiration): void {
        $count = $this->getTransientCount($key);
        \set_transient($key, $count + 1, $expiration);
    }
    
    /**
     * Log debug message
     * 
     * @param string $message
     * @param array $context
     * @return void
     */
    private function logDebug(string $message, array $context = []): void {
        if ($this->logger && method_exists($this->logger, 'debug')) {
            $this->logger->debug('[ScrapeProtection] ' . $message, $context);
        }
    }
    
    /**
     * Log info message
     * 
     * @param string $message
     * @param array $context
     * @return void
     */
    private function logInfo(string $message, array $context = []): void {
        if ($this->logger && method_exists($this->logger, 'info')) {
            $this->logger->info('[ScrapeProtection] ' . $message, $context);
        }
    }
    
    /**
     * Log warning message
     * 
     * @param string $message
     * @param array $context
     * @return void
     */
    private function logWarning(string $message, array $context = []): void {
        if ($this->logger && method_exists($this->logger, 'warning')) {
            $this->logger->warning('[ScrapeProtection] ' . $message, $context);
        }
    }
    
    /**
     * Log error message
     * 
     * @param string $message
     * @param array $context
     * @return void
     */
    private function logError(string $message, array $context = []): void {
        if ($this->logger && method_exists($this->logger, 'error')) {
            $this->logger->error('[ScrapeProtection] ' . $message, $context);
        }
    }
}