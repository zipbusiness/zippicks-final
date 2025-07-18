<?php
/**
 * Database Installer for ZipPicks Vibes
 * 
 * Creates and verifies database tables required for the vibes system
 * Uses existing v1 tables for compatibility
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Database;

use Exception;

/**
 * Database Installer Class
 */
class Installer {
    
    /**
     * Install database tables
     */
    public static function install(): void {
        global $wpdb;
        
        // Load required WordPress upgrade functions
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table names
        $vibes_table = $wpdb->prefix . 'zippicks_vibes';
        $categories_table = $wpdb->prefix . 'zippicks_vibe_categories';
        $assignments_table = $wpdb->prefix . 'zippicks_vibe_category_assignments';
        $waitlist_table = $wpdb->prefix . 'zippicks_waitlist';
        $scrape_log_table = $wpdb->prefix . 'zippicks_scrape_log';
        $security_log_table = $wpdb->prefix . 'zippicks_security_log';
        $rate_limit_log_table = $wpdb->prefix . 'zippicks_rate_limit_log';
        $security_events_table = $wpdb->prefix . 'zippicks_security_events';
        $blocked_ips_table = $wpdb->prefix . 'zippicks_blocked_ips';
        $audit_log_table = $wpdb->prefix . 'zippicks_audit_log';
        $metrics_table = $wpdb->prefix . 'zippicks_performance_metrics';
        
        try {
            // Prepare table creation SQL statements
            $tables = [];
            
            /**
             * Vibes Table
             * Core table for storing vibe definitions
             */
            $tables['vibes'] = "CREATE TABLE $vibes_table (
                id int(11) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                slug varchar(255) NOT NULL,
                description text,
                icon varchar(255) DEFAULT 'default',
                color varchar(7) DEFAULT '#000000',
                order_position int(11) DEFAULT 0,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY is_active (is_active),
                KEY order_position (order_position)
            ) $charset_collate;";
            
            /**
             * Categories Table
             * Hierarchical organization for vibes
             */
            $tables['categories'] = "CREATE TABLE $categories_table (
                id int(11) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                slug varchar(255) NOT NULL,
                description text,
                parent_id int(11) DEFAULT 0,
                order_position int(11) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY parent_id (parent_id)
            ) $charset_collate;";
            
            /**
             * Category Assignments Table
             * Many-to-many relationship between vibes and categories
             */
            $tables['assignments'] = "CREATE TABLE $assignments_table (
                id int(11) NOT NULL AUTO_INCREMENT,
                vibe_id int(11) NOT NULL,
                category_id int(11) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY vibe_category (vibe_id, category_id),
                KEY vibe_id (vibe_id),
                KEY category_id (category_id)
            ) $charset_collate;";
            
            /**
             * Waitlist Table
             * Tracks user interest in vibes by location
             */
            $tables['waitlist'] = "CREATE TABLE $waitlist_table (
                id int(11) NOT NULL AUTO_INCREMENT,
                vibe_id int(11) NOT NULL,
                user_email varchar(255) NOT NULL,
                zip_code varchar(10) DEFAULT NULL,
                city varchar(100) DEFAULT NULL,
                state varchar(50) DEFAULT NULL,
                ip_address varchar(45) DEFAULT NULL,
                user_agent text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY vibe_id (vibe_id),
                KEY user_email (user_email),
                KEY zip_code (zip_code),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            /**
             * Scrape Log Table
             * Anti-scraping protection logging
             */
            $tables['scrape_log'] = "CREATE TABLE $scrape_log_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                ip_address varchar(45) NOT NULL,
                user_agent text,
                request_path varchar(255) NOT NULL,
                referrer varchar(255) DEFAULT NULL,
                request_method varchar(10) DEFAULT 'GET',
                response_code int(3) DEFAULT NULL,
                attempt_type varchar(50) DEFAULT NULL,
                blocked tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ip_address (ip_address),
                KEY created_at (created_at),
                KEY blocked (blocked)
            ) $charset_collate;";
            
            /**
             * Security Log Table
             * General security event logging
             */
            $tables['security_log'] = "CREATE TABLE $security_log_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                event_type varchar(50) NOT NULL,
                ip_address varchar(45) NOT NULL,
                user_id bigint(20) DEFAULT NULL,
                user_agent text,
                request_uri varchar(255) DEFAULT NULL,
                violation_details text,
                severity varchar(20) DEFAULT 'low',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ip_address (ip_address),
                KEY event_type (event_type),
                KEY severity (severity),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            /**
             * Rate Limit Log Table
             * API rate limiting enforcement
             */
            $tables['rate_limit_log'] = "CREATE TABLE $rate_limit_log_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                ip_address varchar(45) NOT NULL,
                endpoint varchar(255) NOT NULL,
                requests_count int(11) DEFAULT 1,
                window_start datetime NOT NULL,
                window_end datetime NOT NULL,
                blocked tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ip_endpoint (ip_address, endpoint),
                KEY window_start (window_start),
                KEY blocked (blocked)
            ) $charset_collate;";
            
            /**
             * Security Events Table
             * Enhanced security event tracking with JSON context
             */
            $tables['security_events'] = "CREATE TABLE $security_events_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                event_type varchar(100) NOT NULL,
                ip_address varchar(45) NOT NULL,
                user_id bigint(20) DEFAULT NULL,
                context json DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY event_type (event_type),
                KEY ip_address (ip_address),
                KEY user_id (user_id),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            /**
             * Blocked IPs Table
             * IP blacklist management
             */
            $tables['blocked_ips'] = "CREATE TABLE $blocked_ips_table (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                reason TEXT,
                blocked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME,
                blocked_by BIGINT(20) UNSIGNED,
                PRIMARY KEY (id),
                UNIQUE KEY idx_ip (ip_address),
                INDEX idx_expires (expires_at)
            ) $charset_collate;";
            
            /**
             * Audit Log Table
             * Enterprise-grade audit trail
             */
            $tables['audit_log'] = "CREATE TABLE $audit_log_table (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                event_type varchar(50) NOT NULL,
                event_action varchar(50) NOT NULL,
                event_category varchar(50) NOT NULL DEFAULT 'general',
                user_id bigint(20) UNSIGNED NULL,
                ip_address varchar(45) NOT NULL,
                user_agent text,
                object_type varchar(50),
                object_id bigint(20) UNSIGNED,
                changes json,
                metadata json,
                severity varchar(20) DEFAULT 'info',
                status varchar(20) DEFAULT 'success',
                duration_ms int(11) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_event_type (event_type),
                KEY idx_event_action (event_action),
                KEY idx_user_date (user_id, created_at),
                KEY idx_object (object_type, object_id),
                KEY idx_created (created_at),
                KEY idx_severity (severity),
                KEY idx_category_date (event_category, created_at)
            ) $charset_collate;";
            
            /**
             * Performance Metrics Table
             * Application performance monitoring
             */
            $tables['metrics'] = "CREATE TABLE $metrics_table (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                metric_type varchar(50) NOT NULL,
                metric_name varchar(100) NOT NULL,
                metric_value decimal(10,4) NOT NULL,
                endpoint varchar(255) DEFAULT NULL,
                user_id bigint(20) UNSIGNED NULL,
                context json,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_type_name (metric_type, metric_name),
                KEY idx_created (created_at),
                KEY idx_endpoint (endpoint),
                KEY idx_metric_date (metric_name, created_at)
            ) $charset_collate;";
            
            // Execute table creation with error handling
            $created_tables = [];
            $failed_tables = [];
            
            foreach ($tables as $table_name => $sql) {
                try {
                    $result = dbDelta($sql);
                    
                    // Check if table was created or modified
                    if (!empty($result)) {
                        $created_tables[$table_name] = $result;
                        error_log("ZipPicks Vibes: Successfully processed table '$table_name'");
                    }
                } catch (\Exception $e) {
                    $failed_tables[$table_name] = $e->getMessage();
                    error_log("ZipPicks Vibes: Failed to create table '$table_name' - " . $e->getMessage());
                }
            }
            
            // Log overall results
            if (!empty($created_tables)) {
                error_log("ZipPicks Vibes: Database tables created/updated: " . implode(', ', array_keys($created_tables)));
            }
            
            if (!empty($failed_tables)) {
                error_log("ZipPicks Vibes: Failed tables: " . json_encode($failed_tables));
            }
            
            // Insert default categories if none exist
            self::insert_default_categories();
            
            // Log installation with Foundation if available
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                try {
                    $logger = zippicks()->get('logger');
                    $logger->info('ZipPicks Vibes database tables installed', [
                    'created_tables' => array_keys($created_tables),
                    'failed_tables' => $failed_tables,
                    'tables' => [
                        $vibes_table,
                        $categories_table,
                        $assignments_table,
                        $waitlist_table,
                        $scrape_log_table,
                        $security_log_table,
                        $rate_limit_log_table,
                        $security_events_table,
                        $blocked_ips_table,
                        $audit_log_table,
                        $metrics_table
                    ]
                ]);
                } catch (\Exception $e) {
                    // Silently fail logging
                    error_log('ZipPicks Vibes: Failed to log with Foundation - ' . $e->getMessage());
                }
            }
            
        } catch (\Exception $e) {
            error_log("ZipPicks Vibes: Critical error during database installation - " . $e->getMessage());
            error_log("ZipPicks Vibes: Stack trace - " . $e->getTraceAsString());
            
            // Re-throw to ensure activation fails if database setup fails
            throw $e;
        }
    }
    
    /**
     * Insert default categories
     */
    private static function insert_default_categories(): void {
        global $wpdb;
        
        $categories_table = $wpdb->prefix . 'zippicks_vibe_categories';
        
        try {
            // Check if categories exist
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $categories_table");
            
            if ($count == 0) {
                $default_categories = [
                    ['name' => 'Dining', 'slug' => 'dining', 'description' => 'Restaurant and food vibes'],
                    ['name' => 'Nightlife', 'slug' => 'nightlife', 'description' => 'Bars, clubs, and evening entertainment'],
                    ['name' => 'Outdoor', 'slug' => 'outdoor', 'description' => 'Parks, patios, and outdoor spaces'],
                    ['name' => 'Cultural', 'slug' => 'cultural', 'description' => 'Arts, museums, and cultural experiences'],
                    ['name' => 'Wellness', 'slug' => 'wellness', 'description' => 'Health, fitness, and relaxation'],
                    ['name' => 'Shopping', 'slug' => 'shopping', 'description' => 'Retail and boutique experiences'],
                    ['name' => 'Work', 'slug' => 'work', 'description' => 'Coffee shops, co-working, and productivity'],
                    ['name' => 'Family', 'slug' => 'family', 'description' => 'Kid-friendly and family activities'],
                    ['name' => 'Special', 'slug' => 'special', 'description' => 'Unique and special occasion vibes'],
                    ['name' => 'Local', 'slug' => 'local', 'description' => 'Neighborhood and community favorites']
                ];
                
                foreach ($default_categories as $index => $category) {
                    $result = $wpdb->insert(
                        $categories_table,
                        array_merge($category, ['order_position' => $index * 10])
                    );
                    
                    if ($result === false) {
                        error_log("ZipPicks Vibes: Failed to insert category '{$category['name']}' - " . $wpdb->last_error);
                    }
                }
                
                error_log("ZipPicks Vibes: Inserted " . count($default_categories) . " default categories");
            }
        } catch (\Exception $e) {
            error_log("ZipPicks Vibes: Error inserting default categories - " . $e->getMessage());
        }
    }
    
    /**
     * Check if all tables exist
     */
    public static function tables_exist(): bool {
        global $wpdb;
        
        $required_tables = [
            $wpdb->prefix . 'zippicks_vibes',
            $wpdb->prefix . 'zippicks_vibe_categories',
            $wpdb->prefix . 'zippicks_vibe_category_assignments',
            $wpdb->prefix . 'zippicks_waitlist',
            $wpdb->prefix . 'zippicks_scrape_log',
            $wpdb->prefix . 'zippicks_security_log',
            $wpdb->prefix . 'zippicks_rate_limit_log',
            $wpdb->prefix . 'zippicks_security_events',
            $wpdb->prefix . 'zippicks_blocked_ips',
            $wpdb->prefix . 'zippicks_audit_log',
            $wpdb->prefix . 'zippicks_performance_metrics'
        ];
        
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get schema SQL for Foundation integration
     */
    public static function get_schema_sql(): string {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;
        
        $sql = "
        -- ZipPicks Vibes Database Schema
        
        -- Vibes table
        CREATE TABLE {$prefix}zippicks_vibes (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            icon varchar(255) DEFAULT 'default',
            color varchar(7) DEFAULT '#000000',
            order_position int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active),
            KEY order_position (order_position)
        ) $charset_collate;
        
        -- Categories table
        CREATE TABLE {$prefix}zippicks_vibe_categories (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            parent_id int(11) DEFAULT 0,
            order_position int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY parent_id (parent_id)
        ) $charset_collate;
        
        -- Category assignments table
        CREATE TABLE {$prefix}zippicks_vibe_category_assignments (
            id int(11) NOT NULL AUTO_INCREMENT,
            vibe_id int(11) NOT NULL,
            category_id int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY vibe_category (vibe_id, category_id),
            KEY vibe_id (vibe_id),
            KEY category_id (category_id)
        ) $charset_collate;
        
        -- Waitlist table
        CREATE TABLE {$prefix}zippicks_waitlist (
            id int(11) NOT NULL AUTO_INCREMENT,
            vibe_id int(11) NOT NULL,
            user_email varchar(255) NOT NULL,
            zip_code varchar(10) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            state varchar(50) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY vibe_id (vibe_id),
            KEY user_email (user_email),
            KEY zip_code (zip_code),
            KEY created_at (created_at)
        ) $charset_collate;
        
        -- Scrape log table
        CREATE TABLE {$prefix}zippicks_scrape_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            request_path varchar(255) NOT NULL,
            referrer varchar(255) DEFAULT NULL,
            request_method varchar(10) DEFAULT 'GET',
            response_code int(3) DEFAULT NULL,
            attempt_type varchar(50) DEFAULT NULL,
            blocked tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ip_address (ip_address),
            KEY created_at (created_at),
            KEY blocked (blocked)
        ) $charset_collate;
        
        -- Security log table
        CREATE TABLE {$prefix}zippicks_security_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            user_agent text,
            request_uri varchar(255) DEFAULT NULL,
            violation_details text,
            severity varchar(20) DEFAULT 'low',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ip_address (ip_address),
            KEY event_type (event_type),
            KEY severity (severity),
            KEY created_at (created_at)
        ) $charset_collate;
        
        -- Rate limit log table
        CREATE TABLE {$prefix}zippicks_rate_limit_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            endpoint varchar(255) NOT NULL,
            requests_count int(11) DEFAULT 1,
            window_start datetime NOT NULL,
            window_end datetime NOT NULL,
            blocked tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ip_endpoint (ip_address, endpoint),
            KEY window_start (window_start),
            KEY blocked (blocked)
        ) $charset_collate;
        
        -- Blocked IPs table
        CREATE TABLE {$prefix}zippicks_blocked_ips (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address VARCHAR(45) NOT NULL,
            reason TEXT,
            blocked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME,
            blocked_by BIGINT(20) UNSIGNED,
            PRIMARY KEY (id),
            UNIQUE KEY idx_ip (ip_address),
            INDEX idx_expires (expires_at)
        ) $charset_collate;
        
        -- Security events table
        CREATE TABLE {$prefix}zippicks_security_events (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            context json DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY ip_address (ip_address),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;
        
        -- Audit log table
        CREATE TABLE {$prefix}zippicks_audit_log (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_action varchar(50) NOT NULL,
            event_category varchar(50) NOT NULL DEFAULT 'general',
            user_id bigint(20) UNSIGNED NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            object_type varchar(50),
            object_id bigint(20) UNSIGNED,
            changes json,
            metadata json,
            severity varchar(20) DEFAULT 'info',
            status varchar(20) DEFAULT 'success',
            duration_ms int(11) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_event_action (event_action),
            KEY idx_user_date (user_id, created_at),
            KEY idx_object (object_type, object_id),
            KEY idx_created (created_at),
            KEY idx_severity (severity),
            KEY idx_category_date (event_category, created_at)
        ) $charset_collate;
        
        -- Performance metrics table
        CREATE TABLE {$prefix}zippicks_performance_metrics (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            metric_type varchar(50) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value decimal(10,4) NOT NULL,
            endpoint varchar(255) DEFAULT NULL,
            user_id bigint(20) UNSIGNED NULL,
            context json,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_type_name (metric_type, metric_name),
            KEY idx_created (created_at),
            KEY idx_endpoint (endpoint),
            KEY idx_metric_date (metric_name, created_at)
        ) $charset_collate;
        ";
        
        return $sql;
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables(): void {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'zippicks_vibes',
            $wpdb->prefix . 'zippicks_vibe_categories', 
            $wpdb->prefix . 'zippicks_vibe_category_assignments',
            $wpdb->prefix . 'zippicks_waitlist',
            $wpdb->prefix . 'zippicks_scrape_log',
            $wpdb->prefix . 'zippicks_security_log',
            $wpdb->prefix . 'zippicks_rate_limit_log',
            $wpdb->prefix . 'zippicks_security_events',
            $wpdb->prefix . 'zippicks_blocked_ips',
            $wpdb->prefix . 'zippicks_audit_log',
            $wpdb->prefix . 'zippicks_performance_metrics'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}