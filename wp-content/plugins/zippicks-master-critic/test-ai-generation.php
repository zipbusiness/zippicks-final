<?php
/**
 * Test AI Generation
 * 
 * This script tests the Master Critic AI generation to diagnose issues
 * Access via browser (must be logged in as admin)
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

// Define plugin directory if not defined
if (!defined('ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR')) {
    define('ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR', dirname(__FILE__) . '/');
}

// Load required classes
require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-ai-service.php';
require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Master Critic AI Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        pre { background: white; padding: 15px; border: 1px solid #ddd; overflow: auto; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h1>Master Critic AI Generation Test</h1>
    
    <?php
    echo "<h2>1. Checking API Key Configuration</h2>";
    echo "<pre>";
    
    $api_key = ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_anthropic_api_key');
    
    if (empty($api_key)) {
        echo "<span class='error'>❌ ERROR: No Anthropic API key found in settings</span>\n";
        echo "Please add your API key in Master Critic > Settings\n";
        echo "</pre></body></html>";
        exit;
    }
    
    echo "<span class='success'>✅ Anthropic API key found (length: " . strlen($api_key) . ")</span>\n";
    
    $openai_key = ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_openai_api_key');
    if (!empty($openai_key)) {
        echo "<span class='success'>✅ OpenAI API key found (length: " . strlen($openai_key) . ")</span>\n";
    } else {
        echo "<span class='info'>ℹ️ No OpenAI API key configured</span>\n";
    }
    
    echo "</pre>";
    
    echo "<h2>2. Testing Simple AI Generation</h2>";
    echo "<pre>";
    
    // Initialize AI service
    $ai_service = new ZipPicks_Master_Critic_AI_Service();
    
    // Simple test prompt
    $test_prompt = "List 3 popular Italian restaurants. Just the names, one per line.";
    
    echo "Sending test prompt to AI...\n";
    echo "Prompt: " . htmlspecialchars($test_prompt) . "\n\n";
    
    // Enable error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Execute AI generation
    $start_time = microtime(true);
    $result = $ai_service->execute_ai_generation($test_prompt, 'anthropic');
    $execution_time = microtime(true) - $start_time;
    
    echo "Execution time: " . round($execution_time, 2) . " seconds\n\n";
    
    if ($result['success']) {
        echo "<span class='success'>✅ AI generation successful!</span>\n";
        echo "Provider: " . ($result['provider'] ?? 'unknown') . "\n";
        echo "Model: " . ($result['model'] ?? 'unknown') . "\n";
        echo "Response length: " . strlen($result['data']) . " characters\n\n";
        echo "Response:\n";
        echo htmlspecialchars($result['data']) . "\n";
    } else {
        echo "<span class='error'>❌ AI generation failed!</span>\n";
        echo "Error: " . htmlspecialchars($result['error']) . "\n";
        
        if (isset($result['http_code'])) {
            echo "HTTP Code: " . $result['http_code'] . "\n";
        }
        if (isset($result['wp_error_code'])) {
            echo "WP Error Code: " . $result['wp_error_code'] . "\n";
        }
        if (isset($result['raw_response'])) {
            echo "\nRaw response:\n" . htmlspecialchars(substr($result['raw_response'], 0, 500)) . "\n";
        }
    }
    
    echo "</pre>";
    
    // Check error logs
    echo "<h2>3. Recent Error Log Entries</h2>";
    echo "<pre>";
    
    // Try to read PHP error log
    $error_log = ini_get('error_log');
    if ($error_log && file_exists($error_log)) {
        $log_contents = file_get_contents($error_log);
        $lines = explode("\n", $log_contents);
        $recent_lines = array_slice($lines, -20);
        
        $found_zippicks = false;
        foreach ($recent_lines as $line) {
            if (stripos($line, 'zippicks') !== false || stripos($line, 'ai service') !== false) {
                echo htmlspecialchars($line) . "\n";
                $found_zippicks = true;
            }
        }
        
        if (!$found_zippicks) {
            echo "No recent ZipPicks errors found in PHP error log\n";
        }
    } else {
        echo "Could not access PHP error log\n";
    }
    
    echo "</pre>";
    
    echo "<h2>4. Testing Restaurant-Specific Prompt</h2>";
    echo "<pre>";
    
    $restaurant_prompt = 'Generate a JSON array of 3 Italian restaurants with this structure:
[
  {
    "rank": 1,
    "name": "Restaurant Name",
    "score": 9.2,
    "summary": "Brief description"
  }
]';
    
    echo "Sending restaurant prompt to AI...\n\n";
    
    $start_time = microtime(true);
    $result2 = $ai_service->execute_ai_generation($restaurant_prompt, 'anthropic');
    $execution_time = microtime(true) - $start_time;
    
    echo "Execution time: " . round($execution_time, 2) . " seconds\n\n";
    
    if ($result2['success']) {
        echo "<span class='success'>✅ Restaurant generation successful!</span>\n";
        echo "Response preview:\n";
        echo htmlspecialchars(substr($result2['data'], 0, 300)) . "...\n";
        
        // Try to parse
        $parsed = $ai_service->parse_ai_response($result2['data']);
        if ($parsed) {
            echo "\n<span class='success'>✅ Successfully parsed " . count($parsed) . " restaurants</span>\n";
        } else {
            echo "\n<span class='error'>❌ Failed to parse restaurant data</span>\n";
        }
    } else {
        echo "<span class='error'>❌ Restaurant generation failed!</span>\n";
        echo "Error: " . htmlspecialchars($result2['error']) . "\n";
    }
    
    echo "</pre>";
    
    ?>
    
    <h2>Actions</h2>
    <p>
        <a href="<?php echo admin_url('admin.php?page=zippicks-master-critic'); ?>" class="button">Go to Master Critic</a>
        <a href="<?php echo admin_url('admin.php?page=zippicks-master-critic-settings'); ?>" class="button">Go to Settings</a>
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="button">Run Test Again</a>
    </p>
    
</body>
</html>