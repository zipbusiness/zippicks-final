<?php
/**
 * Test Geo API Connection
 * 
 * This script tests the connection between the WordPress plugin
 * and the ZipBusiness Geo API endpoints.
 */

// Find and load WordPress
$wp_load = null;
$dir = dirname(__FILE__);
for ($i = 0; $i < 6; $i++) {
    if (file_exists($dir . '/wp-load.php')) {
        $wp_load = $dir . '/wp-load.php';
        break;
    }
    $dir = dirname($dir);
}

if (!$wp_load) {
    die('Error: Could not locate wp-load.php. Please ensure this plugin is installed in the correct WordPress directory.');
}

require_once $wp_load;

// Security check with improved error handling
if (!is_user_logged_in()) {
    wp_die(
        '<h1>Authentication Required</h1>' .
        '<p>You must be logged in to access this page.</p>' .
        '<p><a href="' . wp_login_url($_SERVER['REQUEST_URI']) . '">Click here to log in</a></p>',
        'Authentication Required',
        ['response' => 401]
    );
}

if (!current_user_can('manage_options')) {
    wp_die(
        '<h1>Insufficient Permissions</h1>' .
        '<p>You must have administrator privileges to access this page.</p>' .
        '<p>Your current role: ' . implode(', ', wp_get_current_user()->roles) . '</p>' .
        '<p><a href="' . admin_url() . '">Return to Dashboard</a></p>',
        'Access Denied',
        ['response' => 403]
    );
}

// Load plugin classes
require_once dirname(__FILE__) . '/includes/class-geo-api-client.php';

use ZipPicks\Geo\Geo_API_Client;

echo "<h1>Geo Service API Connection Test</h1>";
echo "<pre>";

// 1. Check configuration
echo "=== Configuration Check ===\n";
echo "API URL: " . (defined('ZIPPICKS_API_URL') ? ZIPPICKS_API_URL : 'NOT DEFINED') . "\n";
echo "API Key: " . (defined('ZIPPICKS_API_KEY') ? substr(ZIPPICKS_API_KEY, 0, 10) . '...' : 'NOT DEFINED') . "\n\n";

// 2. Initialize API client
echo "=== Initializing API Client ===\n";
$api_client = new Geo_API_Client();
echo "API Client initialized successfully\n\n";

// 3. Test location detection
echo "=== Testing Location Detection ===\n";
$location = $api_client->detect_location('test-session-123');
if ($location) {
    echo "✓ Location detection successful:\n";
    echo "  Latitude: " . $location['latitude'] . "\n";
    echo "  Longitude: " . $location['longitude'] . "\n";
    echo "  City: " . $location['city'] . "\n";
    echo "  State: " . $location['state'] . "\n";
    echo "  Source: " . $location['source'] . "\n";
} else {
    echo "✗ Location detection failed\n";
    echo "  Error: " . $api_client->get_last_error() . "\n";
}
echo "\n";

// 4. Test nearby search
echo "=== Testing Nearby Search ===\n";
$nearby = $api_client->find_nearby(34.0522, -118.2437, 5, 10);
if ($nearby && isset($nearby['results'])) {
    echo "✓ Nearby search successful:\n";
    echo "  Found " . count($nearby['results']) . " restaurants\n";
    if (count($nearby['results']) > 0) {
        $first = $nearby['results'][0];
        echo "  First result: " . $first['name'] . " (" . $first['distance_miles'] . " miles)\n";
    }
} else {
    echo "✗ Nearby search failed\n";
}
echo "\n";

// 5. Test distance calculation
echo "=== Testing Distance Calculation ===\n";
$distance = $api_client->calculate_distance(
    ['lat' => 34.0522, 'lng' => -118.2437], // LA
    ['lat' => 37.7749, 'lng' => -122.4194], // SF
    'miles'
);
if ($distance && isset($distance['distance'])) {
    echo "✓ Distance calculation successful:\n";
    echo "  LA to SF: " . $distance['distance'] . " " . $distance['unit'] . "\n";
} else {
    echo "✗ Distance calculation failed\n";
}
echo "\n";

// 6. Test geocoding
echo "=== Testing ZIP Code Geocoding ===\n";
$geocode = $api_client->geocode('90210', 'zip');
if ($geocode && isset($geocode['latitude'])) {
    echo "✓ Geocoding successful:\n";
    echo "  ZIP 90210: " . $geocode['city'] . ", " . $geocode['state'] . "\n";
    echo "  Coordinates: " . $geocode['latitude'] . ", " . $geocode['longitude'] . "\n";
} else {
    echo "✗ Geocoding failed\n";
}

echo "\n=== Test Complete ===\n";
echo "</pre>";

// Add link to create geo endpoints if they don't exist
if (!$location || !$nearby) {
    echo "<div style='background: #ffe4e1; padding: 20px; margin: 20px 0;'>";
    echo "<h3>⚠️ API Endpoints Not Found</h3>";
    echo "<p>The geo endpoints don't appear to be available on the API server.</p>";
    echo "<p>You need to add the geo endpoints to your ZipBusiness API on Render.</p>";
    echo "<p>The geo.py file should be added to: <code>app/api/endpoints/geo.py</code></p>";
    echo "</div>";
}