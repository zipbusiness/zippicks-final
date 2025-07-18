<?php
/**
 * Database Migration System for ZipPicks Master Critic
 *
 * Handles safe database schema evolution as new features are added.
 * Prevents conflicts and ensures proper versioning.
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Database_Migrator {
    
    /**
     * Current database schema version
     */
    const CURRENT_SCHEMA_VERSION = '1.4.0';
    
    /**
     * Option key for storing database version
     */
    const VERSION_OPTION = 'zippicks_master_critic_db_version';
    
    /**
     * Migration lock option to prevent concurrent migrations
     */
    const MIGRATION_LOCK = 'zippicks_master_critic_migration_lock';
    
    /**
     * Available migrations
     */
    private static $migrations = [
        '1.0.0' => 'migrate_to_1_0_0',
        '1.1.0' => 'migrate_to_1_1_0', 
        '1.2.0' => 'migrate_to_1_2_0',
        '1.3.0' => 'migrate_to_1_3_0',
        '1.4.0' => 'migrate_to_1_4_0',
    ];
    
    /**
     * Run all pending migrations
     *
     * @return array Migration results
     */
    public static function run_migrations() {
        // Check for migration lock
        if (get_transient(self::MIGRATION_LOCK)) {
            return [
                'status' => 'locked',
                'message' => 'Migration already in progress'
            ];
        }
        
        // Set migration lock (expires in 5 minutes)
        set_transient(self::MIGRATION_LOCK, time(), 300);
        
        try {
            $current_version = get_option(self::VERSION_OPTION, '0.0.0');
            $target_version = self::CURRENT_SCHEMA_VERSION;
            
            // If we're already at the latest version, nothing to do
            if (version_compare($current_version, $target_version, '>=')) {
                delete_transient(self::MIGRATION_LOCK);
                return [
                    'status' => 'up_to_date',
                    'current_version' => $current_version,
                    'target_version' => $target_version
                ];
            }
            
            $migration_results = [];
            
            // Run each migration in order
            foreach (self::$migrations as $version => $method) {
                // Skip if we're already past this version
                if (version_compare($current_version, $version, '>=')) {
                    continue;
                }
                
                // Run the migration
                $result = self::$method();
                $migration_results[$version] = $result;
                
                // If migration failed, stop here
                if (!$result['success']) {
                    delete_transient(self::MIGRATION_LOCK);
                    return [
                        'status' => 'failed',
                        'failed_at' => $version,
                        'error' => $result['error'],
                        'migrations' => $migration_results
                    ];
                }
                
                // Update version after successful migration
                update_option(self::VERSION_OPTION, $version);
                
                // Log the migration
                self::log_migration($version, $result);
            }
            
            // Release lock
            delete_transient(self::MIGRATION_LOCK);
            
            return [
                'status' => 'success',
                'from_version' => $current_version,
                'to_version' => $target_version,
                'migrations' => $migration_results
            ];
            
        } catch (Exception $e) {
            // Release lock on error
            delete_transient(self::MIGRATION_LOCK);
            
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if migrations are needed
     *
     * @return bool
     */
    public static function needs_migration() {
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        return version_compare($current_version, self::CURRENT_SCHEMA_VERSION, '<');
    }
    
    /**
     * Get current database version
     *
     * @return string
     */
    public static function get_current_version() {
        return get_option(self::VERSION_OPTION, '0.0.0');
    }
    
    /**
     * Migration to version 1.0.0 - Initial tables
     *
     * @return array
     */
    private static function migrate_to_1_0_0() {
        try {
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-database.php';
            
            // Create initial tables
            $results = ZipPicks_Master_Critic_Database::create_tables();
            
            // Verify tables were created
            if (!ZipPicks_Master_Critic_Database::verify_tables()) {
                return [
                    'success' => false,
                    'error' => 'Failed to create initial tables'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Created initial database tables',
                'tables_created' => [
                    'zippicks_generations',
                    'zippicks_prompt_templates',
                    'zippicks_list_analytics'
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Migration to version 1.1.0 - Add performance monitoring tables
     *
     * @return array
     */
    private static function migrate_to_1_1_0() {
        global $wpdb;
        
        try {
            $charset_collate = $wpdb->get_charset_collate();
            
            // Add new performance monitoring tables
            $query_metrics_table = $wpdb->prefix . 'zippicks_query_metrics';
            $api_usage_table = $wpdb->prefix . 'zippicks_api_usage_log';
            $api_cost_table = $wpdb->prefix . 'zippicks_api_cost_log';
            
            $sql_queries = [
                "CREATE TABLE IF NOT EXISTS $query_metrics_table (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    event_type varchar(50) NOT NULL,
                    duration_ms float NOT NULL,
                    metadata longtext,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_event_type (event_type),
                    KEY idx_created_at (created_at)
                ) $charset_collate;",
                
                "CREATE TABLE IF NOT EXISTS $api_usage_table (
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
                ) $charset_collate;",
                
                "CREATE TABLE IF NOT EXISTS $api_cost_table (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    api_name varchar(50) NOT NULL,
                    cost decimal(10,4) NOT NULL,
                    timestamp datetime DEFAULT CURRENT_TIMESTAMP,
                    metadata longtext,
                    PRIMARY KEY (id),
                    KEY idx_api_name (api_name),
                    KEY idx_timestamp (timestamp)
                ) $charset_collate;"
            ];
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $results = [];
            foreach ($sql_queries as $sql) {
                $result = dbDelta($sql);
                $results[] = $result;
            }
            
            return [
                'success' => true,
                'message' => 'Added performance monitoring tables',
                'tables_added' => [
                    'zippicks_query_metrics',
                    'zippicks_api_usage_log', 
                    'zippicks_api_cost_log'
                ],
                'dbdelta_results' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Migration to version 1.2.0 - Add anti-scraping protection
     *
     * @return array
     */
    private static function migrate_to_1_2_0() {
        global $wpdb;
        
        try {
            $charset_collate = $wpdb->get_charset_collate();
            
            // Add anti-scraping tables
            $scrape_log_table = $wpdb->prefix . 'zippicks_scrape_log';
            $query_patterns_table = $wpdb->prefix . 'zippicks_query_patterns';
            
            $sql_queries = [
                "CREATE TABLE IF NOT EXISTS $scrape_log_table (
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
                ) $charset_collate;",
                
                "CREATE TABLE IF NOT EXISTS $query_patterns_table (
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
                ) $charset_collate;"
            ];
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $results = [];
            foreach ($sql_queries as $sql) {
                $result = dbDelta($sql);
                $results[] = $result;
            }
            
            // Add indexes to existing tables if they don't exist
            self::add_missing_indexes();
            
            return [
                'success' => true,
                'message' => 'Added anti-scraping protection tables',
                'tables_added' => [
                    'zippicks_scrape_log',
                    'zippicks_query_patterns'
                ],
                'dbdelta_results' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Add missing indexes to existing tables
     *
     * @return void
     */
    private static function add_missing_indexes() {
        global $wpdb;
        
        // Add confidence_score index to generations table if it doesn't exist
        $generations_table = $wpdb->prefix . 'zippicks_generations';
        
        $index_exists = $wpdb->get_var(
            "SHOW INDEX FROM $generations_table WHERE Key_name = 'idx_confidence_score'"
        );
        
        if (!$index_exists) {
            $wpdb->query("ALTER TABLE $generations_table ADD INDEX idx_confidence_score (confidence_score)");
        }
    }
    
    /**
     * Log migration activity
     *
     * @param string $version
     * @param array $result
     */
    private static function log_migration($version, $result) {
        // Log via Foundation if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('Database migration completed', [
                'version' => $version,
                'result' => $result,
                'plugin' => 'master-critic'
            ]);
        }
        
        // Also log to WordPress debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'ZipPicks Master Critic: Database migrated to version %s - %s',
                $version,
                $result['success'] ? 'SUCCESS' : 'FAILED'
            ));
        }
    }
    
    /**
     * Rollback to a previous version (emergency use only)
     *
     * @param string $target_version
     * @return array
     */
    public static function rollback_to_version($target_version) {
        // This is a dangerous operation - only allow for admins
        if (!current_user_can('manage_options')) {
            return [
                'success' => false,
                'error' => 'Insufficient permissions'
            ];
        }
        
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        
        if (version_compare($target_version, $current_version, '>=')) {
            return [
                'success' => false,
                'error' => 'Cannot rollback to same or newer version'
            ];
        }
        
        // For now, just update the version - in the future we could add 
        // rollback migrations if needed
        update_option(self::VERSION_OPTION, $target_version);
        
        return [
            'success' => true,
            'message' => "Rolled back database version to $target_version",
            'warning' => 'Manual verification of database state recommended'
        ];
    }
    
    /**
     * Get migration status for admin display
     *
     * @return array
     */
    public static function get_migration_status() {
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        $target_version = self::CURRENT_SCHEMA_VERSION;
        $needs_migration = self::needs_migration();
        $migration_locked = (bool) get_transient(self::MIGRATION_LOCK);
        
        $pending_migrations = [];
        if ($needs_migration) {
            foreach (self::$migrations as $version => $method) {
                if (version_compare($current_version, $version, '<')) {
                    $pending_migrations[] = $version;
                }
            }
        }
        
        return [
            'current_version' => $current_version,
            'target_version' => $target_version,
            'needs_migration' => $needs_migration,
            'migration_locked' => $migration_locked,
            'pending_migrations' => $pending_migrations,
            'total_migrations' => count(self::$migrations)
        ];
    }
    
    /**
     * Migration to version 1.3.0 - Add list_category field for Top 10 categories
     *
     * @return array
     */
    private static function migrate_to_1_3_0() {
        global $wpdb;
        
        try {
            $generations_table = $wpdb->prefix . 'zippicks_generations';
            
            // Check if list_category column already exists
            $column_exists = $wpdb->get_var(
                "SHOW COLUMNS FROM $generations_table LIKE 'list_category'"
            );
            
            if (!$column_exists) {
                // Add list_category column after search_type
                $result = $wpdb->query(
                    "ALTER TABLE $generations_table 
                    ADD COLUMN list_category VARCHAR(50) DEFAULT 'best_overall' 
                    AFTER search_type"
                );
                
                if ($result === false) {
                    return [
                        'success' => false,
                        'error' => 'Failed to add list_category column: ' . $wpdb->last_error
                    ];
                }
                
                // Add index for list_category
                $wpdb->query(
                    "ALTER TABLE $generations_table 
                    ADD INDEX idx_list_category (list_category)"
                );
            }
            
            // Register post meta for master_critic_list posts
            register_post_meta('master_critic_list', '_mc_list_category', [
                'type' => 'string',
                'description' => 'The Top 10 category type',
                'single' => true,
                'default' => 'best_overall',
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ]);
            
            register_post_meta('master_critic_list', '_mc_vibe_ids', [
                'type' => 'array',
                'description' => 'Associated vibe taxonomy IDs',
                'single' => true,
                'default' => [],
                'show_in_rest' => [
                    'schema' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'integer'
                        ]
                    ]
                ]
            ]);
            
            return [
                'success' => true,
                'message' => 'Added list_category field and post meta registrations',
                'changes' => [
                    'Added list_category column to generations table',
                    'Added index on list_category',
                    'Registered _mc_list_category post meta',
                    'Registered _mc_vibe_ids post meta'
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Migration to version 1.4.0 - Add ZipBusiness API integration fields
     *
     * @return array
     */
    private static function migrate_to_1_4_0() {
        global $wpdb;
        
        try {
            $charset_collate = $wpdb->get_charset_collate();
            
            // 1. Add columns to generations table
            $generations_table = $wpdb->prefix . 'zippicks_generations';
            
            // Check and add api_verification_status
            $column_exists = $wpdb->get_var(
                "SHOW COLUMNS FROM $generations_table LIKE 'api_verification_status'"
            );
            
            if (!$column_exists) {
                $wpdb->query(
                    "ALTER TABLE $generations_table 
                    ADD COLUMN api_verification_status ENUM('verified', 'unverified', 'partial') DEFAULT 'unverified',
                    ADD COLUMN verified_count INT DEFAULT 0,
                    ADD COLUMN api_fetch_time TIMESTAMP NULL,
                    ADD COLUMN city_restaurant_count INT DEFAULT 0"
                );
            }
            
            // 2. Create API restaurant cache table
            $api_cache_table = $wpdb->prefix . 'zippicks_api_restaurant_cache';
            
            $sql = "CREATE TABLE IF NOT EXISTS $api_cache_table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                zpid varchar(20) NOT NULL,
                city varchar(100) NOT NULL,
                state varchar(2) NOT NULL,
                restaurant_data longtext NOT NULL,
                enriched_data longtext DEFAULT NULL,
                cache_time timestamp DEFAULT CURRENT_TIMESTAMP,
                enriched_time timestamp NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY idx_zpid (zpid),
                KEY idx_city_state (city, state),
                KEY idx_cache_time (cache_time)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // 3. Add zpid and verification fields to posts meta
            register_post_meta('zippicks_business', '_zpid', [
                'type' => 'string',
                'description' => 'ZipBusiness API ID',
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ]);
            
            register_post_meta('zippicks_business', '_api_verified', [
                'type' => 'boolean',
                'description' => 'Whether this business is verified via API',
                'single' => true,
                'default' => false,
                'show_in_rest' => true
            ]);
            
            register_post_meta('zippicks_business', '_api_confidence_score', [
                'type' => 'number',
                'description' => 'API match confidence score',
                'single' => true,
                'show_in_rest' => true
            ]);
            
            // 4. Add indexes
            $wpdb->query(
                "ALTER TABLE $generations_table 
                ADD INDEX idx_api_verification (api_verification_status),
                ADD INDEX idx_verified_count (verified_count)"
            );
            
            return [
                'success' => true,
                'message' => 'Added ZipBusiness API integration fields',
                'changes' => [
                    'Added API verification columns to generations table',
                    'Created zippicks_api_restaurant_cache table',
                    'Added zpid and verification post meta',
                    'Added relevant indexes'
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}