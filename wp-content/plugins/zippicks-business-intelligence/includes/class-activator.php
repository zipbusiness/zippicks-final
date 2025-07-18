<?php
/**
 * Fired during plugin activation
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Includes;

class Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        // Create database tables if needed
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Create roles and capabilities
        self::create_roles();
        
        // Schedule cron events
        self::schedule_events();
        
        // Clear cache
        wp_cache_flush();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Business cache table
        $table_name = $wpdb->prefix . 'zippicks_business_cache';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            zpid varchar(100) NOT NULL,
            city varchar(100) NOT NULL,
            data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY zpid (zpid),
            KEY city (city),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // API request log table with enhanced columns
        $log_table = $wpdb->prefix . 'zippicks_bi_api_log';
        
        $sql = "CREATE TABLE IF NOT EXISTS $log_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            endpoint varchar(255) NOT NULL,
            method varchar(10) NOT NULL,
            status_code int(3),
            response_time float,
            error_message text,
            request_params text,
            context text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY endpoint (endpoint),
            KEY status_code (status_code)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = [
            'zippicks_bi_api_url' => 'https://api.zipbusiness.ai/v1',
            'zippicks_bi_api_key' => '',
            'zippicks_bi_cache_ttl' => 3600, // 1 hour
            'zippicks_bi_debug_mode' => false,
            'zippicks_bi_rate_limit' => 60, // requests per minute
            'zippicks_bi_retry_attempts' => 3,
            'zippicks_bi_timeout' => 30, // seconds
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
        
        // Plugin version
        update_option('zippicks_bi_version', ZIPPICKS_BI_VERSION);
    }
    
    /**
     * Create custom roles and capabilities
     */
    private static function create_roles() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_business_intelligence');
            $admin->add_cap('view_business_intelligence_logs');
        }
    }
    
    /**
     * Schedule cron events
     */
    private static function schedule_events() {
        if (!wp_next_scheduled('zippicks_bi_cleanup_cache')) {
            wp_schedule_event(time(), 'hourly', 'zippicks_bi_cleanup_cache');
        }
        
        if (!wp_next_scheduled('zippicks_bi_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'zippicks_bi_cleanup_logs');
        }
    }
}