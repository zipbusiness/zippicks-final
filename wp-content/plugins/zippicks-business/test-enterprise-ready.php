<?php
/**
 * ZipPicks Business Enterprise Validation Script
 * 
 * Run this script to validate that the plugin is enterprise-ready
 * and all functionality is working correctly.
 */

// Load WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die("Error: Cannot find wp-load.php at expected path: $wp_load_path\n");
}
require_once($wp_load_path);

// Colors for output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

echo "\n{$yellow}=== ZipPicks Business Enterprise Validation ==={$reset}\n\n";

// Test results array
$results = array();
$has_errors = false;

// 1. Check WordPress environment
echo "1. Checking WordPress environment...\n";
if (defined('ABSPATH')) {
    $results[] = "{$green}✓{$reset} WordPress loaded successfully";
} else {
    $results[] = "{$red}✗{$reset} WordPress not loaded";
    $has_errors = true;
}

// 2. Check plugin activation
echo "2. Checking plugin activation...\n";
if (is_plugin_active('zippicks-business/zippicks-business.php')) {
    $results[] = "{$green}✓{$reset} Plugin is active";
} else {
    $results[] = "{$red}✗{$reset} Plugin is not active";
    $has_errors = true;
}

// 3. Check PHP version
echo "3. Checking PHP version...\n";
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    $results[] = "{$green}✓{$reset} PHP version " . PHP_VERSION . " meets requirements";
} else {
    $results[] = "{$red}✗{$reset} PHP version " . PHP_VERSION . " is below 8.0 requirement";
    $has_errors = true;
}

// 4. Check plugin constants
echo "4. Checking plugin constants...\n";
$required_constants = array(
    'ZIPPICKS_BUSINESS_VERSION',
    'ZIPPICKS_BUSINESS_PLUGIN_DIR',
    'ZIPPICKS_BUSINESS_PLUGIN_URL',
    'ZIPPICKS_BUSINESS_PLUGIN_BASENAME',
    'ZIPPICKS_BUSINESS_ENV',
    'ZIPPICKS_BUSINESS_DEBUG',
    'ZIPPICKS_BUSINESS_RATE_LIMIT_WINDOW',
    'ZIPPICKS_BUSINESS_RATE_LIMIT_REQUESTS'
);

foreach ($required_constants as $constant) {
    if (defined($constant)) {
        $results[] = "{$green}✓{$reset} Constant $constant is defined";
    } else {
        $results[] = "{$red}✗{$reset} Constant $constant is not defined";
        $has_errors = true;
    }
}

// Check if nonce key is defined after init
add_action('init', function() use (&$results, $green, $red, &$has_errors) {
    if (defined('ZIPPICKS_BUSINESS_NONCE_KEY')) {
        $results[] = "{$green}✓{$reset} ZIPPICKS_BUSINESS_NONCE_KEY defined after init";
    } else {
        $results[] = "{$red}✗{$reset} ZIPPICKS_BUSINESS_NONCE_KEY not defined after init";
        $has_errors = true;
    }
}, 100);

// 5. Check required classes
echo "5. Checking required classes...\n";
$required_classes = array(
    'ZipPicks_Business',
    'ZipPicks_Business_Database',
    'ZipPicks_Business_Installer',
    'ZipPicks_Business_Post_Types',
    'ZipPicks_Business_Manager',
    'ZipPicks_Business_Admin',
    'ZipPicks_Business_Activator',
    'ZipPicks_Business_Deactivator'
);

foreach ($required_classes as $class) {
    if (class_exists($class)) {
        $results[] = "{$green}✓{$reset} Class $class exists";
    } else {
        $results[] = "{$red}✗{$reset} Class $class not found";
        $has_errors = true;
    }
}

// 6. Check database tables
echo "6. Checking database tables...\n";
global $wpdb;

$tables = array(
    'zippicks_business_analytics' => ZipPicks_Business_Database::get_analytics_table(),
    'zippicks_business_monetization' => ZipPicks_Business_Database::get_monetization_table(),
    'zippicks_business_verification' => ZipPicks_Business_Database::get_verification_table(),
    'zippicks_scrape_log' => ZipPicks_Business_Database::get_scrape_log_table()
);

foreach ($tables as $name => $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    if ($exists) {
        $results[] = "{$green}✓{$reset} Table $name exists";
    } else {
        $results[] = "{$red}✗{$reset} Table $name missing";
        $has_errors = true;
    }
}

// 7. Check post type registration
echo "7. Checking post type registration...\n";
if (post_type_exists('zippicks_business')) {
    $results[] = "{$green}✓{$reset} Post type 'zippicks_business' is registered";
} else {
    $results[] = "{$red}✗{$reset} Post type 'zippicks_business' not registered";
    $has_errors = true;
}

// 8. Check Foundation integration
echo "8. Checking Foundation integration...\n";
if (function_exists('zippicks')) {
    $results[] = "{$green}✓{$reset} Foundation is available";
    
    // Check if business manager is registered
    if (zippicks()->has('business.manager')) {
        $results[] = "{$green}✓{$reset} Business manager registered with Foundation";
    } else {
        $results[] = "{$yellow}⚠{$reset} Business manager not registered (graceful degradation)";
    }
} else {
    $results[] = "{$yellow}⚠{$reset} Foundation not available (running in standalone mode)";
}

// 9. Check REST API endpoints
echo "9. Checking REST API endpoints...\n";
$rest_server = rest_get_server();
$routes = $rest_server->get_routes();

$required_endpoints = array(
    '/zippicks/v1/businesses',
    '/zippicks/v1/businesses/bulk-create',
    '/zippicks/v1/businesses/(?P<id>\d+)/track'
);

foreach ($required_endpoints as $endpoint) {
    $found = false;
    foreach ($routes as $route => $data) {
        if (preg_match('#^' . $endpoint . '$#', $route)) {
            $found = true;
            break;
        }
    }
    
    if ($found) {
        $results[] = "{$green}✓{$reset} REST endpoint $endpoint registered";
    } else {
        $results[] = "{$red}✗{$reset} REST endpoint $endpoint not found";
        $has_errors = true;
    }
}

// 10. Check anti-scraping implementation
echo "10. Checking anti-scraping implementation...\n";

// Check if scrape log table has proper indexes
$indexes = $wpdb->get_results("SHOW INDEX FROM " . ZipPicks_Business_Database::get_scrape_log_table());
$required_indexes = array('ip_address', 'request_path', 'timestamp');
$found_indexes = array_column($indexes, 'Column_name');

foreach ($required_indexes as $index) {
    if (in_array($index, $found_indexes)) {
        $results[] = "{$green}✓{$reset} Scrape log index on '$index' exists";
    } else {
        $results[] = "{$yellow}⚠{$reset} Scrape log index on '$index' missing (performance impact)";
    }
}

// 11. Check capabilities
echo "11. Checking user capabilities...\n";
$admin_role = get_role('administrator');
$required_caps = array(
    'manage_businesses',
    'edit_businesses',
    'publish_businesses',
    'verify_businesses',
    'manage_business_monetization',
    'view_business_analytics'
);

foreach ($required_caps as $cap) {
    if ($admin_role && isset($admin_role->capabilities[$cap])) {
        $results[] = "{$green}✓{$reset} Capability '$cap' exists";
    } else {
        $results[] = "{$yellow}⚠{$reset} Capability '$cap' not found";
    }
}

// 12. Check options
echo "12. Checking plugin options...\n";
$required_options = array(
    'zippicks_business_db_version',
    'zippicks_business_tiers'
);

foreach ($required_options as $option) {
    if (get_option($option) !== false) {
        $results[] = "{$green}✓{$reset} Option '$option' exists";
    } else {
        $results[] = "{$yellow}⚠{$reset} Option '$option' not found";
    }
}

// 13. Test database operations
echo "13. Testing database operations...\n";
try {
    // Test analytics tracking
    $test_id = ZipPicks_Business_Database::track_event(1, 'test', 'validation');
    if ($test_id) {
        $results[] = "{$green}✓{$reset} Analytics tracking works";
        // Clean up test data
        $wpdb->delete(ZipPicks_Business_Database::get_analytics_table(), array('id' => $test_id));
    } else {
        $results[] = "{$red}✗{$reset} Analytics tracking failed";
        $has_errors = true;
    }
} catch (Exception $e) {
    $results[] = "{$red}✗{$reset} Database operation error: " . $e->getMessage();
    $has_errors = true;
}

// 14. Check security measures
echo "14. Checking security measures...\n";

// Test nonce creation (after init)
do_action('init');

if (function_exists('wp_create_nonce')) {
    $test_nonce = wp_create_nonce('zippicks_business_test');
    if ($test_nonce) {
        $results[] = "{$green}✓{$reset} Nonce creation works";
    } else {
        $results[] = "{$red}✗{$reset} Nonce creation failed";
        $has_errors = true;
    }
}

// 15. Check error handling
echo "15. Checking error handling...\n";
if (defined('ZIPPICKS_BUSINESS_DEBUG')) {
    $results[] = "{$green}✓{$reset} Debug mode configured";
} else {
    $results[] = "{$yellow}⚠{$reset} Debug mode not configured";
}

// Display results
echo "\n{$yellow}=== Validation Results ==={$reset}\n\n";
foreach ($results as $result) {
    echo $result . "\n";
}

// Summary
echo "\n{$yellow}=== Summary ==={$reset}\n";
$total_tests = count($results);
$passed_tests = substr_count(implode('', $results), '✓');
$failed_tests = substr_count(implode('', $results), '✗');
$warning_tests = substr_count(implode('', $results), '⚠');

echo "Total tests: $total_tests\n";
echo "{$green}Passed: $passed_tests{$reset}\n";
echo "{$red}Failed: $failed_tests{$reset}\n";
echo "{$yellow}Warnings: $warning_tests{$reset}\n";

if ($has_errors) {
    echo "\n{$red}❌ Plugin has critical issues that need to be fixed!{$reset}\n";
    exit(1);
} else {
    echo "\n{$green}✅ Plugin is enterprise-ready!{$reset}\n";
    exit(0);
}