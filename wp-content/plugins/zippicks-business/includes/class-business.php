<?php
/**
 * The core plugin class.
 *
 * This is used to define admin-specific hooks, public-facing site hooks,
 * and register services with the Foundation.
 */
class ZipPicks_Business {
    
    /**
     * The unique identifier of this plugin.
     */
    protected $plugin_name;
    
    /**
     * The current version of the plugin.
     */
    protected $version;
    
    /**
     * Plugin instances
     */
    protected $post_types;
    protected $business_manager;
    protected $admin;
    protected $api_metabox;
    protected $rest_controller;
    protected $template_loader;
    
    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        $this->plugin_name = 'zippicks-business';
        $this->version = ZIPPICKS_BUSINESS_VERSION;
        
        $this->load_dependencies();
        $this->register_services();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    
    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // Core classes
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-database.php';
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-installer.php';
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-post-types.php';
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-business-manager.php';
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'includes/class-template-loader.php';
        
        // Admin classes (only load in admin)
        if (is_admin()) {
            require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'admin/class-admin.php';
            require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'admin/class-api-metabox.php';
        }
        
        // REST API controller
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'api/class-rest-controller.php';
        
        // Initialize instances
        $this->post_types = new ZipPicks_Business_Post_Types();
        $this->business_manager = new ZipPicks_Business_Manager();
        
        // Initialize API metabox in admin
        if (is_admin() && class_exists('ZipPicks_Business_API_Metabox')) {
            $this->api_metabox = new ZipPicks_Business_API_Metabox();
        }
        
        // Initialize REST API controller
        $this->rest_controller = new ZipPicks_Business_REST_Controller();
        
        // Initialize template loader
        $this->template_loader = new ZipPicks_Business_Template_Loader();
    }
    
    /**
     * Register services with Foundation (graceful degradation)
     */
    private function register_services() {
        // Register with Foundation if available - follows CLAUDE.md pattern
        if (function_exists('zippicks') && is_callable('zippicks')) {
            try {
                $foundation = zippicks();
                
                // Core services - check before binding
                if (method_exists($foundation, 'bind')) {
                    $foundation->bind('business.manager', $this->business_manager);
                    $foundation->bind('business.version', $this->version);
                    $foundation->bind('business.path', ZIPPICKS_BUSINESS_PLUGIN_DIR);
                }
                
                // Register database schema if Foundation supports it
                if (method_exists($foundation, 'has') && $foundation->has('database.installer')) {
                    $installer = $foundation->get('database.installer');
                    if (method_exists($installer, 'register_schema')) {
                        $installer->register_schema('zippicks-business', function() {
                            return ZipPicks_Business_Database::get_schema_sql();
                        }, $this->version);
                    }
                }
                
                // Log registration if logger available
                if (method_exists($foundation, 'has') && $foundation->has('logger')) {
                    $logger = $foundation->get('logger');
                    if (method_exists($logger, 'info')) {
                        $logger->info('ZipPicks Business services registered', array(
                            'version' => $this->version,
                            'foundation' => 'available'
                        ));
                    }
                }
                
            } catch (Exception $e) {
                // Graceful failure - continue without Foundation
                error_log('ZipPicks Business: Foundation registration failed (continuing without) - ' . $e->getMessage());
            }
        } else {
            // Foundation not available - log for debugging
            error_log('ZipPicks Business: Foundation not available - running in standalone mode');
        }
    }
    
    /**
     * Register all of the hooks related to the admin area functionality.
     */
    private function define_admin_hooks() {
        if (!is_admin()) {
            return;
        }
        
        // Initialize admin only if class exists
        if (class_exists('ZipPicks_Business_Admin')) {
            $this->admin = new ZipPicks_Business_Admin($this->plugin_name, $this->version);
        } else {
            return; // Skip admin hooks if admin class not loaded
        }
        
        // Admin menu
        if (method_exists($this->admin, 'add_admin_menu')) {
            add_action('admin_menu', array($this->admin, 'add_admin_menu'));
        }
        
        // Admin scripts and styles
        if (method_exists($this->admin, 'enqueue_styles')) {
            add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_styles'));
        }
        if (method_exists($this->admin, 'enqueue_scripts')) {
            add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_scripts'));
        }
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // AJAX handlers
        if (method_exists($this->admin, 'handle_quick_edit')) {
            add_action('wp_ajax_zippicks_business_quick_edit', array($this->admin, 'handle_quick_edit'));
        }
        if (method_exists($this->admin, 'handle_verify_business')) {
            add_action('wp_ajax_zippicks_verify_business', array($this->admin, 'handle_verify_business'));
        }
        if (method_exists($this->admin, 'handle_change_tier')) {
            add_action('wp_ajax_zippicks_change_business_tier', array($this->admin, 'handle_change_tier'));
        }
        
        // Manual table creation
        add_action('admin_action_zippicks_business_create_tables', array($this, 'manual_create_tables'));
    }
    
    /**
     * Register all of the hooks related to the public-facing functionality.
     */
    private function define_public_hooks() {
        // Track business views
        add_action('template_redirect', array($this, 'track_business_view'));
        
        // Add schema markup
        add_action('wp_head', array($this, 'add_schema_markup'));
        
        // Anti-scraping measures
        add_action('init', array($this, 'implement_anti_scraping'));
    }
    
    /**
     * Run the plugin
     */
    public function run() {
        // Initialize components
        add_action('init', array($this, 'init'), 0);
        
        // Auto-create tables if missing
        add_action('init', array($this, 'check_tables'), 5);
        
        // Register REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Schedule cron jobs
        add_action('zippicks_business_daily_analytics', array($this, 'run_daily_analytics'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('zippicks-business', false, dirname(plugin_basename(ZIPPICKS_BUSINESS_PLUGIN_DIR)) . '/languages');
        
        // Register post types and taxonomies
        $this->post_types->register();
        
        // Flush rewrite rules if needed
        if (get_option('zippicks_business_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('zippicks_business_flush_rewrite_rules');
        }
    }
    
    /**
     * Check and create tables if missing
     */
    public function check_tables() {
        if (!ZipPicks_Business_Installer::tables_exist()) {
            ZipPicks_Business_Installer::install();
            
            // Show admin notice if tables still don't exist
            if (!ZipPicks_Business_Installer::tables_exist()) {
                set_transient('zippicks_business_table_error', true, 60);
            }
        }
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Table creation error
        if (get_transient('zippicks_business_table_error')) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('ZipPicks Business:', 'zippicks-business'); ?></strong>
                    <?php _e('Database tables could not be created automatically.', 'zippicks-business'); ?>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?action=zippicks_business_create_tables'), 'create_tables'); ?>" 
                       class="button button-primary" style="margin-left: 10px;">
                        <?php _e('Create Tables Now', 'zippicks-business'); ?>
                    </a>
                </p>
            </div>
            <?php
            delete_transient('zippicks_business_table_error');
        }
        
        // Success message after manual table creation
        if (isset($_GET['tables_created']) && $_GET['tables_created'] === '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('ZipPicks Business:', 'zippicks-business'); ?></strong>
                    <?php _e('Database tables created successfully!', 'zippicks-business'); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Manual table creation handler
     */
    public function manual_create_tables() {
        // Check nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'create_tables')) {
            wp_die(__('Security check failed', 'zippicks-business'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'zippicks-business'));
        }
        
        // Create tables
        ZipPicks_Business_Database::create_tables_direct();
        
        // Redirect with success message
        wp_redirect(add_query_arg('tables_created', '1', admin_url('admin.php?page=zippicks-business')));
        exit;
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Register API controller routes
        if ($this->rest_controller) {
            $this->rest_controller->register_routes();
        }
        
        // Business endpoints
        register_rest_route('zippicks/v1', '/businesses', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_businesses'),
            'permission_callback' => '__return_true',
            'args' => array(
                'per_page' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ),
                'page' => array(
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ),
                'tier' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'verified' => array(
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ),
            ),
        ));
        
        // Create businesses endpoint (for Master Critic integration)
        register_rest_route('zippicks/v1', '/businesses/bulk-create', array(
            'methods' => 'POST',
            'callback' => array($this, 'bulk_create_businesses'),
            'permission_callback' => array($this, 'can_create_businesses'),
            'args' => array(
                'businesses' => array(
                    'required' => true,
                    'type' => 'array',
                ),
                'context' => array(
                    'type' => 'object',
                ),
            ),
        ));
        
        // Analytics endpoint
        register_rest_route('zippicks/v1', '/businesses/(?P<id>\d+)/track', array(
            'methods' => 'POST',
            'callback' => array($this, 'track_business_event'),
            'permission_callback' => '__return_true',
            'args' => array(
                'event_type' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'event_value' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }
    
    /**
     * REST API: Get businesses
     */
    public function get_businesses($request) {
        try {
            // Validate parameters
            $per_page = min(100, max(1, $request->get_param('per_page')));
            $page = max(1, $request->get_param('page'));
            
            $args = array(
                'post_type' => 'zippicks_business',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'post_status' => 'publish',
            );
            
            // Filter by tier
            if ($tier = $request->get_param('tier')) {
                $valid_tiers = array('basic', 'featured', 'premium');
                if (in_array($tier, $valid_tiers)) {
                    $args['meta_query'][] = array(
                        'key' => '_zp_listing_tier',
                        'value' => $tier,
                    );
                } else {
                    return new WP_Error(
                        'invalid_tier',
                        __('Invalid tier specified', 'zippicks-business'),
                        array('status' => 400)
                    );
                }
            }
            
            // Filter by verification
            if ($request->get_param('verified') !== null) {
                $args['meta_query'][] = array(
                    'key' => '_zp_verified',
                    'value' => $request->get_param('verified') ? '1' : '0',
                );
            }
            
            $query = new WP_Query($args);
            $businesses = array();
            
            foreach ($query->posts as $post) {
                try {
                    $businesses[] = $this->prepare_business_for_response($post);
                } catch (Exception $e) {
                    // Log individual business preparation errors
                    if (ZIPPICKS_BUSINESS_DEBUG) {
                        error_log('Failed to prepare business ' . $post->ID . ': ' . $e->getMessage());
                    }
                    // Continue with other businesses
                }
            }
            
            return new WP_REST_Response(array(
                'businesses' => $businesses,
                'total' => $query->found_posts,
                'pages' => $query->max_num_pages,
            ), 200);
            
        } catch (Exception $e) {
            // Log error
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->error('REST API get_businesses error', array(
                    'error' => $e->getMessage(),
                    'params' => $request->get_params()
                ));
            }
            
            return new WP_Error(
                'server_error',
                __('An error occurred while retrieving businesses', 'zippicks-business'),
                array('status' => 500)
            );
        }
    }
    
    /**
     * REST API: Bulk create businesses
     */
    public function bulk_create_businesses($request) {
        $businesses = $request->get_param('businesses');
        $context = $request->get_param('context') ?: array();
        
        // Add API source to context
        $context['source'] = 'rest_api';
        $context['user_id'] = get_current_user_id();
        
        // Use business manager to create businesses
        $result = $this->business_manager->bulk_create_from_ai($businesses, $context);
        
        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }
    
    /**
     * REST API: Track business event
     */
    public function track_business_event($request) {
        $business_id = $request->get_param('id');
        $event_type = $request->get_param('event_type');
        $event_value = $request->get_param('event_value');
        
        // Verify business exists
        if (get_post_type($business_id) !== 'zippicks_business') {
            return new WP_Error('invalid_business', 'Invalid business ID', array('status' => 404));
        }
        
        // Track event
        $result = ZipPicks_Business_Database::track_event($business_id, $event_type, $event_value);
        
        return new WP_REST_Response(array('success' => $result), $result ? 200 : 500);
    }
    
    /**
     * Prepare business post for REST response
     */
    private function prepare_business_for_response($post) {
        $business_data = array(
            'id' => $post->ID,
            'name' => $post->post_title,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'created_at' => $post->post_date,
            'modified_at' => $post->post_modified,
        );
        
        // Add meta fields
        $meta_fields = array(
            'address' => '_zp_address',
            'city' => '_zp_city',
            'state' => '_zp_state',
            'zip' => '_zp_zip',
            'phone' => '_zp_phone',
            'website' => '_zp_website',
            'business_type' => '_zp_business_type',
            'listing_tier' => '_zp_listing_tier',
            'verified' => '_zp_verified',
            'featured' => '_zp_featured',
            'latitude' => '_zp_latitude',
            'longitude' => '_zp_longitude'
        );
        
        foreach ($meta_fields as $key => $meta_key) {
            $business_data[$key] = get_post_meta($post->ID, $meta_key, true);
        }
        
        // Add verification status
        global $wpdb;
        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT verification_status, verified_at FROM " . ZipPicks_Business_Database::get_verification_table() . " WHERE business_id = %d ORDER BY created_at DESC LIMIT 1",
            $post->ID
        ));
        
        if ($verification) {
            $business_data['verification_status'] = $verification->verification_status;
            $business_data['verified_at'] = $verification->verified_at;
        }
        
        // Add monetization info
        $monetization = $wpdb->get_row($wpdb->prepare(
            "SELECT tier, subscription_status, expires_at FROM " . ZipPicks_Business_Database::get_monetization_table() . " WHERE business_id = %d",
            $post->ID
        ));
        
        if ($monetization) {
            $business_data['subscription_tier'] = $monetization->tier;
            $business_data['subscription_status'] = $monetization->subscription_status;
            $business_data['subscription_expires'] = $monetization->expires_at;
        }
        
        return $business_data;
    }
    
    /**
     * Check if user can create businesses
     */
    public function can_create_businesses() {
        return current_user_can('publish_businesses');
    }
    
    /**
     * Track business view (called from template_redirect)
     */
    public function track_business_view() {
        if (is_singular('zippicks_business')) {
            global $post;
            
            try {
                // Track view event
                $tracked = ZipPicks_Business_Database::track_event($post->ID, 'view', 'page_view');
                
                if (!$tracked && ZIPPICKS_BUSINESS_DEBUG) {
                    error_log('ZipPicks Business: Failed to track view for business ID ' . $post->ID);
                }
                
                // Log potential scraping attempt
                $this->log_scrape_attempt($_SERVER['REQUEST_URI']);
                
            } catch (Exception $e) {
                // Log error but don't interrupt page view
                if (ZIPPICKS_BUSINESS_DEBUG) {
                    error_log('ZipPicks Business tracking error: ' . $e->getMessage());
                }
                
                // Log to Foundation if available
                if (function_exists('zippicks') && zippicks()->has('logger')) {
                    $logger = zippicks()->get('logger');
                    $logger->error('Business view tracking failed', array(
                        'business_id' => $post->ID,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ));
                }
            }
        }
    }
    
    /**
     * Add schema markup to business pages
     */
    public function add_schema_markup() {
        if (is_singular('zippicks_business')) {
            global $post;
            
            $business_data = array(
                '@context' => 'https://schema.org',
                '@type' => 'LocalBusiness',
                'name' => $post->post_title,
                'description' => $post->post_excerpt,
                'url' => get_permalink($post->ID)
            );
            
            // Add address if available
            $address = get_post_meta($post->ID, '_zp_address', true);
            $city = get_post_meta($post->ID, '_zp_city', true);
            $state = get_post_meta($post->ID, '_zp_state', true);
            $zip = get_post_meta($post->ID, '_zp_zip', true);
            
            if ($address || $city) {
                $business_data['address'] = array(
                    '@type' => 'PostalAddress',
                    'streetAddress' => $address,
                    'addressLocality' => $city,
                    'addressRegion' => $state,
                    'postalCode' => $zip
                );
            }
            
            // Add phone
            $phone = get_post_meta($post->ID, '_zp_phone', true);
            if ($phone) {
                $business_data['telephone'] = $phone;
            }
            
            // Add website
            $website = get_post_meta($post->ID, '_zp_website', true);
            if ($website) {
                $business_data['url'] = $website;
            }
            
            // Add anti-scraping fingerprint per CLAUDE.md
            $business_data['zippicks_fingerprint'] = 'ZP' . wp_generate_password(8, false);
            
            echo '<script type="application/ld+json">' . wp_json_encode($business_data) . '</script>';
        }
    }
    
    /**
     * Implement anti-scraping measures per CLAUDE.md
     */
    public function implement_anti_scraping() {
        // Add anti-scraping headers
        if (is_singular('zippicks_business') || is_post_type_archive('zippicks_business')) {
            header('X-Robots-Tag: noindex');
            header('Cache-Control: private, max-age=0');
            header('X-ZipPicks-Source: frontend-only');
        }
        
        // Rate limiting check
        $this->check_rate_limits();
        
        // Add content protection hooks
        add_action('wp_footer', array($this, 'add_copy_traps'));
    }
    
    /**
     * Log scrape attempt per CLAUDE.md requirements
     */
    private function log_scrape_attempt($request_path) {
        global $wpdb;
        
        $ip_address = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        // Check if this looks like scraping behavior
        $suspicious = false;
        
        // Empty or CLI user agents
        if (empty($user_agent) || strpos($user_agent, 'curl') !== false || strpos($user_agent, 'wget') !== false) {
            $suspicious = true;
        }
        
        // Insert or update scrape log
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . ZipPicks_Business_Database::get_scrape_log_table() . " 
             WHERE ip_address = %s AND request_path = %s AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            $ip_address, $request_path
        ));
        
        if ($existing) {
            // Update request count
            $wpdb->query($wpdb->prepare(
                "UPDATE " . ZipPicks_Business_Database::get_scrape_log_table() . " 
                 SET request_count = request_count + 1 WHERE id = %d",
                $existing
            ));
        } else {
            // Insert new log entry
            $wpdb->insert(
                ZipPicks_Business_Database::get_scrape_log_table(),
                array(
                    'ip_address' => $ip_address,
                    'request_path' => $request_path,
                    'user_agent' => $user_agent,
                    'referrer' => $referrer,
                    'timestamp' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
        }
        
        // Alert on suspicious activity
        if ($suspicious) {
            error_log("ZipPicks: Suspicious scraping activity from {$ip_address} - {$user_agent}");
        }
    }
    
    /**
     * Check rate limits per CLAUDE.md
     */
    private function check_rate_limits() {
        global $wpdb;
        
        $ip_address = $this->get_client_ip();
        $limit = 10; // requests per minute
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(request_count) FROM " . ZipPicks_Business_Database::get_scrape_log_table() . " 
             WHERE ip_address = %s AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            $ip_address
        ));
        
        if ($count > $limit) {
            header('HTTP/1.1 429 Too Many Requests');
            header('Retry-After: 60');
            wp_die('Rate limit exceeded. Please slow down.', 'Too Many Requests', array('response' => 429));
        }
    }
    
    /**
     * Add invisible copy traps per CLAUDE.md
     */
    public function add_copy_traps() {
        if (is_singular('zippicks_business')) {
            echo '<span class="zp-fp" data-hash="' . wp_generate_password(12, false) . '" style="display:none;">ZipPicks-Trap</span>';
        }
    }
    
    /**
     * Get client IP address (handles proxies/CDNs)
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Run daily analytics cleanup and processing
     */
    public function run_daily_analytics() {
        global $wpdb;
        
        // Clean up old analytics data (older than 90 days)
        $wpdb->query(
            "DELETE FROM " . ZipPicks_Business_Database::get_analytics_table() . " 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        
        // Clean up old scrape logs (older than 30 days)
        $wpdb->query(
            "DELETE FROM " . ZipPicks_Business_Database::get_scrape_log_table() . " 
             WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Log cleanup
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('ZipPicks Business: Daily analytics cleanup completed');
        }
    }
    
}