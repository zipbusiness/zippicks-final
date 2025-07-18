<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class ZipPicks_Business_Deactivator {

    /**
     * Plugin deactivation handler.
     *
     * Cleans up scheduled tasks and flushes rewrite rules.
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('zippicks_business_daily_analytics');
        wp_clear_scheduled_hook('zippicks_business_subscription_check');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any transients
        delete_transient('zippicks_business_stats');
        delete_transient('zippicks_featured_businesses');
        
        // Log deactivation if logger is available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('ZipPicks Business deactivated', array(
                'version' => ZIPPICKS_BUSINESS_VERSION,
                'time' => current_time('mysql')
            ));
        }
        
        // Note: We intentionally do NOT:
        // - Remove database tables (data preservation)
        // - Remove capabilities (they may be used by other plugins)
        // - Delete options (settings preservation)
    }
}