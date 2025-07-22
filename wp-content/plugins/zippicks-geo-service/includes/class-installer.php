<?php
/**
 * Installer Class
 * 
 * Handles plugin activation, deactivation, and database setup
 * 
 * @package ZipPicks_Geo_Service
 */

namespace ZipPicks\Geo;

class Installer {
    
    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Create necessary directories
        self::create_directories();
        
        // Schedule cron jobs
        self::schedule_cron_jobs();
        
        // Flush rewrite rules for REST API
        flush_rewrite_rules();
        
        // Log activation
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('ZipPicks Geo Service activated', [
                'version' => ZIPPICKS_GEO_VERSION,
                'db_version' => self::DB_VERSION,
            ]);
        }
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        self::clear_cron_jobs();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('ZipPicks Geo Service deactivated');
        }
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // User locations table - matches PostgreSQL naming convention
        $table_name = $wpdb->prefix . 'user_locations';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) unsigned DEFAULT NULL,
            session_id varchar(64) DEFAULT NULL,
            location_type varchar(20) NOT NULL,
            latitude decimal(10,8) NOT NULL,
            longitude decimal(11,8) NOT NULL,
            accuracy_meters int(11) DEFAULT NULL,
            zip_code varchar(10) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            state varchar(50) DEFAULT NULL,
            country varchar(2) DEFAULT 'US',
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_session (session_id),
            KEY idx_user (wp_user_id),
            KEY idx_expires (expires_at),
            KEY idx_created (created_at),
            KEY idx_location (latitude, longitude)
        ) $charset_collate;";
        
        // Location geocode cache table
        $table_name2 = $wpdb->prefix . 'location_geocode_cache';
        $sql2 = "CREATE TABLE IF NOT EXISTS $table_name2 (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            input_string varchar(255) NOT NULL,
            input_type varchar(20) NOT NULL,
            latitude decimal(10,8) DEFAULT NULL,
            longitude decimal(11,8) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            state varchar(50) DEFAULT NULL,
            country varchar(2) DEFAULT NULL,
            accuracy varchar(20) DEFAULT NULL,
            provider varchar(50) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_input (input_string, input_type),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        // ZIP code database table
        $table_name3 = $wpdb->prefix . 'zip_codes';
        $sql3 = "CREATE TABLE IF NOT EXISTS $table_name3 (
            zip_code varchar(10) NOT NULL,
            latitude decimal(10,8) NOT NULL,
            longitude decimal(11,8) NOT NULL,
            city varchar(100) NOT NULL,
            state varchar(2) NOT NULL,
            county varchar(100) DEFAULT NULL,
            timezone varchar(50) DEFAULT NULL,
            PRIMARY KEY (zip_code),
            KEY idx_location (latitude, longitude),
            KEY idx_city_state (city, state)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
        dbDelta($sql3);
        
        // Update database version
        update_option('zippicks_geo_db_version', self::DB_VERSION);
        
        // Add geohash column to restaurants table if it exists
        self::update_restaurants_table();
    }
    
    /**
     * Update restaurants table with geo columns
     */
    private static function update_restaurants_table() {
        global $wpdb;
        
        // Check if restaurants table exists (PostgreSQL)
        // This would be handled by the main application
        // For now, we'll just log the intent
        
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('Geo columns should be added to restaurants table', [
                'columns' => ['geo_hash', 'geo_updated_at'],
            ]);
        }
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        // Plugin settings
        add_option('zippicks_geo_settings', [
            'enable_gps' => true,
            'enable_ip_detection' => true,
            'cache_ttl' => 300,
            'rate_limits' => [
                'public' => 60,
                'user' => 100,
                'critic' => 200,
                'business' => 200,
                'admin' => 1000,
            ],
            'default_location' => [
                'lat' => 34.0522,
                'lng' => -118.2437,
                'city' => 'Los Angeles',
                'state' => 'CA',
            ],
            'privacy_defaults' => [
                'level' => 'city',
                'allow_gps' => false,
                'store_history' => false,
            ],
        ]);
        
        // MaxMind settings
        add_option('zippicks_geo_maxmind_key', '');
        add_option('zippicks_geo_maxmind_last_update', 0);
        
        // Statistics
        add_option('zippicks_geo_stats', [
            'total_lookups' => 0,
            'cache_hits' => 0,
            'ip_lookups' => 0,
            'gps_lookups' => 0,
            'last_reset' => current_time('timestamp'),
        ]);
    }
    
    /**
     * Create necessary directories
     */
    private static function create_directories() {
        $directories = [
            ZIPPICKS_GEO_PLUGIN_DIR . 'geoip',
            ZIPPICKS_GEO_PLUGIN_DIR . 'cache',
            ZIPPICKS_GEO_PLUGIN_DIR . 'logs',
        ];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Add index.php for security
                $index_content = '<?php // Silence is golden';
                file_put_contents($dir . '/index.php', $index_content);
            }
        }
    }
    
    /**
     * Schedule cron jobs
     */
    private static function schedule_cron_jobs() {
        // Schedule MaxMind database update (weekly)
        if (!wp_next_scheduled('zippicks_geo_update_maxmind')) {
            wp_schedule_event(time(), 'weekly', 'zippicks_geo_update_maxmind');
        }
        
        // Schedule cache cleanup (daily)
        if (!wp_next_scheduled('zippicks_geo_cleanup_cache')) {
            wp_schedule_event(time(), 'daily', 'zippicks_geo_cleanup_cache');
        }
        
        // Schedule location history cleanup (monthly)
        if (!wp_next_scheduled('zippicks_geo_cleanup_history')) {
            wp_schedule_event(time(), 'monthly', 'zippicks_geo_cleanup_history');
        }
    }
    
    /**
     * Clear cron jobs
     */
    private static function clear_cron_jobs() {
        wp_clear_scheduled_hook('zippicks_geo_update_maxmind');
        wp_clear_scheduled_hook('zippicks_geo_cleanup_cache');
        wp_clear_scheduled_hook('zippicks_geo_cleanup_history');
    }
    
    /**
     * Check if tables exist
     */
    public static function tables_exist() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'user_locations',
            $wpdb->prefix . 'location_geocode_cache',
            $wpdb->prefix . 'zip_codes',
        ];
        
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get database schema SQL
     */
    public static function get_schema_sql() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;
        
        return [
            'user_locations' => "CREATE TABLE {$prefix}user_locations (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                wp_user_id bigint(20) unsigned DEFAULT NULL,
                session_id varchar(64) DEFAULT NULL,
                location_type varchar(20) NOT NULL,
                latitude decimal(10,8) NOT NULL,
                longitude decimal(11,8) NOT NULL,
                accuracy_meters int(11) DEFAULT NULL,
                zip_code varchar(10) DEFAULT NULL,
                city varchar(100) DEFAULT NULL,
                state varchar(50) DEFAULT NULL,
                country varchar(2) DEFAULT 'US',
                ip_address varchar(45) DEFAULT NULL,
                user_agent text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                expires_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_session (session_id),
                KEY idx_user (wp_user_id),
                KEY idx_expires (expires_at),
                KEY idx_created (created_at),
                KEY idx_location (latitude, longitude)
            ) $charset_collate;",
            
            'geocode_cache' => "CREATE TABLE {$prefix}location_geocode_cache (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                input_string varchar(255) NOT NULL,
                input_type varchar(20) NOT NULL,
                latitude decimal(10,8) DEFAULT NULL,
                longitude decimal(11,8) DEFAULT NULL,
                city varchar(100) DEFAULT NULL,
                state varchar(50) DEFAULT NULL,
                country varchar(2) DEFAULT NULL,
                accuracy varchar(20) DEFAULT NULL,
                provider varchar(50) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_input (input_string, input_type),
                KEY idx_created (created_at)
            ) $charset_collate;",
            
            'zip_codes' => "CREATE TABLE {$prefix}zip_codes (
                zip_code varchar(10) NOT NULL,
                latitude decimal(10,8) NOT NULL,
                longitude decimal(11,8) NOT NULL,
                city varchar(100) NOT NULL,
                state varchar(2) NOT NULL,
                county varchar(100) DEFAULT NULL,
                timezone varchar(50) DEFAULT NULL,
                PRIMARY KEY (zip_code),
                KEY idx_location (latitude, longitude),
                KEY idx_city_state (city, state)
            ) $charset_collate;"
        ];
    }
    
    /**
     * Plugin uninstall (complete removal)
     */
    public static function uninstall() {
        global $wpdb;
        
        // Only run if explicitly deleting plugin
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }
        
        // Remove tables
        $tables = [
            $wpdb->prefix . 'user_locations',
            $wpdb->prefix . 'location_geocode_cache',
            $wpdb->prefix . 'zip_codes',
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Remove options
        delete_option('zippicks_geo_db_version');
        delete_option('zippicks_geo_settings');
        delete_option('zippicks_geo_maxmind_key');
        delete_option('zippicks_geo_maxmind_last_update');
        delete_option('zippicks_geo_stats');
        
        // Remove user meta
        $wpdb->delete($wpdb->usermeta, ['meta_key' => 'zippicks_location_preferences']);
        
        // Remove transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_zippicks:geo:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_zippicks:geo:%'");
        
        // Remove directories
        self::remove_directory(ZIPPICKS_GEO_PLUGIN_DIR . 'geoip');
        self::remove_directory(ZIPPICKS_GEO_PLUGIN_DIR . 'cache');
        self::remove_directory(ZIPPICKS_GEO_PLUGIN_DIR . 'logs');
    }
    
    /**
     * Recursively remove directory
     */
    private static function remove_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::remove_directory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}