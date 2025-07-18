<?php
/**
 * Plugin Name: ZipPicks Favorites
 * Plugin URI: https://zippicks.com/plugins/favorites
 * Description: Location-smart favorites system for saving and managing restaurant discoveries with intelligent geospatial filtering
 * Version: 1.0.0
 * Author: ZipPicks
 * Author URI: https://zippicks.com
 * License: Proprietary
 * Text Domain: zippicks-favorites
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

namespace ZipPicks\Favorites;

if (!defined('ABSPATH')) {
    exit;
}

define('ZIPPICKS_FAVORITES_VERSION', '1.0.0');
define('ZIPPICKS_FAVORITES_PLUGIN_FILE', __FILE__);
define('ZIPPICKS_FAVORITES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZIPPICKS_FAVORITES_PLUGIN_URL', plugin_dir_url(__FILE__));

class ZipPicks_Favorites {
    
    private static $instance = null;
    private $loader;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->define_hooks();
    }
    
    private function load_dependencies() {
        require_once ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/class-installer.php';
        require_once ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/class-activator.php';
        require_once ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/class-deactivator.php';
        require_once ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/class-database.php';
        require_once ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/class-api.php';
        require_once ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/class-location-service.php';
        require_once ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/class-favorites-manager.php';
        require_once ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/class-admin.php';
        require_once ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/class-analytics.php';
    }
    
    private function define_hooks() {
        register_activation_hook(__FILE__, [__NAMESPACE__ . '\Activator', 'activate']);
        register_deactivation_hook(__FILE__, [__NAMESPACE__ . '\Deactivator', 'deactivate']);
        
        add_action('plugins_loaded', [$this, 'init']);
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_notices', [$this, 'check_tables_exist']);
    }
    
    public function init() {
        // Check dependencies
        if (!$this->check_dependencies()) {
            return;
        }
        
        // Ensure tables exist (run installer if needed)
        if (!Installer::tables_exist()) {
            Installer::install();
        }
        
        // Register database schema with foundation
        if (function_exists('zippicks') && zippicks()->has('database.installer')) {
            $installer = zippicks()->get('database.installer');
            $installer->register_schema('zippicks-favorites', function() {
                return Database::get_schema_sql();
            }, ZIPPICKS_FAVORITES_VERSION);
            
            // Ensure tables exist on admin pages
            if (is_admin()) {
                add_action('admin_init', function() use ($installer) {
                    $installer->ensure_tables_exist();
                });
            }
        }
        
        // Initialize components
        new API();
        new Frontend();
        
        if (is_admin()) {
            new Admin();
        }
        
        // Register service with foundation if available
        if (function_exists('zippicks')) {
            zippicks()->bind('favorites', new Favorites_Manager());
            zippicks()->bind('location.service', new Location_Service());
        }
        
        // Add admin action for manual table creation
        add_action('admin_action_zippicks_create_tables', [$this, 'manual_create_tables']);
    }
    
    /**
     * Manual table creation via admin action
     */
    public function manual_create_tables() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Verify nonce for security
        if (isset($_GET['_wpnonce']) && !wp_verify_nonce($_GET['_wpnonce'], 'zippicks_create_tables')) {
            wp_die('Security check failed');
        }
        
        // Create tables
        Database::create_tables();
        
        // Set a transient to show success message
        set_transient('zippicks_tables_created', true, 30);
        
        // Redirect to plugin settings or admin page
        $redirect_url = add_query_arg(
            ['tables_created' => '1'],
            admin_url('admin.php?page=zippicks-favorites')
        );
        
        wp_redirect($redirect_url);
        exit;
    }
    
    private function check_dependencies() {
        // Check if ZipPicks Core is active
        if (!function_exists('zippicks')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('ZipPicks Favorites requires ZipPicks Core to be active.', 'zippicks-favorites'); ?></p>
                </div>
                <?php
            });
            return false;
        }
        
        return true;
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'zippicks-favorites',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Check if database tables exist and show admin notice if not
     */
    public function check_tables_exist() {
        // Only check on admin pages
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        
        // Check if we just created tables
        if (get_transient('zippicks_tables_created')) {
            delete_transient('zippicks_tables_created');
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>ZipPicks Favorites:</strong> Database tables created successfully!</p>
            </div>
            <?php
            return;
        }
        
        // Check if tables exist
        global $wpdb;
        $missing_tables = [];
        $tables = [
            'zippicks_favorites',
            'zippicks_favorites_meta',
            'zippicks_location_cache'
        ];
        
        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            if (!$exists) {
                $missing_tables[] = $full_table_name;
            }
        }
        
        // Show notice if tables are missing
        if (!empty($missing_tables)) {
            $create_url = wp_nonce_url(
                admin_url('admin.php?action=zippicks_create_tables'),
                'zippicks_create_tables'
            );
            $manual_url = plugins_url('create-tables.php', __FILE__);
            ?>
            <div class="notice notice-error">
                <p><strong>ZipPicks Favorites:</strong> Required database tables are missing!</p>
                <p>Missing tables: <code><?php echo implode(', ', $missing_tables); ?></code></p>
                <p>
                    <a href="<?php echo esc_url($create_url); ?>" class="button button-primary">Create Tables Now</a>
                    <a href="<?php echo esc_url($manual_url); ?>" class="button" target="_blank">Manual Creation Tool</a>
                </p>
            </div>
            <?php
        }
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    ZipPicks_Favorites::get_instance();
}, 5);