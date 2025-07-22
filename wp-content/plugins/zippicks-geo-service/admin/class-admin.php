<?php
/**
 * Admin Class
 * 
 * Handles admin interface and settings pages
 * 
 * @package ZipPicks_Geo_Service
 */

namespace ZipPicks\Geo;

class Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add admin styles and scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Add admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
        
        // AJAX handlers
        add_action('wp_ajax_zippicks_geo_test_location', [$this, 'ajax_test_location']);
        add_action('wp_ajax_zippicks_geo_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_zippicks_geo_update_maxmind', [$this, 'ajax_update_maxmind']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Geo Service', 'zippicks-geo'),
            __('Geo Service', 'zippicks-geo'),
            'manage_options',
            'zippicks-geo',
            [$this, 'display_main_page'],
            'dashicons-location-alt',
            30
        );
        
        add_submenu_page(
            'zippicks-geo',
            __('Settings', 'zippicks-geo'),
            __('Settings', 'zippicks-geo'),
            'manage_options',
            'zippicks-geo-settings',
            [$this, 'display_settings_page']
        );
        
        add_submenu_page(
            'zippicks-geo',
            __('Statistics', 'zippicks-geo'),
            __('Statistics', 'zippicks-geo'),
            'manage_options',
            'zippicks-geo-stats',
            [$this, 'display_stats_page']
        );
        
        add_submenu_page(
            'zippicks-geo',
            __('Tools', 'zippicks-geo'),
            __('Tools', 'zippicks-geo'),
            'manage_options',
            'zippicks-geo-tools',
            [$this, 'display_tools_page']
        );
    }
    
    /**
     * Display main admin page
     */
    public function display_main_page() {
        // Check if tables exist
        if (!Installer::tables_exist()) {
            $this->display_setup_notice();
            return;
        }
        
        // Get service instances
        $detector = new Location_Detector();
        $cache = new Geo_Cache();
        $ip_service = new IP_Geolocation();
        
        // Get current location
        $current_location = $detector->get_user_location(get_current_user_id());
        
        // Get cache stats
        $cache_stats = $cache->get_stats();
        
        // Get database info
        $db_info = $ip_service->get_database_info();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="zippicks-geo-dashboard">
                <div class="card">
                    <h2><?php _e('Current Location', 'zippicks-geo'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Latitude', 'zippicks-geo'); ?></th>
                            <td><?php echo esc_html($current_location['latitude']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Longitude', 'zippicks-geo'); ?></th>
                            <td><?php echo esc_html($current_location['longitude']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('City', 'zippicks-geo'); ?></th>
                            <td><?php echo esc_html($current_location['city'] ?? 'Unknown'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('State', 'zippicks-geo'); ?></th>
                            <td><?php echo esc_html($current_location['state'] ?? 'Unknown'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Source', 'zippicks-geo'); ?></th>
                            <td><?php echo esc_html($current_location['source'] ?? 'Unknown'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Accuracy', 'zippicks-geo'); ?></th>
                            <td><?php echo esc_html($current_location['accuracy'] ?? 'Unknown'); ?></td>
                        </tr>
                    </table>
                    <p>
                        <button type="button" class="button" id="test-location">
                            <?php _e('Test Location Detection', 'zippicks-geo'); ?>
                        </button>
                    </p>
                </div>
                
                <div class="card">
                    <h2><?php _e('Cache Status', 'zippicks-geo'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Driver', 'zippicks-geo'); ?></th>
                            <td><?php echo esc_html(ucfirst($cache_stats['driver'])); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Connected', 'zippicks-geo'); ?></th>
                            <td>
                                <?php if ($cache_stats['connected']) : ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                                <?php else : ?>
                                    <span class="dashicons dashicons-dismiss" style="color: red;"></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($cache_stats['driver'] === 'redis') : ?>
                        <tr>
                            <th><?php _e('Keys', 'zippicks-geo'); ?></th>
                            <td><?php echo esc_html(number_format($cache_stats['keys'])); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Memory', 'zippicks-geo'); ?></th>
                            <td><?php echo esc_html($cache_stats['memory']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    <p>
                        <button type="button" class="button" id="clear-cache">
                            <?php _e('Clear Cache', 'zippicks-geo'); ?>
                        </button>
                    </p>
                </div>
                
                <div class="card">
                    <h2><?php _e('MaxMind Database', 'zippicks-geo'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Status', 'zippicks-geo'); ?></th>
                            <td>
                                <?php if ($db_info['available']) : ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                                    <?php _e('Available', 'zippicks-geo'); ?>
                                <?php else : ?>
                                    <span class="dashicons dashicons-warning" style="color: orange;"></span>
                                    <?php _e('Not Available', 'zippicks-geo'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($db_info['available']) : ?>
                        <tr>
                            <th><?php _e('Size', 'zippicks-geo'); ?></th>
                            <td><?php echo esc_html(size_format($db_info['size'])); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Last Updated', 'zippicks-geo'); ?></th>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), $db_info['modified'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    <?php if (get_option('zippicks_geo_maxmind_key')) : ?>
                    <p>
                        <button type="button" class="button" id="update-maxmind">
                            <?php _e('Update Database', 'zippicks-geo'); ?>
                        </button>
                    </p>
                    <?php else : ?>
                    <p class="description">
                        <?php printf(
                            __('Add your MaxMind license key in the <a href="%s">settings</a> to enable automatic updates.', 'zippicks-geo'),
                            admin_url('admin.php?page=zippicks-geo-settings')
                        ); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .zippicks-geo-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .zippicks-geo-dashboard .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
        }
        .zippicks-geo-dashboard h2 {
            margin-top: 0;
        }
        </style>
        <?php
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('zippicks_geo_settings');
                do_settings_sections('zippicks_geo_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Display statistics page
     */
    public function display_stats_page() {
        global $wpdb;
        
        $stats = get_option('zippicks_geo_stats', []);
        
        // Get location history stats
        $table = $wpdb->prefix . 'user_locations';
        $location_stats = [];
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $location_stats = [
                'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
                'today' => $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE DATE(created_at) = %s",
                    current_time('Y-m-d')
                )),
                'unique_users' => $wpdb->get_var("SELECT COUNT(DISTINCT wp_user_id) FROM $table WHERE wp_user_id IS NOT NULL"),
                'unique_sessions' => $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM $table"),
            ];
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="card">
                <h2><?php _e('Usage Statistics', 'zippicks-geo'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Metric', 'zippicks-geo'); ?></th>
                            <th><?php _e('Value', 'zippicks-geo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php _e('Total Lookups', 'zippicks-geo'); ?></td>
                            <td><?php echo esc_html(number_format($stats['total_lookups'] ?? 0)); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Cache Hits', 'zippicks-geo'); ?></td>
                            <td>
                                <?php 
                                $hits = $stats['cache_hits'] ?? 0;
                                $total = $stats['total_lookups'] ?? 0;
                                $rate = $total > 0 ? round(($hits / $total) * 100, 1) : 0;
                                echo esc_html(number_format($hits) . ' (' . $rate . '%)');
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><?php _e('IP Lookups', 'zippicks-geo'); ?></td>
                            <td><?php echo esc_html(number_format($stats['ip_lookups'] ?? 0)); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('GPS Lookups', 'zippicks-geo'); ?></td>
                            <td><?php echo esc_html(number_format($stats['gps_lookups'] ?? 0)); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Last Reset', 'zippicks-geo'); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $stats['last_reset'] ?? 0)); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($location_stats)) : ?>
            <div class="card">
                <h2><?php _e('Location History', 'zippicks-geo'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Metric', 'zippicks-geo'); ?></th>
                            <th><?php _e('Value', 'zippicks-geo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php _e('Total Records', 'zippicks-geo'); ?></td>
                            <td><?php echo esc_html(number_format($location_stats['total'])); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Today', 'zippicks-geo'); ?></td>
                            <td><?php echo esc_html(number_format($location_stats['today'])); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Unique Users', 'zippicks-geo'); ?></td>
                            <td><?php echo esc_html(number_format($location_stats['unique_users'])); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Unique Sessions', 'zippicks-geo'); ?></td>
                            <td><?php echo esc_html(number_format($location_stats['unique_sessions'])); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <p>
                <button type="button" class="button" onclick="if(confirm('<?php esc_attr_e('Reset all statistics?', 'zippicks-geo'); ?>')) { window.location.href='<?php echo wp_nonce_url(admin_url('admin.php?page=zippicks-geo-stats&action=reset'), 'reset_stats'); ?>'; }">
                    <?php _e('Reset Statistics', 'zippicks-geo'); ?>
                </button>
            </p>
        </div>
        <?php
    }
    
    /**
     * Display tools page
     */
    public function display_tools_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="card">
                <h2><?php _e('Distance Calculator', 'zippicks-geo'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="from-lat"><?php _e('From', 'zippicks-geo'); ?></label></th>
                        <td>
                            <input type="number" id="from-lat" step="any" placeholder="Latitude" />
                            <input type="number" id="from-lng" step="any" placeholder="Longitude" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="to-lat"><?php _e('To', 'zippicks-geo'); ?></label></th>
                        <td>
                            <input type="number" id="to-lat" step="any" placeholder="Latitude" />
                            <input type="number" id="to-lng" step="any" placeholder="Longitude" />
                        </td>
                    </tr>
                    <tr>
                        <th></th>
                        <td>
                            <button type="button" class="button button-primary" id="calculate-distance">
                                <?php _e('Calculate Distance', 'zippicks-geo'); ?>
                            </button>
                            <span id="distance-result"></span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2><?php _e('Geohash Converter', 'zippicks-geo'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="geo-lat"><?php _e('Coordinates', 'zippicks-geo'); ?></label></th>
                        <td>
                            <input type="number" id="geo-lat" step="any" placeholder="Latitude" />
                            <input type="number" id="geo-lng" step="any" placeholder="Longitude" />
                            <input type="number" id="geo-precision" min="1" max="12" value="8" placeholder="Precision" />
                        </td>
                    </tr>
                    <tr>
                        <th></th>
                        <td>
                            <button type="button" class="button" id="encode-geohash">
                                <?php _e('Encode', 'zippicks-geo'); ?>
                            </button>
                            <span id="geohash-result"></span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2><?php _e('Export/Import', 'zippicks-geo'); ?></h2>
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zippicks-geo-tools&action=export'), 'export_data'); ?>" class="button">
                        <?php _e('Export Location Data', 'zippicks-geo'); ?>
                    </a>
                </p>
                <p class="description">
                    <?php _e('Export all location history and cache data as CSV.', 'zippicks-geo'); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display setup notice
     */
    private function display_setup_notice() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Database Setup Required', 'zippicks-geo'); ?></strong><br>
                    <?php _e('The Geo Service database tables have not been created yet.', 'zippicks-geo'); ?>
                </p>
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zippicks-geo&action=install'), 'install_tables'); ?>" class="button button-primary">
                        <?php _e('Create Tables', 'zippicks-geo'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('zippicks_geo_settings', 'zippicks_geo_settings');
        register_setting('zippicks_geo_settings', 'zippicks_geo_maxmind_key');
        register_setting('zippicks_geo_settings', 'zippicks_geo_trusted_proxies', [
            'sanitize_callback' => [$this, 'sanitize_trusted_proxies']
        ]);
        
        add_settings_section(
            'zippicks_geo_general',
            __('General Settings', 'zippicks-geo'),
            null,
            'zippicks_geo_settings'
        );
        
        add_settings_field(
            'enable_gps',
            __('Enable GPS Detection', 'zippicks-geo'),
            [$this, 'render_checkbox_field'],
            'zippicks_geo_settings',
            'zippicks_geo_general',
            ['field' => 'enable_gps']
        );
        
        add_settings_field(
            'enable_ip_detection',
            __('Enable IP Detection', 'zippicks-geo'),
            [$this, 'render_checkbox_field'],
            'zippicks_geo_settings',
            'zippicks_geo_general',
            ['field' => 'enable_ip_detection']
        );
        
        add_settings_section(
            'zippicks_geo_security',
            __('Security Settings', 'zippicks-geo'),
            [$this, 'render_security_section'],
            'zippicks_geo_settings'
        );
        
        add_settings_field(
            'trusted_proxies',
            __('Trusted Proxy IPs/Ranges', 'zippicks-geo'),
            [$this, 'render_trusted_proxies_field'],
            'zippicks_geo_settings',
            'zippicks_geo_security'
        );
        
        add_settings_section(
            'zippicks_geo_maxmind',
            __('MaxMind Settings', 'zippicks-geo'),
            null,
            'zippicks_geo_settings'
        );
        
        add_settings_field(
            'maxmind_key',
            __('License Key', 'zippicks-geo'),
            [$this, 'render_maxmind_key_field'],
            'zippicks_geo_settings',
            'zippicks_geo_maxmind'
        );
    }
    
    /**
     * Render checkbox field
     */
    public function render_checkbox_field($args) {
        $settings = get_option('zippicks_geo_settings', []);
        $field = $args['field'];
        $value = $settings[$field] ?? true;
        ?>
        <input type="checkbox" name="zippicks_geo_settings[<?php echo esc_attr($field); ?>]" value="1" <?php checked($value); ?> />
        <?php
    }
    
    /**
     * Render MaxMind key field
     */
    public function render_maxmind_key_field() {
        $key = get_option('zippicks_geo_maxmind_key', '');
        ?>
        <input type="text" name="zippicks_geo_maxmind_key" value="<?php echo esc_attr($key); ?>" class="regular-text" />
        <p class="description">
            <?php printf(
                __('Get your free license key from <a href="%s" target="_blank">MaxMind</a>.', 'zippicks-geo'),
                'https://www.maxmind.com/en/geolite2/signup'
            ); ?>
        </p>
        <?php
    }
    
    /**
     * Render security section description
     */
    public function render_security_section() {
        echo '<p>' . __('Configure security settings for IP geolocation. Only trust proxy headers from known proxy servers to prevent IP spoofing.', 'zippicks-geo') . '</p>';
    }
    
    /**
     * Render trusted proxies field
     */
    public function render_trusted_proxies_field() {
        $trusted_proxies = get_option('zippicks_geo_trusted_proxies', []);
        $ip_geo = new \ZipPicks\Geo\IP_Geolocation();
        $default_proxies = $ip_geo->get_default_trusted_proxies();
        ?>
        <textarea name="zippicks_geo_trusted_proxies" rows="10" cols="50" class="large-text code"><?php 
            echo esc_textarea(implode("\n", $trusted_proxies)); 
        ?></textarea>
        <p class="description">
            <?php _e('Enter one IP address or CIDR range per line. Examples:', 'zippicks-geo'); ?><br>
            <code>192.168.1.1</code> - Single IP<br>
            <code>10.0.0.0/8</code> - CIDR range<br>
            <code>2606:4700::/32</code> - IPv6 range
        </p>
        <p class="description">
            <strong><?php _e('Warning:', 'zippicks-geo'); ?></strong> 
            <?php _e('Only add IPs of proxy servers you control or trust (e.g., Cloudflare, your load balancer).', 'zippicks-geo'); ?>
        </p>
        <details style="margin-top: 10px;">
            <summary style="cursor: pointer;"><?php _e('Use Cloudflare defaults', 'zippicks-geo'); ?></summary>
            <p class="description" style="margin-top: 10px;">
                <?php _e('Click the button below to populate with Cloudflare\'s IP ranges:', 'zippicks-geo'); ?>
            </p>
            <button type="button" class="button" onclick="document.querySelector('[name=zippicks_geo_trusted_proxies]').value = <?php echo esc_attr(json_encode(implode("\n", $default_proxies))); ?>">
                <?php _e('Use Cloudflare IPs', 'zippicks-geo'); ?>
            </button>
        </details>
        <?php
    }
    
    /**
     * Sanitize trusted proxies input
     */
    public function sanitize_trusted_proxies($input) {
        if (!is_string($input)) {
            return [];
        }
        
        // Split by newlines and clean up
        $lines = explode("\n", $input);
        $proxies = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Basic validation - more thorough validation happens in is_trusted_proxy()
            if (preg_match('/^[0-9a-fA-F:.\/]+$/', $line)) {
                $proxies[] = $line;
            }
        }
        
        return array_unique($proxies);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'zippicks-geo') === false) {
            return;
        }
        
        wp_enqueue_script(
            'zippicks-geo-admin',
            ZIPPICKS_GEO_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            ZIPPICKS_GEO_VERSION,
            true
        );
        
        wp_localize_script('zippicks-geo-admin', 'zippicks_geo_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_geo_admin'),
        ]);
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Check for actions
        if (isset($_GET['page']) && $_GET['page'] === 'zippicks-geo') {
            if (isset($_GET['action']) && $_GET['action'] === 'install' && wp_verify_nonce($_GET['_wpnonce'], 'install_tables')) {
                Installer::create_tables();
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Database tables created successfully!', 'zippicks-geo'); ?></p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * AJAX: Test location detection
     */
    public function ajax_test_location() {
        check_ajax_referer('zippicks_geo_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $detector = new Location_Detector();
        $location = $detector->get_user_location(get_current_user_id());
        
        wp_send_json_success($location);
    }
    
    /**
     * AJAX: Clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('zippicks_geo_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_zippicks:geo:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_zippicks:geo:%'");
        
        wp_send_json_success(['message' => __('Cache cleared successfully!', 'zippicks-geo')]);
    }
    
    /**
     * AJAX: Update MaxMind database
     */
    public function ajax_update_maxmind() {
        check_ajax_referer('zippicks_geo_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $ip_service = new IP_Geolocation();
        $result = $ip_service->update_database();
        
        if ($result) {
            update_option('zippicks_geo_maxmind_last_update', current_time('timestamp'));
            wp_send_json_success(['message' => __('Database updated successfully!', 'zippicks-geo')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update database.', 'zippicks-geo')]);
        }
    }
}