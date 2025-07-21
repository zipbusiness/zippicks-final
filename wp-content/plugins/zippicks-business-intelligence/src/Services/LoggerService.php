<?php
/**
 * Logger Service for structured API logging
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Services;

class LoggerService {
    
    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    /**
     * Configuration service
     *
     * @var ConfigService
     */
    private $config;
    
    /**
     * Foundation logger instance if available
     *
     * @var mixed
     */
    private $foundation_logger = null;
    
    /**
     * Constructor
     *
     * @param ConfigService $config
     */
    public function __construct(ConfigService $config) {
        $this->config = $config;
        
        // Try to get Foundation logger if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $this->foundation_logger = zippicks()->get('logger');
        }
    }
    
    /**
     * Log API request
     *
     * @param string $endpoint
     * @param string $method
     * @param array $params
     * @param int $status_code
     * @param float $response_time
     * @param array $context
     * @return void
     */
    public function log_api_request(
        string $endpoint,
        string $method,
        array $params,
        int $status_code,
        float $response_time,
        array $context = []
    ): void {
        global $wpdb;
        
        // Log to database
        $wpdb->insert(
            $wpdb->prefix . 'zippicks_bi_api_log',
            [
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $status_code,
                'response_time' => $response_time,
                'request_params' => json_encode($params),
                'context' => json_encode($context),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%d', '%f', '%s', '%s', '%s']
        );
        
        // Log to Foundation logger if available
        if ($this->foundation_logger) {
            $this->foundation_logger->info('BI API Request', [
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $status_code,
                'response_time' => $response_time,
                'params' => $params,
                'context' => $context
            ]);
        }
    }
    
    /**
     * Log API error
     *
     * @param string $endpoint
     * @param string $method
     * @param string $error_message
     * @param array $context
     * @return void
     */
    public function log_api_error(
        string $endpoint,
        string $method,
        string $error_message,
        array $context = []
    ): void {
        global $wpdb;
        
        // Log to database
        $wpdb->insert(
            $wpdb->prefix . 'zippicks_bi_api_log',
            [
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => 0,
                'response_time' => 0,
                'error_message' => $error_message,
                'context' => json_encode($context),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%d', '%f', '%s', '%s', '%s']
        );
        
        // Log to Foundation logger if available
        if ($this->foundation_logger) {
            $this->foundation_logger->error('BI API Error', [
                'endpoint' => $endpoint,
                'method' => $method,
                'error' => $error_message,
                'context' => $context
            ]);
        }
        
        // Also log to WordPress error log if debug mode
        if ($this->config->get('debug_mode')) {
            error_log(sprintf(
                '[ZipPicks BI] API Error: %s %s - %s',
                $method,
                $endpoint,
                $error_message
            ));
        }
    }
    
    /**
     * Log general message
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void {
        // Use Foundation logger if available
        if ($this->foundation_logger) {
            switch ($level) {
                case self::LEVEL_DEBUG:
                    $this->foundation_logger->debug($message, $context);
                    break;
                case self::LEVEL_INFO:
                    $this->foundation_logger->info($message, $context);
                    break;
                case self::LEVEL_WARNING:
                    $this->foundation_logger->warning($message, $context);
                    break;
                case self::LEVEL_ERROR:
                    $this->foundation_logger->error($message, $context);
                    break;
                case self::LEVEL_CRITICAL:
                    $this->foundation_logger->critical($message, $context);
                    break;
            }
        }
        
        // Log to WordPress error log if debug mode
        if ($this->config->get('debug_mode')) {
            error_log(sprintf(
                '[ZipPicks BI] [%s] %s %s',
                strtoupper($level),
                $message,
                !empty($context) ? json_encode($context) : ''
            ));
        }
    }
    
    /**
     * Get recent logs from database
     *
     * @param int $limit
     * @param array $filters
     * @return array
     */
    public function get_recent_logs(int $limit = 100, array $filters = []): array {
        global $wpdb;
        
        $where_clauses = ['1=1'];
        $where_values = [];
        
        // Apply filters
        if (!empty($filters['endpoint'])) {
            $where_clauses[] = 'endpoint LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like($filters['endpoint']) . '%';
        }
        
        if (!empty($filters['method'])) {
            $where_clauses[] = 'method = %s';
            $where_values[] = $filters['method'];
        }
        
        if (!empty($filters['has_error'])) {
            $where_clauses[] = 'error_message IS NOT NULL';
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $query = "SELECT * FROM {$wpdb->prefix}zippicks_bi_api_log 
                  WHERE {$where_sql} 
                  ORDER BY created_at DESC 
                  LIMIT %d";
        
        $where_values[] = $limit;
        
        $results = $wpdb->get_results(
            $wpdb->prepare($query, $where_values),
            ARRAY_A
        );
        
        return is_array($results) ? $results : [];
    }
    
    /**
     * Get log statistics
     *
     * @param string $period ('hour', 'day', 'week', 'month')
     * @return array
     */
    public function get_statistics(string $period = 'day'): array {
        global $wpdb;
        
        $interval = match($period) {
            'hour' => '1 HOUR',
            'day' => '1 DAY',
            'week' => '7 DAY',
            'month' => '30 DAY',
            default => '1 DAY'
        };
        
        // Get total requests
        $total_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_bi_api_log 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %s)",
            $interval
        ));
        
        // Get error count
        $error_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_bi_api_log 
             WHERE error_message IS NOT NULL 
             AND created_at >= DATE_SUB(NOW(), INTERVAL %s)",
            $interval
        ));
        
        // Get average response time
        $avg_response_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(response_time) FROM {$wpdb->prefix}zippicks_bi_api_log 
             WHERE response_time > 0 
             AND created_at >= DATE_SUB(NOW(), INTERVAL %s)",
            $interval
        ));
        
        // Get requests by endpoint
        $by_endpoint = $wpdb->get_results($wpdb->prepare(
            "SELECT endpoint, COUNT(*) as count, AVG(response_time) as avg_time 
             FROM {$wpdb->prefix}zippicks_bi_api_log 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %s)
             GROUP BY endpoint 
             ORDER BY count DESC",
            $interval
        ), ARRAY_A);
        
        return [
            'period' => $period,
            'total_requests' => (int) $total_requests,
            'error_count' => (int) $error_count,
            'error_rate' => $total_requests > 0 ? ($error_count / $total_requests) * 100 : 0,
            'avg_response_time' => round((float) $avg_response_time, 3),
            'by_endpoint' => is_array($by_endpoint) ? $by_endpoint : []
        ];
    }
    
    /**
     * Clear old logs
     *
     * @param int $days_to_keep
     * @return int Number of deleted rows
     */
    public function cleanup_old_logs(int $days_to_keep = 30): int {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}zippicks_bi_api_log 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_to_keep
        ));
    }
}