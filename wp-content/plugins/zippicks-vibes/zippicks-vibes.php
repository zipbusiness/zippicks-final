<?php
/**
 * Plugin Name: ZipPicks Vibes
 * Plugin URI: https://zippicks.com/plugins/vibes
 * Description: Enterprise-grade vibe discovery engine with advanced security, anti-scraping protection, and Foundation integration. Powers mood-based local discovery through AI-driven taste matching.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: ZipPicks Enterprise Team
 * Author URI: https://zippicks.com/
 * License: Proprietary
 * License URI: https://zippicks.com/license
 * Text Domain: zippicks-vibes
 * Domain Path: /languages
 * Network: true
 * Update URI: https://zippicks.com/plugins/vibes/updates
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 * 
 * This plugin requires the ZipPicks Foundation to be active for full functionality.
 * It will operate in limited standalone mode if Foundation is not available.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access prohibited');
}

// Define plugin version constant
if (!defined('ZIPPICKS_VIBES_VERSION')) {
    define('ZIPPICKS_VIBES_VERSION', '2.0.0');
}

// Define plugin constants
define('ZIPPICKS_VIBES_DIR', plugin_dir_path(__FILE__));
define('ZIPPICKS_VIBES_URL', plugin_dir_url(__FILE__));
define('ZIPPICKS_VIBES_FILE', __FILE__);
define('ZIPPICKS_VIBES_BASENAME', plugin_basename(__FILE__));

// Security constants
define('ZIPPICKS_VIBES_RATE_LIMIT_REQUESTS', 10); // Per minute
define('ZIPPICKS_VIBES_CACHE_TTL', 300); // 5 minutes
define('ZIPPICKS_VIBES_SESSION_REQUIRED', false);

// Include guard for required files with proper error handling
$required_files = [
    'src/ServiceProvider.php' => 'Service Provider',
    'src/Database/Installer.php' => 'Database Installer'
];

$missing_files = [];
foreach ($required_files as $file => $description) {
    $file_path = ZIPPICKS_VIBES_DIR . $file;
    if (!file_exists($file_path)) {
        $missing_files[$description] = $file_path;
    }
}

// If critical files are missing, deactivate plugin and show error
if (!empty($missing_files)) {
    // Deactivate the plugin cleanly
    if (function_exists('deactivate_plugins')) {
        deactivate_plugins(plugin_basename(__FILE__));
    }
    
    // Build error message
    $error_message = '<h1>' . __('ZipPicks Vibes: Critical Files Missing', 'zippicks-vibes') . '</h1>';
    $error_message .= '<p>' . __('The following required files are missing:', 'zippicks-vibes') . '</p>';
    $error_message .= '<ul style="list-style: disc; margin-left: 20px;">';
    
    foreach ($missing_files as $description => $file_path) {
        $error_message .= '<li>' . sprintf(
            __('%s - Expected at: %s', 'zippicks-vibes'),
            esc_html($description),
            '<code>' . esc_html($file_path) . '</code>'
        ) . '</li>';
    }
    
    $error_message .= '</ul>';
    $error_message .= '<p>' . __('The plugin has been deactivated. Please reinstall the plugin or contact support.', 'zippicks-vibes') . '</p>';
    
    // For multisite, include network admin link if applicable
    if (is_multisite() && is_network_admin()) {
        $error_message .= '<p><a href="' . esc_url(network_admin_url('plugins.php')) . '">' . __('Return to Network Plugins', 'zippicks-vibes') . '</a></p>';
    } else {
        $error_message .= '<p><a href="' . esc_url(admin_url('plugins.php')) . '">' . __('Return to Plugins', 'zippicks-vibes') . '</a></p>';
    }
    
    wp_die($error_message, __('Plugin Error', 'zippicks-vibes'), ['back_link' => true]);
}

// Load the main plugin class
require_once ZIPPICKS_VIBES_DIR . 'src/class-vibes-plugin.php';

// Initialize plugin
function zippicks_vibes_init() {
    return ZipPicksVibes\VibesPlugin::get_instance();
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, function() {
    zippicks_vibes_init()->activate();
});

register_deactivation_hook(__FILE__, function() {
    zippicks_vibes_init()->deactivate();
});

// CRITICAL: Prevent multiple initializations
global $zippicks_vibes_initialized;
if (!empty($zippicks_vibes_initialized)) {
    return;
}
$zippicks_vibes_initialized = true;

// Load compatibility layer
require_once ZIPPICKS_VIBES_DIR . 'includes/object-cache-compat.php';

// Load AJAX fixes for network error issues
if (file_exists(ZIPPICKS_VIBES_DIR . 'includes/ajax-fix.php')) {
    require_once ZIPPICKS_VIBES_DIR . 'includes/ajax-fix.php';
}

// Load template loader fix EARLY to ensure it hooks properly
if (file_exists(ZIPPICKS_VIBES_DIR . 'includes/template-loader-fix.php')) {
    require_once ZIPPICKS_VIBES_DIR . 'includes/template-loader-fix.php';
}

// EMERGENCY: Force AJAX handler registration if main init fails
if (file_exists(ZIPPICKS_VIBES_DIR . 'emergency-ajax-fix.php')) {
    require_once ZIPPICKS_VIBES_DIR . 'emergency-ajax-fix.php';
}

// Add shutdown handler for cleanup
register_shutdown_function(function() {
    // Clean up any open connections
    if (function_exists('zippicks') && zippicks()->has('vibes.cache')) {
        try {
            $cache = zippicks()->get('vibes.cache');
            if (method_exists($cache, 'disconnect')) {
                $cache->disconnect();
            }
        } catch (Exception $e) {
            // Silent fail on shutdown
        }
    }
});

// CRITICAL FIX: Ensure AJAX handlers are always registered
add_action('init', function() {
    // Only proceed if in admin or doing AJAX
    if (!is_admin() && !wp_doing_ajax()) {
        return;
    }
    
    // Check if main plugin class exists
    if (!class_exists('ZipPicksVibes\VibesPlugin')) {
        error_log('[ZipPicks Vibes] CRITICAL: Main plugin class not found');
        
        // Load the plugin class file directly
        $plugin_class_file = ZIPPICKS_VIBES_DIR . 'src/class-vibes-plugin.php';
        if (file_exists($plugin_class_file)) {
            require_once $plugin_class_file;
        } else {
            error_log('[ZipPicks Vibes] CRITICAL: Plugin class file missing: ' . $plugin_class_file);
            return;
        }
    }
    
    // Ensure the plugin is initialized
    $plugin = zippicks_vibes_init();
    
    // Verify AJAX handlers are registered
    global $wp_filter;
    if (!isset($wp_filter['wp_ajax_zippicks_vibes_save'])) {
        error_log('[ZipPicks Vibes] WARNING: AJAX handlers not registered, forcing registration');
        
        // Force load the admin controller
        zippicks_vibes_force_admin_init();
    }
}, 0); // Run very early

// Additional hook to ensure AJAX handlers are registered even earlier
add_action('admin_init', function() {
    if (!wp_doing_ajax()) {
        return;
    }
    
    global $wp_filter;
    if (!isset($wp_filter['wp_ajax_zippicks_vibes_save'])) {
        error_log('[ZipPicks Vibes] CRITICAL: AJAX handlers still not registered in admin_init, forcing registration');
        zippicks_vibes_force_admin_init();
    }
}, -999); // Run extremely early

/**
 * Force admin controller initialization
 */
function zippicks_vibes_force_admin_init() {
    try {
        // Load required files in correct order
        $base_dir = ZIPPICKS_VIBES_DIR . 'src/';
        
        // 1. Load interfaces first
        if (file_exists($base_dir . 'Repositories/VibeRepositoryInterface.php')) {
            require_once $base_dir . 'Repositories/VibeRepositoryInterface.php';
        }
        
        // 2. Load models
        $model_files = [
            'Models/Vibe.php',
            'Models/PaginatedResult.php'
        ];
        foreach ($model_files as $file) {
            if (file_exists($base_dir . $file)) {
                require_once $base_dir . $file;
            }
        }
        
        // 3. Load repository
        if (file_exists($base_dir . 'Repositories/VibeRepository.php')) {
            require_once $base_dir . 'Repositories/VibeRepository.php';
        }
        
        // 4. Load service
        if (file_exists($base_dir . 'Services/VibeService.php')) {
            require_once $base_dir . 'Services/VibeService.php';
        }
        
        // 5. Load cache dependencies if needed
        if (file_exists($base_dir . 'Cache/CacheInterface.php')) {
            require_once $base_dir . 'Cache/CacheInterface.php';
        }
        
        // Load cache adapters
        $cache_adapters = [
            'Cache/Adapters/TransientAdapter.php',
            'Cache/Adapters/ObjectCacheAdapter.php',
            'Cache/Adapters/RedisAdapter.php'
        ];
        foreach ($cache_adapters as $adapter) {
            if (file_exists($base_dir . $adapter)) {
                require_once $base_dir . $adapter;
            }
        }
        
        // Now load cache manager
        if (file_exists($base_dir . 'Cache/CacheManager.php')) {
            require_once $base_dir . 'Cache/CacheManager.php';
        }
        
        // 6. Load admin controller
        if (file_exists($base_dir . 'Admin/VibesAdminController.php')) {
            require_once $base_dir . 'Admin/VibesAdminController.php';
        }
        
        // Create instances with proper parameters
        $cache = null;
        if (class_exists('\ZipPicksVibes\Cache\CacheManager')) {
            $config = [
                'prefix' => 'zippicks_vibes_',
                'default_ttl' => defined('ZIPPICKS_VIBES_CACHE_TTL') ? ZIPPICKS_VIBES_CACHE_TTL : 300
            ];
            $cache = new \ZipPicksVibes\Cache\CacheManager(null, $config, null);
        }
        
        $repository = new \ZipPicksVibes\Repositories\VibeRepository(null, $cache, null);
        $service = new \ZipPicksVibes\Services\VibeService($repository, null, $cache);
        $controller = new \ZipPicksVibes\Admin\VibesAdminController($service, null);
        
        // Initialize the controller (this registers AJAX handlers)
        $controller->init();
        
        error_log('[ZipPicks Vibes] Admin controller force-initialized successfully');
        
    } catch (Exception $e) {
        error_log('[ZipPicks Vibes] CRITICAL: Failed to force-init admin controller: ' . $e->getMessage());
        error_log('[ZipPicks Vibes] Stack trace: ' . $e->getTraceAsString());
    }
}

// Template loader fix loaded earlier in the file

// Initialize the plugin
add_action('plugins_loaded', 'zippicks_vibes_init', 0);

// CRITICAL: Direct AJAX handler registration
add_action('init', function() {
    // Only proceed if doing AJAX
    if (!wp_doing_ajax()) {
        return;
    }
    
    // Try to get the admin controller from Foundation or create it directly
    $admin_controller = null;
    
    // First try Foundation
    if (function_exists('zippicks') && zippicks()->has('vibes.admin')) {
        try {
            $admin_controller = zippicks()->get('vibes.admin');
        } catch (Exception $e) {
            error_log('[ZipPicks Vibes] Failed to get admin controller from Foundation: ' . $e->getMessage());
        }
    }
    
    // If Foundation failed, create controller directly
    if (!$admin_controller) {
        try {
            // Load required files
            $base_dir = ZIPPICKS_VIBES_DIR . 'src/';
            
            // Load dependencies in correct order
            $files_to_load = [
                'Cache/CacheInterface.php',
                'Cache/Adapters/TransientAdapter.php',
                'Cache/Adapters/ObjectCacheAdapter.php',
                'Cache/Adapters/RedisAdapter.php',
                'Cache/CacheManager.php',
                'Repositories/VibeRepositoryInterface.php',
                'Models/Vibe.php',
                'Models/PaginatedResult.php',
                'Repositories/VibeRepository.php',
                'Services/VibeService.php',
                'Admin/VibesAdminController.php'
            ];
            
            foreach ($files_to_load as $file) {
                $filepath = $base_dir . $file;
                if (file_exists($filepath)) {
                    require_once $filepath;
                }
            }
            
            // Create instances
            $cache = new \ZipPicksVibes\Cache\CacheManager(null, ['prefix' => 'zippicks_vibes_'], null);
            $repository = new \ZipPicksVibes\Repositories\VibeRepository(null, $cache, null);
            $service = new \ZipPicksVibes\Services\VibeService($repository, null, $cache);
            $admin_controller = new \ZipPicksVibes\Admin\VibesAdminController($service, null);
            
        } catch (Exception $e) {
            error_log('[ZipPicks Vibes] Failed to create admin controller directly: ' . $e->getMessage());
        }
    }
    
    // Register AJAX handlers if we have a controller
    if ($admin_controller) {
        $ajax_actions = [
            'zippicks_vibes_save' => [$admin_controller, 'ajax_save_vibe'],
            'zippicks_vibes_delete' => [$admin_controller, 'ajax_delete_vibe'],
            'zippicks_vibes_get' => [$admin_controller, 'ajax_get_vibe'],
            'zippicks_vibes_bulk' => [$admin_controller, 'ajax_bulk_action'],
            'zippicks_vibes_toggle_status' => [$admin_controller, 'ajax_toggle_status'],
            'zippicks_vibes_reorder' => [$admin_controller, 'ajax_reorder_vibes'],
            'zippicks_vibes_categories' => [$admin_controller, 'ajax_get_categories'],
            'zippicks_vibes_save_category' => [$admin_controller, 'ajax_save_category'],
            'zippicks_vibes_delete_category' => [$admin_controller, 'ajax_delete_category']
        ];
        
        foreach ($ajax_actions as $action => $callback) {
            add_action('wp_ajax_' . $action, $callback);
        }
        
        error_log('[ZipPicks Vibes] AJAX handlers registered successfully');
    } else {
        error_log('[ZipPicks Vibes] WARNING: Could not create admin controller for AJAX handlers');
    }
}, 0);