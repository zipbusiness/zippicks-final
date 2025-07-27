<?php
/**
 * Plugin deactivation handler
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_Deactivator
 * 
 * Handles plugin deactivation tasks
 */
class ZipPicks_Social_Deactivator {
    
    /**
     * Deactivate the plugin
     *
     * @return void
     */
    public static function deactivate(): void {
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Clear transient cache
        self::clear_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('ZipPicks Social deactivated', [
                'version' => ZIPPICKS_SOCIAL_VERSION
            ]);
        }
        
        // Note: We do NOT remove capabilities or data on deactivation
        // This preserves user data if they reactivate the plugin
    }
    
    /**
     * Clear scheduled cron events
     *
     * @return void
     */
    private static function clear_scheduled_events(): void {
        $events = [
            'zippicks_social_calculate_stats',
            'zippicks_social_cleanup',
            'zippicks_social_send_digests'
        ];
        
        foreach ($events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
        }
    }
    
    /**
     * Clear plugin cache
     *
     * @return void
     */
    private static function clear_cache(): void {
        global $wpdb;
        
        // Clear all plugin transients
        $transients = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_zippicks_social_%' 
             OR option_name LIKE '_transient_timeout_zippicks_social_%'"
        );
        
        foreach ($transients as $transient) {
            delete_option($transient);
        }
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('zippicks_social');
        }
    }
}