<?php
namespace ZipPicks\Favorites;

if (!defined('ABSPATH')) {
    exit;
}

class Deactivator {
    
    public static function deactivate() {
        // Unschedule cron jobs
        self::unschedule_cron_jobs();
        
        // Clean up transients
        self::cleanup_transients();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            zippicks()->get('logger')->info('ZipPicks Favorites plugin deactivated', [
                'version' => ZIPPICKS_FAVORITES_VERSION,
                'user_id' => get_current_user_id()
            ]);
        }
    }
    
    private static function unschedule_cron_jobs() {
        wp_clear_scheduled_hook('zippicks_favorites_cleanup_cache');
        wp_clear_scheduled_hook('zippicks_favorites_aggregate_analytics');
    }
    
    private static function cleanup_transients() {
        global $wpdb;
        
        // Delete all plugin transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_zippicks_favorites_%' 
             OR option_name LIKE '_transient_timeout_zippicks_favorites_%'"
        );
    }
    
    public static function uninstall() {
        // This would be called on plugin deletion
        // Remove all data if needed
        
        if (get_option('zippicks_favorites_delete_on_uninstall', false)) {
            // Drop tables
            Database::drop_tables();
            
            // Delete all options
            self::delete_options();
            
            // Remove capabilities
            self::remove_capabilities();
        }
    }
    
    private static function delete_options() {
        $options = [
            'zippicks_favorites_default_radius',
            'zippicks_favorites_max_radius',
            'zippicks_favorites_enable_geolocation',
            'zippicks_favorites_cache_ttl',
            'zippicks_favorites_per_page',
            'zippicks_favorites_enable_map',
            'zippicks_favorites_map_provider',
            'zippicks_favorites_enable_export',
            'zippicks_favorites_track_analytics',
            'zippicks_favorites_analytics_retention',
            'zippicks_favorites_api_rate_limit',
            'zippicks_favorites_enable_public_lists',
            'zippicks_favorites_delete_on_uninstall'
        ];
        
        foreach ($options as $option) {
            delete_option($option);
        }
    }
    
    private static function remove_capabilities() {
        $roles = ['administrator', 'editor'];
        $caps = ['manage_zippicks_favorites', 'view_favorites_analytics'];
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
}