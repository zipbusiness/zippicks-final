<?php
/**
 * Installation handler for ZipPicks Business plugin.
 *
 * Creates database tables and sets up initial data.
 */
class ZipPicks_Business_Installer {
    
    /**
     * Install plugin database tables and initial data
     */
    public static function install() {
        // Create database tables
        self::create_tables();
        
        // Set database version
        update_option('zippicks_business_db_version', ZIPPICKS_BUSINESS_VERSION);
        
        // Create default data
        self::create_default_data();
        
        // Register with Foundation if available
        self::register_with_foundation();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-database.php';
        
        // Try dbDelta first
        $result = ZipPicks_Business_Database::create_tables();
        
        // If tables don't exist, try direct creation
        if (!ZipPicks_Business_Database::verify_tables()) {
            ZipPicks_Business_Database::create_tables_direct();
        }
        
        // Final verification
        if (!ZipPicks_Business_Database::verify_tables()) {
            // Log error if logger is available
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->error('Failed to create ZipPicks Business tables');
            }
            
            // Show admin notice
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('ZipPicks Business: Failed to create database tables. Please check database permissions.', 'zippicks-business'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=zippicks-business-create-tables'); ?>" class="button">Create Tables Manually</a></p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * Check if tables exist
     */
    public static function tables_exist() {
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-database.php';
        return ZipPicks_Business_Database::verify_tables();
    }
    
    /**
     * Create default data
     */
    private static function create_default_data() {
        // Default monetization tiers
        $tiers = array(
            'basic' => array(
                'name' => 'Basic Listing',
                'price' => 0,
                'features' => array(
                    'business_profile' => true,
                    'contact_info' => true,
                    'basic_analytics' => true
                )
            ),
            'featured' => array(
                'name' => 'Featured Listing',
                'price' => 99,
                'features' => array(
                    'business_profile' => true,
                    'contact_info' => true,
                    'basic_analytics' => true,
                    'featured_badge' => true,
                    'priority_ranking' => true,
                    'advanced_analytics' => true
                )
            ),
            'premium' => array(
                'name' => 'Premium Listing',
                'price' => 199,
                'features' => array(
                    'business_profile' => true,
                    'contact_info' => true,
                    'basic_analytics' => true,
                    'featured_badge' => true,
                    'priority_ranking' => true,
                    'advanced_analytics' => true,
                    'api_access' => true,
                    'custom_branding' => true,
                    'lead_generation' => true
                )
            )
        );
        
        update_option('zippicks_business_tiers', $tiers);
    }
    
    /**
     * Register with Foundation
     */
    private static function register_with_foundation() {
        if (function_exists('zippicks') && zippicks()->has('database.installer')) {
            $installer = zippicks()->get('database.installer');
            $installer->register_schema('zippicks-business', function() {
                require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-database.php';
                return array(
                    'analytics' => ZipPicks_Business_Database::get_analytics_table_sql(),
                    'monetization' => ZipPicks_Business_Database::get_monetization_table_sql(),
                    'verification' => ZipPicks_Business_Database::get_verification_table_sql(),
                    'scrape_log' => ZipPicks_Business_Database::get_scrape_log_table_sql(),
                    'vibes' => ZipPicks_Business_Database::get_vibes_table_sql()
                );
            }, ZIPPICKS_BUSINESS_VERSION);
        }
    }
    
    /**
     * Uninstall plugin (called from uninstall.php)
     */
    public static function uninstall() {
        global $wpdb;
        
        // Only run if explicitly deleting plugin data
        if (!defined('ZIPPICKS_BUSINESS_REMOVE_ALL_DATA') || !ZIPPICKS_BUSINESS_REMOVE_ALL_DATA) {
            return;
        }
        
        // Remove tables
        $tables = array(
            ZipPicks_Business_Database::get_analytics_table(),
            ZipPicks_Business_Database::get_monetization_table(),
            ZipPicks_Business_Database::get_verification_table(),
            ZipPicks_Business_Database::get_scrape_log_table(),
            ZipPicks_Business_Database::get_vibes_table()
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Remove options
        delete_option('zippicks_business_version');
        delete_option('zippicks_business_db_version');
        delete_option('zippicks_business_settings');
        delete_option('zippicks_business_tiers');
        delete_option('zippicks_business_installed');
        
        // Remove capabilities
        $roles = wp_roles();
        foreach ($roles->roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if ($role) {
                $role->remove_cap('manage_businesses');
                $role->remove_cap('edit_business');
                $role->remove_cap('edit_businesses');
                $role->remove_cap('edit_others_businesses');
                $role->remove_cap('publish_businesses');
                $role->remove_cap('read_business');
                $role->remove_cap('read_private_businesses');
                $role->remove_cap('delete_business');
                $role->remove_cap('delete_businesses');
                $role->remove_cap('delete_others_businesses');
                $role->remove_cap('delete_private_businesses');
                $role->remove_cap('delete_published_businesses');
                $role->remove_cap('verify_businesses');
                $role->remove_cap('manage_business_monetization');
                $role->remove_cap('view_business_analytics');
            }
        }
        
        // Remove business owner role
        remove_role('business_owner');
    }
}