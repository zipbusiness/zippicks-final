<?php
/**
 * Debug script to verify AJAX handlers are properly registered
 * 
 * Run this script by visiting: /wp-content/plugins/zippicks-vibes/debug-ajax-handlers.php
 */

// WordPress bootstrap
require_once '../../../wp-config.php';

echo "<h1>ZipPicks Vibes AJAX Handler Debug</h1>";

// Check if WordPress is loaded
if (!function_exists('wp_create_nonce')) {
    echo "<p style='color: red;'>❌ WordPress not properly loaded</p>";
    exit;
}

echo "<p style='color: green;'>✅ WordPress loaded successfully</p>";

// Check if plugin is active
if (!function_exists('zippicks_vibes_init')) {
    echo "<p style='color: red;'>❌ ZipPicks Vibes plugin not active</p>";
    exit;
}

echo "<p style='color: green;'>✅ ZipPicks Vibes plugin is active</p>";

// Check global wp_filter for AJAX handlers
global $wp_filter;

$ajax_actions = [
    'wp_ajax_zippicks_vibes_save',
    'wp_ajax_zippicks_vibes_delete',
    'wp_ajax_zippicks_vibes_get',
    'wp_ajax_zippicks_vibes_bulk',
    'wp_ajax_zippicks_vibes_toggle_status',
    'wp_ajax_zippicks_vibes_reorder',
    'wp_ajax_zippicks_vibes_categories',
    'wp_ajax_zippicks_vibes_save_category',
    'wp_ajax_zippicks_vibes_delete_category'
];

echo "<h2>AJAX Handler Registration Status</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Action</th><th>Registered</th><th>Priority</th><th>Callback</th></tr>";

foreach ($ajax_actions as $action) {
    $registered = isset($wp_filter[$action]);
    $priority = $registered ? array_keys($wp_filter[$action]->callbacks)[0] ?? 'N/A' : 'N/A';
    $callback = 'N/A';
    
    if ($registered && isset($wp_filter[$action]->callbacks)) {
        $first_priority = array_keys($wp_filter[$action]->callbacks)[0];
        $first_callback = array_values($wp_filter[$action]->callbacks[$first_priority])[0];
        $callback = is_array($first_callback['function']) 
            ? get_class($first_callback['function'][0]) . '->' . $first_callback['function'][1]
            : (string) $first_callback['function'];
    }
    
    $status_color = $registered ? 'green' : 'red';
    $status_text = $registered ? '✅ Yes' : '❌ No';
    
    echo "<tr>";
    echo "<td><code>$action</code></td>";
    echo "<td style='color: $status_color;'>$status_text</td>";
    echo "<td>$priority</td>";
    echo "<td>$callback</td>";
    echo "</tr>";
}

echo "</table>";

// Test localization data
echo "<h2>Admin Script Localization</h2>";

// Simulate admin context
define('WP_ADMIN', true);
$_GET['page'] = 'zippicks-vibes-add';

// Initialize plugin
$plugin = zippicks_vibes_init();

// Get admin controller if possible
try {
    if (method_exists($plugin, 'get_admin_controller')) {
        $admin_controller = $plugin->get_admin_controller();
        echo "<p style='color: green;'>✅ Admin controller available</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Admin controller method not available</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error getting admin controller: " . $e->getMessage() . "</p>";
}

// Check nonce generation
$nonce = wp_create_nonce('zippicks_vibes_admin');
echo "<p><strong>Sample nonce:</strong> <code>$nonce</code></p>";

// Check AJAX URL
$ajax_url = admin_url('admin-ajax.php');
echo "<p><strong>AJAX URL:</strong> <code>$ajax_url</code></p>";

// Test manual AJAX call simulation
echo "<h2>Manual AJAX Test</h2>";
echo "<p>Testing direct AJAX handler call...</p>";

// Simulate AJAX request
$_POST = [
    'action' => 'zippicks_vibes_save',
    'nonce' => $nonce,
    'vibe_name' => 'Test Vibe',
    'vibe_description' => 'Test Description',
    'vibe_color' => '#194FAD',
    'vibe_id' => 0
];

// Set up admin user context
wp_set_current_user(1); // Assume user ID 1 is admin

try {
    ob_start();
    do_action('wp_ajax_zippicks_vibes_save');
    $output = ob_get_clean();
    
    if (empty($output)) {
        echo "<p style='color: red;'>❌ No output from AJAX handler</p>";
    } else {
        echo "<p style='color: green;'>✅ AJAX handler produced output:</p>";
        echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
        echo htmlspecialchars($output);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error calling AJAX handler: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><em>Debug completed at " . date('Y-m-d H:i:s') . "</em></p>";