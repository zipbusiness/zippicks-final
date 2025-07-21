<?php
/**
 * Handles plugin installation and database setup
 * 
 * This is a more robust installer that ensures tables are created
 * during various scenarios (activation, update, manual trigger).
 */

namespace ZipPicks\Favorites;

if (!defined('ABSPATH')) {
    exit;
}

class Installer {
    
    /**
     * Run the installer
     */
    public static function install() {
        // Check if we've already run installation for this version
        $installed_version = get_option('zippicks_favorites_version');
        
        if ($installed_version === ZIPPICKS_FAVORITES_VERSION) {
            // Already installed, but check if tables exist
            if (!self::tables_exist()) {
                self::create_tables();
            }
            return;
        }
        
        // Run installation tasks
        self::create_tables();
        self::create_roles();
        self::set_default_options();
        self::create_cron_jobs();
        
        // Update version
        update_option('zippicks_favorites_version', ZIPPICKS_FAVORITES_VERSION);
        
        // Set a flag to show activation notice
        set_transient('zippicks_favorites_activated', true, 30);
        
        // Clear any cached data
        wp_cache_flush();
        
        // Log installation
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            zippicks()->get('logger')->info('ZipPicks Favorites installed', [
                'version' => ZIPPICKS_FAVORITES_VERSION,
                'previous_version' => $installed_version
            ]);
        }
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        require_once ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/class-database.php';
        Database::create_tables();
    }
    
    /**
     * Check if all tables exist
     */
    public static function tables_exist() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'zippicks_favorites',
            $wpdb->prefix . 'zippicks_favorites_meta',
            $wpdb->prefix . 'zippicks_location_cache'
        ];
        
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            if (!$exists) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create roles and capabilities
     */
    private static function create_roles() {
        // Add capabilities to existing roles
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_zippicks_favorites');
            $admin->add_cap('view_favorites_analytics');
            $admin->add_cap('export_favorites_data');
        }
        
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('view_favorites_analytics');
        }
        
        // Create a custom role for favorites managers
        add_role('favorites_manager', __('Favorites Manager', 'zippicks-favorites'), [
            'read' => true,
            'manage_zippicks_favorites' => true,
            'view_favorites_analytics' => true,
            'export_favorites_data' => true
        ]);
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = [
            // Location settings
            'zippicks_favorites_default_radius' => 10,
            'zippicks_favorites_max_radius' => 50,
            'zippicks_favorites_enable_geolocation' => true,
            'zippicks_favorites_cache_ttl' => 86400,
            
            // UI settings
            'zippicks_favorites_per_page' => 20,
            'zippicks_favorites_enable_map' => true,
            'zippicks_favorites_map_provider' => 'mapbox',
            'zippicks_favorites_enable_export' => true,
            'zippicks_favorites_show_distance' => true,
            
            // Analytics settings
            'zippicks_favorites_track_analytics' => true,
            'zippicks_favorites_analytics_retention' => 90,
            
            // API settings
            'zippicks_favorites_api_rate_limit' => 100,
            'zippicks_favorites_enable_public_lists' => false,
            
            // Feature flags
            'zippicks_favorites_enable_collections' => true,
            'zippicks_favorites_enable_sharing' => true,
            'zippicks_favorites_enable_notes' => true
        ];
        
        foreach ($defaults as $option => $value) {
            add_option($option, $value);
        }
    }
    
    /**
     * Create scheduled cron jobs
     */
    private static function create_cron_jobs() {
        // Clean up old location cache entries
        if (!wp_next_scheduled('zippicks_favorites_cleanup_cache')) {
            wp_schedule_event(time(), 'daily', 'zippicks_favorites_cleanup_cache');
        }
        
        // Aggregate analytics data
        if (!wp_next_scheduled('zippicks_favorites_aggregate_analytics')) {
            wp_schedule_event(time(), 'hourly', 'zippicks_favorites_aggregate_analytics');
        }
        
        // Clean up orphaned favorites
        if (!wp_next_scheduled('zippicks_favorites_cleanup_orphaned')) {
            wp_schedule_event(time(), 'weekly', 'zippicks_favorites_cleanup_orphaned');
        }
    }
    
    /**
     * Run on plugin deactivation
     */
    public static function deactivate() {
        // Remove cron jobs
        wp_clear_scheduled_hook('zippicks_favorites_cleanup_cache');
        wp_clear_scheduled_hook('zippicks_favorites_aggregate_analytics');
        wp_clear_scheduled_hook('zippicks_favorites_cleanup_orphaned');
        
        // Clear transients
        delete_transient('zippicks_favorites_activated');
        delete_transient('zippicks_tables_created');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Run on plugin uninstall
     */
    public static function uninstall() {
        // Only run if explicitly uninstalling
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }
        
        // Remove all options
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'zippicks_favorites_%'");
        
        // Remove custom role
        remove_role('favorites_manager');
        
        // Remove capabilities from other roles
        $roles = ['administrator', 'editor'];
        $caps = ['manage_zippicks_favorites', 'view_favorites_analytics', 'export_favorites_data'];
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
        
        // Optionally remove tables (uncomment if desired)
        // require_once ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/class-database.php';
        // Database::drop_tables();
    }
}