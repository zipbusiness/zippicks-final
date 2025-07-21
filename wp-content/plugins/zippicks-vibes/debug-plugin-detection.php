<?php
/**
 * Debug Plugin Detection Issues
 * 
 * This script provides deep analysis of why WordPress cannot detect the plugin
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

// Include plugin functions
require_once ABSPATH . 'wp-admin/includes/plugin.php';

echo "<h1>ZipPicks Vibes Plugin Detection Debug</h1>";
echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>";

// 1. Clear plugin cache
echo "<h2>1. Clearing Plugin Cache</h2>";
wp_cache_delete('plugins', 'plugins');
echo "✅ Plugin cache cleared<br>";

// 2. Force plugin discovery
echo "<h2>2. Plugin Discovery</h2>";
$all_plugins = get_plugins();
echo "Total plugins found: " . count($all_plugins) . "<br><br>";

// Look for our plugin
$found_vibes = false;
echo "<h3>Searching for Vibes Plugin:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Plugin Key</th><th>Plugin Name</th><th>Version</th><th>Status</th></tr>";

foreach ($all_plugins as $plugin_key => $plugin_data) {
    if (stripos($plugin_key, 'vibes') !== false || stripos($plugin_data['Name'], 'vibes') !== false) {
        $found_vibes = true;
        $is_active = is_plugin_active($plugin_key);
        echo "<tr>";
        echo "<td>" . esc_html($plugin_key) . "</td>";
        echo "<td>" . esc_html($plugin_data['Name']) . "</td>";
        echo "<td>" . esc_html($plugin_data['Version']) . "</td>";
        echo "<td style='color: " . ($is_active ? 'green' : 'red') . "'>" . ($is_active ? 'ACTIVE' : 'INACTIVE') . "</td>";
        echo "</tr>";
    }
}
echo "</table>";

if (!$found_vibes) {
    echo "<p style='color: red;'>❌ No Vibes plugin found in WordPress plugin list!</p>";
}

// 3. Direct file system check
echo "<h2>3. File System Check</h2>";
$plugin_dir = WP_PLUGIN_DIR . '/zippicks-vibes';
echo "Plugin directory: " . esc_html($plugin_dir) . "<br>";
echo "Directory exists: " . (is_dir($plugin_dir) ? '✅ Yes' : '❌ No') . "<br>";

if (is_dir($plugin_dir)) {
    echo "<h3>PHP files in plugin directory:</h3>";
    $files = glob($plugin_dir . '/*.php');
    echo "<ul>";
    foreach ($files as $file) {
        $basename = basename($file);
        echo "<li>" . esc_html($basename);
        
        // Check if it has plugin headers
        $plugin_data = get_plugin_data($file, false, false);
        if (!empty($plugin_data['Name'])) {
            echo " - <strong>Valid Plugin File</strong>";
            echo " (Name: " . esc_html($plugin_data['Name']) . ")";
        }
        echo "</li>";
    }
    echo "</ul>";
}

// 4. Check active plugins
echo "<h2>4. Active Plugins Check</h2>";
$active_plugins = get_option('active_plugins', []);
echo "Active plugins in database:<br>";
echo "<pre>" . print_r($active_plugins, true) . "</pre>";

// Check multisite
if (is_multisite()) {
    echo "<h3>Multisite Network Active Plugins:</h3>";
    $network_plugins = get_site_option('active_sitewide_plugins', []);
    echo "<pre>" . print_r($network_plugins, true) . "</pre>";
}

// 5. Try different plugin paths
echo "<h2>5. Testing Different Plugin Paths</h2>";
$possible_paths = [
    'zippicks-vibes/zippicks-vibes.php',
    'zippicks-vibes/zippicks-vibes.php',
    'zippicks-vibes/zippicks-vibes.php',
    'zippicks-vibes/zippicks-vibes.php',
];

foreach ($possible_paths as $path) {
    $full_path = WP_PLUGIN_DIR . '/' . $path;
    echo "Testing: " . esc_html($path) . " - ";
    
    if (file_exists($full_path)) {
        echo "✅ File exists";
        $plugin_data = get_plugin_data($full_path, false, false);
        if (!empty($plugin_data['Name'])) {
            echo " - Valid plugin: " . esc_html($plugin_data['Name']);
        }
    } else {
        echo "❌ File not found";
    }
    echo "<br>";
}

// 6. Manual plugin header check
echo "<h2>6. Manual Plugin Header Check</h2>";
$main_file = $plugin_dir . '/zippicks-vibes.php';
if (file_exists($main_file)) {
    echo "Reading main plugin file headers...<br>";
    $fp = fopen($main_file, 'r');
    $file_data = fread($fp, 8192); // Read first 8KB
    fclose($fp);
    
    // Extract plugin headers manually
    preg_match('/Plugin Name:\s*(.*)$/mi', $file_data, $name);
    preg_match('/Version:\s*(.*)$/mi', $file_data, $version);
    preg_match('/Description:\s*(.*)$/mi', $file_data, $description);
    
    if (!empty($name[1])) {
        echo "✅ Valid plugin headers found:<br>";
        echo "- Name: " . esc_html(trim($name[1])) . "<br>";
        echo "- Version: " . esc_html(trim($version[1] ?? 'Unknown')) . "<br>";
        echo "- Description: " . esc_html(substr(trim($description[1] ?? ''), 0, 100)) . "...<br>";
    } else {
        echo "❌ No valid plugin headers found<br>";
    }
}

// 7. WordPress Plugin API direct test
echo "<h2>7. Direct Plugin Validation</h2>";
$test_file = 'zippicks-vibes/zippicks-vibes.php';
$validation = validate_plugin($test_file);
if (is_wp_error($validation)) {
    echo "❌ Validation error: " . esc_html($validation->get_error_message()) . "<br>";
} else {
    echo "✅ Plugin validation passed<br>";
}

// 8. Try to get plugin data directly
echo "<h2>8. Direct Plugin Data Retrieval</h2>";
if (isset($all_plugins['zippicks-vibes/zippicks-vibes.php'])) {
    echo "✅ Plugin found in get_plugins() array<br>";
    echo "<pre>" . print_r($all_plugins['zippicks-vibes/zippicks-vibes.php'], true) . "</pre>";
} else {
    echo "❌ Plugin NOT found in get_plugins() array<br>";
    echo "Available plugin keys:<br>";
    echo "<ul>";
    foreach (array_keys($all_plugins) as $key) {
        echo "<li>" . esc_html($key) . "</li>";
    }
    echo "</ul>";
}

// 9. File permissions check
echo "<h2>9. File Permissions Check</h2>";
if (file_exists($main_file)) {
    $perms = fileperms($main_file);
    echo "Main file permissions: " . substr(sprintf('%o', $perms), -4) . "<br>";
    echo "Readable: " . (is_readable($main_file) ? '✅ Yes' : '❌ No') . "<br>";
}

// 10. Check for syntax errors
echo "<h2>10. PHP Syntax Check</h2>";
if (file_exists($main_file)) {
    $output = shell_exec("php -l '$main_file' 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✅ No syntax errors in main plugin file<br>";
    } else {
        echo "❌ Syntax error found:<br>";
        echo "<pre>" . esc_html($output) . "</pre>";
    }
}

// Solutions
echo "<hr>";
echo "<h2>Recommended Solutions:</h2>";
echo "<ol>";
echo "<li><strong>Manual Activation:</strong> <a href='activate-plugin.php' class='button button-primary'>Run Activation Script</a></li>";
echo "<li><strong>Clear All Caches:</strong> Clear WordPress object cache and browser cache</li>";
echo "<li><strong>Check File Ownership:</strong> Ensure WordPress can read the plugin files</li>";
echo "<li><strong>Reinstall Plugin:</strong> Delete and re-upload the plugin files</li>";
echo "</ol>";

// Debug info
echo "<h2>Debug Information:</h2>";
echo "<pre>";
echo "WP_PLUGIN_DIR: " . WP_PLUGIN_DIR . "\n";
echo "WP_CONTENT_DIR: " . WP_CONTENT_DIR . "\n";
echo "ABSPATH: " . ABSPATH . "\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Is Multisite: " . (is_multisite() ? 'Yes' : 'No') . "\n";
if (is_multisite()) {
    echo "Blog ID: " . get_current_blog_id() . "\n";
    echo "Network ID: " . get_current_network_id() . "\n";
}
echo "</pre>";