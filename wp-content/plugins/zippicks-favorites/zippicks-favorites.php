<?php
/**
 * Plugin Name: ZipPicks Favorites
 * Plugin URI: https://zippicks.com
 * Description: API-based favorites system with intelligent location filtering for restaurant discovery
 * Version: 2.0.0
 * Author: ZipPicks
 * Author URI: https://zippicks.com
 * License: Proprietary
 * Text Domain: zippicks-favorites
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

namespace ZipPicks\Favorites;

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('ZIPPICKS_FAVORITES_VERSION', '2.0.0');
define('ZIPPICKS_FAVORITES_PLUGIN_FILE', __FILE__);
define('ZIPPICKS_FAVORITES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZIPPICKS_FAVORITES_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'ZipPicks\\Favorites\\';
    $base_dir = ZIPPICKS_FAVORITES_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main plugin class
 */
class Plugin {
    
    private static $instance = null;
    private $api_client = null;
    private $cache = null;
    private $rest_controller = null;
    
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
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Core hooks
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Integration hooks
        add_filter('zippicks_restaurant_card_actions', [$this, 'add_favorite_button'], 10, 2);
        add_filter('zippicks_search_result_item', [$this, 'add_favorite_indicator'], 10, 2);
        add_action('zippicks_author_profile_content', [$this, 'render_favorites_section'], 20);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        }
    }
    
    /**
     * Plugin initialization
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain(
            'zippicks-favorites',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
        
        // Initialize components
        $this->api_client = new API\Client();
        $this->cache = new Cache();
        $this->rest_controller = new API\RestController($this->api_client, $this->cache);
        
        // Register service with foundation if available
        if (function_exists('zippicks')) {
            zippicks()->bind('favorites', new FavoritesService($this->api_client, $this->cache));
        }
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on pages that need favorites
        if (!is_user_logged_in()) {
            return;
        }
        
        // No separate CSS file - styles are in child theme
        
        // Scripts
        wp_enqueue_script(
            'zippicks-favorites',
            ZIPPICKS_FAVORITES_PLUGIN_URL . 'assets/js/favorites.js',
            ['jquery', 'wp-api-fetch'],
            ZIPPICKS_FAVORITES_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('zippicks-favorites', 'zippicksFavorites', [
            'apiUrl' => home_url('/wp-json/zippicks/v1/favorites'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'i18n' => [
                'save' => __('Save to favorites', 'zippicks-favorites'),
                'saved' => __('Saved', 'zippicks-favorites'),
                'remove' => __('Remove from favorites', 'zippicks-favorites'),
                'error' => __('Error updating favorite', 'zippicks-favorites'),
                'loading' => __('Loading...', 'zippicks-favorites')
            ]
        ]);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        $this->rest_controller->register_routes();
    }
    
    /**
     * Add favorite button to restaurant cards
     */
    public function add_favorite_button($actions, $business_id) {
        if (!is_user_logged_in()) {
            return $actions;
        }
        
        $is_favorited = $this->is_favorited($business_id);
        
        $actions['favorite'] = sprintf(
            '<button class="zp-favorite-btn %s" data-business-id="%s" aria-label="%s">
                <span class="zp-favorite-icon">%s</span>
                <span class="zp-favorite-text">%s</span>
            </button>',
            $is_favorited ? 'is-favorited' : '',
            esc_attr($business_id),
            $is_favorited ? __('Remove from favorites', 'zippicks-favorites') : __('Save to favorites', 'zippicks-favorites'),
            $is_favorited ? '♥' : '♡',
            $is_favorited ? __('Saved', 'zippicks-favorites') : __('Save', 'zippicks-favorites')
        );
        
        return $actions;
    }
    
    /**
     * Add favorite indicator to search results
     */
    public function add_favorite_indicator($item_html, $business_id) {
        if (!is_user_logged_in() || !$this->is_favorited($business_id)) {
            return $item_html;
        }
        
        // Add favorited class and indicator
        $indicator = '<span class="zp-favorited-indicator" title="' . __('In your favorites', 'zippicks-favorites') . '">★</span>';
        
        // Insert indicator after title
        $item_html = str_replace('</h3>', $indicator . '</h3>', $item_html);
        
        return $item_html;
    }
    
    /**
     * Render favorites section on author profile
     */
    public function render_favorites_section($user_id) {
        // Check if viewing own profile or has permission
        if ($user_id !== get_current_user_id() && !current_user_can('edit_users')) {
            return;
        }
        
        // Load template
        include ZIPPICKS_FAVORITES_PLUGIN_DIR . 'templates/author-favorites.php';
    }
    
    /**
     * Check if a business is favorited by current user
     */
    private function is_favorited($business_id) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        $cache_key = "favorited_{$user_id}_{$business_id}";
        
        // Check cache first
        $cached = $this->cache->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Check via API
        try {
            $response = $this->api_client->get("/users/{$user_id}/favorites/check", [
                'business_id' => $business_id
            ]);
            
            $is_favorited = !empty($response['is_favorited']);
            $this->cache->set($cache_key, $is_favorited, 300); // Cache for 5 minutes
            
            return $is_favorited;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'zippicks',
            __('Favorites Settings', 'zippicks-favorites'),
            __('Favorites', 'zippicks-favorites'),
            'manage_options',
            'zippicks-favorites',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        include ZIPPICKS_FAVORITES_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'zippicks_page_zippicks-favorites') {
            return;
        }
        
        // Admin styles handled by theme/core
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        add_option('zippicks_favorites_api_endpoint', 'https://api.zippicks.com/v1');
        add_option('zippicks_favorites_cache_ttl', 300);
        add_option('zippicks_favorites_location_radius_default', 5);
        
        // Clear rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear any scheduled tasks
        wp_clear_scheduled_hook('zippicks_favorites_sync');
        
        // Clear cache
        if ($this->cache) {
            $this->cache->flush();
        }
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    Plugin::get_instance();
}, 5);