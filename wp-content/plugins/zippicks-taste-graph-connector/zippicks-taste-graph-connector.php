<?php
/**
 * Plugin Name: ZipPicks Taste Graph Connector
 * Plugin URI: https://zippicks.com/
 * Description: Connects WordPress to ZipBusiness Taste Graph for personalized restaurant recommendations
 * Version: 1.0.0
 * Author: ZipPicks
 * Author URI: https://zippicks.com/
 * License: GPL v2 or later
 * Text Domain: zippicks-taste-graph-connector
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TGC_VERSION', '1.0.0');
define('TGC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TGC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TGC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load configuration from wp-config.php or default values
// For testing, you can define TGC_API_URL_OVERRIDE in wp-config.php:
// define('TGC_API_URL_OVERRIDE', 'https://zipbusiness-api-test.onrender.com');
define('TGC_API_URL', defined('TGC_API_URL_OVERRIDE') ? TGC_API_URL_OVERRIDE : 'https://zipbusiness-api.onrender.com');

// JWT Secret with validation to ensure it's never empty
$jwt_secret = '';
if (defined('TGC_JWT_SECRET_OVERRIDE') && TGC_JWT_SECRET_OVERRIDE) {
    $jwt_secret = TGC_JWT_SECRET_OVERRIDE;
} else {
    $jwt_secret = get_option('tgc_jwt_secret', '');
    
    // Generate and store a new secret if none exists
    if (empty($jwt_secret)) {
        $jwt_secret = wp_generate_password(64, true, true);
        update_option('tgc_jwt_secret', $jwt_secret);
    }
}
define('TGC_JWT_SECRET', $jwt_secret);

// Redis configuration (optional)
define('TGC_REDIS_ENABLED', defined('TGC_REDIS_HOST') && class_exists('Redis'));
if (TGC_REDIS_ENABLED) {
    define('TGC_REDIS_HOST', defined('TGC_REDIS_HOST') ? TGC_REDIS_HOST : '127.0.0.1');
    define('TGC_REDIS_PORT', defined('TGC_REDIS_PORT') ? TGC_REDIS_PORT : 6379);
}

// API retry configuration
define('TGC_API_TIMEOUT', 30); // seconds
define('TGC_API_MAX_RETRIES', 3);
define('TGC_API_RETRY_DELAY', 1); // seconds

/**
 * Main plugin class
 */
class TasteGraphConnector {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get single instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Autoloader for Composer dependencies
        if (file_exists(TGC_PLUGIN_DIR . 'vendor/autoload.php')) {
            require_once TGC_PLUGIN_DIR . 'vendor/autoload.php';
        }
        
        // Core classes
        require_once TGC_PLUGIN_DIR . 'includes/class-jwt-handler.php';
        require_once TGC_PLUGIN_DIR . 'includes/class-session-tracker.php';
        require_once TGC_PLUGIN_DIR . 'includes/class-api-client.php';
        require_once TGC_PLUGIN_DIR . 'includes/class-queue-manager.php';
        require_once TGC_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        
        // Admin classes
        if (is_admin()) {
            require_once TGC_PLUGIN_DIR . 'admin/settings.php';
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Init hook
        add_action('init', array($this, 'init'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // User login hook for session linking
        add_action('wp_login', array($this, 'link_session_on_login'), 10, 2);
        
        // AJAX handlers
        if (class_exists('TGC_Ajax_Handlers')) {
            $ajax_handlers = new TGC_Ajax_Handlers();
            $ajax_handlers->register_handlers();
        }
        
        // Admin menu
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }
        
        // Cron jobs for queue processing
        add_action('tgc_process_queue', array($this, 'process_queue'));
        
        // Add custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Schedule cron jobs
        if (!wp_next_scheduled('tgc_process_queue')) {
            wp_schedule_event(time(), 'tgc_every_minute', 'tgc_process_queue');
        }
        
        // Set default options
        add_option('tgc_api_url', TGC_API_URL);
        add_option('tgc_tracking_enabled', 'yes');
        add_option('tgc_debug_mode', 'no');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('tgc_process_queue');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load plugin textdomain
        load_plugin_textdomain('zippicks-taste-graph-connector', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Check if tracking is enabled
        if (get_option('tgc_tracking_enabled', 'yes') !== 'yes') {
            return;
        }
        
        // Initialize session tracker for frontend
        if (!is_admin()) {
            TGC_Session_Tracker::init();
        }
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_assets() {
        // Only load if tracking is enabled
        if (get_option('tgc_tracking_enabled', 'yes') !== 'yes') {
            return;
        }
        
        // Enqueue tracker script
        wp_enqueue_script(
            'tgc-tracker',
            TGC_PLUGIN_URL . 'assets/js/tracker.js',
            array('jquery'),
            TGC_VERSION,
            true
        );
        
        // Localize script with necessary data
        wp_localize_script('tgc-tracker', 'tgc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'api_url' => TGC_API_URL,
            'nonce' => wp_create_nonce('tgc_ajax_nonce'),
            'user_id' => get_current_user_id(),
            'is_logged_in' => is_user_logged_in(),
            'debug_mode' => get_option('tgc_debug_mode', 'no') === 'yes'
        ));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'zippicks-taste-graph-connector') === false) {
            return;
        }
        
        wp_enqueue_style(
            'tgc-admin',
            TGC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TGC_VERSION
        );
    }
    
    /**
     * Link anonymous session to user on login
     */
    public function link_session_on_login($user_login, $user) {
        // Get session ID from cookie
        if (isset($_COOKIE['tgc_session_id'])) {
            $session_id = sanitize_text_field($_COOKIE['tgc_session_id']);
            $wp_user_id = $user->ID;
            
            // Send to API
            $api_client = new TGC_API_Client();
            $result = $api_client->link_session($session_id, $wp_user_id);
            
            if ($result && get_option('tgc_debug_mode', 'no') === 'yes') {
                error_log('TGC: Session linked successfully - ' . $session_id . ' to user ' . $wp_user_id);
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Taste Graph', 'zippicks-taste-graph-connector'),
            __('Taste Graph', 'zippicks-taste-graph-connector'),
            'manage_options',
            'zippicks-taste-graph-connector',
            array($this, 'admin_page'),
            'dashicons-chart-pie',
            30
        );
        
        if (class_exists('TGC_Admin_Settings')) {
            add_submenu_page(
                'zippicks-taste-graph-connector',
                __('Settings', 'zippicks-taste-graph-connector'),
                __('Settings', 'zippicks-taste-graph-connector'),
                'manage_options',
                'taste-graph-connector-settings',
                array('TGC_Admin_Settings', 'render_page')
            );
        }
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Taste Graph Connector', 'zippicks-taste-graph-connector') . '</h1>';
        echo '<p>' . __('Welcome to the Taste Graph Connector plugin.', 'zippicks-taste-graph-connector') . '</p>';
        
        // Display integration status
        $this->display_integration_status();
        
        echo '</div>';
    }
    
    /**
     * Display integration status
     */
    private function display_integration_status() {
        echo '<div class="tgc-status-widget">';
        echo '<h2>' . __('Integration Status', 'zippicks-taste-graph-connector') . '</h2>';
        
        // API status
        if (class_exists('TGC_API_Client')) {
            $api_client = new TGC_API_Client();
            $health = $api_client->check_health();
            
            if ($health) {
                echo '<p class="status-ok">✅ ' . __('API Connection: OK', 'zippicks-taste-graph-connector') . '</p>';
            } else {
                echo '<p class="status-error">❌ ' . __('API Connection: Failed', 'zippicks-taste-graph-connector') . '</p>';
            }
        } else {
            echo '<p class="status-error">⚠️ ' . __('API Client not available', 'zippicks-taste-graph-connector') . '</p>';
        }
        
        // Queue status
        if (class_exists('TGC_Queue_Manager')) {
            $queue_manager = new TGC_Queue_Manager();
            $queue_count = $queue_manager->get_queue_count();
            echo '<p>' . sprintf(__('Queue Items: %d', 'zippicks-taste-graph-connector'), $queue_count) . '</p>';
        } else {
            echo '<p>' . __('Queue Manager not available', 'zippicks-taste-graph-connector') . '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Process queue (cron job)
     */
    public function process_queue() {
        $queue_manager = new TGC_Queue_Manager();
        $queue_manager->process_queue();
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['tgc_every_minute'] = array(
            'interval' => 60,
            'display' => __('Every Minute', 'zippicks-taste-graph-connector')
        );
        return $schedules;
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // API call queue table
        $table_name = $wpdb->prefix . 'tgc_api_queue';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            endpoint varchar(255) NOT NULL,
            method varchar(10) NOT NULL DEFAULT 'POST',
            payload longtext,
            headers longtext,
            status varchar(20) NOT NULL DEFAULT 'pending',
            retry_count int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            next_retry_at datetime DEFAULT CURRENT_TIMESTAMP,
            error_message text,
            PRIMARY KEY (id),
            KEY status_retry (status, next_retry_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Register activation/deactivation hooks outside the class
register_activation_hook(__FILE__, 'tgc_activate_plugin');
register_deactivation_hook(__FILE__, 'tgc_deactivate_plugin');

// Initialize the plugin
add_action('plugins_loaded', array('TasteGraphConnector', 'get_instance'));

// Static activation method
function tgc_activate_plugin() {
    TasteGraphConnector::get_instance()->activate();
}

// Static deactivation method  
function tgc_deactivate_plugin() {
    TasteGraphConnector::get_instance()->deactivate();
}