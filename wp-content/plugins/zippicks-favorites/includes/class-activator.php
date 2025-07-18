<?php
namespace ZipPicks\Favorites;

if (!defined('ABSPATH')) {
    exit;
}

class Activator {
    
    public static function activate() {
        // Create database tables
        Database::create_tables();
        
        // Add capabilities
        self::add_capabilities();
        
        // Schedule cron jobs
        self::schedule_cron_jobs();
        
        // Set default options
        self::set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            zippicks()->get('logger')->info('ZipPicks Favorites plugin activated', [
                'version' => ZIPPICKS_FAVORITES_VERSION,
                'user_id' => get_current_user_id()
            ]);
        }
    }
    
    private static function add_capabilities() {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_zippicks_favorites');
            $role->add_cap('view_favorites_analytics');
        }
        
        // Add capabilities for other roles
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('view_favorites_analytics');
        }
    }
    
    private static function schedule_cron_jobs() {
        // Schedule location cache cleanup
        if (!wp_next_scheduled('zippicks_favorites_cleanup_cache')) {
            wp_schedule_event(time(), 'daily', 'zippicks_favorites_cleanup_cache');
        }
        
        // Schedule analytics aggregation
        if (!wp_next_scheduled('zippicks_favorites_aggregate_analytics')) {
            wp_schedule_event(time(), 'hourly', 'zippicks_favorites_aggregate_analytics');
        }
    }
    
    private static function set_default_options() {
        // Location settings
        add_option('zippicks_favorites_default_radius', 10); // km
        add_option('zippicks_favorites_max_radius', 50); // km
        add_option('zippicks_favorites_enable_geolocation', true);
        add_option('zippicks_favorites_cache_ttl', 86400); // 24 hours
        
        // UI settings
        add_option('zippicks_favorites_per_page', 20);
        add_option('zippicks_favorites_enable_map', true);
        add_option('zippicks_favorites_map_provider', 'mapbox'); // or 'google'
        add_option('zippicks_favorites_enable_export', true);
        
        // Analytics settings
        add_option('zippicks_favorites_track_analytics', true);
        add_option('zippicks_favorites_analytics_retention', 90); // days
        
        // API settings
        add_option('zippicks_favorites_api_rate_limit', 100); // requests per minute
        add_option('zippicks_favorites_enable_public_lists', false);
    }
}