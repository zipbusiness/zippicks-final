<?php
/**
 * Test script to identify plugin activation issues
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Ensure we're in admin context
if (!defined('WP_ADMIN')) {
    define('WP_ADMIN', true);
}

// Include plugin functions
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Test PHP syntax first
$plugin_file = dirname(__FILE__) . '/zippicks-vibes.php';
$syntax_check = shell_exec("php -l '$plugin_file' 2>&1");
echo "=== PHP Syntax Check ===\n";
echo $syntax_check . "\n\n";

// Check all PHP files for syntax errors
echo "=== Checking all PHP files ===\n";
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(dirname(__FILE__))
);

$has_syntax_errors = false;
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        $result = shell_exec("php -l '$path' 2>&1");
        if (strpos($result, 'No syntax errors') === false) {
            echo "ERROR in: " . str_replace(dirname(__FILE__) . '/', '', $path) . "\n";
            echo $result . "\n";
            $has_syntax_errors = true;
        }
    }
}

if (!$has_syntax_errors) {
    echo "All PHP files have valid syntax.\n\n";
}

// Test plugin activation
echo "=== Testing Plugin Activation ===\n";
$plugin = 'zippicks-vibes/zippicks-vibes.php';

// Check if plugin exists
if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
    echo "ERROR: Plugin file not found at expected location.\n";
    exit;
}

// Capture any errors during activation
ob_start();
$result = activate_plugin($plugin, '', false, true);
$output = ob_get_clean();

if (is_wp_error($result)) {
    echo "ERROR: " . $result->get_error_message() . "\n";
    if ($output) {
        echo "Output: " . $output . "\n";
    }
} else {
    echo "Plugin activated successfully!\n";
    
    // Check if services are registered
    echo "\n=== Checking Foundation Integration ===\n";
    if (function_exists('zippicks')) {
        echo "Foundation is available.\n";
        
        $services = [
            'vibes.repository',
            'vibes.service',
            'vibes.scrape_protection',
            'vibes.renderer',
            'vibes.rate_limiter',
            'vibes.nonce_validator',
            'vibes.api',
            'vibes.admin'
        ];
        
        foreach ($services as $service) {
            if (zippicks()->has($service)) {
                echo "✓ Service registered: $service\n";
            } else {
                echo "✗ Service missing: $service\n";
            }
        }
    } else {
        echo "Foundation not available.\n";
    }
    
    // Check database tables
    echo "\n=== Checking Database Tables ===\n";
    global $wpdb;
    $tables = [
        'zippicks_vibes',
        'zippicks_vibe_categories',
        'zippicks_vibe_category_assignments',
        'zippicks_waitlist',
        'zippicks_scrape_log',
        'zippicks_security_log',
        'zippicks_rate_limit_log'
    ];
    
    foreach ($tables as $table) {
        $full_table = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
        echo ($exists ? "✓" : "✗") . " Table: $full_table\n";
    }
}

// Check PHP version and requirements
echo "\n=== Environment Check ===\n";
echo "PHP Version: " . PHP_VERSION . " (Required: 8.0+)\n";
echo "WordPress Version: " . get_bloginfo('version') . " (Required: 6.0+)\n";

// Check for PHP 8 features used
echo "\n=== PHP 8 Features Check ===\n";
$php8_features = [
    'Typed properties' => version_compare(PHP_VERSION, '7.4', '>='),
    'Union types' => version_compare(PHP_VERSION, '8.0', '>='),
    'Nullsafe operator' => version_compare(PHP_VERSION, '8.0', '>='),
    'Named arguments' => version_compare(PHP_VERSION, '8.0', '>='),
];

foreach ($php8_features as $feature => $supported) {
    echo $feature . ": " . ($supported ? "✓ Supported" : "✗ Not supported") . "\n";
}