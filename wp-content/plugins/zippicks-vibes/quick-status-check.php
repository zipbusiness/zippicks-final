<?php
/**
 * Quick status check for ZipPicks Vibes plugin
 */

echo "=== ZipPicks Vibes Plugin Status Check ===\n\n";

// 1. Check main plugin file
$plugin_file = __DIR__ . '/zippicks-vibes.php';
echo "1. Main plugin file (zippicks-vibes.php): ";
if (file_exists($plugin_file)) {
    echo "✅ EXISTS\n";
    
    // Check if it's a valid WordPress plugin
    $plugin_data = get_file_data($plugin_file, array(
        'Name' => 'Plugin Name',
        'Version' => 'Version',
    ), 'plugin');
    
    if (!empty($plugin_data['Name'])) {
        echo "   - Plugin Name: " . $plugin_data['Name'] . "\n";
        echo "   - Version: " . $plugin_data['Version'] . "\n";
    }
} else {
    echo "❌ MISSING\n";
}

// 2. Check critical dependencies
echo "\n2. Critical Files:\n";
$critical_files = [
    'src/ServiceProvider.php' => 'Service Provider',
    'src/Database/Installer.php' => 'Database Installer',
    'assets/js/vibes-app.js' => 'Main JavaScript',
    'templates/client-render/vibe-single.php' => 'Single Template',
    'templates/client-render/vibe-archive.php' => 'Archive Template'
];

foreach ($critical_files as $file => $desc) {
    echo "   - $desc: ";
    echo file_exists(__DIR__ . '/' . $file) ? "✅\n" : "❌\n";
}

// 3. Check PHP version
echo "\n3. PHP Version: " . PHP_VERSION;
echo (version_compare(PHP_VERSION, '8.0', '>=')) ? " ✅\n" : " ❌ (Requires 8.0+)\n";

// 4. Check for syntax errors in main file
echo "\n4. Syntax Check: ";
$code = file_get_contents($plugin_file);
$tokens = @token_get_all($code);
echo ($tokens !== false) ? "✅ Valid PHP syntax\n" : "❌ Syntax errors detected\n";

// 5. Summary
echo "\n=== SUMMARY ===\n";
echo "The plugin should now be visible in WordPress admin.\n";
echo "Look for 'ZipPicks Vibes' in the plugins list.\n";
echo "\nIf still not visible, check:\n";
echo "- WordPress debug log for errors\n";
echo "- File permissions (should be readable by web server)\n";
echo "- Clear any caching plugins\n";