<?php
/**
 * Enterprise Audit Logger for Master Critic Plugin
 *
 * Provides comprehensive logging for compliance and security monitoring
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

class ZipPicks_Master_Critic_Audit_Logger {
    
    /**
     * Audit log table name
     */
    const TABLE_NAME = 'zippicks_audit_log';
    
    /**
     * Log levels
     */
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    const LEVEL_SECURITY = 'security';
    
    /**
     * Event types
     */
    const EVENT_GENERATION_START = 'generation_start';
    const EVENT_GENERATION_SUCCESS = 'generation_success';
    const EVENT_GENERATION_FAILURE = 'generation_failure';
    const EVENT_API_CALL = 'api_call';
    const EVENT_API_ERROR = 'api_error';
    const EVENT_BUSINESS_CREATE = 'business_create';
    const EVENT_LIST_CREATE = 'list_create';
    const EVENT_TEMPLATE_CHANGE = 'template_change';
    const EVENT_SETTINGS_CHANGE = 'settings_change';
    const EVENT_ACCESS_DENIED = 'access_denied';
    const EVENT_RATE_LIMIT = 'rate_limit';
    const EVENT_DATA_EXPORT = 'data_export';
    const EVENT_DATA_IMPORT = 'data_import';
    
    /**
     * Initialize the audit logger
     */
    public static function init() {
        // Create table if not exists
        self::create_table();
        
        // Hook into critical events
        add_action('zippicks_generation_started', array(__CLASS__, 'log_generation_start'), 10, 2);
        add_action('zippicks_generation_completed', array(__CLASS__, 'log_generation_success'), 10, 3);
        add_action('zippicks_generation_failed', array(__CLASS__, 'log_generation_failure'), 10, 3);
        add_action('zippicks_api_call', array(__CLASS__, 'log_api_call'), 10, 3);
        add_action('zippicks_settings_updated', array(__CLASS__, 'log_settings_change'), 10, 2);
        add_action('admin_init', array(__CLASS__, 'setup_cleanup_cron'));
    }
    
    /**
     * Create audit log table
     */
    private static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_level varchar(20) NOT NULL DEFAULT 'info',
            user_id bigint(20) DEFAULT NULL,
            user_login varchar(60) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            event_data longtext,
            event_message text,
            event_context text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_event_level (event_level),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_ip_address (ip_address)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log an audit event
     *
     * @param string $event_type
     * @param string $level
     * @param string $message
     * @param array $data
     * @param array $context
     * @return bool
     */
    public static function log($event_type, $level, $message, $data = array(), $context = array()) {
        global $wpdb;
        
        $user = wp_get_current_user();
        $user_id = $user->ID ?: null;
        $user_login = $user->user_login ?: 'anonymous';
        
        // Get IP address
        $ip_address = self::get_client_ip();
        
        // Get user agent
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // Prepare context
        $default_context = array(
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'http_method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
            'timestamp' => time(),
        );
        $context = array_merge($default_context, $context);
        
        // Insert log entry
        $result = $wpdb->insert(
            $wpdb->prefix . self::TABLE_NAME,
            array(
                'event_type' => $event_type,
                'event_level' => $level,
                'user_id' => $user_id,
                'user_login' => $user_login,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'event_data' => json_encode($data),
                'event_message' => $message,
                'event_context' => json_encode($context),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        // For critical events, also send to error log
        if ($level === self::LEVEL_CRITICAL || $level === self::LEVEL_SECURITY) {
            error_log(sprintf(
                '[Master Critic Audit] [%s] %s - User: %s, IP: %s',
                strtoupper($level),
                $message,
                $user_login,
                $ip_address
            ));
        }
        
        return $result !== false;
    }
    
    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Log generation start
     */
    public static function log_generation_start($generation_id, $params) {
        self::log(
            self::EVENT_GENERATION_START,
            self::LEVEL_INFO,
            'AI generation started',
            array(
                'generation_id' => $generation_id,
                'category' => $params['business_category'],
                'location' => $params['location'],
                'provider' => $params['ai_provider']
            )
        );
    }
    
    /**
     * Log generation success
     */
    public static function log_generation_success($generation_id, $result, $params) {
        self::log(
            self::EVENT_GENERATION_SUCCESS,
            self::LEVEL_INFO,
            'AI generation completed successfully',
            array(
                'generation_id' => $generation_id,
                'businesses_count' => count($result['businesses']),
                'confidence_score' => $result['confidence_score'] ?? null,
                'duration' => $result['duration'] ?? null
            )
        );
    }
    
    /**
     * Log generation failure
     */
    public static function log_generation_failure($generation_id, $error, $params) {
        self::log(
            self::EVENT_GENERATION_FAILURE,
            self::LEVEL_ERROR,
            'AI generation failed',
            array(
                'generation_id' => $generation_id,
                'error' => $error,
                'category' => $params['business_category'],
                'location' => $params['location']
            )
        );
    }
    
    /**
     * Log API call
     */
    public static function log_api_call($provider, $endpoint, $response_code) {
        self::log(
            self::EVENT_API_CALL,
            self::LEVEL_INFO,
            sprintf('API call to %s', $provider),
            array(
                'provider' => $provider,
                'endpoint' => $endpoint,
                'response_code' => $response_code
            )
        );
    }
    
    /**
     * Log settings change
     */
    public static function log_settings_change($setting_name, $changes) {
        self::log(
            self::EVENT_SETTINGS_CHANGE,
            self::LEVEL_WARNING,
            sprintf('Settings changed: %s', $setting_name),
            array(
                'setting' => $setting_name,
                'old_value' => $changes['old'] ?? null,
                'new_value' => $changes['new'] ?? null
            )
        );
    }
    
    /**
     * Log security event
     */
    public static function log_security_event($event_type, $message, $data = array()) {
        self::log(
            $event_type,
            self::LEVEL_SECURITY,
            $message,
            $data,
            array(
                'security_event' => true,
                'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null
            )
        );
    }
    
    /**
     * Get audit logs with filtering
     *
     * @param array $args
     * @return array
     */
    public static function get_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'event_type' => null,
            'event_level' => null,
            'user_id' => null,
            'date_from' => null,
            'date_to' => null,
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        $where = array('1=1');
        
        if ($args['event_type']) {
            $where[] = $wpdb->prepare('event_type = %s', $args['event_type']);
        }
        
        if ($args['event_level']) {
            $where[] = $wpdb->prepare('event_level = %s', $args['event_level']);
        }
        
        if ($args['user_id']) {
            $where[] = $wpdb->prepare('user_id = %d', $args['user_id']);
        }
        
        if ($args['date_from']) {
            $where[] = $wpdb->prepare('created_at >= %s', $args['date_from']);
        }
        
        if ($args['date_to']) {
            $where[] = $wpdb->prepare('created_at <= %s', $args['date_to']);
        }
        
        $where_clause = implode(' AND ', $where);
        $order_clause = sprintf('%s %s', $args['orderby'], $args['order']);
        
        $query = $wpdb->prepare(
            "SELECT * FROM `{$table_name}` 
             WHERE {$where_clause} 
             ORDER BY {$order_clause} 
             LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Setup cleanup cron job
     */
    public static function setup_cleanup_cron() {
        if (!wp_next_scheduled('zippicks_audit_cleanup')) {
            wp_schedule_event(time(), 'daily', 'zippicks_audit_cleanup');
        }
        
        add_action('zippicks_audit_cleanup', array(__CLASS__, 'cleanup_old_logs'));
    }
    
    /**
     * Cleanup old logs (keep 90 days by default)
     */
    public static function cleanup_old_logs() {
        global $wpdb;
        
        $retention_days = apply_filters('zippicks_audit_retention_days', 90);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$table_name}` WHERE created_at < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Export audit logs
     *
     * @param array $args
     * @return string CSV content
     */
    public static function export_logs($args = array()) {
        $logs = self::get_logs($args);
        
        $csv = "ID,Event Type,Level,User,IP Address,Message,Data,Created At\n";
        
        foreach ($logs as $log) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $log['id'],
                $log['event_type'],
                $log['event_level'],
                $log['user_login'],
                $log['ip_address'],
                str_replace('"', '""', $log['event_message']),
                str_replace('"', '""', $log['event_data']),
                $log['created_at']
            );
        }
        
        // Log the export event
        self::log(
            self::EVENT_DATA_EXPORT,
            self::LEVEL_INFO,
            'Audit logs exported',
            array('count' => count($logs))
        );
        
        return $csv;
    }
}