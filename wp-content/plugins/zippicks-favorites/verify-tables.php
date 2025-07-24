<?php
/**
 * Verification script to test table creation
 * 
 * This script verifies that all table creation methods work properly.
 */

// Find and load WordPress
$depth = 0;
while ($depth < 10) {
    $wp_load = str_repeat('../', $depth) . 'wp-load.php';
    if (file_exists($wp_load)) {
        define('WP_USE_THEMES', false);
        require_once($wp_load);
        break;
    }
    $depth++;
}

if (!defined('ABSPATH')) {
    die('Could not load WordPress');
}

// Check permissions
if (!current_user_can('manage_options')) {
    die('Unauthorized - please login as admin');
}

// Load dependencies
require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-installer.php';

// Output
header('Content-Type: text/plain; charset=utf-8');
echo "ZipPicks Favorites - Table Verification\n";
echo str_repeat('=', 50) . "\n\n";

// 1. Check current table status
echo "1. Current Table Status:\n";
echo str_repeat('-', 30) . "\n";

global $wpdb;
$tables = [
    'zippicks_favorites',
    'zippicks_favorites_meta',
    'zippicks_location_cache'
];

$all_exist = true;
foreach ($tables as $table) {
    $full_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_name'");
    $status = $exists ? '✓ EXISTS' : '✗ MISSING';
    echo "$full_name: $status\n";
    if (!$exists) $all_exist = false;
}

echo "\n";

// 2. Test Installer::tables_exist() method
echo "2. Installer::tables_exist() Test:\n";
echo str_repeat('-', 30) . "\n";
$installer_check = \ZipPicks\Favorites\Installer::tables_exist();
echo "Result: " . ($installer_check ? 'TRUE (all tables exist)' : 'FALSE (tables missing)') . "\n\n";

// 3. Test admin action URL generation
echo "3. Admin Action URL:\n";
echo str_repeat('-', 30) . "\n";
$admin_url = wp_nonce_url(
    admin_url('admin.php?action=zippicks_create_tables'),
    'zippicks_create_tables'
);
echo "URL: $admin_url\n\n";

// 4. Test Database::get_schema_sql() method
echo "4. Schema SQL Generation Test:\n";
echo str_repeat('-', 30) . "\n";
$schemas = \ZipPicks\Favorites\Database::get_schema_sql();
echo "Generated " . count($schemas) . " table schemas\n";
foreach ($schemas as $table => $sql) {
    echo "- $table: " . (strlen($sql) > 0 ? 'OK' : 'EMPTY') . " (" . strlen($sql) . " chars)\n";
}

echo "\n";

// 5. Check plugin version tracking
echo "5. Version Tracking:\n";
echo str_repeat('-', 30) . "\n";
echo "Plugin Version Constant: " . (defined('ZIPPICKS_FAVORITES_VERSION') ? ZIPPICKS_FAVORITES_VERSION : 'NOT DEFINED') . "\n";
echo "Installed Version Option: " . get_option('zippicks_favorites_version', 'NOT SET') . "\n";

echo "\n";

// 6. Check if foundation is available
echo "6. Foundation Integration:\n";
echo str_repeat('-', 30) . "\n";
echo "zippicks() function exists: " . (function_exists('zippicks') ? 'YES' : 'NO') . "\n";
if (function_exists('zippicks')) {
    echo "Has database.installer service: " . (zippicks()->has('database.installer') ? 'YES' : 'NO') . "\n";
}

echo "\n";

// 7. Summary and recommendations
echo "7. Summary:\n";
echo str_repeat('-', 30) . "\n";

if ($all_exist) {
    echo "✓ All tables exist - plugin should work correctly!\n";
} else {
    echo "✗ Tables are missing. Recommended actions:\n";
    echo "1. Visit: $admin_url\n";
    echo "2. Or access: " . plugins_url('create-tables.php', __FILE__) . "\n";
    echo "3. Or deactivate and reactivate the plugin\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Verification complete.\n";