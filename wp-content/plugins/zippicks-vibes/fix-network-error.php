<?php
/**
 * ZipPicks Vibes - Network Error Fix
 * 
 * This script diagnoses and fixes the intermittent network error when saving vibes/categories
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}

global $wpdb;

echo "<h1>ZipPicks Vibes - Network Error Diagnostic & Fix</h1>";
echo "<pre>";

// 1. Check if tables exist
echo "=== CHECKING DATABASE TABLES ===\n";
$tables = [
    'zippicks_vibes',
    'zippicks_vibe_categories',
    'zippicks_vibe_category_assignments'
];

$missing_tables = [];
foreach ($tables as $table) {
    $full_table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
    
    if ($exists) {
        echo "✅ Table exists: $full_table_name\n";
        
        // Check if table has data
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
        echo "   Records: $count\n";
    } else {
        echo "❌ Table missing: $full_table_name\n";
        $missing_tables[] = $table;
    }
}

// 2. Check WordPress AJAX configuration
echo "\n=== CHECKING AJAX CONFIGURATION ===\n";
echo "AJAX URL: " . admin_url('admin-ajax.php') . "\n";
echo "Site URL: " . site_url() . "\n";
echo "Admin URL: " . admin_url() . "\n";

// Check if AJAX actions are registered
$ajax_actions = [
    'wp_ajax_zippicks_vibes_save',
    'wp_ajax_zippicks_vibes_save_category',
    'wp_ajax_zippicks_vibes_delete',
    'wp_ajax_zippicks_vibes_get'
];

echo "\n=== CHECKING AJAX ACTIONS ===\n";
global $wp_filter;
foreach ($ajax_actions as $action) {
    if (isset($wp_filter[$action])) {
        echo "✅ Action registered: $action\n";
    } else {
        echo "❌ Action missing: $action\n";
    }
}

// 3. Check PHP configuration
echo "\n=== CHECKING PHP CONFIGURATION ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "\n";
echo "Post Max Size: " . ini_get('post_max_size') . "\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";

// 4. Test database connection
echo "\n=== TESTING DATABASE CONNECTION ===\n";
try {
    $test_query = $wpdb->get_results("SELECT 1");
    if ($test_query) {
        echo "✅ Database connection is working\n";
    } else {
        echo "❌ Database connection test failed\n";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

// 5. Check for specific PHP 8.3 issues
echo "\n=== CHECKING PHP 8.3 COMPATIBILITY ===\n";
if (version_compare(PHP_VERSION, '8.3.0', '>=')) {
    echo "⚠️  Running PHP 8.3 - strict return types are enforced\n";
    echo "   This can cause fatal errors if methods don't return expected types\n";
} else {
    echo "✅ PHP version is below 8.3\n";
}

// 6. Create missing tables if needed
if (!empty($missing_tables)) {
    echo "\n=== CREATING MISSING TABLES ===\n";
    
    if (isset($_GET['create_tables']) && $_GET['create_tables'] === '1') {
        require_once dirname(__FILE__) . '/src/Database/Installer.php';
        
        try {
            \ZipPicksVibes\Database\Installer::install();
            echo "✅ Tables created successfully!\n";
        } catch (Exception $e) {
            echo "❌ Failed to create tables: " . $e->getMessage() . "\n";
        }
    } else {
        echo "⚠️  Missing tables detected. <a href='?create_tables=1'>Click here to create them</a>\n";
    }
}

// 7. Apply fixes
echo "\n=== APPLYING FIXES ===\n";

if (isset($_GET['apply_fixes']) && $_GET['apply_fixes'] === '1') {
    
    // Fix 1: Clear object cache
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
        echo "✅ Object cache cleared\n";
    }
    
    // Fix 2: Clear transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_zippicks_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_zippicks_%'");
    echo "✅ Transients cleared\n";
    
    // Fix 3: Reset rate limiting
    delete_transient('zippicks_vibes_rate_limit');
    echo "✅ Rate limiting reset\n";
    
    // Fix 4: Increase WordPress memory limit if needed
    if (defined('WP_MEMORY_LIMIT') && wp_convert_hr_to_bytes(WP_MEMORY_LIMIT) < 268435456) {
        @ini_set('memory_limit', '256M');
        echo "✅ Memory limit increased to 256M for this session\n";
    }
    
    echo "\n✅ All fixes applied!\n";
    echo "\nPlease test saving vibes/categories again.\n";
    
} else {
    echo "<a href='?apply_fixes=1'>Click here to apply fixes</a>\n";
}

// 8. Recommendations
echo "\n=== RECOMMENDATIONS ===\n";
echo "1. Ensure all database tables exist before using the plugin\n";
echo "2. Check your server error logs for PHP fatal errors\n";
echo "3. Consider increasing PHP timeout values in wp-config.php:\n";
echo "   define('WP_MEMORY_LIMIT', '256M');\n";
echo "   set_time_limit(300);\n";
echo "4. Monitor the browser console for JavaScript errors\n";
echo "5. Check if any security plugins are blocking AJAX requests\n";

// 9. Test AJAX endpoint directly
echo "\n=== TESTING AJAX ENDPOINT ===\n";
$test_url = admin_url('admin-ajax.php');
$test_response = wp_remote_get($test_url);

if (!is_wp_error($test_response)) {
    $response_code = wp_remote_retrieve_response_code($test_response);
    if ($response_code === 200) {
        echo "✅ AJAX endpoint is accessible (HTTP $response_code)\n";
    } else {
        echo "⚠️  AJAX endpoint returned HTTP $response_code\n";
    }
} else {
    echo "❌ Cannot reach AJAX endpoint: " . $test_response->get_error_message() . "\n";
}

echo "</pre>";

// Add JavaScript test
?>
<script>
jQuery(document).ready(function($) {
    $('#test-ajax').on('click', function(e) {
        e.preventDefault();
        
        console.log('Testing AJAX save...');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'zippicks_vibes_save',
                nonce: '<?php echo wp_create_nonce('zippicks_vibes_admin'); ?>',
                vibe_name: 'Test Vibe ' + Date.now(),
                vibe_slug: 'test-vibe-' + Date.now(),
                vibe_description: 'Test description',
                vibe_color: '#FF0000',
                vibe_status: '1'
            },
            timeout: 60000, // 60 second timeout
            success: function(response) {
                console.log('Success:', response);
                alert('AJAX test successful! Check console for details.');
            },
            error: function(xhr, status, error) {
                console.error('Error:', status, error);
                console.error('Response:', xhr.responseText);
                alert('AJAX test failed! Check console for details.');
            }
        });
    });
});
</script>

<h2>Interactive Tests</h2>
<button id="test-ajax" class="button button-primary">Test AJAX Save</button>
<p>Click the button above to test the AJAX save functionality. Check your browser console for detailed information.</p>

<style>
pre {
    background: #f5f5f5;
    padding: 20px;
    overflow-x: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
}
h1, h2 {
    color: #333;
}
.button {
    margin: 10px 0;
}
</style>