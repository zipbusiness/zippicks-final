<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load plugin file for access to constants
require_once plugin_dir_path(__FILE__) . 'zippicks-business.php';

// Only proceed if user explicitly wants to remove all data
// To enable complete removal, define this constant in wp-config.php:
// define('ZIPPICKS_BUSINESS_REMOVE_ALL_DATA', true);
if (!defined('ZIPPICKS_BUSINESS_REMOVE_ALL_DATA') || !ZIPPICKS_BUSINESS_REMOVE_ALL_DATA) {
    return;
}

// Load required files
require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-installer.php';

// Remove all plugin data
ZipPicks_Business_Installer::uninstall();

// Additional cleanup
global $wpdb;

// Delete all business posts
$business_posts = get_posts(array(
    'post_type' => 'zippicks_business',
    'numberposts' => -1,
    'post_status' => 'any'
));

foreach ($business_posts as $post) {
    wp_delete_post($post->ID, true); // Force delete (no trash)
}

// Delete all business post meta
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_zp_%'");

// Clear any cached data
wp_cache_flush();

// Remove scheduled cron events
wp_clear_scheduled_hook('zippicks_business_daily_analytics');
wp_clear_scheduled_hook('zippicks_business_subscription_check');

// Remove transients
delete_transient('zippicks_business_stats');
delete_transient('zippicks_featured_businesses');
delete_transient('zippicks_business_table_error');

// Log uninstall if Foundation logger is available
if (function_exists('zippicks') && is_callable('zippicks')) {
    try {
        $foundation = zippicks();
        if (method_exists($foundation, 'has') && $foundation->has('logger')) {
            $logger = $foundation->get('logger');
            if (method_exists($logger, 'info')) {
                $logger->info('ZipPicks Business uninstalled completely', array(
                    'timestamp' => current_time('mysql'),
                    'user_id' => get_current_user_id()
                ));
            }
        }
    } catch (Exception $e) {
        // Fail silently during uninstall
    }
}