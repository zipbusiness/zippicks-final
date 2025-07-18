<?php
/**
 * Admin functionality
 *
 * @package ZipPicks_Master_Critic
 */

require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/ScoringEngine.php';
use ZP_MasterCritic_ScoringEngine;

class ZipPicks_Master_Critic_Admin {
    
    /**
     * Plugin name
     *
     * @var string
     */
    private $plugin_name;
    
    /**
     * Plugin version
     *
     * @var string
     */
    private $version;
    
    /**
     * AI Service
     *
     * @var ZipPicks_Master_Critic_AI_Service
     */
    private $ai_service;
    
    /**
     * Constructor
     *
     * @param string $plugin_name
     * @param string $version
     * @param ZipPicks_Master_Critic_AI_Service $ai_service
     */
    public function __construct($plugin_name, $version, $ai_service) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->ai_service = $ai_service;
        
        // Add meta box hooks
        add_action('add_meta_boxes', array($this, 'add_vibe_selection_metabox'));
        add_action('save_post_master_critic_list', array($this, 'save_vibe_associations'), 10, 2);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu - points to AI Generation
        add_menu_page(
            'ZipPicks Master Critic - AI Generation',
            'Master Critic',
            'manage_zippicks_master_critic',
            'zippicks-master-critic',
            array($this, 'display_generation_page'),
            'dashicons-star-filled',
            30
        );
        
        // AI Generation submenu (first item) - priority 10
        add_submenu_page(
            'zippicks-master-critic',
            'AI Generation',
            'AI Generation',
            'use_zippicks_ai_generation',
            'zippicks-master-critic',
            array($this, 'display_generation_page'),
            10
        );
        
        // Hybrid Data submenu - priority 20
        add_submenu_page(
            'zippicks-master-critic',
            'Hybrid Data System',
            'Hybrid Data',
            'manage_options',
            'zippicks-hybrid-data',
            array($this, 'display_hybrid_data_page'),
            20
        );
        
        // Settings submenu - priority 50
        add_submenu_page(
            'zippicks-master-critic',
            'Settings',
            'Settings',
            'manage_zippicks_master_critic',
            'zippicks-master-critic-settings',
            array($this, 'display_settings_page'),
            50
        );
        
        // History submenu - priority 60
        add_submenu_page(
            'zippicks-master-critic',
            'Generation History',
            'History',
            'use_zippicks_ai_generation',
            'zippicks-master-critic-history',
            array($this, 'display_history_page'),
            60
        );
        
        // Templates submenu - priority 70
        add_submenu_page(
            'zippicks-master-critic',
            'Prompt Templates',
            'Templates',
            'manage_zippicks_prompt_templates',
            'zippicks-master-critic-templates',
            array($this, 'display_templates_page'),
            70
        );
        
        // Health Check submenu - priority 80
        add_submenu_page(
            'zippicks-master-critic',
            'Health Check',
            'Health Check',
            'manage_zippicks_master_critic',
            'zippicks-master-critic-health',
            array($this, 'display_health_page'),
            80
        );
        
        // Hook to reorder submenu items after all have been added
        add_action('admin_menu', array($this, 'reorder_submenu_items'), 999);
    }
    
    /**
     * Reorder submenu items to ensure correct order
     */
    public function reorder_submenu_items() {
        global $submenu;
        
        if (!isset($submenu['zippicks-master-critic'])) {
            return;
        }
        
        // Define the desired order
        $order = array(
            'zippicks-master-critic' => 10,      // AI Generation
            'zippicks-hybrid-data' => 20,         // Hybrid Data
            'zippicks-master-critic-settings' => 30,    // Settings
            'zippicks-master-critic-history' => 40,     // History
            'zippicks-master-critic-templates' => 50,   // Templates
            'zippicks-master-critic-health' => 60       // Health Check
        );
        
        // Sort submenu items
        usort($submenu['zippicks-master-critic'], function($a, $b) use ($order) {
            $a_slug = $a[2] ?? '';
            $b_slug = $b[2] ?? '';
            
            $a_order = $order[$a_slug] ?? 999;
            $b_order = $order[$b_slug] ?? 999;
            
            return $a_order - $b_order;
        });
    }
    
    /**
     * Display generation page
     */
    public function display_generation_page() {
        // Handle table creation if requested
        if (isset($_GET['action']) && $_GET['action'] === 'create-tables') {
            // Check user permissions
            if (!current_user_can('manage_zippicks_master_critic')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }
            
            // Verify nonce if provided
            if (isset($_GET['_wpnonce']) && !wp_verify_nonce($_GET['_wpnonce'], 'create_tables_action')) {
                wp_die(__('Security check failed.'));
            }
            
            // Create tables
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-installer.php';
            ZipPicks_Master_Critic_Installer::install();
            
            // Verify tables were created
            $tables_exist = ZipPicks_Master_Critic_Installer::tables_exist();
            $status = $tables_exist ? 'success' : 'failed';
            
            wp_redirect(admin_url('admin.php?page=zippicks-master-critic&tables-created=' . $status));
            exit;
        }
        
        // Handle database migration if requested
        if (isset($_GET['action']) && $_GET['action'] === 'run-migration') {
            // Check user permissions
            if (!current_user_can('manage_zippicks_master_critic')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }
            
            // Verify nonce
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'run_migration_action')) {
                wp_die(__('Security check failed.'));
            }
            
            // Run migration
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-database-migrator.php';
            $migration_result = ZipPicks_Master_Critic_Database_Migrator::run_migrations();
            
            $status = $migration_result['status'] === 'success' ? 'migration-success' : 'migration-failed';
            $redirect_url = admin_url('admin.php?page=zippicks-master-critic&migration-result=' . $status);
            
            if ($migration_result['status'] === 'failed' && isset($migration_result['error'])) {
                $redirect_url .= '&error=' . urlencode($migration_result['error']);
            }
            
            wp_redirect($redirect_url);
            exit;
        }
        
        // Handle form submission fallback (when JavaScript fails)
        if (!isset($_GET['page']) && (isset($_GET['generated_prompt']) || isset($_GET['business_category']))) {
            // Use error handler to display proper error page
            ZipPicks_Master_Critic_Error_Handler::display_error_page(
                'The Master Critic AI Generator requires JavaScript to function properly. Please enable JavaScript in your browser settings and try again.',
                'JavaScript Required'
            );
            exit;
        }
        
        // Additional check for malformed URLs
        if (isset($_GET['page']) && $_GET['page'] === 'zippicks-master-critic' && isset($_GET['business_category']) && !isset($_POST['nonce'])) {
            // This is a GET submission when it should be AJAX
            wp_redirect(admin_url('admin.php?page=zippicks-master-critic&error=js-required'));
            exit;
        }
        
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'admin/class-generation-page.php';
        $page = new ZipPicks_Master_Critic_Generation_Page($this->ai_service);
        $page->render();
    }
    
    /**
     * Display hybrid data page
     */
    public function display_hybrid_data_page() {
        // Check if HybridServiceProvider is loaded
        if (!class_exists('\ZipPicks\MasterCritic\Hybrid\HybridServiceProvider')) {
            wp_die(__('Hybrid Data System is not available. Please check plugin installation.'));
        }
        
        // Use the HybridServiceProvider's render method
        \ZipPicks\MasterCritic\Hybrid\HybridServiceProvider::render_admin_page();
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'admin/class-settings-page.php';
        $page = new ZipPicks_Master_Critic_Settings_Page();
        $page->render();
    }
    
    /**
     * Display history page
     */
    public function display_history_page() {
        ?>
        <div class="wrap">
            <h1>Generation History</h1>
            <?php
            $generations = ZipPicks_Master_Critic_Database::get_recent_generations(50);
            if (empty($generations)) {
                echo '<p>No generations yet.</p>';
            } else {
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 100px;">Category</th>
                            <th style="width: 150px;">Topic</th>
                            <th style="width: 150px;">Location</th>
                            <th style="width: 100px;">Provider</th>
                            <th>Prompt</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 150px;">Created</th>
                            <th style="width: 80px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($generations as $gen): ?>
                        <tr>
                            <td><?php echo esc_html($gen->id); ?></td>
                            <td><?php echo esc_html(ucfirst($gen->business_category)); ?></td>
                            <td><?php echo esc_html($gen->topic); ?></td>
                            <td><?php echo esc_html($gen->location); ?></td>
                            <td><?php echo esc_html(strtoupper($gen->ai_provider)); ?></td>
                            <td>
                                <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr($gen->prompt); ?>">
                                    <?php echo esc_html(substr($gen->prompt, 0, 100)) . (strlen($gen->prompt) > 100 ? '...' : ''); ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-<?php echo esc_attr($gen->status); ?>">
                                    <?php echo esc_html(ucfirst($gen->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($gen->created_at); ?></td>
                            <td>
                                <a href="#" class="view-generation" data-id="<?php echo esc_attr($gen->id); ?>">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Display templates page
     */
    public function display_templates_page() {
        ?>
        <div class="wrap">
            <h1>Prompt Templates</h1>
            <p>Manage reusable prompt templates for different business categories.</p>
            <?php
            $templates = ZipPicks_Master_Critic_Database::get_prompt_templates();
            if (empty($templates)) {
                echo '<p>No custom templates yet. The default template is being used.</p>';
            } else {
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Default</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template): ?>
                        <tr>
                            <td><?php echo esc_html($template->name); ?></td>
                            <td><?php echo esc_html(ucfirst($template->business_category)); ?></td>
                            <td><?php echo $template->is_default ? '✓' : ''; ?></td>
                            <td><?php echo esc_html($template->created_at); ?></td>
                            <td>
                                <a href="#" class="edit-template" data-id="<?php echo esc_attr($template->id); ?>">Edit</a> |
                                <a href="#" class="delete-template" data-id="<?php echo esc_attr($template->id); ?>">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Display health check page
     */
    public function display_health_page() {
        // Check user permissions
        if (!current_user_can('manage_zippicks_master_critic')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Get health check instance
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-health-check.php';
        $health_check = new ZipPicks_Master_Critic_Health_Check();
        
        // Get current health status
        $health_status = $health_check->get_status();
        
        ?>
        <div class="wrap">
            <h1>Health Check</h1>
            <p>Monitor the health and performance of the Master Critic plugin.</p>
            
            <!-- Overall Status -->
            <div class="health-overview">
                <h2>Overall Status</h2>
                <?php
                $status_class = 'notice-success';
                $status_text = 'Healthy';
                
                if ($health_status['status'] === 'warning') {
                    $status_class = 'notice-warning';
                    $status_text = 'Warning';
                } elseif ($health_status['status'] === 'critical') {
                    $status_class = 'notice-error';
                    $status_text = 'Critical Issues Detected';
                }
                ?>
                <div class="notice <?php echo esc_attr($status_class); ?> inline">
                    <p><strong>Status:</strong> <?php echo esc_html($status_text); ?></p>
                    <p><strong>Last Check:</strong> <?php echo esc_html($health_status['timestamp']); ?></p>
                </div>
            </div>
            
            <!-- Issues and Warnings -->
            <?php if (!empty($health_status['issues'])): ?>
            <div class="health-issues">
                <h2>Critical Issues</h2>
                <div class="notice notice-error inline">
                    <ul>
                        <?php foreach ($health_status['issues'] as $issue): ?>
                        <li><?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($health_status['warnings'])): ?>
            <div class="health-warnings">
                <h2>Warnings</h2>
                <div class="notice notice-warning inline">
                    <ul>
                        <?php foreach ($health_status['warnings'] as $warning): ?>
                        <li><?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Detailed Check Results -->
            <div class="health-details">
                <h2>Detailed Check Results</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Check</th>
                            <th>Status</th>
                            <th>Message</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($health_status['checks'] as $check_name => $check_result): ?>
                        <tr>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $check_name))); ?></td>
                            <td>
                                <?php
                                $status_badge = '';
                                switch ($check_result['status']) {
                                    case 'healthy':
                                        $status_badge = '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Healthy';
                                        break;
                                    case 'warning':
                                        $status_badge = '<span class="dashicons dashicons-warning" style="color: orange;"></span> Warning';
                                        break;
                                    case 'critical':
                                        $status_badge = '<span class="dashicons dashicons-dismiss" style="color: red;"></span> Critical';
                                        break;
                                }
                                echo $status_badge;
                                ?>
                            </td>
                            <td><?php echo esc_html($check_result['message']); ?></td>
                            <td>
                                <?php if (!empty($check_result['details'])): ?>
                                <details>
                                    <summary>View Details</summary>
                                    <pre><?php echo esc_html(is_array($check_result['details']) ? json_encode($check_result['details'], JSON_PRETTY_PRINT) : $check_result['details']); ?></pre>
                                </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Performance Metrics -->
            <?php if (!empty($health_status['metrics'])): ?>
            <div class="health-metrics">
                <h2>Performance Metrics</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($health_status['metrics'] as $metric_name => $metric_value): ?>
                        <tr>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $metric_name))); ?></td>
                            <td><?php echo esc_html(is_array($metric_value) ? json_encode($metric_value) : $metric_value); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Actions -->
            <div class="health-actions">
                <h2>Actions</h2>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zippicks-master-critic-health')); ?>" class="button button-primary">Refresh Health Check</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zippicks-master-critic-settings')); ?>" class="button">Go to Settings</a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue scripts
     *
     * @param string $hook
     */
    public function enqueue_scripts($hook) {
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Master Critic enqueue_scripts called with hook: ' . $hook);
        }
        
        // Only load on our pages - use stripos for case-insensitive check
        if (stripos($hook, 'zippicks-master-critic') === false) {
            return;
        }
        
        wp_enqueue_script(
            'zippicks-master-critic-admin',
            ZIPPICKS_MASTER_CRITIC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            $this->version . '-' . time(), // Force cache refresh during development
            true
        );
        
        // Localize script
        wp_localize_script('zippicks-master-critic-admin', 'zippicks_master_critic', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_master_critic_nonce'),
            'default_provider' => get_option('zippicks_default_ai_provider', 'anthropic'),
            'business_categories' => get_option('zippicks_business_categories'),
            'settings_url' => admin_url('admin.php?page=zippicks-master-critic-settings'),
            'strings' => array(
                'generating' => __('Generating prompt...', 'zippicks-master-critic'),
                'executing' => __('Calling AI API...', 'zippicks-master-critic'),
                'error' => __('An error occurred. Please try again.', 'zippicks-master-critic'),
                'confirm_execute' => __('Are you sure you want to execute this AI generation?', 'zippicks-master-critic')
            )
        ));
    }
    
    /**
     * Enqueue styles
     *
     * @param string $hook
     */
    public function enqueue_styles($hook) {
        // Only load on our pages - use stripos for case-insensitive check
        if (stripos($hook, 'zippicks-master-critic') === false) {
            return;
        }
        
        wp_enqueue_style(
            'zippicks-master-critic-admin',
            ZIPPICKS_MASTER_CRITIC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $this->version
        );
    }
    
    /**
     * AJAX handler for generating prompt
     */
    public function ajax_generate_prompt() {
        check_ajax_referer('zippicks_master_critic_nonce', 'nonce');
        
        if (!current_user_can('use_zippicks_ai_generation')) {
            wp_die('Unauthorized');
        }
        
        $params = array(
            'business_category' => sanitize_text_field($_POST['business_category']),
            'topic' => sanitize_text_field($_POST['topic']),
            'location' => sanitize_text_field($_POST['location']),
            'search_type' => sanitize_text_field($_POST['search_type']),
            'list_category' => sanitize_text_field($_POST['list_category'] ?? 'best_overall')
        );
        
        $prompt = $this->ai_service->generate_prompt($params);
        
        // Save generation record with prompt when prompt is generated
        $generation_id = ZipPicks_Master_Critic_Database::insert_generation(array(
            'business_category' => $params['business_category'],
            'topic' => $params['topic'],
            'location' => $params['location'],
            'search_type' => $params['search_type'],
            'list_category' => $params['list_category'],
            'ai_provider' => get_option('zippicks_default_ai_provider', 'anthropic'), // Use default provider for now
            'prompt' => $prompt,
            'status' => 'draft' // Mark as draft until AI execution
        ));
        
        wp_send_json_success(array(
            'prompt' => $prompt,
            'generation_id' => $generation_id
        ));
    }
    
    /**
     * AJAX handler for executing AI generation
     */
    public function ajax_execute_ai_generation() {
        check_ajax_referer('zippicks_master_critic_nonce', 'nonce');
        
        if (!current_user_can('use_zippicks_ai_generation')) {
            wp_die('Unauthorized');
        }
        
        $prompt = wp_unslash($_POST['prompt']);
        $provider = sanitize_text_field($_POST['ai_provider']);
        
        // Check if we have an existing generation ID from prompt generation
        if (isset($_POST['generation_id']) && !empty($_POST['generation_id'])) {
            $generation_id = intval($_POST['generation_id']);
            
            // Update the existing generation record with AI provider and status
            ZipPicks_Master_Critic_Database::update_generation($generation_id, array(
                'ai_provider' => $provider,
                'prompt' => $prompt, // Update prompt in case it was edited
                'status' => 'processing'
            ));
        } else {
            // Create new generation record if none exists
            $generation_id = ZipPicks_Master_Critic_Database::insert_generation(array(
                'business_category' => sanitize_text_field($_POST['business_category']),
                'topic' => sanitize_text_field($_POST['topic']),
                'location' => sanitize_text_field($_POST['location']),
                'search_type' => sanitize_text_field($_POST['search_type']),
                'list_category' => sanitize_text_field($_POST['list_category'] ?? 'best_overall'),
                'ai_provider' => $provider,
                'prompt' => $prompt,
                'status' => 'processing'
            ));
        }
        
        // Execute enhanced AI generation with confidence scoring
        $params = array(
            'business_category' => sanitize_text_field($_POST['business_category']),
            'topic' => sanitize_text_field($_POST['topic']),
            'location' => sanitize_text_field($_POST['location']),
            'search_type' => sanitize_text_field($_POST['search_type']),
            'list_category' => sanitize_text_field($_POST['list_category'] ?? 'best_overall')
        );
        
        // Check if API verification is enabled
        $use_api_verification = get_option('zippicks_enable_api_verification', false);
        
        if ($use_api_verification) {
            // Step 1: Fetch real restaurants from API
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/services/class-zipbusiness-api-client.php';
            $api_client = new ZipPicks_Master_Critic_ZipBusiness_API_Client();
            
            // Parse location to extract city and state
            $location_parts = $this->parse_location($params['location']);
            $city = $location_parts['city'];
            $state = $location_parts['state'];
            
            $city_restaurants = $api_client->get_city_restaurants($city, $state);
            
            if (empty($city_restaurants)) {
                // Fallback mode - mark as unverified
                error_log('[ZipPicks] No restaurants found via API for ' . $city . ', ' . $state . ' - using unverified mode');
                $result = $this->execute_unverified_generation($params, $provider, $prompt);
            } else {
                // Update generation record with city restaurant count
                ZipPicks_Master_Critic_Database::update_generation($generation_id, array(
                    'city_restaurant_count' => count($city_restaurants),
                    'api_fetch_time' => current_time('mysql')
                ));
                
                // Step 2: Filter by vibes if applicable
                require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/services/class-vibe-integration-service.php';
                $vibe_service = new ZipPicks_Master_Critic_Vibe_Integration();
                $filtered_restaurants = $vibe_service->filter_by_vibes(
                    $city_restaurants, 
                    $params['list_category']
                );
                
                // Step 3: Send to AI for ranking
                $result = $this->ai_service->execute_verified_generation($params, $filtered_restaurants);
                
                if ($result['success']) {
                    // Step 4: Enrich top 10
                    $top_10_zpids = array_slice(array_column($result['data'], 'zpid'), 0, 10);
                    if (!empty($top_10_zpids)) {
                        $enriched_data = $api_client->enrich_restaurants($top_10_zpids);
                        
                        // Merge enriched data
                        foreach ($result['data'] as &$restaurant) {
                            if (isset($enriched_data[$restaurant['zpid']])) {
                                $restaurant = array_merge($restaurant, $enriched_data[$restaurant['zpid']]);
                            }
                        }
                    }
                    
                    // Update verification status
                    ZipPicks_Master_Critic_Database::update_generation($generation_id, array(
                        'api_verification_status' => 'verified',
                        'verified_count' => $result['total_verified'] ?? count($result['data'])
                    ));
                }
            }
        } else {
            // Use traditional enhanced generation without API verification
            $use_enhanced = apply_filters('zippicks_use_enhanced_generation', true);
            
            if ($use_enhanced) {
                $result = $this->ai_service->execute_enhanced_generation($params);
            } else {
                // Fallback to basic generation
                error_log('[ZipPicks] Using basic generation due to filter override');
                $result = $this->ai_service->execute_ai_generation($prompt, $provider);
            }
        }
        
        if ($result['success']) {
            // Parse businesses from result
            $businesses = $result['businesses'] ?? null;
            
            // If no businesses array, try to parse from data
            if (!$businesses && isset($result['data'])) {
                $businesses = $this->ai_service->parse_ai_response($result['data']);
            }
            
            // Update generation record with confidence and validation (if available)
            ZipPicks_Master_Critic_Database::update_generation($generation_id, array(
                'ai_response' => json_encode($businesses),
                'confidence_score' => $result['confidence'] ?? null,
                'validation_report' => json_encode($result['validation_report'] ?? null),
                'status' => 'completed'
            ));
            
            // Apply scoring engine if businesses were successfully parsed
            // DISABLED: Scoring engine overwrites AI-generated content
            // Uncomment only if you want to use review-based scoring instead of AI
            /*
            if (!empty($businesses) && is_array($businesses)) {
                $engine = new ZP_MasterCritic_ScoringEngine();
                $scored_businesses = $engine->calculate_scores($businesses);
                $businesses = $scored_businesses;
            }
            */
            
            wp_send_json_success(array(
                'generation_id' => $generation_id,
                'response' => json_encode($businesses),
                'businesses' => $businesses,
                'confidence' => $result['confidence'] ?? 0,
                'validation_report' => $result['validation_report'] ?? null,
                'provider' => $result['provider'] ?? 'anthropic',
                'cached' => isset($result['cached']) ? $result['cached'] : false
            ));
        } else {
            // Log the complete error for debugging
            error_log('[ZipPicks Admin] AI Generation Failed');
            error_log('[ZipPicks Admin] Error: ' . ($result['error'] ?? 'Unknown error'));
            error_log('[ZipPicks Admin] Provider: ' . ($result['provider'] ?? 'Unknown'));
            error_log('[ZipPicks Admin] HTTP Code: ' . ($result['http_code'] ?? 'N/A'));
            error_log('[ZipPicks Admin] WP Error Code: ' . ($result['wp_error_code'] ?? 'N/A'));
            error_log('[ZipPicks Admin] Execution Time: ' . ($result['execution_time'] ?? 'N/A'));
            
            // Store detailed error information in the database
            $error_details = array(
                'error' => $result['error'] ?? 'Unknown error',
                'http_code' => $result['http_code'] ?? null,
                'wp_error_code' => $result['wp_error_code'] ?? null,
                'raw_response' => isset($result['raw_response']) ? substr($result['raw_response'], 0, 1000) : null,
                'execution_time' => $result['execution_time'] ?? null,
                'timestamp' => current_time('mysql')
            );
            
            // Update generation record with error details
            ZipPicks_Master_Critic_Database::update_generation($generation_id, array(
                'status' => 'failed',
                'ai_response' => json_encode($error_details) // Store error details in ai_response field
            ));
            
            $error_data = array(
                'message' => $result['error'],
                'provider' => $result['provider']
            );
            
            // Pass through additional error information
            if (isset($result['suggestion'])) {
                $error_data['suggestion'] = $result['suggestion'];
            }
            if (isset($result['http_code'])) {
                $error_data['http_code'] = $result['http_code'];
            }
            if (isset($result['wp_error_code'])) {
                $error_data['wp_error_code'] = $result['wp_error_code'];
            }
            if (isset($result['execution_time'])) {
                $error_data['execution_time'] = $result['execution_time'];
            }
            
            wp_send_json_error($error_data);
        }
    }
    
    /**
     * AJAX handler for saving prompt template
     */
    public function ajax_save_prompt_template() {
        check_ajax_referer('zippicks_master_critic_nonce', 'nonce');
        
        if (!current_user_can('manage_zippicks_prompt_templates')) {
            wp_die('Unauthorized');
        }
        
        $template_id = ZipPicks_Master_Critic_Database::save_prompt_template(array(
            'name' => sanitize_text_field($_POST['name']),
            'business_category' => sanitize_text_field($_POST['business_category']),
            'prompt_template' => wp_unslash($_POST['prompt_template']),
            'is_default' => !empty($_POST['is_default'])
        ));
        
        if ($template_id) {
            wp_send_json_success(array(
                'template_id' => $template_id,
                'message' => 'Template saved successfully'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to save template'
            ));
        }
    }
    
    /**
     * AJAX handler for viewing generation details
     */
    public function ajax_view_generation() {
        check_ajax_referer('zippicks_master_critic_nonce', 'nonce');
        
        if (!current_user_can('use_zippicks_ai_generation')) {
            wp_die('Unauthorized');
        }
        
        $generation_id = intval($_POST['generation_id']);
        $generation = ZipPicks_Master_Critic_Database::get_generation($generation_id);
        
        if (!$generation) {
            wp_send_json_error(array(
                'message' => 'Generation not found'
            ));
            return;
        }
        
        wp_send_json_success($generation);
    }
    
    /**
     * AJAX handler for getting template details
     */
    public function ajax_get_template() {
        check_ajax_referer('zippicks_master_critic_nonce', 'nonce');
        
        if (!current_user_can('manage_zippicks_prompt_templates')) {
            wp_die('Unauthorized');
        }
        
        $template_id = intval($_POST['template_id']);
        $template = ZipPicks_Master_Critic_Database::get_template($template_id);
        
        if (!$template) {
            wp_send_json_error(array(
                'message' => 'Template not found'
            ));
            return;
        }
        
        wp_send_json_success($template);
    }
    
    /**
     * AJAX handler for updating template
     */
    public function ajax_update_template() {
        check_ajax_referer('zippicks_master_critic_nonce', 'nonce');
        
        if (!current_user_can('manage_zippicks_prompt_templates')) {
            wp_die('Unauthorized');
        }
        
        $template_id = intval($_POST['template_id']);
        
        $updated = ZipPicks_Master_Critic_Database::update_template($template_id, array(
            'name' => sanitize_text_field($_POST['name']),
            'business_category' => sanitize_text_field($_POST['business_category']),
            'prompt_template' => wp_unslash($_POST['prompt_template']),
            'is_default' => !empty($_POST['is_default'])
        ));
        
        if ($updated) {
            wp_send_json_success(array(
                'message' => 'Template updated successfully'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to update template'
            ));
        }
    }
    
    /**
     * AJAX handler for deleting template
     */
    public function ajax_delete_template() {
        check_ajax_referer('zippicks_master_critic_nonce', 'nonce');
        
        if (!current_user_can('manage_zippicks_prompt_templates')) {
            wp_die('Unauthorized');
        }
        
        $template_id = intval($_POST['template_id']);
        
        if (ZipPicks_Master_Critic_Database::delete_template($template_id)) {
            wp_send_json_success(array(
                'message' => 'Template deleted successfully'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to delete template'
            ));
        }
    }
    
    /**
     * AJAX handler for creating businesses
     */
    public function ajax_create_businesses() {
        check_ajax_referer('zippicks_master_critic_nonce', 'nonce');
        
        if (!current_user_can('use_zippicks_ai_generation')) {
            wp_die('Unauthorized');
        }
        
        $generation_id = intval($_POST['generation_id']);
        $businesses = json_decode(stripslashes($_POST['businesses']), true);
        
        if (!is_array($businesses)) {
            wp_send_json_error(array('message' => 'Invalid business data'));
            return;
        }
        
        // Apply scoring engine to ensure businesses have proper scores
        if (!empty($businesses)) {
            $engine = new ZP_MasterCritic_ScoringEngine();
            $scored_businesses = $engine->calculate_scores($businesses);
            $businesses = $scored_businesses;
        }
        
        // Check if Business plugin is available
        if (!function_exists('zippicks') || !zippicks()->has('business.manager')) {
            wp_send_json_error(array(
                'message' => 'ZipPicks Business plugin is not active. Please activate it to create businesses.'
            ));
            return;
        }
        
        // Use Business plugin API to create businesses
        $business_manager = zippicks()->get('business.manager');
        $results = $business_manager->bulk_create_from_ai($businesses, array(
            'source' => 'master_critic',
            'generation_id' => $generation_id,
            'ai_provider' => sanitize_text_field($_POST['ai_provider']),
            'business_category' => sanitize_text_field($_POST['business_category'])
        ));
        
        // Update generation record
        if ($generation_id && $results['created_count'] > 0) {
            ZipPicks_Master_Critic_Database::update_generation($generation_id, array(
                'businesses_created' => $results['created_count']
            ));
        }
        
        wp_send_json_success(array(
            'created' => $results['created_count'],
            'business_ids' => $results['business_ids'],
            'message' => sprintf('%d businesses created successfully', $results['created_count'])
        ));
    }
    
    /**
     * AJAX handler for creating a list
     */
    public function ajax_create_list() {
        check_ajax_referer('zippicks_master_critic_nonce', 'nonce');
        
        if (!current_user_can('use_zippicks_ai_generation')) {
            wp_die('Unauthorized');
        }
        
        // Get data from request
        $generation_id = intval($_POST['generation_id']);
        $businesses = json_decode(stripslashes($_POST['businesses']), true);
        $topic = sanitize_text_field($_POST['topic']);
        $location = sanitize_text_field($_POST['location']);
        $business_category = sanitize_text_field($_POST['business_category']);
        $ai_provider = sanitize_text_field($_POST['ai_provider']);
        
        if (!is_array($businesses) || empty($businesses)) {
            wp_send_json_error(array('message' => 'No businesses to add to list'));
            return;
        }
        
        // Apply scoring engine to ensure businesses have proper scores
        if (!empty($businesses)) {
            $engine = new ZP_MasterCritic_ScoringEngine();
            $scored_businesses = $engine->calculate_scores($businesses);
            $businesses = $scored_businesses;
        }
        
        // Check if Business plugin is available
        if (!function_exists('zippicks') || !zippicks()->has('business.manager')) {
            wp_send_json_error(array(
                'message' => 'Cannot create list without ZipPicks Business plugin active.'
            ));
            return;
        }
        
        // Get category configuration
        $categories = get_option('zippicks_business_categories');
        $category_config = isset($categories[$business_category]) ? $categories[$business_category] : $categories['custom'];
        
        // Create list title and content
        $list_title = sprintf('Top %s in %s', ucfirst($topic), $location);
        $list_content = sprintf(
            '<p>Our AI-curated list of the best %s in %s, powered by %s.</p>',
            $topic,
            $location,
            $ai_provider === 'anthropic' ? 'Claude' : 'GPT-4'
        );
        
        // Add businesses to content
        $list_content .= '<div class="master-critic-list">';
        foreach ($businesses as $index => $business) {
            $rank = $index + 1;
            $list_content .= sprintf(
                '<div class="list-item" data-rank="%d">
                    <h3>#%d - %s</h3>
                    <div class="score">Score: %s</div>
                    <div class="price">%s • %d reviews</div>
                    <p>%s</p>
                    <div class="vibes">%s</div>
                </div>',
                $rank,
                $rank,
                esc_html($business['name']),
                esc_html($business['score']),
                esc_html($business['price_tier']),
                intval($business['review_count']),
                esc_html($business['summary']),
                implode(', ', array_map('esc_html', $business['vibes']))
            );
        }
        $list_content .= '</div>';
        
        // Extract all vibe IDs from businesses
        $all_vibe_ids = array();
        foreach ($businesses as $business) {
            if (!empty($business['vibe_ids'])) {
                $all_vibe_ids = array_merge($all_vibe_ids, $business['vibe_ids']);
            }
        }
        $unique_vibe_ids = array_unique($all_vibe_ids);
        
        // Get list category from POST data, defaulting to 'best_overall'
        $list_category = sanitize_text_field($_POST['list_category'] ?? 'best_overall');
        
        // Create the list post
        $list_data = array(
            'post_title' => $list_title,
            'post_content' => $list_content,
            'post_type' => 'master_critic_list',
            'post_status' => 'publish',
            'meta_input' => array(
                // Slug fields for routing
                'city_slug' => sanitize_title($location),
                'dish_slug' => sanitize_title($topic),
                
                // Display fields
                '_mc_topic' => $topic,
                '_mc_location' => $location,
                '_mc_category' => $business_category,
                '_mc_restaurants' => wp_json_encode($businesses),
                '_mc_ai_provider' => $ai_provider,
                '_mc_generation_id' => $generation_id,
                '_mc_count' => count($businesses),
                '_mc_list_category' => $list_category,
                '_mc_vibe_ids' => $unique_vibe_ids
            )
        );
        
        $list_id = wp_insert_post($list_data);
        
        if (is_wp_error($list_id)) {
            wp_send_json_error(array(
                'message' => 'Failed to create list: ' . $list_id->get_error_message()
            ));
            return;
        }
        
        // Update generation record with list ID
        if ($generation_id) {
            ZipPicks_Master_Critic_Database::update_generation($generation_id, array(
                'list_id' => $list_id
            ));
        }
        
        // Fire action for other plugins
        do_action('zippicks_list_created', $list_id, array(
            'source' => 'master_critic',
            'generation_id' => $generation_id,
            'business_count' => count($businesses),
            'topic' => $topic,
            'location' => $location,
            'category' => $business_category
        ));
        
        wp_send_json_success(array(
            'list_id' => $list_id,
            'list_url' => get_permalink($list_id),
            'edit_url' => get_edit_post_link($list_id, 'raw'),
            'message' => sprintf('List "%s" created successfully', $list_title)
        ));
    }
    
    /**
     * Add vibe selection metabox
     */
    public function add_vibe_selection_metabox() {
        add_meta_box(
            'zippicks_vibe_associations',
            'Vibe Associations',
            array($this, 'render_vibe_selection_metabox'),
            'master_critic_list',
            'side',
            'default'
        );
    }
    
    /**
     * Render vibe selection metabox
     * 
     * @param WP_Post $post The current post object
     */
    public function render_vibe_selection_metabox($post) {
        // Add nonce for security
        wp_nonce_field('zippicks_save_vibe_associations', 'zippicks_vibe_nonce');
        
        // Get current vibe associations
        $current_vibes = get_post_meta($post->ID, '_mc_vibe_ids', false);
        if (!is_array($current_vibes)) {
            $current_vibes = [];
        }
        
        // Flatten array if needed
        $flat_vibes = [];
        foreach ($current_vibes as $vibe) {
            if (is_array($vibe)) {
                $flat_vibes = array_merge($flat_vibes, $vibe);
            } else {
                $flat_vibes[] = $vibe;
            }
        }
        $current_vibes = array_unique(array_map('absint', $flat_vibes));
        
        // Check if Vibes plugin is active
        $vibes_active = class_exists('ZipPicksVibes\\VibesPlugin') || 
                       function_exists('zippicks') && zippicks()->has('vibes.service');
        
        if (!$vibes_active) {
            echo '<p>The Vibes plugin must be active to associate vibes with this list.</p>';
            return;
        }
        
        // Get all available vibes
        $vibes = [];
        if (function_exists('zippicks') && zippicks()->has('vibes.service')) {
            try {
                $vibe_service = zippicks()->get('vibes.service');
                $vibes = $vibe_service->getAll();
            } catch (Exception $e) {
                error_log('Failed to get vibes: ' . $e->getMessage());
            }
        }
        
        if (empty($vibes)) {
            echo '<p>No vibes available. Please create vibes first.</p>';
            return;
        }
        
        ?>
        <div class="zippicks-vibe-selection">
            <p class="description">Select vibes that match this Top 10 list's theme:</p>
            
            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-top: 10px;">
                <?php foreach ($vibes as $vibe): ?>
                    <?php 
                    $vibe_id = method_exists($vibe, 'getId') ? $vibe->getId() : 0;
                    $vibe_name = method_exists($vibe, 'getName') ? $vibe->getName() : '';
                    
                    if (!$vibe_id) continue;
                    ?>
                    <label style="display: block; margin-bottom: 5px;">
                        <input type="checkbox" 
                               name="zippicks_vibe_ids[]" 
                               value="<?php echo esc_attr($vibe_id); ?>"
                               <?php checked(in_array($vibe_id, $current_vibes)); ?> />
                        <?php echo esc_html($vibe_name); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <p class="description" style="margin-top: 10px;">
                <em>Selected vibes will display this list on their respective pages.</em>
            </p>
        </div>
        <?php
    }
    
    /**
     * Save vibe associations when post is saved
     * 
     * @param int $post_id The post ID
     * @param WP_Post $post The post object
     */
    public function save_vibe_associations($post_id, $post) {
        // Check nonce
        if (!isset($_POST['zippicks_vibe_nonce']) || 
            !wp_verify_nonce($_POST['zippicks_vibe_nonce'], 'zippicks_save_vibe_associations')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Get the integration service
        if (!function_exists('zippicks') || !zippicks()->has('list_vibe.integration')) {
            return;
        }
        
        $integration = zippicks()->get('list_vibe.integration');
        
        // Get submitted vibe IDs
        $vibe_ids = isset($_POST['zippicks_vibe_ids']) ? $_POST['zippicks_vibe_ids'] : [];
        
        // Assign vibes to list
        $integration->assign_vibes_to_list($post_id, $vibe_ids);
    }
    
    /**
     * Parse location string to extract city and state
     *
     * @param string $location Location string (e.g., "Los Angeles, CA" or "NYC")
     * @return array Array with 'city' and 'state' keys
     */
    private function parse_location($location) {
        // Common city abbreviations to full names
        $city_abbreviations = [
            'NYC' => 'New York',
            'LA' => 'Los Angeles',
            'SF' => 'San Francisco',
            'DC' => 'Washington',
            'NOLA' => 'New Orleans',
            'Vegas' => 'Las Vegas',
            'Philly' => 'Philadelphia'
        ];
        
        // Check if it's a known abbreviation
        $location = trim($location);
        if (isset($city_abbreviations[$location])) {
            $city = $city_abbreviations[$location];
            // Default states for common cities
            $default_states = [
                'New York' => 'NY',
                'Los Angeles' => 'CA',
                'San Francisco' => 'CA',
                'Washington' => 'DC',
                'New Orleans' => 'LA',
                'Las Vegas' => 'NV',
                'Philadelphia' => 'PA'
            ];
            $state = $default_states[$city] ?? '';
        } else {
            // Try to parse "City, State" format
            if (strpos($location, ',') !== false) {
                list($city, $state) = array_map('trim', explode(',', $location, 2));
                // Handle full state names by converting to abbreviations
                $state = $this->get_state_abbreviation($state);
            } else {
                // Just a city name, try to guess the state
                $city = $location;
                $state = $this->guess_state_for_city($city);
            }
        }
        
        return [
            'city' => $city,
            'state' => $state
        ];
    }
    
    /**
     * Get state abbreviation from full name or abbreviation
     *
     * @param string $state State name or abbreviation
     * @return string Two-letter state abbreviation
     */
    private function get_state_abbreviation($state) {
        $states = [
            'Alabama' => 'AL', 'Alaska' => 'AK', 'Arizona' => 'AZ', 'Arkansas' => 'AR',
            'California' => 'CA', 'Colorado' => 'CO', 'Connecticut' => 'CT', 'Delaware' => 'DE',
            'Florida' => 'FL', 'Georgia' => 'GA', 'Hawaii' => 'HI', 'Idaho' => 'ID',
            'Illinois' => 'IL', 'Indiana' => 'IN', 'Iowa' => 'IA', 'Kansas' => 'KS',
            'Kentucky' => 'KY', 'Louisiana' => 'LA', 'Maine' => 'ME', 'Maryland' => 'MD',
            'Massachusetts' => 'MA', 'Michigan' => 'MI', 'Minnesota' => 'MN', 'Mississippi' => 'MS',
            'Missouri' => 'MO', 'Montana' => 'MT', 'Nebraska' => 'NE', 'Nevada' => 'NV',
            'New Hampshire' => 'NH', 'New Jersey' => 'NJ', 'New Mexico' => 'NM', 'New York' => 'NY',
            'North Carolina' => 'NC', 'North Dakota' => 'ND', 'Ohio' => 'OH', 'Oklahoma' => 'OK',
            'Oregon' => 'OR', 'Pennsylvania' => 'PA', 'Rhode Island' => 'RI', 'South Carolina' => 'SC',
            'South Dakota' => 'SD', 'Tennessee' => 'TN', 'Texas' => 'TX', 'Utah' => 'UT',
            'Vermont' => 'VT', 'Virginia' => 'VA', 'Washington' => 'WA', 'West Virginia' => 'WV',
            'Wisconsin' => 'WI', 'Wyoming' => 'WY', 'District of Columbia' => 'DC'
        ];
        
        // If already an abbreviation, return uppercase
        if (strlen($state) === 2) {
            return strtoupper($state);
        }
        
        // Look up full state name
        foreach ($states as $full => $abbr) {
            if (strcasecmp($full, $state) === 0) {
                return $abbr;
            }
        }
        
        return '';
    }
    
    /**
     * Guess state for major cities
     *
     * @param string $city City name
     * @return string State abbreviation or empty string
     */
    private function guess_state_for_city($city) {
        $major_cities = [
            'New York' => 'NY', 'Los Angeles' => 'CA', 'Chicago' => 'IL', 'Houston' => 'TX',
            'Phoenix' => 'AZ', 'Philadelphia' => 'PA', 'San Antonio' => 'TX', 'San Diego' => 'CA',
            'Dallas' => 'TX', 'San Jose' => 'CA', 'Austin' => 'TX', 'Jacksonville' => 'FL',
            'Fort Worth' => 'TX', 'Columbus' => 'OH', 'Charlotte' => 'NC', 'San Francisco' => 'CA',
            'Indianapolis' => 'IN', 'Seattle' => 'WA', 'Denver' => 'CO', 'Washington' => 'DC',
            'Boston' => 'MA', 'El Paso' => 'TX', 'Nashville' => 'TN', 'Detroit' => 'MI',
            'Oklahoma City' => 'OK', 'Portland' => 'OR', 'Las Vegas' => 'NV', 'Memphis' => 'TN',
            'Louisville' => 'KY', 'Baltimore' => 'MD', 'Milwaukee' => 'WI', 'Albuquerque' => 'NM',
            'Tucson' => 'AZ', 'Fresno' => 'CA', 'Mesa' => 'AZ', 'Sacramento' => 'CA',
            'Atlanta' => 'GA', 'Kansas City' => 'MO', 'Colorado Springs' => 'CO', 'Omaha' => 'NE',
            'Raleigh' => 'NC', 'Miami' => 'FL', 'Long Beach' => 'CA', 'Virginia Beach' => 'VA',
            'Oakland' => 'CA', 'Minneapolis' => 'MN', 'Tulsa' => 'OK', 'Tampa' => 'FL',
            'Arlington' => 'TX', 'New Orleans' => 'LA'
        ];
        
        return $major_cities[$city] ?? '';
    }
    
    /**
     * Execute unverified generation (fallback when API is unavailable)
     *
     * @param array $params Generation parameters
     * @param string $provider AI provider
     * @param string $prompt The prompt to use
     * @return array Generation result
     */
    private function execute_unverified_generation($params, $provider, $prompt) {
        // Use enhanced generation if available
        $use_enhanced = apply_filters('zippicks_use_enhanced_generation', true);
        
        if ($use_enhanced) {
            $result = $this->ai_service->execute_enhanced_generation($params);
        } else {
            $result = $this->ai_service->execute_ai_generation($prompt, $provider);
        }
        
        // Mark as unverified
        if ($result['success']) {
            $result['api_verification_status'] = 'unverified';
            $result['verified_count'] = 0;
        }
        
        return $result;
    }
    
    /**
     * AJAX handler for testing ZipBusiness API connection
     */
    public function ajax_test_zipbusiness_api() {
        
        // Try to verify nonce, but don't fail if it's missing (settings page issue)
        $nonce_valid = false;
        if (!empty($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'zippicks_master_critic_nonce');
        }
        
        // For settings page, also check for settings nonce
        if (!$nonce_valid && !empty($_POST['_wpnonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['_wpnonce'], 'zippicks-master-critic-settings');
        }
        
        // If still no valid nonce but user can manage options, allow it
        if (!$nonce_valid && !current_user_can('manage_options')) {
            wp_die('Unauthorized - Invalid security token');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized - Insufficient permissions');
        }
        
        // Load the API client
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/services/class-zipbusiness-api-client.php';
        $api_client = new ZipPicks_Master_Critic_ZipBusiness_API_Client();
        
        // First check basic API status
        $status = $api_client->get_api_status();
        
        if (!$status['connected']) {
            wp_send_json_error(array(
                'message' => $status['error'] ?? 'Cannot connect to API',
                'note' => $status['note'] ?? null,
                'api_verification' => $status['api_verification'] ?? 'unknown'
            ));
            return;
        }
        
        // Check if API verification is enabled (default to true for quality)
        $api_verification_enabled = get_option('zippicks_enable_api_verification', true);
        
        // If connected, prepare test results
        $test_results = array(
            'version' => $status['version'] ?? 'Unknown',
            'api_status' => $status['status'] ?? 'Unknown',
            'api_verification' => $api_verification_enabled ? 'enabled' : 'disabled',
            'health_check' => 'passed'
        );
        
        // If API verification is disabled, just return health check results
        if (!$api_verification_enabled) {
            $test_results['message'] = 'API health check passed. Restaurant verification is currently disabled.';
            $test_results['note'] = 'Enable API verification in settings to validate restaurant data.';
            wp_send_json_success($test_results);
            return;
        }
        
        // If API verification is enabled, try to fetch test data
        $test_city = 'San Francisco';
        $test_state = 'CA';
        $test_results['test_city'] = $test_city . ', ' . $test_state;
        $test_results['restaurant_count'] = 0;
        
        try {
            // Attempt to fetch restaurants from the test city
            $restaurants = $api_client->get_city_restaurants($test_city, $test_state);
            
            if (is_array($restaurants)) {
                $test_results['restaurant_count'] = count($restaurants);
                
                if (!empty($restaurants)) {
                    // Sample restaurant for validation
                    $first_restaurant = reset($restaurants);
                    $test_results['sample_restaurant'] = [
                        'name' => $first_restaurant['name'] ?? 'Unknown',
                        'zpid' => $first_restaurant['zpid'] ?? 'Unknown'
                    ];
                    
                    $test_results['message'] = 'API connection successful! Found ' . count($restaurants) . ' restaurants.';
                } else {
                    $test_results['message'] = 'API connection successful but no restaurants found for test city.';
                }
            } else {
                $test_results['message'] = 'API connection successful but returned unexpected data format.';
            }
            
            wp_send_json_success($test_results);
            
        } catch (Exception $e) {
            // Even if restaurant endpoint fails, health check passed
            $test_results['restaurant_error'] = $e->getMessage();
            $test_results['message'] = 'Health check passed but restaurant data is unavailable.';
            $test_results['note'] = 'The ZipBusiness API server appears to be experiencing issues with restaurant data. You can disable API verification to continue using the Master Critic.';
            
            wp_send_json_success($test_results);
        }
    }
}