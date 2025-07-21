<?php
/**
 * Syntax validation for ZipPicks Business plugin
 */

// Colors for output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

echo "\n{$yellow}=== ZipPicks Business Syntax Check ==={$reset}\n\n";

// Get all PHP files in the plugin
$plugin_dir = dirname(__FILE__);
$files = array();

// Recursively find all PHP files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}

$has_errors = false;
$error_count = 0;
$file_count = 0;

echo "Checking " . count($files) . " PHP files...\n\n";

foreach ($files as $file) {
    $file_count++;
    $relative_path = str_replace($plugin_dir . '/', '', $file);
    
    // Check PHP syntax
    $output = array();
    $return_var = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
    
    if ($return_var !== 0) {
        echo "{$red}✗{$reset} {$relative_path}\n";
        foreach ($output as $line) {
            if (strpos($line, 'No syntax errors detected') === false) {
                echo "  {$line}\n";
            }
        }
        $has_errors = true;
        $error_count++;
    } else {
        echo "{$green}✓{$reset} {$relative_path}\n";
    }
}

echo "\n{$yellow}=== Summary ==={$reset}\n";
echo "Total files checked: {$file_count}\n";
echo "{$green}Passed: " . ($file_count - $error_count) . "{$reset}\n";
echo "{$red}Failed: {$error_count}{$reset}\n";

if ($has_errors) {
    echo "\n{$red}❌ Syntax errors found!{$reset}\n";
    exit(1);
} else {
    echo "\n{$green}✅ All files have valid syntax!{$reset}\n";
    exit(0);
}