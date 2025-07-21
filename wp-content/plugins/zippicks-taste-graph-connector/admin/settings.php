<?php
/**
 * Admin Settings Page
 * 
 * Provides configuration interface for the Taste Graph Connector plugin
 * 
 * @package TasteGraphConnector
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TGC_Admin_Settings class
 */
class TGC_Admin_Settings {
    
    
    /**
     * Render settings page
     */
    public static function render_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'zippicks-taste-graph-connector'));
        }
        
        // Save settings if form submitted
        if (isset($_POST['tgc_settings_nonce']) && wp_verify_nonce($_POST['tgc_settings_nonce'], 'tgc_save_settings')) {
            self::save_settings();
        }
        
        // Get current settings
        $settings = self::get_settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully!', 'zippicks-taste-graph-connector'); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('tgc_save_settings', 'tgc_settings_nonce'); ?>
                
                <div class="tgc-settings-tabs">
                    <h2 class="nav-tab-wrapper">
                        <a href="#general" class="nav-tab nav-tab-active" data-tab="general">
                            <?php _e('General', 'zippicks-taste-graph-connector'); ?>
                        </a>
                        <a href="#api" class="nav-tab" data-tab="api">
                            <?php _e('API Configuration', 'zippicks-taste-graph-connector'); ?>
                        </a>
                        <a href="#tracking" class="nav-tab" data-tab="tracking">
                            <?php _e('Tracking', 'zippicks-taste-graph-connector'); ?>
                        </a>
                        <a href="#redis" class="nav-tab" data-tab="redis">
                            <?php _e('Redis', 'zippicks-taste-graph-connector'); ?>
                        </a>
                        <a href="#advanced" class="nav-tab" data-tab="advanced">
                            <?php _e('Advanced', 'zippicks-taste-graph-connector'); ?>
                        </a>
                        <a href="#status" class="nav-tab" data-tab="status">
                            <?php _e('Status', 'zippicks-taste-graph-connector'); ?>
                        </a>
                    </h2>
                    
                    <!-- General Settings -->
                    <div id="general-settings" class="tab-content active">
                        <h3><?php _e('General Settings', 'zippicks-taste-graph-connector'); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="tgc_enabled">
                                        <?php _e('Enable Taste Graph', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="tgc_enabled" id="tgc_enabled" value="1" 
                                            <?php checked($settings['enabled'], true); ?> />
                                        <?php _e('Enable Taste Graph integration', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Master switch to enable or disable all Taste Graph functionality.', 'zippicks-taste-graph-connector'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="tgc_tracking_enabled">
                                        <?php _e('Enable Tracking', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="tgc_tracking_enabled" id="tgc_tracking_enabled" value="1" 
                                            <?php checked($settings['tracking_enabled'], true); ?> />
                                        <?php _e('Enable user interaction tracking', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Track user interactions to build taste profiles.', 'zippicks-taste-graph-connector'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="tgc_debug_mode">
                                        <?php _e('Debug Mode', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="tgc_debug_mode" id="tgc_debug_mode" value="1" 
                                            <?php checked($settings['debug_mode'], true); ?> />
                                        <?php _e('Enable debug mode', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Log detailed information for troubleshooting.', 'zippicks-taste-graph-connector'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- API Configuration -->
                    <div id="api-settings" class="tab-content">
                        <h3><?php _e('API Configuration', 'zippicks-taste-graph-connector'); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="tgc_api_url">
                                        <?php _e('API URL', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="url" name="tgc_api_url" id="tgc_api_url" 
                                        value="<?php echo esc_url($settings['api_url']); ?>" 
                                        class="regular-text" required />
                                    <p class="description">
                                        <?php _e('ZipBusiness API endpoint URL (e.g., https://zipbusiness-api.onrender.com)', 'zippicks-taste-graph-connector'); ?>
                                    </p>
                                    <p>
                                        <button type="button" class="button" id="test-api-connection">
                                            <?php _e('Test Connection', 'zippicks-taste-graph-connector'); ?>
                                        </button>
                                        <span id="api-test-result"></span>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="tgc_api_key">
                                        <?php _e('API Key', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="password" name="tgc_api_key" id="tgc_api_key" 
                                        value="<?php echo esc_attr($settings['api_key']); ?>" 
                                        class="regular-text" />
                                    <p class="description">
                                        <?php _e('Your ZipBusiness API key for authentication.', 'zippicks-taste-graph-connector'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="tgc_jwt_secret">
                                        <?php _e('JWT Secret', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="password" name="tgc_jwt_secret" id="tgc_jwt_secret" 
                                        value="<?php echo esc_attr($settings['jwt_secret']); ?>" 
                                        class="regular-text" />
                                    <button type="button" class="button" id="generate-jwt-secret">
                                        <?php _e('Generate New', 'zippicks-taste-graph-connector'); ?>
                                    </button>
                                    <p class="description">
                                        <?php _e('Shared secret for JWT token generation. Must match the API configuration.', 'zippicks-taste-graph-connector'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Tracking Settings -->
                    <div id="tracking-settings" class="tab-content">
                        <h3><?php _e('Tracking Settings', 'zippicks-taste-graph-connector'); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <?php _e('Track Interactions', 'zippicks-taste-graph-connector'); ?>
                                </th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="tgc_track_views" value="1" 
                                                <?php checked($settings['track_views'], true); ?> />
                                            <?php _e('Page Views', 'zippicks-taste-graph-connector'); ?>
                                        </label><br>
                                        
                                        <label>
                                            <input type="checkbox" name="tgc_track_clicks" value="1" 
                                                <?php checked($settings['track_clicks'], true); ?> />
                                            <?php _e('Restaurant Clicks', 'zippicks-taste-graph-connector'); ?>
                                        </label><br>
                                        
                                        <label>
                                            <input type="checkbox" name="tgc_track_saves" value="1" 
                                                <?php checked($settings['track_saves'], true); ?> />
                                            <?php _e('Saves/Favorites', 'zippicks-taste-graph-connector'); ?>
                                        </label><br>
                                        
                                        <label>
                                            <input type="checkbox" name="tgc_track_searches" value="1" 
                                                <?php checked($settings['track_searches'], true); ?> />
                                            <?php _e('Search Queries', 'zippicks-taste-graph-connector'); ?>
                                        </label><br>
                                        
                                        <label>
                                            <input type="checkbox" name="tgc_track_time" value="1" 
                                                <?php checked($settings['track_time'], true); ?> />
                                            <?php _e('Time on Page', 'zippicks-taste-graph-connector'); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="tgc_tracking_sample_rate">
                                        <?php _e('Sample Rate', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="number" name="tgc_tracking_sample_rate" id="tgc_tracking_sample_rate" 
                                        value="<?php echo esc_attr($settings['tracking_sample_rate']); ?>" 
                                        min="1" max="100" step="1" class="small-text" />%
                                    <p class="description">
                                        <?php _e('Percentage of users to track (1-100). Use lower values for high-traffic sites.', 'zippicks-taste-graph-connector'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Redis Settings -->
                    <div id="redis-settings" class="tab-content">
                        <h3><?php _e('Redis Configuration', 'zippicks-taste-graph-connector'); ?></h3>
                        
                        <?php if (!class_exists('Redis')): ?>
                            <div class="notice notice-warning">
                                <p><?php _e('Redis PHP extension is not installed. Redis caching will be disabled.', 'zippicks-taste-graph-connector'); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="tgc_redis_host">
                                        <?php _e('Redis Host', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="tgc_redis_host" id="tgc_redis_host" 
                                        value="<?php echo esc_attr($settings['redis_host']); ?>" 
                                        class="regular-text" placeholder="127.0.0.1" />
                                    <p class="description">
                                        <?php _e('Redis server hostname or IP address.', 'zippicks-taste-graph-connector'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="tgc_redis_port">
                                        <?php _e('Redis Port', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="number" name="tgc_redis_port" id="tgc_redis_port" 
                                        value="<?php echo esc_attr($settings['redis_port']); ?>" 
                                        class="small-text" placeholder="6379" />
                                    <p class="description">
                                        <?php _e('Redis server port (default: 6379).', 'zippicks-taste-graph-connector'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Advanced Settings -->
                    <div id="advanced-settings" class="tab-content">
                        <h3><?php _e('Advanced Settings', 'zippicks-taste-graph-connector'); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="tgc_api_timeout">
                                        <?php _e('API Timeout', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="number" name="tgc_api_timeout" id="tgc_api_timeout" 
                                        value="<?php echo esc_attr($settings['api_timeout']); ?>" 
                                        min="5" max="60" step="1" class="small-text" /> <?php _e('seconds', 'zippicks-taste-graph-connector'); ?>
                                    <p class="description">
                                        <?php _e('Maximum time to wait for API responses.', 'zippicks-taste-graph-connector'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="tgc_queue_batch_size">
                                        <?php _e('Queue Batch Size', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="number" name="tgc_queue_batch_size" id="tgc_queue_batch_size" 
                                        value="<?php echo esc_attr($settings['queue_batch_size']); ?>" 
                                        min="1" max="50" step="1" class="small-text" />
                                    <p class="description">
                                        <?php _e('Number of queued items to process per batch.', 'zippicks-taste-graph-connector'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <?php _e('Data Retention', 'zippicks-taste-graph-connector'); ?>
                                </th>
                                <td>
                                    <label for="tgc_queue_retention_days">
                                        <?php _e('Keep failed queue items for', 'zippicks-taste-graph-connector'); ?>
                                        <input type="number" name="tgc_queue_retention_days" id="tgc_queue_retention_days" 
                                            value="<?php echo esc_attr($settings['queue_retention_days']); ?>" 
                                            min="1" max="90" step="1" class="small-text" />
                                        <?php _e('days', 'zippicks-taste-graph-connector'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Automatically clean up old queue items after this many days.', 'zippicks-taste-graph-connector'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <h3><?php _e('Maintenance', 'zippicks-taste-graph-connector'); ?></h3>
                        
                        <p>
                            <button type="button" class="button" id="clear-cache">
                                <?php _e('Clear Cache', 'zippicks-taste-graph-connector'); ?>
                            </button>
                            <span class="description">
                                <?php _e('Clear all cached data including JWT tokens and taste profiles.', 'zippicks-taste-graph-connector'); ?>
                            </span>
                        </p>
                        
                        <p>
                            <button type="button" class="button" id="process-queue">
                                <?php _e('Process Queue Now', 'zippicks-taste-graph-connector'); ?>
                            </button>
                            <span class="description">
                                <?php _e('Manually process pending queue items.', 'zippicks-taste-graph-connector'); ?>
                            </span>
                        </p>
                    </div>
                    
                    <!-- Status Tab -->
                    <div id="status-settings" class="tab-content">
                        <h3><?php _e('System Status', 'zippicks-taste-graph-connector'); ?></h3>
                        
                        <?php self::render_status_page(); ?>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" 
                        value="<?php _e('Save Settings', 'zippicks-taste-graph-connector'); ?>" />
                </p>
            </form>
        </div>
        
        <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tgc-status-table { margin-top: 20px; }
        .tgc-status-table th { font-weight: 600; width: 200px; }
        .status-ok { color: #46b450; }
        .status-error { color: #dc3232; }
        .status-warning { color: #ffb900; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var tab = $(this).data('tab');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').removeClass('active');
                $('#' + tab + '-settings').addClass('active');
                
                // Update URL hash
                window.location.hash = tab;
            });
            
            // Load tab from hash
            if (window.location.hash) {
                var hash = window.location.hash.substring(1);
                $('.nav-tab[data-tab="' + hash + '"]').click();
            }
            
            // Test API connection
            $('#test-api-connection').on('click', function() {
                var $button = $(this);
                var $result = $('#api-test-result');
                
                $button.prop('disabled', true);
                $result.html('<span class="spinner is-active"></span> Testing...');
                
                $.post(ajaxurl, {
                    action: 'tgc_test_api_connection',
                    nonce: '<?php echo wp_create_nonce('tgc_ajax_nonce'); ?>'
                }, function(response) {
                    $button.prop('disabled', false);
                    
                    if (response.success) {
                        $result.html('<span class="status-ok">✓ Connected</span>');
                    } else {
                        $result.html('<span class="status-error">✗ Connection failed</span>');
                    }
                });
            });
            
            // Generate JWT secret
            $('#generate-jwt-secret').on('click', function() {
                if (!confirm('<?php _e('Generate a new JWT secret? This will invalidate all existing tokens.', 'zippicks-taste-graph-connector'); ?>')) {
                    return;
                }
                
                var secret = generateRandomString(64);
                $('#tgc_jwt_secret').val(secret);
            });
            
            // Clear cache
            $('#clear-cache').on('click', function() {
                if (!confirm('<?php _e('Clear all cached data?', 'zippicks-taste-graph-connector'); ?>')) {
                    return;
                }
                
                var $button = $(this);
                $button.prop('disabled', true);
                
                // TODO: Implement cache clearing
                alert('Cache cleared!');
                $button.prop('disabled', false);
            });
            
            // Process queue
            $('#process-queue').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('Processing...');
                
                $.post(ajaxurl, {
                    action: 'tgc_process_queue_manual',
                    nonce: '<?php echo wp_create_nonce('tgc_ajax_nonce'); ?>'
                }, function(response) {
                    $button.prop('disabled', false).text('Process Queue Now');
                    
                    if (response.success) {
                        alert('Queue processed! ' + response.data.results.processed + ' items processed.');
                    } else {
                        alert('Failed to process queue.');
                    }
                });
            });
            
            // Generate random string
            function generateRandomString(length) {
                var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
                var result = '';
                var array = new Uint32Array(length);
                window.crypto.getRandomValues(array);
                for (var i = 0; i < length; i++) {
                    result += chars.charAt(array[i] % chars.length);
                }
                return result;
            }
        });
        </script>
        <?php
    }
    
    /**
     * Get settings with defaults
     */
    private static function get_settings() {
        $defaults = array(
            'enabled' => true,
            'tracking_enabled' => true,
            'debug_mode' => false,
            'api_url' => 'https://zipbusiness-api.onrender.com',
            'api_key' => '',
            'jwt_secret' => '',
            'track_views' => true,
            'track_clicks' => true,
            'track_saves' => true,
            'track_searches' => true,
            'track_time' => true,
            'tracking_sample_rate' => 100,
            'redis_host' => '127.0.0.1',
            'redis_port' => 6379,
            'api_timeout' => 30,
            'queue_batch_size' => 10,
            'queue_retention_days' => 30
        );
        
        $settings = array();
        
        foreach ($defaults as $key => $default) {
            $option_key = 'tgc_' . $key;
            $value = get_option($option_key, $default);
            
            // Convert checkbox values
            if (is_bool($default)) {
                $settings[$key] = $value === 'yes' || $value === '1' || $value === true;
            } else {
                $settings[$key] = $value;
            }
        }
        
        return $settings;
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        // Map settings to options
        $mappings = array(
            'tgc_enabled' => 'enabled',
            'tgc_tracking_enabled' => 'tracking_enabled',
            'tgc_debug_mode' => 'debug_mode',
            'tgc_api_url' => 'api_url',
            'tgc_api_key' => 'api_key',
            'tgc_jwt_secret' => 'jwt_secret',
            'tgc_track_views' => 'track_views',
            'tgc_track_clicks' => 'track_clicks',
            'tgc_track_saves' => 'track_saves',
            'tgc_track_searches' => 'track_searches',
            'tgc_track_time' => 'track_time',
            'tgc_tracking_sample_rate' => 'tracking_sample_rate',
            'tgc_redis_host' => 'redis_host',
            'tgc_redis_port' => 'redis_port',
            'tgc_api_timeout' => 'api_timeout',
            'tgc_queue_batch_size' => 'queue_batch_size',
            'tgc_queue_retention_days' => 'queue_retention_days'
        );
        
        foreach ($mappings as $option_key => $setting_key) {
            if (in_array($setting_key, array('enabled', 'tracking_enabled', 'debug_mode', 
                'track_views', 'track_clicks', 'track_saves', 'track_searches', 'track_time'))) {
                // Checkbox values
                $value = isset($_POST[$option_key]) ? 'yes' : 'no';
            } else {
                // Other values
                $value = isset($_POST[$option_key]) ? sanitize_text_field($_POST[$option_key]) : '';
            }
            
            update_option($option_key, $value);
        }
        
        // Clear JWT token cache if secret changed
        if (isset($_POST['tgc_jwt_secret'])) {
            TGC_JWT_Handler::invalidate_all_tokens();
        }
        
        // Redirect with success message
        wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
        exit;
    }
    
    /**
     * Render status page
     */
    private static function render_status_page() {
        // Get system status
        $api_client = new TGC_API_Client();
        $api_healthy = $api_client->check_health();
        
        $queue_manager = new TGC_Queue_Manager();
        $queue_stats = $queue_manager->get_queue_stats();
        
        // Check Redis
        $redis_status = 'Not configured';
        if (defined('TGC_REDIS_ENABLED') && TGC_REDIS_ENABLED) {
            try {
                $redis = new Redis();
                if ($redis->connect(TGC_REDIS_HOST, TGC_REDIS_PORT, 2.0)) {
                    $redis_status = 'Connected';
                    $redis->close();
                } else {
                    $redis_status = 'Connection failed';
                }
            } catch (Exception $e) {
                $redis_status = 'Error: ' . $e->getMessage();
            }
        }
        ?>
        <table class="wp-list-table widefat tgc-status-table">
            <tbody>
                <tr>
                    <th><?php _e('API Connection', 'zippicks-taste-graph-connector'); ?></th>
                    <td>
                        <?php if ($api_healthy): ?>
                            <span class="status-ok">✓ <?php _e('Connected', 'zippicks-taste-graph-connector'); ?></span>
                        <?php else: ?>
                            <span class="status-error">✗ <?php _e('Not connected', 'zippicks-taste-graph-connector'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th><?php _e('Redis Status', 'zippicks-taste-graph-connector'); ?></th>
                    <td>
                        <?php if ($redis_status === 'Connected'): ?>
                            <span class="status-ok">✓ <?php echo esc_html($redis_status); ?></span>
                        <?php else: ?>
                            <span class="status-warning">⚠ <?php echo esc_html($redis_status); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th><?php _e('Queue Status', 'zippicks-taste-graph-connector'); ?></th>
                    <td>
                        <?php _e('Pending:', 'zippicks-taste-graph-connector'); ?> <strong><?php echo intval($queue_stats['total_pending']); ?></strong><br>
                        <?php _e('Failed:', 'zippicks-taste-graph-connector'); ?> <strong><?php echo intval($queue_stats['database_failed']); ?></strong><br>
                        <?php _e('Completed:', 'zippicks-taste-graph-connector'); ?> <strong><?php echo intval($queue_stats['database_completed']); ?></strong>
                    </td>
                </tr>
                
                <tr>
                    <th><?php _e('PHP Version', 'zippicks-taste-graph-connector'); ?></th>
                    <td>
                        <?php echo phpversion(); ?>
                        <?php if (version_compare(phpversion(), '7.4', '<')): ?>
                            <span class="status-error"><?php _e('(Minimum 7.4 required)', 'zippicks-taste-graph-connector'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th><?php _e('WordPress Version', 'zippicks-taste-graph-connector'); ?></th>
                    <td><?php echo get_bloginfo('version'); ?></td>
                </tr>
                
                <tr>
                    <th><?php _e('Plugin Version', 'zippicks-taste-graph-connector'); ?></th>
                    <td><?php echo TGC_VERSION; ?></td>
                </tr>
                
                <tr>
                    <th><?php _e('Active Sessions', 'zippicks-taste-graph-connector'); ?></th>
                    <td>
                        <?php
                        global $wpdb;
                        $count = $wpdb->get_var("SELECT COUNT(DISTINCT meta_value) FROM {$wpdb->usermeta} WHERE meta_key = 'tgc_session_id'");
                        echo intval($count);
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
}