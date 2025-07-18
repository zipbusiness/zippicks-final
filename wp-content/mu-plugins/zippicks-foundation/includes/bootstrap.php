<?php
/**
 * Foundation Simple Bootstrap
 * 
 * Manually loads Foundation classes without complex autoloading
 *
 * @package ZipPicks\Foundation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define foundation path
if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
    define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__));
}

// Manual loading of core files in dependency order
$files_to_load = [
    // PSR interfaces first
    '/src/Psr/Container/ContainerInterface.php',
    '/src/Psr/Container/ContainerExceptionInterface.php',
    '/src/Psr/Container/NotFoundExceptionInterface.php',
    
    // Contracts
    '/src/Contracts/Container/ContainerInterface.php',
    '/src/Contracts/Container/ContainerException.php',
    '/src/Contracts/Container/NotFoundException.php',
    '/src/Contracts/ServiceProviderInterface.php',
    '/src/Contracts/Logging/LoggerInterface.php',
    
    // Core classes
    '/src/Core/Container.php',
    '/src/Core/EnvironmentManager.php',
    '/src/Logging/FileLogger.php',
    '/src/Providers/ServiceProvider.php',
    
    // Main Foundation class
    '/src/Core/Foundation.php',
    
    // Helpers last
    '/src/helpers.php',
    '/src/Http/helpers.php',
    '/src/Auth/helpers.php',
    '/src/Cache/helpers.php',
];

$loaded_files = [];
$errors = [];

foreach ($files_to_load as $file) {
    $filepath = ZIPPICKS_FOUNDATION_PATH . $file;
    if (file_exists($filepath)) {
        try {
            require_once $filepath;
            $loaded_files[] = $file;
        } catch (Exception $e) {
            $errors[] = "Failed to load $file: " . $e->getMessage();
        }
    } else {
        $errors[] = "File not found: $file";
    }
}

// Log any errors
if (!empty($errors)) {
    foreach ($errors as $error) {
        error_log('ZipPicks Foundation Bootstrap Error: ' . $error);
    }
}

// Try to initialize Foundation
try {
    if (class_exists('\ZipPicks\Foundation\Core\Foundation')) {
        $foundation = \ZipPicks\Foundation\Core\Foundation::getInstance();
        
        // Allow validation to run - all providers should be fixed now
        // Uncomment below to skip validation if needed for debugging
        // if (!defined('ZIPPICKS_FOUNDATION_SKIP_VALIDATION')) {
        //     define('ZIPPICKS_FOUNDATION_SKIP_VALIDATION', true);
        // }
        
        $foundation->boot();
        
        // Success - log it
        error_log('ZipPicks Foundation successfully booted with manual loading');
    } else {
        error_log('ZipPicks Foundation class not found after manual loading');
        
        // Provide fallback
        if (!function_exists('zippicks_foundation')) {
            function zippicks_foundation() {
                return null;
            }
        }
    }
} catch (Throwable $e) {
    error_log('ZipPicks Foundation boot error: ' . $e->getMessage());
    
    // Provide fallback
    if (!function_exists('zippicks_foundation')) {
        function zippicks_foundation() {
            return null;
        }
    }
}