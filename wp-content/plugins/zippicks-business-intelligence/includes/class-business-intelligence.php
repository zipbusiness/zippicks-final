<?php
/**
 * Core plugin class
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Includes;

use ZipPicks\BusinessIntelligence\Services\BusinessService;
use ZipPicks\BusinessIntelligence\Services\CacheService;
use ZipPicks\BusinessIntelligence\Services\ConfigService;
use ZipPicks\BusinessIntelligence\Services\LoggerService;
use ZipPicks\BusinessIntelligence\Clients\ZipBusinessAPIClient;
use ZipPicks\BusinessIntelligence\Admin\AdminDashboard;
use ZipPicks\BusinessIntelligence\Admin\SettingsPage;

class BusinessIntelligence {
    
    /**
     * The loader that's responsible for maintaining and registering all hooks
     *
     * @var Loader
     */
    protected $loader;
    
    /**
     * The unique identifier of this plugin
     *
     * @var string
     */
    protected $plugin_name;
    
    /**
     * The current version of the plugin
     *
     * @var string
     */
    protected $version;
    
    /**
     * Core services
     */
    protected $services = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->version = ZIPPICKS_BI_VERSION;
        $this->plugin_name = 'zippicks-business-intelligence';
        
        $this->load_dependencies();
        $this->register_services();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }
    
    /**
     * Load the required dependencies
     */
    private function load_dependencies() {
        require_once ZIPPICKS_BI_PLUGIN_DIR . 'includes/class-loader.php';
        $this->loader = new Loader();
    }
    
    /**
     * Register services with Foundation
     */
    private function register_services() {
        if (!function_exists('zippicks')) {
            return;
        }
        
        // Register configuration service
        $config = new ConfigService();
        zippicks()->bind('business_intelligence.config', $config);
        $this->services['config'] = $config;
        
        // Register logger service
        $logger = new LoggerService($config);
        zippicks()->bind('business_intelligence.logger', $logger);
        $this->services['logger'] = $logger;
        
        // Register cache service
        $cache = new CacheService($config);
        zippicks()->bind('business_intelligence.cache', $cache);
        $this->services['cache'] = $cache;
        
        // Register API client with logger
        $api_client = new ZipBusinessAPIClient($config, $cache, $logger);
        zippicks()->bind('business_intelligence.api_client', $api_client);
        $this->services['api_client'] = $api_client;
        
        // Register main business service
        $business_service = new BusinessService($api_client, $cache, $config);
        zippicks()->bind('business_intelligence.service', $business_service);
        $this->services['business'] = $business_service;
        
        // Log service registration
        if (zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('Business Intelligence services registered', [
                'services' => array_keys($this->services)
            ]);
        }
    }
    
    /**
     * Register admin hooks
     */
    private function define_admin_hooks() {
        if (!is_admin()) {
            return;
        }
        
        // Admin dashboard
        $admin = new AdminDashboard($this->services);
        $this->loader->add_action('admin_menu', $admin, 'add_admin_menu');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');
        
        // Settings page
        $settings = new SettingsPage($this->services['config']);
        $this->loader->add_action('admin_menu', $settings, 'add_settings_page');
        $this->loader->add_action('admin_init', $settings, 'register_settings');
        
        // AJAX handlers
        $this->loader->add_action('wp_ajax_zippicks_bi_get_city_businesses', $admin, 'ajax_get_city_businesses');
        $this->loader->add_action('wp_ajax_zippicks_bi_trigger_collection', $admin, 'ajax_trigger_collection');
        $this->loader->add_action('wp_ajax_zippicks_bi_clear_cache', $admin, 'ajax_clear_cache');
        $this->loader->add_action('wp_ajax_zippicks_bi_sync_city', $admin, 'ajax_sync_city');
        
        // Dashboard widget
        $this->loader->add_action('wp_dashboard_setup', $admin, 'add_dashboard_widget');
    }
    
    /**
     * Register public hooks
     */
    private function define_public_hooks() {
        // Scheduled tasks
        $this->loader->add_action('zippicks_bi_cleanup_cache', $this->services['cache'], 'cleanup_expired');
        $this->loader->add_action('zippicks_bi_cleanup_logs', $this, 'cleanup_old_logs');
    }
    
    /**
     * Register REST API endpoints
     */
    private function define_api_hooks() {
        $this->loader->add_action('rest_api_init', $this, 'register_rest_routes');
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        $namespace = 'zippicks/v1';
        
        // Get business by ZPID
        register_rest_route($namespace, '/businesses/by-zpid/(?P<zpid>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_business'],
            'permission_callback' => '__return_true',
            'args' => [
                'zpid' => [
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ]
            ]
        ]);
        
        // Get businesses by location (ZIP)
        register_rest_route($namespace, '/businesses/by-location', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_businesses_by_location'],
            'permission_callback' => '__return_true',
            'args' => [
                'zip' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{5}$/', $param);
                    }
                ],
                'limit' => [
                    'default' => 50,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 100;
                    }
                ]
            ]
        ]);
        
        // Search businesses
        register_rest_route($namespace, '/businesses/search', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_search_businesses'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && strlen($param) >= 2;
                    }
                ],
                'zip' => [
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{5}$/', $param);
                    }
                ],
                'city' => [
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
                'state' => [
                    'default' => 'CA',
                    'validate_callback' => function($param) {
                        return is_string($param) && strlen($param) === 2;
                    }
                ],
                'limit' => [
                    'default' => 50,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 100;
                    }
                ]
            ]
        ]);
        
        // Health check
        register_rest_route($namespace, '/businesses/health', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_health_check'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * REST API callbacks
     */
    public function rest_get_businesses_by_location($request) {
        $zip = $request->get_param('zip');
        $limit = $request->get_param('limit') ?? 50;
        
        try {
            $businesses = $this->services['business']->get_businesses_by_zip($zip, $limit);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $businesses,
                'count' => count($businesses),
                'zip' => $zip
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function rest_search_businesses($request) {
        $query = $request->get_param('q');
        
        $filters = [];
        if ($zip = $request->get_param('zip')) {
            $filters['zip'] = $zip;
        }
        if ($city = $request->get_param('city')) {
            $filters['city'] = $city;
        }
        if ($state = $request->get_param('state')) {
            $filters['state'] = $state;
        }
        
        $filters['limit'] = $request->get_param('limit') ?? 50;
        
        try {
            $businesses = $this->services['business']->search_businesses($query, $filters);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $businesses,
                'count' => count($businesses),
                'query' => $query,
                'filters' => $filters
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function rest_get_business($request) {
        $zpid = $request->get_param('zpid');
        
        try {
            $business = $this->services['business']->get_business_by_zpid($zpid);
            
            if (!$business) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Business not found'
                ], 404);
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $business
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function rest_trigger_collection($request) {
        $city = $request->get_param('city');
        
        try {
            $result = $this->services['business']->trigger_city_collection($city);
            
            return new \WP_REST_Response([
                'success' => $result,
                'message' => $result ? 'Collection triggered successfully' : 'Failed to trigger collection'
            ], $result ? 200 : 500);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function rest_health_check() {
        $health = [
            'status' => 'healthy',
            'services' => [],
            'timestamp' => current_time('mysql')
        ];
        
        // Check API connectivity
        try {
            $api_health = $this->services['api_client']->health_check();
            $health['services']['api'] = $api_health;
        } catch (\Exception $e) {
            $health['services']['api'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
            $health['status'] = 'degraded';
        }
        
        // Check cache
        $cache_health = $this->services['cache']->health_check();
        $health['services']['cache'] = $cache_health;
        
        // Check database
        global $wpdb;
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->prefix . 'zippicks_business_cache'
            )
        );
        
        $health['services']['database'] = [
            'status' => $table_exists ? 'healthy' : 'unhealthy',
            'tables_exist' => (bool) $table_exists
        ];
        
        return new \WP_REST_Response($health, 200);
    }
    
    /**
     * Cleanup old API logs
     */
    public function cleanup_old_logs() {
        // Use logger service to cleanup
        if (isset($this->services['logger'])) {
            $deleted = $this->services['logger']->cleanup_old_logs(30);
            
            // Log the cleanup action
            $this->services['logger']->log(
                LoggerService::LEVEL_INFO,
                'Cleaned up old API logs',
                ['deleted_count' => $deleted]
            );
        }
    }
    
    /**
     * Run the plugin
     */
    public function run() {
        $this->loader->run();
    }
    
    /**
     * Get plugin name
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return $this->version;
    }
}