<?php
/**
 * Plugin Name: ZipPicks Social
 * Plugin URI: https://zippicks.com/plugins/social
 * Description: Enterprise-grade social following system for ZipPicks platform. Enables users to follow critics, businesses, and other users with activity feeds and notifications.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: ZipPicks Engineering
 * Author URI: https://zippicks.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: zippicks-social
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ZIPPICKS_SOCIAL_VERSION', '1.0.0');
define('ZIPPICKS_SOCIAL_DB_VERSION', '1.0.0');
define('ZIPPICKS_SOCIAL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZIPPICKS_SOCIAL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZIPPICKS_SOCIAL_PLUGIN_FILE', __FILE__);
define('ZIPPICKS_SOCIAL_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function() {
    require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-activator.php';
    ZipPicks_Social_Activator::activate();
});

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-deactivator.php';
    ZipPicks_Social_Deactivator::deactivate();
});

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', function() {
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('ZipPicks Social requires PHP 8.0 or higher. Please update your PHP version.', 'zippicks-social'); ?></p>
            </div>
            <?php
        });
        return;
    }

    // Check WordPress version
    if (version_compare(get_bloginfo('version'), '6.0', '<')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('ZipPicks Social requires WordPress 6.0 or higher. Please update WordPress.', 'zippicks-social'); ?></p>
            </div>
            <?php
        });
        return;
    }

    // Load core classes
    require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-follow-manager.php';
    require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-cache-manager.php';
    require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-database.php';
    
    // Initialize the plugin
    $follow_manager = new ZipPicks_Social_Follow_Manager();
    
    // Register with Foundation if available
    if (function_exists('zippicks')) {
        zippicks()->bind('social.follow_manager', $follow_manager);
        
        // Register cache service if available
        if (zippicks()->has('cache')) {
            $cache_manager = new ZipPicks_Social_Cache_Manager(zippicks()->get('cache'));
            zippicks()->bind('social.cache', $cache_manager);
        }
    }
    
    // Load API handlers
    if (is_admin()) {
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'admin/class-admin.php';
        new ZipPicks_Social_Admin();
    } else {
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'public/class-public.php';
        new ZipPicks_Social_Public();
    }
    
    // Load REST API
    add_action('rest_api_init', function() {
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'api/class-rest-controller.php';
        $controller = new ZipPicks_Social_REST_Controller();
        $controller->register_routes();
    });
    
    // Load AJAX handlers
    require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'api/class-ajax-handler.php';
    new ZipPicks_Social_AJAX_Handler();
});

/**
 * Add action links to plugins page
 */
add_filter('plugin_action_links_' . ZIPPICKS_SOCIAL_PLUGIN_BASENAME, function($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=zippicks-social'),
        __('Settings', 'zippicks-social')
    );
    array_unshift($links, $settings_link);
    return $links;
});

/**
 * Load text domain for internationalization
 */
add_action('init', function() {
    load_plugin_textdomain(
        'zippicks-social',
        false,
        dirname(ZIPPICKS_SOCIAL_PLUGIN_BASENAME) . '/languages'
    );
});

/**
 * Public API functions for theme integration
 */
if (!function_exists('zippicks_social_follow_button')) {
    /**
     * Display a follow button
     *
     * @param int $entity_id Entity ID to follow
     * @param string $entity_type Type of entity (user, critic, business, list)
     * @param array $args Additional arguments
     * @return string HTML for follow button
     */
    function zippicks_social_follow_button($entity_id, $entity_type = 'user', $args = []) {
        if (!class_exists('ZipPicks_Social_Follow_Manager')) {
            return '';
        }
        
        $defaults = [
            'class' => 'zps-follow-button',
            'show_count' => true,
            'size' => 'medium', // small, medium, large
            'style' => 'default', // default, minimal, rounded
        ];
        
        $args = wp_parse_args($args, $defaults);
        $follow_manager = new ZipPicks_Social_Follow_Manager();
        
        return $follow_manager->render_follow_button($entity_id, $entity_type, $args);
    }
}

if (!function_exists('zippicks_social_followers_count')) {
    /**
     * Get followers count for an entity
     *
     * @param int $entity_id Entity ID
     * @param string $entity_type Type of entity
     * @return int Number of followers
     */
    function zippicks_social_followers_count($entity_id, $entity_type = 'user') {
        if (!class_exists('ZipPicks_Social_Follow_Manager')) {
            return 0;
        }
        
        $follow_manager = new ZipPicks_Social_Follow_Manager();
        return $follow_manager->get_followers_count($entity_id, $entity_type);
    }
}

if (!function_exists('zippicks_social_is_following')) {
    /**
     * Check if a user is following an entity
     *
     * @param int $follower_id User ID of follower
     * @param int $followed_id Entity ID being followed
     * @param string $followed_type Type of entity
     * @return bool True if following
     */
    function zippicks_social_is_following($follower_id, $followed_id, $followed_type = 'user') {
        if (!class_exists('ZipPicks_Social_Follow_Manager')) {
            return false;
        }
        
        $follow_manager = new ZipPicks_Social_Follow_Manager();
        return $follow_manager->is_following($follower_id, $followed_id, $followed_type);
    }
}