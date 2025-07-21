<?php
namespace ZipPicks\Favorites;

if (!defined('ABSPATH')) {
    exit;
}

class Database {
    
    private static $favorites_table = 'zippicks_favorites';
    private static $favorites_meta_table = 'zippicks_favorites_meta';
    private static $location_cache_table = 'zippicks_location_cache';
    
    public static function get_favorites_table() {
        global $wpdb;
        return $wpdb->prefix . self::$favorites_table;
    }
    
    public static function get_favorites_meta_table() {
        global $wpdb;
        return $wpdb->prefix . self::$favorites_meta_table;
    }
    
    public static function get_location_cache_table() {
        global $wpdb;
        return $wpdb->prefix . self::$location_cache_table;
    }
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Main favorites table
        $favorites_table = self::get_favorites_table();
        $sql_favorites = "CREATE TABLE $favorites_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            business_id bigint(20) UNSIGNED NOT NULL,
            saved_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            user_notes text,
            latitude decimal(10, 8),
            longitude decimal(11, 8),
            city varchar(100),
            state varchar(50),
            country varchar(2) DEFAULT 'US',
            neighborhood varchar(100),
            zip_code varchar(10),
            PRIMARY KEY (id),
            UNIQUE KEY user_business (user_id, business_id),
            KEY user_id (user_id),
            KEY business_id (business_id),
            KEY location (latitude, longitude),
            KEY city_state (city, state),
            KEY saved_date (saved_date),
            KEY neighborhood (neighborhood),
            KEY zip_code (zip_code)
        ) $charset_collate;";
        
        // Favorites metadata table
        $favorites_meta_table = self::get_favorites_meta_table();
        $sql_favorites_meta = "CREATE TABLE $favorites_meta_table (
            meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            favorite_id bigint(20) UNSIGNED NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY favorite_id (favorite_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";
        
        // Location cache table for performance
        $location_cache_table = self::get_location_cache_table();
        $sql_location_cache = "CREATE TABLE $location_cache_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            location_key varchar(255) NOT NULL,
            latitude decimal(10, 8) NOT NULL,
            longitude decimal(11, 8) NOT NULL,
            city varchar(100),
            state varchar(50),
            country varchar(2) DEFAULT 'US',
            neighborhood varchar(100),
            zip_code varchar(10),
            formatted_address text,
            cached_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY location_key (location_key),
            KEY cached_date (cached_date)
        ) $charset_collate;";
        
        // Create tables - process each one individually
        $results = [];
        
        // Create favorites table
        $results['favorites'] = dbDelta($sql_favorites);
        
        // Force a small delay to ensure first table is created
        usleep(100000); // 0.1 second
        
        // Create favorites meta table
        $results['favorites_meta'] = dbDelta($sql_favorites_meta);
        
        // Another small delay
        usleep(100000); // 0.1 second
        
        // Create location cache table
        $results['location_cache'] = dbDelta($sql_location_cache);
        
        // Log results if logger is available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            zippicks()->get('logger')->info('ZipPicks Favorites tables created', [
                'results' => $results
            ]);
        }
        
        // Create stored procedures for geospatial queries
        self::create_stored_procedures();
        
        // Verify all tables were created
        $verification = self::verify_tables();
        if (!$verification['all_exist']) {
            // Try alternative creation method for missing tables
            self::create_tables_alternative();
        }
    }
    
    /**
     * Alternative table creation method using direct SQL
     */
    private static function create_tables_alternative() {
        global $wpdb;
        
        // Check and create each table individually
        $tables_to_check = [
            'favorites' => self::get_favorites_table(),
            'favorites_meta' => self::get_favorites_meta_table(),
            'location_cache' => self::get_location_cache_table()
        ];
        
        foreach ($tables_to_check as $key => $table_name) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if (!$exists) {
                // Get the SQL for this specific table
                $schemas = self::get_schema_sql();
                if (isset($schemas[$table_name])) {
                    $wpdb->query($schemas[$table_name]);
                }
            }
        }
    }
    
    /**
     * Verify tables exist
     */
    public static function verify_tables() {
        global $wpdb;
        
        $tables = [
            'favorites' => self::get_favorites_table(),
            'favorites_meta' => self::get_favorites_meta_table(),
            'location_cache' => self::get_location_cache_table()
        ];
        
        $all_exist = true;
        $status = [];
        
        foreach ($tables as $key => $table_name) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            $status[$key] = (bool) $exists;
            if (!$exists) {
                $all_exist = false;
            }
        }
        
        return [
            'all_exist' => $all_exist,
            'status' => $status
        ];
    }
    
    private static function create_stored_procedures() {
        global $wpdb;
        
        // Drop existing procedure if it exists
        $wpdb->query("DROP PROCEDURE IF EXISTS zippicks_get_favorites_within_radius");
        
        // Create procedure for radius-based searches
        $sql = "
        CREATE PROCEDURE zippicks_get_favorites_within_radius(
            IN user_id_param BIGINT,
            IN lat DECIMAL(10,8),
            IN lng DECIMAL(11,8),
            IN radius_km INT
        )
        BEGIN
            SELECT 
                f.*,
                (6371 * acos(
                    cos(radians(lat)) * cos(radians(f.latitude)) * 
                    cos(radians(f.longitude) - radians(lng)) + 
                    sin(radians(lat)) * sin(radians(f.latitude))
                )) AS distance_km
            FROM " . self::get_favorites_table() . " f
            WHERE f.user_id = user_id_param
            AND f.latitude IS NOT NULL 
            AND f.longitude IS NOT NULL
            HAVING distance_km <= radius_km
            ORDER BY distance_km;
        END";
        
        $wpdb->query($sql);
    }
    
    /**
     * Get schema SQL for foundation installer
     */
    public static function get_schema_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $schemas = [];
        
        // Main favorites table
        $favorites_table = self::get_favorites_table();
        $schemas[$favorites_table] = "CREATE TABLE $favorites_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            business_id bigint(20) UNSIGNED NOT NULL,
            saved_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            user_notes text,
            latitude decimal(10, 8),
            longitude decimal(11, 8),
            city varchar(100),
            state varchar(50),
            country varchar(2) DEFAULT 'US',
            neighborhood varchar(100),
            zip_code varchar(10),
            PRIMARY KEY (id),
            UNIQUE KEY user_business (user_id, business_id),
            KEY user_id (user_id),
            KEY business_id (business_id),
            KEY location (latitude, longitude),
            KEY city_state (city, state),
            KEY saved_date (saved_date),
            KEY neighborhood (neighborhood),
            KEY zip_code (zip_code)
        ) $charset_collate;";
        
        // Favorites metadata table
        $favorites_meta_table = self::get_favorites_meta_table();
        $schemas[$favorites_meta_table] = "CREATE TABLE $favorites_meta_table (
            meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            favorite_id bigint(20) UNSIGNED NOT NULL,
            meta_key varchar(255),
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY favorite_id (favorite_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";
        
        // Location cache table
        $location_cache_table = self::get_location_cache_table();
        $schemas[$location_cache_table] = "CREATE TABLE $location_cache_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            location_key varchar(255) NOT NULL,
            latitude decimal(10, 8) NOT NULL,
            longitude decimal(11, 8) NOT NULL,
            city varchar(100),
            state varchar(50),
            country varchar(2) DEFAULT 'US',
            neighborhood varchar(100),
            zip_code varchar(10),
            formatted_address text,
            cached_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY location_key (location_key),
            KEY cached_date (cached_date)
        ) $charset_collate;";
        
        return $schemas;
    }
    
    public static function drop_tables() {
        global $wpdb;
        
        // Drop stored procedures
        $wpdb->query("DROP PROCEDURE IF EXISTS zippicks_get_favorites_within_radius");
        
        // Drop tables
        $wpdb->query("DROP TABLE IF EXISTS " . self::get_favorites_table());
        $wpdb->query("DROP TABLE IF EXISTS " . self::get_favorites_meta_table());
        $wpdb->query("DROP TABLE IF EXISTS " . self::get_location_cache_table());
    }
    
    public static function seed_sample_data() {
        // Optional: Add sample data for testing
        global $wpdb;
        
        $sample_locations = [
            ['lat' => 34.0522, 'lng' => -118.2437, 'city' => 'Los Angeles', 'state' => 'CA'],
            ['lat' => 40.7128, 'lng' => -74.0060, 'city' => 'New York', 'state' => 'NY'],
            ['lat' => 37.7749, 'lng' => -122.4194, 'city' => 'San Francisco', 'state' => 'CA'],
        ];
        
        foreach ($sample_locations as $location) {
            $wpdb->insert(
                self::get_location_cache_table(),
                [
                    'location_key' => md5($location['city'] . $location['state']),
                    'latitude' => $location['lat'],
                    'longitude' => $location['lng'],
                    'city' => $location['city'],
                    'state' => $location['state'],
                    'country' => 'US'
                ]
            );
        }
    }
}