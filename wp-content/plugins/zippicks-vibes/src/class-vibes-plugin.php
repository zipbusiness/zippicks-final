<?php
/**
 * Main plugin bootstrap class
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes;

use Exception;

/**
 * Main plugin bootstrap class
 * 
 * @since 2.0.0
 */
final class VibesPlugin {
    
    /**
     * Plugin instance
     * 
     * @var VibesPlugin
     */
    private static ?VibesPlugin $instance = null;
    
    /**
     * Service provider instance
     * 
     * @var ServiceProvider
     */
    private ?ServiceProvider $provider = null;
    
    /**
     * Foundation availability
     * 
     * @var bool
     */
    private bool $has_foundation = false;
    
    /**
     * Get plugin instance
     * 
     * @return VibesPlugin
     */
    public static function get_instance(): VibesPlugin {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->check_dependencies();
        $this->load_autoloader();
        $this->initialize();
        
        // Log successful initialization
        error_log('[ZipPicks Vibes] Plugin class constructed successfully');
    }
    
    /**
     * Check plugin dependencies
     */
    private function check_dependencies(): void {
        // Check if Foundation is available
        $this->has_foundation = function_exists('zippicks');
        
        // PHP version check
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            add_action('admin_notices', [$this, 'php_version_notice']);
            return;
        }
        
        // WordPress version check
        if (version_compare(get_bloginfo('version'), '6.0', '<')) {
            add_action('admin_notices', [$this, 'wp_version_notice']);
            return;
        }
    }
    
    /**
     * Load PSR-4 autoloader
     */
    private function load_autoloader(): void {
        spl_autoload_register(function ($class) {
            // Only autoload our namespace
            $namespace = 'ZipPicksVibes\\';
            if (strpos($class, $namespace) !== 0) {
                return;
            }
            
            // Convert namespace to path
            $relative_class = substr($class, strlen($namespace));
            $file = ZIPPICKS_VIBES_DIR . 'src/' . str_replace('\\', '/', $relative_class) . '.php';
            
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
    
    /**
     * Initialize plugin
     */
    private function initialize(): void {
        // Load text domain
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        
        // Initialize service provider
        add_action('plugins_loaded', [$this, 'init_services'], 5);
        
        // Initialize plugin components
        add_action('init', [$this, 'init'], 0);
        
        // Don't register services again - they're already registered in init_services
        // add_action('init', [$this, 'register_services'], 1);
        
        // Admin initialization
        if (is_admin()) {
            add_action('admin_init', [$this, 'admin_init']);
        }
        
        // Ensure admin is initialized
        add_action('admin_init', [$this, 'ensure_admin_init'], 0);
        
        // Frontend initialization
        if (!is_admin()) {
            add_action('wp', [$this, 'frontend_init']);
        }
        
        // REST API initialization
        add_action('rest_api_init', [$this, 'rest_api_init']);
        
        // Admin action for manual table creation
        add_action('admin_post_zippicks_vibes_create_tables', [$this, 'manual_create_tables']);
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'zippicks-vibes',
            false,
            dirname(ZIPPICKS_VIBES_BASENAME) . '/languages'
        );
    }
    
    /**
     * Initialize services with Foundation
     */
    public function init_services(): void {
        try {
            // Create service provider
            $this->provider = new ServiceProvider();
            
            // Register services
            $this->provider->register();
            
            // Boot services
            $this->provider->boot();
        } catch (\Exception $e) {
            // Log error and continue gracefully
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Vibes: Service initialization error - ' . $e->getMessage());
            }
            
            // Show admin notice
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php printf(__('ZipPicks Vibes: Service initialization failed - %s', 'zippicks-vibes'), esc_html($e->getMessage())); ?></p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * Plugin initialization
     */
    public function init(): void {
        // Check if tables exist, create if missing
        $this->ensure_database_tables();
        
        // Register custom post types if needed
        $this->register_post_types();
        
        // Add rewrite rules
        $this->add_rewrite_rules();
        
        // Initialize security headers
        $this->init_security_headers();
        
        // Add comprehensive security headers
        $this->add_security_headers();
    }
    
    /**
     * Register services with the service provider
     */
    public function register_services(): void {
        // Skip if provider already exists to prevent recursion
        if ($this->provider !== null) {
            return;
        }
        
        if (class_exists('ZipPicksVibes\ServiceProvider')) {
            // Use existing provider instance or create new one
            if (!$this->provider) {
                $this->provider = new ServiceProvider();
                $this->provider->register();
                error_log('[ZipPicks Vibes] Services registered');
            }
        } else {
            error_log('[ZipPicks Vibes] ERROR: ServiceProvider class not found');
        }
    }
    
    /**
     * Ensure admin controller is initialized
     */
    public function ensure_admin_init(): void {
        // Double-check AJAX handlers are registered
        global $wp_filter;
        if (!isset($wp_filter['wp_ajax_zippicks_vibes_save']) && function_exists('zippicks_vibes_force_admin_init')) {
            zippicks_vibes_force_admin_init();
        }
    }
    
    /**
     * Admin initialization
     */
    public function admin_init(): void {
        // Initialize admin controller if available
        if ($this->has_foundation && zippicks()->has('vibes.admin')) {
            try {
                $admin = zippicks()->get('vibes.admin');
                $admin->init();
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('ZipPicks Vibes: Admin init error - ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Frontend initialization
     */
    public function frontend_init(): void {
        // Add security headers for frontend
        $this->add_frontend_security_headers();
        
        // Initialize client-side rendering
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        
        // Add body class for vibes pages
        add_filter('body_class', [$this, 'add_vibes_body_class']);
    }
    
    /**
     * REST API initialization
     */
    public function rest_api_init(): void {
        // Register REST routes if service is available
        if ($this->has_foundation && zippicks()->has('vibes.api')) {
            try {
                $api = zippicks()->get('vibes.api');
                $api->register_routes();
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('ZipPicks Vibes: REST API init error - ' . $e->getMessage());
                }
            }
        } elseif (!$this->has_foundation && $this->provider) {
            // Standalone mode - get API controller from provider
            try {
                $api = $this->provider->get_service('api');
                if ($api) {
                    $api->register_routes();
                }
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('ZipPicks Vibes: Standalone API init error - ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Ensure database tables exist
     */
    private function ensure_database_tables(): void {
        // Include Installer class if not already loaded
        if (!class_exists('ZipPicksVibes\\Database\\Installer')) {
            $installer_file = ZIPPICKS_VIBES_DIR . 'src/Database/Installer.php';
            if (file_exists($installer_file)) {
                require_once $installer_file;
            } else {
                return; // Can't proceed without installer
            }
        }
        
        // Check if tables exist using Installer class
        if (!Database\Installer::tables_exist()) {
            // Attempt to create tables
            try {
                Database\Installer::install();
                
                // Log activation time
                update_option('zippicks_vibes_last_activation', time());
                
                // Verify tables were created successfully
                if (!Database\Installer::tables_exist()) {
                    // Tables still missing, show admin notice
                    add_action('admin_notices', [$this, 'database_notice']);
                    
                    // Log the failure
                    error_log('ZipPicks Vibes: Failed to create database tables during initialization');
                    
                    // Log with Foundation if available
                    if ($this->has_foundation && zippicks()->has('logger')) {
                        $logger = zippicks()->get('logger');
                        $logger->error('Failed to create ZipPicks Vibes database tables', [
                            'plugin' => 'zippicks-vibes',
                            'action' => 'auto-create'
                        ]);
                    }
                } else {
                    // Tables created successfully
                    if ($this->has_foundation && zippicks()->has('logger')) {
                        $logger = zippicks()->get('logger');
                        $logger->info('ZipPicks Vibes database tables created successfully', [
                            'plugin' => 'zippicks-vibes',
                            'action' => 'auto-create'
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Creation failed, show admin notice
                add_action('admin_notices', [$this, 'database_error_notice']);
                
                // Log the error
                error_log('ZipPicks Vibes: Database creation exception - ' . $e->getMessage());
                
                if ($this->has_foundation && zippicks()->has('logger')) {
                    $logger = zippicks()->get('logger');
                    $logger->error('Exception during database table creation', [
                        'plugin' => 'zippicks-vibes',
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        // Register with Foundation database installer if available
        if ($this->has_foundation && function_exists('zippicks') && zippicks()->has('database.installer')) {
            try {
                $installer = zippicks()->get('database.installer');
                $installer->register_schema('vibes', function() {
                    return Database\Installer::get_schema_sql();
                }, ZIPPICKS_VIBES_VERSION);
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('ZipPicks Vibes: Foundation schema registration error - ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Register custom post types
     */
    private function register_post_types(): void {
        // Vibes don't use CPTs, they use custom tables
        // This is here for future expansion if needed
    }
    
    /**
     * Add rewrite rules
     */
    private function add_rewrite_rules(): void {
        // Add vibe archive rewrite rules
        add_rewrite_rule(
            '^vibes/?$',
            'index.php?zippicks_vibes=1',
            'top'
        );
        
        add_rewrite_rule(
            '^vibes/([^/]+)/?$',
            'index.php?zippicks_vibes=1&vibe_slug=$matches[1]',
            'top'
        );
        
        // Add query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'zippicks_vibes';
            $vars[] = 'vibe_slug';
            return $vars;
        });
    }
    
    /**
     * Initialize security headers
     */
    private function init_security_headers(): void {
        // Add robots meta tag
        add_action('wp_head', function() {
            if (is_singular() || is_archive()) {
                $current_url = $_SERVER['REQUEST_URI'] ?? '';
                if (strpos($current_url, '/vibes') !== false) {
                    echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
                }
            }
        }, 1);
    }
    
    /**
     * Add frontend security headers
     */
    private function add_frontend_security_headers(): void {
        // Security headers for vibe pages
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($current_url, '/vibes') !== false || strpos($current_url, 'zippicks/v2/vibes') !== false) {
            // Anti-scraping headers
            header('X-Robots-Tag: noindex, nofollow');
            header('Cache-Control: private, no-cache, no-store, must-revalidate');
            header('X-ZipPicks-Source: frontend-only');
            
            // Security headers
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            // Content Security Policy
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com; " .
                   "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' https://fonts.gstatic.com; " .
                   "connect-src 'self' " . home_url() . "; " .
                   "frame-ancestors 'none'; " .
                   "base-uri 'self'; " .
                   "form-action 'self';";
            
            // header('Content-Security-Policy: ' . $csp); // Temporarily disabled to fix Chrome blank page issue
            
            // Additional anti-scraping headers
            header('X-Permitted-Cross-Domain-Policies: none');
            header('X-Download-Options: noopen');
            
            // Log security headers for monitoring
            if ($this->has_foundation && zippicks()->has('logger')) {
                try {
                    $logger = zippicks()->get('logger');
                    $logger->debug('Security headers applied', [
                        'url' => $current_url,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);
                } catch (\Exception $e) {
                    // Silently fail
                }
            }
        }
    }
    
    /**
     * Add comprehensive security headers
     */
    private function add_security_headers(): void {
        // This method is called on send_headers action
        add_action('send_headers', function() {
            $current_url = $_SERVER['REQUEST_URI'] ?? '';
            
            // Apply to all plugin-related URLs
            if (strpos($current_url, '/vibes') !== false || 
                strpos($current_url, 'zippicks') !== false ||
                is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'zippicks-vibes') !== false) {
                
                // Core security headers
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: DENY');
                header('X-XSS-Protection: 1; mode=block');
                header('Referrer-Policy: strict-origin-when-cross-origin');
                
                // Prevent content type sniffing
                header('X-Permitted-Cross-Domain-Policies: none');
                
                // Feature Policy / Permissions Policy
                header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
                
                // Content Security Policy for admin pages
                if (is_admin()) {
                    $admin_csp = "default-src 'self'; " .
                                 "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
                                 "style-src 'self' 'unsafe-inline'; " .
                                 "img-src 'self' data: https:; " .
                                 "connect-src 'self';";
                    
                    header('Content-Security-Policy: ' . $admin_csp);
                }
            }
        }, 1);
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts(): void {
        // Check if we're on a vibes page using multiple detection methods
        $is_vibes_page = false;
        
        // Primary detection: URL-based (most reliable during wp_enqueue_scripts)
        $request_uri = isset($_SERVER['REQUEST_URI']) 
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) 
            : '';
        
        // Parse URL to get path without query string
        $parsed_url = wp_parse_url($request_uri);
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        
        // Check if path contains /vibes
        if (!empty($path) && (
            strpos($path, '/vibes') !== false || 
            preg_match('#^/vibes(?:/|$)#', $path)
        )) {
            $is_vibes_page = true;
        }
        
        // Secondary detection: Query vars (when available)
        if (!$is_vibes_page && (get_query_var('zippicks_vibes') || get_query_var('vibe_slug'))) {
            $is_vibes_page = true;
        }
        
        // Tertiary detection: Check for vibes-specific query parameters
        if (!$is_vibes_page && !empty($request_uri)) {
            // Check for vibes-related query parameters
            if (strpos($request_uri, 'zippicks_vibes=') !== false || 
                strpos($request_uri, 'vibe_slug=') !== false ||
                strpos($request_uri, 'vibes_category=') !== false) {
                $is_vibes_page = true;
            }
        }
        
        if (!$is_vibes_page) {
            return;
        }
        
        // REMOVED: JavaScript enqueuing for vibes-app.js
        // The archive now uses server-side rendering for SEO optimization
        
        // Check if styles exist before enqueuing
        $styles_path = ZIPPICKS_VIBES_DIR . 'assets/css/vibes-frontend.css';
        if (file_exists($styles_path)) {
            // Enqueue styles
            wp_enqueue_style(
                'zippicks-vibes-frontend',
                ZIPPICKS_VIBES_URL . 'assets/css/vibes-frontend.css',
                [],
                ZIPPICKS_VIBES_VERSION
            );
            
            // Enqueue list cards styles
            wp_enqueue_style(
                'zippicks-vibes-list-cards',
                ZIPPICKS_VIBES_URL . 'assets/css/list-cards.css',
                ['zippicks-vibes-frontend'],
                ZIPPICKS_VIBES_VERSION
            );
        }
        
        // Enqueue autocomplete styles
        $autocomplete_styles_path = ZIPPICKS_VIBES_DIR . 'assets/css/vibes-autocomplete.css';
        if (file_exists($autocomplete_styles_path)) {
            wp_enqueue_style(
                'zippicks-vibes-autocomplete',
                ZIPPICKS_VIBES_URL . 'assets/css/vibes-autocomplete.css',
                ['zippicks-vibes-frontend'],
                ZIPPICKS_VIBES_VERSION
            );
        }
        
        // Enqueue fixes CSS for display issues
        $fixes_path = ZIPPICKS_VIBES_DIR . 'assets/css/vibes-fixes.css';
        if (file_exists($fixes_path)) {
            wp_enqueue_style(
                'zippicks-vibes-fixes',
                ZIPPICKS_VIBES_URL . 'assets/css/vibes-fixes.css',
                ['zippicks-vibes-frontend'],
                ZIPPICKS_VIBES_VERSION
            );
        }
        
        // Enqueue Chrome-specific fixes if needed
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (strpos($user_agent, 'Chrome') !== false) {
            $chrome_fixes_path = ZIPPICKS_VIBES_DIR . 'assets/css/vibes-chrome-fix.css';
            if (file_exists($chrome_fixes_path)) {
                wp_enqueue_style(
                    'zippicks-vibes-chrome-fix',
                    ZIPPICKS_VIBES_URL . 'assets/css/vibes-chrome-fix.css',
                    ['zippicks-vibes-frontend'],
                    ZIPPICKS_VIBES_VERSION
                );
            }
        }
        
        // Enqueue category filter JavaScript for server-rendered archive
        $category_filter_path = ZIPPICKS_VIBES_DIR . 'assets/js/vibes-category-filter.js';
        if (file_exists($category_filter_path)) {
            wp_enqueue_script(
                'zippicks-vibes-category-filter',
                ZIPPICKS_VIBES_URL . 'assets/js/vibes-category-filter.js',
                [],
                ZIPPICKS_VIBES_VERSION,
                true // Load in footer
            );
            
            // Localize script with AJAX data
            wp_localize_script(
                'zippicks-vibes-category-filter',
                'vibesAjax',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('zippicks_vibes_nonce'),
                    'isUserLoggedIn' => is_user_logged_in()
                ]
            );
        }
        
    }
    
    /**
     * Add body class for vibes pages
     * 
     * @param array $classes Body classes
     * @return array Modified body classes
     */
    public function add_vibes_body_class(array $classes): array {
        // Check if we're on a vibes page
        if (get_query_var('zippicks_vibes') || get_query_var('vibe_slug')) {
            $classes[] = 'vibes-archive';
            $classes[] = 'zippicks-vibes-page';
            
            // Add specific vibe slug if available
            $vibe_slug = get_query_var('vibe_slug');
            if ($vibe_slug) {
                $classes[] = 'vibes-single';
                $classes[] = 'vibe-' . sanitize_html_class($vibe_slug);
            }
        }
        
        // Also check URL as fallback
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($current_url, '/vibes') !== false && !in_array('vibes-archive', $classes)) {
            $classes[] = 'vibes-archive';
            $classes[] = 'zippicks-vibes-page';
        }
        
        return $classes;
    }
    
    /**
     * Plugin activation
     */
    public function activate(): void {
        // Ensure we have proper permissions
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Load required WordPress functions
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        try {
            // Create/verify database tables
            Database\Installer::install();
            
            // Log activation time
            update_option('zippicks_vibes_activation_time', time());
            
            // Verify tables were created
            if (!Database\Installer::tables_exist()) {
                // Log error
                error_log('ZipPicks Vibes: Failed to create database tables during activation');
                
                // Try alternative creation method
                global $wpdb;
                $sql = Database\Installer::get_schema_sql();
                dbDelta($sql);
            }
            
            // Flush rewrite rules
            $this->add_rewrite_rules();
            flush_rewrite_rules();
            
            // Set default options
            add_option('zippicks_vibes_version', ZIPPICKS_VIBES_VERSION);
            add_option('zippicks_vibes_installed', time());
            add_option('zippicks_vibes_db_version', '2.0.0');
            
            // Log successful activation
            error_log('ZipPicks Vibes: Plugin activated successfully at ' . date('Y-m-d H:i:s'));
            
        } catch (\Exception $e) {
            error_log('ZipPicks Vibes: Activation error - ' . $e->getMessage());
            // Don't throw exception to avoid breaking activation
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_zippicks_vibes_%'");
        
        // Log deactivation
        error_log('ZipPicks Vibes: Plugin deactivated at ' . date('Y-m-d H:i:s'));
    }
    
    /**
     * PHP version notice
     */
    public function php_version_notice(): void {
        ?>
        <div class="notice notice-error">
            <p><?php _e('ZipPicks Vibes requires PHP 8.0 or higher. Please upgrade your PHP version.', 'zippicks-vibes'); ?></p>
        </div>
        <?php
    }
    
    /**
     * WordPress version notice
     */
    public function wp_version_notice(): void {
        ?>
        <div class="notice notice-error">
            <p><?php _e('ZipPicks Vibes requires WordPress 6.0 or higher. Please upgrade WordPress.', 'zippicks-vibes'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Database notice
     */
    public function database_notice(): void {
        $manual_url = plugins_url('create-tables.php', ZIPPICKS_VIBES_FILE);
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php _e('ZipPicks Vibes: Database tables are missing.', 'zippicks-vibes'); ?></strong>
            </p>
            <p>
                <?php _e('The plugin attempted to create the required database tables but was unsuccessful.', 'zippicks-vibes'); ?>
                <?php _e('You can:', 'zippicks-vibes'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url($manual_url); ?>" class="button button-primary" target="_blank">
                    <?php _e('Create Tables Manually', 'zippicks-vibes'); ?>
                </a>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zippicks_vibes_create_tables'), 'zippicks_vibes_create_tables')); ?>" class="button">
                    <?php _e('Try Auto-Create Again', 'zippicks-vibes'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Database error notice
     */
    public function database_error_notice(): void {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php _e('ZipPicks Vibes: Database creation error.', 'zippicks-vibes'); ?></strong>
            </p>
            <p>
                <?php _e('An error occurred while creating the database tables. Please check your error logs or contact support.', 'zippicks-vibes'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url(plugins_url('create-tables.php', ZIPPICKS_VIBES_FILE)); ?>" class="button button-primary" target="_blank">
                    <?php _e('Create Tables Manually', 'zippicks-vibes'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Handle manual table creation via admin action
     */
    public function manual_create_tables(): void {
        // Check nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'zippicks_vibes_create_tables')) {
            wp_die(__('Security check failed', 'zippicks-vibes'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'zippicks-vibes'));
        }
        
        // Log activation time
        update_option('zippicks_vibes_last_manual_creation', time());
        
        // Attempt to create tables
        try {
            Database\Installer::install();
            
            // Check if successful
            if (Database\Installer::tables_exist()) {
                // Success - redirect with success message
                wp_redirect(add_query_arg([
                    'page' => 'zippicks-vibes',
                    'message' => 'tables_created'
                ], admin_url('admin.php')));
            } else {
                // Failed - redirect with error
                wp_redirect(add_query_arg([
                    'page' => 'zippicks-vibes',
                    'error' => 'tables_failed'
                ], admin_url('admin.php')));
            }
        } catch (\Exception $e) {
            // Error - redirect with error
            error_log('ZipPicks Vibes: Manual table creation error - ' . $e->getMessage());
            wp_redirect(add_query_arg([
                'page' => 'zippicks-vibes',
                'error' => 'tables_error'
            ], admin_url('admin.php')));
        }
        
        exit;
    }
}