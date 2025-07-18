<?php
/**
 * Enterprise Refactoring Script - Remove V2 References
 * This script will clean up all V2 references and establish proper naming
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

echo "=== ZipPicks Vibes Enterprise Refactoring ===\n";
echo "Removing all V2 references and establishing clean naming conventions\n\n";

$plugin_dir = dirname(__FILE__);
$changes_made = 0;

// Define replacements
$replacements = [
    // Plugin header
    'Plugin Name: ZipPicks Vibes' => 'Plugin Name: ZipPicks Vibes',
    'Text Domain: zippicks-vibes' => 'Text Domain: zippicks-vibes',
    '@package ZipPicksVibes' => '@package ZipPicksVibes',
    
    // Namespace
    'namespace ZipPicksVibes' => 'namespace ZipPicksVibes',
    'use ZipPicksVibes' => 'use ZipPicksVibes',
    
    // Class names
    'class VibesPlugin' => 'class VibesPlugin',
    'VibesPlugin::' => 'VibesPlugin::',
    
    // Constants
    'ZIPPICKS_VIBES_VERSION' => 'ZIPPICKS_VIBES_VERSION',
    'ZIPPICKS_VIBES_DIR' => 'ZIPPICKS_VIBES_DIR',
    'ZIPPICKS_VIBES_URL' => 'ZIPPICKS_VIBES_URL',
    'ZIPPICKS_VIBES_FILE' => 'ZIPPICKS_VIBES_FILE',
    'ZIPPICKS_VIBES_BASENAME' => 'ZIPPICKS_VIBES_BASENAME',
    'ZIPPICKS_VIBES_RATE_LIMIT_REQUESTS' => 'ZIPPICKS_VIBES_RATE_LIMIT_REQUESTS',
    'ZIPPICKS_VIBES_CACHE_TTL' => 'ZIPPICKS_VIBES_CACHE_TTL',
    'ZIPPICKS_VIBES_SESSION_REQUIRED' => 'ZIPPICKS_VIBES_SESSION_REQUIRED',
    
    // JavaScript object
    'zippicksVibes' => 'zippicksVibes',
    
    // File references
    'zippicks-vibes.php' => 'zippicks-vibes.php',
    'zippicks-vibes/zippicks-vibes.php' => 'zippicks-vibes/zippicks-vibes.php',
    
    // Option names
    'zippicks_vibes_' => 'zippicks_vibes_',
    
    // Menu slugs
    'zippicks-vibes' => 'zippicks-vibes',
    
    // Script handles
    "'zippicks-vibes-" => "'zippicks-vibes-",
    '"zippicks-vibes-' => '"zippicks-vibes-',
];

// Function to process files
function processFile($file, $replacements, &$changes_made) {
    $content = file_get_contents($file);
    $original = $content;
    
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        $changes_made++;
        echo "✓ Updated: " . basename(dirname($file)) . '/' . basename($file) . "\n";
        return true;
    }
    return false;
}

// Process all PHP files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        // Skip this script
        if ($file->getFilename() === 'refactor-remove-v2.php') {
            continue;
        }
        processFile($file->getPathname(), $replacements, $changes_made);
    }
}

// Rename the main plugin file
$old_file = $plugin_dir . '/zippicks-vibes.php';
$new_file = $plugin_dir . '/zippicks-vibes.php';

if (file_exists($old_file) && !file_exists($new_file)) {
    rename($old_file, $new_file);
    echo "\n✓ Renamed main plugin file: zippicks-vibes.php → zippicks-vibes.php\n";
    $changes_made++;
}

// Update composer.json if it exists
$composer_file = $plugin_dir . '/composer.json';
if (file_exists($composer_file)) {
    $composer = json_decode(file_get_contents($composer_file), true);
    $updated = false;
    
    if (isset($composer['name']) && strpos($composer['name'], '-v2') !== false) {
        $composer['name'] = str_replace('-v2', '', $composer['name']);
        $updated = true;
    }
    
    if (isset($composer['autoload']['psr-4']['ZipPicksVibes\\'])) {
        $composer['autoload']['psr-4']['ZipPicksVibes\\'] = $composer['autoload']['psr-4']['ZipPicksVibes\\'];
        unset($composer['autoload']['psr-4']['ZipPicksVibes\\']);
        $updated = true;
    }
    
    if ($updated) {
        file_put_contents($composer_file, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "✓ Updated composer.json\n";
        $changes_made++;
    }
}

echo "\n=== Summary ===\n";
echo "Total files updated: $changes_made\n";
echo "\nNext steps:\n";
echo "1. Clear WordPress caches\n";
echo "2. Deactivate and reactivate the plugin\n";
echo "3. Verify all functionality works correctly\n";
echo "\nThe plugin should now appear as 'ZipPicks Vibes' without any V2 references.\n";