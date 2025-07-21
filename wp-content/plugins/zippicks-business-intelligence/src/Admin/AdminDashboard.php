<?php
/**
 * Admin Dashboard
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Admin;

class AdminDashboard {
    
    /**
     * Services
     *
     * @var array
     */
    private $services;
    
    /**
     * Constructor
     *
     * @param array $services
     */
    public function __construct(array $services) {
        $this->services = $services;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Business Intelligence', 'zippicks-business-intelligence'),
            __('Business Intel', 'zippicks-business-intelligence'),
            'manage_business_intelligence',
            'zippicks-business-intelligence',
            [$this, 'display_dashboard'],
            'dashicons-store',
            30
        );
        
        // Dashboard submenu (rename the first item)
        add_submenu_page(
            'zippicks-business-intelligence',
            __('Dashboard', 'zippicks-business-intelligence'),
            __('Dashboard', 'zippicks-business-intelligence'),
            'manage_business_intelligence',
            'zippicks-business-intelligence',
            [$this, 'display_dashboard']
        );
        
        // Cities submenu
        add_submenu_page(
            'zippicks-business-intelligence',
            __('Cities', 'zippicks-business-intelligence'),
            __('Cities', 'zippicks-business-intelligence'),
            'manage_business_intelligence',
            'zippicks-bi-cities',
            [$this, 'display_cities']
        );
        
        // API Logs submenu
        add_submenu_page(
            'zippicks-business-intelligence',
            __('API Logs', 'zippicks-business-intelligence'),
            __('API Logs', 'zippicks-business-intelligence'),
            'view_business_intelligence_logs',
            'zippicks-bi-logs',
            [$this, 'display_logs']
        );
        
        // Debug Panel submenu
        add_submenu_page(
            'zippicks-business-intelligence',
            __('Debug Panel', 'zippicks-business-intelligence'),
            __('Debug', 'zippicks-business-intelligence'),
            'manage_business_intelligence',
            'zippicks-bi-debug',
            [$this, 'display_debug_panel']
        );
        
        // Cache Inspector submenu
        add_submenu_page(
            'zippicks-business-intelligence',
            __('Cache Inspector', 'zippicks-business-intelligence'),
            __('Cache', 'zippicks-business-intelligence'),
            'manage_business_intelligence',
            'zippicks-bi-cache',
            [$this, 'display_cache_inspector']
        );
    }
    
    /**
     * Display dashboard page
     */
    public function display_dashboard() {
        // Get statistics
        $stats = $this->services['business']->get_statistics();
        $config_errors = $this->services['config']->validate();
        
        // Health check
        $api_health = $this->services['api_client']->health_check();
        $cache_health = $this->services['cache']->health_check();
        
        include ZIPPICKS_BI_PLUGIN_DIR . 'views/admin/dashboard.php';
    }
    
    /**
     * Display cities page
     */
    public function display_cities() {
        global $wpdb;
        
        // Get cities from cache
        $table = $wpdb->prefix . 'zippicks_business_cache';
        $cities = $wpdb->get_results(
            "SELECT city, COUNT(DISTINCT zpid) as business_count, MAX(updated_at) as last_updated
             FROM {$table}
             GROUP BY city
             ORDER BY business_count DESC",
            ARRAY_A
        );
        
        include ZIPPICKS_BI_PLUGIN_DIR . 'views/admin/cities.php';
    }
    
    /**
     * Display API logs page
     */
    public function display_logs() {
        // Handle clear logs action
        if (isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
            if (!wp_verify_nonce($_POST['bi_logs_nonce'], 'bi_clear_logs')) {
                wp_die(__('Security check failed', 'zippicks-business-intelligence'));
            }
            
            if (current_user_can('manage_business_intelligence')) {
                $deleted = $this->services['logger']->cleanup_old_logs(30);
                add_action('admin_notices', function() use ($deleted) {
                    ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php printf(__('Cleared %d old log entries.', 'zippicks-business-intelligence'), $deleted); ?></p>
                    </div>
                    <?php
                });
            }
        }
        
        // Get filter parameters
        $filters = [];
        if (!empty($_GET['filter_endpoint'])) {
            $filters['endpoint'] = sanitize_text_field($_GET['filter_endpoint']);
        }
        if (!empty($_GET['filter_method'])) {
            $filters['method'] = sanitize_text_field($_GET['filter_method']);
        }
        if (!empty($_GET['filter_status'])) {
            switch ($_GET['filter_status']) {
                case 'success':
                    $filters['status_min'] = 200;
                    $filters['status_max'] = 299;
                    break;
                case 'error':
                    $filters['status_min'] = 400;
                    $filters['status_max'] = 599;
                    break;
                case 'failed':
                    $filters['has_error'] = true;
                    break;
            }
        }
        if (!empty($_GET['filter_date'])) {
            switch ($_GET['filter_date']) {
                case 'today':
                    $filters['date_from'] = date('Y-m-d 00:00:00');
                    break;
                case 'yesterday':
                    $filters['date_from'] = date('Y-m-d 00:00:00', strtotime('-1 day'));
                    $filters['date_to'] = date('Y-m-d 23:59:59', strtotime('-1 day'));
                    break;
                case 'week':
                    $filters['date_from'] = date('Y-m-d 00:00:00', strtotime('-7 days'));
                    break;
                case 'month':
                    $filters['date_from'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
                    break;
            }
        }
        
        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        // Get logs using the logger service with filters
        $logs = [];
        $total_items = 0;
        
        if (isset($this->services['logger'])) {
            // Use enhanced query with filters
            global $wpdb;
            $table = $wpdb->prefix . 'zippicks_bi_api_log';
            
            $where_clauses = ['1=1'];
            $where_values = [];
            
            if (!empty($filters['endpoint'])) {
                $where_clauses[] = 'endpoint = %s';
                $where_values[] = $filters['endpoint'];
            }
            if (!empty($filters['method'])) {
                $where_clauses[] = 'method = %s';
                $where_values[] = $filters['method'];
            }
            if (isset($filters['status_min']) && isset($filters['status_max'])) {
                $where_clauses[] = 'status_code BETWEEN %d AND %d';
                $where_values[] = $filters['status_min'];
                $where_values[] = $filters['status_max'];
            }
            if (!empty($filters['has_error'])) {
                $where_clauses[] = '(status_code = 0 OR error_message IS NOT NULL)';
            }
            if (!empty($filters['date_from'])) {
                $where_clauses[] = 'created_at >= %s';
                $where_values[] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $where_clauses[] = 'created_at <= %s';
                $where_values[] = $filters['date_to'];
            }
            
            $where_sql = implode(' AND ', $where_clauses);
            
            // Get total count
            $count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
            if (!empty($where_values)) {
                $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
            } else {
                $total_items = $wpdb->get_var($count_query);
            }
            
            // Get logs
            $logs_query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $query_values = array_merge($where_values, [$per_page, $offset]);
            
            $logs = $wpdb->get_results(
                $wpdb->prepare($logs_query, $query_values),
                ARRAY_A
            );
        }
        
        $total_pages = ceil($total_items / $per_page);
        
        include ZIPPICKS_BI_PLUGIN_DIR . 'views/admin/logs.php';
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'zippicks_bi_status',
            __('Business Intelligence Status', 'zippicks-business-intelligence'),
            [$this, 'display_dashboard_widget']
        );
    }
    
    /**
     * Display dashboard widget
     */
    public function display_dashboard_widget() {
        $stats = $this->services['business']->get_statistics();
        $api_health = $this->services['api_client']->health_check();
        
        ?>
        <div class="zippicks-bi-widget">
            <div class="status-grid">
                <div class="status-item">
                    <span class="label"><?php _e('Total Businesses:', 'zippicks-business-intelligence'); ?></span>
                    <span class="value"><?php echo number_format($stats['total_businesses_cached']); ?></span>
                </div>
                <div class="status-item">
                    <span class="label"><?php _e('Cities:', 'zippicks-business-intelligence'); ?></span>
                    <span class="value"><?php echo number_format($stats['total_cities']); ?></span>
                </div>
                <div class="status-item">
                    <span class="label"><?php _e('API Status:', 'zippicks-business-intelligence'); ?></span>
                    <span class="value <?php echo $api_health['status'] === 'healthy' ? 'healthy' : 'unhealthy'; ?>">
                        <?php echo ucfirst($api_health['status']); ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="label"><?php _e('API Calls Today:', 'zippicks-business-intelligence'); ?></span>
                    <span class="value"><?php echo number_format($stats['api_requests_today']); ?></span>
                </div>
            </div>
            <p class="actions">
                <a href="<?php echo admin_url('admin.php?page=zippicks-business-intelligence'); ?>" class="button">
                    <?php _e('View Dashboard', 'zippicks-business-intelligence'); ?>
                </a>
            </p>
        </div>
        <style>
            .status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
            .status-item { display: flex; justify-content: space-between; }
            .status-item .label { color: #666; }
            .status-item .value { font-weight: bold; }
            .status-item .value.healthy { color: #46b450; }
            .status-item .value.unhealthy { color: #dc3232; }
        </style>
        <?php
    }
    
    /**
     * Enqueue admin scripts
     *
     * @param string $hook
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'zippicks-business-intelligence') === false &&
            strpos($hook, 'zippicks-bi-') === false) {
            return;
        }
        
        wp_enqueue_style(
            'zippicks-bi-admin',
            ZIPPICKS_BI_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ZIPPICKS_BI_VERSION
        );
        
        wp_enqueue_script(
            'zippicks-bi-admin',
            ZIPPICKS_BI_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api'],
            ZIPPICKS_BI_VERSION,
            true
        );
        
        wp_localize_script('zippicks-bi-admin', 'zippicks_bi', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'api_url' => rest_url('zippicks/v1/businesses'),
            'nonce' => wp_create_nonce('zippicks_bi_ajax'),
            'strings' => [
                'loading' => __('Loading...', 'zippicks-business-intelligence'),
                'error' => __('An error occurred', 'zippicks-business-intelligence'),
                'confirm_trigger' => __('Are you sure you want to trigger collection for this city?', 'zippicks-business-intelligence'),
                'confirm_clear_cache' => __('Are you sure you want to clear all cache?', 'zippicks-business-intelligence')
            ]
        ]);
    }
    
    /**
     * AJAX handler: Get city businesses
     */
    public function ajax_get_city_businesses() {
        check_ajax_referer('zippicks_bi_ajax', 'nonce');
        
        if (!current_user_can('manage_business_intelligence')) {
            wp_die(__('Insufficient permissions', 'zippicks-business-intelligence'));
        }
        
        $city = sanitize_text_field($_POST['city'] ?? '');
        
        if (empty($city)) {
            wp_send_json_error(['message' => __('City is required', 'zippicks-business-intelligence')]);
        }
        
        try {
            $businesses = $this->services['business']->get_city_businesses($city);
            wp_send_json_success([
                'businesses' => $businesses,
                'count' => count($businesses)
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX handler: Trigger collection
     */
    public function ajax_trigger_collection() {
        check_ajax_referer('zippicks_bi_ajax', 'nonce');
        
        if (!current_user_can('manage_business_intelligence')) {
            wp_die(__('Insufficient permissions', 'zippicks-business-intelligence'));
        }
        
        $city = sanitize_text_field($_POST['city'] ?? '');
        
        if (empty($city)) {
            wp_send_json_error(['message' => __('City is required', 'zippicks-business-intelligence')]);
        }
        
        $result = $this->services['business']->trigger_city_collection($city);
        
        if ($result) {
            wp_send_json_success(['message' => __('Collection triggered successfully', 'zippicks-business-intelligence')]);
        } else {
            wp_send_json_error(['message' => __('Failed to trigger collection', 'zippicks-business-intelligence')]);
        }
    }
    
    /**
     * AJAX handler: Clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('zippicks_bi_ajax', 'nonce');
        
        if (!current_user_can('manage_business_intelligence')) {
            wp_die(__('Insufficient permissions', 'zippicks-business-intelligence'));
        }
        
        $this->services['cache']->flush();
        
        wp_send_json_success(['message' => __('Cache cleared successfully', 'zippicks-business-intelligence')]);
    }
    
    /**
     * Display debug panel page
     */
    public function display_debug_panel() {
        // Get system information
        $debug_info = [
            'plugin_version' => ZIPPICKS_BI_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->get_mysql_version(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ];
        
        // Get configuration
        $config_values = [
            'api_url' => $this->services['config']->get('api_url'),
            'api_key' => $this->services['config']->has_api_key() ? 'Set (hidden)' : 'Not set',
            'cache_ttl' => $this->services['config']->get('cache_ttl'),
            'debug_mode' => $this->services['config']->get('debug_mode') ? 'Enabled' : 'Disabled',
            'rate_limit' => $this->services['config']->get('rate_limit'),
            'retry_attempts' => $this->services['config']->get('retry_attempts'),
            'timeout' => $this->services['config']->get('timeout')
        ];
        
        // Get API stats if logger is available
        $api_stats = [];
        if (isset($this->services['logger'])) {
            $api_stats = $this->services['logger']->get_statistics('day');
        }
        
        // Get cache stats
        $cache_stats = $this->services['cache']->get_stats();
        
        // Get recent errors
        $recent_errors = [];
        if (isset($this->services['logger'])) {
            $recent_errors = $this->services['logger']->get_recent_logs(10, ['has_error' => true]);
        }
        
        // Get Foundation services status
        $foundation_services = $this->check_foundation_services();
        
        // Check database tables
        $db_tables = $this->check_database_tables();
        
        include ZIPPICKS_BI_PLUGIN_DIR . 'views/admin/debug-panel.php';
    }
    
    /**
     * Get MySQL version
     */
    private function get_mysql_version() {
        global $wpdb;
        $version = $wpdb->get_var("SELECT VERSION()");
        return $version ?: 'Unknown';
    }
    
    /**
     * Check Foundation services
     */
    private function check_foundation_services() {
        if (!function_exists('zippicks')) {
            return ['available' => false];
        }
        
        $services = [
            'available' => true,
            'services' => []
        ];
        
        $check_services = [
            'logger' => 'Logger',
            'cache' => 'Cache Manager',
            'database.installer' => 'Database Installer',
            'queue' => 'Queue System',
            'monitor' => 'Monitoring'
        ];
        
        foreach ($check_services as $key => $name) {
            $services['services'][$key] = [
                'name' => $name,
                'available' => zippicks()->has($key)
            ];
        }
        
        return $services;
    }
    
    /**
     * Check database tables
     */
    private function check_database_tables() {
        global $wpdb;
        
        $tables = [
            'business_cache' => $wpdb->prefix . 'zippicks_business_cache',
            'api_log' => $wpdb->prefix . 'zippicks_bi_api_log'
        ];
        
        $results = [];
        foreach ($tables as $key => $table_name) {
            $exists = $wpdb->get_var(
                $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
            ) !== null;
            
            $count = 0;
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            }
            
            $results[$key] = [
                'name' => $table_name,
                'exists' => $exists,
                'count' => $count
            ];
        }
        
        return $results;
    }
    
    /**
     * Display cache inspector page
     */
    public function display_cache_inspector() {
        // Handle actions
        if (isset($_POST['action']) && $_POST['action'] === 'delete_cache_key') {
            check_admin_referer('zippicks_bi_cache_action');
            $key = sanitize_text_field($_POST['cache_key']);
            if ($this->services['cache']->delete($key)) {
                add_settings_error(
                    'zippicks_bi_cache',
                    'cache_deleted',
                    sprintf(__('Cache key "%s" deleted successfully.', 'zippicks-business-intelligence'), $key),
                    'success'
                );
            }
        }
        
        if (isset($_POST['action']) && $_POST['action'] === 'clear_pattern') {
            check_admin_referer('zippicks_bi_cache_action');
            $pattern = sanitize_text_field($_POST['cache_pattern']);
            $cleared = $this->services['cache']->clear_by_pattern($pattern);
            add_settings_error(
                'zippicks_bi_cache',
                'pattern_cleared',
                sprintf(__('Cleared %d cache entries matching pattern "%s".', 'zippicks-business-intelligence'), $cleared, $pattern),
                'success'
            );
        }
        
        // Get cache entries
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $cache_entries = $this->services['cache']->get_all_keys($search ? "*{$search}*" : '*');
        
        // Get cache statistics
        $cache_stats = $this->services['cache']->get_stats();
        
        // Prepare entries with details
        $detailed_entries = [];
        foreach ($cache_entries as $key) {
            $details = $this->services['cache']->get_entry_details($key);
            if ($details) {
                $detailed_entries[] = $details;
            }
        }
        
        // Sort by key name
        usort($detailed_entries, function($a, $b) {
            return strcmp($a['key'], $b['key']);
        });
        
        include ZIPPICKS_BI_PLUGIN_DIR . 'views/admin/cache-inspector.php';
    }
}