<?php
namespace ZipPicks\Favorites;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    
    private $analytics;
    
    public function __construct() {
        $this->analytics = new Analytics();
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Favorites Analytics', 'zippicks-favorites'),
            __('Favorites', 'zippicks-favorites'),
            'view_favorites_analytics',
            'zippicks-favorites',
            [$this, 'render_analytics_page'],
            'dashicons-heart',
            30
        );
        
        add_submenu_page(
            'zippicks-favorites',
            __('Analytics', 'zippicks-favorites'),
            __('Analytics', 'zippicks-favorites'),
            'view_favorites_analytics',
            'zippicks-favorites',
            [$this, 'render_analytics_page']
        );
        
        add_submenu_page(
            'zippicks-favorites',
            __('Settings', 'zippicks-favorites'),
            __('Settings', 'zippicks-favorites'),
            'manage_zippicks_favorites',
            'zippicks-favorites-settings',
            [$this, 'render_settings_page']
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'zippicks-favorites') === false && $hook !== 'index.php') {
            return;
        }
        
        wp_enqueue_style(
            'zippicks-favorites-admin',
            ZIPPICKS_FAVORITES_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ZIPPICKS_FAVORITES_VERSION
        );
        
        if (strpos($hook, 'zippicks-favorites') !== false) {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js', [], '4.4.0');
            
            wp_enqueue_script(
                'zippicks-favorites-admin',
                ZIPPICKS_FAVORITES_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery', 'chart-js'],
                ZIPPICKS_FAVORITES_VERSION,
                true
            );
            
            wp_localize_script('zippicks-favorites-admin', 'zipPicksFavoritesAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('zippicks_favorites_admin')
            ]);
        }
    }
    
    public function add_dashboard_widget() {
        if (!current_user_can('view_favorites_analytics')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'zippicks_favorites_widget',
            __('Favorites Overview', 'zippicks-favorites'),
            [$this, 'render_dashboard_widget']
        );
    }
    
    public function render_analytics_page() {
        $analytics_data = $this->analytics->get_analytics_data();
        ?>
        <div class="wrap zippicks-favorites-admin">
            <h1><?php _e('Favorites Analytics', 'zippicks-favorites'); ?></h1>
            
            <div class="analytics-grid">
                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="stat-card">
                        <h3><?php _e('Total Favorites', 'zippicks-favorites'); ?></h3>
                        <div class="stat-number"><?php echo number_format($analytics_data['total_favorites']); ?></div>
                        <div class="stat-change">
                            <?php 
                            $change = $analytics_data['favorites_change_7d'];
                            $arrow = $change >= 0 ? '↑' : '↓';
                            $class = $change >= 0 ? 'positive' : 'negative';
                            ?>
                            <span class="<?php echo $class; ?>"><?php echo $arrow . ' ' . abs($change) . '%'; ?></span>
                            <?php _e('from last week', 'zippicks-favorites'); ?>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php _e('Active Users', 'zippicks-favorites'); ?></h3>
                        <div class="stat-number"><?php echo number_format($analytics_data['active_users']); ?></div>
                        <div class="stat-detail">
                            <?php echo sprintf(
                                __('%d%% of total users', 'zippicks-favorites'),
                                $analytics_data['user_percentage']
                            ); ?>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php _e('Popular Cities', 'zippicks-favorites'); ?></h3>
                        <div class="stat-number"><?php echo count($analytics_data['top_cities']); ?></div>
                        <div class="stat-detail">
                            <?php 
                            if (!empty($analytics_data['top_cities'])) {
                                echo esc_html($analytics_data['top_cities'][0]['display_name']);
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php _e('Avg per User', 'zippicks-favorites'); ?></h3>
                        <div class="stat-number"><?php echo number_format($analytics_data['avg_per_user'], 1); ?></div>
                        <div class="stat-detail"><?php _e('favorites', 'zippicks-favorites'); ?></div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="charts-section">
                    <div class="chart-container">
                        <h3><?php _e('Favorites Over Time', 'zippicks-favorites'); ?></h3>
                        <canvas id="favorites-timeline-chart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3><?php _e('Top Cities', 'zippicks-favorites'); ?></h3>
                        <canvas id="cities-chart"></canvas>
                    </div>
                </div>
                
                <!-- Tables -->
                <div class="tables-section">
                    <div class="table-container">
                        <h3><?php _e('Most Favorited Businesses', 'zippicks-favorites'); ?></h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Business', 'zippicks-favorites'); ?></th>
                                    <th><?php _e('Category', 'zippicks-favorites'); ?></th>
                                    <th><?php _e('Location', 'zippicks-favorites'); ?></th>
                                    <th><?php _e('Favorites', 'zippicks-favorites'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analytics_data['top_businesses'] as $business): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo get_edit_post_link($business->business_id); ?>">
                                            <?php echo esc_html($business->business_name); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($business->category); ?></td>
                                    <td><?php echo esc_html($business->location); ?></td>
                                    <td><?php echo number_format($business->favorite_count); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="table-container">
                        <h3><?php _e('Location Patterns', 'zippicks-favorites'); ?></h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('City', 'zippicks-favorites'); ?></th>
                                    <th><?php _e('State', 'zippicks-favorites'); ?></th>
                                    <th><?php _e('Total Favorites', 'zippicks-favorites'); ?></th>
                                    <th><?php _e('Unique Users', 'zippicks-favorites'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analytics_data['location_patterns'] as $location): ?>
                                <tr>
                                    <td><?php echo esc_html($location->city); ?></td>
                                    <td><?php echo esc_html($location->state); ?></td>
                                    <td><?php echo number_format($location->total_favorites); ?></td>
                                    <td><?php echo number_format($location->unique_users); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <script>
                var analyticsData = <?php echo json_encode($analytics_data); ?>;
            </script>
        </div>
        <?php
    }
    
    public function render_settings_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('zippicks_favorites_settings');
            
            // Update settings
            update_option('zippicks_favorites_default_radius', intval($_POST['default_radius']));
            update_option('zippicks_favorites_max_radius', intval($_POST['max_radius']));
            update_option('zippicks_favorites_enable_geolocation', isset($_POST['enable_geolocation']));
            update_option('zippicks_favorites_per_page', intval($_POST['per_page']));
            update_option('zippicks_favorites_enable_map', isset($_POST['enable_map']));
            update_option('zippicks_favorites_map_provider', sanitize_text_field($_POST['map_provider']));
            update_option('zippicks_favorites_enable_export', isset($_POST['enable_export']));
            update_option('zippicks_favorites_track_analytics', isset($_POST['track_analytics']));
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'zippicks-favorites') . '</p></div>';
        }
        
        // Get current settings
        $default_radius = get_option('zippicks_favorites_default_radius', 10);
        $max_radius = get_option('zippicks_favorites_max_radius', 50);
        $enable_geolocation = get_option('zippicks_favorites_enable_geolocation', true);
        $per_page = get_option('zippicks_favorites_per_page', 20);
        $enable_map = get_option('zippicks_favorites_enable_map', true);
        $map_provider = get_option('zippicks_favorites_map_provider', 'mapbox');
        $enable_export = get_option('zippicks_favorites_enable_export', true);
        $track_analytics = get_option('zippicks_favorites_track_analytics', true);
        ?>
        <div class="wrap">
            <h1><?php _e('Favorites Settings', 'zippicks-favorites'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('zippicks_favorites_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Location Settings', 'zippicks-favorites'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="enable_geolocation" value="1" <?php checked($enable_geolocation); ?>>
                                    <?php _e('Enable geolocation features', 'zippicks-favorites'); ?>
                                </label>
                                <br><br>
                                
                                <label>
                                    <?php _e('Default search radius:', 'zippicks-favorites'); ?>
                                    <input type="number" name="default_radius" value="<?php echo $default_radius; ?>" min="1" max="100" class="small-text"> km
                                </label>
                                <br><br>
                                
                                <label>
                                    <?php _e('Maximum search radius:', 'zippicks-favorites'); ?>
                                    <input type="number" name="max_radius" value="<?php echo $max_radius; ?>" min="10" max="200" class="small-text"> km
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Display Settings', 'zippicks-favorites'); ?></th>
                        <td>
                            <label>
                                <?php _e('Items per page:', 'zippicks-favorites'); ?>
                                <input type="number" name="per_page" value="<?php echo $per_page; ?>" min="5" max="100" class="small-text">
                            </label>
                            <br><br>
                            
                            <label>
                                <input type="checkbox" name="enable_export" value="1" <?php checked($enable_export); ?>>
                                <?php _e('Enable export functionality', 'zippicks-favorites'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Map Settings', 'zippicks-favorites'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_map" value="1" <?php checked($enable_map); ?>>
                                <?php _e('Enable map view', 'zippicks-favorites'); ?>
                            </label>
                            <br><br>
                            
                            <label>
                                <?php _e('Map provider:', 'zippicks-favorites'); ?>
                                <select name="map_provider">
                                    <option value="mapbox" <?php selected($map_provider, 'mapbox'); ?>>Mapbox</option>
                                    <option value="google" <?php selected($map_provider, 'google'); ?>>Google Maps</option>
                                    <option value="leaflet" <?php selected($map_provider, 'leaflet'); ?>>OpenStreetMap</option>
                                </select>
                            </label>
                            <p class="description">
                                <?php _e('Note: Mapbox and Google Maps require API keys to be configured separately.', 'zippicks-favorites'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Analytics', 'zippicks-favorites'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="track_analytics" value="1" <?php checked($track_analytics); ?>>
                                <?php _e('Track favorites analytics', 'zippicks-favorites'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Enable tracking of user behavior and location patterns for analytics.', 'zippicks-favorites'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function render_dashboard_widget() {
        $analytics_data = $this->analytics->get_dashboard_summary();
        ?>
        <div class="zippicks-favorites-widget">
            <div class="widget-summary">
                <div class="summary-item">
                    <span class="label"><?php _e('Total Favorites:', 'zippicks-favorites'); ?></span>
                    <span class="value"><?php echo number_format($analytics_data['total_favorites']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label"><?php _e('This Week:', 'zippicks-favorites'); ?></span>
                    <span class="value">+<?php echo number_format($analytics_data['favorites_this_week']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label"><?php _e('Top City:', 'zippicks-favorites'); ?></span>
                    <span class="value"><?php echo esc_html($analytics_data['top_city']); ?></span>
                </div>
            </div>
            
            <p class="widget-link">
                <a href="<?php echo admin_url('admin.php?page=zippicks-favorites'); ?>" class="button">
                    <?php _e('View Full Analytics', 'zippicks-favorites'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}