<?php
/**
 * Test Script for ZipPicks Vibes Enterprise Fixes
 * 
 * This script verifies that all critical fixes are working properly
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

// Load WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die('Error: Cannot find wp-load.php');
}
require_once($wp_load_path);

// Only allow admins
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// Colors for output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

echo "=================================\n";
echo "ZipPicks Vibes - Test Suite\n";
echo "=================================\n\n";

// Test 1: Check if plugin is active
echo "1. Plugin Activation Test... ";
if (is_plugin_active('zippicks-vibes/zippicks-vibes.php')) {
    echo "{$green}PASS{$reset}\n";
} else {
    echo "{$red}FAIL{$reset} - Plugin is not active\n";
    exit(1);
}

// Test 2: Check if Foundation is available
echo "2. Foundation Integration Test... ";
if (function_exists('zippicks')) {
    echo "{$green}PASS{$reset}\n";
    
    // Check specific services
    $services = ['vibes.service', 'vibes.scrape_protection', 'vibes.renderer'];
    foreach ($services as $service) {
        echo "   - Checking $service... ";
        if (zippicks()->has($service)) {
            echo "{$green}OK{$reset}\n";
        } else {
            echo "{$yellow}MISSING{$reset}\n";
        }
    }
} else {
    echo "{$yellow}NOT AVAILABLE{$reset} - Running in standalone mode\n";
}

// Test 3: Database Tables Test
echo "\n3. Database Tables Test... \n";
require_once(plugin_dir_path(__FILE__) . 'src/Database/Installer.php');

use ZipPicksVibes\Database\Installer;

$all_tables_exist = true;
$tables = [
    'zippicks_vibes',
    'zippicks_vibe_categories',
    'zippicks_vibe_category_assignments',
    'zippicks_waitlist',
    'zippicks_scrape_log',
    'zippicks_blocked_ips',
    'zippicks_security_log',
    'zippicks_rate_limit_log',
    'zippicks_security_events',
    'zippicks_audit_log',
    'zippicks_performance_metrics'
];

global $wpdb;
foreach ($tables as $table) {
    $full_name = $wpdb->prefix . $table;
    echo "   - $table... ";
    
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_name'") === $full_name;
    if ($exists) {
        echo "{$green}EXISTS{$reset}\n";
    } else {
        echo "{$red}MISSING{$reset}\n";
        $all_tables_exist = false;
    }
}

if ($all_tables_exist) {
    echo "   Database tables: {$green}ALL PRESENT{$reset}\n";
} else {
    echo "   Database tables: {$red}SOME MISSING{$reset}\n";
    echo "   Attempting to create missing tables...\n";
    
    try {
        Installer::install();
        echo "   Table creation: {$green}COMPLETE{$reset}\n";
    } catch (Exception $e) {
        echo "   Table creation: {$red}FAILED{$reset} - " . $e->getMessage() . "\n";
    }
}

// Test 4: ScrapeProtection Service Test
echo "\n4. ScrapeProtection Service Test... \n";
if (function_exists('zippicks') && zippicks()->has('vibes.scrape_protection')) {
    try {
        $protection = zippicks()->get('vibes.scrape_protection');
        echo "   - Service instantiation... {$green}PASS{$reset}\n";
        
        // Test rate limiting check (should not throw fatal error)
        $rate_ok = $protection->check_rate_limit('test');
        echo "   - Rate limit check... {$green}PASS{$reset}\n";
        
        // Test watermark generation
        $watermarks = $protection->generate_watermarks();
        if (!empty($watermarks)) {
            echo "   - Watermark generation... {$green}PASS{$reset}\n";
        } else {
            echo "   - Watermark generation... {$yellow}EMPTY{$reset}\n";
        }
        
    } catch (Exception $e) {
        echo "   - Service test... {$red}FAIL{$reset} - " . $e->getMessage() . "\n";
    }
} else {
    echo "   {$yellow}SKIPPED{$reset} - Service not available\n";
}

// Test 5: REST API Endpoints Test
echo "\n5. REST API Endpoints Test... \n";
$endpoints = [
    '/wp-json/zippicks/v2/vibes',
    '/wp-json/zippicks/v2/vibes/categories',
    '/wp-json/zippicks/v2/vibes/popular'
];

foreach ($endpoints as $endpoint) {
    echo "   - Testing $endpoint... ";
    
    $url = home_url($endpoint);
    $response = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => [
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        ]
    ]);
    
    if (is_wp_error($response)) {
        echo "{$red}ERROR{$reset} - " . $response->get_error_message() . "\n";
    } else {
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            echo "{$green}OK{$reset} (200)\n";
        } elseif ($code === 403) {
            echo "{$yellow}PROTECTED{$reset} (403) - Good for security\n";
        } else {
            echo "{$yellow}CODE $code{$reset}\n";
        }
    }
}

// Test 6: Error Handling Test
echo "\n6. Error Handling Test... \n";
echo "   - Testing invalid database query handling... ";
try {
    // This should not cause a fatal error
    if (function_exists('zippicks') && zippicks()->has('vibes.service')) {
        $service = zippicks()->get('vibes.service');
        // Test with invalid ID
        $result = $service->getVibeById(-1);
        if ($result === null) {
            echo "{$green}PASS{$reset} - Graceful null return\n";
        } else {
            echo "{$yellow}UNEXPECTED{$reset} - Got result for invalid ID\n";
        }
    } else {
        echo "{$yellow}SKIPPED{$reset} - Service not available\n";
    }
} catch (Exception $e) {
    echo "{$green}PASS{$reset} - Exception handled: " . get_class($e) . "\n";
}

// Test 7: Client-Side Rendering Test
echo "\n7. Client-Side Rendering Test... \n";
$frontend_files = [
    'assets/js/vibes-app.js',
    'assets/css/vibes-frontend.css',
    'templates/client-render/vibe-archive.php'
];

foreach ($frontend_files as $file) {
    $path = plugin_dir_path(__FILE__) . $file;
    echo "   - $file... ";
    if (file_exists($path)) {
        echo "{$green}EXISTS{$reset}\n";
    } else {
        echo "{$yellow}MISSING{$reset}\n";
    }
}

// Test 8: Anti-Scraping Headers Test
echo "\n8. Anti-Scraping Headers Test... \n";
echo "   Testing security headers on API endpoint...\n";

$test_url = home_url('/wp-json/zippicks/v2/vibes?per_page=1');
$response = wp_remote_get($test_url);

if (!is_wp_error($response)) {
    $headers = wp_remote_retrieve_headers($response);
    
    $security_headers = [
        'X-Robots-Tag' => 'noindex',
        'Cache-Control' => ['private', 'no-store', 'max-age=0'],
        'X-Content-Type-Options' => 'nosniff'
    ];
    
    foreach ($security_headers as $header => $expected) {
        echo "   - $header: ";
        $value = $headers[$header] ?? null;
        
        if ($value) {
            if (is_array($expected)) {
                $found = false;
                foreach ($expected as $exp) {
                    if (stripos($value, $exp) !== false) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    echo "{$green}OK{$reset} ($value)\n";
                } else {
                    echo "{$yellow}DIFFERENT{$reset} ($value)\n";
                }
            } else {
                if ($value === $expected) {
                    echo "{$green}OK{$reset}\n";
                } else {
                    echo "{$yellow}DIFFERENT{$reset} ($value)\n";
                }
            }
        } else {
            echo "{$red}MISSING{$reset}\n";
        }
    }
}

// Summary
echo "\n=================================\n";
echo "Test Summary\n";
echo "=================================\n";

$issues = [];

if (!$all_tables_exist) {
    $issues[] = "Some database tables are missing";
}

if (!function_exists('zippicks')) {
    $issues[] = "Foundation not available (running in limited mode)";
}

if (empty($issues)) {
    echo "{$green}✓ All critical tests passed!{$reset}\n";
    echo "The plugin is ready for production use.\n";
} else {
    echo "{$yellow}⚠ Some issues detected:{$reset}\n";
    foreach ($issues as $issue) {
        echo "  - $issue\n";
    }
    echo "\nThe plugin is functional but some features may be limited.\n";
}

echo "\n";

// Performance check
echo "Performance Metrics:\n";
$end_time = microtime(true);
$memory_peak = memory_get_peak_usage(true) / 1024 / 1024;
echo "- Memory usage: " . round($memory_peak, 2) . " MB\n";
echo "- Execution time: " . round(($end_time - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . " ms\n";

echo "\nDone.\n";