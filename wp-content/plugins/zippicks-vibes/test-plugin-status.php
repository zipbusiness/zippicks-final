<?php
/**
 * ZipPicks Vibes Plugin Status Checker
 * 
 * This script checks the current status of the plugin and identifies issues
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Output header
echo "<h1>ZipPicks Vibes Plugin Status Check</h1>";
echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>";

// Check if user can manage options
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

// 1. Check Foundation
echo "<h2>1. Foundation Check</h2>";
if (function_exists('zippicks')) {
    echo "✅ Foundation is available<br>";
    
    // Check specific services
    $services = [
        'logger' => 'Logger Service',
        'cache' => 'Cache Service',
        'database' => 'Database Service',
        'vibes.cache' => 'Vibes Cache Service',
        'vibes.repository' => 'Vibes Repository',
        'vibes.service' => 'Vibes Service',
        'vibes.admin' => 'Vibes Admin Controller',
        'vibes.api' => 'Vibes API Controller'
    ];
    
    echo "<h3>Service Registration Status:</h3>";
    echo "<ul>";
    foreach ($services as $key => $name) {
        if (zippicks()->has($key)) {
            echo "<li>✅ $name ($key) - Registered</li>";
        } else {
            echo "<li>❌ $name ($key) - NOT registered</li>";
        }
    }
    echo "</ul>";
} else {
    echo "❌ Foundation is NOT available<br>";
}

// 2. Check Database Tables
echo "<h2>2. Database Tables Check</h2>";
global $wpdb;

$tables = [
    'zippicks_vibes' => 'Main vibes table',
    'zippicks_vibe_categories' => 'Vibe categories',
    'zippicks_vibe_category_assignments' => 'Category assignments',
    'zippicks_waitlist' => 'Waitlist entries',
    'zippicks_scrape_log' => 'Scraping protection log',
    'zippicks_security_log' => 'Security event log',
    'zippicks_rate_limit_log' => 'Rate limiting log',
    'zippicks_security_events' => 'Security events enhanced',
    'zippicks_audit_log' => 'Audit log',
    'zippicks_performance_metrics' => 'Performance metrics'
];

$missing_tables = [];
echo "<ul>";
foreach ($tables as $table => $description) {
    $full_table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
        echo "<li>✅ $full_table_name ($description) - Exists with $count rows</li>";
    } else {
        echo "<li>❌ $full_table_name ($description) - MISSING</li>";
        $missing_tables[] = $full_table_name;
    }
}
echo "</ul>";

if (!empty($missing_tables)) {
    echo "<p><strong>Missing tables detected!</strong> <a href='create-tables.php' class='button button-primary'>Create Tables</a></p>";
}

// 3. Check Plugin Files
echo "<h2>3. Plugin Files Check</h2>";

$critical_files = [
    'zippicks-vibes.php' => 'Main plugin file',
    'src/ServiceProvider.php' => 'Service Provider',
    'src/Database/Installer.php' => 'Database Installer',
    'src/Repositories/VibeRepository.php' => 'Vibe Repository',
    'src/Services/VibeService.php' => 'Vibe Service',
    'src/Admin/VibesAdminController.php' => 'Admin Controller',
    'src/Api/VibesRestController.php' => 'REST API Controller',
    'src/Cache/CacheManager.php' => 'Cache Manager',
    'src/Models/Vibe.php' => 'Vibe Model',
    'templates/client-render/vibe-archive.php' => 'Archive Template',
    'templates/client-render/vibe-single.php' => 'Single Template'
];

echo "<ul>";
foreach ($critical_files as $file => $description) {
    $full_path = ZIPPICKS_VIBES_DIR . $file;
    if (file_exists($full_path)) {
        $size = filesize($full_path);
        echo "<li>✅ $file ($description) - " . number_format($size) . " bytes</li>";
    } else {
        echo "<li>❌ $file ($description) - MISSING</li>";
    }
}
echo "</ul>";

// 4. Check Plugin Status
echo "<h2>4. Plugin Status</h2>";
$active_plugins = get_option('active_plugins', []);
$plugin_file = 'zippicks-vibes/zippicks-vibes.php';

if (in_array($plugin_file, $active_plugins)) {
    echo "✅ Plugin is ACTIVE<br>";
} else {
    echo "❌ Plugin is NOT active<br>";
    echo "<p>Active plugins: " . implode(', ', $active_plugins) . "</p>";
}

// 5. Check Constants
echo "<h2>5. Plugin Constants</h2>";
$constants = [
    'ZIPPICKS_VIBES_VERSION',
    'ZIPPICKS_VIBES_DIR',
    'ZIPPICKS_VIBES_URL',
    'ZIPPICKS_VIBES_FILE',
    'ZIPPICKS_VIBES_BASENAME',
    'ZIPPICKS_VIBES_RATE_LIMIT_REQUESTS',
    'ZIPPICKS_VIBES_CACHE_TTL',
    'ZIPPICKS_VIBES_SESSION_REQUIRED'
];

echo "<ul>";
foreach ($constants as $constant) {
    if (defined($constant)) {
        $value = constant($constant);
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        echo "<li>✅ $constant = " . esc_html($value) . "</li>";
    } else {
        echo "<li>❌ $constant - NOT defined</li>";
    }
}
echo "</ul>";

// 6. Check Autoloader
echo "<h2>6. Autoloader Check</h2>";

// Test if classes can be loaded
$test_classes = [
    'ZipPicksVibes\\ServiceProvider',
    'ZipPicksVibes\\Database\\Installer',
    'ZipPicksVibes\\Repositories\\VibeRepository',
    'ZipPicksVibes\\Services\\VibeService',
    'ZipPicksVibes\\Admin\\VibesAdminController',
    'ZipPicksVibes\\Api\\VibesRestController',
    'ZipPicksVibes\\Cache\\CacheManager',
    'ZipPicksVibes\\Models\\Vibe'
];

echo "<ul>";
foreach ($test_classes as $class) {
    if (class_exists($class)) {
        echo "<li>✅ $class - Can be loaded</li>";
    } else {
        echo "<li>❌ $class - CANNOT be loaded</li>";
    }
}
echo "</ul>";

// 7. Test Service Instantiation
echo "<h2>7. Service Instantiation Test</h2>";

if (function_exists('zippicks')) {
    try {
        // Test getting services
        if (zippicks()->has('vibes.service')) {
            $service = zippicks()->get('vibes.service');
            echo "✅ Vibes Service instantiated successfully<br>";
            
            // Try to get categories
            try {
                $categories = $service->getAllCategories();
                echo "✅ getAllCategories() returned " . count($categories) . " categories<br>";
            } catch (Exception $e) {
                echo "❌ getAllCategories() failed: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "❌ Vibes Service not registered<br>";
        }
        
        if (zippicks()->has('vibes.repository')) {
            $repo = zippicks()->get('vibes.repository');
            echo "✅ Vibes Repository instantiated successfully<br>";
        } else {
            echo "❌ Vibes Repository not registered<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Service instantiation error: " . $e->getMessage() . "<br>";
    }
}

// 8. Check REST API Routes
echo "<h2>8. REST API Routes</h2>";
$rest_server = rest_get_server();
$routes = $rest_server->get_routes();

$vibe_routes = array_filter(array_keys($routes), function($route) {
    return strpos($route, '/zippicks/v2/vibes') !== false;
});

if (!empty($vibe_routes)) {
    echo "✅ Found " . count($vibe_routes) . " vibe routes:<br>";
    echo "<ul>";
    foreach ($vibe_routes as $route) {
        echo "<li>" . esc_html($route) . "</li>";
    }
    echo "</ul>";
} else {
    echo "❌ No vibe routes registered<br>";
}

// 9. Check Admin Menu
echo "<h2>9. Admin Menu Check</h2>";
global $submenu;

if (isset($submenu['zippicks-vibes'])) {
    echo "✅ Admin submenu exists<br>";
    echo "<ul>";
    foreach ($submenu['zippicks-vibes'] as $item) {
        echo "<li>" . esc_html($item[0]) . " - " . esc_html($item[2]) . "</li>";
    }
    echo "</ul>";
} else {
    echo "❌ Admin submenu not found<br>";
}

// 10. Error Log Check
echo "<h2>10. Recent Error Log Entries</h2>";
$error_log = WP_CONTENT_DIR . '/debug.log';
if (file_exists($error_log)) {
    $lines = file($error_log);
    $recent_errors = array_slice($lines, -20); // Last 20 lines
    
    $vibe_errors = array_filter($recent_errors, function($line) {
        return stripos($line, 'zippicks') !== false || stripos($line, 'vibe') !== false;
    });
    
    if (!empty($vibe_errors)) {
        echo "<pre style='background: #f0f0f0; padding: 10px; overflow-x: auto;'>";
        foreach ($vibe_errors as $error) {
            echo esc_html($error);
        }
        echo "</pre>";
    } else {
        echo "✅ No recent vibe-related errors found<br>";
    }
} else {
    echo "ℹ️ Debug log not found<br>";
}

echo "<hr>";
echo "<p><a href='admin.php?page=zippicks-vibes' class='button'>Go to Vibes Admin</a> ";
echo "<a href='create-tables.php' class='button'>Create Tables</a> ";
echo "<a href='verify-tables.php' class='button'>Verify Tables</a></p>";