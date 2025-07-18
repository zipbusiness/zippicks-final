<?php
/**
 * ZipPicks Core Foundation Class
 * 
 * @package ZipPicks\Foundation
 */

namespace ZipPicks\Foundation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main foundation class that coordinates all shared services
 */
class Core {
    
    /**
     * Singleton instance
     * 
     * @var Core
     */
    private static $instance = null;
    
    /**
     * Container for shared services
     * 
     * @var array
     */
    private $services = [];
    
    /**
     * Plugin dependencies registry
     * 
     * @var array
     */
    private $dependencies = [];
    
    /**
     * Get singleton instance
     * 
     * @return Core
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
        $this->define_constants();
        $this->init_hooks();
        $this->load_core_services();
    }
    
    /**
     * Define global constants
     */
    private function define_constants() {
        // Platform constants
        define('ZIPPICKS_PLATFORM_VERSION', '1.0.0');
        define('ZIPPICKS_MIN_PHP_VERSION', '7.4');
        define('ZIPPICKS_MIN_WP_VERSION', '5.8');
        
        // Feature flags
        define('ZIPPICKS_ENABLE_CACHE', true);
        define('ZIPPICKS_ENABLE_DEBUG', defined('WP_DEBUG') && WP_DEBUG);
        
        // API endpoints
        define('ZIPPICKS_API_NAMESPACE', 'zippicks/v1');
        define('ZIPPICKS_MASTER_CRITIC_ENDPOINT', 'https://api.zippicks.com/critic/v1');
        
        // Scoring system constants
        define('ZIPPICKS_MAX_SCORE', 10);
        define('ZIPPICKS_MIN_SCORE', 0);
        define('ZIPPICKS_SCORE_PRECISION', 1);
        
        // Database table prefixes
        global $wpdb;
        define('ZIPPICKS_TABLE_PREFIX', $wpdb->prefix . 'zippicks_');
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Core activation/deactivation
        register_activation_hook(ZIPPICKS_FOUNDATION_BASENAME, [$this, 'activate']);
        register_deactivation_hook(ZIPPICKS_FOUNDATION_BASENAME, [$this, 'deactivate']);
        
        // Core WordPress hooks
        add_action('init', [$this, 'init'], 0);
        add_action('rest_api_init', [$this, 'init_rest_api']);
        add_action('admin_init', [$this, 'admin_init']);
        
        // Plugin dependency checks
        add_action('plugins_loaded', [$this, 'check_dependencies'], 5);
    }
    
    /**
     * Load core services
     */
    private function load_core_services() {
        // Initialize services
        $this->services['taste_graph'] = new TasteGraph();
        $this->services['vibe_taxonomy'] = new VibeTaxonomy();
        $this->services['scoring_engine'] = new ScoringEngine();
        $this->services['zip_intelligence'] = new ZipIntelligence();
        $this->services['schema_manager'] = new Database\SchemaManager();
        $this->services['cache_manager'] = new CacheManager();
        $this->services['logger'] = new Logger();
        
        // Initialize admin if in admin area
        if (is_admin()) {
            $this->services['admin'] = new Admin\Dashboard();
        }
    }
    
    /**
     * Initialize foundation
     */
    public function init() {
        // Register taxonomies
        $this->services['vibe_taxonomy']->register();
        
        // Initialize database schema
        $this->services['schema_manager']->maybe_create_tables();
        
        // Load text domain
        load_plugin_textdomain('zippicks-foundation', false, dirname(ZIPPICKS_FOUNDATION_BASENAME) . '/languages');
        
        // Fire loaded action
        do_action('zippicks_foundation_loaded', $this);
    }
    
    /**
     * Initialize REST API
     */
    public function init_rest_api() {
        $api = new API\RestController($this);
        $api->register_routes();
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Check system requirements
        $this->check_system_requirements();
    }
    
    /**
     * Check system requirements
     */
    private function check_system_requirements() {
        // PHP version check
        if (version_compare(PHP_VERSION, ZIPPICKS_MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('ZipPicks requires PHP %s or higher. You are running PHP %s.', 'zippicks-foundation'),
                    ZIPPICKS_MIN_PHP_VERSION,
                    PHP_VERSION
                );
                echo '</p></div>';
            });
        }
        
        // WordPress version check
        if (version_compare(get_bloginfo('version'), ZIPPICKS_MIN_WP_VERSION, '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('ZipPicks requires WordPress %s or higher. Please update WordPress.', 'zippicks-foundation'),
                    ZIPPICKS_MIN_WP_VERSION
                );
                echo '</p></div>';
            });
        }
    }
    
    /**
     * Register a plugin dependency
     * 
     * @param string $plugin_id Plugin identifier
     * @param array $config Dependency configuration
     */
    public function register_dependency($plugin_id, $config) {
        $this->dependencies[$plugin_id] = wp_parse_args($config, [
            'name' => $plugin_id,
            'version' => '1.0.0',
            'required' => false,
            'callback' => null
        ]);
    }
    
    /**
     * Check plugin dependencies
     */
    public function check_dependencies() {
        foreach ($this->dependencies as $plugin_id => $config) {
            $is_active = $this->is_plugin_active($plugin_id);
            
            if ($config['required'] && !$is_active) {
                add_action('admin_notices', function() use ($config) {
                    echo '<div class="notice notice-error"><p>';
                    printf(
                        __('%s is required for ZipPicks to function properly.', 'zippicks-foundation'),
                        esc_html($config['name'])
                    );
                    echo '</p></div>';
                });
            }
            
            if ($is_active && is_callable($config['callback'])) {
                call_user_func($config['callback']);
            }
        }
    }
    
    /**
     * Check if a plugin is active
     * 
     * @param string $plugin_id Plugin identifier
     * @return bool
     */
    private function is_plugin_active($plugin_id) {
        $active_plugins = get_option('active_plugins', []);
        
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, $plugin_id) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get a service instance
     * 
     * @param string $service Service name
     * @return mixed
     */
    public function get_service($service) {
        return isset($this->services[$service]) ? $this->services[$service] : null;
    }
    
    /**
     * Activation hook
     */
    public function activate() {
        // Create database tables
        $this->services['schema_manager']->create_tables();
        
        // Set activation flag
        update_option('zippicks_foundation_activated', time());
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Deactivation hook
     */
    public function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook('zippicks_daily_maintenance');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}