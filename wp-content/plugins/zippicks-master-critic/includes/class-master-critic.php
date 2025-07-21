<?php
/**
 * The core plugin class
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic {
    
    /**
     * The unique identifier of this plugin
     *
     * @var string
     */
    protected $plugin_name;
    
    /**
     * The current version of the plugin
     *
     * @var string
     */
    protected $version;
    
    /**
     * AI Service instance
     *
     * @var ZipPicks_Master_Critic_AI_Service
     */
    protected $ai_service;
    
    /**
     * Router instance
     *
     * @var ZipPicks_Master_Critic_Router
     */
    public $router;
    
    /**
     * Post Type instance
     *
     * @var ZipPicks_Master_Critic_Post_Type
     */
    public $post_type;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin_name = 'zippicks-master-critic';
        $this->version = ZIPPICKS_MASTER_CRITIC_VERSION;
        
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_schema_hooks();
        $this->register_with_foundation();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Load required classes
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-database.php';
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-installer.php';
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-ai-service.php';
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-master-critic-router.php';
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-master-critic-post-type.php';
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-audit-logger.php';
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'admin/class-admin.php';
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'admin/class-settings-page.php';
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'admin/class-generation-page.php';
        
        // Check if hybrid AI service is available
        if (file_exists(ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/services/class-ai-service-hybrid.php')) {
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/services/class-ai-service-hybrid.php';
            $this->ai_service = new \ZipPicks\MasterCritic\Services\AIServiceHybrid();
        } else {
            // Fall back to standard AI service
            $this->ai_service = new ZipPicks_Master_Critic_AI_Service();
        }
        
        // Initialize router and post type
        $this->router = new ZipPicks_Master_Critic_Router();
        $this->post_type = new ZipPicks_Master_Critic_Post_Type();
        
        // Initialize audit logger
        ZipPicks_Master_Critic_Audit_Logger::init();
        
        // Initialize schema generator
        if (file_exists(ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-schema-generator.php')) {
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-schema-generator.php';
            ZipPicks_Master_Critic_Schema_Generator::init();
        }
        
        // Initialize vibe display handler
        if (file_exists(ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-vibe-display-handler.php')) {
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-vibe-display-handler.php';
            ZipPicks_Master_Critic_Vibe_Display_Handler::init();
        }
    }
    
    /**
     * Register all hooks related to admin functionality
     */
    private function define_admin_hooks() {
        $admin = new ZipPicks_Master_Critic_Admin($this->plugin_name, $this->version, $this->ai_service);
        
        add_action('admin_menu', array($admin, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_styles'));
        
        // Add frontend script enqueuing
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_zippicks_generate_prompt', array($admin, 'ajax_generate_prompt'));
        add_action('wp_ajax_zippicks_execute_ai_generation', array($admin, 'ajax_execute_ai_generation'));
        add_action('wp_ajax_zippicks_save_prompt_template', array($admin, 'ajax_save_prompt_template'));
        add_action('wp_ajax_zippicks_create_businesses', array($admin, 'ajax_create_businesses'));
        add_action('wp_ajax_zippicks_create_list', array($admin, 'ajax_create_list'));
        add_action('wp_ajax_zippicks_view_generation', array($admin, 'ajax_view_generation'));
        add_action('wp_ajax_zippicks_get_template', array($admin, 'ajax_get_template'));
        add_action('wp_ajax_zippicks_update_template', array($admin, 'ajax_update_template'));
        add_action('wp_ajax_zippicks_delete_template', array($admin, 'ajax_delete_template'));
        add_action('wp_ajax_zippicks_test_zipbusiness_api', array($admin, 'ajax_test_zipbusiness_api'));
        
        // Public AJAX handlers
        add_action('wp_ajax_zp_add_to_waitlist', array($this, 'ajax_add_to_waitlist'));
        add_action('wp_ajax_nopriv_zp_add_to_waitlist', array($this, 'ajax_add_to_waitlist'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Schema validation notices
        add_action('admin_notices', array($this, 'schema_validation_notices'));
        
        // Table existence check
        add_action('admin_notices', array($this, 'table_existence_notices'));
    }
    
    /**
     * Register all hooks related to schema functionality
     */
    private function define_schema_hooks() {
        $schema_hooks = new ZipPicks_Master_Critic_Schema_Hooks();
        $schema_hooks->init();
    }
    
    /**
     * Register with Foundation if available
     */
    private function register_with_foundation() {
        // Check if Foundation exists
        if (!function_exists('zippicks')) {
            // Log that Foundation is not available
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Master Critic: Foundation not available, running in standalone mode');
            }
            return;
        }
        
        try {
            $foundation = zippicks();
            
            // Register AI service if bind method exists
            if (method_exists($foundation, 'bind')) {
                $foundation->bind('master_critic.ai', $this->ai_service);
                
                // Log successful registration
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('ZipPicks Master Critic: AI service registered with Foundation');
                }
            }
            
            // Register database schema if installer service exists
            if (method_exists($foundation, 'has') && $foundation->has('database.installer')) {
                $installer = $foundation->get('database.installer');
                
                if ($installer && method_exists($installer, 'register_schema')) {
                    $installer->register_schema('master-critic', function() {
                        return ZipPicks_Master_Critic_Database::get_schema_sql();
                    }, $this->version);
                    
                    // Explicitly register analytics schema for clarity
                    $installer->register_schema('master-critic-analytics', function() {
                        return ZipPicks_Master_Critic_Database::get_schema_sql();
                    }, $this->version);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('ZipPicks Master Critic: Database schemas registered with Foundation');
                    }
                }
            } else {
                // Foundation doesn't have database installer - ensure tables exist manually
                $this->ensure_tables_exist();
            }
        } catch (Exception $e) {
            // Log any errors but don't fail
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Master Critic: Error registering with Foundation: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Run the plugin
     */
    public function run() {
        // Check and create tables on init
        add_action('init', array($this, 'init'));
        
        // Register hooks for plugin communication
        add_action('zippicks_business_created', array($this, 'handle_business_created'), 10, 2);
        
        // Add manual table creation action
        add_action('admin_action_master_critic_create_tables', array($this, 'manual_create_tables'));
    }
    
    /**
     * Ensure database tables exist
     */
    private function ensure_tables_exist() {
        if (!ZipPicks_Master_Critic_Installer::tables_exist()) {
            ZipPicks_Master_Critic_Installer::install();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ZipPicks Master Critic: Database tables created');
            }
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Auto-create tables if missing
        if (!ZipPicks_Master_Critic_Installer::tables_exist()) {
            ZipPicks_Master_Critic_Installer::install();
        }
        
        // Register post meta for master_critic_list posts
        register_post_meta('master_critic_list', '_mc_list_category', [
            'type' => 'string',
            'description' => 'The Top 10 category type',
            'single' => true,
            'default' => 'best_overall',
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        register_post_meta('master_critic_list', '_mc_vibe_ids', [
            'type' => 'array',
            'description' => 'Associated vibe taxonomy IDs',
            'single' => true,
            'default' => [],
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'integer'
                    ]
                ]
            ]
        ]);
        
        // The post type and router are already initialized in their constructors
        // which hook into 'init' action themselves, so we don't need to call init() here
    }
    
    /**
     * Enqueue frontend scripts for Master Critic lists
     */
    public function enqueue_frontend_scripts() {
        // Only load on Master Critic list pages
        if (is_singular('master_critic_list')) {
            wp_enqueue_script(
                'zippicks-list-loader',
                ZIPPICKS_MASTER_CRITIC_PLUGIN_URL . 'assets/js/list-loader.js',
                array('jquery'),
                $this->version,
                true
            );
            
            wp_localize_script('zippicks-list-loader', 'zippicksAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('zippicks_load_content'),
                'listId' => get_the_ID(),
                'restUrl' => rest_url('zippicks/v1/'),
                'settings' => array(
                    'animationDuration' => 300,
                    'retryAttempts' => 3,
                    'retryDelay' => 2000
                )
            ));
        }
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Show table creation result if present
        if (isset($_GET['tables-created'])) {
            if ($_GET['tables-created'] === 'success') {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>ZipPicks Master Critic:</strong> Database tables created successfully!</p>
                </div>
                <?php
            } elseif ($_GET['tables-created'] === 'failed') {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong>ZipPicks Master Critic:</strong> Failed to create database tables. Please try the manual creation method or contact support.</p>
                </div>
                <?php
            }
        }
        
        // Show migration result if present
        if (isset($_GET['migration-result'])) {
            if ($_GET['migration-result'] === 'migration-success') {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>ZipPicks Master Critic:</strong> Database migration completed successfully!</p>
                </div>
                <?php
            } elseif ($_GET['migration-result'] === 'migration-failed') {
                $error_message = isset($_GET['error']) ? esc_html($_GET['error']) : 'Unknown error';
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong>ZipPicks Master Critic:</strong> Database migration failed: <?php echo $error_message; ?></p>
                    <p>Please try the manual creation method or contact support.</p>
                </div>
                <?php
            }
        }
        
        // Check migration status
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-database-migrator.php';
        $migration_status = ZipPicks_Master_Critic_Database_Migrator::get_migration_status();
        
        if ($migration_status['needs_migration']) {
            $migrate_url = wp_nonce_url(
                admin_url('admin.php?page=zippicks-master-critic&action=run-migration'),
                'run_migration_action'
            );
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>ZipPicks Master Critic:</strong> Database needs migration to version <?php echo esc_html($migration_status['target_version']); ?> 
                    (currently at <?php echo esc_html($migration_status['current_version']); ?>).
                    <a href="<?php echo esc_url($migrate_url); ?>" class="button button-primary">Run Migration</a>
                </p>
            </div>
            <?php
        } elseif (!ZipPicks_Master_Critic_Installer::tables_exist()) {
            $create_tables_url = wp_nonce_url(
                admin_url('admin.php?page=zippicks-master-critic&action=create-tables'),
                'create_tables_action'
            );
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>ZipPicks Master Critic:</strong> Required database tables are missing. 
                    <a href="<?php echo esc_url($create_tables_url); ?>" class="button button-primary">Create Tables Now</a>
                </p>
            </div>
            <?php
        }
        
        // Check API keys
        $anthropic_key = get_option('zippicks_anthropic_api_key');
        $openai_key = get_option('zippicks_openai_api_key');
        
        if (empty($anthropic_key) && empty($openai_key)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>ZipPicks Master Critic:</strong> No AI API keys configured. 
                    <a href="<?php echo admin_url('admin.php?page=zippicks-master-critic-settings'); ?>">Configure API Keys</a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Display schema validation notices
     */
    public function schema_validation_notices() {
        global $pagenow, $post;
        
        // Only show on edit post screen for lists
        if ($pagenow !== 'post.php' || !$post || !in_array($post->post_type, ['zippicks_list', 'master_critic_list'])) {
            return;
        }
        
        // Check if businesses data exists
        $businesses_json = get_post_meta($post->ID, 'zippicks_list_businesses', true);
        
        if (empty($businesses_json)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>Schema.org Notice:</strong> This list does not have business data for schema generation. 
                    Schema will not be generated until businesses are added.
                </p>
            </div>
            <?php
            return;
        }
        
        // Check if schema has been generated
        $cached_schema = get_post_meta($post->ID, '_zippicks_cached_schema', true);
        $generated_time = get_post_meta($post->ID, '_zippicks_schema_generated', true);
        
        if (!$cached_schema) {
            ?>
            <div class="notice notice-info">
                <p>
                    <strong>Schema.org Notice:</strong> Schema has not been generated yet for this list. 
                    It will be automatically generated when the list is viewed.
                    <a href="<?php echo get_permalink($post->ID); ?>" target="_blank" class="button button-secondary">View List</a>
                </p>
            </div>
            <?php
        } else {
            // Validate the cached schema
            $schema_generator = new ZipPicks_Master_Critic_Schema_Generator();
            $validation = $schema_generator->validate_schema($cached_schema);
            
            if ($validation['valid']) {
                ?>
                <div class="notice notice-success">
                    <p>
                        <strong>Schema.org Status:</strong> ✓ Valid ItemList schema is active. 
                        Last generated: <?php echo esc_html($generated_time); ?>
                        <a href="https://search.google.com/test/rich-results?url=<?php echo urlencode(get_permalink($post->ID)); ?>" target="_blank" class="button button-secondary">Test in Google</a>
                    </p>
                </div>
                <?php
            } else {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong>Schema.org Error:</strong> The schema has validation errors. 
                        Please check the Schema Preview meta box for details.
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Handle business created event
     *
     * @param array $business_ids
     * @param array $data
     */
    public function handle_business_created($business_ids, $data) {
        // Only handle our own generation updates
        if (isset($data['source']) && $data['source'] === 'master_critic' && isset($data['generation_id'])) {
            ZipPicks_Master_Critic_Database::update_generation(
                $data['generation_id'],
                array('businesses_created' => count($business_ids))
            );
        }
    }
    
    /**
     * Get plugin name
     *
     * @return string
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }
    
    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Get AI service
     *
     * @return ZipPicks_Master_Critic_AI_Service
     */
    public function get_ai_service() {
        return $this->ai_service;
    }
    
    /**
     * Display table existence notices
     */
    public function table_existence_notices() {
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if tables exist
        if (!ZipPicks_Master_Critic_Installer::tables_exist()) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>ZipPicks Master Critic:</strong> Required database tables are missing. 
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?action=master_critic_create_tables'), 'master_critic_create_tables'); ?>" class="button button-primary">Create Tables Now</a>
                    or <a href="<?php echo ZIPPICKS_MASTER_CRITIC_PLUGIN_URL . 'create-tables.php'; ?>" target="_blank">use manual creation tool</a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Handle manual table creation
     */
    public function manual_create_tables() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'master_critic_create_tables')) {
            wp_die(__('Security check failed.'));
        }
        
        // Create tables
        ZipPicks_Master_Critic_Installer::install();
        
        // Redirect back with success message
        wp_redirect(add_query_arg(
            array(
                'page' => 'zippicks-master-critic',
                'message' => 'tables_created'
            ),
            admin_url('admin.php')
        ));
        exit;
    }
    
    /**
     * AJAX handler for adding users to Top 10 waitlist
     */
    public function ajax_add_to_waitlist() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zp_waitlist_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Validate required fields
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;
        
        if (empty($email) || empty($zip_code)) {
            wp_send_json_error('Email and ZIP code are required');
        }
        
        // Validate email
        if (!is_email($email)) {
            wp_send_json_error('Please provide a valid email address');
        }
        
        // Validate ZIP code (5 digits)
        if (!preg_match('/^[0-9]{5}$/', $zip_code)) {
            wp_send_json_error('Please provide a valid 5-digit ZIP code');
        }
        
        global $wpdb;
        
        // Check if already on waitlist
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}zippicks_waitlist 
             WHERE email = %s AND zip_code = %s AND list_id = %d",
            $email, $zip_code, $list_id
        ));
        
        if ($existing) {
            wp_send_json_success('You are already on the waitlist for this Top 10 list!');
        }
        
        // Add to waitlist
        $data = array(
            'email' => $email,
            'zip_code' => $zip_code,
            'city' => $city,
            'category' => $category,
            'list_id' => $list_id,
            'created_at' => current_time('mysql'),
            'user_id' => get_current_user_id() ?: null
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zippicks_waitlist',
            $data,
            array('%s', '%s', '%s', '%s', '%d', '%s', '%d')
        );
        
        if ($result === false) {
            // If the table doesn't exist, try the vibes waitlist table as fallback
            $vibe_result = $wpdb->insert(
                $wpdb->prefix . 'zippicks_waitlist',
                array(
                    'vibe_id' => 0, // Use 0 for Top 10 lists
                    'zip_code' => $zip_code,
                    'email' => $email,
                    'user_id' => get_current_user_id() ?: null,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%d', '%s')
            );
            
            if ($vibe_result === false) {
                wp_send_json_error('Unable to add you to the waitlist. Please try again later.');
            }
        }
        
        // Log the waitlist signup if logger is available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('Top 10 waitlist signup', array(
                'email' => $email,
                'zip_code' => $zip_code,
                'city' => $city,
                'category' => $category,
                'list_id' => $list_id
            ));
        }
        
        wp_send_json_success('Thank you! We will notify you when this Top 10 list is ready.');
    }
}