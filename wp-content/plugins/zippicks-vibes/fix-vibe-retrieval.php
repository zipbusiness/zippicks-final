<?php
/**
 * Fix for vibe retrieval issues
 */

// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

echo "<h1>Vibe Retrieval Fix</h1>";

// Clear all vibe-related caches
global $wpdb;

echo "<h2>1. Clearing Cached Data</h2>";

// Clear transients
$cleared = $wpdb->query("
    DELETE FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient%vibe%' 
       OR option_name LIKE '_transient_timeout%vibe%'
");

echo "<p>Cleared $cleared transient entries</p>";

// Clear object cache if available
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
    echo "<p>Object cache flushed</p>";
}

// Test vibe retrieval
echo "<h2>2. Testing Vibe Retrieval</h2>";

$vibes_table = $wpdb->prefix . 'zippicks_vibes';

// Direct database test
echo "<h3>Direct Database Query:</h3>";
$all_vibes = $wpdb->get_results("SELECT id, name, slug, is_active FROM $vibes_table ORDER BY id");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Slug</th><th>Active</th></tr>";
foreach ($all_vibes as $vibe) {
    echo "<tr>";
    echo "<td>{$vibe->id}</td>";
    echo "<td>{$vibe->name}</td>";
    echo "<td>{$vibe->slug}</td>";
    echo "<td>" . ($vibe->is_active ? 'Yes' : 'No') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test repository retrieval
echo "<h3>Repository Find Test:</h3>";

// Load required files
require_once(__DIR__ . '/src/Repositories/VibeRepositoryInterface.php');
require_once(__DIR__ . '/src/Repositories/VibeRepository.php');
require_once(__DIR__ . '/src/Models/Vibe.php');

$repository = new \ZipPicksVibes\Repositories\VibeRepository();

foreach ($all_vibes as $vibe) {
    $found = $repository->find($vibe->id);
    if ($found) {
        echo "<p style='color:green'>✓ Repository found vibe ID {$vibe->id}: {$found->name}</p>";
    } else {
        echo "<p style='color:red'>✗ Repository FAILED to find vibe ID {$vibe->id}</p>";
    }
}

// Test service layer
echo "<h3>Service Layer Test:</h3>";

require_once(__DIR__ . '/src/Models/PaginatedResult.php');
require_once(__DIR__ . '/src/Services/VibeService.php');

$service = new \ZipPicksVibes\Services\VibeService($repository);

foreach ($all_vibes as $vibe) {
    try {
        $vibeModel = $service->getVibe($vibe->id);
        echo "<p style='color:green'>✓ Service found vibe ID {$vibe->id}: {$vibeModel->getName()}</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>✗ Service FAILED for vibe ID {$vibe->id}: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>3. Fix Applied</h2>";
echo "<p>Cache has been cleared. <a href='" . admin_url('admin.php?page=zippicks-vibes') . "'>Return to Vibes Admin</a></p>";

// Additional diagnostic
echo "<h2>4. Business Count Check</h2>";
echo "<p>Looking for tables that might link vibes to businesses...</p>";

$tables = $wpdb->get_col("SHOW TABLES LIKE '%zippicks%business%'");
if (!empty($tables)) {
    echo "<p>Found business-related tables:</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
        
        // Check if table has vibe_id column
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table");
        if (in_array('vibe_id', $columns)) {
            echo " - <strong>Has vibe_id column!</strong>";
        }
    }
    echo "</ul>";
} else {
    echo "<p>No business tables found. Business count feature may not be implemented yet.</p>";
}