<?php
/**
 * Diagnostic tool for blank page issue on /vibes/
 * Access this directly at: /wp-content/plugins/zippicks-vibes/diagnose-blank-page.php
 */

// Load WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die('Could not find wp-load.php');
}
require_once($wp_load_path);

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>ZipPicks Vibes - Diagnostic Tool</title>
    <style>
        body { font-family: -apple-system, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .warning { background: #fff3cd; color: #856404; }
        .info { background: #d1ecf1; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <h1>ZipPicks Vibes - Diagnostic Tool</h1>
    
    <?php
    // Check plugin activation
    echo '<h2>1. Plugin Status</h2>';
    if (is_plugin_active('zippicks-vibes/zippicks-vibes.php')) {
        echo '<div class="status success">✓ ZipPicks Vibes plugin is active</div>';
    } else {
        echo '<div class="status error">✗ ZipPicks Vibes plugin is NOT active</div>';
    }
    
    // Check Foundation
    echo '<h2>2. Foundation Status</h2>';
    if (function_exists('zippicks')) {
        echo '<div class="status success">✓ Foundation is available</div>';
        
        // Check vibes service
        if (zippicks()->has('vibes.service')) {
            echo '<div class="status success">✓ Vibes service is registered</div>';
            
            try {
                $service = zippicks()->get('vibes.service');
                echo '<div class="status success">✓ Vibes service is accessible</div>';
            } catch (Exception $e) {
                echo '<div class="status error">✗ Error accessing vibes service: ' . esc_html($e->getMessage()) . '</div>';
            }
        } else {
            echo '<div class="status warning">⚠ Vibes service is NOT registered</div>';
        }
    } else {
        echo '<div class="status error">✗ Foundation is NOT available</div>';
    }
    
    // Check database tables
    echo '<h2>3. Database Tables</h2>';
    global $wpdb;
    $tables = [
        'vibes' => $wpdb->prefix . 'zippicks_vibes_vibes',
        'categories' => $wpdb->prefix . 'zippicks_vibes_categories',
        'vibe_categories' => $wpdb->prefix . 'zippicks_vibes_vibe_categories'
    ];
    
    foreach ($tables as $name => $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
        if ($exists) {
            echo '<div class="status success">✓ Table exists: ' . esc_html($table) . '</div>';
            
            // Count records
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            echo '<div class="status info">ℹ Records in ' . esc_html($name) . ': ' . intval($count) . '</div>';
        } else {
            echo '<div class="status error">✗ Table missing: ' . esc_html($table) . '</div>';
        }
    }
    
    // Check rewrite rules
    echo '<h2>4. Rewrite Rules</h2>';
    global $wp_rewrite;
    $rules = $wp_rewrite->wp_rewrite_rules();
    $vibe_rules = array_filter($rules, function($rule) {
        return strpos($rule, 'zippicks_vibes') !== false;
    });
    
    if (!empty($vibe_rules)) {
        echo '<div class="status success">✓ Vibes rewrite rules found</div>';
        echo '<pre>' . print_r($vibe_rules, true) . '</pre>';
    } else {
        echo '<div class="status error">✗ No vibes rewrite rules found</div>';
        echo '<div class="status info">ℹ Try flushing rewrite rules in Settings > Permalinks</div>';
    }
    
    // Check query vars
    echo '<h2>5. Query Variables</h2>';
    global $wp;
    $required_vars = ['zippicks_vibes', 'vibe_slug'];
    foreach ($required_vars as $var) {
        if (in_array($var, $wp->public_query_vars)) {
            echo '<div class="status success">✓ Query var registered: ' . esc_html($var) . '</div>';
        } else {
            echo '<div class="status error">✗ Query var NOT registered: ' . esc_html($var) . '</div>';
        }
    }
    
    // Check template files
    echo '<h2>6. Template Files</h2>';
    $templates = [
        'Archive Template' => ZIPPICKS_VIBES_DIR . 'templates/client-render/vibe-archive.php',
        'Debug Template' => ZIPPICKS_VIBES_DIR . 'templates/client-render/vibe-archive-debug.php',
        'List Partial' => ZIPPICKS_VIBES_DIR . 'templates/partials/vibe-list.php',
        'Item Partial' => ZIPPICKS_VIBES_DIR . 'templates/partials/vibe-item.php'
    ];
    
    foreach ($templates as $name => $path) {
        if (file_exists($path)) {
            echo '<div class="status success">✓ ' . esc_html($name) . ' exists</div>';
        } else {
            echo '<div class="status error">✗ ' . esc_html($name) . ' missing: ' . esc_html($path) . '</div>';
        }
    }
    
    // Check CSS files
    echo '<h2>7. CSS Files</h2>';
    $css_files = [
        'Frontend CSS' => ZIPPICKS_VIBES_DIR . 'assets/css/vibes-frontend.css',
        'Chrome Fix CSS' => ZIPPICKS_VIBES_DIR . 'assets/css/vibes-chrome-fix.css'
    ];
    
    foreach ($css_files as $name => $path) {
        if (file_exists($path)) {
            echo '<div class="status success">✓ ' . esc_html($name) . ' exists</div>';
        } else {
            echo '<div class="status warning">⚠ ' . esc_html($name) . ' missing</div>';
        }
    }
    
    // Browser detection
    echo '<h2>8. Browser Detection</h2>';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    echo '<div class="status info">ℹ User Agent: ' . esc_html($user_agent) . '</div>';
    
    if (strpos($user_agent, 'Chrome') !== false) {
        echo '<div class="status info">ℹ Chrome browser detected - special fixes will be applied</div>';
    }
    
    // Test vibes URL
    echo '<h2>9. Test URLs</h2>';
    $vibes_url = home_url('/vibes/');
    echo '<div class="status info">ℹ Vibes Archive URL: <a href="' . esc_url($vibes_url) . '" target="_blank">' . esc_html($vibes_url) . '</a></div>';
    
    // Add debug mode link
    $debug_url = add_query_arg('debug', '1', $vibes_url);
    echo '<div class="status info">ℹ Debug Mode URL: <a href="' . esc_url($debug_url) . '" target="_blank">' . esc_html($debug_url) . '</a></div>';
    
    // Error log check
    echo '<h2>10. Recent Error Log</h2>';
    $error_log = ini_get('error_log');
    if ($error_log && file_exists($error_log)) {
        $lines = array_slice(file($error_log), -10);
        $vibe_errors = array_filter($lines, function($line) {
            return strpos($line, 'ZipPicks Vibes') !== false;
        });
        
        if (!empty($vibe_errors)) {
            echo '<div class="status warning">⚠ Recent vibes-related errors found:</div>';
            echo '<pre>' . esc_html(implode('', $vibe_errors)) . '</pre>';
        } else {
            echo '<div class="status success">✓ No recent vibes-related errors in log</div>';
        }
    }
    
    // Recommendations
    echo '<h2>11. Recommendations</h2>';
    echo '<ol>';
    echo '<li>If rewrite rules are missing, go to Settings > Permalinks and click Save Changes</li>';
    echo '<li>If database tables are missing, deactivate and reactivate the plugin</li>';
    echo '<li>Enable WP_DEBUG in wp-config.php to see more detailed errors</li>';
    echo '<li>Try accessing the debug template URL to see if basic rendering works</li>';
    echo '<li>Check browser console for JavaScript errors</li>';
    echo '<li>Disable other plugins temporarily to rule out conflicts</li>';
    echo '</ol>';
    ?>
    
    <hr>
    <p><em>Generated on <?php echo date('Y-m-d H:i:s'); ?></em></p>
</body>
</html>