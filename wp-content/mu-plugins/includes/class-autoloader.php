<?php
/**
 * Autoloader for ZipPicks Foundation
 * 
 * @package ZipPicks\Foundation
 */

namespace ZipPicks\Foundation;

if (!defined('ABSPATH')) {
    exit;
}

class Autoloader {
    
    /**
     * Register the autoloader
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }
    
    /**
     * Autoload classes
     * 
     * @param string $class Class name
     */
    public static function autoload($class) {
        // Only autoload our namespace
        if (strpos($class, 'ZipPicks\\Foundation\\') !== 0) {
            return;
        }
        
        // Remove namespace prefix and convert to file path
        $relative_class = str_replace('ZipPicks\\Foundation\\', '', $class);
        $file_path = strtolower(str_replace('\\', '/', $relative_class));
        $file_path = str_replace('_', '-', $file_path);
        
        // Build the full file path
        $file = ZIPPICKS_FOUNDATION_PATH . 'includes/class-' . $file_path . '.php';
        
        // Check for trait files
        if (strpos($relative_class, 'Traits\\') === 0) {
            $trait_path = str_replace('Traits\\', '', $relative_class);
            $trait_path = strtolower(str_replace('_', '-', $trait_path));
            $file = ZIPPICKS_FOUNDATION_PATH . 'traits/trait-' . $trait_path . '.php';
        }
        
        // Check for admin files
        if (strpos($relative_class, 'Admin\\') === 0) {
            $admin_path = str_replace('Admin\\', '', $relative_class);
            $admin_path = strtolower(str_replace('_', '-', $admin_path));
            $file = ZIPPICKS_FOUNDATION_PATH . 'admin/class-' . $admin_path . '.php';
        }
        
        // Check for API files
        if (strpos($relative_class, 'API\\') === 0) {
            $api_path = str_replace('API\\', '', $relative_class);
            $api_path = strtolower(str_replace('_', '-', $api_path));
            $file = ZIPPICKS_FOUNDATION_PATH . 'api/class-' . $api_path . '.php';
        }
        
        // Check for database files
        if (strpos($relative_class, 'Database\\') === 0) {
            $db_path = str_replace('Database\\', '', $relative_class);
            $db_path = strtolower(str_replace('_', '-', $db_path));
            $file = ZIPPICKS_FOUNDATION_PATH . 'database/class-' . $db_path . '.php';
        }
        
        // Load the file if it exists
        if (file_exists($file)) {
            require_once $file;
        }
    }
}