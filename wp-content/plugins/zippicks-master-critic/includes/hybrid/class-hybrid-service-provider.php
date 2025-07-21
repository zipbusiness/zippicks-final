<?php
/**
 * Hybrid Service Provider - Registers all hybrid data services
 * 
 * @package ZipPicks_Master_Critic
 * @subpackage Hybrid
 */

namespace ZipPicks\MasterCritic\Hybrid;

use ZipPicks\MasterCritic\Services\CacheManager;
use ZipPicks\MasterCritic\Services\AIService;

class HybridServiceProvider {
    
    /**
     * Service instances
     */
    private static ?SmartRouter $smart_router = null;
    private static ?ConfidenceEngine $confidence_engine = null;
    private static ?DataAggregator $data_aggregator = null;
    private static ?PaidAPIManager $paid_api_manager = null;
    private static ?CostOptimizer $cost_optimizer = null;
    
    /**
     * Register all hybrid services
     */
    public static function register(): void {
        // Register with Foundation if available
        if (function_exists('zippicks')) {
            self::register_with_foundation();
        }
        
        // Add hooks
        self::add_hooks();
        
        // Register admin interfaces
        if (is_admin()) {
            self::register_admin_interfaces();
        }
    }
    
    /**
     * Register services with Foundation
     */
    private static function register_with_foundation(): void {
        // Register Smart Router as primary data service
        zippicks()->bind('hybrid.smart_router', function() {
            return self::get_smart_router();
        });
        
        // Register individual services
        zippicks()->bind('hybrid.confidence_engine', function() {
            return self::get_confidence_engine();
        });
        
        zippicks()->bind('hybrid.data_aggregator', function() {
            return self::get_data_aggregator();
        });
        
        zippicks()->bind('hybrid.paid_api_manager', function() {
            return self::get_paid_api_manager();
        });
        
        zippicks()->bind('hybrid.cost_optimizer', function() {
            return self::get_cost_optimizer();
        });
    }
    
    /**
     * Add WordPress hooks
     */
    private static function add_hooks(): void {
        // REST API endpoints
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        
        // Cron jobs for optimization
        add_action('init', [__CLASS__, 'schedule_optimization_tasks']);
        
        // Anti-scraping protection
        add_action('init', [__CLASS__, 'init_scrape_protection'], 1);
        
        // Budget monitoring
        add_action('zippicks_budget_warning', [__CLASS__, 'handle_budget_warning']);
        add_action('zippicks_budget_critical', [__CLASS__, 'handle_budget_critical']);
        
        // AJAX handlers
        add_action('wp_ajax_zippicks_refresh_hybrid_dashboard', [__CLASS__, 'ajax_refresh_dashboard']);
        add_action('wp_ajax_zippicks_warm_cache', [__CLASS__, 'ajax_warm_cache']);
        add_action('wp_ajax_zippicks_clear_hybrid_stats', [__CLASS__, 'ajax_clear_stats']);
        add_action('wp_ajax_zippicks_execute_recommendation', [__CLASS__, 'ajax_execute_recommendation']);
    }
    
    /**
     * Register admin interfaces
     */
    private static function register_admin_interfaces(): void {
        // Don't register admin menu here - it's now handled by the main Admin class
        // Only register assets and dashboard widget
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('wp_dashboard_setup', [__CLASS__, 'add_dashboard_widget']);
    }
    
    /**
     * Get Smart Router instance
     */
    public static function get_smart_router(): SmartRouter {
        if (self::$smart_router === null) {
            self::$smart_router = new SmartRouter();
        }
        return self::$smart_router;
    }
    
    /**
     * Get Confidence Engine instance
     */
    public static function get_confidence_engine(): ConfidenceEngine {
        if (self::$confidence_engine === null) {
            self::$confidence_engine = new ConfidenceEngine();
        }
        return self::$confidence_engine;
    }
    
    /**
     * Get Data Aggregator instance
     */
    public static function get_data_aggregator(): DataAggregator {
        if (self::$data_aggregator === null) {
            self::$data_aggregator = new DataAggregator();
        }
        return self::$data_aggregator;
    }
    
    /**
     * Get Paid API Manager instance
     */
    public static function get_paid_api_manager(): PaidAPIManager {
        if (self::$paid_api_manager === null) {
            self::$paid_api_manager = new PaidAPIManager();
        }
        return self::$paid_api_manager;
    }
    
    /**
     * Get Cost Optimizer instance
     */
    public static function get_cost_optimizer(): CostOptimizer {
        if (self::$cost_optimizer === null) {
            self::$cost_optimizer = new CostOptimizer();
        }
        return self::$cost_optimizer;
    }
    
    /**
     * Register REST API routes
     */
    public static function register_rest_routes(): void {
        register_rest_route('zippicks/v1', '/hybrid/query', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_hybrid_query'],
            'permission_callback' => '__return_true',
            'args' => [
                'business_name' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'city' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'state' => [
                    'type' => 'string'
                ],
                'list_type' => [
                    'type' => 'string'
                ],
                'include_reviews' => [
                    'type' => 'boolean'
                ]
            ]
        ]);
        
        register_rest_route('zippicks/v1', '/hybrid/stats', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_hybrid_stats'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    /**
     * Handle hybrid query via REST API
     */
    public static function handle_hybrid_query(\WP_REST_Request $request): \WP_REST_Response {
        $query = [
            'business_name' => $request->get_param('business_name'),
            'city' => $request->get_param('city'),
            'state' => $request->get_param('state') ?? '',
            'list_type' => $request->get_param('list_type') ?? 'general',
            'include_reviews' => $request->get_param('include_reviews') ?? false,
            'user_initiated' => true,
            'user_id' => get_current_user_id()
        ];
        
        try {
            $router = self::get_smart_router();
            $result = $router->route_query($query);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $result
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get hybrid system statistics
     */
    public static function get_hybrid_stats(): \WP_REST_Response {
        $optimizer = self::get_cost_optimizer();
        
        return new \WP_REST_Response([
            'daily_stats' => $optimizer->get_today_stats(),
            'monthly_stats' => $optimizer->get_monthly_stats(),
            'recommendations' => $optimizer->get_optimization_recommendations(),
            'api_usage' => self::get_paid_api_manager()->get_usage_stats()
        ], 200);
    }
    
    /**
     * Schedule optimization tasks
     */
    public static function schedule_optimization_tasks(): void {
        if (!wp_next_scheduled('zippicks_hybrid_daily_optimization')) {
            wp_schedule_event(time(), 'daily', 'zippicks_hybrid_daily_optimization');
        }
        
        if (!wp_next_scheduled('zippicks_hybrid_cache_warmup')) {
            wp_schedule_event(time(), 'twicedaily', 'zippicks_hybrid_cache_warmup');
        }
        
        add_action('zippicks_hybrid_daily_optimization', [__CLASS__, 'run_daily_optimization']);
        add_action('zippicks_hybrid_cache_warmup', [__CLASS__, 'run_cache_warmup']);
    }
    
    /**
     * Run daily optimization tasks
     */
    public static function run_daily_optimization(): void {
        $optimizer = self::get_cost_optimizer();
        
        // Reset daily stats
        $optimizer->track_query(['reset' => true]);
        
        // Clean up old logs
        self::cleanup_old_logs();
        
        // Generate optimization report
        $report = $optimizer->get_optimization_recommendations();
        
        // Email report to admin if issues found
        if (!empty($report)) {
            wp_mail(
                get_option('admin_email'),
                'ZipPicks Hybrid System Daily Report',
                self::format_optimization_report($report)
            );
        }
    }
    
    /**
     * Run cache warmup
     */
    public static function run_cache_warmup(): void {
        $optimizer = self::get_cost_optimizer();
        $candidates = $optimizer->get_pre_warm_candidates();
        
        $router = self::get_smart_router();
        
        foreach ($candidates as $candidate) {
            if ($optimizer->has_budget_remaining()) {
                $router->route_query($candidate['query']);
            }
        }
    }
    
    /**
     * Initialize scrape protection
     */
    public static function init_scrape_protection(): void {
        // Check for suspicious patterns
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check for API endpoints
        if (strpos($request_uri, '/api/') !== false || 
            strpos($request_uri, '/zippicks_list/') !== false ||
            strpos($request_uri, '/vibes-api/') !== false) {
            
            // Log potential scraping attempt
            self::log_scrape_attempt($ip, $request_uri, $user_agent);
            
            // Check rate limits
            if (self::is_rate_limited($ip)) {
                if (!headers_sent()) {
                    header('HTTP/1.1 429 Too Many Requests');
                    header('X-Robots-Tag: noindex');
                }
                wp_die('Rate limit exceeded', 'Too Many Requests', ['response' => 429]);
            }
        }
        
        // Add anti-scraping headers
        if (!is_admin() && !headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow');
            header('Cache-Control: private, max-age=0');
            header('X-ZipPicks-Source: frontend-only');
        }
    }
    
    /**
     * Log scrape attempt
     */
    private static function log_scrape_attempt(string $ip, string $path, string $user_agent): void {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'zippicks_scrape_log',
            [
                'ip_address' => $ip,
                'request_path' => substr($path, 0, 255),
                'user_agent' => $user_agent,
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'timestamp' => current_time('mysql')
            ]
        );
    }
    
    /**
     * Check if IP is rate limited
     */
    private static function is_rate_limited(string $ip): bool {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_scrape_log
             WHERE ip_address = %s 
             AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            $ip
        ));
        
        return (int)$count > 10; // More than 10 requests per minute
    }
    
    /**
     * Add admin menu
     * @deprecated This is now handled by the main Admin class
     */
    public static function add_admin_menu(): void {
        // This method is kept for backwards compatibility but is no longer used
        // The menu is now registered in ZipPicks_Master_Critic_Admin::add_admin_menu()
    }
    
    /**
     * Render admin page
     */
    public static function render_admin_page(): void {
        try {
            // Check user permissions
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }
            
            $optimizer = self::get_cost_optimizer();
            $api_manager = self::get_paid_api_manager();
            
            $daily_stats = $optimizer->get_today_stats();
            $monthly_stats = $optimizer->get_monthly_stats();
            $api_usage = $api_manager->get_usage_stats();
            $recommendations = $optimizer->get_optimization_recommendations();
            
            // Check if view file exists
            $view_file = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'admin/views/hybrid-dashboard.php';
            if (!file_exists($view_file)) {
                wp_die(__('Dashboard view file not found. Please reinstall the plugin.'));
            }
            
            include $view_file;
        } catch (\Exception $e) {
            wp_die(__('Error loading Hybrid Data dashboard: ') . esc_html($e->getMessage()));
        }
    }
    
    /**
     * Add dashboard widget
     */
    public static function add_dashboard_widget(): void {
        wp_add_dashboard_widget(
            'zippicks_hybrid_status',
            'ZipPicks Hybrid Data Status',
            [__CLASS__, 'render_dashboard_widget']
        );
    }
    
    /**
     * Render dashboard widget
     */
    public static function render_dashboard_widget(): void {
        $optimizer = self::get_cost_optimizer();
        $metrics = $optimizer->get_dashboard_metrics();
        
        echo '<div class="zippicks-hybrid-widget">';
        
        // Status indicator
        $status_class = 'optimal';
        $status_text = 'Optimal';
        
        if ($metrics['status'] === 'warning') {
            $status_class = 'warning';
            $status_text = 'Warning';
        } elseif ($metrics['status'] === 'critical') {
            $status_class = 'critical';
            $status_text = 'Critical';
        }
        
        echo '<div class="status-indicator ' . esc_attr($status_class) . '">';
        echo '<span class="status-dot"></span> System Status: ' . esc_html($status_text);
        echo '</div>';
        
        // Daily metrics
        echo '<div class="metric-grid">';
        echo '<div class="metric">';
        echo '<span class="label">Today\'s Cost:</span>';
        echo '<span class="value">' . esc_html($metrics['daily']['cost']) . '</span>';
        echo '</div>';
        
        echo '<div class="metric">';
        echo '<span class="label">Cache Hit Rate:</span>';
        echo '<span class="value">' . esc_html($metrics['daily']['cache_hit_rate']) . '</span>';
        echo '</div>';
        
        echo '<div class="metric">';
        echo '<span class="label">Monthly Savings:</span>';
        echo '<span class="value">' . esc_html($metrics['monthly']['savings']) . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<p class="description">';
        echo 'Hybrid system is processing ' . esc_html($metrics['daily']['queries']) . ' queries today ';
        echo 'with ' . esc_html($metrics['daily']['enhancement_rate']) . ' requiring paid APIs.';
        echo '</p>';
        
        echo '<p><a href="' . admin_url('admin.php?page=zippicks-hybrid-data') . '" class="button">View Details</a></p>';
        
        echo '</div>';
    }
    
    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, 'zippicks-hybrid') === false && $hook !== 'index.php') {
            return;
        }
        
        wp_enqueue_style(
            'zippicks-hybrid-admin',
            ZIPPICKS_MASTER_CRITIC_PLUGIN_URL . 'assets/css/hybrid-admin.css',
            [],
            ZIPPICKS_MASTER_CRITIC_VERSION
        );
        
        wp_enqueue_script(
            'zippicks-hybrid-admin',
            ZIPPICKS_MASTER_CRITIC_PLUGIN_URL . 'assets/js/hybrid-admin.js',
            ['jquery', 'wp-api', 'chart.js'],
            ZIPPICKS_MASTER_CRITIC_VERSION,
            true
        );
        
        wp_localize_script('zippicks-hybrid-admin', 'zippicks_hybrid_admin', [
            'apiUrl' => rest_url('zippicks/v1/hybrid'),
            'nonce' => wp_create_nonce('zippicks_hybrid_admin')
        ]);
    }
    
    /**
     * Handle budget warning
     */
    public static function handle_budget_warning(array $data): void {
        // Log warning
        error_log(sprintf(
            'ZipPicks Hybrid Budget Warning: %s%% used, $%s remaining',
            $data['usage_percent'],
            $data['remaining_budget']
        ));
        
        // Reduce enhancement threshold
        update_option('zippicks_hybrid_enhancement_threshold', 80);
    }
    
    /**
     * Handle budget critical
     */
    public static function handle_budget_critical(array $data): void {
        // Log critical
        error_log(sprintf(
            'ZipPicks Hybrid Budget CRITICAL: %s%% used, $%s remaining',
            $data['usage_percent'],
            $data['remaining_budget']
        ));
        
        // Emergency mode - only enhance top priority queries
        update_option('zippicks_hybrid_enhancement_threshold', 95);
        update_option('zippicks_hybrid_emergency_mode', true);
        
        // Email admin
        wp_mail(
            get_option('admin_email'),
            'CRITICAL: ZipPicks Hybrid Budget Alert',
            sprintf(
                "The hybrid data system has used %s%% of today's budget.\n\n" .
                "Remaining budget: $%s\n" .
                "System has entered emergency mode - only critical queries will use paid APIs.\n\n" .
                "Please review: %s",
                $data['usage_percent'],
                $data['remaining_budget'],
                admin_url('admin.php?page=zippicks-hybrid-data')
            )
        );
    }
    
    /**
     * Clean up old logs
     */
    private static function cleanup_old_logs(): void {
        global $wpdb;
        
        // Delete logs older than 30 days
        $tables = [
            'zippicks_query_metrics',
            'zippicks_api_usage_log',
            'zippicks_api_cost_log',
            'zippicks_scrape_log'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query(
                "DELETE FROM {$wpdb->prefix}{$table} 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
                 OR timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
        }
    }
    
    /**
     * Format optimization report
     */
    private static function format_optimization_report(array $report): string {
        $message = "ZipPicks Hybrid System Daily Optimization Report\n";
        $message .= "================================================\n\n";
        
        foreach ($report as $item) {
            $message .= sprintf(
                "[%s] %s\n   Action: %s\n\n",
                strtoupper($item['type']),
                $item['message'],
                $item['action']
            );
        }
        
        $message .= "\nView full details: " . admin_url('admin.php?page=zippicks-hybrid-data');
        
        return $message;
    }
    
    /**
     * AJAX handler for refreshing dashboard
     */
    public static function ajax_refresh_dashboard(): void {
        check_ajax_referer('zippicks_hybrid_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $optimizer = self::get_cost_optimizer();
        $metrics = $optimizer->get_dashboard_metrics();
        
        wp_send_json_success($metrics);
    }
    
    /**
     * AJAX handler for cache warming
     */
    public static function ajax_warm_cache(): void {
        check_ajax_referer('zippicks_hybrid_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get cache manager
        $cache_manager = new \ZipPicks_Master_Critic_Cache_Manager();
        $cache_manager->warm_cache();
        
        // Get updated statistics
        $cache_stats = get_option('zippicks_cache_daily_stats', []);
        $today_stats = $cache_stats[date('Y-m-d')] ?? ['hit_rate' => 0];
        
        wp_send_json_success([
            'hit_rate' => $today_stats['hit_rate'],
            'message' => 'Cache warming completed successfully'
        ]);
    }
    
    /**
     * AJAX handler for clearing statistics
     */
    public static function ajax_clear_stats(): void {
        check_ajax_referer('zippicks_hybrid_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Clear all statistics
        delete_option('zippicks_cache_daily_stats');
        delete_option('zippicks_cache_stats_' . date('Y-m-d'));
        
        // Clear database logs
        global $wpdb;
        $tables = [
            'zippicks_query_metrics',
            'zippicks_api_usage_log',
            'zippicks_api_cost_log'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$table}");
        }
        
        wp_send_json_success(['message' => 'Statistics cleared successfully']);
    }
    
    /**
     * AJAX handler for executing recommendations
     */
    public static function ajax_execute_recommendation(): void {
        check_ajax_referer('zippicks_hybrid_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $recommendation = sanitize_text_field($_POST['recommendation'] ?? '');
        
        $success = false;
        $message = '';
        
        switch ($recommendation) {
            case 'increase_cache_ttl':
                update_option('zippicks_cache_ttl_multiplier', 1.5);
                $success = true;
                $message = 'Cache TTL increased by 50%';
                break;
                
            case 'enable_aggressive_caching':
                update_option('zippicks_aggressive_caching', true);
                $success = true;
                $message = 'Aggressive caching enabled';
                break;
                
            case 'reduce_enhancement_threshold':
                update_option('zippicks_hybrid_enhancement_threshold', 85);
                $success = true;
                $message = 'Enhancement threshold reduced to 85%';
                break;
                
            case 'optimize_api_usage':
                update_option('zippicks_optimize_api_calls', true);
                $success = true;
                $message = 'API usage optimization enabled';
                break;
                
            default:
                $message = 'Unknown recommendation';
        }
        
        if ($success) {
            wp_send_json_success(['message' => $message]);
        } else {
            wp_send_json_error(['message' => $message]);
        }
    }
}