<?php
/**
 * Admin Interface
 * 
 * Provides admin dashboard for search analytics and settings
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Smart Search', 'zippicks-smart-search'),
            __('Smart Search', 'zippicks-smart-search'),
            'manage_options',
            'zippicks-smart-search',
            [$this, 'display_dashboard'],
            'dashicons-search',
            26
        );
        
        // Dashboard submenu
        add_submenu_page(
            'zippicks-smart-search',
            __('Dashboard', 'zippicks-smart-search'),
            __('Dashboard', 'zippicks-smart-search'),
            'manage_options',
            'zippicks-smart-search',
            [$this, 'display_dashboard']
        );
        
        // Analytics submenu
        add_submenu_page(
            'zippicks-smart-search',
            __('Analytics', 'zippicks-smart-search'),
            __('Analytics', 'zippicks-smart-search'),
            'manage_options',
            'zippicks-search-analytics',
            [$this, 'display_analytics']
        );
        
        // Demand tracking submenu
        add_submenu_page(
            'zippicks-smart-search',
            __('Demand Tracking', 'zippicks-smart-search'),
            __('Demand Tracking', 'zippicks-smart-search'),
            'manage_options',
            'zippicks-search-demand',
            [$this, 'display_demand']
        );
        
        // Settings submenu
        add_submenu_page(
            'zippicks-smart-search',
            __('Settings', 'zippicks-smart-search'),
            __('Settings', 'zippicks-smart-search'),
            'manage_options',
            'zippicks-search-settings',
            [$this, 'display_settings']
        );
    }
    
    /**
     * Display dashboard page
     */
    public function display_dashboard() {
        // Get API status
        $api_client = new API_Client();
        $api_status = $api_client->check_connection();
        
        // Get cache status
        $cache = Cache_Manager::instance();
        $cache_stats = $cache->get_stats();
        
        // Get recent searches from API
        $analytics = new Analytics();
        $recent_searches = $analytics->get_recent_searches(10);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="zippicks-search-dashboard">
                <!-- Status Cards -->
                <div class="status-grid">
                    <div class="status-card">
                        <h3><?php _e('API Status', 'zippicks-smart-search'); ?></h3>
                        <div class="status-indicator <?php echo $api_status ? 'status-green' : 'status-red'; ?>">
                            <span class="dashicons <?php echo $api_status ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                            <?php echo $api_status ? __('Connected', 'zippicks-smart-search') : __('Disconnected', 'zippicks-smart-search'); ?>
                        </div>
                        <?php if (!$api_status): ?>
                        <p class="description"><?php _e('Check API configuration in wp-config.php', 'zippicks-smart-search'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="status-card">
                        <h3><?php _e('Cache Status', 'zippicks-smart-search'); ?></h3>
                        <div class="status-indicator <?php echo $cache_stats['enabled'] ? 'status-green' : 'status-yellow'; ?>">
                            <span class="dashicons <?php echo $cache_stats['enabled'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                            <?php 
                            if ($cache_stats['enabled']) {
                                echo sprintf(__('%s Cache Active', 'zippicks-smart-search'), ucfirst($cache_stats['driver']));
                            } else {
                                _e('No Object Cache', 'zippicks-smart-search');
                            }
                            ?>
                        </div>
                        <?php if ($cache_stats['enabled']): ?>
                        <p>
                            <button type="button" class="button" id="clear-search-cache">
                                <?php _e('Clear Search Cache', 'zippicks-smart-search'); ?>
                            </button>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="status-card">
                        <h3><?php _e('Geo Service', 'zippicks-smart-search'); ?></h3>
                        <div class="status-indicator <?php echo class_exists('\\ZipPicks\\Geo\\Location_Detector') ? 'status-green' : 'status-red'; ?>">
                            <span class="dashicons <?php echo class_exists('\\ZipPicks\\Geo\\Location_Detector') ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                            <?php echo class_exists('\\ZipPicks\\Geo\\Location_Detector') ? __('Active', 'zippicks-smart-search') : __('Not Found', 'zippicks-smart-search'); ?>
                        </div>
                        <?php if (!class_exists('\\ZipPicks\\Geo\\Location_Detector')): ?>
                        <p class="description"><?php _e('Geo Service plugin is required for location detection', 'zippicks-smart-search'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Searches -->
                <div class="dashboard-section">
                    <h2><?php _e('Recent Searches', 'zippicks-smart-search'); ?></h2>
                    <?php if (!is_wp_error($recent_searches) && !empty($recent_searches)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Query', 'zippicks-smart-search'); ?></th>
                                <th><?php _e('Intent', 'zippicks-smart-search'); ?></th>
                                <th><?php _e('Results', 'zippicks-smart-search'); ?></th>
                                <th><?php _e('Location', 'zippicks-smart-search'); ?></th>
                                <th><?php _e('Time', 'zippicks-smart-search'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_searches as $search): ?>
                            <tr>
                                <td><?php echo esc_html($search['query']); ?></td>
                                <td>
                                    <span class="intent-badge intent-<?php echo esc_attr($search['intent']); ?>">
                                        <?php echo esc_html(ucfirst($search['intent'])); ?>
                                    </span>
                                </td>
                                <td><?php echo intval($search['result_count']); ?></td>
                                <td><?php echo esc_html($search['location']['city'] ?? 'Unknown'); ?></td>
                                <td><?php echo human_time_diff(strtotime($search['created_at'])); ?> ago</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p><?php _e('No recent searches found.', 'zippicks-smart-search'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .zippicks-search-dashboard .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .status-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .status-card h3 {
            margin-top: 0;
        }
        
        .status-indicator {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-green { color: #46b450; }
        .status-yellow { color: #ffb900; }
        .status-red { color: #dc3232; }
        
        .intent-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .intent-vibe { background: #e1d5f0; color: #533a73; }
        .intent-utility { background: #d5e5f0; color: #2c5282; }
        .intent-hybrid { background: #f0e5d5; color: #826c2c; }
        </style>
        <?php
    }
    
    /**
     * Display analytics page
     */
    public function display_analytics() {
        $analytics = new Analytics();
        $stats = $analytics->get_stats();
        
        if (is_wp_error($stats)) {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="notice notice-error">
                    <p><?php echo esc_html($stats->get_error_message()); ?></p>
                </div>
            </div>
            <?php
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="analytics-grid">
                <!-- Overview Stats -->
                <div class="analytics-card">
                    <h3><?php _e('Overview', 'zippicks-smart-search'); ?></h3>
                    <div class="stat-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($stats['total_searches'] ?? 0); ?></div>
                            <div class="stat-label"><?php _e('Total Searches', 'zippicks-smart-search'); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($stats['unique_users'] ?? 0); ?></div>
                            <div class="stat-label"><?php _e('Unique Users', 'zippicks-smart-search'); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($stats['avg_results'] ?? 0, 1); ?></div>
                            <div class="stat-label"><?php _e('Avg Results/Search', 'zippicks-smart-search'); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo ($stats['click_through_rate'] ?? 0) . '%'; ?></div>
                            <div class="stat-label"><?php _e('Click-through Rate', 'zippicks-smart-search'); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Top Searches -->
                <div class="analytics-card">
                    <h3><?php _e('Top Searches', 'zippicks-smart-search'); ?></h3>
                    <?php if (!empty($stats['top_searches'])): ?>
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th><?php _e('Query', 'zippicks-smart-search'); ?></th>
                                <th><?php _e('Count', 'zippicks-smart-search'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($stats['top_searches'], 0, 10) as $search): ?>
                            <tr>
                                <td><?php echo esc_html($search['query']); ?></td>
                                <td><?php echo number_format($search['count']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p><?php _e('No search data available yet.', 'zippicks-smart-search'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Search Intent Distribution -->
                <div class="analytics-card">
                    <h3><?php _e('Search Intent Distribution', 'zippicks-smart-search'); ?></h3>
                    <?php if (!empty($stats['intent_distribution'])): ?>
                    <div class="intent-distribution">
                        <?php foreach ($stats['intent_distribution'] as $intent => $percentage): ?>
                        <div class="intent-bar">
                            <div class="intent-label"><?php echo esc_html(ucfirst($intent)); ?></div>
                            <div class="progress-bar">
                                <div class="progress-fill intent-<?php echo esc_attr($intent); ?>" style="width: <?php echo $percentage; ?>%">
                                    <?php echo $percentage; ?>%
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p><?php _e('No intent data available yet.', 'zippicks-smart-search'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .analytics-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        
        .analytics-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 600;
            color: #23282d;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .analytics-table {
            width: 100%;
            margin-top: 15px;
        }
        
        .analytics-table th,
        .analytics-table td {
            padding: 8px 12px;
            text-align: left;
        }
        
        .intent-distribution {
            margin-top: 15px;
        }
        
        .intent-bar {
            margin-bottom: 15px;
        }
        
        .intent-label {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .progress-bar {
            background: #f0f0f1;
            height: 25px;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            display: flex;
            align-items: center;
            padding: 0 10px;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
        }
        </style>
        <?php
    }
    
    /**
     * Display demand tracking page
     */
    public function display_demand() {
        $demand_tracker = new Demand_Tracker();
        $demand_data = $demand_tracker->get_demand_analytics();
        
        if (is_wp_error($demand_data)) {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="notice notice-error">
                    <p><?php echo esc_html($demand_data->get_error_message()); ?></p>
                </div>
            </div>
            <?php
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <p><?php _e('Track which businesses users are looking for that aren\'t yet in the system.', 'zippicks-smart-search'); ?></p>
            
            <?php if (!empty($demand_data['top_demanded'])): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Business', 'zippicks-smart-search'); ?></th>
                        <th><?php _e('Requests', 'zippicks-smart-search'); ?></th>
                        <th><?php _e('Unique Users', 'zippicks-smart-search'); ?></th>
                        <th><?php _e('First Requested', 'zippicks-smart-search'); ?></th>
                        <th><?php _e('Last Requested', 'zippicks-smart-search'); ?></th>
                        <th><?php _e('Status', 'zippicks-smart-search'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($demand_data['top_demanded'] as $demand): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($demand['name']); ?></strong>
                            <br>
                            <span class="description"><?php echo esc_html($demand['zpid']); ?></span>
                        </td>
                        <td><?php echo number_format($demand['request_count']); ?></td>
                        <td><?php echo number_format($demand['unique_users']); ?></td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($demand['first_requested'])); ?></td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($demand['last_requested'])); ?></td>
                        <td>
                            <?php if ($demand['added']): ?>
                            <span class="status-badge status-added"><?php _e('Added', 'zippicks-smart-search'); ?></span>
                            <?php else: ?>
                            <span class="status-badge status-pending"><?php _e('Pending', 'zippicks-smart-search'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?php _e('No demand data available yet.', 'zippicks-smart-search'); ?></p>
            <?php endif; ?>
        </div>
        
        <style>
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-added {
            background: #d5f0d5;
            color: #2c6e2c;
        }
        
        .status-pending {
            background: #f0e5d5;
            color: #826c2c;
        }
        </style>
        <?php
    }
    
    /**
     * Display settings page
     */
    public function display_settings() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('zippicks_search_settings');
                do_settings_sections('zippicks_search_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting('zippicks_search_settings', 'zippicks_search_cache_ttl');
        register_setting('zippicks_search_settings', 'zippicks_search_max_results');
        register_setting('zippicks_search_settings', 'zippicks_search_default_radius');
        register_setting('zippicks_search_settings', 'zippicks_search_default_location');
        register_setting('zippicks_search_settings', 'zippicks_search_frontend_api_key');
        
        // General section
        add_settings_section(
            'zippicks_search_general',
            __('General Settings', 'zippicks-smart-search'),
            null,
            'zippicks_search_settings'
        );
        
        add_settings_field(
            'cache_ttl',
            __('Cache TTL (seconds)', 'zippicks-smart-search'),
            [$this, 'render_cache_ttl_field'],
            'zippicks_search_settings',
            'zippicks_search_general'
        );
        
        add_settings_field(
            'max_results',
            __('Max Results', 'zippicks-smart-search'),
            [$this, 'render_max_results_field'],
            'zippicks_search_settings',
            'zippicks_search_general'
        );
        
        add_settings_field(
            'default_radius',
            __('Default Search Radius (miles)', 'zippicks-smart-search'),
            [$this, 'render_default_radius_field'],
            'zippicks_search_settings',
            'zippicks_search_general'
        );
        
        add_settings_field(
            'default_location',
            __('Default Location', 'zippicks-smart-search'),
            [$this, 'render_default_location_field'],
            'zippicks_search_settings',
            'zippicks_search_general'
        );
        
        // API section
        add_settings_section(
            'zippicks_search_api',
            __('API Settings', 'zippicks-smart-search'),
            [$this, 'render_api_section'],
            'zippicks_search_settings'
        );
        
        add_settings_field(
            'frontend_api_key',
            __('Frontend API Key', 'zippicks-smart-search'),
            [$this, 'render_frontend_api_key_field'],
            'zippicks_search_settings',
            'zippicks_search_api'
        );
    }
    
    /**
     * Render cache TTL field
     */
    public function render_cache_ttl_field() {
        $value = get_option('zippicks_search_cache_ttl', 300);
        ?>
        <input type="number" name="zippicks_search_cache_ttl" value="<?php echo esc_attr($value); ?>" min="0" max="3600" />
        <p class="description"><?php _e('How long to cache search results (0 to use default per search type)', 'zippicks-smart-search'); ?></p>
        <?php
    }
    
    /**
     * Render max results field
     */
    public function render_max_results_field() {
        $value = get_option('zippicks_search_max_results', 20);
        ?>
        <input type="number" name="zippicks_search_max_results" value="<?php echo esc_attr($value); ?>" min="1" max="100" />
        <p class="description"><?php _e('Maximum number of search results to display', 'zippicks-smart-search'); ?></p>
        <?php
    }
    
    /**
     * Render default radius field
     */
    public function render_default_radius_field() {
        $value = get_option('zippicks_search_default_radius', 10);
        ?>
        <input type="number" name="zippicks_search_default_radius" value="<?php echo esc_attr($value); ?>" min="1" max="50" />
        <p class="description"><?php _e('Default search radius in miles', 'zippicks-smart-search'); ?></p>
        <?php
    }
    
    /**
     * Render default location field
     */
    public function render_default_location_field() {
        $value = get_option('zippicks_search_default_location', [
            'lat' => 34.0522,
            'lng' => -118.2437,
            'city' => 'Los Angeles',
            'state' => 'CA'
        ]);
        ?>
        <table class="form-table-inner">
            <tr>
                <td><label><?php _e('Latitude', 'zippicks-smart-search'); ?></label></td>
                <td><input type="text" name="zippicks_search_default_location[lat]" value="<?php echo esc_attr($value['lat']); ?>" /></td>
            </tr>
            <tr>
                <td><label><?php _e('Longitude', 'zippicks-smart-search'); ?></label></td>
                <td><input type="text" name="zippicks_search_default_location[lng]" value="<?php echo esc_attr($value['lng']); ?>" /></td>
            </tr>
            <tr>
                <td><label><?php _e('City', 'zippicks-smart-search'); ?></label></td>
                <td><input type="text" name="zippicks_search_default_location[city]" value="<?php echo esc_attr($value['city']); ?>" /></td>
            </tr>
            <tr>
                <td><label><?php _e('State', 'zippicks-smart-search'); ?></label></td>
                <td><input type="text" name="zippicks_search_default_location[state]" value="<?php echo esc_attr($value['state']); ?>" /></td>
            </tr>
        </table>
        <p class="description"><?php _e('Default location when user location cannot be determined', 'zippicks-smart-search'); ?></p>
        <?php
    }
    
    /**
     * Render API section description
     */
    public function render_api_section() {
        echo '<p>' . __('API configuration for frontend search functionality', 'zippicks-smart-search') . '</p>';
    }
    
    /**
     * Render frontend API key field
     */
    public function render_frontend_api_key_field() {
        $value = get_option('zippicks_search_frontend_api_key', '');
        ?>
        <input type="text" name="zippicks_search_frontend_api_key" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php _e('Read-only API key for frontend JavaScript (leave empty to disable direct API access)', 'zippicks-smart-search'); ?></p>
        <?php
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'zippicks-smart-search') === false && strpos($hook, 'zippicks-search') === false) {
            return;
        }
        
        wp_enqueue_script(
            'zippicks-search-admin',
            ZIPPICKS_SEARCH_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            ZIPPICKS_SEARCH_VERSION,
            true
        );
        
        wp_localize_script('zippicks-search-admin', 'zippicks_search_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_search_admin_nonce'),
            'strings' => [
                'clearing_cache' => __('Clearing cache...', 'zippicks-smart-search'),
                'cache_cleared' => __('Cache cleared successfully!', 'zippicks-smart-search'),
                'error' => __('An error occurred', 'zippicks-smart-search')
            ]
        ]);
    }
}