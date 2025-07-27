<?php
/**
 * Plugin activation handler
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_Activator
 * 
 * Handles plugin activation tasks
 */
class ZipPicks_Social_Activator {
    
    /**
     * Activate the plugin
     *
     * @return void
     */
    public static function activate(): void {
        // Set initial plugin version
        update_option('zippicks_social_version', ZIPPICKS_SOCIAL_VERSION);
        
        // Run database migrations
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-database-migrator.php';
        $migration_result = ZipPicks_Social_Database_Migrator::run_migrations();
        
        // If migrations failed, try fallback table creation
        if ($migration_result['status'] !== 'success' && $migration_result['status'] !== 'up_to_date') {
            require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-database.php';
            ZipPicks_Social_Database::create_tables();
        }
        
        // Register with Foundation if available
        self::register_with_foundation();
        
        // Set default options
        self::set_default_options();
        
        // Create custom capabilities
        self::create_capabilities();
        
        // Schedule cron events
        self::schedule_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('ZipPicks Social activated', [
                'version' => ZIPPICKS_SOCIAL_VERSION,
                'migration_result' => $migration_result
            ]);
        }
    }
    
    /**
     * Register with Foundation database installer
     *
     * @return void
     */
    private static function register_with_foundation(): void {
        if (function_exists('zippicks') && zippicks()->has('database.installer')) {
            require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-database.php';
            require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-database-migrator.php';
            
            $installer = zippicks()->get('database.installer');
            $installer->register_schema(
                'zippicks-social',
                function() {
                    return ZipPicks_Social_Database::get_schema_sql();
                },
                ZipPicks_Social_Database_Migrator::CURRENT_SCHEMA_VERSION
            );
        }
    }
    
    /**
     * Set default plugin options
     *
     * @return void
     */
    private static function set_default_options(): void {
        $defaults = [
            'zippicks_social_enable_notifications' => 'yes',
            'zippicks_social_enable_activity_feed' => 'yes',
            'zippicks_social_enable_suggestions' => 'yes',
            'zippicks_social_follow_rate_limit' => 50, // per hour
            'zippicks_social_activity_retention_days' => 90,
            'zippicks_social_cache_duration' => 300, // 5 minutes
            'zippicks_social_enable_privacy_controls' => 'yes',
            'zippicks_social_default_notification_pref' => 'important',
            'zippicks_social_enable_digest_emails' => 'yes',
            'zippicks_social_digest_frequency' => 'weekly',
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }
    
    /**
     * Create custom capabilities
     *
     * @return void
     */
    private static function create_capabilities(): void {
        $roles = ['administrator', 'editor'];
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->add_cap('manage_zippicks_social');
                $role->add_cap('view_zippicks_social_analytics');
            }
        }
    }
    
    /**
     * Schedule cron events
     *
     * @return void
     */
    private static function schedule_events(): void {
        // Schedule hourly stats calculation
        if (!wp_next_scheduled('zippicks_social_calculate_stats')) {
            wp_schedule_event(time(), 'hourly', 'zippicks_social_calculate_stats');
        }
        
        // Schedule daily cleanup
        if (!wp_next_scheduled('zippicks_social_cleanup')) {
            wp_schedule_event(time(), 'daily', 'zippicks_social_cleanup');
        }
        
        // Schedule weekly digest emails
        if (!wp_next_scheduled('zippicks_social_send_digests')) {
            wp_schedule_event(time(), 'weekly', 'zippicks_social_send_digests');
        }
    }
    
    /**
     * Check if tables exist
     *
     * @return bool
     */
    public static function tables_exist(): bool {
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-database.php';
        return ZipPicks_Social_Database::verify_tables();
    }
}