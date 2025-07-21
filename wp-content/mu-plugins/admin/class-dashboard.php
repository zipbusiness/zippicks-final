<?php
/**
 * Admin Dashboard
 * 
 * Main admin dashboard for ZipPicks Foundation.
 * 
 * @package ZipPicks\Foundation\Admin
 */

namespace ZipPicks\Foundation\Admin;

use ZipPicks\Foundation\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Dashboard {
    
    /**
     * Core instance
     * 
     * @var Core
     */
    private $core;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->core = Core::get_instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets']);
        
        // AJAX handlers
        add_action('wp_ajax_zippicks_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_zippicks_get_stats', [$this, 'ajax_get_stats']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('ZipPicks', 'zippicks-foundation'),
            __('ZipPicks', 'zippicks-foundation'),
            'manage_options',
            'zippicks-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-location-alt',
            3
        );
        
        // Dashboard submenu
        add_submenu_page(
            'zippicks-dashboard',
            __('Dashboard', 'zippicks-foundation'),
            __('Dashboard', 'zippicks-foundation'),
            'manage_options',
            'zippicks-dashboard',
            [$this, 'render_dashboard']
        );
        
        // System Status
        add_submenu_page(
            'zippicks-dashboard',
            __('System Status', 'zippicks-foundation'),
            __('System Status', 'zippicks-foundation'),
            'manage_options',
            'zippicks-system',
            [$this, 'render_system_status']
        );
        
        // Logs
        add_submenu_page(
            'zippicks-dashboard',
            __('Logs', 'zippicks-foundation'),
            __('Logs', 'zippicks-foundation'),
            'manage_options',
            'zippicks-logs',
            [$this, 'render_logs']
        );
        
        // Settings
        add_submenu_page(
            'zippicks-dashboard',
            __('Settings', 'zippicks-foundation'),
            __('Settings', 'zippicks-foundation'),
            'manage_options',
            'zippicks-settings',
            [$this, 'render_settings']
        );
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'zippicks') === false) {
            return;
        }
        
        // Styles
        wp_enqueue_style(
            'zippicks-admin',
            ZIPPICKS_FOUNDATION_URL . 'assets/css/admin.css',
            [],
            ZIPPICKS_FOUNDATION_VERSION
        );
        
        // Scripts
        wp_enqueue_script(
            'zippicks-admin',
            ZIPPICKS_FOUNDATION_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api'],
            ZIPPICKS_FOUNDATION_VERSION,
            true
        );
        
        wp_localize_script('zippicks-admin', 'zippicks_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_admin'),
            'strings' => [
                'confirm_clear_cache' => __('Are you sure you want to clear all caches?', 'zippicks-foundation'),
                'cache_cleared' => __('Cache cleared successfully', 'zippicks-foundation'),
                'error' => __('An error occurred', 'zippicks-foundation')
            ]
        ]);
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'zippicks_overview',
            __('ZipPicks Overview', 'zippicks-foundation'),
            [$this, 'render_overview_widget']
        );
        
        wp_add_dashboard_widget(
            'zippicks_activity',
            __('ZipPicks Activity', 'zippicks-foundation'),
            [$this, 'render_activity_widget']
        );
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        // Get stats
        $stats = $this->get_platform_stats();
        ?>
        <div class="wrap zippicks-dashboard">
            <h1><?php _e('ZipPicks Dashboard', 'zippicks-foundation'); ?></h1>
            
            <div class="zippicks-stats-grid">
                <div class="stat-box">
                    <h3><?php _e('Total Businesses', 'zippicks-foundation'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['businesses']); ?></div>
                </div>
                
                <div class="stat-box">
                    <h3><?php _e('Total Reviews', 'zippicks-foundation'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['reviews']); ?></div>
                </div>
                
                <div class="stat-box">
                    <h3><?php _e('Active Users', 'zippicks-foundation'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['users']); ?></div>
                </div>
                
                <div class="stat-box">
                    <h3><?php _e('Active ZIPs', 'zippicks-foundation'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['zips']); ?></div>
                </div>
            </div>
            
            <div class="zippicks-dashboard-sections">
                <div class="dashboard-section">
                    <h2><?php _e('Recent Activity', 'zippicks-foundation'); ?></h2>
                    <?php $this->render_recent_activity(); ?>
                </div>
                
                <div class="dashboard-section">
                    <h2><?php _e('Trending Vibes', 'zippicks-foundation'); ?></h2>
                    <?php $this->render_trending_vibes(); ?>
                </div>
                
                <div class="dashboard-section">
                    <h2><?php _e('System Health', 'zippicks-foundation'); ?></h2>
                    <?php $this->render_system_health(); ?>
                </div>
            </div>
        </div>
        
        <style>
            .zippicks-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            .stat-box {
                background: #fff;
                padding: 20px;
                border: 1px solid #ddd;
                text-align: center;
            }
            .stat-box h3 {
                margin: 0 0 10px;
                color: #666;
                font-size: 14px;
                font-weight: normal;
            }
            .stat-number {
                font-size: 36px;
                font-weight: bold;
                color: #2271b1;
            }
            .zippicks-dashboard-sections {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-top: 30px;
            }
            .dashboard-section {
                background: #fff;
                padding: 20px;
                border: 1px solid #ddd;
            }
            .dashboard-section h2 {
                margin-top: 0;
            }
        </style>
        <?php
    }
    
    /**
     * Render system status page
     */
    public function render_system_status() {
        $schema_manager = $this->core->get_service('schema_manager');
        $cache_manager = $this->core->get_service('cache_manager');
        
        $table_status = $schema_manager->get_table_status();
        $cache_stats = $cache_manager->get_stats();
        ?>
        <div class="wrap">
            <h1><?php _e('System Status', 'zippicks-foundation'); ?></h1>
            
            <div class="system-status-section">
                <h2><?php _e('Database Tables', 'zippicks-foundation'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Table', 'zippicks-foundation'); ?></th>
                            <th><?php _e('Status', 'zippicks-foundation'); ?></th>
                            <th><?php _e('Rows', 'zippicks-foundation'); ?></th>
                            <th><?php _e('Size', 'zippicks-foundation'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($table_status as $table => $status): ?>
                            <tr>
                                <td><?php echo esc_html($table); ?></td>
                                <td>
                                    <?php if ($status['exists']): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($status['rows']); ?></td>
                                <td><?php echo $status['size_mb']; ?> MB</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="system-status-section">
                <h2><?php _e('Cache Status', 'zippicks-foundation'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Object Cache', 'zippicks-foundation'); ?></th>
                        <td>
                            <?php if ($cache_stats['object_cache_enabled']): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                <?php _e('Enabled', 'zippicks-foundation'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-no-alt" style="color: #d63638;"></span>
                                <?php _e('Disabled', 'zippicks-foundation'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Transients', 'zippicks-foundation'); ?></th>
                        <td>
                            <?php echo sprintf(
                                __('%d transients using %s', 'zippicks-foundation'),
                                $cache_stats['transient_count'],
                                $cache_stats['transient_size']
                            ); ?>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button class="button button-secondary" id="clear-cache-btn">
                        <?php _e('Clear All Caches', 'zippicks-foundation'); ?>
                    </button>
                </p>
            </div>
            
            <div class="system-status-section">
                <h2><?php _e('PHP Info', 'zippicks-foundation'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e('PHP Version', 'zippicks-foundation'); ?></th>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Memory Limit', 'zippicks-foundation'); ?></th>
                        <td><?php echo ini_get('memory_limit'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Max Execution Time', 'zippicks-foundation'); ?></th>
                        <td><?php echo ini_get('max_execution_time'); ?>s</td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render logs page
     */
    public function render_logs() {
        $logger = $this->core->get_service('logger');
        $recent_errors = $logger->get_recent_errors(50);
        ?>
        <div class="wrap">
            <h1><?php _e('Logs', 'zippicks-foundation'); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="zippicks_export_logs">
                        <?php wp_nonce_field('zippicks_export_logs'); ?>
                        <button type="submit" class="button">
                            <?php _e('Export Logs', 'zippicks-foundation'); ?>
                        </button>
                    </form>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 150px;"><?php _e('Time', 'zippicks-foundation'); ?></th>
                        <th style="width: 80px;"><?php _e('Level', 'zippicks-foundation'); ?></th>
                        <th><?php _e('Message', 'zippicks-foundation'); ?></th>
                        <th style="width: 100px;"><?php _e('User', 'zippicks-foundation'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_errors as $error): ?>
                        <tr>
                            <td><?php echo esc_html($error->created_at); ?></td>
                            <td>
                                <span class="log-level log-level-<?php echo esc_attr($error->level); ?>">
                                    <?php echo esc_html(strtoupper($error->level)); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo esc_html($error->message); ?>
                                <?php if ($error->context): ?>
                                    <br><small><?php echo esc_html($error->context); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($error->user_id): ?>
                                    <?php $user = get_user_by('id', $error->user_id); ?>
                                    <?php echo $user ? esc_html($user->display_name) : 'User #' . $error->user_id; ?>
                                <?php else: ?>
                                    <?php _e('Guest', 'zippicks-foundation'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <style>
            .log-level {
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
            }
            .log-level-debug { background: #f0f0f0; color: #666; }
            .log-level-info { background: #d1ecf1; color: #0c5460; }
            .log-level-warning { background: #fff3cd; color: #856404; }
            .log-level-error { background: #f8d7da; color: #721c24; }
            .log-level-critical { background: #721c24; color: #fff; }
        </style>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings() {
        ?>
        <div class="wrap">
            <h1><?php _e('ZipPicks Settings', 'zippicks-foundation'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('zippicks_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="zippicks_default_zip">
                                <?php _e('Default ZIP Code', 'zippicks-foundation'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="zippicks_default_zip" 
                                   name="zippicks_default_zip" 
                                   value="<?php echo esc_attr(get_option('zippicks_default_zip', '10001')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="zippicks_search_radius">
                                <?php _e('Default Search Radius', 'zippicks-foundation'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="zippicks_search_radius" 
                                   name="zippicks_search_radius" 
                                   value="<?php echo esc_attr(get_option('zippicks_search_radius', 5)); ?>" 
                                   min="1" 
                                   max="50" 
                                   class="small-text" />
                            <span class="description"><?php _e('miles', 'zippicks-foundation'); ?></span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php _e('Enable Features', 'zippicks-foundation'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                           name="zippicks_enable_taste_graph" 
                                           value="1"
                                           <?php checked(get_option('zippicks_enable_taste_graph', true)); ?> />
                                    <?php _e('Enable Taste Graph', 'zippicks-foundation'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" 
                                           name="zippicks_enable_ai_scoring" 
                                           value="1"
                                           <?php checked(get_option('zippicks_enable_ai_scoring', true)); ?> />
                                    <?php _e('Enable AI Scoring', 'zippicks-foundation'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" 
                                           name="zippicks_enable_demand_tracking" 
                                           value="1"
                                           <?php checked(get_option('zippicks_enable_demand_tracking', true)); ?> />
                                    <?php _e('Enable Demand Tracking', 'zippicks-foundation'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render overview widget
     */
    public function render_overview_widget() {
        $stats = $this->get_platform_stats();
        ?>
        <div class="zippicks-widget-content">
            <ul>
                <li><?php echo sprintf(__('%d Businesses', 'zippicks-foundation'), $stats['businesses']); ?></li>
                <li><?php echo sprintf(__('%d Reviews', 'zippicks-foundation'), $stats['reviews']); ?></li>
                <li><?php echo sprintf(__('%d Active Users', 'zippicks-foundation'), $stats['users']); ?></li>
                <li><?php echo sprintf(__('%d Active ZIPs', 'zippicks-foundation'), $stats['zips']); ?></li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Render activity widget
     */
    public function render_activity_widget() {
        // Get recent activity
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'interactions';
        
        $recent = $wpdb->get_results("
            SELECT * FROM {$table}
            ORDER BY timestamp DESC
            LIMIT 5
        ");
        ?>
        <div class="zippicks-widget-content">
            <?php if ($recent): ?>
                <ul>
                    <?php foreach ($recent as $interaction): ?>
                        <li>
                            <?php
                            $user = get_user_by('id', $interaction->user_id);
                            $business = get_post($interaction->business_id);
                            
                            if ($user && $business) {
                                echo sprintf(
                                    __('%s %s %s', 'zippicks-foundation'),
                                    esc_html($user->display_name),
                                    esc_html($interaction->interaction_type),
                                    esc_html($business->post_title)
                                );
                            }
                            ?>
                            <small><?php echo human_time_diff(strtotime($interaction->timestamp)); ?> ago</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php _e('No recent activity', 'zippicks-foundation'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get platform statistics
     * 
     * @return array Statistics
     */
    private function get_platform_stats() {
        global $wpdb;
        
        return [
            'businesses' => wp_count_posts('zippicks_business')->publish ?? 0,
            'reviews' => wp_count_posts('zippicks_review')->publish ?? 0,
            'users' => count_users()['total_users'],
            'zips' => $wpdb->get_var("
                SELECT COUNT(DISTINCT meta_value) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_zippicks_zip'
            ") ?: 0
        ];
    }
    
    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'interactions';
        
        $recent = $wpdb->get_results("
            SELECT * FROM {$table}
            ORDER BY timestamp DESC
            LIMIT 10
        ");
        
        if ($recent) {
            echo '<ul>';
            foreach ($recent as $interaction) {
                $user = get_user_by('id', $interaction->user_id);
                $business = get_post($interaction->business_id);
                
                if ($user && $business) {
                    echo '<li>';
                    echo sprintf(
                        '%s %s <a href="%s">%s</a> %s ago',
                        esc_html($user->display_name),
                        esc_html($interaction->interaction_type),
                        get_edit_post_link($business->ID),
                        esc_html($business->post_title),
                        human_time_diff(strtotime($interaction->timestamp))
                    );
                    echo '</li>';
                }
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('No recent activity', 'zippicks-foundation') . '</p>';
        }
    }
    
    /**
     * Render trending vibes
     */
    private function render_trending_vibes() {
        $vibe_taxonomy = $this->core->get_service('vibe_taxonomy');
        $trending = $vibe_taxonomy->get_trending_vibes(['number' => 5]);
        
        if ($trending) {
            echo '<ul>';
            foreach ($trending as $vibe) {
                echo '<li>';
                echo sprintf(
                    '<strong>%s</strong> - %d businesses',
                    esc_html($vibe['label']),
                    $vibe['count']
                );
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('No trending vibes', 'zippicks-foundation') . '</p>';
        }
    }
    
    /**
     * Render system health
     */
    private function render_system_health() {
        $cache_manager = $this->core->get_service('cache_manager');
        $cache_stats = $cache_manager->get_stats();
        
        echo '<ul>';
        
        // PHP Version
        $php_ok = version_compare(PHP_VERSION, ZIPPICKS_MIN_PHP_VERSION, '>=');
        echo '<li>';
        echo $php_ok ? '✅' : '❌';
        echo ' PHP ' . PHP_VERSION;
        echo '</li>';
        
        // WordPress Version
        $wp_ok = version_compare(get_bloginfo('version'), ZIPPICKS_MIN_WP_VERSION, '>=');
        echo '<li>';
        echo $wp_ok ? '✅' : '❌';
        echo ' WordPress ' . get_bloginfo('version');
        echo '</li>';
        
        // Object Cache
        echo '<li>';
        echo $cache_stats['object_cache_enabled'] ? '✅' : '⚠️';
        echo ' ' . __('Object Cache', 'zippicks-foundation');
        echo '</li>';
        
        // Database
        echo '<li>';
        echo '✅ ' . __('Database Connected', 'zippicks-foundation');
        echo '</li>';
        
        echo '</ul>';
    }
    
    /**
     * AJAX handler for clearing cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('zippicks_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $cache_manager = $this->core->get_service('cache_manager');
        $cache_manager->clear_all();
        
        wp_send_json_success(['message' => __('Cache cleared successfully', 'zippicks-foundation')]);
    }
    
    /**
     * AJAX handler for getting stats
     */
    public function ajax_get_stats() {
        check_ajax_referer('zippicks_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $stats = $this->get_platform_stats();
        wp_send_json_success($stats);
    }
}