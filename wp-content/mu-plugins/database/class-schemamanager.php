<?php
/**
 * Database Schema Manager
 * 
 * Manages database tables and schema for the ZipPicks platform.
 * 
 * @package ZipPicks\Foundation\Database
 */

namespace ZipPicks\Foundation\Database;

if (!defined('ABSPATH')) {
    exit;
}

class SchemaManager {
    
    /**
     * Database version
     * 
     * @var string
     */
    private $db_version = '1.0.0';
    
    /**
     * Option name for DB version
     * 
     * @var string
     */
    private $version_option = 'zippicks_db_version';
    
    /**
     * Constructor
     */
    public function __construct() {
        // No hooks here, called directly from Core
    }
    
    /**
     * Maybe create tables on activation
     */
    public function maybe_create_tables() {
        $current_version = get_option($this->version_option, '0');
        
        if (version_compare($current_version, $this->db_version, '<')) {
            $this->create_tables();
            update_option($this->version_option, $this->db_version);
        }
    }
    
    /**
     * Create all tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Include upgrade functions
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables
        $this->create_taste_profiles_table($charset_collate);
        $this->create_interactions_table($charset_collate);
        $this->create_zip_data_table($charset_collate);
        $this->create_demand_tracking_table($charset_collate);
        $this->create_demand_insights_table($charset_collate);
        $this->create_error_logs_table($charset_collate);
        $this->create_performance_logs_table($charset_collate);
        $this->create_notifications_table($charset_collate);
        $this->create_waitlist_table($charset_collate);
    }
    
    /**
     * Create taste profiles table
     * 
     * @param string $charset_collate Charset collation
     */
    private function create_taste_profiles_table($charset_collate) {
        global $wpdb;
        
        $table_name = ZIPPICKS_TABLE_PREFIX . 'taste_profiles';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            preferences longtext,
            behavior_data longtext,
            social_connections longtext,
            taste_vector text,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY last_updated (last_updated)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create interactions table
     * 
     * @param string $charset_collate Charset collation
     */
    private function create_interactions_table($charset_collate) {
        global $wpdb;
        
        $table_name = ZIPPICKS_TABLE_PREFIX . 'interactions';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            business_id bigint(20) unsigned NOT NULL,
            interaction_type varchar(50) NOT NULL,
            interaction_data longtext,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_business (user_id, business_id),
            KEY business_type (business_id, interaction_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create ZIP data table
     * 
     * @param string $charset_collate Charset collation
     */
    private function create_zip_data_table($charset_collate) {
        global $wpdb;
        
        $table_name = ZIPPICKS_TABLE_PREFIX . 'zip_data';
        
        $sql = "CREATE TABLE $table_name (
            zip_code varchar(10) NOT NULL,
            city varchar(100),
            state varchar(2),
            county varchar(100),
            latitude decimal(10,8),
            longitude decimal(11,8),
            timezone varchar(50),
            population int unsigned,
            median_income int unsigned,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (zip_code),
            KEY city_state (city, state),
            KEY lat_lng (latitude, longitude)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create demand tracking table
     * 
     * @param string $charset_collate Charset collation
     */
    private function create_demand_tracking_table($charset_collate) {
        global $wpdb;
        
        $table_name = ZIPPICKS_TABLE_PREFIX . 'demand_tracking';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            zip_code varchar(10) NOT NULL,
            demand_type varchar(50) NOT NULL,
            demand_data longtext,
            user_id bigint(20) unsigned,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY zip_type (zip_code, demand_type),
            KEY timestamp (timestamp),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create demand insights table
     * 
     * @param string $charset_collate Charset collation
     */
    private function create_demand_insights_table($charset_collate) {
        global $wpdb;
        
        $table_name = ZIPPICKS_TABLE_PREFIX . 'demand_insights';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            zip_code varchar(10) NOT NULL,
            period_type varchar(20) NOT NULL,
            period_value datetime NOT NULL,
            demand_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY zip_period (zip_code, period_type, period_value),
            KEY period (period_type, period_value)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create error logs table
     * 
     * @param string $charset_collate Charset collation
     */
    private function create_error_logs_table($charset_collate) {
        global $wpdb;
        
        $table_name = ZIPPICKS_TABLE_PREFIX . 'error_logs';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            user_id bigint(20) unsigned,
            ip_address varchar(45),
            url varchar(255),
            user_agent varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level_date (level, created_at),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create performance logs table
     * 
     * @param string $charset_collate Charset collation
     */
    private function create_performance_logs_table($charset_collate) {
        global $wpdb;
        
        $table_name = ZIPPICKS_TABLE_PREFIX . 'performance_logs';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            operation varchar(100) NOT NULL,
            duration decimal(10,3),
            memory_used bigint(20),
            queries int unsigned,
            context longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY operation_date (operation, created_at),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create notifications table
     * 
     * @param string $charset_collate Charset collation
     */
    private function create_notifications_table($charset_collate) {
        global $wpdb;
        
        $table_name = ZIPPICKS_TABLE_PREFIX . 'notifications';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text,
            data longtext,
            is_read tinyint(1) DEFAULT 0,
            is_seen tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            read_at datetime,
            PRIMARY KEY (id),
            KEY user_unread (user_id, is_read, created_at),
            KEY type_date (type, created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create waitlist table
     * 
     * @param string $charset_collate Charset collation
     */
    private function create_waitlist_table($charset_collate) {
        global $wpdb;
        
        $table_name = ZIPPICKS_TABLE_PREFIX . 'waitlist';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            zip_code varchar(10),
            user_type varchar(50),
            preferences longtext,
            referral_source varchar(100),
            status varchar(20) DEFAULT 'pending',
            invited_at datetime,
            joined_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY zip_status (zip_code, status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Drop all tables
     */
    public function drop_tables() {
        global $wpdb;
        
        $tables = [
            'taste_profiles',
            'interactions',
            'zip_data',
            'demand_tracking',
            'demand_insights',
            'error_logs',
            'performance_logs',
            'notifications',
            'waitlist'
        ];
        
        foreach ($tables as $table) {
            $table_name = ZIPPICKS_TABLE_PREFIX . $table;
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
        
        delete_option($this->version_option);
    }
    
    /**
     * Get table status
     * 
     * @return array Table status
     */
    public function get_table_status() {
        global $wpdb;
        
        $tables = [
            'taste_profiles',
            'interactions',
            'zip_data',
            'demand_tracking',
            'demand_insights',
            'error_logs',
            'performance_logs',
            'notifications',
            'waitlist'
        ];
        
        $status = [];
        
        foreach ($tables as $table) {
            $table_name = ZIPPICKS_TABLE_PREFIX . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            if ($exists) {
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                $size = $wpdb->get_var("
                    SELECT 
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                    FROM information_schema.TABLES 
                    WHERE table_schema = '" . DB_NAME . "' 
                    AND table_name = '$table_name'
                ");
                
                $status[$table] = [
                    'exists' => true,
                    'rows' => intval($row_count),
                    'size_mb' => floatval($size)
                ];
            } else {
                $status[$table] = [
                    'exists' => false,
                    'rows' => 0,
                    'size_mb' => 0
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * Optimize tables
     */
    public function optimize_tables() {
        global $wpdb;
        
        $tables = [
            'taste_profiles',
            'interactions',
            'zip_data',
            'demand_tracking',
            'demand_insights',
            'error_logs',
            'performance_logs',
            'notifications',
            'waitlist'
        ];
        
        foreach ($tables as $table) {
            $table_name = ZIPPICKS_TABLE_PREFIX . $table;
            $wpdb->query("OPTIMIZE TABLE $table_name");
        }
    }
    
    /**
     * Run maintenance tasks
     */
    public function run_maintenance() {
        // Clean old data
        $this->clean_old_logs();
        $this->clean_old_interactions();
        
        // Optimize tables
        $this->optimize_tables();
    }
    
    /**
     * Clean old logs
     */
    private function clean_old_logs() {
        global $wpdb;
        
        // Keep error logs for 90 days
        $error_table = ZIPPICKS_TABLE_PREFIX . 'error_logs';
        $wpdb->query("
            DELETE FROM $error_table 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        
        // Keep performance logs for 30 days
        $perf_table = ZIPPICKS_TABLE_PREFIX . 'performance_logs';
        $wpdb->query("
            DELETE FROM $perf_table 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
    }
    
    /**
     * Clean old interactions
     */
    private function clean_old_interactions() {
        global $wpdb;
        
        // Keep interactions for 180 days
        $table = ZIPPICKS_TABLE_PREFIX . 'interactions';
        $wpdb->query("
            DELETE FROM $table 
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL 180 DAY)
        ");
    }
}