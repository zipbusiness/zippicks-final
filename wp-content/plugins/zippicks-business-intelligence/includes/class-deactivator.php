<?php
/**
 * Fired during plugin deactivation
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Includes;

class Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('zippicks_bi_cleanup_cache');
        wp_clear_scheduled_hook('zippicks_bi_cleanup_logs');
        
        // Clear cache
        wp_cache_flush();
        
        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_zippicks_bi_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_zippicks_bi_%'");
    }
}