<?php
/**
 * Plugin Name:       ZipPicks Business
 * Plugin URI:        https://zippicks.com/
 * Description:       Centralized business management system for ZipPicks platform - handles monetization, verification, analytics and business operations
 * Version:           1.0.0
 * Author:            ZipPicks
 * Author URI:        https://zippicks.com/
 * License:           Proprietary
 * Text Domain:       zippicks-business
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Enterprise PHP version check
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php printf(__('ZipPicks Business requires PHP 8.0 or higher. You are running PHP %s.', 'zippicks-business'), PHP_VERSION); ?></p>
        </div>
        <?php
    });
    return; // Stop plugin execution
}

// Define plugin constants
define('ZIPPICKS_BUSINESS_VERSION', '1.0.0');
define('ZIPPICKS_BUSINESS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZIPPICKS_BUSINESS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZIPPICKS_BUSINESS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Enterprise environment detection
if (!defined('ZIPPICKS_BUSINESS_ENV')) {
    define('ZIPPICKS_BUSINESS_ENV', wp_get_environment_type() ?: 'production');
}

// Debug mode - disabled in production
if (!defined('ZIPPICKS_BUSINESS_DEBUG')) {
    define('ZIPPICKS_BUSINESS_DEBUG', ZIPPICKS_BUSINESS_ENV !== 'production' && defined('WP_DEBUG') && WP_DEBUG);
}

// Security constants - static values only, dynamic values set later
define('ZIPPICKS_BUSINESS_RATE_LIMIT_WINDOW', 60); // seconds
define('ZIPPICKS_BUSINESS_RATE_LIMIT_REQUESTS', 60); // requests per window

// Activation/Deactivation hooks
register_activation_hook(__FILE__, 'activate_zippicks_business');
register_deactivation_hook(__FILE__, 'deactivate_zippicks_business');

function activate_zippicks_business() {
    // Enterprise activation with error handling
    try {
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-activator.php';
        
        // Verify class exists before using
        if (!class_exists('ZipPicks_Business_Activator')) {
            throw new Exception('Activator class not found. Plugin files may be corrupted.');
        }
        
        ZipPicks_Business_Activator::activate();
        
    } catch (Exception $e) {
        // Log error if possible
        if (function_exists('error_log')) {
            error_log('ZipPicks Business activation failed: ' . $e->getMessage());
        }
        
        // Store error for display
        set_transient('zippicks_business_activation_error', $e->getMessage(), 60);
        
        // Deactivate plugin
        deactivate_plugins(plugin_basename(__FILE__));
        
        // Show error
        wp_die(
            sprintf(
                __('Plugin activation failed: %s', 'zippicks-business'),
                esc_html($e->getMessage())
            ),
            __('Activation Error', 'zippicks-business'),
            array('back_link' => true)
        );
    }
}

function deactivate_zippicks_business() {
    require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-deactivator.php';
    ZipPicks_Business_Deactivator::deactivate();
}

// Verify critical files exist before loading
$critical_files = array(
    'includes/class-business.php',
    'includes/class-database.php',
    'includes/class-installer.php',
    'includes/class-post-types.php',
    'includes/class-business-manager.php'
);

$missing_files = array();
foreach ($critical_files as $file) {
    if (!file_exists(ZIPPICKS_BUSINESS_PLUGIN_DIR . $file)) {
        $missing_files[] = $file;
    }
}

if (!empty($missing_files)) {
    add_action('admin_notices', function() use ($missing_files) {
        ?>
        <div class="notice notice-error">
            <p><strong><?php _e('ZipPicks Business Error:', 'zippicks-business'); ?></strong></p>
            <p><?php _e('Critical files are missing:', 'zippicks-business'); ?></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach ($missing_files as $file) : ?>
                    <li><?php echo esc_html($file); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    });
    return; // Stop execution
}

// Load main plugin class
require ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-business.php';

// Initialize security constants after WordPress loads
add_action('init', function() {
    if (!defined('ZIPPICKS_BUSINESS_NONCE_KEY')) {
        define('ZIPPICKS_BUSINESS_NONCE_KEY', 'zp_business_' . wp_hash('zippicks_business_nonce'));
    }
}, 1); // Priority 1 to run early

// Initialize plugin
function run_zippicks_business() {
    try {
        if (!class_exists('ZipPicks_Business')) {
            throw new Exception('Main plugin class not found');
        }
        
        $plugin = new ZipPicks_Business();
        $plugin->run();
        
    } catch (Exception $e) {
        if (ZIPPICKS_BUSINESS_DEBUG) {
            error_log('ZipPicks Business initialization error: ' . $e->getMessage());
        }
        
        add_action('admin_notices', function() use ($e) {
            ?>
            <div class="notice notice-error">
                <p><strong><?php _e('ZipPicks Business initialization failed:', 'zippicks-business'); ?></strong> 
                <?php echo esc_html($e->getMessage()); ?></p>
            </div>
            <?php
        });
    }
}

// Load enterprise validation system
require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'enterprise-validation.php';

// Check dependencies and run
add_action('plugins_loaded', function() {
    // Run enterprise validation in production
    if (ZIPPICKS_BUSINESS_ENV === 'production' || ZIPPICKS_BUSINESS_DEBUG) {
        $validation_passed = ZipPicks_Business_Enterprise_Validator::validate();
        
        if (!$validation_passed) {
            // Display validation errors in admin
            add_action('admin_notices', array('ZipPicks_Business_Enterprise_Validator', 'display_admin_report'));
            
            // In production, stop execution if critical errors
            if (ZIPPICKS_BUSINESS_ENV === 'production') {
                return; // Do not initialize plugin
            }
        } elseif (ZIPPICKS_BUSINESS_DEBUG) {
            // Show validation report in debug mode
            add_action('admin_notices', array('ZipPicks_Business_Enterprise_Validator', 'display_admin_report'));
        }
    }
    
    // Check if Foundation is available (optional but recommended)
    if (!function_exists('zippicks')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e('ZipPicks Business works best with ZipPicks Foundation. Some features may be limited.', 'zippicks-business'); ?></p>
            </div>
            <?php
        });
    }
    
    // Always run the plugin - it will degrade gracefully
    run_zippicks_business();
}, 10); // Priority 10 to run after foundation