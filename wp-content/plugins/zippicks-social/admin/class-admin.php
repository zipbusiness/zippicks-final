<?php
/**
 * Admin interface for ZipPicks Social
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_Admin
 * 
 * Handles admin interface and settings
 */
class ZipPicks_Social_Admin {
    
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
        
        // Add user profile fields
        add_action('show_user_profile', [$this, 'add_user_profile_fields']);
        add_action('edit_user_profile', [$this, 'add_user_profile_fields']);
        
        // Add admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
        
        // Add dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }
    
    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu(): void {
        // Main menu
        add_menu_page(
            __('ZipPicks Social', 'zippicks-social'),
            __('Social', 'zippicks-social'),
            'manage_options',
            'zippicks-social',
            [$this, 'display_main_page'],
            'dashicons-groups',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'zippicks-social',
            __('Settings', 'zippicks-social'),
            __('Settings', 'zippicks-social'),
            'manage_options',
            'zippicks-social',
            [$this, 'display_main_page']
        );
        
        // Analytics submenu
        add_submenu_page(
            'zippicks-social',
            __('Analytics', 'zippicks-social'),
            __('Analytics', 'zippicks-social'),
            'view_zippicks_social_analytics',
            'zippicks-social-analytics',
            [$this, 'display_analytics_page']
        );
        
        // Migration submenu
        add_submenu_page(
            'zippicks-social',
            __('Database', 'zippicks-social'),
            __('Database', 'zippicks-social'),
            'manage_options',
            'zippicks-social-database',
            [$this, 'display_database_page']
        );
    }
    
    /**
     * Display main settings page
     *
     * @return void
     */
    public function display_main_page(): void {
        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('zippicks_social_settings')) {
            $this->save_settings();
        }
        
        // Get current settings
        $settings = $this->get_settings();
        
        include ZIPPICKS_SOCIAL_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
    
    /**
     * Display analytics page
     *
     * @return void
     */
    public function display_analytics_page(): void {
        global $wpdb;
        
        // Get analytics data
        $stats = $this->get_analytics_data();
        
        include ZIPPICKS_SOCIAL_PLUGIN_DIR . 'admin/views/analytics-dashboard.php';
    }
    
    /**
     * Display database page
     *
     * @return void
     */
    public function display_database_page(): void {
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-database-migrator.php';
        
        // Handle migration action
        if (isset($_GET['action']) && $_GET['action'] === 'run-migration') {
            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions'));
            }
            
            if (!wp_verify_nonce($_GET['_wpnonce'], 'run_migration_action')) {
                wp_die(__('Security check failed'));
            }
            
            $result = ZipPicks_Social_Database_Migrator::run_migrations();
            $status = $result['status'] === 'success' ? 'migration-success' : 'migration-failed';
            
            wp_redirect(admin_url("admin.php?page=zippicks-social-database&migration-result={$status}"));
            exit;
        }
        
        // Handle table creation action
        if (isset($_GET['action']) && $_GET['action'] === 'create-tables') {
            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions'));
            }
            
            if (!wp_verify_nonce($_GET['_wpnonce'], 'create_tables_action')) {
                wp_die(__('Security check failed'));
            }
            
            require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-database.php';
            ZipPicks_Social_Database::create_tables();
            
            wp_redirect(admin_url('admin.php?page=zippicks-social-database&tables-created=1'));
            exit;
        }
        
        // Get migration status
        $migration_status = ZipPicks_Social_Database_Migrator::get_migration_status();
        
        // Check if tables exist
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-activator.php';
        $tables_exist = ZipPicks_Social_Activator::tables_exist();
        
        include ZIPPICKS_SOCIAL_PLUGIN_DIR . 'admin/views/database-page.php';
    }
    
    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings(): void {
        // General settings section
        add_settings_section(
            'zippicks_social_general',
            __('General Settings', 'zippicks-social'),
            [$this, 'render_general_section'],
            'zippicks-social'
        );
        
        // Register settings
        $settings = [
            'zippicks_social_enable_notifications' => [
                'type' => 'boolean',
                'label' => __('Enable Notifications', 'zippicks-social'),
                'description' => __('Send notifications for new followers and activities', 'zippicks-social')
            ],
            'zippicks_social_enable_activity_feed' => [
                'type' => 'boolean',
                'label' => __('Enable Activity Feed', 'zippicks-social'),
                'description' => __('Show activity feed on user profiles', 'zippicks-social')
            ],
            'zippicks_social_enable_suggestions' => [
                'type' => 'boolean',
                'label' => __('Enable Follow Suggestions', 'zippicks-social'),
                'description' => __('Show personalized follow suggestions to users', 'zippicks-social')
            ],
            'zippicks_social_follow_rate_limit' => [
                'type' => 'number',
                'label' => __('Follow Rate Limit', 'zippicks-social'),
                'description' => __('Maximum follows per hour per user', 'zippicks-social')
            ],
            'zippicks_social_activity_retention_days' => [
                'type' => 'number',
                'label' => __('Activity Retention Days', 'zippicks-social'),
                'description' => __('Days to keep activity data before cleanup', 'zippicks-social')
            ],
            'zippicks_social_cache_duration' => [
                'type' => 'number',
                'label' => __('Cache Duration', 'zippicks-social'),
                'description' => __('Cache duration in seconds', 'zippicks-social')
            ]
        ];
        
        foreach ($settings as $option => $config) {
            register_setting('zippicks_social_settings', $option);
            
            add_settings_field(
                $option,
                $config['label'],
                [$this, 'render_setting_field'],
                'zippicks-social',
                'zippicks_social_general',
                [
                    'option' => $option,
                    'type' => $config['type'],
                    'description' => $config['description']
                ]
            );
        }
    }
    
    /**
     * Render general settings section
     *
     * @return void
     */
    public function render_general_section(): void {
        echo '<p>' . __('Configure the social follow system settings.', 'zippicks-social') . '</p>';
    }
    
    /**
     * Render setting field
     *
     * @param array $args Field arguments
     * @return void
     */
    public function render_setting_field(array $args): void {
        $option = $args['option'];
        $type = $args['type'];
        $value = get_option($option);
        $description = $args['description'];
        
        switch ($type) {
            case 'boolean':
                ?>
                <label>
                    <input type="checkbox" 
                           name="<?php echo esc_attr($option); ?>" 
                           value="yes" 
                           <?php checked($value, 'yes'); ?>>
                    <?php echo esc_html($description); ?>
                </label>
                <?php
                break;
                
            case 'number':
                ?>
                <input type="number" 
                       name="<?php echo esc_attr($option); ?>" 
                       value="<?php echo esc_attr($value); ?>"
                       class="small-text">
                <p class="description"><?php echo esc_html($description); ?></p>
                <?php
                break;
        }
    }
    
    /**
     * Get settings
     *
     * @return array
     */
    private function get_settings(): array {
        return [
            'enable_notifications' => get_option('zippicks_social_enable_notifications', 'yes'),
            'enable_activity_feed' => get_option('zippicks_social_enable_activity_feed', 'yes'),
            'enable_suggestions' => get_option('zippicks_social_enable_suggestions', 'yes'),
            'follow_rate_limit' => get_option('zippicks_social_follow_rate_limit', 50),
            'activity_retention_days' => get_option('zippicks_social_activity_retention_days', 90),
            'cache_duration' => get_option('zippicks_social_cache_duration', 300),
        ];
    }
    
    /**
     * Save settings
     *
     * @return void
     */
    private function save_settings(): void {
        $settings = [
            'zippicks_social_enable_notifications',
            'zippicks_social_enable_activity_feed',
            'zippicks_social_enable_suggestions',
            'zippicks_social_follow_rate_limit',
            'zippicks_social_activity_retention_days',
            'zippicks_social_cache_duration'
        ];
        
        foreach ($settings as $option) {
            if (isset($_POST[$option])) {
                update_option($option, sanitize_text_field($_POST[$option]));
            } else {
                // Handle unchecked checkboxes
                if (strpos($option, 'enable_') !== false) {
                    update_option($option, 'no');
                }
            }
        }
        
        add_settings_error(
            'zippicks_social_settings',
            'settings_updated',
            __('Settings saved successfully.', 'zippicks-social'),
            'updated'
        );
    }
    
    /**
     * Get analytics data
     *
     * @return array
     */
    private function get_analytics_data(): array {
        global $wpdb;
        
        $follows_table = $wpdb->prefix . 'zippicks_follows';
        $stats_table = $wpdb->prefix . 'zippicks_follow_stats';
        $activities_table = $wpdb->prefix . 'zippicks_activities';
        
        // Total follows
        $total_follows = $wpdb->get_var("SELECT COUNT(*) FROM {$follows_table} WHERE status = 'active'");
        
        // New follows today
        $today_follows = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$follows_table} 
             WHERE status = 'active' 
             AND DATE(created_at) = CURDATE()"
        );
        
        // Most followed entities
        $most_followed = $wpdb->get_results(
            "SELECT entity_id, entity_type, followers_count 
             FROM {$stats_table} 
             ORDER BY followers_count DESC 
             LIMIT 10"
        );
        
        // Recent activities
        $recent_activities = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$activities_table} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        return [
            'total_follows' => (int) $total_follows,
            'today_follows' => (int) $today_follows,
            'most_followed' => $most_followed,
            'recent_activities' => (int) $recent_activities
        ];
    }
    
    /**
     * Add user profile fields
     *
     * @param WP_User $user
     * @return void
     */
    public function add_user_profile_fields($user): void {
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-follow-manager.php';
        $follow_manager = new ZipPicks_Social_Follow_Manager();
        
        $followers_count = $follow_manager->get_followers_count($user->ID, 'user');
        $following_count = $follow_manager->get_following_count($user->ID);
        
        ?>
        <h3><?php _e('Social Following', 'zippicks-social'); ?></h3>
        <table class="form-table">
            <tr>
                <th><?php _e('Followers', 'zippicks-social'); ?></th>
                <td><?php echo number_format_i18n($followers_count); ?></td>
            </tr>
            <tr>
                <th><?php _e('Following', 'zippicks-social'); ?></th>
                <td><?php echo number_format_i18n($following_count); ?></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Display admin notices
     *
     * @return void
     */
    public function admin_notices(): void {
        // Check if on our admin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'zippicks-social') === false) {
            return;
        }
        
        // Check migration status
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-database-migrator.php';
        $migration_status = ZipPicks_Social_Database_Migrator::get_migration_status();
        
        if ($migration_status['needs_migration']) {
            $migrate_url = wp_nonce_url(
                admin_url('admin.php?page=zippicks-social-database&action=run-migration'),
                'run_migration_action'
            );
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('ZipPicks Social:', 'zippicks-social'); ?></strong>
                    <?php printf(
                        __('Database needs migration to version %s.', 'zippicks-social'),
                        esc_html($migration_status['target_version'])
                    ); ?>
                    <a href="<?php echo esc_url($migrate_url); ?>" class="button button-primary">
                        <?php _e('Run Migration', 'zippicks-social'); ?>
                    </a>
                </p>
            </div>
            <?php
        } elseif (!ZipPicks_Social_Activator::tables_exist()) {
            $create_url = wp_nonce_url(
                admin_url('admin.php?page=zippicks-social-database&action=create-tables'),
                'create_tables_action'
            );
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('ZipPicks Social:', 'zippicks-social'); ?></strong>
                    <?php _e('Database tables are missing.', 'zippicks-social'); ?>
                    <a href="<?php echo esc_url($create_url); ?>" class="button button-primary">
                        <?php _e('Create Tables', 'zippicks-social'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
        
        // Display migration result notices
        if (isset($_GET['migration-result'])) {
            if ($_GET['migration-result'] === 'migration-success') {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Database migration completed successfully.', 'zippicks-social'); ?></p>
                </div>
                <?php
            } elseif ($_GET['migration-result'] === 'migration-failed') {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e('Database migration failed. Please check the error logs.', 'zippicks-social'); ?></p>
                </div>
                <?php
            }
        }
        
        if (isset($_GET['tables-created'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Database tables created successfully.', 'zippicks-social'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Add dashboard widget
     *
     * @return void
     */
    public function add_dashboard_widget(): void {
        wp_add_dashboard_widget(
            'zippicks_social_dashboard',
            __('Social Following Overview', 'zippicks-social'),
            [$this, 'render_dashboard_widget']
        );
    }
    
    /**
     * Render dashboard widget
     *
     * @return void
     */
    public function render_dashboard_widget(): void {
        $stats = $this->get_analytics_data();
        
        ?>
        <div class="zps-dashboard-widget">
            <div class="zps-stat-row">
                <div class="zps-stat">
                    <h4><?php _e('Total Follows', 'zippicks-social'); ?></h4>
                    <p class="zps-stat-number"><?php echo number_format_i18n($stats['total_follows']); ?></p>
                </div>
                <div class="zps-stat">
                    <h4><?php _e('New Today', 'zippicks-social'); ?></h4>
                    <p class="zps-stat-number"><?php echo number_format_i18n($stats['today_follows']); ?></p>
                </div>
            </div>
            <p class="zps-dashboard-link">
                <a href="<?php echo admin_url('admin.php?page=zippicks-social-analytics'); ?>">
                    <?php _e('View Full Analytics', 'zippicks-social'); ?> &rarr;
                </a>
            </p>
        </div>
        <style>
            .zps-stat-row { display: flex; gap: 20px; margin-bottom: 15px; }
            .zps-stat { flex: 1; }
            .zps-stat h4 { margin: 0 0 5px; color: #666; font-size: 12px; }
            .zps-stat-number { font-size: 24px; font-weight: bold; margin: 0; }
            .zps-dashboard-link { margin: 0; }
        </style>
        <?php
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void {
        // Only load on our admin pages
        if (strpos($hook, 'zippicks-social') === false) {
            return;
        }
        
        wp_enqueue_style(
            'zippicks-social-admin',
            ZIPPICKS_SOCIAL_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ZIPPICKS_SOCIAL_VERSION
        );
        
        wp_enqueue_script(
            'zippicks-social-admin',
            ZIPPICKS_SOCIAL_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            ZIPPICKS_SOCIAL_VERSION,
            true
        );
        
        wp_localize_script('zippicks-social-admin', 'zippicksSocialAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_social_admin'),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this?', 'zippicks-social'),
                'error' => __('An error occurred. Please try again.', 'zippicks-social')
            ]
        ]);
    }
}