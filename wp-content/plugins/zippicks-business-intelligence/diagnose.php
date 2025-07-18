<?php
/**
 * Diagnostic script for ZipPicks Business Intelligence plugin
 */

// Check if file is directly accessible
echo "=== ZipPicks Business Intelligence Plugin Diagnostic ===\n\n";

// 1. Check PHP version
echo "1. PHP Version: " . PHP_VERSION . "\n";
echo "   Required: 7.4+\n\n";

// 2. Check if main plugin file exists and is readable
$plugin_file = __DIR__ . '/zippicks-business-intelligence.php';
echo "2. Main plugin file:\n";
echo "   Path: " . $plugin_file . "\n";
echo "   Exists: " . (file_exists($plugin_file) ? 'Yes' : 'No') . "\n";
echo "   Readable: " . (is_readable($plugin_file) ? 'Yes' : 'No') . "\n\n";

// 3. Check PHP syntax of main file
echo "3. PHP Syntax Check:\n";
$output = [];
$return_var = 0;
exec('php -l ' . escapeshellarg($plugin_file) . ' 2>&1', $output, $return_var);
echo "   " . implode("\n   ", $output) . "\n\n";

// 4. Check required files
echo "4. Required Files Check:\n";
$required_files = [
    'includes/class-activator.php',
    'includes/class-deactivator.php',
    'includes/class-business-intelligence.php',
    'includes/class-loader.php'
];

foreach ($required_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    echo "   - $file: " . (file_exists($full_path) ? 'EXISTS' : 'MISSING') . "\n";
}
echo "\n";

// 5. Check plugin header
echo "5. Plugin Header Check:\n";
if (file_exists($plugin_file)) {
    $plugin_data = get_file_data($plugin_file, [
        'Name' => 'Plugin Name',
        'PluginURI' => 'Plugin URI',
        'Version' => 'Version',
        'Description' => 'Description',
        'Author' => 'Author',
        'AuthorURI' => 'Author URI',
        'TextDomain' => 'Text Domain',
        'DomainPath' => 'Domain Path',
    ]);
    
    foreach ($plugin_data as $key => $value) {
        echo "   $key: " . ($value ?: '(empty)') . "\n";
    }
} else {
    echo "   Cannot read plugin file\n";
}
echo "\n";

// 6. Check for namespace issues
echo "6. Namespace Check:\n";
if (file_exists($plugin_file)) {
    $content = file_get_contents($plugin_file);
    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
        echo "   Main namespace: " . $matches[1] . "\n";
    } else {
        echo "   No namespace found\n";
    }
}
echo "\n";

// 7. Check directory structure
echo "7. Directory Structure:\n";
$dirs = ['assets', 'includes', 'src', 'views'];
foreach ($dirs as $dir) {
    echo "   - $dir/: " . (is_dir(__DIR__ . '/' . $dir) ? 'EXISTS' : 'MISSING') . "\n";
}

echo "\n=== End of Diagnostic ===\n";

// Helper function for get_file_data if not in WordPress context
if (!function_exists('get_file_data')) {
    function get_file_data($file, $headers) {
        $file_data = [];
        $file_contents = file_get_contents($file);
        
        foreach ($headers as $field => $regex) {
            preg_match('/' . preg_quote($regex, '/') . ':(.*)$/mi', $file_contents, $match);
            $file_data[$field] = isset($match[1]) ? trim($match[1]) : '';
        }
        
        return $file_data;
    }
}