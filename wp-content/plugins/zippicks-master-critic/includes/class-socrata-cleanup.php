<?php
/**
 * Socrata Cleanup Utility
 * 
 * One-time cleanup to remove deprecated Socrata integration
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Socrata_Cleanup {
    
    /**
     * Run the cleanup on plugin activation
     */
    public static function run_on_activation() {
        // Check if cleanup has already been performed
        if (get_option('zippicks_socrata_cleanup_completed')) {
            return;
        }
        
        // Remove Socrata app token option
        delete_option('zippicks_socrata_app_token');
        
        // Remove any transients that might contain Socrata data
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '%socrata%' 
             AND option_name LIKE '_transient_%'"
        );
        
        // Clear any cached Socrata data
        wp_cache_delete('socrata_app_token', 'zippicks');
        
        // Mark cleanup as completed
        update_option('zippicks_socrata_cleanup_completed', true);
    }
}