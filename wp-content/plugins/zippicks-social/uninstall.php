<?php
/**
 * Uninstall handler for ZipPicks Social
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Only proceed if user has admin capabilities
if (!current_user_can('activate_plugins')) {
    return;
}

// Check if it's a multisite installation
if (is_multisite()) {
    // Get all blog IDs
    $blog_ids = get_sites(['fields' => 'ids']);
    
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        zippicks_social_uninstall();
        restore_current_blog();
    }
} else {
    zippicks_social_uninstall();
}

/**
 * Perform uninstall tasks
 */
function zippicks_social_uninstall() {
    global $wpdb;
    
    // Remove options
    $options = [
        'zippicks_social_version',
        'zippicks_social_db_version',
        'zippicks_social_enable_notifications',
        'zippicks_social_enable_activity_feed',
        'zippicks_social_enable_suggestions',
        'zippicks_social_follow_rate_limit',
        'zippicks_social_activity_retention_days',
        'zippicks_social_cache_duration',
        'zippicks_social_enable_privacy_controls',
        'zippicks_social_default_notification_pref',
        'zippicks_social_enable_digest_emails',
        'zippicks_social_digest_frequency',
    ];
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Remove transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_zippicks_social_%' 
         OR option_name LIKE '_transient_timeout_zippicks_social_%'"
    );
    
    // Remove capabilities
    $roles = ['administrator', 'editor'];
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->remove_cap('manage_zippicks_social');
            $role->remove_cap('view_zippicks_social_analytics');
        }
    }
    
    // Drop database tables (optional - uncomment if you want to remove all data)
    // WARNING: This will permanently delete all follow data!
    /*
    $tables = [
        $wpdb->prefix . 'zippicks_follows',
        $wpdb->prefix . 'zippicks_follow_stats',
        $wpdb->prefix . 'zippicks_activities',
        $wpdb->prefix . 'zippicks_follow_suggestions'
    ];
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
    */
    
    // Clear scheduled cron events
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
    
    // Clear object cache
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('zippicks_social');
    }
}