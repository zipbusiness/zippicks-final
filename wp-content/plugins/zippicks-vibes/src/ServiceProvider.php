<?php
/**
 * Service Provider for ZipPicks Vibes
 * 
 * Registers all services with the Foundation container
 * Implements security-first architecture with graceful degradation
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes;

use Exception;
use WP_Site;
use WP_Error;
use ZipPicksVibes\Admin\VibesAdminController;
use ZipPicksVibes\Api\VibesRestController;
use ZipPicksVibes\Api\Middleware\RateLimiter;
use ZipPicksVibes\Api\Middleware\NonceValidator;
use ZipPicksVibes\Repositories\VibeRepository;
use ZipPicksVibes\Services\VibeService;
use ZipPicksVibes\Services\VibeRenderer;
use ZipPicksVibes\Services\ScrapeProtection;
use ZipPicksVibes\Security\RequestValidator;
use ZipPicksVibes\Security\CsrfProtection;
use ZipPicksVibes\Cache\CacheManager;
use ZipPicksVibes\HealthCheck\HealthCheckManager;
use ZipPicksVibes\HealthCheck\Checks\DatabaseCheck;
use ZipPicksVibes\HealthCheck\Checks\CacheCheck;
use ZipPicksVibes\HealthCheck\Checks\SecurityCheck;
use ZipPicksVibes\HealthCheck\Checks\ApiCheck;
use ZipPicksVibes\Audit\AuditLogger;
use ZipPicksVibes\Audit\AuditRepository;

/**
 * Service Provider Class
 */
class ServiceProvider {
    
    /**
     * Foundation availability
     * 
     * @var bool
     */
    private bool $has_foundation = false;
    
    /**
     * Registered services
     * 
     * @var array
     */
    private array $services = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->has_foundation = function_exists('zippicks');
    }
    
    /**
     * Register all services
     */
    public function register(): void {
        // Skip registration in WP-CLI context unless explicitly needed
        if (defined('WP_CLI') && WP_CLI && !$this->is_cli_command_requiring_services()) {
            return;
        }
        
        // Load all required classes first
        $this->loadRequiredClasses();
        
        if (!$this->has_foundation) {
            // Fallback mode without Foundation
            $this->register_standalone();
            return;
        }
        
        // Register with Foundation
        $this->register_with_foundation();
        
        // CRITICAL: Always register admin in admin/AJAX context
        if (is_admin() || wp_doing_ajax()) {
            $this->registerAdminController();
        }
        
        error_log('[ZipPicks Vibes] ServiceProvider::register() completed');
    }
    
    /**
     * Boot services
     */
    public function boot(): void {
        // Skip boot in WP-CLI context unless explicitly needed
        if (defined('WP_CLI') && WP_CLI && !$this->is_cli_command_requiring_services()) {
            return;
        }
        
        try {
            // Initialize hooks for registered services
            $this->init_hooks();
            
            // Start security monitoring
            $this->init_security();
            
            // Initialize health check if available
            $this->init_health_check();
        } catch (\Exception $e) {
            // Log error but don't break plugin initialization
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Service boot error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Check if current WP-CLI command requires services
     * 
     * @return bool
     */
    private function is_cli_command_requiring_services(): bool {
        if (!defined('WP_CLI') || !WP_CLI) {
            return false;
        }
        
        // Get the current command
        $command = $_SERVER['argv'] ?? [];
        $command_string = implode(' ', $command);
        
        // Commands that require services
        $service_commands = [
            'vibes',
            'zippicks',
            'db create',
            'db tables',
            'plugin activate zippicks-vibes',
            'plugin install zippicks-vibes'
        ];
        
        foreach ($service_commands as $cmd) {
            if (strpos($command_string, $cmd) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get service (works for both Foundation and standalone modes)
     * 
     * @param string $service Service name (can be short name or full Foundation key)
     * @return mixed|null
     */
    public function get_service(string $service) {
        if ($this->has_foundation && function_exists('zippicks')) {
            // Map short names to Foundation service names
            $service_map = [
                'cache' => 'vibes.cache',
                'repository' => 'vibes.repository',
                'service' => 'vibes.service',
                'protection' => 'vibes.scrape_protection',
                'renderer' => 'vibes.renderer',
                'rate_limiter' => 'vibes.rate_limiter',
                'nonce_validator' => 'vibes.nonce_validator',
                'request_validator' => 'vibes.request_validator',
                'csrf_protection' => 'vibes.csrf_protection',
                'api' => 'vibes.api',
                'admin' => 'vibes.admin',
                'health_check' => 'vibes.health_check',
                'audit_repository' => 'vibes.audit_repository',
                'audit_logger' => 'vibes.audit_logger'
            ];
            
            // Use mapped name if available, otherwise try direct Foundation key
            $foundation_key = $service_map[$service] ?? 'vibes.' . $service;
            
            if (zippicks()->has($foundation_key)) {
                return zippicks()->get($foundation_key);
            }
        }
        
        // Fall back to standalone services
        return $this->services[$service] ?? null;
    }
    
    /**
     * Register services with Foundation
     */
    private function register_with_foundation(): void {
        try {
            // CRITICAL: Only register if not already registered
            if (!zippicks()->has('vibes.cache')) {
                // Cache Manager service - MUST BE SINGLETON
                zippicks()->singleton('vibes.cache', function() {
                    static $instance = null;
                    if ($instance === null) {
                        $logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
                        $config = [
                            'prefix' => 'zippicks_vibes_',
                            'default_ttl' => defined('ZIPPICKS_VIBES_CACHE_TTL') ? ZIPPICKS_VIBES_CACHE_TTL : 300,
                            'max_connections' => 5, // Add connection limit
                            'connection_timeout' => 2 // Add timeout
                        ];
                        
                        $instance = new CacheManager(null, $config, $logger);
                    }
                    return $instance;
                });
            }
            
            // Repository layer - SINGLETON
            if (!zippicks()->has('vibes.repository')) {
                zippicks()->singleton('vibes.repository', function() {
                    $db = zippicks()->has('database') ? zippicks()->get('database') : null;
                    $cache = zippicks()->get('vibes.cache');
                    $logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
                    
                    return new VibeRepository($db, $cache, $logger);
                });
            }
            
            // Service layer - SINGLETON
            if (!zippicks()->has('vibes.service')) {
                zippicks()->singleton('vibes.service', function() {
                    $repository = zippicks()->get('vibes.repository');
                    $logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
                    $cache = zippicks()->get('vibes.cache');
                    
                    return new VibeService($repository, $logger, $cache);
                });
            }
            
            // Scrape protection service - SINGLETON
            if (!zippicks()->has('vibes.scrape_protection')) {
                zippicks()->singleton('vibes.scrape_protection', function() {
                    $logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
                    $cache = zippicks()->get('vibes.cache');
                    
                    // Create ScrapeProtection if class exists
                    if (class_exists('\\ZipPicksVibes\\Services\\ScrapeProtection')) {
                        return new ScrapeProtection($logger, $cache);
                    }
                    // Return null if class doesn't exist
                    return null;
                });
            }
            
            // Renderer service (client-side rendering) - SINGLETON
            if (!zippicks()->has('vibes.renderer')) {
                zippicks()->singleton('vibes.renderer', function() {
                    $service = zippicks()->get('vibes.service');
                    $protection = zippicks()->has('vibes.scrape_protection') ? zippicks()->get('vibes.scrape_protection') : null;
                    
                    return new VibeRenderer($service, $protection);
                });
            }
            
            // Rate limiter middleware - SINGLETON
            if (!zippicks()->has('vibes.rate_limiter')) {
                zippicks()->singleton('vibes.rate_limiter', function() {
                    $cache = zippicks()->get('vibes.cache');
                    $logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
                    
                    return new RateLimiter($cache, $logger);
                });
            }
            
            // Nonce validator middleware - SINGLETON
            if (!zippicks()->has('vibes.nonce_validator')) {
                zippicks()->singleton('vibes.nonce_validator', function() {
                    return new NonceValidator();
                });
            }
            
            // Request validator (enhanced security) - SINGLETON
            if (!zippicks()->has('vibes.request_validator')) {
                zippicks()->singleton('vibes.request_validator', function() {
                    $logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
                    $cache = zippicks()->get('vibes.cache');
                    
                    return new RequestValidator($logger, $cache);
                });
            }
            
            // CSRF protection service - SINGLETON
            if (!zippicks()->has('vibes.csrf_protection')) {
                zippicks()->singleton('vibes.csrf_protection', function() {
                    $logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
                    $cache = zippicks()->get('vibes.cache');
                    
                    return new CsrfProtection($logger, $cache);
                });
            }
            
            // REST API controller - SINGLETON
            if (!zippicks()->has('vibes.api')) {
                zippicks()->singleton('vibes.api', function() {
                    $service = zippicks()->get('vibes.service');
                    $rateLimiter = zippicks()->get('vibes.rate_limiter');
                    $nonceValidator = zippicks()->get('vibes.nonce_validator');
                    $renderer = zippicks()->get('vibes.renderer');
                    $requestValidator = zippicks()->get('vibes.request_validator');
                    
                    return new VibesRestController($service, $rateLimiter, $nonceValidator, $renderer, $requestValidator);
                });
            }
            
            // Admin controller - SINGLETON
            if (!zippicks()->has('vibes.admin')) {
                zippicks()->singleton('vibes.admin', function() {
                    $service = zippicks()->get('vibes.service');
                    $protection = zippicks()->has('vibes.scrape_protection') ? zippicks()->get('vibes.scrape_protection') : null;
                    
                    return new VibesAdminController($service, $protection);
                });
            }
            
            // Health check manager - SINGLETON
            if (!zippicks()->has('vibes.health_check')) {
                zippicks()->singleton('vibes.health_check', function() {
                    $logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
                    $cache = zippicks()->get('vibes.cache');
                    
                    $manager = new HealthCheckManager($logger, $cache);
                    
                    // Register health checks if classes exist
                    if (class_exists('\\ZipPicksVibes\\HealthCheck\\Checks\\DatabaseCheck')) {
                        $manager->register(new DatabaseCheck());
                    }
                    if (class_exists('\\ZipPicksVibes\\HealthCheck\\Checks\\CacheCheck')) {
                        $manager->register(new CacheCheck($cache));
                    }
                    if (class_exists('\\ZipPicksVibes\\HealthCheck\\Checks\\SecurityCheck')) {
                        $manager->register(new SecurityCheck($logger));
                    }
                    if (class_exists('\\ZipPicksVibes\\HealthCheck\\Checks\\ApiCheck')) {
                        $manager->register(new ApiCheck());
                    }
                    
                    return $manager;
                });
            }
            
            // Audit repository - SINGLETON
            if (!zippicks()->has('vibes.audit_repository')) {
                zippicks()->singleton('vibes.audit_repository', function() {
                    return new AuditRepository();
                });
            }
            
            // Audit logger - SINGLETON
            if (!zippicks()->has('vibes.audit_logger')) {
                zippicks()->singleton('vibes.audit_logger', function() {
                    $logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
                    $cache = zippicks()->get('vibes.cache');
                    $database = zippicks()->has('database') ? zippicks()->get('database') : null;
                    
                    return new AuditLogger($logger, $cache, $database);
                });
            }
        } catch (\Exception $e) {
            // Log error but continue with what we have
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Foundation registration error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Register standalone services (without Foundation)
     */
    private function register_standalone(): void {
        // Log warning about standalone mode
        error_log('ZipPicks Vibes running in fallback standalone mode');
        
        try {
            // Create cache manager first
            $config = [
                'prefix' => 'zippicks_vibes_',
                'default_ttl' => defined('ZIPPICKS_VIBES_CACHE_TTL') ? ZIPPICKS_VIBES_CACHE_TTL : 300
            ];
            $this->services['cache'] = new CacheManager(null, $config, null);
            
            // Create basic service instances
            $this->services['repository'] = new VibeRepository(null, $this->services['cache'], null);
            $this->services['service'] = new VibeService($this->services['repository'], null, $this->services['cache']);
            
            // Create protection service if class exists
            if (class_exists('\\ZipPicksVibes\\Services\\ScrapeProtection')) {
                $this->services['protection'] = new ScrapeProtection(null, $this->services['cache']);
            } else {
                $this->services['protection'] = null;
            }
            $this->services['renderer'] = new VibeRenderer($this->services['service'], $this->services['protection']);
            $this->services['rate_limiter'] = new RateLimiter($this->services['cache'], null);
            $this->services['nonce_validator'] = new NonceValidator();
            $this->services['request_validator'] = new RequestValidator(null, $this->services['cache']);
            $this->services['csrf_protection'] = new CsrfProtection(null, $this->services['cache']);
            
            // API and Admin controllers
            $this->services['api'] = new VibesRestController(
                $this->services['service'],
                $this->services['rate_limiter'],
                $this->services['nonce_validator'],
                $this->services['renderer'],
                $this->services['request_validator']
            );
            
            $this->services['admin'] = new VibesAdminController(
                $this->services['service'],
                $this->services['protection'] ?? null
            );
            
            // Health check manager
            $this->services['health_check'] = new HealthCheckManager(null, $this->services['cache']);
            // Register health checks if classes exist
            if (class_exists('\\ZipPicksVibes\\HealthCheck\\Checks\\DatabaseCheck')) {
                $this->services['health_check']->register(new DatabaseCheck());
            }
            if (class_exists('\\ZipPicksVibes\\HealthCheck\\Checks\\CacheCheck')) {
                $this->services['health_check']->register(new CacheCheck($this->services['cache']));
            }
            if (class_exists('\\ZipPicksVibes\\HealthCheck\\Checks\\SecurityCheck')) {
                $this->services['health_check']->register(new SecurityCheck(null));
            }
            if (class_exists('\\ZipPicksVibes\\HealthCheck\\Checks\\ApiCheck')) {
                $this->services['health_check']->register(new ApiCheck());
            }
            
            // Audit repository and logger
            $this->services['audit_repository'] = new AuditRepository();
            $this->services['audit_logger'] = new AuditLogger(null, $this->services['cache'], null);
        } catch (\Exception $e) {
            // Log error but continue with minimal functionality
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Standalone registration error - ' . $e->getMessage());
            }
            
            // Ensure we have at least a basic cache
            if (!isset($this->services['cache'])) {
                $config = [
                    'prefix' => 'zippicks_vibes_',
                    'default_ttl' => 300
                ];
                $this->services['cache'] = new CacheManager(null, $config, null);
            }
        }
    }
    
    /**
     * Initialize hooks for services
     */
    private function init_hooks(): void {
        // Admin hooks
        if (is_admin()) {
            try {
                // Note: admin menu is registered by the admin controller itself in its init() method
                // So we don't need to add the action here to prevent duplicate menus
                add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
                add_action('wp_ajax_zippicks_vibes_save', [$this, 'handle_ajax_save']);
                add_action('wp_ajax_zippicks_vibes_delete', [$this, 'handle_ajax_delete']);
                add_action('wp_ajax_zippicks_vibes_reorder', [$this, 'handle_ajax_reorder']);
                
                // Multisite hooks
                if (is_multisite()) {
                    add_action('network_admin_menu', [$this, 'register_network_admin_menu']);
                    
                    // Hook for new site creation
                    add_action('wp_initialize_site', [$this, 'new_site_initialization'], 10, 2);
                    
                    // Hook for site deletion
                    add_action('wp_delete_site', [$this, 'site_deletion_cleanup']);
                }
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('ZipPicks Vibes: Admin hooks error - ' . $e->getMessage());
                }
            }
        }
        
        // Frontend hooks
        if (!is_admin()) {
            try {
                // DISABLED: Conflicts with TemplateLoaderFix causing duplicates
                // add_action('template_redirect', [$this, 'handle_vibe_templates']);
                add_filter('the_content', [$this, 'filter_vibe_content'], 999);
                add_action('wp_footer', [$this, 'inject_watermarks']);
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('ZipPicks Vibes: Frontend hooks error - ' . $e->getMessage());
                }
            }
        }
        
        // Global hooks
        try {
            add_action('init', [$this, 'register_shortcodes']);
            add_filter('robots_txt', [$this, 'modify_robots_txt'], 10, 2);
            
            // Multisite global hooks
            if (is_multisite()) {
                add_filter('site_option_active_sitewide_plugins', [$this, 'filter_network_plugins']);
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Global hooks error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Initialize security monitoring
     */
    private function init_security(): void {
        try {
            // Monitor scraping attempts
            add_action('template_redirect', [$this, 'monitor_scraping'], 1);
            
            // Add security headers
            add_action('send_headers', [$this, 'add_security_headers']);
            
            // Log suspicious activity
            add_action('wp_ajax_nopriv_zippicks_vibes_save', [$this, 'log_unauthorized_access']);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Security init error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Initialize health check system
     */
    private function init_health_check(): void {
        try {
            $health_check = $this->get_service('health_check');
            if ($health_check) {
                // Schedule periodic health checks
                if (!wp_next_scheduled('zippicks_vibes_health_check')) {
                    wp_schedule_event(time(), 'hourly', 'zippicks_vibes_health_check');
                }
                
                add_action('zippicks_vibes_health_check', [$health_check, 'run_all_checks']);
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Health check init error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Register admin menu
     */
    public function register_admin_menu(): void {
        try {
            $admin = $this->get_service('admin');
            if ($admin) {
                $admin->register_menu();
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Admin menu registration error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Register network admin menu for multisite
     */
    public function register_network_admin_menu(): void {
        try {
            $admin = $this->get_service('admin');
            if ($admin && method_exists($admin, 'register_network_menu')) {
                $admin->register_network_menu();
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Network admin menu registration error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle new site initialization in multisite
     * 
     * @param \WP_Site $new_site New site object
     * @param array $args Site creation arguments
     */
    public function new_site_initialization(\WP_Site $new_site, array $args): void {
        try {
            // Switch to new site
            switch_to_blog($new_site->blog_id);
            
            // Create tables for new site
            if (class_exists('ZipPicksVibes\\Database\\Installer')) {
                \ZipPicksVibes\Database\Installer::install();
            }
            
            // Restore to original site
            restore_current_blog();
            
            // Log successful initialization
            if ($this->has_foundation && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->info('Vibes tables created for new site', [
                    'site_id' => $new_site->blog_id,
                    'domain' => $new_site->domain
                ]);
            }
        } catch (\Exception $e) {
            restore_current_blog();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: New site initialization error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle site deletion cleanup
     * 
     * @param \WP_Site $old_site Site being deleted
     */
    public function site_deletion_cleanup(\WP_Site $old_site): void {
        try {
            // Log deletion
            if ($this->has_foundation && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->info('Vibes data cleanup for deleted site', [
                    'site_id' => $old_site->blog_id,
                    'domain' => $old_site->domain
                ]);
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Site deletion cleanup error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Filter network plugins for multisite compatibility
     * 
     * @param array $plugins Network active plugins
     * @return array
     */
    public function filter_network_plugins(array $plugins): array {
        // Add any necessary filtering for network plugins
        return $plugins;
    }
    
    /**
     * Load all required classes
     */
    private function loadRequiredClasses(): void {
        $base_dir = ZIPPICKS_VIBES_DIR . 'src/';
        
        $required_files = [
            // Interfaces
            'Repositories/VibeRepositoryInterface.php',
            
            // Models
            'Models/Vibe.php',
            'Models/PaginatedResult.php',
            
            // Exceptions
            'Exceptions/VibeNotFoundException.php',
            'Exceptions/InvalidVibeDataException.php',
            
            // Core classes
            'Repositories/VibeRepository.php',
            'Services/VibeService.php',
            'Admin/VibesAdminController.php'
        ];
        
        foreach ($required_files as $file) {
            $path = $base_dir . $file;
            if (file_exists($path)) {
                require_once $path;
            } else {
                error_log('[ZipPicks Vibes] Missing required file: ' . $file);
            }
        }
    }
    
    /**
     * Register admin controller
     */
    private function registerAdminController(): void {
        try {
            // Get or create service
            $service = null;
            if (function_exists('zippicks') && zippicks()->has('vibes.service')) {
                $service = zippicks()->get('vibes.service');
            } else {
                // Use service from standalone registration if available
                $service = $this->services['service'] ?? null;
                
                // If still no service, create with proper dependencies
                if (!$service) {
                    $cache = $this->services['cache'] ?? null;
                    if (!$cache) {
                        $config = [
                            'prefix' => 'zippicks_vibes_',
                            'default_ttl' => defined('ZIPPICKS_VIBES_CACHE_TTL') ? ZIPPICKS_VIBES_CACHE_TTL : 300
                        ];
                        $cache = new CacheManager(null, $config, null);
                    }
                    
                    $repository = new VibeRepository(null, $cache, null);
                    $service = new VibeService($repository, null, $cache);
                }
            }
            
            // Create and initialize admin controller
            $admin = new VibesAdminController($service, null);
            $admin->init();
            
            error_log('[ZipPicks Vibes] Admin controller registered successfully');
            
        } catch (\Exception $e) {
            error_log('[ZipPicks Vibes] Failed to register admin controller: ' . $e->getMessage());
            
            // Try direct registration as last resort
            $this->directAjaxRegistration();
        }
    }
    
    /**
     * Direct AJAX registration without dependencies
     */
    private function directAjaxRegistration(): void {
        // Direct AJAX registration without dependencies
        $actions = [
            'zippicks_vibes_save',
            'zippicks_vibes_delete', 
            'zippicks_vibes_get',
            'zippicks_vibes_save_category'
        ];
        
        foreach ($actions as $action) {
            add_action('wp_ajax_' . $action, function() use ($action) {
                // Return error for now - this ensures AJAX doesn't fail silently
                wp_send_json_error([
                    'message' => 'Service initialization failed. Please check error logs.',
                    'action' => $action
                ], 500);
            });
        }
        
        error_log('[ZipPicks Vibes] Direct AJAX registration completed (error mode)');
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, 'zippicks-vibes') === false) {
            return;
        }
        
        try {
            // Copy assets from v1 plugin
            $v1_path = str_replace('/wp-content/', '/zippicks-vibes/', ZIPPICKS_VIBES_DIR);
            
            wp_enqueue_style(
                'zippicks-vibes-admin',
                ZIPPICKS_VIBES_URL . 'src/Admin/Assets/css/vibes-admin.css',
                [],
                ZIPPICKS_VIBES_VERSION
            );
            
            wp_enqueue_script(
                'zippicks-vibes-admin',
                ZIPPICKS_VIBES_URL . 'src/Admin/Assets/js/vibes-admin.js',
                ['jquery', 'jquery-ui-sortable'],
                ZIPPICKS_VIBES_VERSION,
                true
            );
            
            // Localize with security nonce
            wp_localize_script('zippicks-vibes-admin', 'zippicksVibesAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('zippicks_vibes_admin'),
                'strings' => [
                    'confirmDelete' => __('Are you sure you want to delete this vibe?', 'zippicks-vibes'),
                    'saving' => __('Saving...', 'zippicks-vibes'),
                    'saved' => __('Saved!', 'zippicks-vibes'),
                    'error' => __('An error occurred. Please try again.', 'zippicks-vibes')
                ],
                'isMultisite' => is_multisite(),
                'isNetworkAdmin' => is_network_admin()
            ]);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Asset enqueue error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle AJAX save request
     */
    public function handle_ajax_save(): void {
        try {
            // Enhanced security validation
            $request_validator = $this->get_service('request_validator');
            if ($request_validator) {
                $validation = $request_validator->validate($_REQUEST);
                if (is_wp_error($validation)) {
                    wp_send_json_error($validation->get_error_message(), $validation->get_error_data()['status'] ?? 403);
                    return;
                }
            }
            
            // CSRF protection
            $csrf = $this->get_service('csrf_protection');
            if ($csrf) {
                $token = $_REQUEST['zippicks_csrf_token'] ?? '';
                if (!$csrf->validateToken($token, 'save_vibe')) {
                    wp_send_json_error(__('Invalid security token. Please refresh and try again.', 'zippicks-vibes'), 403);
                    return;
                }
            }
            
            // Legacy protection check
            $protection = $this->get_service('protection');
            if ($protection && method_exists($protection, 'validateRequest') && !$protection->validateRequest()) {
                wp_die(__('Security check failed', 'zippicks-vibes'));
            }
            
            $admin = $this->get_service('admin');
            if ($admin) {
                $admin->ajax_save_vibe();
            }
        } catch (\Exception $e) {
            wp_send_json_error(__('An error occurred processing your request.', 'zippicks-vibes'), 500);
        }
    }
    
    /**
     * Handle AJAX delete request
     */
    public function handle_ajax_delete(): void {
        try {
            // Enhanced security validation
            $request_validator = $this->get_service('request_validator');
            if ($request_validator) {
                $validation = $request_validator->validate($_REQUEST);
                if (is_wp_error($validation)) {
                    wp_send_json_error($validation->get_error_message(), $validation->get_error_data()['status'] ?? 403);
                    return;
                }
            }
            
            // CSRF protection
            $csrf = $this->get_service('csrf_protection');
            if ($csrf) {
                $token = $_REQUEST['zippicks_csrf_token'] ?? '';
                if (!$csrf->validateToken($token, 'delete_vibe')) {
                    wp_send_json_error(__('Invalid security token. Please refresh and try again.', 'zippicks-vibes'), 403);
                    return;
                }
            }
            
            // Legacy protection check
            $protection = $this->get_service('protection');
            if ($protection && method_exists($protection, 'validateRequest') && !$protection->validateRequest()) {
                wp_die(__('Security check failed', 'zippicks-vibes'));
            }
            
            $admin = $this->get_service('admin');
            if ($admin) {
                $admin->ajax_delete_vibe();
            }
        } catch (\Exception $e) {
            wp_send_json_error(__('An error occurred processing your request.', 'zippicks-vibes'), 500);
        }
    }
    
    /**
     * Handle AJAX reorder request
     */
    public function handle_ajax_reorder(): void {
        try {
            // Enhanced security validation
            $request_validator = $this->get_service('request_validator');
            if ($request_validator) {
                $validation = $request_validator->validate($_REQUEST);
                if (is_wp_error($validation)) {
                    wp_send_json_error($validation->get_error_message(), $validation->get_error_data()['status'] ?? 403);
                    return;
                }
            }
            
            // CSRF protection
            $csrf = $this->get_service('csrf_protection');
            if ($csrf) {
                $token = $_REQUEST['zippicks_csrf_token'] ?? '';
                if (!$csrf->validateToken($token, 'reorder_vibes')) {
                    wp_send_json_error(__('Invalid security token. Please refresh and try again.', 'zippicks-vibes'), 403);
                    return;
                }
            }
            
            // Legacy protection check
            $protection = $this->get_service('protection');
            if ($protection && method_exists($protection, 'validateRequest') && !$protection->validateRequest()) {
                wp_die(__('Security check failed', 'zippicks-vibes'));
            }
            
            $admin = $this->get_service('admin');
            if ($admin) {
                $admin->ajax_reorder_vibes();
            }
        } catch (\Exception $e) {
            wp_send_json_error(__('An error occurred processing your request.', 'zippicks-vibes'), 500);
        }
    }
    
    /**
     * Handle vibe template routing
     */
    public function handle_vibe_templates(): void {
        try {
            $vibe_slug = get_query_var('vibe_slug');
            $is_vibes_archive = get_query_var('zippicks_vibes');
            
            if ($is_vibes_archive || $vibe_slug) {
                // Check session requirement
                if (ZIPPICKS_VIBES_SESSION_REQUIRED && !is_user_logged_in()) {
                    // Show limited preview
                    $renderer = $this->get_service('renderer');
                    if ($renderer && method_exists($renderer, 'render_preview')) {
                        $renderer->render_preview();
                        exit;
                    }
                }
                
                // Load appropriate template
                if ($vibe_slug) {
                    $this->load_vibe_single_template($vibe_slug);
                } else {
                    $this->load_vibe_archive_template();
                }
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Template handling error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Filter vibe content for security
     */
    public function filter_vibe_content(string $content): string {
        try {
            $current_url = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($current_url, '/vibes') === false) {
                return $content;
            }
            
            $renderer = $this->get_service('renderer');
            if ($renderer) {
                return $renderer->secure_content($content);
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Content filter error - ' . $e->getMessage());
            }
        }
        
        return $content;
    }
    
    /**
     * Inject watermarks into content
     */
    public function inject_watermarks(): void {
        try {
            $current_url = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($current_url, '/vibes') === false) {
                return;
            }
            
            $protection = $this->get_service('protection');
            if ($protection) {
                $watermark_content = $protection->generate_watermarks();
                // Wrap all watermarks in a hidden span with hardened CSS
                echo '<span class="zp-watermark" style="display:none !important;">' . $watermark_content . '</span>';
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Watermark injection error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes(): void {
        add_shortcode('zippicks_vibes', [$this, 'render_vibes_shortcode']);
        add_shortcode('zippicks_vibe_search', [$this, 'render_vibe_search_shortcode']);
    }
    
    /**
     * Modify robots.txt
     */
    public function modify_robots_txt(string $output, string $public): string {
        $output .= "\n# ZipPicks Vibes Protection\n";
        $output .= "User-agent: *\n";
        $output .= "Disallow: /vibes/\n";
        $output .= "Disallow: /wp-json/zippicks/v2/vibes/\n";
        $output .= "Disallow: *?zippicks_vibes=*\n";
        
        return $output;
    }
    
    /**
     * Monitor scraping attempts
     */
    public function monitor_scraping(): void {
        try {
            $protection = $this->get_service('protection');
            if ($protection) {
                $protection->monitor_request();
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Scraping monitor error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers(): void {
        try {
            $current_url = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($current_url, '/vibes') !== false || strpos($current_url, 'zippicks/v2/vibes') !== false) {
                header('X-Robots-Tag: noindex, nofollow');
                header('Cache-Control: private, no-cache, no-store, must-revalidate');
                header('X-ZipPicks-Source: frontend-only');
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: DENY');
                header('Referrer-Policy: no-referrer');
            }
        } catch (\Exception $e) {
            // Silently fail - headers may already be sent
        }
    }
    
    /**
     * Log unauthorized access attempts
     */
    public function log_unauthorized_access(): void {
        try {
            $protection = $this->get_service('protection');
            if ($protection) {
                $protection->log_unauthorized_attempt();
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Unauthorized access logging error - ' . $e->getMessage());
            }
        }
        
        wp_die(__('Unauthorized access', 'zippicks-vibes'), 403);
    }
    
    
    /**
     * Load vibe single template
     */
    private function load_vibe_single_template(string $slug): void {
        $template = ZIPPICKS_VIBES_DIR . 'templates/client-render/vibe-single.php';
        if (file_exists($template)) {
            include $template;
            exit;
        }
    }
    
    /**
     * Load vibe archive template
     */
    private function load_vibe_archive_template(): void {
        $template = ZIPPICKS_VIBES_DIR . 'templates/client-render/vibe-archive.php';
        if (file_exists($template)) {
            include $template;
            exit;
        }
    }
    
    /**
     * Render vibes shortcode
     */
    public function render_vibes_shortcode(array $atts): string {
        try {
            $atts = shortcode_atts([
                'category' => '',
                'limit' => 10,
                'columns' => 3
            ], $atts);
            
            $renderer = $this->get_service('renderer');
            if ($renderer) {
                return $renderer->render_vibes_grid($atts);
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Vibes shortcode error - ' . $e->getMessage());
            }
        }
        
        return '';
    }
    
    /**
     * Render vibe search shortcode
     */
    public function render_vibe_search_shortcode(array $atts): string {
        try {
            $atts = shortcode_atts([
                'placeholder' => __('Search vibes...', 'zippicks-vibes'),
                'ajax' => true
            ], $atts);
            
            $renderer = $this->get_service('renderer');
            if ($renderer) {
                return $renderer->render_search_form($atts);
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Search shortcode error - ' . $e->getMessage());
            }
        }
        
        return '';
    }
}