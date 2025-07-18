<?php
/**
 * Database handling class for ZipPicks Master Critic
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Database {
    
    /**
     * Table names
     */
    const TABLE_GENERATIONS = 'zippicks_generations';
    const TABLE_PROMPT_TEMPLATES = 'zippicks_prompt_templates';
    const TABLE_LIST_ANALYTICS = 'zippicks_list_analytics';
    const TABLE_QUERY_METRICS = 'zippicks_query_metrics';
    const TABLE_API_USAGE_LOG = 'zippicks_api_usage_log';
    const TABLE_API_COST_LOG = 'zippicks_api_cost_log';
    const TABLE_QUERY_PATTERNS = 'zippicks_query_patterns';
    const TABLE_SCRAPE_LOG = 'zippicks_scrape_log';
    
    /**
     * Get the generations table name with prefix
     *
     * @return string
     */
    public static function get_generations_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_GENERATIONS;
    }
    
    /**
     * Get the prompt templates table name with prefix
     *
     * @return string
     */
    public static function get_prompt_templates_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_PROMPT_TEMPLATES;
    }
    
    /**
     * Get the list analytics table name with prefix
     *
     * @return string
     */
    public static function get_list_analytics_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_LIST_ANALYTICS;
    }
    
    /**
     * Get the query metrics table name with prefix
     *
     * @return string
     */
    public static function get_query_metrics_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_QUERY_METRICS;
    }
    
    /**
     * Get the API usage log table name with prefix
     *
     * @return string
     */
    public static function get_api_usage_log_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_API_USAGE_LOG;
    }
    
    /**
     * Get the API cost log table name with prefix
     *
     * @return string
     */
    public static function get_api_cost_log_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_API_COST_LOG;
    }
    
    /**
     * Get the query patterns table name with prefix
     *
     * @return string
     */
    public static function get_query_patterns_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_QUERY_PATTERNS;
    }
    
    /**
     * Get the scrape log table name with prefix
     *
     * @return string
     */
    public static function get_scrape_log_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SCRAPE_LOG;
    }
    
    /**
     * Create all tables
     *
     * @return array Results of table creation
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $results = array();
        
        // Create generations table
        $generations_table = self::get_generations_table();
        $sql_generations = "CREATE TABLE $generations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            business_category varchar(100) NOT NULL,
            topic varchar(255) NOT NULL,
            location varchar(255) NOT NULL,
            search_type varchar(50) NOT NULL,
            list_category varchar(50) DEFAULT 'best_overall',
            ai_provider varchar(50) NOT NULL,
            prompt longtext NOT NULL,
            ai_response longtext,
            businesses_created int(11) DEFAULT 0,
            list_id bigint(20) unsigned DEFAULT NULL,
            confidence_score float DEFAULT NULL,
            validation_report longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(50) DEFAULT 'pending',
            PRIMARY KEY (id),
            KEY idx_category (business_category),
            KEY idx_location (location),
            KEY idx_status (status),
            KEY idx_created (created_at),
            KEY idx_list_category (list_category)
        ) $charset_collate;";
        
        // Create prompt templates table
        $templates_table = self::get_prompt_templates_table();
        $sql_templates = "CREATE TABLE $templates_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            business_category varchar(100) NOT NULL,
            prompt_template longtext NOT NULL,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_category (business_category),
            KEY idx_default (is_default)
        ) $charset_collate;";
        
        // Create list analytics table
        $analytics_table = self::get_list_analytics_table();
        $sql_analytics = "CREATE TABLE $analytics_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            event_type varchar(50) NOT NULL DEFAULT 'view',
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            referrer text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_event_type (event_type)
        ) $charset_collate;";
        
        // Create query metrics table
        $query_metrics_table = self::get_query_metrics_table();
        $sql_query_metrics = "CREATE TABLE $query_metrics_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            duration_ms float NOT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        // Create API usage log table
        $api_usage_table = self::get_api_usage_log_table();
        $sql_api_usage = "CREATE TABLE $api_usage_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            api_name varchar(50) NOT NULL,
            usage_date date NOT NULL,
            request_count int(11) DEFAULT 1,
            cost decimal(10,4) DEFAULT 0,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_api_date (api_name, usage_date),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        // Create API cost log table
        $api_cost_table = self::get_api_cost_log_table();
        $sql_api_cost = "CREATE TABLE $api_cost_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            api_name varchar(50) NOT NULL,
            cost decimal(10,4) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            metadata longtext,
            PRIMARY KEY (id),
            KEY idx_api_name (api_name),
            KEY idx_timestamp (timestamp)
        ) $charset_collate;";
        
        // Create query patterns table
        $query_patterns_table = self::get_query_patterns_table();
        $sql_query_patterns = "CREATE TABLE $query_patterns_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            query_pattern longtext NOT NULL,
            frequency int(11) DEFAULT 1,
            revenue_potential decimal(10,2) DEFAULT 0,
            estimated_cost decimal(10,4) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_frequency (frequency),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        // Create scrape log table
        $scrape_log_table = self::get_scrape_log_table();
        $sql_scrape_log = "CREATE TABLE $scrape_log_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            list_id bigint(20) unsigned DEFAULT NULL,
            fingerprint varchar(64) DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            referer text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            action varchar(50) DEFAULT NULL,
            is_suspicious tinyint(1) DEFAULT 0,
            params longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_ip_address (ip_address),
            KEY idx_timestamp (timestamp),
            KEY idx_list_id (list_id),
            KEY idx_action (action),
            KEY idx_is_suspicious (is_suspicious)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $results['generations'] = dbDelta($sql_generations);
        $results['templates'] = dbDelta($sql_templates);
        $results['analytics'] = dbDelta($sql_analytics);
        $results['query_metrics'] = dbDelta($sql_query_metrics);
        $results['api_usage'] = dbDelta($sql_api_usage);
        $results['api_cost'] = dbDelta($sql_api_cost);
        $results['query_patterns'] = dbDelta($sql_query_patterns);
        $results['scrape_log'] = dbDelta($sql_scrape_log);
        
        return $results;
    }
    
    /**
     * Verify tables exist
     *
     * @return bool
     */
    public static function verify_tables() {
        global $wpdb;
        
        $tables = [
            self::get_generations_table(),
            self::get_prompt_templates_table(),
            self::get_list_analytics_table(),
            self::get_query_metrics_table(),
            self::get_api_usage_log_table(),
            self::get_api_cost_log_table(),
            self::get_query_patterns_table(),
            self::get_scrape_log_table()
        ];
        
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get schema SQL for Foundation integration
     *
     * @return string
     */
    public static function get_schema_sql() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $generations_table = self::get_generations_table();
        $templates_table = self::get_prompt_templates_table();
        $analytics_table = self::get_list_analytics_table();
        
        return "
        CREATE TABLE IF NOT EXISTS $generations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            business_category varchar(100) NOT NULL,
            topic varchar(255) NOT NULL,
            location varchar(255) NOT NULL,
            search_type varchar(50) NOT NULL,
            list_category varchar(50) DEFAULT 'best_overall',
            ai_provider varchar(50) NOT NULL,
            prompt longtext NOT NULL,
            ai_response longtext,
            businesses_created int(11) DEFAULT 0,
            list_id bigint(20) unsigned DEFAULT NULL,
            confidence_score float DEFAULT NULL,
            validation_report longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(50) DEFAULT 'pending',
            PRIMARY KEY (id),
            KEY idx_category (business_category),
            KEY idx_location (location),
            KEY idx_status (status),
            KEY idx_created (created_at),
            KEY idx_list_category (list_category)
        ) $charset_collate;
        
        CREATE TABLE IF NOT EXISTS $templates_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            business_category varchar(100) NOT NULL,
            prompt_template longtext NOT NULL,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_category (business_category),
            KEY idx_default (is_default)
        ) $charset_collate;
        
        CREATE TABLE IF NOT EXISTS $analytics_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            event_type varchar(50) NOT NULL DEFAULT 'view',
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            referrer text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_event_type (event_type)
        ) $charset_collate;
        
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_query_metrics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            duration_ms float NOT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_created_at (created_at)
        ) $charset_collate;
        
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_api_usage_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            api_name varchar(50) NOT NULL,
            usage_date date NOT NULL,
            request_count int(11) DEFAULT 1,
            cost decimal(10,4) DEFAULT 0,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_api_date (api_name, usage_date),
            KEY idx_created_at (created_at)
        ) $charset_collate;
        
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_api_cost_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            api_name varchar(50) NOT NULL,
            cost decimal(10,4) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            metadata longtext,
            PRIMARY KEY (id),
            KEY idx_api_name (api_name),
            KEY idx_timestamp (timestamp)
        ) $charset_collate;
        
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_query_patterns (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            query_pattern longtext NOT NULL,
            frequency int(11) DEFAULT 1,
            revenue_potential decimal(10,2) DEFAULT 0,
            estimated_cost decimal(10,4) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_frequency (frequency),
            KEY idx_created_at (created_at)
        ) $charset_collate;
        
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_scrape_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            list_id bigint(20) unsigned DEFAULT NULL,
            fingerprint varchar(64) DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            referer text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            action varchar(50) DEFAULT NULL,
            is_suspicious tinyint(1) DEFAULT 0,
            params longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_ip_address (ip_address),
            KEY idx_timestamp (timestamp),
            KEY idx_list_id (list_id),
            KEY idx_action (action),
            KEY idx_is_suspicious (is_suspicious)
        ) $charset_collate;
        ";
    }
    
    /**
     * Get generations table SQL
     *
     * @return string
     */
    public static function get_generations_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = self::get_generations_table();
        
        return "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            business_category varchar(100) NOT NULL,
            topic varchar(255) NOT NULL,
            location varchar(255) NOT NULL,
            search_type varchar(50) NOT NULL,
            list_category varchar(50) DEFAULT 'best_overall',
            ai_provider varchar(50) NOT NULL,
            prompt longtext NOT NULL,
            ai_response longtext,
            businesses_created int(11) DEFAULT 0,
            list_id bigint(20) unsigned DEFAULT NULL,
            confidence_score float DEFAULT NULL,
            validation_report longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(50) DEFAULT 'pending',
            PRIMARY KEY (id),
            KEY idx_category (business_category),
            KEY idx_location (location),
            KEY idx_status (status),
            KEY idx_created (created_at),
            KEY idx_list_category (list_category)
        ) $charset_collate;";
    }
    
    /**
     * Get templates table SQL
     *
     * @return string
     */
    public static function get_templates_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = self::get_prompt_templates_table();
        
        return "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            business_category varchar(100) NOT NULL,
            prompt_template longtext NOT NULL,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_category (business_category),
            KEY idx_default (is_default)
        ) $charset_collate;";
    }
    
    /**
     * Get analytics table SQL
     *
     * @return string
     */
    public static function get_analytics_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = self::get_list_analytics_table();
        
        return "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            list_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            referer text DEFAULT NULL,
            event_type varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_list_id (list_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_event_type (event_type)
        ) $charset_collate;";
    }
    
    /**
     * Get query metrics table SQL
     *
     * @return string
     */
    public static function get_query_metrics_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'zippicks_query_metrics';
        
        return "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            duration_ms float NOT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
    }
    
    /**
     * Get API usage table SQL
     *
     * @return string
     */
    public static function get_api_usage_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'zippicks_api_usage_log';
        
        return "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            api_name varchar(50) NOT NULL,
            usage_date date NOT NULL,
            request_count int(11) DEFAULT 1,
            cost decimal(10,4) DEFAULT 0,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_api_date (api_name, usage_date),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
    }
    
    /**
     * Get API cost table SQL
     *
     * @return string
     */
    public static function get_api_cost_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'zippicks_api_cost_log';
        
        return "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            api_name varchar(50) NOT NULL,
            cost decimal(10,4) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            metadata longtext,
            PRIMARY KEY (id),
            KEY idx_api_name (api_name),
            KEY idx_timestamp (timestamp)
        ) $charset_collate;";
    }
    
    /**
     * Get query patterns table SQL
     *
     * @return string
     */
    public static function get_query_patterns_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'zippicks_query_patterns';
        
        return "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            query_pattern longtext NOT NULL,
            frequency int(11) DEFAULT 1,
            revenue_potential decimal(10,2) DEFAULT 0,
            estimated_cost decimal(10,4) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_frequency (frequency),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
    }
    
    /**
     * Get scrape log table SQL
     *
     * @return string
     */
    public static function get_scrape_log_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = self::get_scrape_log_table();
        
        return "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            list_id bigint(20) unsigned DEFAULT NULL,
            fingerprint varchar(64) DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            referer text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            action varchar(50) DEFAULT NULL,
            is_suspicious tinyint(1) DEFAULT 0,
            params longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_ip_address (ip_address),
            KEY idx_timestamp (timestamp),
            KEY idx_list_id (list_id),
            KEY idx_action (action),
            KEY idx_is_suspicious (is_suspicious)
        ) $charset_collate;";
    }
    
    /**
     * Database cleanup routines for scrape logs
     *
     * @param int $days_to_keep Number of days to keep logs (default 30)
     * @return int Number of deleted records
     */
    public static function cleanup_scrape_logs($days_to_keep = 30) {
        global $wpdb;
        
        $scrape_log_table = self::get_scrape_log_table();
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$scrape_log_table} WHERE timestamp < %s",
            $cutoff_date
        ));
        
        return $deleted ?: 0;
    }
    
    /**
     * Get scrape log analytics
     *
     * @param int $days Number of days to analyze (default 7)
     * @return array Analytics data
     */
    public static function get_scrape_analytics($days = 7) {
        global $wpdb;
        
        $scrape_log_table = self::get_scrape_log_table();
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Total requests and suspicious activity
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                SUM(is_suspicious) as suspicious_requests,
                COUNT(DISTINCT ip_address) as unique_ips
            FROM {$scrape_log_table} 
            WHERE timestamp >= %s",
            $since_date
        ), ARRAY_A);
        
        // Top IPs by request count
        $top_ips = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                ip_address, 
                COUNT(*) as request_count,
                SUM(is_suspicious) as suspicious_count
            FROM {$scrape_log_table} 
            WHERE timestamp >= %s
            GROUP BY ip_address 
            ORDER BY request_count DESC 
            LIMIT 10",
            $since_date
        ), ARRAY_A);
        
        // Requests by action type
        $actions = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                action, 
                COUNT(*) as count,
                SUM(is_suspicious) as suspicious_count
            FROM {$scrape_log_table} 
            WHERE timestamp >= %s AND action IS NOT NULL
            GROUP BY action 
            ORDER BY count DESC",
            $since_date
        ), ARRAY_A);
        
        // Daily request breakdown
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(timestamp) as date,
                COUNT(*) as total_requests,
                SUM(is_suspicious) as suspicious_requests,
                COUNT(DISTINCT ip_address) as unique_ips
            FROM {$scrape_log_table} 
            WHERE timestamp >= %s
            GROUP BY DATE(timestamp) 
            ORDER BY date DESC",
            $since_date
        ), ARRAY_A);
        
        return array(
            'summary' => $totals,
            'top_ips' => $top_ips,
            'actions' => $actions,
            'daily_stats' => $daily_stats,
            'period_days' => $days
        );
    }
    
    /**
     * Log scrape attempt (optimized method)
     *
     * @param array $data Log data
     * @return bool Success status
     */
    public static function log_scrape_attempt($data) {
        global $wpdb;
        
        $scrape_log_table = self::get_scrape_log_table();
        
        $defaults = array(
            'list_id' => null,
            'fingerprint' => null,
            'ip_address' => '',
            'user_agent' => '',
            'referer' => '',
            'timestamp' => current_time('mysql'),
            'action' => null,
            'is_suspicious' => 0,
            'params' => null
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Ensure JSON encoding for params
        if (is_array($data['params'])) {
            $data['params'] = json_encode($data['params']);
        }
        
        return $wpdb->insert(
            $scrape_log_table,
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        ) !== false;
    }
    
    /**
     * Test scrape logging functionality
     *
     * @return array Test results
     */
    public static function test_scrape_logging() {
        $results = array();
        
        // Test 1: Table exists
        $table_exists = self::get_scrape_log_table();
        $results['table_exists'] = !empty($table_exists);
        
        // Test 2: Can write to table
        $test_data = array(
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test User Agent',
            'action' => 'test_log',
            'is_suspicious' => 0,
            'params' => json_encode(array('test' => true))
        );
        
        $write_success = self::log_scrape_attempt($test_data);
        $results['can_write'] = $write_success;
        
        // Test 3: Can read from table
        global $wpdb;
        $scrape_log_table = self::get_scrape_log_table();
        $test_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$scrape_log_table} WHERE action = %s ORDER BY id DESC LIMIT 1",
            'test_log'
        ));
        
        $results['can_read'] = !empty($test_record);
        
        // Test 4: Analytics function works
        try {
            $analytics = self::get_scrape_analytics(1);
            $results['analytics_works'] = !empty($analytics);
        } catch (Exception $e) {
            $results['analytics_works'] = false;
            $results['analytics_error'] = $e->getMessage();
        }
        
        // Clean up test data
        if ($write_success) {
            $wpdb->delete(
                $scrape_log_table,
                array('action' => 'test_log'),
                array('%s')
            );
        }
        
        return $results;
    }
    
    /**
     * Get suspicious activity alerts
     *
     * @param int $threshold Requests per minute threshold (default 10)
     * @return array Alert data
     */
    public static function get_suspicious_activity_alerts($threshold = 10) {
        global $wpdb;
        
        $scrape_log_table = self::get_scrape_log_table();
        $since_time = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        // IPs exceeding threshold
        $suspicious_ips = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                ip_address,
                COUNT(*) as request_count,
                MAX(timestamp) as last_request,
                SUM(is_suspicious) as suspicious_count
            FROM {$scrape_log_table} 
            WHERE timestamp >= %s
            GROUP BY ip_address 
            HAVING request_count > %d
            ORDER BY request_count DESC",
            $since_time,
            $threshold * 60 // Convert to hourly threshold
        ), ARRAY_A);
        
        // Recent suspicious activity
        $recent_suspicious = $wpdb->get_results($wpdb->prepare(
            "SELECT *
            FROM {$scrape_log_table} 
            WHERE is_suspicious = 1 AND timestamp >= %s
            ORDER BY timestamp DESC
            LIMIT 20",
            $since_time
        ), ARRAY_A);
        
        return array(
            'high_volume_ips' => $suspicious_ips,
            'recent_suspicious' => $recent_suspicious,
            'threshold' => $threshold,
            'period' => '1 hour'
        );
    }
    
    /**
     * Alternative table creation method (fallback)
     *
     * @return array
     */
    public static function create_tables_alternative() {
        global $wpdb;
        
        $results = array();
        $charset_collate = $wpdb->get_charset_collate();
        
        // Drop and recreate generations table
        $generations_table = self::get_generations_table();
        
        // Validate table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $generations_table)) {
            return array('error' => 'Invalid table name: ' . esc_html($generations_table));
        }
        
        // Use backticks for table name to prevent injection
        $wpdb->query("DROP TABLE IF EXISTS `{$generations_table}`");
        
        $sql = "CREATE TABLE `{$generations_table}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            business_category varchar(100) NOT NULL,
            topic varchar(255) NOT NULL,
            location varchar(255) NOT NULL,
            search_type varchar(50) NOT NULL,
            list_category varchar(50) DEFAULT 'best_overall',
            ai_provider varchar(50) NOT NULL,
            prompt longtext NOT NULL,
            ai_response longtext,
            businesses_created int(11) DEFAULT 0,
            list_id bigint(20) unsigned DEFAULT NULL,
            confidence_score float DEFAULT NULL,
            validation_report longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(50) DEFAULT 'pending',
            PRIMARY KEY (id),
            KEY idx_category (business_category),
            KEY idx_location (location),
            KEY idx_status (status),
            KEY idx_created (created_at),
            KEY idx_list_category (list_category)
        ) $charset_collate;";
        
        $results['generations'] = $wpdb->query($sql);
        
        // Drop and recreate templates table
        $templates_table = self::get_prompt_templates_table();
        
        // Validate table name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $templates_table)) {
            return array('error' => 'Invalid table name: ' . esc_html($templates_table));
        }
        
        $wpdb->query("DROP TABLE IF EXISTS `{$templates_table}`");
        
        $sql = "CREATE TABLE `{$templates_table}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            business_category varchar(100) NOT NULL,
            prompt_template longtext NOT NULL,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_category (business_category),
            KEY idx_default (is_default)
        ) $charset_collate;";
        
        $results['templates'] = $wpdb->query($sql);
        
        // Drop and recreate analytics table
        $analytics_table = self::get_list_analytics_table();
        
        // Validate table name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $analytics_table)) {
            return array('error' => 'Invalid table name: ' . esc_html($analytics_table));
        }
        
        $wpdb->query("DROP TABLE IF EXISTS `{$analytics_table}`");
        
        $sql = "CREATE TABLE `{$analytics_table}` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            event_type varchar(50) NOT NULL DEFAULT 'view',
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            referrer text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_event_type (event_type)
        ) $charset_collate;";
        
        $results['analytics'] = $wpdb->query($sql);
        
        return $results;
    }
    
    /**
     * Insert generation record
     *
     * @param array $data
     * @return int|false
     */
    public static function insert_generation($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            self::get_generations_table(),
            array(
                'business_category' => $data['business_category'],
                'topic' => $data['topic'],
                'location' => $data['location'],
                'search_type' => $data['search_type'],
                'list_category' => isset($data['list_category']) ? $data['list_category'] : 'best_overall',
                'ai_provider' => $data['ai_provider'],
                'prompt' => $data['prompt'],
                'ai_response' => isset($data['ai_response']) ? $data['ai_response'] : null,
                'confidence_score' => isset($data['confidence_score']) ? $data['confidence_score'] : null,
                'validation_report' => isset($data['validation_report']) ? $data['validation_report'] : null,
                'status' => isset($data['status']) ? $data['status'] : 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Insert generation with transaction support
     *
     * @param array $data
     * @param bool $use_transaction
     * @return int|false
     */
    public static function insert_generation_transactional($data, $use_transaction = true) {
        global $wpdb;
        
        if ($use_transaction) {
            $wpdb->query('START TRANSACTION');
        }
        
        try {
            $generation_id = self::insert_generation($data);
            
            if (!$generation_id) {
                throw new Exception('Failed to insert generation record');
            }
            
            if ($use_transaction) {
                $wpdb->query('COMMIT');
            }
            
            return $generation_id;
            
        } catch (Exception $e) {
            if ($use_transaction) {
                $wpdb->query('ROLLBACK');
            }
            
            // Log error
            if (class_exists('ZipPicks_Master_Critic_Error_Handler')) {
                ZipPicks_Master_Critic_Error_Handler::log_error('Database transaction failed', array(
                    'error' => $e->getMessage(),
                    'data' => $data
                ));
            }
            
            return false;
        }
    }
    
    /**
     * Update generation record
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update_generation($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if (isset($data['prompt'])) {
            $update_data['prompt'] = $data['prompt'];
            $format[] = '%s';
        }
        
        if (isset($data['ai_provider'])) {
            $update_data['ai_provider'] = $data['ai_provider'];
            $format[] = '%s';
        }
        
        if (isset($data['ai_response'])) {
            $update_data['ai_response'] = $data['ai_response'];
            $format[] = '%s';
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = $data['status'];
            $format[] = '%s';
        }
        
        if (isset($data['businesses_created'])) {
            $update_data['businesses_created'] = $data['businesses_created'];
            $format[] = '%d';
        }
        
        if (isset($data['list_id'])) {
            $update_data['list_id'] = $data['list_id'];
            $format[] = '%d';
        }
        
        if (isset($data['confidence_score'])) {
            $update_data['confidence_score'] = $data['confidence_score'];
            $format[] = '%f';
        }
        
        if (isset($data['validation_report'])) {
            $update_data['validation_report'] = $data['validation_report'];
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            self::get_generations_table(),
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        ) !== false;
    }
    
    /**
     * Get generation by ID
     *
     * @param int $id
     * @return object|null
     */
    public static function get_generation($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::get_generations_table() . " WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get recent generations
     *
     * @param int $limit
     * @return array
     */
    public static function get_recent_generations($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::get_generations_table() . " 
            ORDER BY created_at DESC 
            LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Save prompt template
     *
     * @param array $data
     * @return int|false
     */
    public static function save_prompt_template($data) {
        global $wpdb;
        
        // If marking as default, unset other defaults for this category
        if (!empty($data['is_default'])) {
            $wpdb->update(
                self::get_prompt_templates_table(),
                array('is_default' => 0),
                array('business_category' => $data['business_category']),
                array('%d'),
                array('%s')
            );
        }
        
        $result = $wpdb->insert(
            self::get_prompt_templates_table(),
            array(
                'name' => $data['name'],
                'business_category' => $data['business_category'],
                'prompt_template' => $data['prompt_template'],
                'is_default' => !empty($data['is_default']) ? 1 : 0
            ),
            array('%s', '%s', '%s', '%d')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get prompt templates for category
     *
     * @param string $category
     * @return array
     */
    public static function get_prompt_templates($category = null) {
        global $wpdb;
        
        if ($category) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM " . self::get_prompt_templates_table() . " 
                WHERE business_category = %s 
                ORDER BY is_default DESC, name ASC",
                $category
            ));
        }
        
        return $wpdb->get_results(
            "SELECT * FROM " . self::get_prompt_templates_table() . " 
            ORDER BY business_category, is_default DESC, name ASC"
        );
    }
    
    /**
     * Get a single template by ID
     *
     * @param int $id
     * @return object|null
     */
    public static function get_template($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::get_prompt_templates_table() . " WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Update a template
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update_template($id, $data) {
        global $wpdb;
        
        // If marking as default, unset other defaults for this category
        if (!empty($data['is_default'])) {
            $wpdb->update(
                self::get_prompt_templates_table(),
                array('is_default' => 0),
                array('business_category' => $data['business_category']),
                array('%d'),
                array('%s')
            );
        }
        
        $update_data = array(
            'name' => $data['name'],
            'business_category' => $data['business_category'],
            'prompt_template' => $data['prompt_template'],
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'updated_at' => current_time('mysql')
        );
        
        return $wpdb->update(
            self::get_prompt_templates_table(),
            $update_data,
            array('id' => $id),
            array('%s', '%s', '%s', '%d', '%s'),
            array('%d')
        ) !== false;
    }
    
    /**
     * Delete a template
     *
     * @param int $id
     * @return bool
     */
    public static function delete_template($id) {
        global $wpdb;
        
        return $wpdb->delete(
            self::get_prompt_templates_table(),
            array('id' => $id),
            array('%d')
        ) !== false;
    }
}