<?php
/**
 * Settings Page
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Admin;

use ZipPicks\BusinessIntelligence\Services\ConfigService;

class SettingsPage {
    
    /**
     * Configuration service
     *
     * @var ConfigService
     */
    private $config;
    
    /**
     * Constructor
     *
     * @param ConfigService $config
     */
    public function __construct(ConfigService $config) {
        $this->config = $config;
    }
    
    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_submenu_page(
            'zippicks-business-intelligence',
            __('Settings', 'zippicks-business-intelligence'),
            __('Settings', 'zippicks-business-intelligence'),
            'manage_business_intelligence',
            'zippicks-bi-settings',
            [$this, 'display_settings']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // API Settings Section
        add_settings_section(
            'zippicks_bi_api_settings',
            __('API Configuration', 'zippicks-business-intelligence'),
            [$this, 'api_section_callback'],
            'zippicks-bi-settings'
        );
        
        // API URL
        register_setting('zippicks_bi_settings', 'zippicks_bi_api_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'https://zipbusiness-api.onrender.com'
        ]);
        
        add_settings_field(
            'zippicks_bi_api_url',
            __('API URL', 'zippicks-business-intelligence'),
            [$this, 'api_url_field_callback'],
            'zippicks-bi-settings',
            'zippicks_bi_api_settings'
        );
        
        // API Key
        register_setting('zippicks_bi_settings', 'zippicks_bi_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        add_settings_field(
            'zippicks_bi_api_key',
            __('API Key', 'zippicks-business-intelligence'),
            [$this, 'api_key_field_callback'],
            'zippicks-bi-settings',
            'zippicks_bi_api_settings'
        );
        
        // Cache Settings Section
        add_settings_section(
            'zippicks_bi_cache_settings',
            __('Cache Configuration', 'zippicks-business-intelligence'),
            [$this, 'cache_section_callback'],
            'zippicks-bi-settings'
        );
        
        // Cache TTL
        register_setting('zippicks_bi_settings', 'zippicks_bi_cache_ttl', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 3600
        ]);
        
        add_settings_field(
            'zippicks_bi_cache_ttl',
            __('Cache Duration (seconds)', 'zippicks-business-intelligence'),
            [$this, 'cache_ttl_field_callback'],
            'zippicks-bi-settings',
            'zippicks_bi_cache_settings'
        );
        
        // Redis Settings Section
        add_settings_section(
            'zippicks_bi_redis_settings',
            __('Redis Configuration (Optional)', 'zippicks-business-intelligence'),
            [$this, 'redis_section_callback'],
            'zippicks-bi-settings'
        );
        
        // Redis Host
        register_setting('zippicks_bi_settings', 'zippicks_bi_redis_host', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '127.0.0.1'
        ]);
        
        add_settings_field(
            'zippicks_bi_redis_host',
            __('Redis Host', 'zippicks-business-intelligence'),
            [$this, 'redis_host_field_callback'],
            'zippicks-bi-settings',
            'zippicks_bi_redis_settings'
        );
        
        // Redis Port
        register_setting('zippicks_bi_settings', 'zippicks_bi_redis_port', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 6379
        ]);
        
        add_settings_field(
            'zippicks_bi_redis_port',
            __('Redis Port', 'zippicks-business-intelligence'),
            [$this, 'redis_port_field_callback'],
            'zippicks-bi-settings',
            'zippicks_bi_redis_settings'
        );
        
        // Advanced Settings Section
        add_settings_section(
            'zippicks_bi_advanced_settings',
            __('Advanced Settings', 'zippicks-business-intelligence'),
            [$this, 'advanced_section_callback'],
            'zippicks-bi-settings'
        );
        
        // Debug Mode
        register_setting('zippicks_bi_settings', 'zippicks_bi_debug_mode', [
            'type' => 'boolean',
            'sanitize_callback' => [$this, 'sanitize_checkbox'],
            'default' => false
        ]);
        
        add_settings_field(
            'zippicks_bi_debug_mode',
            __('Debug Mode', 'zippicks-business-intelligence'),
            [$this, 'debug_mode_field_callback'],
            'zippicks-bi-settings',
            'zippicks_bi_advanced_settings'
        );
        
        // Rate Limit
        register_setting('zippicks_bi_settings', 'zippicks_bi_rate_limit', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 60
        ]);
        
        add_settings_field(
            'zippicks_bi_rate_limit',
            __('API Rate Limit (per minute)', 'zippicks-business-intelligence'),
            [$this, 'rate_limit_field_callback'],
            'zippicks-bi-settings',
            'zippicks_bi_advanced_settings'
        );
    }
    
    /**
     * Display settings page
     */
    public function display_settings() {
        // Check if settings were just saved
        $settings_saved = isset($_GET['settings-updated']) && $_GET['settings-updated'];
        
        // Validate configuration
        $validation_errors = $this->config->validate();
        
        // Make config available to the view
        $config = $this->config;
        
        include ZIPPICKS_BI_PLUGIN_DIR . 'views/admin/settings.php';
    }
    
    /**
     * Section callbacks
     */
    public function api_section_callback() {
        echo '<p>' . __('Configure your ZipBusiness.ai API connection settings.', 'zippicks-business-intelligence') . '</p>';
    }
    
    public function cache_section_callback() {
        echo '<p>' . __('Configure caching behavior to optimize performance.', 'zippicks-business-intelligence') . '</p>';
    }
    
    public function redis_section_callback() {
        echo '<p>' . __('If Redis is available, it will be used for improved caching performance.', 'zippicks-business-intelligence') . '</p>';
    }
    
    public function advanced_section_callback() {
        echo '<p>' . __('Advanced configuration options for debugging and performance tuning.', 'zippicks-business-intelligence') . '</p>';
    }
    
    /**
     * Field callbacks
     */
    public function api_url_field_callback() {
        $value = $this->config->get('api_url');
        ?>
        <input type="url" 
               id="zippicks_bi_api_url" 
               name="zippicks_bi_api_url" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('Enter the ZipBusiness API base URL (e.g., https://zipbusiness-api.onrender.com or http://localhost:8000)', 'zippicks-business-intelligence'); ?>
        </p>
        <?php
    }
    
    public function api_key_field_callback() {
        $value = $this->config->get('api_key');
        ?>
        <input type="password" 
               id="zippicks_bi_api_key" 
               name="zippicks_bi_api_key" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('Your ZipBusiness.ai API key.', 'zippicks-business-intelligence'); ?>
        </p>
        <?php
    }
    
    public function cache_ttl_field_callback() {
        $value = $this->config->get('cache_ttl');
        ?>
        <input type="number" 
               id="zippicks_bi_cache_ttl" 
               name="zippicks_bi_cache_ttl" 
               value="<?php echo esc_attr($value); ?>" 
               min="60" 
               max="86400" 
               step="60" />
        <p class="description">
            <?php _e('How long to cache business data (in seconds). Default is 3600 (1 hour).', 'zippicks-business-intelligence'); ?>
        </p>
        <?php
    }
    
    public function redis_host_field_callback() {
        $value = $this->config->get('redis_host');
        ?>
        <input type="text" 
               id="zippicks_bi_redis_host" 
               name="zippicks_bi_redis_host" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('Redis server hostname or IP address.', 'zippicks-business-intelligence'); ?>
        </p>
        <?php
    }
    
    public function redis_port_field_callback() {
        $value = $this->config->get('redis_port');
        ?>
        <input type="number" 
               id="zippicks_bi_redis_port" 
               name="zippicks_bi_redis_port" 
               value="<?php echo esc_attr($value); ?>" 
               min="1" 
               max="65535" />
        <p class="description">
            <?php _e('Redis server port number.', 'zippicks-business-intelligence'); ?>
        </p>
        <?php
    }
    
    public function debug_mode_field_callback() {
        $value = $this->config->get('debug_mode');
        ?>
        <label>
            <input type="checkbox" 
                   id="zippicks_bi_debug_mode" 
                   name="zippicks_bi_debug_mode" 
                   value="1" 
                   <?php checked($value, true); ?> />
            <?php _e('Enable debug logging', 'zippicks-business-intelligence'); ?>
        </label>
        <p class="description">
            <?php _e('Enable detailed logging for troubleshooting.', 'zippicks-business-intelligence'); ?>
        </p>
        <?php
    }
    
    public function rate_limit_field_callback() {
        $value = $this->config->get('rate_limit');
        ?>
        <input type="number" 
               id="zippicks_bi_rate_limit" 
               name="zippicks_bi_rate_limit" 
               value="<?php echo esc_attr($value); ?>" 
               min="1" 
               max="1000" />
        <p class="description">
            <?php _e('Maximum API requests allowed per minute.', 'zippicks-business-intelligence'); ?>
        </p>
        <?php
    }
    
    /**
     * Sanitize checkbox
     *
     * @param mixed $value
     * @return bool
     */
    public function sanitize_checkbox($value) {
        return !empty($value);
    }
    
}