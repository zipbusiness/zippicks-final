<?php
/**
 * Plugin Name: ZipPicks Smart Search
 * Plugin URI: https://zippicks.com
 * Description: Intelligent search system that understands vibe-based and utility queries for local discovery
 * Version: 1.0.0
 * Author: ZipPicks Engineering
 * Text Domain: zippicks-smart-search
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('ZIPPICKS_SEARCH_VERSION', '1.0.0');
define('ZIPPICKS_SEARCH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZIPPICKS_SEARCH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZIPPICKS_SEARCH_PLUGIN_FILE', __FILE__);

// Database version for migrations
define('ZIPPICKS_SEARCH_DB_VERSION', '1.0.0');

/**
 * Main plugin class
 */
final class Smart_Search {
    
    /**
     * Instance
     * @var Smart_Search
     */
    private static $instance = null;
    
    /**
     * Get instance
     * @return Smart_Search
     */
    public static function instance() {
        if (is_null(self::$instance)) {
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
        // Core classes
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-installer.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-business-cpt.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-search-engine.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-intent-classifier.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-api-client.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-cache-manager.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-analytics.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-rest-controller.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-demand-tracker.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-rate-limiter.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-performance-optimizer.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-security-manager.php';
        require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'includes/class-error-tracker.php';
        
        // Admin classes
        if (is_admin()) {
            require_once ZIPPICKS_SEARCH_PLUGIN_DIR . 'admin/class-admin.php';
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(ZIPPICKS_SEARCH_PLUGIN_FILE, [Installer::class, 'activate']);
        register_deactivation_hook(ZIPPICKS_SEARCH_PLUGIN_FILE, [Installer::class, 'deactivate']);
        
        // Init action
        add_action('init', [$this, 'init']);
        
        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // AJAX handlers
        if (defined('DOING_AJAX') && DOING_AJAX) {
            new Ajax_Handlers();
        }
        
        // Admin
        if (is_admin()) {
            new Admin();
        }
        
        // Initialize performance optimizer
        Performance_Optimizer::instance();
        
        // Initialize security manager
        Security_Manager::instance();
        
        // Initialize error tracker
        Error_Tracker::instance();
    }
    
    /**
     * Init callback
     */
    public function init() {
        // Register Business CPT
        Business_CPT::register();
        
        // Load text domain
        load_plugin_textdomain('zippicks-smart-search', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Check for database updates
        $this->maybe_update_database();
        
        // Register shortcodes
        $this->register_shortcodes();
    }
    
    /**
     * Register shortcodes
     */
    private function register_shortcodes() {
        add_shortcode('zippicks_search', [$this, 'render_search_shortcode']);
        add_shortcode('zippicks_search_results', [$this, 'render_results_shortcode']);
    }
    
    /**
     * Render search bar shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_search_shortcode($atts) {
        $atts = shortcode_atts([
            'title' => '',
            'placeholder' => __('Search for vibes, places, or experiences...', 'zippicks-smart-search'),
            'show_location' => 'yes'
        ], $atts);
        
        ob_start();
        include ZIPPICKS_SEARCH_PLUGIN_DIR . 'templates/search-bar.php';
        return ob_get_clean();
    }
    
    /**
     * Render search results container shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_results_shortcode($atts) {
        $atts = shortcode_atts([
            'container_class' => 'zippicks-search-results-container'
        ], $atts);
        
        return sprintf(
            '<div class="%s"><div class="zippicks-search-results"></div></div>',
            esc_attr($atts['container_class'])
        );
    }
    
    /**
     * Register REST routes
     */
    public function register_rest_routes() {
        $controller = new REST_Controller();
        $controller->register_routes();
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on pages that need search
        if (!$this->should_load_search_assets()) {
            return;
        }
        
        // Apply CDN filter to URLs
        $css_url = apply_filters('zippicks_search_asset_url', ZIPPICKS_SEARCH_PLUGIN_URL . 'assets/css/search.css');
        $error_reporter_url = apply_filters('zippicks_search_asset_url', ZIPPICKS_SEARCH_PLUGIN_URL . 'assets/js/error-reporter.js');
        $security_js_url = apply_filters('zippicks_search_asset_url', ZIPPICKS_SEARCH_PLUGIN_URL . 'assets/js/security-helper.js');
        $js_url = apply_filters('zippicks_search_asset_url', ZIPPICKS_SEARCH_PLUGIN_URL . 'assets/js/search.js');
        $autocomplete_url = apply_filters('zippicks_search_asset_url', ZIPPICKS_SEARCH_PLUGIN_URL . 'assets/js/autocomplete.js');
        
        // Search styles
        wp_enqueue_style(
            'zippicks-smart-search',
            $css_url,
            [],
            ZIPPICKS_SEARCH_VERSION
        );
        
        // Error reporter (load first for early error catching)
        wp_enqueue_script(
            'zippicks-error-reporter',
            $error_reporter_url,
            [],
            ZIPPICKS_SEARCH_VERSION,
            true
        );
        
        // Security helper
        wp_enqueue_script(
            'zippicks-search-security',
            $security_js_url,
            [],
            ZIPPICKS_SEARCH_VERSION,
            true
        );
        
        // Search scripts
        wp_enqueue_script(
            'zippicks-smart-search',
            $js_url,
            ['jquery', 'zippicks-search-security'],
            ZIPPICKS_SEARCH_VERSION,
            true
        );
        
        // Autocomplete
        wp_enqueue_script(
            'zippicks-search-autocomplete',
            $autocomplete_url,
            ['zippicks-smart-search'],
            ZIPPICKS_SEARCH_VERSION,
            true
        );
        
        // Prepare localization data
        $localize_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('zippicks/v1/'),
            'nonce' => wp_create_nonce('zippicks_search_nonce'),
            'api_key' => $this->get_frontend_api_key(),
            'default_location' => $this->get_default_location(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'strings' => [
                'searching' => __('Searching...', 'zippicks-smart-search'),
                'no_results' => __('No results found', 'zippicks-smart-search'),
                'error' => __('An error occurred. Please try again.', 'zippicks-smart-search'),
                'coming_soon' => __('Coming Soon', 'zippicks-smart-search'),
                'notify_me' => __('Notify Me', 'zippicks-smart-search'),
            ]
        ];
        
        // Apply security data filter
        $localize_data = apply_filters('zippicks_search_localize_data', $localize_data);
        
        // Localize script
        wp_localize_script('zippicks-smart-search', 'zippicks_search', $localize_data);
    }
    
    /**
     * Check if search assets should be loaded
     * @return bool
     */
    private function should_load_search_assets() {
        // Load on home page
        if (is_front_page() || is_home()) {
            return true;
        }
        
        // Load on pages with search shortcode
        global $post;
        if ($post && has_shortcode($post->post_content, 'zippicks_search')) {
            return true;
        }
        
        // Load on business archive pages
        if (is_post_type_archive('business')) {
            return true;
        }
        
        // Allow filtering
        return apply_filters('zippicks_search_load_assets', false);
    }
    
    /**
     * Get frontend API key (read-only)
     * @return string
     */
    private function get_frontend_api_key() {
        // This should be a read-only API key for frontend use
        return get_option('zippicks_search_frontend_api_key', '');
    }
    
    /**
     * Get default location
     * @return array
     */
    private function get_default_location() {
        return [
            'lat' => 34.0522,
            'lng' => -118.2437,
            'city' => 'Los Angeles',
            'state' => 'CA'
        ];
    }
    
    /**
     * Check and run database updates if needed
     */
    private function maybe_update_database() {
        $current_version = get_option('zippicks_search_db_version', '0');
        
        if (version_compare($current_version, ZIPPICKS_SEARCH_DB_VERSION, '<')) {
            Installer::update_database($current_version, ZIPPICKS_SEARCH_DB_VERSION);
        }
    }
}

// Initialize plugin
Smart_Search::instance();