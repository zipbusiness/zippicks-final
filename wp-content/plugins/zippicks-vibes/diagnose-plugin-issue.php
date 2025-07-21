<?php
/**
 * Plugin Detection Diagnostic Tool
 * 
 * Run this to understand why WordPress isn't detecting the plugin
 */

// Define WordPress constants if not already defined
if (!defined('ABSPATH')) {
    // Try to load WordPress
    $wp_load_paths = [
        dirname(__FILE__) . '/../../../wp-load.php',
        dirname(__FILE__) . '/../../../../wp-load.php',
        dirname(__FILE__) . '/../../../../../wp-load.php',
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('Could not load WordPress. Please run this from within WordPress.');
    }
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

echo "<h1>ZipPicks Vibes Plugin Detection Diagnostic</h1>";
echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>";

// 1. Check plugin file location
echo "<h2>1. Plugin File Location</h2>";
$plugin_file = dirname(__FILE__) . '/zippicks-vibes.php';
$plugin_dir = dirname(__FILE__);
echo "Plugin directory: <code>" . esc_html($plugin_dir) . "</code><br>";
echo "Plugin file: <code>" . esc_html($plugin_file) . "</code><br>";
echo "File exists: " . (file_exists($plugin_file) ? '✅ YES' : '❌ NO') . "<br>";
echo "Is readable: " . (is_readable($plugin_file) ? '✅ YES' : '❌ NO') . "<br>";

// 2. Check WordPress plugin directory
echo "<h2>2. WordPress Plugin System</h2>";
echo "WP_PLUGIN_DIR: <code>" . WP_PLUGIN_DIR . "</code><br>";
echo "plugins_url(): <code>" . plugins_url() . "</code><br>";
echo "plugin_dir_url(): <code>" . plugin_dir_url(__FILE__) . "</code><br>";

// 3. Try to parse plugin headers directly
echo "<h2>3. Plugin Header Parsing</h2>";
if (file_exists($plugin_file)) {
    $plugin_data = get_file_data($plugin_file, array(
        'Name' => 'Plugin Name',
        'PluginURI' => 'Plugin URI',
        'Version' => 'Version',
        'Description' => 'Description',
        'Author' => 'Author',
        'AuthorURI' => 'Author URI',
        'TextDomain' => 'Text Domain',
        'DomainPath' => 'Domain Path',
        'Network' => 'Network',
        'RequiresWP' => 'Requires at least',
        'RequiresPHP' => 'Requires PHP',
    ));
    
    if (!empty($plugin_data['Name'])) {
        echo "✅ Plugin headers parsed successfully:<br>";
        echo "<pre>";
        print_r($plugin_data);
        echo "</pre>";
    } else {
        echo "❌ Failed to parse plugin headers<br>";
        
        // Show raw file content
        echo "<h4>First 50 lines of plugin file:</h4>";
        $lines = file($plugin_file);
        echo "<pre style='background: #f0f0f0; padding: 10px; overflow-x: auto;'>";
        for ($i = 0; $i < min(50, count($lines)); $i++) {
            echo sprintf("%3d: %s", $i + 1, htmlspecialchars($lines[$i]));
        }
        echo "</pre>";
    }
}

// 4. Check for PHP errors
echo "<h2>4. PHP Syntax Check</h2>";
$php_check = shell_exec('php -l ' . escapeshellarg($plugin_file) . ' 2>&1');
if ($php_check) {
    if (strpos($php_check, 'No syntax errors') !== false) {
        echo "✅ " . esc_html($php_check);
    } else {
        echo "❌ PHP Syntax Error:<br><pre>" . esc_html($php_check) . "</pre>";
    }
} else {
    echo "⚠️ Could not run PHP syntax check (shell_exec may be disabled)<br>";
}

// 5. Check all plugins WordPress can see
echo "<h2>5. All Detected Plugins</h2>";
if (!function_exists('get_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$all_plugins = get_plugins();
$plugin_key = 'zippicks-vibes/zippicks-vibes.php';

if (isset($all_plugins[$plugin_key])) {
    echo "✅ <strong>Plugin IS detected by WordPress!</strong><br>";
    echo "Details:<br>";
    echo "<pre>";
    print_r($all_plugins[$plugin_key]);
    echo "</pre>";
    
    // Check activation status
    if (is_plugin_active($plugin_key)) {
        echo "✅ Plugin is ACTIVE<br>";
    } else {
        echo "❌ Plugin is NOT active<br>";
        $activate_url = wp_nonce_url(
            admin_url('plugins.php?action=activate&plugin=' . $plugin_key),
            'activate-plugin_' . $plugin_key
        );
        echo '<a href="' . esc_url($activate_url) . '" class="button button-primary">Activate Plugin</a><br>';
    }
} else {
    echo "❌ Plugin NOT detected by WordPress<br>";
    echo "Looking for variations...<br>";
    
    // Check for any zippicks-vibes related entries
    $found_vibes = false;
    foreach ($all_plugins as $key => $plugin) {
        if (strpos($key, 'vibes') !== false || strpos(strtolower($plugin['Name']), 'vibe') !== false) {
            echo "Found related plugin: <code>" . esc_html($key) . "</code> - " . esc_html($plugin['Name']) . "<br>";
            $found_vibes = true;
        }
    }
    
    if (!$found_vibes) {
        echo "No vibes-related plugins found at all.<br>";
    }
}

// 6. File permissions
echo "<h2>6. File Permissions</h2>";
$perms = fileperms($plugin_file);
echo "File permissions: " . substr(sprintf('%o', $perms), -4) . "<br>";
echo "File owner: " . posix_getpwuid(fileowner($plugin_file))['name'] . "<br>";
echo "File group: " . posix_getgrgid(filegroup($plugin_file))['name'] . "<br>";

// 7. Check parent directory
echo "<h2>7. Parent Directory Check</h2>";
$parent_dir = dirname($plugin_file);
$grandparent_dir = dirname($parent_dir);
echo "Parent directory: <code>" . esc_html($parent_dir) . "</code><br>";
echo "Parent exists: " . (is_dir($parent_dir) ? '✅ YES' : '❌ NO') . "<br>";
echo "Parent readable: " . (is_readable($parent_dir) ? '✅ YES' : '❌ NO') . "<br>";
echo "Grandparent: <code>" . esc_html($grandparent_dir) . "</code><br>";

// 8. WordPress cache
echo "<h2>8. WordPress Cache Status</h2>";
if (wp_cache_flush()) {
    echo "✅ Cache flushed successfully<br>";
} else {
    echo "⚠️ Could not flush cache<br>";
}

// Clear plugin cache
wp_clean_plugins_cache();
echo "✅ Plugin cache cleaned<br>";

// 9. Direct plugin loading test
echo "<h2>9. Direct Plugin Loading Test</h2>";
ob_start();
$error_handler_set = false;

try {
    // Set custom error handler
    $old_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
        echo "⚠️ Error: $errstr in $errfile on line $errline<br>";
        return true;
    });
    $error_handler_set = true;
    
    // Try to include the plugin file
    include_once $plugin_file;
    echo "✅ Plugin file loaded without fatal errors<br>";
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
} catch (ParseError $e) {
    echo "❌ Parse Error: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "<br>";
} finally {
    if ($error_handler_set) {
        restore_error_handler();
    }
}

$output = ob_get_clean();
if (!empty($output)) {
    echo "Output from loading plugin:<br><pre>" . esc_html($output) . "</pre>";
}

// 10. Summary and recommendations
echo "<h2>10. Summary and Recommendations</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";

if (isset($all_plugins[$plugin_key])) {
    echo "<strong>✅ Good news!</strong> The plugin is detected by WordPress.<br>";
    if (!is_plugin_active($plugin_key)) {
        echo "You just need to activate it from the plugins page.<br>";
    }
} else {
    echo "<strong>❌ Issue:</strong> WordPress cannot detect the plugin.<br>";
    echo "<strong>Likely causes:</strong><br>";
    echo "<ul>";
    echo "<li>PHP parse error in the plugin file</li>";
    echo "<li>Invalid plugin header format</li>";
    echo "<li>File permissions issue</li>";
    echo "<li>WordPress cache needs clearing</li>";
    echo "</ul>";
    
    echo "<strong>Try these solutions:</strong><br>";
    echo "<ol>";
    echo "<li>Clear all caches and refresh the plugins page</li>";
    echo "<li>Check the plugin file for syntax errors</li>";
    echo "<li>Ensure file permissions are 644 or 664</li>";
    echo "<li>Try deactivating other plugins to check for conflicts</li>";
    echo "</ol>";
}

echo "</div>";

// Links
echo "<hr>";
echo '<p>';
echo '<a href="' . admin_url('plugins.php') . '" class="button button-primary">Go to Plugins Page</a> ';
echo '<a href="' . admin_url('plugins.php?plugin_status=inactive') . '" class="button">View Inactive Plugins</a> ';
echo '<a href="?refresh=1" class="button">Refresh This Page</a>';
echo '</p>';