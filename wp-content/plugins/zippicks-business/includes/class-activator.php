<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class ZipPicks_Business_Activator {

    /**
     * Plugin activation handler.
     *
     * Creates database tables, sets default options, and performs initial setup.
     */
    public static function activate() {
        // Load dependencies
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-database.php';
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-installer.php';
        
        // Create database tables
        ZipPicks_Business_Installer::install();
        
        // Set default options
        add_option('zippicks_business_version', ZIPPICKS_BUSINESS_VERSION);
        add_option('zippicks_business_installed', time());
        
        // Default settings
        $default_settings = array(
            'featured_listing_price' => 99,
            'premium_badge_price' => 49,
            'verification_enabled' => true,
            'analytics_enabled' => true,
            'anti_scraping_enabled' => true,
            'rate_limit_requests' => 60,
            'rate_limit_window' => 60 // seconds
        );
        
        add_option('zippicks_business_settings', $default_settings);
        
        // Create custom capabilities
        self::add_capabilities();
        
        // Schedule cron jobs
        if (!wp_next_scheduled('zippicks_business_daily_analytics')) {
            wp_schedule_event(time(), 'daily', 'zippicks_business_daily_analytics');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation if logger is available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('ZipPicks Business activated', array(
                'version' => ZIPPICKS_BUSINESS_VERSION,
                'time' => current_time('mysql')
            ));
        }
    }
    
    /**
     * Add custom capabilities for business management.
     */
    private static function add_capabilities() {
        $admin_role = get_role('administrator');
        
        if ($admin_role) {
            // Business management capabilities
            $admin_role->add_cap('manage_businesses');
            $admin_role->add_cap('edit_business');
            $admin_role->add_cap('edit_businesses');
            $admin_role->add_cap('edit_others_businesses');
            $admin_role->add_cap('publish_businesses');
            $admin_role->add_cap('read_business');
            $admin_role->add_cap('read_private_businesses');
            $admin_role->add_cap('delete_business');
            $admin_role->add_cap('delete_businesses');
            $admin_role->add_cap('delete_others_businesses');
            $admin_role->add_cap('delete_private_businesses');
            $admin_role->add_cap('delete_published_businesses');
            
            // Special capabilities
            $admin_role->add_cap('verify_businesses');
            $admin_role->add_cap('manage_business_monetization');
            $admin_role->add_cap('view_business_analytics');
        }
        
        // Create business owner role
        add_role('business_owner', 'Business Owner', array(
            'read' => true,
            'edit_business' => true,
            'read_business' => true,
            'upload_files' => true
        ));
    }
}