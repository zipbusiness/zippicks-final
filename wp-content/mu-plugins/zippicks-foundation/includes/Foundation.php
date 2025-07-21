<?php
/**
 * Foundation Bootstrap File
 * 
 * This file bootstraps the ZipPicks Foundation system.
 * It's loaded by the MU plugin loader.
 *
 * @package ZipPicks\Foundation
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define foundation path if not already defined
if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
    define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__));
}

// Define foundation version if not already defined
if (!defined('ZIPPICKS_FOUNDATION_VERSION')) {
    define('ZIPPICKS_FOUNDATION_VERSION', '1.0.0');
}

try {
    // Load the autoloader class directly first
    require_once ZIPPICKS_FOUNDATION_PATH . '/src/Core/Autoloader.php';
    
    // Create and register the autoloader instance
    $autoloader = new \ZipPicks\Foundation\Core\Autoloader();
    
    // Add the Foundation namespace
    $autoloader->addNamespace('ZipPicks\\Foundation\\', ZIPPICKS_FOUNDATION_PATH . '/src/');
    
    // Add the PSR namespace for container interfaces
    $autoloader->addNamespace('Psr\\Container\\', ZIPPICKS_FOUNDATION_PATH . '/src/Psr/Container/');
    
    // Register the autoloader
    $autoloader->register();
    
    // Now we can load other classes
    
    // Load helpers
    require_once ZIPPICKS_FOUNDATION_PATH . '/src/helpers.php';
    
    // Load Composer autoloader if available
    if (file_exists(ZIPPICKS_FOUNDATION_PATH . '/vendor/autoload.php')) {
        require_once ZIPPICKS_FOUNDATION_PATH . '/vendor/autoload.php';
    }
    
    // Boot Foundation
    $foundation = \ZipPicks\Foundation\Core\Foundation::getInstance();
    $foundation->boot();
    
    // Register global helper if not exists
    if (!function_exists('zippicks_foundation')) {
        function zippicks_foundation() {
            return \ZipPicks\Foundation\Core\Foundation::getInstance();
        }
    }
    
} catch (\Throwable $e) {
    // Log error
    error_log('ZipPicks Foundation Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    // Show admin notice
    add_action('admin_notices', function() use ($e) {
        ?>
        <div class="notice notice-error">
            <p><strong>ZipPicks Foundation Error:</strong> <?php echo esc_html($e->getMessage()); ?></p>
        </div>
        <?php
    });
    
    // Provide fallback to prevent crashes
    if (!function_exists('zippicks_foundation')) {
        function zippicks_foundation() {
            return null;
        }
    }
}