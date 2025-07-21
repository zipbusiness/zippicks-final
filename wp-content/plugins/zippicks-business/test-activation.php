<?php
/**
 * Plugin activation test script for ZipPicks Business
 *
 * Tests that the plugin can be activated without errors
 * and all dependencies are met.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If not in WordPress context, load it for testing
    $wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        die('WordPress environment not found');
    }
}

// Test results
$test_results = array();
$all_passed = true;

// Test 1: Check PHP version
$php_version = phpversion();
$php_required = '8.0';
$php_pass = version_compare($php_version, $php_required, '>=');
$test_results[] = array(
    'test' => 'PHP Version',
    'required' => $php_required . '+',
    'actual' => $php_version,
    'passed' => $php_pass
);
if (!$php_pass) $all_passed = false;

// Test 2: Check WordPress version
$wp_version = get_bloginfo('version');
$wp_required = '6.0';
$wp_pass = version_compare($wp_version, $wp_required, '>=');
$test_results[] = array(
    'test' => 'WordPress Version',
    'required' => $wp_required . '+',
    'actual' => $wp_version,
    'passed' => $wp_pass
);
if (!$wp_pass) $all_passed = false;

// Test 3: Check if Foundation is available
$foundation_available = function_exists('zippicks');
$test_results[] = array(
    'test' => 'ZipPicks Foundation',
    'required' => 'Optional (recommended)',
    'actual' => $foundation_available ? 'Available' : 'Not available',
    'passed' => true // Optional, so always passes
);

// Test 4: Check required files
$plugin_dir = dirname(__FILE__);
$required_files = array(
    'zippicks-business.php',
    'includes/class-activator.php',
    'includes/class-deactivator.php',
    'includes/class-business.php',
    'includes/class-database.php',
    'includes/class-installer.php',
    'includes/class-post-types.php',
    'includes/class-business-manager.php',
    'admin/class-admin.php'
);

$missing_files = array();
foreach ($required_files as $file) {
    if (!file_exists($plugin_dir . '/' . $file)) {
        $missing_files[] = $file;
    }
}

$files_pass = empty($missing_files);
$test_results[] = array(
    'test' => 'Required Files',
    'required' => 'All plugin files',
    'actual' => $files_pass ? 'All files present' : 'Missing: ' . implode(', ', $missing_files),
    'passed' => $files_pass
);
if (!$files_pass) $all_passed = false;

// Test 5: Check database permissions
$can_create_tables = true;
$db_error = '';
try {
    global $wpdb;
    $test_table = $wpdb->prefix . 'zippicks_test_' . time();
    $wpdb->query("CREATE TABLE IF NOT EXISTS $test_table (id INT PRIMARY KEY)");
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$test_table'") === $test_table;
    if ($table_exists) {
        $wpdb->query("DROP TABLE $test_table");
    } else {
        $can_create_tables = false;
        $db_error = 'Cannot create tables';
    }
} catch (Exception $e) {
    $can_create_tables = false;
    $db_error = $e->getMessage();
}

$test_results[] = array(
    'test' => 'Database Permissions',
    'required' => 'CREATE TABLE permission',
    'actual' => $can_create_tables ? 'Can create tables' : 'Error: ' . $db_error,
    'passed' => $can_create_tables
);
if (!$can_create_tables) $all_passed = false;

// Test 6: Check for conflicting plugins
$conflicting_plugins = array();
$active_plugins = get_option('active_plugins', array());

// Check for potential conflicts (none known currently)
$known_conflicts = array(
    // Add known conflicting plugins here if discovered
);

foreach ($known_conflicts as $plugin => $reason) {
    if (in_array($plugin, $active_plugins)) {
        $conflicting_plugins[$plugin] = $reason;
    }
}

$no_conflicts = empty($conflicting_plugins);
$test_results[] = array(
    'test' => 'Plugin Conflicts',
    'required' => 'No known conflicts',
    'actual' => $no_conflicts ? 'No conflicts detected' : 'Conflicts: ' . implode(', ', array_keys($conflicting_plugins)),
    'passed' => $no_conflicts
);
if (!$no_conflicts) $all_passed = false;

// Test 7: Memory limit
$memory_limit = ini_get('memory_limit');
$memory_bytes = wp_convert_hr_to_bytes($memory_limit);
$required_memory = '256M';
$required_bytes = wp_convert_hr_to_bytes($required_memory);
$memory_pass = $memory_bytes >= $required_bytes;

$test_results[] = array(
    'test' => 'PHP Memory Limit',
    'required' => $required_memory . '+',
    'actual' => $memory_limit,
    'passed' => $memory_pass
);
if (!$memory_pass) $all_passed = false;

// Test 8: Load test - Try loading main classes
$load_test_pass = true;
$load_errors = array();

try {
    // Test loading each class
    $classes_to_test = array(
        'includes/class-database.php' => 'ZipPicks_Business_Database',
        'includes/class-installer.php' => 'ZipPicks_Business_Installer',
        'includes/class-activator.php' => 'ZipPicks_Business_Activator',
        'includes/class-post-types.php' => 'ZipPicks_Business_Post_Types',
        'includes/class-business-manager.php' => 'ZipPicks_Business_Manager'
    );
    
    foreach ($classes_to_test as $file => $class_name) {
        if (!class_exists($class_name)) {
            $file_path = $plugin_dir . '/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                if (!class_exists($class_name)) {
                    $load_errors[] = "Class $class_name not found in $file";
                    $load_test_pass = false;
                }
            }
        }
    }
} catch (Exception $e) {
    $load_test_pass = false;
    $load_errors[] = $e->getMessage();
}

$test_results[] = array(
    'test' => 'Class Loading',
    'required' => 'All classes load without errors',
    'actual' => $load_test_pass ? 'All classes loaded successfully' : 'Errors: ' . implode(', ', $load_errors),
    'passed' => $load_test_pass
);
if (!$load_test_pass) $all_passed = false;

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>ZipPicks Business - Activation Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #23282d;
            margin-bottom: 30px;
        }
        .test-result {
            margin: 20px 0;
            padding: 15px;
            border-radius: 6px;
            display: grid;
            grid-template-columns: 30px 1fr auto;
            gap: 15px;
            align-items: center;
        }
        .test-result.pass {
            background: #f0f8f0;
            border: 1px solid #46b450;
        }
        .test-result.fail {
            background: #fef5f5;
            border: 1px solid #dc3232;
        }
        .icon {
            font-size: 24px;
            text-align: center;
        }
        .icon.pass {
            color: #46b450;
        }
        .icon.fail {
            color: #dc3232;
        }
        .test-name {
            font-weight: 600;
        }
        .test-details {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .test-status {
            text-align: right;
            font-size: 14px;
        }
        .summary {
            margin: 30px 0;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
        }
        .summary.pass {
            background: #f0f8f0;
            border: 1px solid #46b450;
            color: #46b450;
        }
        .summary.fail {
            background: #fef5f5;
            border: 1px solid #dc3232;
            color: #dc3232;
        }
        .summary h2 {
            margin: 0 0 10px;
        }
        .actions {
            text-align: center;
            margin-top: 30px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 5px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .button:hover {
            background: #005a87;
        }
        .button.secondary {
            background: #666;
        }
        .button.secondary:hover {
            background: #555;
        }
        code {
            background: #f0f0f0;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ZipPicks Business - Activation Test</h1>
        
        <?php foreach ($test_results as $result) : ?>
            <div class="test-result <?php echo $result['passed'] ? 'pass' : 'fail'; ?>">
                <div class="icon <?php echo $result['passed'] ? 'pass' : 'fail'; ?>">
                    <?php echo $result['passed'] ? '✓' : '✗'; ?>
                </div>
                <div>
                    <div class="test-name"><?php echo esc_html($result['test']); ?></div>
                    <div class="test-details">
                        Required: <?php echo esc_html($result['required']); ?><br>
                        Actual: <?php echo esc_html($result['actual']); ?>
                    </div>
                </div>
                <div class="test-status">
                    <?php echo $result['passed'] ? 'PASSED' : 'FAILED'; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="summary <?php echo $all_passed ? 'pass' : 'fail'; ?>">
            <?php if ($all_passed) : ?>
                <h2>✅ All Tests Passed!</h2>
                <p>The ZipPicks Business plugin is ready to be activated.</p>
            <?php else : ?>
                <h2>❌ Some Tests Failed</h2>
                <p>Please resolve the issues above before activating the plugin.</p>
            <?php endif; ?>
        </div>
        
        <div class="actions">
            <?php if ($all_passed && current_user_can('activate_plugins')) : ?>
                <a href="<?php echo wp_nonce_url(admin_url('plugins.php?action=activate&plugin=zippicks-business/zippicks-business.php'), 'activate-plugin_zippicks-business/zippicks-business.php'); ?>" class="button">
                    Activate Plugin
                </a>
            <?php endif; ?>
            <a href="<?php echo admin_url('plugins.php'); ?>" class="button secondary">
                Back to Plugins
            </a>
        </div>
    </div>
</body>
</html>