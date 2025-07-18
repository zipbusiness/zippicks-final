<?php
/**
 * Enterprise V2 Cleanup Script
 * Removes all V2 references from the ZipPicks Vibes plugin
 */

echo "=== ZipPicks Vibes Enterprise V2 Cleanup ===\n\n";

$plugin_dir = dirname(__FILE__);
$fixes_applied = 0;
$files_processed = 0;

// First, ensure main plugin file is renamed
if (file_exists($plugin_dir . '/zippicks-vibes.php') && !file_exists($plugin_dir . '/zippicks-vibes.php')) {
    rename($plugin_dir . '/zippicks-vibes.php', $plugin_dir . '/zippicks-vibes.php');
    echo "✓ Renamed main plugin file\n";
    $fixes_applied++;
}

// Process all PHP files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filepath = $file->getPathname();
        
        // Skip this script
        if (basename($filepath) === 'enterprise-v2-cleanup.php') {
            continue;
        }
        
        $content = file_get_contents($filepath);
        $original = $content;
        
        // Apply all replacements
        $content = str_replace('ZipPicksVibes', 'ZipPicksVibes', $content);
        $content = str_replace('zippicks-vibes', 'zippicks-vibes', $content);
        $content = str_replace('zippicksVibes', 'zippicksVibes', $content);
        $content = str_replace('ZIPPICKS_VIBES_', 'ZIPPICKS_VIBES_', $content);
        $content = str_replace('VibesPlugin', 'VibesPlugin', $content);
        $content = str_replace('Vibes', 'Vibes', $content);
        $content = str_replace('vibes_', 'vibes_', $content);
        
        if ($content !== $original) {
            file_put_contents($filepath, $content);
            echo "✓ Updated: " . str_replace($plugin_dir . '/', '', $filepath) . "\n";
            $fixes_applied++;
        }
        
        $files_processed++;
    }
}

// Update composer.json if exists
$composer_path = $plugin_dir . '/composer.json';
if (file_exists($composer_path)) {
    $composer = json_decode(file_get_contents($composer_path), true);
    
    if (isset($composer['autoload']['psr-4']['ZipPicksVibes\\'])) {
        $value = $composer['autoload']['psr-4']['ZipPicksVibes\\'];
        unset($composer['autoload']['psr-4']['ZipPicksVibes\\']);
        $composer['autoload']['psr-4']['ZipPicksVibes\\'] = $value;
        
        file_put_contents($composer_path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "✓ Updated composer.json\n";
        $fixes_applied++;
    }
}

echo "\n=== Summary ===\n";
echo "Files processed: $files_processed\n";
echo "Fixes applied: $fixes_applied\n";
echo "\nNext steps:\n";
echo "1. Clear all WordPress caches\n";
echo "2. Refresh the plugins page\n";
echo "3. Look for 'ZipPicks Vibes' (without V2)\n";
echo "4. Activate the plugin\n";

// Create verification file
$verification = <<<'PHP'
<?php
// Quick verification
$main_file = dirname(__FILE__) . '/zippicks-vibes.php';
if (file_exists($main_file)) {
    $content = file_get_contents($main_file);
    if (strpos($content, 'Plugin Name: ZipPicks Vibes') !== false && 
        strpos($content, 'V2') === false) {
        echo "✅ Plugin cleaned successfully - no V2 references in header\n";
    } else {
        echo "❌ Plugin still contains V2 references\n";
    }
} else {
    echo "❌ Main plugin file not found\n";
}
PHP;

file_put_contents($plugin_dir . '/verify-cleanup.php', $verification);
echo "\nCreated verify-cleanup.php for quick verification\n";