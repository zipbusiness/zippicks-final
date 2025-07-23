<?php
/**
 * Plugin Name: ZipPicks Geo Service
 * Plugin URI: https://zippicks.com/
 * Description: Centralized location detection, storage, and distance calculation service for ZipPicks platform
 * Version: 1.0.0
 * Author: ZipPicks Engineering
 * Author URI: https://zippicks.com/
 * License: GPL v2 or later
 * Text Domain: zippicks-geo
 * Domain Path: /languages
 * 
 * @package ZipPicks_Geo_Service
 */

namespace ZipPicks\Geo;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('ZIPPICKS_GEO_VERSION', '1.0.0');
define('ZIPPICKS_GEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZIPPICKS_GEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZIPPICKS_GEO_PLUGIN_FILE', __FILE__);
define('ZIPPICKS_GEO_DB_VERSION', '1.0.0');

// Error codes
define('ZIPPICKS_GEO_ERRORS', [
    'GEO001' => 'Invalid coordinates',
    'GEO002' => 'Location service unavailable',
    'GEO003' => 'Permission denied',
    'GEO004' => 'Rate limit exceeded',
    'GEO005' => 'Cache connection failed',
    'GEO006' => 'Checksum verification failed',
    'GEO007' => 'Directory path validation failed',
]);

/**
 * Main plugin class
 */
class ZipPicks_Geo_Service {
    
    /**
     * Instance of this class
     * @var ZipPicks_Geo_Service
     */
    private static $instance = null;
    
    /**
     * Initialization flag
     */
    private static $initialized = false;
    
    /**
     * Service instances
     */
    private $api_client;
    private $location_detector;
    private $distance_calculator;
    private $geo_cache;
    private $rest_controller;
    private $admin;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Check if plugin is initialized
     */
    public static function is_initialized() {
        return self::$initialized;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->register_with_foundation();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once ZIPPICKS_GEO_PLUGIN_DIR . 'includes/class-geo-api-client.php';
        require_once ZIPPICKS_GEO_PLUGIN_DIR . 'includes/class-location-detector.php';
        require_once ZIPPICKS_GEO_PLUGIN_DIR . 'includes/class-distance-calculator.php';
        require_once ZIPPICKS_GEO_PLUGIN_DIR . 'includes/class-geo-cache.php';
        require_once ZIPPICKS_GEO_PLUGIN_DIR . 'includes/class-rest-controller.php';
        require_once ZIPPICKS_GEO_PLUGIN_DIR . 'includes/class-ip-geolocation.php';
        require_once ZIPPICKS_GEO_PLUGIN_DIR . 'includes/class-privacy-manager.php';
        require_once ZIPPICKS_GEO_PLUGIN_DIR . 'includes/class-geohash.php';
        require_once ZIPPICKS_GEO_PLUGIN_DIR . 'includes/class-installer.php';
        
        // Admin classes
        if (is_admin()) {
            require_once ZIPPICKS_GEO_PLUGIN_DIR . 'admin/class-admin.php';
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, ['\ZipPicks\Geo\Installer', 'activate']);
        register_deactivation_hook(__FILE__, ['\ZipPicks\Geo\Installer', 'deactivate']);
        
        // Initialize services
        add_action('init', [$this, 'init_services']);
        
        // REST API initialization
        add_action('rest_api_init', [$this, 'init_rest_api']);
        
        // Frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        
        // Admin initialization
        if (is_admin()) {
            add_action('init', [$this, 'init_admin']);
        }
        
        // User profile fields
        add_action('show_user_profile', [$this, 'add_user_location_fields']);
        add_action('edit_user_profile', [$this, 'add_user_location_fields']);
        add_action('personal_options_update', [$this, 'save_user_location_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_location_fields']);
    }
    
    /**
     * Initialize service instances
     */
    public function init_services() {
        // Initialize API client first
        $this->api_client = new Geo_API_Client();
        
        // Initialize core services
        $this->location_detector = new Location_Detector();
        $this->distance_calculator = new Distance_Calculator();
        $this->geo_cache = new Geo_Cache();
        
        // Set up service dependencies
        $this->location_detector->set_cache($this->geo_cache);
        $this->location_detector->set_api_client($this->api_client);
        $this->distance_calculator->set_cache($this->geo_cache);
        $this->distance_calculator->set_api_client($this->api_client);
        
        // Set logger if Foundation is available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $this->api_client->set_logger($logger);
            $this->location_detector->set_logger($logger);
            $this->distance_calculator->set_logger($logger);
        }
        
        // Mark as initialized
        self::$initialized = true;
    }
    
    /**
     * Initialize REST API
     */
    public function init_rest_api() {
        $this->rest_controller = new REST_Controller($this->location_detector, $this->distance_calculator);
        $this->rest_controller->register_routes();
    }
    
    /**
     * Register with ZipPicks Foundation if available
     */
    private function register_with_foundation() {
        if (function_exists('zippicks')) {
            // Register geo service
            zippicks()->bind('geo', [
                'detector' => $this->location_detector,
                'calculator' => $this->distance_calculator,
                'cache' => $this->geo_cache,
            ]);
            
            // Log registration
            if (zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->info('ZipPicks Geo Service registered', [
                    'version' => ZIPPICKS_GEO_VERSION,
                ]);
            }
        }
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        if (!is_admin()) {
            wp_enqueue_script(
                'zippicks-geo',
                ZIPPICKS_GEO_PLUGIN_URL . 'assets/js/location-manager.js',
                ['jquery'],
                ZIPPICKS_GEO_VERSION,
                true
            );
            
            wp_localize_script('zippicks-geo', 'zippicks_geo', [
                'api_endpoint' => rest_url('zippicks/v1/geo/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'user_id' => get_current_user_id(),
                'session_id' => $this->get_session_id(),
            ]);
        }
    }
    
    /**
     * Initialize admin interface
     */
    public function init_admin() {
        $this->admin = new Admin();
    }
    
    /**
     * Add location fields to user profile
     */
    public function add_user_location_fields($user) {
        $privacy_manager = new Privacy_Manager();
        $privacy_manager->render_user_fields($user);
    }
    
    /**
     * Save location fields from user profile
     */
    public function save_user_location_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        $privacy_manager = new Privacy_Manager();
        $privacy_manager->save_user_fields($user_id);
    }
    
    /**
     * Get or create session ID using WordPress-friendly methods
     */
    private function get_session_id() {
        // Ensure services are initialized before use
        if (!$this->location_detector) {
            $this->init_services();
        }
        
        return $this->location_detector->get_session_id();
    }
    
    /**
     * Public API methods
     */
    
    /**
     * Get current user location
     */
    public function get_current_location($user_id = null, $session_id = null) {
        if (!$this->location_detector) {
            return new \WP_Error('geo_not_ready', 'Geo service not initialized');
        }
        
        return $this->location_detector->get_user_location($user_id, $session_id);
    }
    
    /**
     * Calculate distance between two points
     */
    public function calculate_distance($lat1, $lng1, $lat2, $lng2, $unit = 'miles') {
        if (!$this->distance_calculator) {
            return new \WP_Error('geo_not_ready', 'Geo service not initialized');
        }
        
        return $this->distance_calculator->calculate_distance($lat1, $lng1, $lat2, $lng2, $unit);
    }
    
    /**
     * Find businesses within radius
     */
    public function find_nearby($lat, $lng, $radius_miles = 5, $limit = 20) {
        if (!$this->distance_calculator) {
            return new \WP_Error('geo_not_ready', 'Geo service not initialized');
        }
        
        return $this->distance_calculator->find_within_radius($lat, $lng, $radius_miles, $limit);
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    ZipPicks_Geo_Service::get_instance();
});

// Global helper functions
if (!function_exists('zippicks_geo')) {
    function zippicks_geo() {
        // Check if plugin is initialized before returning instance
        if (!ZipPicks_Geo_Service::is_initialized()) {
            return null;
        }
        return ZipPicks_Geo_Service::get_instance();
    }
}

if (!function_exists('zippicks_get_location')) {
    function zippicks_get_location($user_id = null, $session_id = null) {
        $geo = zippicks_geo();
        if (!$geo) {
            return new \WP_Error('geo_not_ready', 'Geo service not initialized');
        }
        return $geo->get_current_location($user_id, $session_id);
    }
}

if (!function_exists('zippicks_calculate_distance')) {
    function zippicks_calculate_distance($lat1, $lng1, $lat2, $lng2, $unit = 'miles') {
        $geo = zippicks_geo();
        if (!$geo) {
            return new \WP_Error('geo_not_ready', 'Geo service not initialized');
        }
        return $geo->calculate_distance($lat1, $lng1, $lat2, $lng2, $unit);
    }
}

if (!function_exists('zippicks_find_nearby')) {
    function zippicks_find_nearby($lat, $lng, $radius_miles = 5, $limit = 20) {
        $geo = zippicks_geo();
        if (!$geo) {
            return new \WP_Error('geo_not_ready', 'Geo service not initialized');
        }
        return $geo->find_nearby($lat, $lng, $radius_miles, $limit);
    }
}