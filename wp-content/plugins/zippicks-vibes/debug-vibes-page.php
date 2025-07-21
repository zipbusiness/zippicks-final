<?php
/**
 * Debug script for vibes page issue
 * Access this directly to see what's happening
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Admin access required');
}

echo "<h1>Vibes Page Debug</h1>";
echo "<pre>";

// 1. Check if plugin is active
echo "1. Plugin Status:\n";
echo "   - Plugin active: " . (is_plugin_active('zippicks-vibes/zippicks-vibes.php') ? 'YES' : 'NO') . "\n";
echo "   - Plugin file exists: " . (file_exists(WP_PLUGIN_DIR . '/zippicks-vibes/zippicks-vibes.php') ? 'YES' : 'NO') . "\n\n";

// 2. Check rewrite rules
echo "2. Rewrite Rules:\n";
global $wp_rewrite;
$rules = $wp_rewrite->rules;
$vibe_rules = array_filter($rules, function($rule) {
    return strpos($rule, 'vibes') !== false || strpos($rule, 'vibe') !== false;
});
if (empty($vibe_rules)) {
    echo "   - NO VIBE REWRITE RULES FOUND!\n";
} else {
    foreach ($vibe_rules as $pattern => $redirect) {
        echo "   - $pattern => $redirect\n";
    }
}
echo "\n";

// 3. Check query vars
echo "3. Query Vars:\n";
global $wp;
$public_vars = $wp->public_query_vars;
$has_vibe_vars = in_array('zippicks_vibes', $public_vars) || in_array('vibe_slug', $public_vars);
echo "   - Has vibe query vars: " . ($has_vibe_vars ? 'YES' : 'NO') . "\n";
echo "   - zippicks_vibes registered: " . (in_array('zippicks_vibes', $public_vars) ? 'YES' : 'NO') . "\n";
echo "   - vibe_slug registered: " . (in_array('vibe_slug', $public_vars) ? 'YES' : 'NO') . "\n\n";

// 4. Check template files
echo "4. Template Files:\n";
$archive_template = WP_PLUGIN_DIR . '/zippicks-vibes/templates/client-render/vibe-archive.php';
$single_template = WP_PLUGIN_DIR . '/zippicks-vibes/templates/client-render/vibe-single.php';
echo "   - Archive template exists: " . (file_exists($archive_template) ? 'YES' : 'NO') . "\n";
echo "   - Single template exists: " . (file_exists($single_template) ? 'YES' : 'NO') . "\n\n";

// 5. Check if Foundation is available
echo "5. Foundation Status:\n";
echo "   - Foundation function exists: " . (function_exists('zippicks') ? 'YES' : 'NO') . "\n";
if (function_exists('zippicks')) {
    echo "   - Has vibes.service: " . (zippicks()->has('vibes.service') ? 'YES' : 'NO') . "\n";
}
echo "\n";

// 6. Check database tables
echo "6. Database Tables:\n";
global $wpdb;
$tables = $wpdb->get_col("SHOW TABLES LIKE '%vibes%'");
if (empty($tables)) {
    echo "   - NO VIBE TABLES FOUND!\n";
} else {
    foreach ($tables as $table) {
        echo "   - $table\n";
    }
}
echo "\n";

// 7. Test what happens when accessing /vibes/
echo "7. URL Test:\n";
$vibes_url = home_url('/vibes/');
echo "   - Vibes URL: $vibes_url\n";
echo "   - Current permalink structure: " . get_option('permalink_structure') . "\n";

// 8. Check for PHP errors
echo "\n8. PHP Error Log (last 10 lines):\n";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $lines = array_slice(file($error_log), -10);
    foreach ($lines as $line) {
        if (strpos($line, 'vibes') !== false || strpos($line, 'ZipPicks') !== false) {
            echo "   " . trim($line) . "\n";
        }
    }
}

echo "</pre>";

// Add action buttons
echo "<h2>Actions:</h2>";
echo "<p><a href='" . admin_url('options-permalink.php') . "' target='_blank'>Go to Permalinks Settings</a></p>";
echo "<p><a href='" . home_url('/vibes/') . "' target='_blank'>Test Vibes Page</a></p>";