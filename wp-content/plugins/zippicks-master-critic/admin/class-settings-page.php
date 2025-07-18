<?php
/**
 * Settings page class
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Settings_Page {
    
    /**
     * Render the settings page
     */
    public function render() {
        // Handle form submission
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        // Get current values (decrypt API keys)
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php';
        $anthropic_key = ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_anthropic_api_key', '');
        $openai_key = ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_openai_api_key', '');
        $anthropic_model = get_option('zippicks_anthropic_model', 'claude-3-sonnet-20240229');
        $openai_model = get_option('zippicks_openai_model', 'gpt-4-turbo-preview');
        $default_provider = get_option('zippicks_default_ai_provider', 'anthropic');
        $rate_limit_minute = get_option('zippicks_ai_rate_limit_per_minute', 20);
        $rate_limit_day = get_option('zippicks_ai_rate_limit_per_day', 1000);
        $cache_ttl = get_option('zippicks_ai_cache_ttl', 3600);
        $api_timeout = get_option('zippicks_ai_timeout', 120);
        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-admin-settings"></span>
                Master Critic Settings
            </h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>Settings saved successfully!</p>
            </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('zippicks_master_critic_settings', 'settings_nonce'); ?>
                
                <h2>API Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="anthropic_api_key">Anthropic API Key</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="anthropic_api_key" 
                                   name="anthropic_api_key" 
                                   class="regular-text"
                                   value="<?php echo esc_attr($anthropic_key); ?>" />
                            <p class="description">
                                Enter your Anthropic API key for Claude AI. 
                                <a href="https://console.anthropic.com/account/keys" target="_blank">Get your API key</a>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="openai_api_key">OpenAI API Key</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="openai_api_key" 
                                   name="openai_api_key" 
                                   class="regular-text"
                                   value="<?php echo esc_attr($openai_key); ?>" />
                            <p class="description">
                                Enter your OpenAI API key for GPT-4. 
                                <a href="https://platform.openai.com/api-keys" target="_blank">Get your API key</a>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="anthropic_model">Claude Model</label>
                        </th>
                        <td>
                            <select id="anthropic_model" name="anthropic_model" class="regular-text">
                                <option value="claude-3-opus-20240229" <?php selected($anthropic_model, 'claude-3-opus-20240229'); ?>>
                                    Claude 3 Opus (Most capable, highest cost)
                                </option>
                                <option value="claude-3-sonnet-20240229" <?php selected($anthropic_model, 'claude-3-sonnet-20240229'); ?>>
                                    Claude 3 Sonnet (Balanced performance)
                                </option>
                                <option value="claude-3-haiku-20240307" <?php selected($anthropic_model, 'claude-3-haiku-20240307'); ?>>
                                    Claude 3 Haiku (Fastest, lowest cost)
                                </option>
                                <option value="claude-2.1" <?php selected($anthropic_model, 'claude-2.1'); ?>>
                                    Claude 2.1 (Previous generation)
                                </option>
                                <option value="claude-2.0" <?php selected($anthropic_model, 'claude-2.0'); ?>>
                                    Claude 2.0 (Legacy)
                                </option>
                                <option value="claude-instant-1.2" <?php selected($anthropic_model, 'claude-instant-1.2'); ?>>
                                    Claude Instant 1.2 (Fastest legacy)
                                </option>
                            </select>
                            <p class="description">
                                Select which Claude model to use. Claude 3 Sonnet is recommended for balanced performance.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="openai_model">OpenAI Model</label>
                        </th>
                        <td>
                            <select id="openai_model" name="openai_model" class="regular-text">
                                <option value="gpt-4o" <?php selected($openai_model, 'gpt-4o'); ?>>
                                    GPT-4o (Latest - Best for structured output)
                                </option>
                                <option value="gpt-4-turbo" <?php selected($openai_model, 'gpt-4-turbo'); ?>>
                                    GPT-4 Turbo (Recommended - Fast & supports JSON mode)
                                </option>
                                <option value="gpt-4-turbo-preview" <?php selected($openai_model, 'gpt-4-turbo-preview'); ?>>
                                    GPT-4 Turbo Preview
                                </option>
                                <option value="gpt-4" <?php selected($openai_model, 'gpt-4'); ?>>
                                    GPT-4 (Standard - Same as ChatGPT)
                                </option>
                                <option value="gpt-4-1106-preview" <?php selected($openai_model, 'gpt-4-1106-preview'); ?>>
                                    GPT-4 Turbo (1106) Preview
                                </option>
                                <option value="gpt-3.5-turbo" <?php selected($openai_model, 'gpt-3.5-turbo'); ?>>
                                    GPT-3.5 Turbo - Faster, Lower Cost
                                </option>
                            </select>
                            <p class="description">
                                Select which OpenAI model to use. Models with "JSON mode" support provide more reliable structured output.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default_ai_provider">Default AI Provider</label>
                        </th>
                        <td>
                            <select id="default_ai_provider" name="default_ai_provider" class="regular-text">
                                <option value="anthropic" <?php selected($default_provider, 'anthropic'); ?>>
                                    Claude (Anthropic)
                                </option>
                                <option value="openai" <?php selected($default_provider, 'openai'); ?>>
                                    GPT-4 (OpenAI)
                                </option>
                            </select>
                            <p class="description">Choose which AI provider to use by default</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Rate Limiting</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="rate_limit_minute">Requests per Minute</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="rate_limit_minute" 
                                   name="rate_limit_minute" 
                                   class="small-text"
                                   value="<?php echo esc_attr($rate_limit_minute); ?>"
                                   min="1"
                                   max="100" />
                            <p class="description">Maximum API requests allowed per minute (default: 20)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="rate_limit_day">Requests per Day</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="rate_limit_day" 
                                   name="rate_limit_day" 
                                   class="small-text"
                                   value="<?php echo esc_attr($rate_limit_day); ?>"
                                   min="1"
                                   max="10000" />
                            <p class="description">Maximum API requests allowed per day (default: 1000)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="api_timeout">API Timeout</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="api_timeout" 
                                   name="api_timeout" 
                                   class="small-text"
                                   value="<?php echo esc_attr($api_timeout); ?>"
                                   min="30"
                                   max="300" /> seconds
                            <p class="description">
                                Maximum time to wait for API response (default: 120 seconds). 
                                Increase if you're getting timeout errors.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2>Cache Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cache_ttl">Cache Duration</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="cache_ttl" 
                                   name="cache_ttl" 
                                   class="small-text"
                                   value="<?php echo esc_attr($cache_ttl); ?>"
                                   min="0" /> seconds
                            <p class="description">
                                How long to cache AI responses (default: 3600 seconds = 1 hour). 
                                Set to 0 to disable caching.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2>Hybrid Data API Keys</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="google_api_key">Google Places API Key</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="google_api_key" 
                                   name="google_api_key" 
                                   class="regular-text"
                                   value="<?php echo esc_attr(ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_google_api_key', '')); ?>" />
                            <p class="description">
                                For enhanced business data. 
                                <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Get API key</a>
                                (Optional - system works without it)
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="yelp_api_key">Yelp Fusion API Key</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="yelp_api_key" 
                                   name="yelp_api_key" 
                                   class="regular-text"
                                   value="<?php echo esc_attr(ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_yelp_api_key', '')); ?>" />
                            <p class="description">
                                For review data enhancement. 
                                <a href="https://www.yelp.com/developers/v3/manage_app" target="_blank">Get API key</a>
                                (Optional - system works without it)
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
                <h2>ZipBusiness API Integration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="zipbusiness_api_url">ZipBusiness API URL</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="zipbusiness_api_url" 
                                   name="zipbusiness_api_url" 
                                   class="regular-text"
                                   value="<?php echo esc_attr(get_option('zippicks_zipbusiness_api_url', 'https://zipbusiness-api.onrender.com')); ?>" />
                            <p class="description">
                                Enter the ZipBusiness API base URL (e.g., https://zipbusiness-api.onrender.com or http://localhost:8000)
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="zipbusiness_api_key">ZipBusiness API Key</label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="zipbusiness_api_key" 
                                   name="zipbusiness_api_key" 
                                   class="regular-text"
                                   value="<?php echo esc_attr(ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_zipbusiness_api_key', 'SzfHh+mInQWzE8fFIR4WAqkMfZ4KEDDVNAIUAO9kLVg=')); ?>" />
                            <p class="description">
                                Enter your ZipBusiness API authentication key for verified restaurant data
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_api_verification">Enable API Verification</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="enable_api_verification" 
                                       name="enable_api_verification" 
                                       value="1"
                                       <?php checked(get_option('zippicks_enable_api_verification', true)); ?> />
                                Use ZipBusiness API to verify all restaurants are real
                            </label>
                            <p class="description">
                                When enabled, the system will only recommend restaurants that exist in the ZipBusiness database.
                                Disable this for testing or if the API is unavailable.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2>System Status</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Foundation Status</th>
                        <td>
                            <?php if (function_exists('zippicks')): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                ZipPicks Foundation is active
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                                ZipPicks Foundation not detected (limited functionality)
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Database Tables</th>
                        <td>
                            <?php if (ZipPicks_Master_Critic_Installer::tables_exist()): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                All tables created successfully
                            <?php else: ?>
                                <span class="dashicons dashicons-no" style="color: #dc3232;"></span>
                                Tables missing - 
                                <a href="<?php echo admin_url('admin.php?page=zippicks-master-critic&action=create-tables'); ?>" 
                                   class="button button-small">Create Tables</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">ZipBusiness API Status</th>
                        <td>
                            <div id="zipbusiness-api-status">
                                <?php
                                $zipbusiness_key = ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_zipbusiness_api_key', '');
                                if (!empty($zipbusiness_key)) {
                                    // Try to get API status
                                    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/services/class-zipbusiness-api-client.php';
                                    $api_client = new ZipPicks_Master_Critic_ZipBusiness_API_Client();
                                    $status = $api_client->get_api_status();
                                    
                                    if ($status['connected']) {
                                        ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                        Connected to ZipBusiness API (<?php echo esc_html($status['version'] ?? 'Unknown version'); ?>)
                                        <?php
                                    } else {
                                        ?>
                                        <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                                        Network error: <?php echo esc_html($status['error'] ?? 'Cannot connect to ZipBusiness API'); ?>
                                        <?php
                                        if (!empty($status['fallback_error'])) {
                                            ?>
                                            <br><small style="color: #666; margin-left: 28px;">Fallback test also failed: <?php echo esc_html($status['fallback_error']); ?></small>
                                            <?php
                                        }
                                    }
                                } else {
                                    ?>
                                    <span class="dashicons dashicons-no" style="color: #999;"></span>
                                    ZipBusiness API key not configured
                                    <?php
                                }
                                ?>
                            </div>
                            <?php if (!empty($zipbusiness_key)): ?>
                            <p style="margin-top: 10px;">
                                <button type="button" id="test-zipbusiness-api" class="button button-secondary">
                                    <span class="dashicons dashicons-update"></span> Test API Connection
                                </button>
                                <span id="test-api-spinner" class="spinner" style="display: none; float: none; margin: 0 10px;"></span>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">API Keys Status</th>
                        <td>
                            <div style="margin-bottom: 5px;">
                                <?php if (!empty($anthropic_key)): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    Anthropic API key configured
                                <?php else: ?>
                                    <span class="dashicons dashicons-no" style="color: #dc3232;"></span>
                                    Anthropic API key not configured
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if (!empty($openai_key)): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    OpenAI API key configured
                                <?php else: ?>
                                    <span class="dashicons dashicons-no" style="color: #dc3232;"></span>
                                    OpenAI API key not configured
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    
                    
                    <tr>
                        <th scope="row">Core Plugin Status</th>
                        <td>
                            <?php if (post_type_exists('zippicks_business')): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                ZipPicks Core plugin detected
                            <?php else: ?>
                                <span class="dashicons dashicons-info" style="color: #0073aa;"></span>
                                Using fallback post types
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <hr />
            
            <h2>Quick Actions</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=zippicks-master-critic'); ?>" 
                   class="button">Go to AI Generator</a>
                <a href="<?php echo admin_url('admin.php?page=zippicks-master-critic-history'); ?>" 
                   class="button">View Generation History</a>
                <a href="<?php echo admin_url('admin.php?page=zippicks-master-critic-templates'); ?>" 
                   class="button">Manage Templates</a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['settings_nonce'], 'zippicks_master_critic_settings')) {
            wp_die('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access - you do not have permission to save these settings');
        }
        
        // Save API keys (encrypted)
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php';
        ZipPicks_Master_Critic_Security::save_encrypted_option('zippicks_anthropic_api_key', sanitize_text_field($_POST['anthropic_api_key']));
        ZipPicks_Master_Critic_Security::save_encrypted_option('zippicks_openai_api_key', sanitize_text_field($_POST['openai_api_key']));
        update_option('zippicks_anthropic_model', sanitize_text_field($_POST['anthropic_model']));
        update_option('zippicks_openai_model', sanitize_text_field($_POST['openai_model']));
        update_option('zippicks_default_ai_provider', sanitize_text_field($_POST['default_ai_provider']));
        
        // Save hybrid API keys
        ZipPicks_Master_Critic_Security::save_encrypted_option('zippicks_google_api_key', sanitize_text_field($_POST['google_api_key']));
        ZipPicks_Master_Critic_Security::save_encrypted_option('zippicks_yelp_api_key', sanitize_text_field($_POST['yelp_api_key']));
        
        // Save rate limits
        update_option('zippicks_ai_rate_limit_per_minute', intval($_POST['rate_limit_minute']));
        update_option('zippicks_ai_rate_limit_per_day', intval($_POST['rate_limit_day']));
        
        // Save timeout setting
        update_option('zippicks_ai_timeout', intval($_POST['api_timeout']));
        
        // Save cache settings
        update_option('zippicks_ai_cache_ttl', intval($_POST['cache_ttl']));
        
        // Save ZipBusiness API settings
        update_option('zippicks_zipbusiness_api_url', esc_url_raw($_POST['zipbusiness_api_url']));
        ZipPicks_Master_Critic_Security::save_encrypted_option('zippicks_zipbusiness_api_key', sanitize_text_field($_POST['zipbusiness_api_key']));
        update_option('zippicks_enable_api_verification', !empty($_POST['enable_api_verification']));
        
        // Redirect with success message
        wp_redirect(admin_url('admin.php?page=zippicks-master-critic-settings&settings-updated=1'));
        exit;
    }
}