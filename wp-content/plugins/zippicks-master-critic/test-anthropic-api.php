<?php
/**
 * Anthropic API Direct Test
 * 
 * This script tests the Anthropic API connection directly to diagnose issues
 * Run from command line: php test-anthropic-api.php
 * Or access via browser (must be logged in as admin)
 */

// Load WordPress if accessing via browser
if (php_sapi_name() !== 'cli') {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
}

// Define plugin directory if not defined
if (!defined('ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR')) {
    define('ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR', dirname(__FILE__) . '/');
}

// Load required classes
require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php';

echo "=== Anthropic API Connection Test ===\n\n";

// Step 1: Check API Key
echo "1. Checking API Key...\n";
$api_key = ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_anthropic_api_key');

if (empty($api_key)) {
    echo "   ❌ ERROR: No API key found in settings\n";
    echo "   Please add your Anthropic API key in WordPress admin > Master Critic > Settings\n";
    exit(1);
}

echo "   ✅ API key found (length: " . strlen($api_key) . ")\n";
echo "   First 10 chars: " . substr($api_key, 0, 10) . "...\n\n";

// Step 2: Test simple API call
echo "2. Testing Anthropic API connection...\n";

$url = 'https://api.anthropic.com/v1/messages';
$model = get_option('zippicks_anthropic_model', 'claude-3-sonnet-20240229');

echo "   Model: $model\n";

$data = array(
    'model' => $model,
    'max_tokens' => 100,
    'temperature' => 0.7,
    'messages' => array(
        array(
            'role' => 'user',
            'content' => 'Say "API test successful" and nothing else.'
        )
    )
);

$args = array(
    'headers' => array(
        'x-api-key' => $api_key,
        'anthropic-version' => '2023-06-01',
        'Content-Type' => 'application/json'
    ),
    'body' => json_encode($data),
    'timeout' => 30,
    'method' => 'POST',
    'sslverify' => true,
    'httpversion' => '1.1'
);

echo "   Sending request...\n";
$start_time = microtime(true);

// Use wp_remote_post if available, otherwise use curl
if (function_exists('wp_remote_post')) {
    $response = wp_remote_post($url, $args);
    
    if (is_wp_error($response)) {
        echo "   ❌ WP Error: " . $response->get_error_message() . "\n";
        echo "   Error Code: " . $response->get_error_code() . "\n";
        exit(1);
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
} else {
    // Fallback to curl for CLI testing
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01',
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $body = curl_exec($ch);
    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo "   ❌ CURL Error: " . curl_error($ch) . "\n";
        curl_close($ch);
        exit(1);
    }
    
    curl_close($ch);
}

$execution_time = microtime(true) - $start_time;
echo "   Response received in " . round($execution_time, 2) . " seconds\n";
echo "   HTTP Status: $response_code\n";

// Step 3: Analyze response
echo "\n3. Analyzing response...\n";

if ($response_code === 200) {
    $decoded = json_decode($body, true);
    
    if (isset($decoded['content'][0]['text'])) {
        echo "   ✅ SUCCESS: API responded correctly\n";
        echo "   Response: " . $decoded['content'][0]['text'] . "\n";
        echo "   Model used: " . ($decoded['model'] ?? 'unknown') . "\n";
        echo "   Usage: " . json_encode($decoded['usage'] ?? []) . "\n";
    } else {
        echo "   ❌ ERROR: Unexpected response structure\n";
        echo "   Response: " . substr($body, 0, 500) . "\n";
    }
} else {
    echo "   ❌ ERROR: HTTP $response_code\n";
    
    $decoded = json_decode($body, true);
    if (isset($decoded['error'])) {
        echo "   Error Type: " . ($decoded['error']['type'] ?? 'unknown') . "\n";
        echo "   Error Message: " . ($decoded['error']['message'] ?? 'No message') . "\n";
    } else {
        echo "   Raw response: " . substr($body, 0, 500) . "\n";
    }
    
    // Common error explanations
    if ($response_code === 401) {
        echo "\n   🔧 Solution: Check your API key in settings. Make sure it's valid and has not been revoked.\n";
    } else if ($response_code === 400) {
        echo "\n   🔧 Solution: The selected model might not be available. Try 'claude-3-sonnet-20240229' or 'claude-3-haiku-20240307'.\n";
    } else if ($response_code === 429) {
        echo "\n   🔧 Solution: Rate limit exceeded. Wait a few minutes before trying again.\n";
    } else if ($response_code >= 500) {
        echo "\n   🔧 Solution: Anthropic API service error. Check https://status.anthropic.com/ for outages.\n";
    }
}

// Step 4: Test with actual prompt
if ($response_code === 200) {
    echo "\n4. Testing with restaurant prompt...\n";
    
    $restaurant_prompt = "List 3 popular Italian restaurants (just names, nothing else):";
    
    $data['messages'][0]['content'] = $restaurant_prompt;
    $data['max_tokens'] = 200;
    
    $args['body'] = json_encode($data);
    
    echo "   Sending restaurant query...\n";
    $start_time = microtime(true);
    
    if (function_exists('wp_remote_post')) {
        $response = wp_remote_post($url, $args);
        
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code === 200) {
                $decoded = json_decode($body, true);
                if (isset($decoded['content'][0]['text'])) {
                    echo "   ✅ Restaurant query successful\n";
                    echo "   Response preview: " . substr($decoded['content'][0]['text'], 0, 100) . "...\n";
                }
            }
        }
    }
}

echo "\n=== Test Complete ===\n";

// If running in browser, add HTML formatting
if (php_sapi_name() !== 'cli') {
    $output = ob_get_contents();
    ob_end_clean();
    
    echo '<pre style="background: #f5f5f5; padding: 20px; font-family: monospace;">';
    echo htmlspecialchars($output);
    echo '</pre>';
    
    echo '<p><a href="' . admin_url('admin.php?page=zippicks-master-critic-settings') . '" class="button button-primary">Go to Settings</a></p>';
}