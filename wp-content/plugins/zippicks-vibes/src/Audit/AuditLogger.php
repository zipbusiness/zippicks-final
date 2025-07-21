<?php
/**
 * Audit Logger
 *
 * Logs security events, searches, and scraping attempts.
 * Works seamlessly with or without Foundation logger.
 * Implements anti-scraping measures per CLAUDE.md requirements.
 *
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Audit;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class AuditLogger {

    /**
     * @var LoggerInterface|null Foundation logger instance
     */
    private $logger;

    /**
     * @var string Database table for scrape logging
     */
    private const SCRAPE_LOG_TABLE = 'zippicks_scrape_log';

    /**
     * @var array PSR-3 log level mapping
     */
    private const LOG_LEVELS = [
        'debug'     => LogLevel::DEBUG,
        'info'      => LogLevel::INFO,
        'notice'    => LogLevel::NOTICE,
        'warning'   => LogLevel::WARNING,
        'error'     => LogLevel::ERROR,
        'critical'  => LogLevel::CRITICAL,
        'alert'     => LogLevel::ALERT,
        'emergency' => LogLevel::EMERGENCY,
    ];

    /**
     * Constructor
     *
     * @param mixed $logger Optional Foundation logger or null
     */
    public function __construct($logger = null) {
        $this->logger = $logger;
        
        // Ensure scrape log table exists
        $this->ensureScrapingLogTable();
    }

    /**
     * Log a search query event
     *
     * @param string $query Search query
     * @param string $ip Client IP address
     * @param array $context Additional context (vibes, location, results count)
     */
    public function logSearch(string $query, string $ip, array $context = []): void {
        $data = array_merge([
            'query' => $this->sanitizeQuery($query),
            'ip'    => $this->anonymizeIp($ip),
            'timestamp' => time(),
            'date' => current_time('mysql'),
            'user_id' => get_current_user_id() ?: null,
            'session_id' => $this->getSessionId(),
            'results_count' => $context['results_count'] ?? 0,
            'vibes' => $context['vibes'] ?? [],
            'location' => $context['location'] ?? null,
        ], $context);

        $this->log('info', 'search.query', $data);
        
        // Track search demand for analytics
        $this->trackSearchDemand($query, $context);
    }

    /**
     * Log rate limit exceeded event
     *
     * @param string $endpoint API endpoint
     * @param string $ip Client IP address
     * @param array $context Additional context
     */
    public function logRateLimitExceeded(string $endpoint, string $ip, array $context = []): void {
        $data = array_merge([
            'endpoint' => $endpoint,
            'ip'       => $ip,
            'timestamp' => time(),
            'date' => current_time('mysql'),
            'user_agent' => $this->getUserAgent(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ], $context);

        $this->log('warning', 'rate.limit.exceeded', $data);
        
        // Track potential abuse
        $this->incrementAbuseCounter($ip);
    }

    /**
     * Log scraping attempt detection
     *
     * @param string $userAgent Detected user agent
     * @param string $ip Client IP address  
     * @param array $context Additional detection context
     */
    public function logScrapeAttempt(string $userAgent, string $ip, array $context = []): void {
        $data = array_merge([
            'ua' => substr($userAgent, 0, 255),
            'ip' => $ip,
            'timestamp' => time(),
            'date' => current_time('mysql'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'detection_reason' => $context['reason'] ?? 'suspicious_user_agent',
        ], $context);

        // Critical security event
        $this->log('critical', 'scrape.detected', $data);
        
        // Always log to database for security tracking
        $this->logScrapeToDatabase($data);
    }

    /**
     * Log access violation
     *
     * @param string $type Violation type (unauthorized, forbidden, etc.)
     * @param string $resource Resource attempting to access
     * @param string $ip Client IP address
     * @param array $context Additional context
     */
    public function logAccessViolation(string $type, string $resource, string $ip, array $context = []): void {
        $data = array_merge([
            'violation_type' => $type,
            'resource' => $resource,
            'ip' => $ip,
            'timestamp' => time(),
            'date' => current_time('mysql'),
            'user_id' => get_current_user_id() ?: null,
            'user_agent' => $this->getUserAgent(),
        ], $context);

        $this->log('critical', 'access.violation', $data);
        
        // Track for potential blocking
        $this->incrementAbuseCounter($ip);
    }

    /**
     * Log XSS attempt
     *
     * @param string $input Malicious input detected
     * @param string $field Field where XSS was attempted
     * @param string $ip Client IP address
     */
    public function logXssAttempt(string $input, string $field, string $ip): void {
        $this->log('critical', 'security.xss_attempt', [
            'field' => $field,
            'input_sample' => substr($input, 0, 100),
            'ip' => $ip,
            'timestamp' => time(),
            'date' => current_time('mysql'),
            'user_agent' => $this->getUserAgent(),
        ]);
        
        $this->incrementAbuseCounter($ip);
    }

    /**
     * Log SQL injection attempt
     *
     * @param string $input Malicious input detected
     * @param string $query Query pattern detected
     * @param string $ip Client IP address
     */
    public function logSqlInjectionAttempt(string $input, string $query, string $ip): void {
        $this->log('critical', 'security.sql_injection_attempt', [
            'input_sample' => substr($input, 0, 100),
            'query_pattern' => $this->extractSqlPattern($query),
            'ip' => $ip,
            'timestamp' => time(),
            'date' => current_time('mysql'),
            'user_agent' => $this->getUserAgent(),
        ]);
        
        $this->incrementAbuseCounter($ip);
    }

    /**
     * Log API response
     *
     * @param string $endpoint API endpoint
     * @param int $status HTTP status code
     * @param float $duration Request duration in seconds
     * @param array $context Additional context
     */
    public function logApiResponse(string $endpoint, int $status, float $duration, array $context = []): void {
        $level = $status >= 400 ? 'warning' : 'info';
        
        $this->log($level, 'api.response', array_merge([
            'endpoint' => $endpoint,
            'status' => $status,
            'duration_ms' => round($duration * 1000, 2),
            'ip' => $this->anonymizeIp($this->getClientIp()),
            'timestamp' => time(),
        ], $context));
    }

    /**
     * Log error event
     *
     * @param string $message Error message
     * @param array $context Error context
     */
    public function logError(string $message, array $context = []): void {
        $context['timestamp'] = time();
        $context['date'] = current_time('mysql');
        $context['trace'] = $this->getSimpleBacktrace();
        
        $this->log('error', $message, $context);
    }

    /**
     * Log debug information
     *
     * @param string $message Debug message
     * @param array $context Debug context
     */
    public function logDebug(string $message, array $context = []): void {
        if (!WP_DEBUG) {
            return;
        }

        $context['timestamp'] = time();
        $this->log('debug', $message, $context);
    }

    /**
     * Log warning event
     *
     * @param string $message Warning message
     * @param array $context Warning context
     */
    public function logWarning(string $message, array $context = []): void {
        $context['timestamp'] = time();
        $context['date'] = current_time('mysql');
        
        $this->log('warning', $message, $context);
    }

    /**
     * Log info event
     *
     * @param string $message Info message
     * @param array $context Info context
     */
    public function logInfo(string $message, array $context = []): void {
        $context['timestamp'] = time();
        
        $this->log('info', $message, $context);
    }

    /**
     * Log performance metrics
     *
     * @param string $operation Operation name
     * @param float $duration Duration in seconds
     * @param array $context Additional metrics
     */
    public function logPerformance(string $operation, float $duration, array $context = []): void {
        $level = $duration > 1.0 ? 'warning' : 'debug';
        
        $this->log($level, 'performance.' . $operation, array_merge([
            'duration_ms' => round($duration * 1000, 2),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'timestamp' => time(),
        ], $context));
    }

    /**
     * Core logging method with Foundation fallback
     *
     * @param string $level Log level
     * @param string $event Event name
     * @param array $context Event context
     */
    private function log(string $level, string $event, array $context): void {
        // Format message
        $message = sprintf('[ZipPicks Audit] %s', $event);
        
        // Try Foundation logger first
        if ($this->logger && method_exists($this->logger, 'log')) {
            try {
                $psrLevel = self::LOG_LEVELS[$level] ?? LogLevel::INFO;
                $this->logger->log($psrLevel, $message, $context);
                return;
            } catch (\Exception $e) {
                // Fall through to error_log
            }
        }

        // Fallback to WordPress error_log
        $this->errorLogFallback($level, $event, $context);
    }

    /**
     * Fallback logging to WordPress error_log
     *
     * @param string $level Log level
     * @param string $event Event name
     * @param array $context Event context
     */
    private function errorLogFallback(string $level, string $event, array $context): void {
        // Remove sensitive data from context
        $safeContext = $this->sanitizeContextForLog($context);
        
        $formatted = sprintf(
            '[ZipPicks Audit] [%s] %s: %s',
            strtoupper($level),
            $event,
            json_encode($safeContext, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)
        );

        error_log($formatted);
    }

    /**
     * Log scraping attempt to database
     *
     * @param array $data Scraping data
     */
    private function logScrapeToDatabase(array $data): void {
        global $wpdb;

        $table = $wpdb->prefix . self::SCRAPE_LOG_TABLE;

        try {
            $wpdb->insert(
                $table,
                [
                    'ip_address'   => $data['ip'],
                    'user_agent'   => $data['ua'] ?? '',
                    'request_path' => substr($data['request_uri'] ?? '', 0, 255),
                    'referrer'     => substr($data['referrer'] ?? '', 0, 255),
                    'timestamp'    => $data['date'] ?? current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        } catch (\Exception $e) {
            $this->errorLogFallback('error', 'scrape_log_failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Ensure scraping log table exists
     */
    private function ensureScrapingLogTable(): void {
        global $wpdb;

        $table = $wpdb->prefix . self::SCRAPE_LOG_TABLE;
        
        // Check if table exists
        $tableExists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table)
        ) === $table;

        if (!$tableExists) {
            $this->createScrapingLogTable();
        }
    }

    /**
     * Create scraping log table
     */
    private function createScrapingLogTable(): void {
        global $wpdb;

        $table = $wpdb->prefix . self::SCRAPE_LOG_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            user_agent varchar(255) DEFAULT '',
            request_path varchar(255) DEFAULT '',
            referrer varchar(255) DEFAULT '',
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ip_timestamp (ip_address, timestamp),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Track search demand for analytics
     *
     * @param string $query Search query
     * @param array $context Search context
     */
    private function trackSearchDemand(string $query, array $context): void {
        // Store search demand for waitlist/analytics
        $demandKey = 'zippicks_search_demand_' . md5($query);
        $count = (int) get_transient($demandKey);
        set_transient($demandKey, $count + 1, DAY_IN_SECONDS);

        // Track ZIP-specific demand
        if (!empty($context['location'])) {
            $zipKey = 'zippicks_zip_demand_' . $context['location'];
            $zipCount = (int) get_transient($zipKey);
            set_transient($zipKey, $zipCount + 1, DAY_IN_SECONDS);
        }
    }

    /**
     * Increment abuse counter for IP
     *
     * @param string $ip IP address
     */
    private function incrementAbuseCounter(string $ip): void {
        $key = 'zippicks_abuse_count_' . md5($ip);
        $count = (int) get_transient($key);
        $newCount = $count + 1;
        
        set_transient($key, $newCount, HOUR_IN_SECONDS);

        // Alert on threshold
        if ($newCount === 10) {
            $this->log('alert', 'abuse.threshold.reached', [
                'ip' => $ip,
                'count' => $newCount,
                'action' => 'consider_blocking'
            ]);
        }
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function getClientIp(): string {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_REAL_IP',           // Nginx
            'HTTP_CLIENT_IP',           // Proxy
            'REMOTE_ADDR'               // Standard
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Anonymize IP for GDPR compliance
     *
     * @param string $ip IP address
     * @return string
     */
    private function anonymizeIp(string $ip): string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Zero last octet
            return preg_replace('/\.\d+$/', '.0', $ip);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Zero last 64 bits
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 4)) . '::';
        }

        return $ip;
    }

    /**
     * Get user agent
     *
     * @return string
     */
    private function getUserAgent(): string {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    /**
     * Get session ID
     *
     * @return string
     */
    private function getSessionId(): string {
        if (session_status() === PHP_SESSION_ACTIVE && session_id()) {
            return session_id();
        }
        
        // Generate a unique ID for non-session requests
        return 'no-session-' . wp_generate_uuid4();
    }

    /**
     * Sanitize search query
     *
     * @param string $query
     * @return string
     */
    private function sanitizeQuery(string $query): string {
        return substr(sanitize_text_field($query), 0, 255);
    }

    /**
     * Sanitize context for logging
     *
     * @param array $context
     * @return array
     */
    private function sanitizeContextForLog(array $context): array {
        $sensitiveKeys = ['password', 'pwd', 'token', 'key', 'secret', 'nonce'];
        
        foreach ($context as $key => $value) {
            // Redact sensitive keys
            foreach ($sensitiveKeys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $context[$key] = '[REDACTED]';
                    break;
                }
            }
            
            // Limit string lengths
            if (is_string($value) && strlen($value) > 1000) {
                $context[$key] = substr($value, 0, 1000) . '...[truncated]';
            }
        }

        return $context;
    }

    /**
     * Extract SQL pattern for logging
     *
     * @param string $query
     * @return string
     */
    private function extractSqlPattern(string $query): string {
        $patterns = [
            'union', 'select', 'insert', 'update', 'delete',
            'drop', 'create', 'alter', 'exec', 'script'
        ];

        foreach ($patterns as $pattern) {
            if (stripos($query, $pattern) !== false) {
                return strtoupper($pattern) . '_PATTERN';
            }
        }

        return 'UNKNOWN_PATTERN';
    }

    /**
     * Get simple backtrace
     *
     * @param int $limit
     * @return array
     */
    private function getSimpleBacktrace(int $limit = 5): array {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit + 2);
        
        // Remove this method and caller
        array_shift($trace);
        array_shift($trace);

        return array_map(function($item) {
            return [
                'file' => isset($item['file']) ? basename($item['file']) : 'unknown',
                'line' => $item['line'] ?? 0,
                'function' => $item['function'] ?? 'unknown',
            ];
        }, $trace);
    }

    /**
     * Clean old scrape logs
     *
     * @param int $days Days to keep logs
     * @return int Number of records deleted
     */
    public function cleanOldScrapeLogs(int $days = 30): int {
        global $wpdb;

        $table = $wpdb->prefix . self::SCRAPE_LOG_TABLE;
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE timestamp < %s",
                $cutoff
            )
        );

        if ($deleted > 0) {
            $this->logInfo('scrape_logs_cleaned', [
                'deleted_count' => $deleted,
                'days_kept' => $days
            ]);
        }

        return $deleted ?: 0;
    }

    /**
     * Get scraping statistics
     *
     * @param int $hours Hours to look back
     * @return array
     */
    public function getScrapingStats(int $hours = 24): array {
        global $wpdb;

        $table = $wpdb->prefix . self::SCRAPE_LOG_TABLE;
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

        // Get total attempts
        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE timestamp > %s",
                $since
            )
        );

        // Get top IPs
        $topIps = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ip_address, COUNT(*) as count 
                FROM $table 
                WHERE timestamp > %s 
                GROUP BY ip_address 
                ORDER BY count DESC 
                LIMIT 10",
                $since
            ),
            ARRAY_A
        );

        // Get top user agents
        $topAgents = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_agent, COUNT(*) as count 
                FROM $table 
                WHERE timestamp > %s 
                GROUP BY user_agent 
                ORDER BY count DESC 
                LIMIT 10",
                $since
            ),
            ARRAY_A
        );

        return [
            'total_attempts' => (int) $total,
            'top_ips' => $topIps,
            'top_agents' => $topAgents,
            'hours_analyzed' => $hours
        ];
    }
}