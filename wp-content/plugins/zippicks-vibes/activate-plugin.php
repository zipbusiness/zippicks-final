<?php
/**
 * Manual Plugin Activation Script
 * 
 * This script manually triggers the plugin activation process
 * Use this if automatic activation fails
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Check permissions
if (!current_user_can('activate_plugins')) {
    wp_die('You do not have permission to activate plugins.');
}

// Define plugin constants if not already defined
if (!defined('ZIPPICKS_VIBES_VERSION')) {
    define('ZIPPICKS_VIBES_VERSION', '2.0.0');
}
if (!defined('ZIPPICKS_VIBES_DIR')) {
    define('ZIPPICKS_VIBES_DIR', plugin_dir_path(__FILE__));
}
if (!defined('ZIPPICKS_VIBES_FILE')) {
    define('ZIPPICKS_VIBES_FILE', ZIPPICKS_VIBES_DIR . 'zippicks-vibes.php');
}

// Log activation time
update_option('zippicks_vibes_manual_activation_attempt', time());
error_log('ZipPicks Vibes: Manual activation started at ' . date('Y-m-d H:i:s'));

// Add admin notices for activation results
add_action('admin_notices', function() {
    if (get_transient('zippicks_vibes_activation_success')) {
        echo '<div class="notice notice-success is-dismissible"><p>ZipPicks Vibes activated successfully!</p></div>';
        delete_transient('zippicks_vibes_activation_success');
    }
    if (get_transient('zippicks_vibes_activation_partial')) {
        echo '<div class="notice notice-warning is-dismissible"><p>ZipPicks Vibes activated but some tables failed to create. Please check the logs.</p></div>';
        delete_transient('zippicks_vibes_activation_partial');
    }
    if (get_transient('zippicks_vibes_activation_failed')) {
        echo '<div class="notice notice-error is-dismissible"><p>ZipPicks Vibes activation failed. Database tables could not be created.</p></div>';
        delete_transient('zippicks_vibes_activation_failed');
    }
    if ($error = get_transient('zippicks_vibes_activation_error')) {
        echo '<div class="notice notice-error is-dismissible"><p>ZipPicks Vibes error: ' . esc_html($error) . '</p></div>';
        delete_transient('zippicks_vibes_activation_error');
    }
});

// Output header
echo "<h1>ZipPicks Vibes - Manual Activation</h1>";
echo "<p>Running activation process...</p>";

// Check multisite status and get proper prefix
global $wpdb;
$is_multisite = is_multisite();
$blog_id = get_current_blog_id();

// Dynamic prefix detection for multisite
if ($is_multisite) {
    $table_prefix = $wpdb->get_blog_prefix($blog_id);
} else {
    $table_prefix = $wpdb->prefix;
}

echo "<h2>Environment Information</h2>";
echo "<ul>";
echo "<li><strong>WordPress Version:</strong> " . get_bloginfo('version') . "</li>";
echo "<li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>";
echo "<li><strong>Table Prefix:</strong> " . esc_html($table_prefix) . "</li>";
echo "<li><strong>Site URL:</strong> " . get_site_url() . "</li>";
echo "<li><strong>Plugin Directory:</strong> " . esc_html(ZIPPICKS_VIBES_DIR) . "</li>";

if ($is_multisite) {
    echo "<li><strong>Multisite:</strong> Yes</li>";
    echo "<li><strong>Blog ID:</strong> " . $blog_id . "</li>";
    echo "<li><strong>Network ID:</strong> " . get_current_network_id() . "</li>";
    
    // Check if network activated
    if (is_plugin_active_for_network('zippicks-vibes/zippicks-vibes.php')) {
        echo "<li><strong>Network Activation:</strong> Yes</li>";
    } else {
        echo "<li><strong>Network Activation:</strong> No</li>";
    }
    
    // Show site details
    $site = get_site($blog_id);
    if ($site) {
        echo "<li><strong>Site Domain:</strong> " . esc_html($site->domain) . "</li>";
        echo "<li><strong>Site Path:</strong> " . esc_html($site->path) . "</li>";
    }
} else {
    echo "<li><strong>Multisite:</strong> No</li>";
}

// Foundation status
if (function_exists('zippicks')) {
    echo "<li><strong>Foundation:</strong> Active</li>";
    if (zippicks()->has('logger')) {
        echo "<li><strong>Logger Service:</strong> Available</li>";
    }
} else {
    echo "<li><strong>Foundation:</strong> Not detected (plugin will run in standalone mode)</li>";
}

echo "</ul>";

// Load the plugin file
require_once ZIPPICKS_VIBES_FILE;

// Get plugin instance
$plugin = ZipPicksVibes\VibesPlugin::get_instance();

echo "<h2>Step 1: Creating Database Tables</h2>";

// Manually run the database installer
require_once ZIPPICKS_VIBES_DIR . 'src/Database/Installer.php';

try {
    // Create tables
    ZipPicksVibes\Database\Installer::install();
    
    // Check if tables exist
    if (ZipPicksVibes\Database\Installer::tables_exist()) {
        echo "✅ Database tables created successfully!<br>";
        error_log('ZipPicks Vibes: Database tables created successfully');
        
        // Log to Foundation if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('Manual activation: Database tables created', [
                'blog_id' => $blog_id,
                'prefix' => $table_prefix
            ]);
        }
        
        // Add admin notice for success
        set_transient('zippicks_vibes_activation_success', true, 30);
        
        $tables = [
            'zippicks_vibes',
            'zippicks_vibe_categories',
            'zippicks_vibe_category_assignments',
            'zippicks_waitlist',
            'zippicks_scrape_log',
            'zippicks_security_log',
            'zippicks_rate_limit_log',
            'zippicks_security_events',
            'zippicks_audit_log',
            'zippicks_performance_metrics'
        ];
        
        echo "<h3>Table Status:</h3>";
        echo "<table style='border-collapse: collapse; margin: 20px 0;'>";
        echo "<tr><th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Table Name</th><th style='border: 1px solid #ddd; padding: 8px;'>Status</th><th style='border: 1px solid #ddd; padding: 8px;'>Rows</th></tr>";
        
        $all_tables_created = true;
        foreach ($tables as $table) {
            $full_name = $table_prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_name'") === $full_name;
            
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . esc_html($full_name) . "</td>";
            
            if ($exists) {
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $full_name");
                echo "<td style='border: 1px solid #ddd; padding: 8px; color: green;'>✅ Created</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . number_format($row_count) . "</td>";
            } else {
                echo "<td style='border: 1px solid #ddd; padding: 8px; color: red;'>❌ Failed</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>-</td>";
                error_log("ZipPicks Vibes: Failed to create table $full_name");
                $all_tables_created = false;
            }
            echo "</tr>";
        }
        echo "</table>";
        
        if (!$all_tables_created) {
            // Add admin notice for partial failure
            set_transient('zippicks_vibes_activation_partial', true, 30);
        }
    } else {
        echo "❌ Failed to create some database tables<br>";
        echo "<p>Please use <a href='create-tables.php'>create-tables.php</a> to manually create them.</p>";
        error_log('ZipPicks Vibes: Failed to create database tables');
        
        // Add admin notice for failure
        set_transient('zippicks_vibes_activation_failed', true, 30);
    }
} catch (Exception $e) {
    echo "❌ Error creating tables: " . esc_html($e->getMessage()) . "<br>";
    error_log('ZipPicks Vibes: Database creation error - ' . $e->getMessage());
    
    // Add detailed error to admin notices
    set_transient('zippicks_vibes_activation_error', $e->getMessage(), 30);
}

echo "<h2>Step 2: Running Full Activation</h2>";

try {
    // Run the activation method
    $plugin->activate();
    echo "✅ Plugin activation completed!<br>";
    error_log('ZipPicks Vibes: Plugin activation completed');
    
    // Log successful activation with details
    update_option('zippicks_vibes_last_activation', time());
    update_option('zippicks_vibes_activation_details', [
        'timestamp' => time(),
        'wp_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'multisite' => $is_multisite,
        'blog_id' => $blog_id,
        'table_prefix' => $table_prefix
    ]);
} catch (Exception $e) {
    echo "❌ Activation error: " . esc_html($e->getMessage()) . "<br>";
    error_log('ZipPicks Vibes: Activation error - ' . $e->getMessage());
}

echo "<h2>Step 3: Activating Plugin in WordPress</h2>";

// Check if plugin is active
$plugin_file = 'zippicks-vibes/zippicks-vibes.php';

// Handle multisite activation differently
if ($is_multisite) {
    if (current_user_can('manage_network_plugins')) {
        // Network admin can choose network activation
        if (!is_plugin_active_for_network($plugin_file) && !is_plugin_active($plugin_file)) {
            echo "<p>Choose activation type:</p>";
            echo "<p><a href='" . network_admin_url('plugins.php') . "' class='button'>Network Activate</a> ";
            echo "<a href='" . admin_url('plugins.php') . "' class='button'>Site Activate</a></p>";
        } else {
            echo "✅ Plugin is already active<br>";
        }
    } else {
        // Regular admin can only activate for current site
        if (!is_plugin_active($plugin_file)) {
            $result = activate_plugin($plugin_file);
            
            if (is_wp_error($result)) {
                echo "❌ Failed to activate plugin: " . esc_html($result->get_error_message()) . "<br>";
                error_log('ZipPicks Vibes: Activation failed - ' . $result->get_error_message());
            } else {
                echo "✅ Plugin activated for this site!<br>";
                error_log('ZipPicks Vibes: Plugin activated for site ' . $blog_id);
            }
        } else {
            echo "✅ Plugin is already active for this site<br>";
        }
    }
} else {
    // Single site activation
    if (!is_plugin_active($plugin_file)) {
        $result = activate_plugin($plugin_file);
        
        if (is_wp_error($result)) {
            echo "❌ Failed to activate plugin: " . esc_html($result->get_error_message()) . "<br>";
            error_log('ZipPicks Vibes: Activation failed - ' . $result->get_error_message());
        } else {
            echo "✅ Plugin activated in WordPress!<br>";
            error_log('ZipPicks Vibes: Plugin activated successfully');
        }
    } else {
        echo "✅ Plugin is already active in WordPress<br>";
    }
}

echo "<h2>Step 4: Final Verification</h2>";

// Check options
$version = get_option('zippicks_vibes_version');
$installed = get_option('zippicks_vibes_installed');
$activation_time = get_option('zippicks_vibes_activation_time');
$manual_activation = get_option('zippicks_vibes_manual_activation_attempt');
$last_activation = get_option('zippicks_vibes_last_activation');
$activation_details = get_option('zippicks_vibes_activation_details');

if ($version && $installed) {
    echo "✅ Plugin options set correctly<br>";
    echo "<table style='margin-left: 20px; border-collapse: collapse;'>";
    echo "<tr><td style='padding: 5px;'><strong>Version:</strong></td><td style='padding: 5px;'>" . esc_html($version) . "</td></tr>";
    echo "<tr><td style='padding: 5px;'><strong>Installed:</strong></td><td style='padding: 5px;'>" . date('Y-m-d H:i:s', $installed) . "</td></tr>";
    if ($activation_time) {
        echo "<tr><td style='padding: 5px;'><strong>First Activation:</strong></td><td style='padding: 5px;'>" . date('Y-m-d H:i:s', $activation_time) . "</td></tr>";
    }
    if ($last_activation) {
        echo "<tr><td style='padding: 5px;'><strong>Last Activation:</strong></td><td style='padding: 5px;'>" . date('Y-m-d H:i:s', $last_activation) . "</td></tr>";
    }
    if ($manual_activation) {
        echo "<tr><td style='padding: 5px;'><strong>Manual Activation:</strong></td><td style='padding: 5px;'>" . date('Y-m-d H:i:s', $manual_activation) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "⚠️ Plugin options may not be set correctly<br>";
    error_log('ZipPicks Vibes: Plugin options not set correctly');
}

// Check if services are registered
if (function_exists('zippicks')) {
    echo "<h3>Foundation Services:</h3>";
    $services = ['vibes.service', 'vibes.repository', 'vibes.admin', 'vibes.api', 'vibes.cache', 'vibes.health_check'];
    echo "<ul>";
    foreach ($services as $service) {
        if (zippicks()->has($service)) {
            echo "<li>✅ $service registered</li>";
        } else {
            echo "<li>❌ $service NOT registered</li>";
            error_log("ZipPicks Vibes: Service $service not registered");
        }
    }
    echo "</ul>";
} else {
    echo "<p>⚠️ Foundation not detected - plugin will run in standalone mode</p>";
}

// Additional multisite information
if ($is_multisite) {
    echo "<h3>Multisite Table Configuration:</h3>";
    echo "<ul>";
    
    // Check if tables are global or per-site
    $global_tables = [
        'zippicks_vibes',
        'zippicks_vibe_categories',
        'zippicks_vibe_category_assignments'
    ];
    
    foreach ($global_tables as $table) {
        $is_global = isset($wpdb->$table) && $wpdb->$table === $wpdb->base_prefix . $table;
        echo "<li><strong>$table:</strong> " . ($is_global ? "Global (shared)" : "Per-site") . "</li>";
    }
    echo "</ul>";
    
    // Log multisite configuration
    error_log(sprintf(
        'ZipPicks Vibes: Multisite configuration - Blog ID: %d, Network ID: %d, Table Prefix: %s',
        $blog_id,
        get_current_network_id(),
        $table_prefix
    ));
}

// Performance metrics
$end_time = microtime(true);
$start_time = get_option('zippicks_vibes_manual_activation_attempt');
if ($start_time) {
    $duration = round($end_time - $start_time, 2);
    echo "<p><strong>Activation Duration:</strong> {$duration} seconds</p>";
}

// Log final status
error_log('ZipPicks Vibes: Manual activation completed at ' . date('Y-m-d H:i:s'));

echo "<hr>";
echo "<h2>Activation Complete!</h2>";
echo "<p><a href='" . admin_url('admin.php?page=zippicks-vibes') . "' class='button button-primary'>Go to Vibes Admin</a> ";
echo "<a href='verify-tables.php' class='button'>Verify Tables</a> ";
echo "<a href='test-plugin-status.php' class='button'>Check Plugin Status</a></p>";

// Add link to network admin for multisite
if ($is_multisite && current_user_can('manage_network')) {
    echo "<p><a href='" . network_admin_url('admin.php?page=zippicks-vibes') . "' class='button'>Network Admin</a></p>";
}

// Provide debug information download
echo "<h3>Debug Information</h3>";
echo "<details>";
echo "<summary>Click to view debug information</summary>";
echo "<pre style='background: #f5f5f5; padding: 10px; overflow: auto;'>";
$debug_info = [
    'wordpress' => [
        'version' => get_bloginfo('version'),
        'multisite' => $is_multisite,
        'blog_id' => $blog_id,
        'site_url' => get_site_url(),
        'home_url' => get_home_url(),
        'table_prefix' => $table_prefix
    ],
    'php' => [
        'version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize')
    ],
    'plugin' => [
        'version' => ZIPPICKS_VIBES_VERSION,
        'directory' => ZIPPICKS_VIBES_DIR,
        'foundation_active' => function_exists('zippicks')
    ],
    'database' => [
        'mysql_version' => $wpdb->db_version(),
        'charset' => $wpdb->charset,
        'collate' => $wpdb->collate
    ]
];
echo htmlspecialchars(json_encode($debug_info, JSON_PRETTY_PRINT));
echo "</pre>";
echo "</details>";