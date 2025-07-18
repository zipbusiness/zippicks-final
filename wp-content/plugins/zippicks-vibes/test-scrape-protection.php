<?php
/**
 * Test script for ScrapeProtection service
 * 
 * Run this to verify all namespace issues are fixed
 */

// Load WordPress
require_once __DIR__ . '/../../../../../../wp-load.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test namespace resolution
echo "Testing ScrapeProtection Service Namespace Resolution\n";
echo "=====================================================\n\n";

// Load the class
require_once __DIR__ . '/src/Services/ScrapeProtection.php';

try {
    // Create instance
    echo "1. Creating ScrapeProtection instance... ";
    $scrapeProtection = new \ZipPicksVibes\Services\ScrapeProtection();
    echo "✓ Success\n";
    
    // Test methods that use WordPress functions
    echo "2. Testing validateRequest()... ";
    $result = $scrapeProtection->validateRequest();
    echo "✓ Success (returned: " . ($result ? 'true' : 'false') . ")\n";
    
    echo "3. Testing monitor_request()... ";
    $scrapeProtection->monitor_request();
    echo "✓ Success\n";
    
    echo "4. Testing generate_watermarks()... ";
    $watermarks = $scrapeProtection->generate_watermarks();
    echo "✓ Success (generated " . strlen($watermarks) . " chars)\n";
    
    echo "5. Testing check_rate_limit()... ";
    $rateOk = $scrapeProtection->check_rate_limit('test');
    echo "✓ Success (returned: " . ($rateOk ? 'true' : 'false') . ")\n";
    
    echo "6. Testing get_security_stats()... ";
    $stats = $scrapeProtection->get_security_stats();
    echo "✓ Success (stats: " . json_encode($stats) . ")\n";
    
    echo "7. Testing isUserAgentMatching()... ";
    $matches = $scrapeProtection->isUserAgentMatching('/bot/i');
    echo "✓ Success (returned: " . ($matches ? 'true' : 'false') . ")\n";
    
    // Test IP management
    $testIP = '192.168.1.100';
    
    echo "8. Testing whitelistIP()... ";
    $whitelisted = $scrapeProtection->whitelistIP($testIP, 'Test whitelist', 300);
    echo "✓ Success (returned: " . ($whitelisted ? 'true' : 'false') . ")\n";
    
    echo "9. Testing unwhitelistIP()... ";
    $unwhitelisted = $scrapeProtection->unwhitelistIP($testIP);
    echo "✓ Success (returned: " . ($unwhitelisted ? 'true' : 'false') . ")\n";
    
    echo "10. Testing blockIP()... ";
    $blocked = $scrapeProtection->blockIP($testIP, 'Test block', 300);
    echo "✓ Success (returned: " . ($blocked ? 'true' : 'false') . ")\n";
    
    echo "11. Testing unblockIP()... ";
    $unblocked = $scrapeProtection->unblockIP($testIP);
    echo "✓ Success (returned: " . ($unblocked ? 'true' : 'false') . ")\n";
    
    echo "\n✅ All tests passed! No namespace resolution errors.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";