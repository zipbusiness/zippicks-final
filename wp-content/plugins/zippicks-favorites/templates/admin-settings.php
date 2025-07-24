<?php
/**
 * Admin Settings Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['zippicks_favorites_nonce'], 'zippicks_favorites_settings')) {
    update_option('zippicks_favorites_api_endpoint', sanitize_url($_POST['api_endpoint']));
    update_option('zippicks_api_key', sanitize_text_field($_POST['api_key']));
    update_option('zippicks_favorites_cache_ttl', intval($_POST['cache_ttl']));
    update_option('zippicks_favorites_location_radius_default', intval($_POST['default_radius']));
    
    echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'zippicks-favorites') . '</p></div>';
}

// Get current settings
$api_endpoint = get_option('zippicks_favorites_api_endpoint', 'https://api.zippicks.com/v1');
$api_key = get_option('zippicks_api_key', '');
$cache_ttl = get_option('zippicks_favorites_cache_ttl', 300);
$default_radius = get_option('zippicks_favorites_location_radius_default', 5);

// Test API connection
$api_status = 'unchecked';
if (!empty($api_key) && isset($_GET['test_connection'])) {
    $api_client = new \ZipPicks\Favorites\API\Client();
    try {
        $api_client->get('/health');
        $api_status = 'connected';
    } catch (\Exception $e) {
        $api_status = 'error';
        $api_error = $e->getMessage();
    }
}
?>

<div class="wrap">
    <h1><?php _e('ZipPicks Favorites Settings', 'zippicks-favorites'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('zippicks_favorites_settings', 'zippicks_favorites_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="api_endpoint"><?php _e('API Endpoint', 'zippicks-favorites'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="api_endpoint" 
                           name="api_endpoint" 
                           value="<?php echo esc_attr($api_endpoint); ?>" 
                           class="regular-text"
                           required>
                    <p class="description">
                        <?php _e('The base URL for the ZipPicks API (e.g., https://api.zippicks.com/v1)', 'zippicks-favorites'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="api_key"><?php _e('API Key', 'zippicks-favorites'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="api_key" 
                           name="api_key" 
                           value="<?php echo esc_attr($api_key); ?>" 
                           class="regular-text"
                           autocomplete="off">
                    <p class="description">
                        <?php _e('Your ZipPicks API key for authentication', 'zippicks-favorites'); ?>
                    </p>
                    
                    <?php if (!empty($api_key)): ?>
                        <p>
                            <a href="<?php echo esc_url(add_query_arg('test_connection', '1')); ?>" 
                               class="button button-secondary">
                                <?php _e('Test Connection', 'zippicks-favorites'); ?>
                            </a>
                            
                            <?php if ($api_status === 'connected'): ?>
                                <span class="dashicons dashicons-yes" style="color: green;"></span>
                                <?php _e('Connected successfully!', 'zippicks-favorites'); ?>
                            <?php elseif ($api_status === 'error'): ?>
                                <span class="dashicons dashicons-no" style="color: red;"></span>
                                <?php printf(__('Connection failed: %s', 'zippicks-favorites'), $api_error); ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="cache_ttl"><?php _e('Cache Duration', 'zippicks-favorites'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="cache_ttl" 
                           name="cache_ttl" 
                           value="<?php echo esc_attr($cache_ttl); ?>" 
                           min="60" 
                           max="3600" 
                           step="60">
                    <span><?php _e('seconds', 'zippicks-favorites'); ?></span>
                    <p class="description">
                        <?php _e('How long to cache API responses (60-3600 seconds)', 'zippicks-favorites'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="default_radius"><?php _e('Default Search Radius', 'zippicks-favorites'); ?></label>
                </th>
                <td>
                    <select id="default_radius" name="default_radius">
                        <option value="5" <?php selected($default_radius, 5); ?>>5 miles</option>
                        <option value="10" <?php selected($default_radius, 10); ?>>10 miles</option>
                        <option value="25" <?php selected($default_radius, 25); ?>>25 miles</option>
                        <option value="50" <?php selected($default_radius, 50); ?>>50 miles</option>
                    </select>
                    <p class="description">
                        <?php _e('Default radius for location-based searches', 'zippicks-favorites'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <hr>
    
    <h2><?php _e('Usage Statistics', 'zippicks-favorites'); ?></h2>
    
    <?php
    // Get some basic stats
    global $wpdb;
    $total_users_with_favorites = $wpdb->get_var("
        SELECT COUNT(DISTINCT user_id) 
        FROM {$wpdb->usermeta} 
        WHERE meta_key LIKE '_zp_favorite_%'
    ");
    
    $cache_size = 0;
    if (function_exists('wp_cache_get_stats')) {
        $stats = wp_cache_get_stats();
        $cache_size = isset($stats['zippicks_favorites']) ? $stats['zippicks_favorites']['size'] : 0;
    }
    ?>
    
    <table class="widefat">
        <thead>
            <tr>
                <th><?php _e('Metric', 'zippicks-favorites'); ?></th>
                <th><?php _e('Value', 'zippicks-favorites'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php _e('Users with Favorites', 'zippicks-favorites'); ?></td>
                <td><?php echo number_format($total_users_with_favorites); ?></td>
            </tr>
            <tr>
                <td><?php _e('Cache Size', 'zippicks-favorites'); ?></td>
                <td><?php echo size_format($cache_size); ?></td>
            </tr>
            <tr>
                <td><?php _e('API Endpoint', 'zippicks-favorites'); ?></td>
                <td><?php echo esc_html($api_endpoint); ?></td>
            </tr>
            <tr>
                <td><?php _e('Plugin Version', 'zippicks-favorites'); ?></td>
                <td><?php echo ZIPPICKS_FAVORITES_VERSION; ?></td>
            </tr>
        </tbody>
    </table>
    
    <p class="description">
        <?php _e('Note: Favorites data is stored in the external Postgres database, not in WordPress.', 'zippicks-favorites'); ?>
    </p>
</div>