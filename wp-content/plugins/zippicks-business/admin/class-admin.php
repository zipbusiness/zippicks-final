<?php
/**
 * The admin-specific functionality of the plugin.
 */
class ZipPicks_Business_Admin {
    
    /**
     * The ID of this plugin.
     */
    private $plugin_name;
    
    /**
     * The version of this plugin.
     */
    private $version;
    
    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }
    
    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            ZIPPICKS_BUSINESS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $this->version,
            'all'
        );
    }
    
    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            ZIPPICKS_BUSINESS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-api'),
            $this->version,
            false
        );
        
        // Enqueue API admin script on business edit screens
        $screen = get_current_screen();
        if ($screen && ($screen->id === 'zippicks_business' || $screen->post_type === 'zippicks_business')) {
            wp_enqueue_script(
                $this->plugin_name . '-api',
                ZIPPICKS_BUSINESS_PLUGIN_URL . 'admin/js/api-admin.js',
                array('jquery'),
                $this->version,
                false
            );
            
            // Localize API script
            wp_localize_script($this->plugin_name . '-api', 'zippicks_business_api', array(
                'nonce' => wp_create_nonce('zippicks_api_sync'),
                'strings' => array(
                    'sync_success' => __('Successfully synced with API', 'zippicks-business'),
                    'sync_failed' => __('API sync failed', 'zippicks-business'),
                    'link_success' => __('Successfully linked ZPID', 'zippicks-business'),
                    'invalid_zpid' => __('Please enter a valid ZPID', 'zippicks-business'),
                ),
            ));
        }
        
        // Localize main script
        wp_localize_script($this->plugin_name, 'zippicks_business', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_business_admin'),
            'strings' => array(
                'confirm_verify' => __('Are you sure you want to verify this business?', 'zippicks-business'),
                'confirm_tier_change' => __('Are you sure you want to change the listing tier?', 'zippicks-business'),
                'processing' => __('Processing...', 'zippicks-business'),
                'error' => __('An error occurred. Please try again.', 'zippicks-business'),
            ),
        ));
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('ZipPicks Business', 'zippicks-business'),
            __('ZipPicks Business', 'zippicks-business'),
            'manage_businesses',
            'zippicks-business',
            array($this, 'display_dashboard_page'),
            'dashicons-store',
            26
        );
        
        // Dashboard
        add_submenu_page(
            'zippicks-business',
            __('Dashboard', 'zippicks-business'),
            __('Dashboard', 'zippicks-business'),
            'manage_businesses',
            'zippicks-business',
            array($this, 'display_dashboard_page')
        );
        
        // Businesses list (redirect to post type)
        add_submenu_page(
            'zippicks-business',
            __('All Businesses', 'zippicks-business'),
            __('All Businesses', 'zippicks-business'),
            'edit_businesses',
            'edit.php?post_type=zippicks_business'
        );
        
        // Add new business
        add_submenu_page(
            'zippicks-business',
            __('Add New Business', 'zippicks-business'),
            __('Add New', 'zippicks-business'),
            'publish_businesses',
            'post-new.php?post_type=zippicks_business'
        );
        
        // Monetization
        add_submenu_page(
            'zippicks-business',
            __('Monetization', 'zippicks-business'),
            __('Monetization', 'zippicks-business'),
            'manage_business_monetization',
            'zippicks-business-monetization',
            array($this, 'display_monetization_page')
        );
        
        // Analytics
        add_submenu_page(
            'zippicks-business',
            __('Analytics', 'zippicks-business'),
            __('Analytics', 'zippicks-business'),
            'view_business_analytics',
            'zippicks-business-analytics',
            array($this, 'display_analytics_page')
        );
        
        // Settings
        add_submenu_page(
            'zippicks-business',
            __('Settings', 'zippicks-business'),
            __('Settings', 'zippicks-business'),
            'manage_options',
            'zippicks-business-settings',
            array($this, 'display_settings_page')
        );
    }
    
    /**
     * Display dashboard page
     */
    public function display_dashboard_page() {
        global $wpdb;
        
        // Get stats
        $total_businesses = wp_count_posts('zippicks_business')->publish;
        $verified_businesses = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = '_zp_verified' AND meta_value = '1'"
        );
        
        // Get tier breakdown
        $tier_stats = $wpdb->get_results(
            "SELECT meta_value as tier, COUNT(*) as count 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_zp_listing_tier' 
            GROUP BY meta_value"
        );
        
        // Get recent activity
        $recent_businesses = get_posts(array(
            'post_type' => 'zippicks_business',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('ZipPicks Business Dashboard', 'zippicks-business'); ?></h1>
            
            <div class="zippicks-dashboard-widgets">
                <!-- Stats Widget -->
                <div class="zippicks-widget">
                    <h2><?php _e('Overview', 'zippicks-business'); ?></h2>
                    <ul class="zippicks-stats">
                        <li>
                            <span class="stat-number"><?php echo number_format($total_businesses); ?></span>
                            <span class="stat-label"><?php _e('Total Businesses', 'zippicks-business'); ?></span>
                        </li>
                        <li>
                            <span class="stat-number"><?php echo number_format($verified_businesses); ?></span>
                            <span class="stat-label"><?php _e('Verified Businesses', 'zippicks-business'); ?></span>
                        </li>
                    </ul>
                </div>
                
                <!-- Tier Breakdown Widget -->
                <div class="zippicks-widget">
                    <h2><?php _e('Listing Tiers', 'zippicks-business'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Tier', 'zippicks-business'); ?></th>
                                <th><?php _e('Count', 'zippicks-business'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tier_stats as $stat) : ?>
                                <tr>
                                    <td><?php echo ucfirst(esc_html($stat->tier)); ?></td>
                                    <td><?php echo number_format($stat->count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Recent Activity Widget -->
                <div class="zippicks-widget">
                    <h2><?php _e('Recent Businesses', 'zippicks-business'); ?></h2>
                    <ul class="zippicks-recent-list">
                        <?php foreach ($recent_businesses as $business) : ?>
                            <li>
                                <a href="<?php echo get_edit_post_link($business->ID); ?>">
                                    <?php echo esc_html($business->post_title); ?>
                                </a>
                                <span class="date">
                                    <?php echo human_time_diff(strtotime($business->post_date), current_time('timestamp')); ?> ago
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="zippicks-quick-actions">
                <h2><?php _e('Quick Actions', 'zippicks-business'); ?></h2>
                <a href="<?php echo admin_url('post-new.php?post_type=zippicks_business'); ?>" class="button button-primary">
                    <?php _e('Add New Business', 'zippicks-business'); ?>
                </a>
                <a href="<?php echo admin_url('edit.php?post_type=zippicks_business'); ?>" class="button">
                    <?php _e('View All Businesses', 'zippicks-business'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=zippicks-business-analytics'); ?>" class="button">
                    <?php _e('View Analytics', 'zippicks-business'); ?>
                </a>
            </div>
        </div>
        
        <style>
            .zippicks-dashboard-widgets {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            .zippicks-widget {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                padding: 20px;
            }
            .zippicks-widget h2 {
                margin: 0 0 15px;
                font-size: 16px;
                font-weight: 600;
            }
            .zippicks-stats {
                list-style: none;
                margin: 0;
                padding: 0;
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            .zippicks-stats li {
                text-align: center;
            }
            .stat-number {
                display: block;
                font-size: 32px;
                font-weight: 600;
                color: #2271b1;
            }
            .stat-label {
                display: block;
                font-size: 14px;
                color: #646970;
            }
            .zippicks-recent-list {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .zippicks-recent-list li {
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f1;
            }
            .zippicks-recent-list li:last-child {
                border-bottom: none;
            }
            .zippicks-recent-list .date {
                float: right;
                color: #646970;
                font-size: 13px;
            }
            .zippicks-quick-actions {
                margin-top: 30px;
            }
            .zippicks-quick-actions .button {
                margin-right: 10px;
            }
        </style>
        <?php
    }
    
    /**
     * Display monetization page
     */
    public function display_monetization_page() {
        global $wpdb;
        
        // Get monetization stats
        $table = $wpdb->prefix . 'zippicks_business_monetization';
        $tier_revenue = $wpdb->get_results(
            "SELECT tier, COUNT(*) as count, SUM(amount) as revenue 
            FROM $table 
            WHERE subscription_status = 'active' 
            GROUP BY tier"
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e('Business Monetization', 'zippicks-business'); ?></h1>
            
            <div class="zippicks-monetization-stats">
                <h2><?php _e('Revenue Overview', 'zippicks-business'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Tier', 'zippicks-business'); ?></th>
                            <th><?php _e('Active Subscriptions', 'zippicks-business'); ?></th>
                            <th><?php _e('Monthly Revenue', 'zippicks-business'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_revenue = 0;
                        foreach ($tier_revenue as $tier) : 
                            $total_revenue += $tier->revenue;
                        ?>
                            <tr>
                                <td><?php echo ucfirst(esc_html($tier->tier)); ?></td>
                                <td><?php echo number_format($tier->count); ?></td>
                                <td>$<?php echo number_format($tier->revenue, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th><?php _e('Total', 'zippicks-business'); ?></th>
                            <th><?php echo number_format(array_sum(array_column($tier_revenue, 'count'))); ?></th>
                            <th>$<?php echo number_format($total_revenue, 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="zippicks-tier-settings">
                <h2><?php _e('Tier Configuration', 'zippicks-business'); ?></h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('zippicks_business_monetization');
                    
                    $tiers = get_option('zippicks_business_tiers', array());
                    foreach ($tiers as $tier_key => $tier_data) :
                    ?>
                        <h3><?php echo esc_html($tier_data['name']); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Price', 'zippicks-business'); ?></th>
                                <td>
                                    <input type="number" 
                                           name="zippicks_business_tiers[<?php echo esc_attr($tier_key); ?>][price]" 
                                           value="<?php echo esc_attr($tier_data['price']); ?>" 
                                           min="0" step="0.01" />
                                </td>
                            </tr>
                        </table>
                    <?php endforeach; ?>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display analytics page
     */
    public function display_analytics_page() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_business_analytics';
        
        // Get date range
        $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
        
        // Get top viewed businesses
        $top_businesses = $wpdb->get_results($wpdb->prepare(
            "SELECT a.business_id, p.post_title, COUNT(*) as views 
            FROM $table a 
            JOIN {$wpdb->posts} p ON a.business_id = p.ID 
            WHERE a.event_type = 'view' 
            AND a.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) 
            GROUP BY a.business_id 
            ORDER BY views DESC 
            LIMIT 10",
            $days
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Business Analytics', 'zippicks-business'); ?></h1>
            
            <div class="tablenav top">
                <form method="get">
                    <input type="hidden" name="page" value="zippicks-business-analytics" />
                    <select name="days">
                        <option value="7" <?php selected($days, 7); ?>><?php _e('Last 7 days', 'zippicks-business'); ?></option>
                        <option value="30" <?php selected($days, 30); ?>><?php _e('Last 30 days', 'zippicks-business'); ?></option>
                        <option value="90" <?php selected($days, 90); ?>><?php _e('Last 90 days', 'zippicks-business'); ?></option>
                    </select>
                    <input type="submit" class="button" value="<?php _e('Filter', 'zippicks-business'); ?>" />
                </form>
            </div>
            
            <h2><?php _e('Top Viewed Businesses', 'zippicks-business'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Business', 'zippicks-business'); ?></th>
                        <th><?php _e('Views', 'zippicks-business'); ?></th>
                        <th><?php _e('Actions', 'zippicks-business'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_businesses as $business) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link($business->business_id); ?>">
                                    <?php echo esc_html($business->post_title); ?>
                                </a>
                            </td>
                            <td><?php echo number_format($business->views); ?></td>
                            <td>
                                <a href="<?php echo get_permalink($business->business_id); ?>" target="_blank">
                                    <?php _e('View', 'zippicks-business'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('ZipPicks Business Settings', 'zippicks-business'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('zippicks_business_settings');
                $settings = get_option('zippicks_business_settings', array());
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Anti-Scraping Protection', 'zippicks-business'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="zippicks_business_settings[anti_scraping_enabled]" 
                                       value="1" <?php checked($settings['anti_scraping_enabled'] ?? true, true); ?> />
                                <?php _e('Enable anti-scraping measures', 'zippicks-business'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Rate Limiting', 'zippicks-business'); ?></th>
                        <td>
                            <input type="number" name="zippicks_business_settings[rate_limit_requests]" 
                                   value="<?php echo esc_attr($settings['rate_limit_requests'] ?? 60); ?>" 
                                   min="1" /> 
                            <?php _e('requests per minute', 'zippicks-business'); ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle quick edit via AJAX
     */
    public function handle_quick_edit() {
        check_ajax_referer('zippicks_business_admin', 'nonce');
        
        if (!current_user_can('edit_businesses')) {
            wp_die(-1);
        }
        
        $business_id = intval($_POST['business_id']);
        $field = sanitize_text_field($_POST['field']);
        $value = sanitize_text_field($_POST['value']);
        
        // Update the field
        update_post_meta($business_id, '_zp_' . $field, $value);
        
        wp_send_json_success();
    }
    
    /**
     * Handle business verification via AJAX
     */
    public function handle_verify_business() {
        check_ajax_referer('zippicks_business_admin', 'nonce');
        
        if (!current_user_can('verify_businesses')) {
            wp_die(-1);
        }
        
        $business_id = intval($_POST['business_id']);
        
        // Get business manager service
        $manager = function_exists('zippicks') && zippicks()->has('business.manager') 
            ? zippicks()->get('business.manager') 
            : new ZipPicks_Business_Manager();
        
        $result = $manager->verify_business($business_id);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    /**
     * Handle tier change via AJAX
     */
    public function handle_change_tier() {
        check_ajax_referer('zippicks_business_admin', 'nonce');
        
        if (!current_user_can('manage_business_monetization')) {
            wp_die(-1);
        }
        
        $business_id = intval($_POST['business_id']);
        $tier = sanitize_text_field($_POST['tier']);
        
        // Get business manager service
        $manager = function_exists('zippicks') && zippicks()->has('business.manager') 
            ? zippicks()->get('business.manager') 
            : new ZipPicks_Business_Manager();
        
        $result = $manager->update_business_tier($business_id, $tier);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
}