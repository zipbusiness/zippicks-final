<?php
/**
 * Test API Connection
 * 
 * This file tests the ZipBusiness API connection through the Business Intelligence plugin
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Check if user is admin
if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Get services
if (!function_exists('zippicks')) {
    wp_die('ZipPicks Foundation not found');
}

$config = zippicks()->get('business_intelligence.config');
$api_client = zippicks()->get('business_intelligence.api_client');
$business_service = zippicks()->get('business_intelligence.service');

if (!$config || !$api_client || !$business_service) {
    wp_die('Business Intelligence services not initialized');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>ZipBusiness API Connection Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .test-section {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .success { color: #46b450; }
        .error { color: #dc3232; }
        .info { color: #00669b; }
        pre {
            background: #23282d;
            color: #f1f1f1;
            padding: 10px;
            overflow-x: auto;
            border-radius: 3px;
        }
        .config-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .config-table th, .config-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .config-table th {
            background: #f0f0f0;
        }
    </style>
</head>
<body>
    <h1>ZipBusiness API Connection Test</h1>
    
    <div class="test-section">
        <h2>1. Configuration Status</h2>
        <?php
        $api_url = $config->get('api_url');
        $api_key = $config->get('api_key');
        $has_key = !empty($api_key);
        ?>
        <table class="config-table">
            <tr>
                <th>Setting</th>
                <th>Value</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>API URL</td>
                <td><?php echo esc_html($api_url); ?></td>
                <td class="<?php echo !empty($api_url) ? 'success' : 'error'; ?>">
                    <?php echo !empty($api_url) ? '✓ Configured' : '✗ Missing'; ?>
                </td>
            </tr>
            <tr>
                <td>API Key</td>
                <td><?php echo $has_key ? '••••••••' . substr($api_key, -4) : 'Not Set'; ?></td>
                <td class="<?php echo $has_key ? 'success' : 'error'; ?>">
                    <?php echo $has_key ? '✓ Configured' : '✗ Missing'; ?>
                </td>
            </tr>
            <tr>
                <td>Configuration Source</td>
                <td colspan="2">
                    <?php
                    // Check if settings match Master Critic
                    $mc_url = get_option('zippicks_zipbusiness_api_url', '');
                    $mc_key = '';
                    if (class_exists('ZipPicks_Master_Critic_Security')) {
                        require_once WP_PLUGIN_DIR . '/zippicks-master-critic/includes/class-security.php';
                        $mc_key = ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_zipbusiness_api_key', '');
                    }
                    
                    echo '<span class="info">✓ Using independent Business Intelligence settings</span>';
                    
                    if (!empty($mc_url) && $mc_url === $api_url && !empty($mc_key) && $mc_key === $api_key) {
                        echo '<br><small class="description">Settings match Master Critic configuration</small>';
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="test-section">
        <h2>2. API Health Check</h2>
        <?php
        try {
            $health = $api_client->health_check();
            if ($health['status'] === 'healthy') {
                echo '<p class="success">✓ API is healthy</p>';
                echo '<p>Response time: ' . $health['response_time'] . 'ms</p>';
                echo '<p>API version: ' . $health['api_version'] . '</p>';
            } else {
                echo '<p class="error">✗ API is unhealthy</p>';
                echo '<p>Error: ' . esc_html($health['error']) . '</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">✗ Health check failed: ' . esc_html($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>3. Test City Data Fetch</h2>
        <?php
        $test_city = 'Berkeley';
        $test_state = 'CA';
        
        echo "<p>Testing fetch for: <strong>{$test_city}, {$test_state}</strong></p>";
        
        try {
            $start = microtime(true);
            $businesses = $business_service->get_city_businesses($test_city, $test_state);
            $duration = round((microtime(true) - $start) * 1000, 2);
            
            $count = count($businesses);
            echo '<p class="success">✓ Successfully fetched ' . $count . ' businesses in ' . $duration . 'ms</p>';
            
            if ($count > 0) {
                echo '<h3>Sample Business Data:</h3>';
                $sample = $businesses[0];
                echo '<pre>' . json_encode($sample->to_array(), JSON_PRETTY_PRINT) . '</pre>';
            }
            
        } catch (Exception $e) {
            echo '<p class="error">✗ Fetch failed: ' . esc_html($e->getMessage()) . '</p>';
            
            // Check if it's an API key issue
            if (strpos($e->getMessage(), 'API key') !== false) {
                echo '<p class="info">ℹ Make sure the API key is configured in the Master Critic settings.</p>';
            }
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>4. Cache Status</h2>
        <?php
        $cache_stats = $business_service->get_statistics();
        ?>
        <table class="config-table">
            <tr>
                <td>Total Businesses Cached</td>
                <td><?php echo number_format($cache_stats['total_businesses_cached']); ?></td>
            </tr>
            <tr>
                <td>Total Cities</td>
                <td><?php echo number_format($cache_stats['total_cities']); ?></td>
            </tr>
            <tr>
                <td>API Requests Today</td>
                <td><?php echo number_format($cache_stats['api_requests_today']); ?></td>
            </tr>
            <tr>
                <td>API Errors Today</td>
                <td><?php echo number_format($cache_stats['api_errors_today']); ?></td>
            </tr>
            <tr>
                <td>Avg Response Time</td>
                <td><?php echo $cache_stats['avg_api_response_time']; ?>s</td>
            </tr>
        </table>
    </div>
    
    <div class="test-section">
        <h2>5. Database Tables</h2>
        <?php
        global $wpdb;
        $tables = [
            'zippicks_business_cache' => 'Business cache table',
            'zippicks_bi_api_log' => 'API log table'
        ];
        
        foreach ($tables as $table => $description) {
            $full_table = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table}'") === $full_table;
            
            echo '<p class="' . ($exists ? 'success' : 'error') . '">';
            echo ($exists ? '✓' : '✗') . ' ' . $description . ' (' . $full_table . ')';
            echo '</p>';
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>Actions</h2>
        <p>
            <a href="<?php echo admin_url('admin.php?page=zippicks-business-intelligence'); ?>" class="button">
                Go to Business Intelligence Dashboard
            </a>
            <a href="<?php echo admin_url('admin.php?page=zippicks-master-critic-settings'); ?>" class="button">
                Configure API Settings in Master Critic
            </a>
        </p>
    </div>
</body>
</html>