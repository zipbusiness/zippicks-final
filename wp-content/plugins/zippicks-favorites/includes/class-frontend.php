<?php
namespace ZipPicks\Favorites;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_shortcode('zippicks_favorites', [$this, 'render_favorites_dashboard']);
        add_filter('the_content', [$this, 'add_favorite_button'], 20);
        add_action('wp_footer', [$this, 'add_favorites_root']);
    }
    
    public function enqueue_scripts() {
        if (!is_user_logged_in()) {
            return;
        }
        
        // React and dependencies
        wp_enqueue_script(
            'zippicks-favorites-react',
            ZIPPICKS_FAVORITES_PLUGIN_URL . 'assets/js/favorites-app.js',
            ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'],
            ZIPPICKS_FAVORITES_VERSION,
            true
        );
        
        // Map integration
        wp_enqueue_script(
            'zippicks-favorites-map',
            ZIPPICKS_FAVORITES_PLUGIN_URL . 'assets/js/map-integration.js',
            [],
            ZIPPICKS_FAVORITES_VERSION,
            true
        );
        
        // Styles
        wp_enqueue_style(
            'zippicks-favorites-styles',
            ZIPPICKS_FAVORITES_PLUGIN_URL . 'assets/css/favorites.css',
            [],
            ZIPPICKS_FAVORITES_VERSION
        );
        
        // Localize script
        wp_localize_script('zippicks-favorites-react', 'zipPicksFavorites', [
            'apiUrl' => home_url('/wp-json/zippicks/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'isLoggedIn' => is_user_logged_in(),
            'settings' => [
                'enableMap' => get_option('zippicks_favorites_enable_map', true),
                'mapProvider' => get_option('zippicks_favorites_map_provider', 'mapbox'),
                'defaultRadius' => get_option('zippicks_favorites_default_radius', 10),
                'perPage' => get_option('zippicks_favorites_per_page', 20),
                'enableExport' => get_option('zippicks_favorites_enable_export', true)
            ],
            'i18n' => [
                'save' => __('Save', 'zippicks-favorites'),
                'saved' => __('Saved', 'zippicks-favorites'),
                'remove' => __('Remove', 'zippicks-favorites'),
                'confirmRemove' => __('Are you sure you want to remove this from your favorites?', 'zippicks-favorites'),
                'nearMe' => __('Near Me', 'zippicks-favorites'),
                'allCities' => __('All Cities', 'zippicks-favorites'),
                'searchPlaceholder' => __('Search favorites...', 'zippicks-favorites'),
                'noFavorites' => __('No favorites found', 'zippicks-favorites'),
                'loading' => __('Loading...', 'zippicks-favorites'),
                'error' => __('An error occurred', 'zippicks-favorites')
            ]
        ]);
        
        // Add map scripts if enabled
        if (get_option('zippicks_favorites_enable_map', true)) {
            $map_provider = get_option('zippicks_favorites_map_provider', 'mapbox');
            
            if ($map_provider === 'mapbox' && get_option('zippicks_mapbox_api_key')) {
                wp_enqueue_script(
                    'mapbox-gl',
                    'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js',
                    [],
                    '2.15.0'
                );
                wp_enqueue_style(
                    'mapbox-gl',
                    'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css',
                    [],
                    '2.15.0'
                );
            }
        }
    }
    
    public function render_favorites_dashboard($atts) {
        if (!is_user_logged_in()) {
            return '<div class="zippicks-favorites-login-prompt">' . 
                   __('Please log in to view your favorites.', 'zippicks-favorites') . 
                   '</div>';
        }
        
        $atts = shortcode_atts([
            'view' => 'grid', // grid or list
            'show_map' => 'true',
            'show_filters' => 'true',
            'initial_city' => ''
        ], $atts);
        
        return '<div id="zippicks-favorites-dashboard" data-settings="' . 
               esc_attr(json_encode($atts)) . '"></div>';
    }
    
    public function add_favorite_button($content) {
        if (!is_singular('zippicks_business') || !is_user_logged_in()) {
            return $content;
        }
        
        $business_id = get_the_ID();
        
        // Check if already favorited
        if (function_exists('zippicks') && zippicks()->has('favorites')) {
            $favorites_manager = zippicks()->get('favorites');
            $is_favorited = $favorites_manager->is_favorited(get_current_user_id(), $business_id);
        } else {
            $favorites_manager = new Favorites_Manager();
            $is_favorited = $favorites_manager->is_favorited(get_current_user_id(), $business_id);
        }
        
        $button_html = '<div class="zippicks-favorite-button-wrapper" data-business-id="' . 
                      esc_attr($business_id) . '" data-favorited="' . 
                      ($is_favorited ? 'true' : 'false') . '"></div>';
        
        // Add button after title or at the beginning of content
        return $button_html . $content;
    }
    
    public function add_favorites_root() {
        if (!is_user_logged_in()) {
            return;
        }
        
        echo '<div id="zippicks-favorites-root"></div>';
    }
}