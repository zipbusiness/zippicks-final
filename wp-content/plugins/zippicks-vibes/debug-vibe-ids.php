<?php
/**
 * Quick debug script to check vibe IDs
 */

// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// Load the plugin files
require_once(__DIR__ . '/src/class-vibes-plugin.php');

echo "<h1>Vibe ID Debug</h1>";

// Initialize the plugin
$plugin = zippicks_vibes_init();

// Try to get the vibe service
$vibe_service = null;
if (function_exists('zippicks') && zippicks()->has('vibes.service')) {
    $vibe_service = zippicks()->get('vibes.service');
} else {
    // Create service manually
    require_once(__DIR__ . '/src/Repositories/VibeRepositoryInterface.php');
    require_once(__DIR__ . '/src/Repositories/VibeRepository.php');
    require_once(__DIR__ . '/src/Models/Vibe.php');
    require_once(__DIR__ . '/src/Models/PaginatedResult.php');
    require_once(__DIR__ . '/src/Services/VibeService.php');
    
    $repository = new \ZipPicksVibes\Repositories\VibeRepository();
    $vibe_service = new \ZipPicksVibes\Services\VibeService($repository);
}

if ($vibe_service) {
    echo "<h2>Testing getVibesPaginated()</h2>";
    try {
        $result = $vibe_service->getVibesPaginated(1, 50, ['status' => 'all']);
        $vibes = $result->getItems();
        
        echo "<p>Total vibes found: " . count($vibes) . "</p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Index</th><th>ID</th><th>Name</th><th>Slug</th><th>Status</th></tr>";
        
        foreach ($vibes as $index => $vibe) {
            echo "<tr>";
            echo "<td>" . $index . "</td>";
            echo "<td>" . $vibe->getId() . "</td>";
            echo "<td>" . $vibe->getName() . "</td>";
            echo "<td>" . $vibe->getSlug() . "</td>";
            echo "<td>" . ($vibe->isActive() ? 'Active' : 'Inactive') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>Testing individual vibe retrieval</h2>";
    for ($i = 1; $i <= 5; $i++) {
        try {
            $vibe = $vibe_service->getVibe($i);
            echo "<p style='color:green'>✓ Vibe ID $i found: " . $vibe->getName() . "</p>";
        } catch (Exception $e) {
            echo "<p style='color:red'>✗ Vibe ID $i: " . $e->getMessage() . "</p>";
        }
    }
} else {
    echo "<p style='color:red'>Could not initialize vibe service</p>";
}

// Direct database check
global $wpdb;
$table = $wpdb->prefix . 'zippicks_vibes';
echo "<h2>Direct Database Query</h2>";
$results = $wpdb->get_results("SELECT id, name, slug, is_active FROM $table ORDER BY id");
echo "<pre>";
print_r($results);
echo "</pre>";