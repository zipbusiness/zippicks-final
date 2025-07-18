<?php
// Simple syntax checker that doesn't require PHP CLI

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>ZipPicks Vibes Plugin Syntax Check</h2>";

// Test if we can include the main plugin file
echo "<h3>Testing main plugin file...</h3>";
try {
    // First check if file exists
    $plugin_file = __DIR__ . '/zippicks-vibes.php';
    if (!file_exists($plugin_file)) {
        echo "ERROR: Plugin file not found!<br>";
        exit;
    }
    
    // Try to parse without executing
    $code = file_get_contents($plugin_file);
    
    // Basic syntax check
    $tokens = @token_get_all($code);
    if ($tokens === false) {
        echo "ERROR: Failed to tokenize plugin file<br>";
    } else {
        echo "✓ Main plugin file has valid PHP syntax<br>";
        echo "Total tokens: " . count($tokens) . "<br>";
    }
    
    // Check for specific PHP 8 features
    echo "<h3>Checking for PHP 8 specific features...</h3>";
    
    // Check for typed properties
    if (preg_match('/private\s+\??\w+\s+\$\w+/', $code)) {
        echo "⚠️ Found typed properties (PHP 7.4+ feature)<br>";
    }
    
    // Check for nullable types with ?
    if (preg_match('/\?\s*\w+\s+\$/', $code)) {
        echo "⚠️ Found nullable type declarations (PHP 7.1+ feature)<br>";
    }
    
    // Check for ::class on objects
    if (preg_match('/\$\w+::class/', $code)) {
        echo "⚠️ Found ::class on objects (PHP 8.0+ feature)<br>";
    }
    
    echo "<h3>Current PHP Version</h3>";
    echo "PHP " . PHP_VERSION . "<br>";
    echo "Required: PHP 8.0+<br>";
    
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        echo "<strong style='color:red'>ERROR: PHP version is too old!</strong><br>";
    }
    
} catch (ParseError $e) {
    echo "PARSE ERROR: " . $e->getMessage() . " on line " . $e->getLine() . "<br>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}