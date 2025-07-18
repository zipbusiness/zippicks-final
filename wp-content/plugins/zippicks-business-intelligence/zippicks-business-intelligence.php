<?php
/**
 * Plugin Name: ZipPicks Business Intelligence
 * Plugin URI: https://zippicks.com/
 * Description: Enterprise-grade business data integration with ZipBusiness.ai API for the ZipPicks platform
 * Version: 1.0.0
 * Author: ZipPicks Engineering
 * Author URI: https://zippicks.com/
 * License: Proprietary
 * Text Domain: zippicks-business-intelligence
 * Domain Path: /languages
 * 
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ZIPPICKS_BI_VERSION', '1.0.0');
define('ZIPPICKS_BI_PLUGIN_FILE', __FILE__);
define('ZIPPICKS_BI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZIPPICKS_BI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZIPPICKS_BI_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'ZipPicks\\BusinessIntelligence\\';
    $base_dir = ZIPPICKS_BI_PLUGIN_DIR . 'src/';
    
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

// Load required files
require_once ZIPPICKS_BI_PLUGIN_DIR . 'includes/class-activator.php';
require_once ZIPPICKS_BI_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once ZIPPICKS_BI_PLUGIN_DIR . 'includes/class-business-intelligence.php';

// Activation and deactivation hooks
register_activation_hook(__FILE__, [__NAMESPACE__ . '\\Includes\\Activator', 'activate']);
register_deactivation_hook(__FILE__, [__NAMESPACE__ . '\\Includes\\Deactivator', 'deactivate']);

/**
 * Begin execution of the plugin
 */
function run_business_intelligence() {
    // Check for Foundation dependency
    if (!function_exists('zippicks')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('ZipPicks Business Intelligence requires ZipPicks Foundation to be activated.', 'zippicks-business-intelligence'); ?></p>
            </div>
            <?php
        });
        return;
    }
    
    $plugin = new Includes\BusinessIntelligence();
    $plugin->run();
}

add_action('plugins_loaded', __NAMESPACE__ . '\\run_business_intelligence', 20);