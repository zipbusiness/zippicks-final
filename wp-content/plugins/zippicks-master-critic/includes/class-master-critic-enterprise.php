<?php
/**
 * Enterprise-ready core plugin class with PHP 8.2+ compatibility
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

/**
 * Main plugin class with enterprise features
 * 
 * @since 1.0.0
 */
#[\AllowDynamicProperties]
class ZipPicks_Master_Critic_Enterprise {
    
    /**
     * Plugin instance
     *
     * @var ZipPicks_Master_Critic_Enterprise|null
     */
    private static ?ZipPicks_Master_Critic_Enterprise $instance = null;
    
    /**
     * The unique identifier of this plugin
     *
     * @var string
     */
    protected string $plugin_name;
    
    /**
     * The current version of the plugin
     *
     * @var string
     */
    protected string $version;
    
    /**
     * AI Service instance
     *
     * @var ZipPicks_Master_Critic_AI_Service|null
     */
    protected ?ZipPicks_Master_Critic_AI_Service $ai_service = null;
    
    /**
     * Router instance
     *
     * @var ZipPicks_Master_Critic_Router|null
     */
    public ?ZipPicks_Master_Critic_Router $router = null;
    
    /**
     * Post Type instance
     *
     * @var ZipPicks_Master_Critic_Post_Type|null
     */
    public ?ZipPicks_Master_Critic_Post_Type $post_type = null;
    
    /**
     * Logger instance for enterprise logging
     *
     * @var ZipPicks_Master_Critic_Logger|null
     */
    protected ?ZipPicks_Master_Critic_Logger $logger = null;
    
    /**
     * Health check instance
     *
     * @var ZipPicks_Master_Critic_Health_Check|null
     */
    protected ?ZipPicks_Master_Critic_Health_Check $health_check = null;
    
    /**
     * Performance monitor instance
     *
     * @var ZipPicks_Master_Critic_Performance_Monitor|null
     */
    protected ?ZipPicks_Master_Critic_Performance_Monitor $performance_monitor = null;
    
    /**
     * Security handler instance
     *
     * @var ZipPicks_Master_Critic_Security_Handler|null
     */
    protected ?ZipPicks_Master_Critic_Security_Handler $security_handler = null;
    
    /**
     * Cache manager instance
     *
     * @var ZipPicks_Master_Critic_Cache_Manager|null
     */
    protected ?ZipPicks_Master_Critic_Cache_Manager $cache_manager = null;
    
    /**
     * Plugin initialization status
     *
     * @var bool
     */
    protected bool $initialized = false;
    
    /**
     * Plugin boot time for performance tracking
     *
     * @var float
     */
    protected float $boot_time;
    
    /**
     * Error count for monitoring
     *
     * @var int
     */
    protected int $error_count = 0;
    
    /**
     * Get singleton instance
     *
     * @return ZipPicks_Master_Critic_Enterprise
     */
    public static function get_instance(): ZipPicks_Master_Critic_Enterprise {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    protected function __construct() {
        $this->boot_time = microtime(true);
        $this->plugin_name = 'zippicks-master-critic';
        $this->version = defined('ZIPPICKS_MASTER_CRITIC_VERSION') 
            ? ZIPPICKS_MASTER_CRITIC_VERSION 
            : '1.0.0';
        
        $this->initialize();
    }
    
    /**
     * Initialize the plugin
     */
    protected function initialize(): void {
        try {
            // Load dependencies
            $this->load_dependencies();
            
            // Initialize components
            $this->init_logger();
            $this->init_security();
            $this->init_cache();
            $this->init_health_check();
            $this->init_performance_monitor();
            
            // Define hooks
            $this->define_admin_hooks();
            $this->define_schema_hooks();
            $this->define_enterprise_hooks();
            
            // Register with foundation
            $this->register_with_foundation();
            
            $this->initialized = true;
            
            // Log successful initialization
            $this->log('info', 'Plugin initialized successfully', [
                'version' => $this->version,
                'boot_time' => microtime(true) - $this->boot_time
            ]);
            
        } catch (\Exception $e) {
            $this->handle_initialization_error($e);
        }
    }
    
    /**
     * Load required dependencies
     */
    protected function load_dependencies(): void {
        $required_files = [
            'includes/class-database.php',
            'includes/class-installer.php',
            'includes/class-ai-service.php',
            'includes/class-master-critic-router.php',
            'includes/class-master-critic-post-type.php',
            'includes/class-audit-logger.php',
            'admin/class-admin.php',
            'admin/class-settings-page.php',
            'admin/class-generation-page.php'
        ];
        
        foreach ($required_files as $file) {
            $filepath = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            } else {
                throw new \RuntimeException("Required file not found: {$file}");
            }
        }
        
        // Initialize core services
        $this->ai_service = new ZipPicks_Master_Critic_AI_Service();
        $this->router = new ZipPicks_Master_Critic_Router();
        $this->post_type = new ZipPicks_Master_Critic_Post_Type();
        
        // Initialize audit logger
        ZipPicks_Master_Critic_Audit_Logger::init();
    }
    
    /**
     * Initialize enterprise logger
     */
    protected function init_logger(): void {
        $logger_file = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-logger.php';
        if (file_exists($logger_file)) {
            require_once $logger_file;
            $this->logger = new ZipPicks_Master_Critic_Logger();
        }
    }
    
    /**
     * Initialize security handler
     */
    protected function init_security(): void {
        $security_file = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security-handler.php';
        if (file_exists($security_file)) {
            require_once $security_file;
            $this->security_handler = new ZipPicks_Master_Critic_Security_Handler();
        }
    }
    
    /**
     * Initialize cache manager
     */
    protected function init_cache(): void {
        $cache_file = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-cache-manager.php';
        if (file_exists($cache_file)) {
            require_once $cache_file;
            $this->cache_manager = new ZipPicks_Master_Critic_Cache_Manager();
        }
    }
    
    /**
     * Initialize health check
     */
    protected function init_health_check(): void {
        $health_file = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-health-check.php';
        if (file_exists($health_file)) {
            require_once $health_file;
            $this->health_check = new ZipPicks_Master_Critic_Health_Check();
        }
    }
    
    /**
     * Initialize performance monitor
     */
    protected function init_performance_monitor(): void {
        $perf_file = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-performance-monitor.php';
        if (file_exists($perf_file)) {
            require_once $perf_file;
            $this->performance_monitor = new ZipPicks_Master_Critic_Performance_Monitor();
        }
    }
    
    /**
     * Register all hooks related to admin functionality
     */
    protected function define_admin_hooks(): void {
        if (!$this->ai_service) {
            return;
        }
        
        $admin = new ZipPicks_Master_Critic_Admin(
            $this->plugin_name, 
            $this->version, 
            $this->ai_service
        );
        
        add_action('admin_menu', [$admin, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$admin, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$admin, 'enqueue_styles']);
        
        // AJAX handlers with security checks
        $ajax_actions = [
            'zippicks_generate_prompt',
            'zippicks_execute_ai_generation',
            'zippicks_save_prompt_template',
            'zippicks_create_businesses',
            'zippicks_create_list',
            'zippicks_view_generation',
            'zippicks_get_template',
            'zippicks_update_template',
            'zippicks_delete_template',
            'zippicks_test_zipbusiness_api'
        ];
        
        foreach ($ajax_actions as $action) {
            $method = 'ajax_' . str_replace('zippicks_', '', $action);
            add_action("wp_ajax_{$action}", [$admin, $method]);
        }
        
        // Admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('admin_notices', [$this, 'schema_validation_notices']);
        add_action('admin_notices', [$this, 'table_existence_notices']);
        add_action('admin_notices', [$this, 'enterprise_status_notices']);
    }
    
    /**
     * Register all hooks related to schema functionality
     */
    protected function define_schema_hooks(): void {
        $schema_hooks = new ZipPicks_Master_Critic_Schema_Hooks();
        $schema_hooks->init();
    }
    
    /**
     * Define enterprise-specific hooks
     */
    protected function define_enterprise_hooks(): void {
        // Health check endpoint
        add_action('wp_ajax_zippicks_health_check', [$this, 'ajax_health_check']);
        add_action('wp_ajax_nopriv_zippicks_health_check', [$this, 'ajax_health_check']);
        
        // Performance monitoring
        add_action('shutdown', [$this, 'log_performance_metrics']);
        
        // Error tracking
        add_action('wp_error_added', [$this, 'track_wp_error'], 10, 4);
        
        // Cache warming
        add_action('zippicks_warm_cache', [$this, 'warm_cache']);
        
        // Scheduled maintenance
        add_action('zippicks_maintenance', [$this, 'run_maintenance']);
        
        // Schedule events
        if (!wp_next_scheduled('zippicks_maintenance')) {
            wp_schedule_event(time(), 'daily', 'zippicks_maintenance');
        }
    }
    
    /**
     * Register with Foundation if available
     */
    protected function register_with_foundation(): void {
        if (!function_exists('zippicks')) {
            $this->log('warning', 'Foundation not available, running in standalone mode');
            return;
        }
        
        try {
            $foundation = zippicks();
            
            // Register services
            $services = [
                'master_critic.ai' => $this->ai_service,
                'master_critic.logger' => $this->logger,
                'master_critic.cache' => $this->cache_manager,
                'master_critic.security' => $this->security_handler,
                'master_critic.health' => $this->health_check
            ];
            
            foreach ($services as $key => $service) {
                if ($service && method_exists($foundation, 'bind')) {
                    $foundation->bind($key, $service);
                }
            }
            
            // Register database schema
            if (method_exists($foundation, 'has') && $foundation->has('database.installer')) {
                $installer = $foundation->get('database.installer');
                if ($installer && method_exists($installer, 'register_schema')) {
                    $installer->register_schema(
                        'master-critic',
                        function() {
                            return ZipPicks_Master_Critic_Database::get_schema_sql();
                        },
                        $this->version
                    );
                }
            }
            
            $this->log('info', 'Successfully registered with Foundation');
            
        } catch (\Exception $e) {
            $this->log('error', 'Failed to register with Foundation', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle initialization errors
     *
     * @param \Exception $e
     */
    protected function handle_initialization_error(\Exception $e): void {
        $this->error_count++;
        
        // Log the error
        error_log(sprintf(
            '[Master Critic Enterprise] Initialization error: %s in %s on line %d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
        
        // Show admin notice
        add_action('admin_notices', function() use ($e) {
            ?>
            <div class="notice notice-error">
                <p><strong>ZipPicks Master Critic Error:</strong></p>
                <p><?php echo esc_html($e->getMessage()); ?></p>
                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                    <details>
                        <summary>Debug Information</summary>
                        <pre><?php echo esc_html($e->getTraceAsString()); ?></pre>
                    </details>
                <?php endif; ?>
            </div>
            <?php
        });
    }
    
    /**
     * Admin notices
     */
    public function admin_notices(): void {
        // Check for missing API keys
        $anthropic_key = get_option('zippicks_anthropic_api_key');
        $openai_key = get_option('zippicks_openai_api_key');
        
        if (empty($anthropic_key) && empty($openai_key)) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong>ZipPicks Master Critic:</strong> 
                    No AI API keys configured. Please add your Anthropic or OpenAI API key in the 
                    <a href="<?php echo admin_url('admin.php?page=zippicks-master-critic-settings'); ?>">settings</a>.
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Schema validation notices
     */
    public function schema_validation_notices(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $validation_errors = get_transient('zippicks_schema_validation_errors');
        if ($validation_errors && is_array($validation_errors)) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>ZipPicks Schema Validation Issues:</strong></p>
                <ul>
                    <?php foreach ($validation_errors as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }
    }
    
    /**
     * Table existence notices
     */
    public function table_existence_notices(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-installer.php';
        
        if (!ZipPicks_Master_Critic_Installer::tables_exist()) {
            $create_url = wp_nonce_url(
                admin_url('admin.php?page=zippicks-master-critic&action=create_tables'),
                'zippicks_create_tables'
            );
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>ZipPicks Master Critic:</strong> 
                    Required database tables are missing. 
                    <a href="<?php echo esc_url($create_url); ?>" class="button button-primary">
                        Create Tables Now
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Enterprise status notices
     */
    public function enterprise_status_notices(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Show enterprise features status
        if ($this->health_check) {
            $status = $this->health_check->get_status();
            if ($status['status'] !== 'healthy') {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong>ZipPicks Master Critic Health Check:</strong> 
                        Some issues detected. 
                        <a href="<?php echo admin_url('admin.php?page=zippicks-master-critic-health'); ?>">
                            View Details
                        </a>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * AJAX health check endpoint
     */
    public function ajax_health_check(): void {
        // Allow public access for monitoring tools
        if (!$this->health_check) {
            wp_send_json_error('Health check not available');
            return;
        }
        
        $status = $this->health_check->get_status();
        
        // Set appropriate HTTP status code
        if ($status['status'] === 'healthy') {
            wp_send_json_success($status);
        } else {
            status_header(503); // Service Unavailable
            wp_send_json_error($status);
        }
    }
    
    /**
     * Log performance metrics on shutdown
     */
    public function log_performance_metrics(): void {
        if (!$this->performance_monitor) {
            return;
        }
        
        $metrics = [
            'execution_time' => microtime(true) - $this->boot_time,
            'memory_peak' => memory_get_peak_usage(true),
            'queries' => get_num_queries(),
            'error_count' => $this->error_count
        ];
        
        $this->performance_monitor->record_metrics($metrics);
        
        // Log slow requests
        if ($metrics['execution_time'] > 2.0) {
            $this->log('warning', 'Slow request detected', $metrics);
        }
    }
    
    /**
     * Track WordPress errors
     *
     * @param string $code
     * @param string $message
     * @param mixed $data
     * @param WP_Error $error
     */
    public function track_wp_error($code, $message, $data, $error): void {
        if (strpos($code, 'zippicks') !== false) {
            $this->error_count++;
            $this->log('error', 'WordPress error tracked', [
                'code' => $code,
                'message' => $message,
                'data' => $data
            ]);
        }
    }
    
    /**
     * Warm cache
     */
    public function warm_cache(): void {
        if (!$this->cache_manager) {
            return;
        }
        
        $this->cache_manager->warm_cache();
    }
    
    /**
     * Run maintenance tasks
     */
    public function run_maintenance(): void {
        // Clean old logs
        if ($this->logger) {
            $this->logger->clean_old_logs(30); // Keep 30 days
        }
        
        // Optimize tables
        ZipPicks_Master_Critic_Database::optimize_tables();
        
        // Clear expired cache
        if ($this->cache_manager) {
            $this->cache_manager->clear_expired();
        }
        
        $this->log('info', 'Maintenance tasks completed');
    }
    
    /**
     * Log message with context
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    protected function log(string $level, string $message, array $context = []): void {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        } else {
            error_log("[Master Critic {$level}] {$message}");
        }
    }
    
    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version(): string {
        return $this->version;
    }
    
    /**
     * Get plugin name
     *
     * @return string
     */
    public function get_plugin_name(): string {
        return $this->plugin_name;
    }
    
    /**
     * Check if plugin is initialized
     *
     * @return bool
     */
    public function is_initialized(): bool {
        return $this->initialized;
    }
    
    /**
     * Get error count
     *
     * @return int
     */
    public function get_error_count(): int {
        return $this->error_count;
    }
    
    /**
     * Get AI service instance
     *
     * @return ZipPicks_Master_Critic_AI_Service|null
     */
    public function get_ai_service(): ?ZipPicks_Master_Critic_AI_Service {
        return $this->ai_service;
    }
    
    /**
     * Get cache manager instance
     *
     * @return ZipPicks_Master_Critic_Cache_Manager|null
     */
    public function get_cache_manager(): ?ZipPicks_Master_Critic_Cache_Manager {
        return $this->cache_manager;
    }
    
    /**
     * Get logger instance
     *
     * @return ZipPicks_Master_Critic_Logger|null
     */
    public function get_logger(): ?ZipPicks_Master_Critic_Logger {
        return $this->logger;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}