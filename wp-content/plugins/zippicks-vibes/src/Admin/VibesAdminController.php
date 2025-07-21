<?php
/**
 * Vibes Admin Controller for ZipPicks Vibes
 * 
 * Provides comprehensive admin interface with security-first architecture
 * Migrated from v1 with enhanced security and Foundation integration
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Admin;

use Exception;
use ZipPicksVibes\Services\VibeService;
use ZipPicksVibes\Services\ScrapeProtection;
use ZipPicksVibes\Exceptions\VibeNotFoundException;

/**
 * Admin Controller Class
 */
class VibesAdminController {
    
    /**
     * Vibe service instance
     * 
     * @var VibeService
     */
    private $vibe_service;
    
    /**
     * Scrape protection service
     * 
     * @var ScrapeProtection|null
     */
    private $protection;
    
    /**
     * Flag to prevent multiple initializations
     * 
     * @var bool
     */
    private static $initialized = false;
    
    /**
     * Flag to prevent multiple menu registrations
     * 
     * @var bool
     */
    private static $menu_registered = false;
    
    /**
     * Constructor
     */
    public function __construct($vibe_service, $protection) {
        $this->vibe_service = $vibe_service;
        $this->protection = $protection;
    }
    
    /**
     * Initialize admin interface with enhanced security and WordPress standards
     */
    public function init() {
        // Prevent multiple initializations
        if (self::$initialized) {
            return;
        }
        
        // Only in admin
        if (!is_admin()) {
            return;
        }
        
        self::$initialized = true;
        
        add_action('admin_menu', [$this, 'register_menu'], 10);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
        
        // AJAX handlers with enhanced security validation
        add_action('wp_ajax_zippicks_vibes_save', [$this, 'ajax_save_vibe']);
        add_action('wp_ajax_zippicks_vibes_delete', [$this, 'ajax_delete_vibe']);
        add_action('wp_ajax_zippicks_vibes_get', [$this, 'ajax_get_vibe']);
        add_action('wp_ajax_zippicks_vibes_bulk', [$this, 'ajax_bulk_action']);
        add_action('wp_ajax_zippicks_vibes_toggle_status', [$this, 'ajax_toggle_status']);
        add_action('wp_ajax_zippicks_vibes_reorder', [$this, 'ajax_reorder_vibes']);
        add_action('wp_ajax_zippicks_vibes_categories', [$this, 'ajax_get_categories']);
        add_action('wp_ajax_zippicks_vibes_save_category', [$this, 'ajax_save_category']);
        add_action('wp_ajax_zippicks_vibes_delete_category', [$this, 'ajax_delete_category']);
        
        // Icon management AJAX handlers
        add_action('wp_ajax_zippicks_get_icons', [$this, 'ajax_get_icons']);
        add_action('wp_ajax_zippicks_upload_icon', [$this, 'ajax_upload_icon']);
        add_action('wp_ajax_zippicks_delete_icon', [$this, 'ajax_delete_icon']);
        
        // Category filtering AJAX handlers (public access)
        add_action('wp_ajax_zippicks_vibes_filter_by_category', [$this, 'ajax_filter_by_category']);
        add_action('wp_ajax_nopriv_zippicks_vibes_filter_by_category', [$this, 'ajax_filter_by_category']);
        
        // WordPress action for settings
        add_action('admin_action_zippicks_vibes_reset_settings', array($this, 'reset_settings'));
        
        // Add extension hooks for other plugins
        do_action('zippicks_vibes_admin_init', $this);
    }
    
    /**
     * Register WordPress settings with enhanced validation and security
     */
    public function register_settings() {
        // Main settings group
        register_setting(
            'zippicks_vibes_options',
            'zippicks_vibes_settings',
            [
                'type' => 'array',
                'description' => __('ZipPicks Vibes Settings', 'zippicks-vibes'),
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'show_in_rest' => false, // Security: prevent REST API exposure
                'default' => $this->get_default_settings()
            ]
        );
        
        // Security settings section
        add_settings_section(
            'zippicks_vibes_security',
            __('Security Settings', 'zippicks-vibes'),
            [$this, 'security_section_callback'],
            'zippicks_vibes_options'
        );
        
        // Rate limiting setting
        add_settings_field(
            'rate_limit_enabled',
            __('Enable Rate Limiting', 'zippicks-vibes'),
            [$this, 'rate_limit_field_callback'],
            'zippicks_vibes_options',
            'zippicks_vibes_security'
        );
        
        // Audit logging setting
        add_settings_field(
            'audit_logging',
            __('Enable Audit Logging', 'zippicks-vibes'),
            [$this, 'audit_logging_field_callback'],
            'zippicks_vibes_options',
            'zippicks_vibes_security'
        );
        
        // Performance settings section
        add_settings_section(
            'zippicks_vibes_performance',
            __('Performance Settings', 'zippicks-vibes'),
            array($this, 'performance_section_callback'),
            'zippicks_vibes_options'
        );
        
        // Cache duration setting
        add_settings_field(
            'cache_duration',
            __('Cache Duration (seconds)', 'zippicks-vibes'),
            array($this, 'cache_duration_field_callback'),
            'zippicks_vibes_options',
            'zippicks_vibes_performance'
        );
        
        // Max vibes per page setting
        add_settings_field(
            'max_vibes_per_page',
            __('Max Vibes Per Page', 'zippicks-vibes'),
            array($this, 'max_vibes_field_callback'),
            'zippicks_vibes_options',
            'zippicks_vibes_performance'
        );
        
        // Features settings section
        add_settings_section(
            'zippicks_vibes_features',
            __('Feature Settings', 'zippicks-vibes'),
            array($this, 'features_section_callback'),
            'zippicks_vibes_options'
        );
        
        // Bulk operations setting
        add_settings_field(
            'enable_bulk_operations',
            __('Enable Bulk Operations', 'zippicks-vibes'),
            array($this, 'bulk_operations_field_callback'),
            'zippicks_vibes_options',
            'zippicks_vibes_features'
        );
        
        // Allow other plugins to register additional settings
        do_action('zippicks_vibes_register_settings', $this);
    }
    
    /**
     * Get default settings
     */
    private function get_default_settings(): array {
        return apply_filters('zippicks_vibes_default_settings', [
            'rate_limit_enabled' => true,
            'audit_logging' => true,
            'max_vibes_per_page' => 50,
            'enable_bulk_operations' => true,
            'cache_duration' => 3600
        ]);
    }
    
    /**
     * Security section callback
     */
    public function security_section_callback() {
        echo '<p>' . esc_html__('Configure security settings for the Vibes admin interface.', 'zippicks-vibes') . '</p>';
    }
    
    /**
     * Rate limit field callback
     */
    public function rate_limit_field_callback() {
        $settings = get_option('zippicks_vibes_settings', $this->get_default_settings());
        $checked = checked($settings['rate_limit_enabled'] ?? true, true, false);
        echo "<input type='checkbox' name='zippicks_vibes_settings[rate_limit_enabled]' value='1' {$checked} />";
        echo '<p class="description">' . esc_html__('Enable rate limiting for admin AJAX requests.', 'zippicks-vibes') . '</p>';
    }
    
    /**
     * Audit logging field callback
     */
    public function audit_logging_field_callback() {
        $settings = get_option('zippicks_vibes_settings', $this->get_default_settings());
        $checked = checked($settings['audit_logging'] ?? true, true, false);
        echo "<input type='checkbox' name='zippicks_vibes_settings[audit_logging]' value='1' {$checked} />";
        echo '<p class="description">' . esc_html__('Log all administrative actions for security auditing.', 'zippicks-vibes') . '</p>';
    }
    
    /**
     * Performance section callback
     */
    public function performance_section_callback() {
        echo '<p>' . esc_html__('Configure performance and caching settings for optimal admin interface performance.', 'zippicks-vibes') . '</p>';
    }
    
    /**
     * Cache duration field callback
     */
    public function cache_duration_field_callback() {
        $settings = get_option('zippicks_vibes_settings', $this->get_default_settings());
        $value = intval($settings['cache_duration'] ?? 3600);
        echo "<input type='number' name='zippicks_vibes_settings[cache_duration]' value='{$value}' min='300' max='86400' step='60' />";
        echo '<p class="description">' . esc_html__('How long to cache data in seconds (300-86400).', 'zippicks-vibes') . '</p>';
    }
    
    /**
     * Max vibes field callback
     */
    public function max_vibes_field_callback() {
        $settings = get_option('zippicks_vibes_settings', $this->get_default_settings());
        $value = intval($settings['max_vibes_per_page'] ?? 50);
        echo "<input type='number' name='zippicks_vibes_settings[max_vibes_per_page]' value='{$value}' min='10' max='200' step='10' />";
        echo '<p class="description">' . esc_html__('Maximum number of vibes to display per page (10-200).', 'zippicks-vibes') . '</p>';
    }
    
    /**
     * Features section callback
     */
    public function features_section_callback() {
        echo '<p>' . esc_html__('Enable or disable specific features of the Vibes admin interface.', 'zippicks-vibes') . '</p>';
    }
    
    /**
     * Bulk operations field callback
     */
    public function bulk_operations_field_callback() {
        $settings = get_option('zippicks_vibes_settings', $this->get_default_settings());
        $checked = checked($settings['enable_bulk_operations'] ?? true, true, false);
        echo "<input type='checkbox' name='zippicks_vibes_settings[enable_bulk_operations]' value='1' {$checked} />";
        echo '<p class="description">' . esc_html__('Allow bulk actions like delete, activate, and deactivate multiple vibes at once.', 'zippicks-vibes') . '</p>';
    }
    
    /**
     * Sanitize settings input with enhanced validation
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        $defaults = $this->get_default_settings();
        
        if (!is_array($input)) {
            add_settings_error(
                'zippicks_vibes_settings',
                'invalid_input',
                __('Invalid settings format provided.', 'zippicks-vibes'),
                'error'
            );
            return $defaults;
        }
        
        // Sanitize each setting with appropriate validation
        foreach ($defaults as $key => $default_value) {
            if (isset($input[$key])) {
                switch ($key) {
                    case 'rate_limit_enabled':
                    case 'audit_logging':
                    case 'enable_bulk_operations':
                        $sanitized[$key] = !empty($input[$key]);
                        break;
                    
                    case 'max_vibes_per_page':
                        $value = intval($input[$key]);
                        $sanitized[$key] = max(10, min(200, $value)); // Limit between 10-200
                        break;
                    
                    case 'cache_duration':
                        $value = intval($input[$key]);
                        $sanitized[$key] = max(300, min(86400, $value)); // 5 minutes to 24 hours
                        break;
                    
                    default:
                        $sanitized[$key] = sanitize_text_field($input[$key]);
                }
            } else {
                $sanitized[$key] = $default_value;
            }
        }
        
        // Add success message
        add_settings_error(
            'zippicks_vibes_settings',
            'settings_saved',
            __('Settings saved successfully.', 'zippicks-vibes'),
            'success'
        );
        
        return apply_filters('zippicks_vibes_sanitize_settings', $sanitized, $input);
    }
    
    /**
     * Reset settings to defaults (WordPress action handler)
     */
    public function reset_settings() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'zippicks-vibes'));
        }
        
        if (!wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'zippicks_vibes_reset_settings')) {
            wp_die(__('Security check failed', 'zippicks-vibes'));
        }
        
        // Reset to defaults
        $defaults = $this->get_default_settings();
        update_option('zippicks_vibes_settings', $defaults);
        
        // Add success notice
        add_settings_error(
            'zippicks_vibes_settings',
            'settings_reset',
            __('Settings have been reset to defaults.', 'zippicks-vibes'),
            'success'
        );
        
        // Redirect back
        wp_redirect(add_query_arg(
            array('page' => 'zippicks-vibes-security'),
            admin_url('admin.php')
        ));
        exit;
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Only show on our admin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'zippicks-vibes') === false) {
            return;
        }
        
        // Display any stored admin notices
        $notices = get_transient('zippicks_vibes_admin_notices_' . get_current_user_id());
        if ($notices) {
            foreach ($notices as $notice) {
                printf(
                    '<div class="notice notice-%s %s"><p>%s</p></div>',
                    esc_attr($notice['type']),
                    $notice['dismissible'] ? 'is-dismissible' : '',
                    wp_kses_post($notice['message'])
                );
            }
            delete_transient('zippicks_vibes_admin_notices_' . get_current_user_id());
        }
    }
    
    /**
     * Add admin notice
     */
    private function add_admin_notice($message, $type = 'info', $dismissible = true) {
        $notices = get_transient('zippicks_vibes_admin_notices_' . get_current_user_id()) ?: [];
        $notices[] = [
            'message' => $message,
            'type' => $type,
            'dismissible' => $dismissible
        ];
        set_transient('zippicks_vibes_admin_notices_' . get_current_user_id(), $notices, 300);
    }
    
    /**
     * Register admin menu
     */
    public function register_menu() {
        // Prevent duplicate menu registration
        if (self::$menu_registered) {
            return;
        }
        
        self::$menu_registered = true;
        
        add_menu_page(
            __('ZipPicks Vibes', 'zippicks-vibes'),
            __('Vibes', 'zippicks-vibes'),
            'manage_options',
            'zippicks-vibes',
            [$this, 'dashboard_page'],
            'dashicons-heart',
            30
        );
        
        // WordPress automatically creates the first submenu with the same slug as the parent
        // So we don't need to explicitly add "All Vibes" - it's created automatically
        
        add_submenu_page(
            'zippicks-vibes',
            __('Add New Vibe', 'zippicks-vibes'),
            __('Add New', 'zippicks-vibes'),
            'manage_options',
            'zippicks-vibes-add',
            [$this, 'add_edit_page']
        );
        
        add_submenu_page(
            'zippicks-vibes',
            __('Categories', 'zippicks-vibes'),
            __('Categories', 'zippicks-vibes'),
            'manage_options',
            'zippicks-vibes-categories',
            [$this, 'categories_page']
        );
        
        add_submenu_page(
            'zippicks-vibes',
            __('Icon Library', 'zippicks-vibes'),
            __('Icon Library', 'zippicks-vibes'),
            'manage_options',
            'zippicks-vibes-icons',
            [$this, 'display_icon_manager']
        );
        
        add_submenu_page(
            'zippicks-vibes',
            __('Security', 'zippicks-vibes'),
            __('Security', 'zippicks-vibes'),
            'manage_options',
            'zippicks-vibes-security',
            [$this, 'security_page']
        );
        
        add_submenu_page(
            'zippicks-vibes',
            __('Settings', 'zippicks-vibes'),
            __('Settings', 'zippicks-vibes'),
            'manage_options',
            'zippicks-vibes-settings',
            [$this, 'settings_page']
        );
    }
    
    /**
     * Enqueue admin assets with enhanced security, versioning, and extensibility
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'zippicks-vibes') === false) {
            return;
        }
        
        // Security check
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // WordPress core dependencies
        wp_enqueue_media();
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('wp-api');
        
        // Get file modification times for cache busting
        $css_path = ZIPPICKS_VIBES_DIR . 'src/Admin/Assets/css/vibes-admin.css';
        $js_path = ZIPPICKS_VIBES_DIR . 'src/Admin/Assets/js/vibes-admin.js';
        
        // Get the latest modification time from all CSS component files for proper cache busting
        $css_version = $this->get_assets_version($css_path, 'css');
        $js_version = file_exists($js_path) ? filemtime($js_path) : ZIPPICKS_VIBES_VERSION;
        
        // Admin CSS with proper versioning and dependencies
        wp_enqueue_style(
            'zippicks-vibes-admin',
            ZIPPICKS_VIBES_URL . 'src/Admin/Assets/css/vibes-admin.css',
            ['wp-color-picker', 'dashicons', 'wp-admin', 'common'],
            $css_version,
            'screen'
        );
        
        // Admin JS with proper versioning and dependencies
        wp_enqueue_script(
            'zippicks-vibes-admin',
            ZIPPICKS_VIBES_URL . 'src/Admin/Assets/js/vibes-admin.js',
            ['jquery', 'wp-color-picker', 'jquery-ui-sortable', 'jquery-ui-draggable', 'wp-api', 'wp-i18n'],
            $js_version,
            true
        );
        
        // Set script translations
        wp_set_script_translations('zippicks-vibes-admin', 'zippicks-vibes');
        
        // Get current settings for JS
        $settings = get_option('zippicks_vibes_settings', $this->get_default_settings());
        
        // Enhanced localized data with security tokens and configuration  
        $localize_data = apply_filters('zippicks_vibes_admin_localize_data', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('zippicks-vibes/v1/'),
            'nonce' => wp_create_nonce('zippicks_vibes_admin'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'settingsNonce' => wp_create_nonce('zippicks_vibes_settings'),
            'pluginUrl' => ZIPPICKS_VIBES_URL,
            'iconsUrl' => ZIPPICKS_VIBES_URL . 'assets/icons/vibes',
            'currentUser' => get_current_user_id(),
            'currentPage' => $hook,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'settings' => array(
                'maxVibesPerPage' => intval($settings['max_vibes_per_page'] ?? 50),
                'enableBulkOperations' => !empty($settings['enable_bulk_operations']),
                'rateLimitEnabled' => !empty($settings['rate_limit_enabled'])
            ),
            'capabilities' => array(
                'manage_vibes' => current_user_can('manage_options'),
                'edit_vibes' => current_user_can('manage_options'),
                'delete_vibes' => current_user_can('manage_options')
            ),
            'security' => array(
                'sessionTimeout' => 1800, // 30 minutes
                'maxRetries' => 3,
                'rateLimitDelay' => 1000 // 1 second
            ),
            'ui' => array(
                'animationDuration' => 300,
                'loadingDelay' => 100,
                'autoSaveInterval' => 30000, // 30 seconds
                'confirmDestructiveActions' => true
            ),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this vibe?', 'zippicks-vibes'),
                'confirmBulkDelete' => __('Are you sure you want to delete the selected vibes?', 'zippicks-vibes'),
                'saving' => __('Saving...', 'zippicks-vibes'),
                'saved' => __('Saved!', 'zippicks-vibes'),
                'loading' => __('Loading...', 'zippicks-vibes'),
                'error' => __('An error occurred. Please try again.', 'zippicks-vibes'),
                'unauthorized' => __('Security check failed', 'zippicks-vibes'),
                'networkError' => __('Network error. Please check your connection.', 'zippicks-vibes'),
                'validationError' => __('Please check your input and try again.', 'zippicks-vibes'),
                'sessionExpired' => __('Your session has expired. Please reload the page.', 'zippicks-vibes'),
                'tooManyRequests' => __('Too many requests. Please wait a moment.', 'zippicks-vibes'),
                'unsavedChanges' => __('You have unsaved changes. Are you sure you want to leave?', 'zippicks-vibes'),
                'autoSaveEnabled' => __('Auto-save enabled', 'zippicks-vibes'),
                'keyboardShortcuts' => __('Press Ctrl+S to save, Esc to cancel', 'zippicks-vibes'),
                'selectActionAndItems' => __('Please select an action and at least one item', 'zippicks-vibes'),
                'confirmBulkAction' => __('Are you sure you want to perform this bulk action?', 'zippicks-vibes'),
                'bulkActionCompleted' => __('Bulk action completed successfully', 'zippicks-vibes')
            )
        ));
        
        wp_localize_script('zippicks-vibes-admin', 'zippicksVibesAdmin', $localize_data);
        
        // Add inline CSS for critical loading states
        wp_add_inline_style('zippicks-vibes-admin', $this->get_critical_admin_styles());
        
        // Add extension hooks for other plugins to enqueue additional assets
        do_action('zippicks_vibes_admin_enqueue_scripts', $hook, $this);
    }
    
    /**
     * Get critical admin styles that must be inline for proper functionality
     */
    private function get_critical_admin_styles() {
        return '
        .zippicks-vibes-admin .is-loading {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }
        
        .zippicks-vibes-admin .is-loading::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--wp-admin-theme-color, #0073aa);
            border-radius: 50%;
            animation: zp-spin 1s linear infinite;
            z-index: 1000;
        }
        
        @keyframes zp-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .zippicks-vibes-admin .sr-only {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }
        
        .zippicks-vibes-admin .zp-error {
            color: #d63638;
            background-color: #fcf0f1;
            border-left: 4px solid #d63638;
            padding: 8px 12px;
            margin: 8px 0;
        }
        
        .zippicks-vibes-admin .zp-success {
            color: #00a32a;
            background-color: #f0f8f0;
            border-left: 4px solid #00a32a;
            padding: 8px 12px;
            margin: 8px 0;
        }
        
        @media (prefers-reduced-motion: reduce) {
            .zippicks-vibes-admin .is-loading::before {
                animation: none;
            }
        }
        
        @media (max-width: 782px) {
            .zippicks-vibes-admin .widefat td,
            .zippicks-vibes-admin .widefat th {
                padding: 8px 6px;
            }
        }
        ';
    }
    
    /**
     * Dashboard page with enhanced data and extensibility
     */
    public function dashboard_page() {
        // Security validation
        if ($this->protection && method_exists($this->protection, 'validateRequest') && !$this->protection->validateRequest()) {
            wp_die(__('Security check failed', 'zippicks-vibes'));
        }
        
        // Get current page from request
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $settings = get_option('zippicks_vibes_settings', $this->get_default_settings());
        $per_page = intval($settings['max_vibes_per_page'] ?? 50);
        
        // Get stats using available methods
        // Pass 'status' => 'all' to get all vibes, not just active ones
        $all_vibes = $this->vibe_service->getAllVibes(['status' => 'all']);
        $categories = $this->vibe_service->getAllCategories();
        
        $stats = [
            'total_vibes' => count($all_vibes),
            'total_categories' => count($categories),
            'active_vibes' => count(array_filter($all_vibes, function($vibe) {
                return $vibe->isActive();
            })),
            'inactive_vibes' => count(array_filter($all_vibes, function($vibe) {
                return !$vibe->isActive();
            }))
        ];
        
        // Get paginated vibes for admin display - show all vibes in admin
        // Explicitly order by order_position to ensure correct display order
        $vibes_result = $this->vibe_service->getVibesPaginated($current_page, $per_page, [
            'status' => 'all',
            'orderby' => 'order_position',
            'order' => 'ASC'
        ]);
        $vibes = $vibes_result->getItems();
        $total_pages = $vibes_result->getTotalPages();
        
        // Prepare data for template
        $template_data = apply_filters('zippicks_vibes_dashboard_data', [
            'stats' => $stats,
            'vibes' => $vibes,
            'categories' => $categories,
            'pagination' => [
                'current_page' => $current_page,
                'total_pages' => $total_pages,
                'per_page' => $per_page,
                'total_items' => $stats['total_vibes']
            ],
            'settings' => $settings,
            'nonce' => wp_create_nonce('zippicks_vibes_dashboard'),
            'page_url' => admin_url('admin.php?page=zippicks-vibes'),
            'capabilities' => [
                'can_add' => current_user_can('manage_options'),
                'can_edit' => current_user_can('manage_options'),
                'can_delete' => current_user_can('manage_options'),
                'can_bulk_edit' => current_user_can('manage_options') && !empty($settings['enable_bulk_operations'])
            ]
        ]);
        
        // Allow pre-render hooks
        do_action('zippicks_vibes_before_dashboard_render', $template_data);
        
        $this->render_template('dashboard', $template_data);
        
        // Allow post-render hooks
        do_action('zippicks_vibes_after_dashboard_render', $template_data);
    }
    
    /**
     * Add/Edit page
     */
    public function add_edit_page() {
        // Security validation
        if ($this->protection && method_exists($this->protection, 'validateRequest') && !$this->protection->validateRequest()) {
            wp_die(__('Security check failed', 'zippicks-vibes'));
        }
        
        $editing = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $vibe = null;
        $categories = $this->vibe_service->getAllCategories();
        
        if ($editing) {
            try {
                $vibe = $this->vibe_service->getVibe($editing);
            } catch (\ZipPicksVibes\Exceptions\VibeNotFoundException $e) {
                // Handle vibe not found error gracefully
                wp_die(
                    sprintf(
                        __('Vibe not found: The vibe with ID %d does not exist. <a href="%s">Return to Vibes list</a>', 'zippicks-vibes'),
                        $editing,
                        admin_url('admin.php?page=zippicks-vibes')
                    ),
                    __('Vibe Not Found', 'zippicks-vibes'),
                    [
                        'response' => 404,
                        'back_link' => true
                    ]
                );
            }
        }
        
        $this->render_template('add-edit', [
            'vibe' => $vibe,
            'categories' => $categories,
            'editing' => $editing
        ]);
    }
    
    /**
     * Categories page
     */
    public function categories_page() {
        // Security validation
        if ($this->protection && method_exists($this->protection, 'validateRequest') && !$this->protection->validateRequest()) {
            wp_die(__('Security check failed', 'zippicks-vibes'));
        }
        
        $categories = $this->vibe_service->getAllCategories();
        
        $this->render_template('categories', [
            'categories' => $categories
        ]);
    }
    
    /**
     * Icon manager page
     */
    public function display_icon_manager() {
        // Security validation
        if ($this->protection && method_exists($this->protection, 'validateRequest') && !$this->protection->validateRequest()) {
            wp_die(__('Security check failed', 'zippicks-vibes'));
        }
        
        include ZIPPICKS_VIBES_DIR . 'src/Admin/Templates/icon-manager.php';
    }
    
    /**
     * Security page
     */
    public function security_page() {
        // Security validation
        if ($this->protection && method_exists($this->protection, 'validateRequest') && !$this->protection->validateRequest()) {
            wp_die(__('Security check failed', 'zippicks-vibes'));
        }
        
        $security_stats = [];
        $recent_attempts = [];
        
        if ($this->protection) {
            if (method_exists($this->protection, 'get_security_stats')) {
                $security_stats = $this->protection->get_security_stats();
            }
            if (method_exists($this->protection, 'get_recent_scraping_attempts')) {
                $recent_attempts = $this->protection->get_recent_scraping_attempts();
            }
        }
        
        $this->render_template('security', [
            'security_stats' => $security_stats,
            'recent_attempts' => $recent_attempts
        ]);
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Security validation
        if ($this->protection && method_exists($this->protection, 'validateRequest') && !$this->protection->validateRequest()) {
            wp_die(__('Security check failed', 'zippicks-vibes'));
        }
        
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'zippicks_vibes_settings')) {
            // WordPress will handle the settings update automatically via register_setting
            $this->add_admin_notice(__('Settings saved successfully.', 'zippicks-vibes'), 'success');
        }
        
        $current_settings = get_option('zippicks_vibes_settings', $this->get_default_settings());
        
        $this->render_template('settings', array(
            'settings' => $current_settings,
            'sections' => array(
                'security' => __('Security Settings', 'zippicks-vibes'),
                'performance' => __('Performance Settings', 'zippicks-vibes'),
                'features' => __('Feature Settings', 'zippicks-vibes')
            )
        ));
    }
    
    /**
     * AJAX save vibe with enhanced security and validation
     */
    public function ajax_save_vibe() {
        // Clear any output buffers to prevent network errors
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh output buffer
        ob_start();
        
        // Enhanced security checks
        if (!$this->validate_ajax_request()) {
            wp_send_json_error([
                'message' => __('Security check failed', 'zippicks-vibes'),
                'code' => 'security_failed'
            ], 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Unauthorized', 'zippicks-vibes'),
                'code' => 'unauthorized'
            ], 403);
        }
        
        $vibe_id = intval($_POST['vibe_id'] ?? 0);
        
        // Enhanced input validation and sanitization
        // Pass data with original field names when they exist
        $data = array();
        
        // Map form fields to service fields only when present in POST data
        if (isset($_POST['vibe_name'])) {
            $data['name'] = sanitize_text_field($_POST['vibe_name']);
        }
        if (isset($_POST['vibe_slug'])) {
            $data['slug'] = sanitize_title($_POST['vibe_slug']);
        }
        if (isset($_POST['vibe_description'])) {
            $data['description'] = sanitize_textarea_field($_POST['vibe_description']);
        }
        if (isset($_POST['vibe_color'])) {
            $data['color'] = sanitize_hex_color($_POST['vibe_color']);
        }
        if (isset($_POST['vibe_icon'])) {
            $data['icon'] = $_POST['vibe_icon']; // Don't sanitize icon to preserve special characters
        }
        if (isset($_POST['vibe_status'])) {
            $data['is_active'] = !empty($_POST['vibe_status']) ? 1 : 0;
        }
        if (isset($_POST['vibe_order_position'])) {
            $data['order_position'] = max(0, intval($_POST['vibe_order_position']));
        }
        if (isset($_POST['vibe_categories'])) {
            $data['categories'] = array_filter(array_map('intval', $_POST['vibe_categories']));
        }
        
        
        // Comprehensive validation
        $validation_errors = $this->validate_vibe_data($data, $vibe_id);
        if (!empty($validation_errors)) {
            wp_send_json_error([
                'message' => __('Validation failed', 'zippicks-vibes'),
                'code' => 'validation_failed',
                'errors' => $validation_errors
            ], 400);
        }
        
        // Auto-generate slug if empty
        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
        }
        
        // Ensure unique slug
        $data['slug'] = $this->ensure_unique_slug($data['slug'], $vibe_id);
        
        try {
            if ($vibe_id) {
                $result = $this->vibe_service->updateVibe($vibe_id, $data);
                $action = 'update';
            } else {
                $result = $this->vibe_service->createVibe($data);
                $vibe_id = $result;
                $action = 'create';
            }
            
            if ($result) {
                // Log successful action with error suppression
                try {
                    if ($this->protection && method_exists($this->protection, 'log_admin_action')) {
                        @$this->protection->log_admin_action('vibe_save', [
                            'vibe_id' => $vibe_id,
                            'action' => $action,
                            'user_id' => get_current_user_id()
                        ]);
                    }
                } catch (Exception $log_exception) {
                    // Ignore logging errors - don't let them break the save
                    error_log('Failed to log admin action: ' . $log_exception->getMessage());
                }
                
                // Ensure no output before JSON response
                if (ob_get_length()) {
                    ob_clean();
                }
                
                wp_send_json_success([
                    'message' => __('Vibe saved successfully', 'zippicks-vibes'),
                    'vibe_id' => $vibe_id,
                    'action' => $action,
                    'redirect_url' => admin_url('admin.php?page=zippicks-vibes')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to save vibe', 'zippicks-vibes'),
                    'code' => 'save_failed'
                ], 500);
            }
            
        } catch (Exception $e) {
            
            // Log error with context
            if ($this->protection && method_exists($this->protection, 'log_admin_error')) {
                $this->protection->log_admin_error('vibe_save_error', [
                    'message' => $e->getMessage(),
                    'vibe_id' => $vibe_id,
                    'user_id' => get_current_user_id(),
                    'data' => $data
                ]);
            }
            
            wp_send_json_error([
                'message' => __('An error occurred while saving the vibe', 'zippicks-vibes'),
                'code' => 'exception',
                'debug' => WP_DEBUG ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Validate vibe data
     */
    private function validate_vibe_data($data, $vibe_id = 0) {
        $errors = [];
        
        // Required fields
        if (empty($data['name'])) {
            $errors['name'] = __('Vibe name is required', 'zippicks-vibes');
        } elseif (strlen($data['name']) > 100) {
            $errors['name'] = __('Vibe name must be 100 characters or less', 'zippicks-vibes');
        }
        
        // Color validation
        if (!empty($data['color']) && !sanitize_hex_color($data['color'])) {
            $errors['color'] = __('Please enter a valid hex color', 'zippicks-vibes');
        }
        
        // Description length
        if (!empty($data['description']) && strlen($data['description']) > 500) {
            $errors['description'] = __('Description must be 500 characters or less', 'zippicks-vibes');
        }
        
        // Icon validation (emoji or simple text)
        if (!empty($data['icon']) && strlen($data['icon']) > 10) {
            $errors['icon'] = __('Icon must be 10 characters or less', 'zippicks-vibes');
        }
        
        return apply_filters('zippicks_vibes_validate_vibe_data', $errors, $data, $vibe_id);
    }
    
    /**
     * Ensure unique slug
     */
    private function ensure_unique_slug($slug, $exclude_id = 0) {
        $original_slug = $slug;
        $counter = 1;
        
        while ($this->slug_exists($slug, $exclude_id)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
            
            // Prevent infinite loop
            if ($counter > 100) {
                $slug = $original_slug . '-' . time();
                break;
            }
        }
        
        return $slug;
    }
    
    /**
     * Check if slug exists
     */
    private function slug_exists($slug, $exclude_id = 0) {
        try {
            $existing = $this->vibe_service->getVibeBySlug($slug);
            return $existing && $existing->getId() !== $exclude_id;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * AJAX delete vibe
     */
    public function ajax_delete_vibe() {
        // Clear any output buffers to prevent network errors
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh output buffer
        ob_start();
        
        if (!$this->validate_ajax_request()) {
            wp_send_json_error([
                'message' => __('Security check failed', 'zippicks-vibes'),
                'code' => 'security_failed'
            ], 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Unauthorized', 'zippicks-vibes'),
                'code' => 'unauthorized'
            ], 403);
        }
        
        $vibe_id = intval($_POST['vibe_id'] ?? 0);
        
        if (!$vibe_id) {
            wp_send_json_error([
                'message' => __('Invalid vibe ID', 'zippicks-vibes'),
                'code' => 'invalid_id'
            ], 400);
        }
        
        try {
            $result = $this->vibe_service->deleteVibe($vibe_id);
            
            if ($result) {
                // Log successful deletion with error suppression
                try {
                    if ($this->protection && method_exists($this->protection, 'log_admin_action')) {
                        @$this->protection->log_admin_action('vibe_delete', ['vibe_id' => $vibe_id]);
                    }
                } catch (Exception $log_exception) {
                    // Ignore logging errors
                    error_log('Failed to log admin action: ' . $log_exception->getMessage());
                }
                
                // Ensure no output before JSON response
                if (ob_get_length()) {
                    ob_clean();
                }
                
                wp_send_json_success([
                    'message' => __('Vibe deleted successfully', 'zippicks-vibes'),
                    'vibe_id' => $vibe_id
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to delete vibe', 'zippicks-vibes'),
                    'code' => 'delete_failed'
                ], 500);
            }
            
        } catch (Exception $e) {
            // Log error with suppression
            try {
                if ($this->protection && method_exists($this->protection, 'log_admin_error')) {
                    @$this->protection->log_admin_error('vibe_delete_error', $e->getMessage());
                }
            } catch (Exception $log_exception) {
                // Ignore logging errors
            }
            
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'exception',
                'debug' => WP_DEBUG ? $e->getTraceAsString() : null
            ], 500);
        }
    }
    
    /**
     * AJAX get vibe
     */
    public function ajax_get_vibe() {
        if (!$this->validate_ajax_request()) {
            wp_send_json_error(__('Security check failed', 'zippicks-vibes'));
        }
        
        $vibe_id = intval($_POST['vibe_id'] ?? 0);
        
        if (!$vibe_id) {
            wp_send_json_error(__('Invalid vibe ID', 'zippicks-vibes'));
        }
        
        try {
            $vibe = $this->vibe_service->getVibe($vibe_id);
            $categories = $this->vibe_service->get_vibe_categories($vibe_id);
            
            if ($vibe) {
                wp_send_json_success([
                    'vibe' => $vibe,
                    'categories' => $categories
                ]);
            } else {
                wp_send_json_error(__('Vibe not found', 'zippicks-vibes'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX get categories
     */
    public function ajax_get_categories() {
        if (!$this->validate_ajax_request()) {
            wp_send_json_error(__('Security check failed', 'zippicks-vibes'));
        }
        
        try {
            $categories = $this->vibe_service->getAllCategories();
            wp_send_json_success($categories);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX toggle status
     */
    public function ajax_toggle_status() {
        if (!$this->validate_ajax_request()) {
            wp_send_json_error(__('Security check failed', 'zippicks-vibes'));
        }
        
        $vibe_id = intval($_POST['vibe_id'] ?? 0);
        $is_active = !empty($_POST['is_active']);
        
        try {
            $result = $this->vibe_service->update_vibe_status($vibe_id, $is_active);
            
            if ($result) {
                wp_send_json_success(__('Status updated', 'zippicks-vibes'));
            } else {
                wp_send_json_error(__('Failed to update status', 'zippicks-vibes'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX reorder vibes
     */
    public function ajax_reorder_vibes() {
        if (!$this->validate_ajax_request()) {
            wp_send_json_error(__('Security check failed', 'zippicks-vibes'));
        }
        
        $order = $_POST['order'] ?? [];
        
        try {
            $result = $this->vibe_service->updateVibeOrder($order);
            
            if ($result) {
                wp_send_json_success(__('Order updated', 'zippicks-vibes'));
            } else {
                wp_send_json_error(__('Failed to update order', 'zippicks-vibes'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX bulk action
     */
    public function ajax_bulk_action() {
        if (!$this->validate_ajax_request()) {
            wp_send_json_error(__('Security check failed', 'zippicks-vibes'));
        }
        
        $action = sanitize_text_field($_POST['action_type'] ?? '');
        $vibe_ids = array_map('intval', $_POST['vibe_ids'] ?? array());
        
        if (empty($vibe_ids) || empty($action)) {
            wp_send_json_error(__('Invalid parameters', 'zippicks-vibes'));
        }
        
        try {
            $result = $this->vibe_service->bulk_action($action, $vibe_ids);
            
            if ($result) {
                // Log bulk action
                if ($this->protection && method_exists($this->protection, 'log_admin_action')) {
                    $this->protection->log_admin_action('vibe_bulk_action', [
                        'action' => $action,
                        'count' => count($vibe_ids)
                    ]);
                }
                
                wp_send_json_success(sprintf(__('%s completed', 'zippicks-vibes'), ucfirst($action)));
            } else {
                wp_send_json_error(__('Bulk action failed', 'zippicks-vibes'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX save category
     */
    public function ajax_save_category() {
        // Enhanced validation with detailed error reporting
        if (!$this->validate_ajax_request()) {
            error_log('ZipPicks Vibes: AJAX save category - Security validation failed');
            wp_send_json_error(__('Security check failed', 'zippicks-vibes'));
        }

        // Check if vibe service is available
        if (!$this->vibe_service) {
            error_log('ZipPicks Vibes: AJAX save category - VibeService not available');
            wp_send_json_error(__('Service not available', 'zippicks-vibes'));
        }

        $category_id = intval($_POST['category_id'] ?? 0);
        $name = sanitize_text_field($_POST['category_name'] ?? '');

        if (empty($name)) {
            wp_send_json_error(__('Category name is required', 'zippicks-vibes'));
        }

        // Get slug - use provided slug or generate from name
        $slug = !empty($_POST['category_slug']) 
            ? sanitize_title($_POST['category_slug']) 
            : sanitize_title($name);
        
        $data = [
            'name'           => $name,
            'description'    => sanitize_textarea_field($_POST['category_description'] ?? ''),
            'slug'           => $slug,
            'parent_id'      => intval($_POST['category_parent'] ?? 0),
            'order_position' => intval($_POST['category_order'] ?? 0)
        ];

        try {
            error_log('ZipPicks Vibes: Attempting to save category: ' . json_encode($data));
            
            if ($category_id) {
                $result = $this->vibe_service->update_category($category_id, $data);
                $message = __('Category updated successfully', 'zippicks-vibes');
            } else {
                $result = $this->vibe_service->create_category($data);
                $category_id = $result;
                $message = __('Category created successfully', 'zippicks-vibes');
            }

            if ($result) {
                error_log("ZipPicks Vibes: Category saved successfully with ID: $category_id");
                wp_send_json_success([
                    'message' => $message,
                    'category_id' => $category_id
                ]);
            } else {
                error_log('ZipPicks Vibes: Category save returned false');
                wp_send_json_error(__('Failed to save category', 'zippicks-vibes'));
            }

        } catch (Exception $e) {
            error_log('ZipPicks Vibes: Category save exception: ' . $e->getMessage());
            error_log('ZipPicks Vibes: Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX delete category
     */
    public function ajax_delete_category() {
        if (!$this->validate_ajax_request()) {
            wp_send_json_error(__('Security check failed', 'zippicks-vibes'));
        }
        
        $category_id = intval($_POST['category_id'] ?? 0);
        
        if (!$category_id) {
            wp_send_json_error(__('Invalid category ID', 'zippicks-vibes'));
        }
        
        try {
            $result = $this->vibe_service->delete_category($category_id);
            
            if ($result) {
                wp_send_json_success(__('Category deleted successfully', 'zippicks-vibes'));
            } else {
                wp_send_json_error(__('Failed to delete category', 'zippicks-vibes'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Validate AJAX request with enhanced security and comprehensive checks
     */
    private function validate_ajax_request() {
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        // Check nonce - try multiple possible nonce fields
        $nonce_valid = false;
        $possible_nonces = ['nonce', '_wpnonce', 'security'];
        
        foreach ($possible_nonces as $nonce_field) {
            if (isset($_POST[$nonce_field]) && wp_verify_nonce($_POST[$nonce_field], 'zippicks_vibes_admin')) {
                $nonce_valid = true;
                break;
            }
        }
        
        if (!$nonce_valid) {
            error_log('ZipPicks Vibes: AJAX nonce validation failed. Available nonce fields: ' . implode(', ', array_keys($_POST)));
            return false;
        }
        
        // Rate limiting check if enabled
        $settings = get_option('zippicks_vibes_settings', $this->get_default_settings());
        if (!empty($settings['rate_limit_enabled'])) {
            if ($this->protection && method_exists($this->protection, 'check_rate_limit') && !$this->protection->check_rate_limit('admin_ajax', 20)) {
                return false;
            }
        }
        
        // Additional security checks
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Block empty user agents or suspicious patterns
        if (empty($user_agent) || preg_match('/bot|crawler|spider|scraper/i', $user_agent)) {
            return false;
        }
        
        // Check for session hijacking
        if (session_status() === PHP_SESSION_ACTIVE) {
            $session_user_id = $_SESSION['zp_user_id'] ?? null;
            $current_user_id = get_current_user_id();
            
            if ($session_user_id && $session_user_id !== $current_user_id) {
                return false;
            }
        }
        
        // Log the request if audit logging is enabled
        if (!empty($settings['audit_logging'])) {
            if ($this->protection && method_exists($this->protection, 'log_admin_request')) {
                $this->protection->log_admin_request([
                    'action' => sanitize_text_field($_POST['action'] ?? ''),
                    'user_id' => get_current_user_id(),
                    'ip' => $remote_addr,
                    'user_agent' => substr($user_agent, 0, 255),
                    'timestamp' => current_time('mysql')
                ]);
            }
        }
        
        return apply_filters('zippicks_vibes_validate_ajax_request', true, $_POST);
    }
    
    /**
     * Render template with enhanced security and extensibility
     */
    private function render_template($template, $data = array()) {
        // Sanitize template name to prevent directory traversal
        $template = sanitize_file_name($template);
        $template = preg_replace('/[^a-zA-Z0-9\-_]/', '', $template);
        
        if (empty($template)) {
            wp_die(__('Invalid template name', 'zippicks-vibes'));
        }
        
        $template_file = ZIPPICKS_VIBES_DIR . 'src/Admin/Templates/' . $template . '.php';
        
        // Security check - ensure template is within allowed directory
        $real_template_path = realpath($template_file);
        $allowed_dir = realpath(ZIPPICKS_VIBES_DIR . 'src/Admin/Templates/');
        
        if (!$real_template_path || strpos($real_template_path, $allowed_dir) !== 0) {
            wp_die(__('Template path not allowed', 'zippicks-vibes'));
        }
        
        if (file_exists($template_file)) {
            // Allow pre-render hooks
            do_action('zippicks_vibes_before_template_render', $template, $data);
            
            // Filter template data for security and extensibility
            $data = apply_filters('zippicks_vibes_template_data', $data, $template);
            $data = apply_filters("zippicks_vibes_template_data_{$template}", $data);
            
            // Add common template variables
            $data['controller'] = $this;
            $data['template_name'] = $template;
            $data['current_user'] = wp_get_current_user();
            $data['admin_url'] = admin_url('admin.php?page=zippicks-vibes');
            
            // Extract data safely
            extract($data, EXTR_SKIP);
            
            // Start output buffering for post-processing
            ob_start();
            include $template_file;
            $content = ob_get_clean();
            
            // Allow content filtering
            $content = apply_filters('zippicks_vibes_template_content', $content, $template, $data);
            $content = apply_filters("zippicks_vibes_template_content_{$template}", $content, $data);
            
            // Output the content
            echo $content;
            
            // Allow post-render hooks
            do_action('zippicks_vibes_after_template_render', $template, $data);
            
        } else {
            // Log missing template error
            if (function_exists('error_log')) {
                error_log("ZipPicks Vibes: Template not found: {$template_file}");
            }
            
            wp_die(
                sprintf(
                    __('Template "%s" not found', 'zippicks-vibes'),
                    esc_html($template)
                )
            );
        }
    }
    
    /**
     * Get template data helper for use in templates
     */
    public function get_template_helper() {
        return (object) array(
            'esc_html' => 'esc_html',
            'esc_attr' => 'esc_attr',
            'esc_url' => 'esc_url',
            'wp_kses_post' => 'wp_kses_post',
            'current_user_can' => 'current_user_can',
            'admin_url' => 'admin_url',
            'wp_nonce_field' => 'wp_nonce_field',
            'submit_button' => 'submit_button'
        );
    }
    
    /**
     * Render a template part with data
     */
    public function render_template_part($part_name, $data = array()) {
        $part_file = ZIPPICKS_VIBES_DIR . 'src/Admin/Templates/parts/' . sanitize_file_name($part_name) . '.php';
        
        // Security check - ensure template part is within allowed directory
        $real_part_path = realpath($part_file);
        $allowed_dir = realpath(ZIPPICKS_VIBES_DIR . 'src/Admin/Templates/parts/');
        
        if (!$real_part_path || strpos($real_part_path, $allowed_dir) !== 0) {
            return '';
        }
        
        if (file_exists($part_file)) {
            // Allow filtering of template part data
            $data = apply_filters('zippicks_vibes_template_part_data', $data, $part_name);
            $data = apply_filters("zippicks_vibes_template_part_data_{$part_name}", $data);
            
            // Extract data safely and include template part
            extract($data, EXTR_SKIP);
            
            ob_start();
            include $part_file;
            $content = ob_get_clean();
            
            // Allow filtering of template part content
            $content = apply_filters('zippicks_vibes_template_part_content', $content, $part_name, $data);
            $content = apply_filters("zippicks_vibes_template_part_content_{$part_name}", $content, $data);
            
            return $content;
        }
        
        return '';
    }
    
    /**
     * Echo a template part with data
     */
    public function template_part($part_name, $data = array()) {
        echo $this->render_template_part($part_name, $data);
    }
    
    /**
     * Get assets version based on modification times of all related files
     */
    private function get_assets_version($main_file, $type = 'css') {
        if (!file_exists($main_file)) {
            return ZIPPICKS_VIBES_VERSION;
        }
        
        $latest_time = filemtime($main_file);
        
        if ($type === 'css') {
            // Check all CSS component files for the latest modification time
            $components_dir = ZIPPICKS_VIBES_DIR . 'src/Admin/Assets/css/components/';
            
            if (is_dir($components_dir)) {
                $component_files = glob($components_dir . '*.css');
                
                foreach ($component_files as $component_file) {
                    if (file_exists($component_file)) {
                        $component_time = filemtime($component_file);
                        if ($component_time > $latest_time) {
                            $latest_time = $component_time;
                        }
                    }
                }
            }
        }
        
        return $latest_time;
    }
    
    /**
     * AJAX handler to get available icons
     */
    public function ajax_get_icons() {
        try {
            // Security check
            $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
            if (!wp_verify_nonce($nonce, 'zippicks_vibes_admin')) {
                wp_send_json_error('Security check failed');
                return;
            }
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }
            
            $icons_dir = ZIPPICKS_VIBES_DIR . 'assets/icons/vibes/';
            $icons_url = ZIPPICKS_VIBES_URL . 'assets/icons/vibes/';
            $icons = [];
            
            if (is_dir($icons_dir)) {
                $files = scandir($icons_dir);
                
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'svg') {
                        $icon_name = pathinfo($file, PATHINFO_FILENAME);
                        
                        // Get SVG content safely
                        $svg_path = $icons_dir . $file;
                        $svg_content = '';
                        if (file_exists($svg_path)) {
                            $svg_content = file_get_contents($svg_path);
                        }
                        
                        $icons[] = [
                            'name' => $icon_name,
                            'url' => $icons_url . $file,
                            'svg' => $svg_content,
                            'usage_count' => 0
                        ];
                    }
                }
            } else {
                // Fallback icons if directory doesn't exist
                $icons = [
                    ['name' => 'default', 'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>', 'url' => '', 'usage_count' => 0],
                    ['name' => 'star', 'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>', 'url' => '', 'usage_count' => 0],
                    ['name' => 'hamburger', 'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5v2h18V5H3zm0 6v2h18v-2H3zm0 6v2h18v-2H3z"/></svg>', 'url' => '', 'usage_count' => 0],
                    ['name' => 'coffee', 'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M2 21h18v-2H2M20 8h-2V5h2m0-2H4v10a4 4 0 004 4h6a4 4 0 004-4v-3h2a2 2 0 002-2V5a2 2 0 00-2-2z"/></svg>', 'url' => '', 'usage_count' => 0],
                    ['name' => 'music', 'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>', 'url' => '', 'usage_count' => 0],
                ];
            }
            
            wp_send_json_success($icons);
        } catch (\Exception $e) {
            wp_send_json_error('Error loading icons: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler to upload new icon
     */
    public function ajax_upload_icon() {
        // Security check
        if (!wp_verify_nonce($_POST['zippicks_icon_nonce'] ?? '', 'zippicks_upload_icon')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (empty($_FILES['icon_file'])) {
            wp_send_json_error('No file uploaded');
            return;
        }
        
        $file = $_FILES['icon_file'];
        
        // Validate file type
        if ($file['type'] !== 'image/svg+xml') {
            wp_send_json_error('Only SVG files are allowed');
            return;
        }
        
        // Validate icon name
        $icon_name = sanitize_file_name($_POST['icon_name'] ?? '');
        if (!preg_match('/^[a-z0-9-]+$/', $icon_name)) {
            wp_send_json_error('Invalid icon name. Use only lowercase letters, numbers, and hyphens.');
            return;
        }
        
        // Read and sanitize SVG content
        $svg_content = file_get_contents($file['tmp_name']);
        
        // Basic SVG validation
        if (strpos($svg_content, '<svg') === false || strpos($svg_content, '</svg>') === false) {
            wp_send_json_error('Invalid SVG file');
            return;
        }
        
        // Remove any scripts or dangerous elements
        $svg_content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $svg_content);
        $svg_content = preg_replace('/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi', '', $svg_content);
        $svg_content = preg_replace('/on[a-z]+\s*=\s*["\'][^"\']*["\']/i', '', $svg_content);
        
        // Save file
        $icons_dir = ZIPPICKS_VIBES_DIR . 'assets/icons/vibes/';
        $file_path = $icons_dir . $icon_name . '.svg';
        
        if (file_exists($file_path)) {
            wp_send_json_error('An icon with this name already exists');
            return;
        }
        
        if (file_put_contents($file_path, $svg_content) === false) {
            wp_send_json_error('Failed to save icon file');
            return;
        }
        
        // Log the upload
        if ($this->logger) {
            $this->logger->info('Icon uploaded', [
                'icon_name' => $icon_name,
                'user_id' => get_current_user_id()
            ]);
        }
        
        wp_send_json_success(['message' => 'Icon uploaded successfully']);
    }
    
    /**
     * AJAX handler to delete icon
     */
    public function ajax_delete_icon() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zippicks_delete_icon')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $icon_name = sanitize_file_name($_POST['icon'] ?? '');
        
        if (empty($icon_name) || $icon_name === 'default') {
            wp_send_json_error('Cannot delete default icon');
            return;
        }
        
        // Check if icon is in use
        global $wpdb;
        $table_name = $wpdb->prefix . 'zippicks_vibes';
        $usage_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE icon = %s",
            $icon_name
        ));
        
        if ($usage_count > 0) {
            wp_send_json_error('Cannot delete icon that is in use');
            return;
        }
        
        // Delete file
        $icons_dir = ZIPPICKS_VIBES_DIR . 'assets/icons/vibes/';
        $file_path = $icons_dir . $icon_name . '.svg';
        
        if (!file_exists($file_path)) {
            wp_send_json_error('Icon file not found');
            return;
        }
        
        if (!unlink($file_path)) {
            wp_send_json_error('Failed to delete icon file');
            return;
        }
        
        // Log the deletion
        if ($this->logger) {
            $this->logger->info('Icon deleted', [
                'icon_name' => $icon_name,
                'user_id' => get_current_user_id()
            ]);
        }
        
        wp_send_json_success(['message' => 'Icon deleted successfully']);
    }
    
    /**
     * AJAX handler for filtering vibes by category
     * 
     * @since 2.0.0
     */
    public function ajax_filter_by_category() {
        // Verify nonce for security
        if (!check_ajax_referer('zippicks_vibes_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid security token']);
            return;
        }
        
        // Get category parameter
        $category_slug = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'all';
        
        try {
            // Get all active vibes
            $params = [
                'status' => 'active',
                'orderby' => 'order_position',
                'order' => 'ASC',
                'limit' => 100
            ];
            
            $all_vibes = $this->vibe_service->getAllVibes($params);
            
            // Filter by category if not 'all'
            if ($category_slug !== 'all') {
                $filtered_vibes = [];
                
                foreach ($all_vibes as $vibe) {
                    // Get vibe categories
                    $categories = [];
                    if (is_object($vibe) && method_exists($vibe, 'getCategories')) {
                        $categories = $vibe->getCategories();
                    } elseif (is_array($vibe) && isset($vibe['categories'])) {
                        $categories = $vibe['categories'];
                    }
                    
                    // Check if vibe belongs to the selected category
                    foreach ($categories as $cat) {
                        $slug = is_object($cat) ? ($cat->slug ?? '') : ($cat['slug'] ?? '');
                        if ($slug === $category_slug) {
                            $filtered_vibes[] = $vibe;
                            break;
                        }
                    }
                }
                
                $vibes = $filtered_vibes;
            } else {
                $vibes = $all_vibes;
            }
            
            // Format vibes for response
            $formatted_vibes = [];
            foreach ($vibes as $vibe) {
                if (is_object($vibe)) {
                    $formatted_vibes[] = [
                        'id' => $vibe->id ?? 0,
                        'name' => $vibe->name ?? '',
                        'slug' => $vibe->slug ?? '',
                        'description' => $vibe->description ?? '',
                        'icon_path' => $this->getVibeIconPath($vibe),
                        'color' => $vibe->color ?? '#194FAD',
                        'categories' => method_exists($vibe, 'getCategories') ? $vibe->getCategories() : []
                    ];
                } elseif (is_array($vibe)) {
                    $formatted_vibes[] = [
                        'id' => $vibe['id'] ?? 0,
                        'name' => $vibe['name'] ?? '',
                        'slug' => $vibe['slug'] ?? '',
                        'description' => $vibe['description'] ?? '',
                        'icon_path' => $this->getVibeIconPath($vibe),
                        'color' => $vibe['color'] ?? '#194FAD',
                        'categories' => $vibe['categories'] ?? []
                    ];
                }
            }
            
            // Send response
            wp_send_json_success([
                'vibes' => $formatted_vibes,
                'count' => count($formatted_vibes),
                'category' => $category_slug
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Failed to filter vibes: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get icon path for a vibe
     * 
     * @param mixed $vibe Vibe object or array
     * @return string Icon path
     */
    private function getVibeIconPath($vibe) {
        $icon = '';
        
        if (is_object($vibe) && isset($vibe->icon)) {
            $icon = $vibe->icon;
        } elseif (is_array($vibe) && isset($vibe['icon'])) {
            $icon = $vibe['icon'];
        }
        
        if (empty($icon) || $icon === 'default') {
            return ZIPPICKS_VIBES_URL . 'assets/icons/vibes/default.svg';
        }
        
        // Check if it's a full path
        if (strpos($icon, 'http') === 0 || strpos($icon, '/') === 0) {
            return $icon;
        }
        
        // Build icon path
        return ZIPPICKS_VIBES_URL . 'assets/icons/vibes/' . $icon . '.svg';
    }
}