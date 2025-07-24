<?php
/**
 * Plugin Installer
 * 
 * Handles plugin activation and deactivation
 * NO DATABASE TABLES - All data lives in PostgreSQL via API
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Installer {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Set default options
        self::set_default_options();
        
        // Create capabilities
        self::create_capabilities();
        
        // Flush rewrite rules for Business CPT
        flush_rewrite_rules();
        
        // Log activation
        error_log('ZipPicks Smart Search activated successfully');
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Remove capabilities
        self::remove_capabilities();
        
        // Clear scheduled events if any
        wp_clear_scheduled_hook('zippicks_search_cache_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('ZipPicks Smart Search deactivated');
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        // Search settings
        add_option('zippicks_search_cache_ttl', 300); // 5 minutes
        add_option('zippicks_search_max_results', 20);
        add_option('zippicks_search_default_radius', 10); // miles
        
        // API settings
        add_option('zippicks_search_api_timeout', 5); // seconds
        add_option('zippicks_search_api_retries', 2);
        
        // Intent classification thresholds
        add_option('zippicks_search_vibe_threshold', 0.7);
        add_option('zippicks_search_utility_threshold', 0.7);
        
        // Feature flags
        add_option('zippicks_search_enable_vibe_expansion', true);
        add_option('zippicks_search_enable_analytics', true);
        add_option('zippicks_search_enable_coming_soon', true);
    }
    
    /**
     * Create capabilities
     */
    private static function create_capabilities() {
        $role = get_role('administrator');
        
        if ($role) {
            $role->add_cap('manage_zippicks_search');
            $role->add_cap('view_search_analytics');
        }
        
        // Editor can view analytics
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('view_search_analytics');
        }
    }
    
    /**
     * Remove capabilities
     */
    private static function remove_capabilities() {
        // Remove from administrator role
        $role = get_role('administrator');
        
        if ($role) {
            $role->remove_cap('manage_zippicks_search');
            $role->remove_cap('view_search_analytics');
        }
        
        // Remove from editor role
        $editor = get_role('editor');
        if ($editor) {
            $editor->remove_cap('view_search_analytics');
        }
    }
}