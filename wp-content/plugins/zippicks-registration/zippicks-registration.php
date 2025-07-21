<?php
/**
 * Plugin Name: ZipPicks Registration
 * Plugin URI: https://zippicks.com
 * Description: Custom registration system for ZipPicks platform using WordPress core user system
 * Version: 1.0.0
 * Author: ZipPicks Engineering Team
 * License: GPL v2 or later
 * Text Domain: zippicks-registration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ZIPPICKS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZIPPICKS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZIPPICKS_VERSION', '1.0.0');

// reCAPTCHA configuration
define('ZIPPICKS_RECAPTCHA_SITE_KEY', '6Lc8sUYrAAAAAGY813swPcwC-uJy5ckmhtTECZXm');
define('ZIPPICKS_RECAPTCHA_SECRET_KEY', '6Lc8sUYrAAAAAIVuKot0eK0PQWYdBHsPAb38fZGE');

class ZipPicksRegistration {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load required files
        $this->loadFiles();
        
        // Initialize hooks
        $this->initHooks();
        
        // Handle verification if token present
        if (isset($_GET['zp_verify'])) {
            $this->handleVerification();
        }
        
        // Add custom user roles
        $this->addCustomRoles();
    }
    
    private function loadFiles() {
        require_once ZIPPICKS_PLUGIN_DIR . 'includes/user-management.php';
        require_once ZIPPICKS_PLUGIN_DIR . 'includes/form.php';
        require_once ZIPPICKS_PLUGIN_DIR . 'handlers/register-handler.php';
        require_once ZIPPICKS_PLUGIN_DIR . 'handlers/verify-handler.php';
        require_once ZIPPICKS_PLUGIN_DIR . 'includes/email.php';
        
        if (is_admin()) {
            require_once ZIPPICKS_PLUGIN_DIR . 'admin/admin-enhancements.php';
        }
    }
    
    private function initHooks() {
        // Shortcode registration
        add_shortcode('zippicks_register_form', array($this, 'renderRegistrationForm'));
        
        // AJAX handlers
        add_action('wp_ajax_zippicks_register', array($this, 'handleRegistration'));
        add_action('wp_ajax_nopriv_zippicks_register', array($this, 'handleRegistration'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'));
        
        // Admin customizations
        if (is_admin()) {
            add_action('admin_init', array($this, 'adminInit'));
            add_filter('manage_users_columns', array($this, 'addUserColumns'));
            add_filter('manage_users_custom_column', array($this, 'showUserColumns'), 10, 3);
            add_action('show_user_profile', array($this, 'showUserProfile'));
            add_action('edit_user_profile', array($this, 'showUserProfile'));
            add_action('personal_options_update', array($this, 'saveUserProfile'));
            add_action('edit_user_profile_update', array($this, 'saveUserProfile'));
        }
        
        // Login redirects
        add_filter('login_redirect', array($this, 'loginRedirect'), 10, 3);
    }
    
    public function activate() {
        $this->addCustomRoles();
        $this->migrateExistingUsers();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function addCustomRoles() {
        // Ensure default roles exist (but we won't use subscriber for ZipPicks users)
        if (!get_role('subscriber')) {
            add_role('subscriber', 'Subscriber', array('read' => true));
        }
        
        // Add ZipPicks Zipper role
        if (!get_role('zippicks_zipper')) {
            add_role('zippicks_zipper', 'ZipPicks Zipper', array(
                'read' => true,
                'zippicks_browse_businesses' => true,
                'zippicks_save_favorites' => true,
                'zippicks_basic_interaction' => true
            ));
        }
        
        // Add ZipPicks Critic role
        if (!get_role('zippicks_critic')) {
            add_role('zippicks_critic', 'ZipPicks Critic', array(
                'read' => true,
                'zippicks_browse_businesses' => true,
                'zippicks_save_favorites' => true,
                'zippicks_basic_interaction' => true,
                'zippicks_write_reviews' => true,
                'zippicks_rate_businesses' => true,
                'zippicks_moderate_content' => true
            ));
        }
        
        // Add ZipPicks Business Owner role
        if (!get_role('zippicks_business_owner')) {
            add_role('zippicks_business_owner', 'ZipPicks Business Owner', array(
                'read' => true,
                'zippicks_manage_business' => true,
                'zippicks_respond_reviews' => true,
                'zippicks_view_analytics' => true,
                'zippicks_manage_promotions' => true
            ));
        }
    }
    
    /**
     * Migrate existing users from subscriber role to zippicks_zipper role
     * This handles the transition when updating the plugin
     */
    private function migrateExistingUsers() {
        // Only run migration once
        if (get_option('zippicks_migration_completed')) {
            return;
        }
        
        $users = get_users(array(
            'role' => 'subscriber',
            'meta_key' => 'zippicks_user_type',
            'meta_value' => 'zipper'
        ));
        
        foreach ($users as $user) {
            // Remove subscriber role and add zippicks_zipper role
            $user->remove_role('subscriber');
            $user->add_role('zippicks_zipper');
        }
        
        // Mark migration as completed
        update_option('zippicks_migration_completed', true);
    }
    
    public function enqueueScripts() {
        // Only load registration scripts on pages that have the registration form
        if (!is_page() || !has_shortcode(get_post()->post_content, 'zippicks_register_form')) {
            return;
        }
        
        wp_enqueue_script('zippicks-registration', ZIPPICKS_PLUGIN_URL . 'assets/registration.js', array('jquery'), ZIPPICKS_VERSION, true);
        wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
        
        wp_localize_script('zippicks-registration', 'zippicks_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_register_nonce')
        ));
        
        wp_enqueue_style('zippicks-registration', ZIPPICKS_PLUGIN_URL . 'assets/style.css', array(), ZIPPICKS_VERSION);
    }
    
    public function renderRegistrationForm($atts) {
        ob_start();
        include ZIPPICKS_PLUGIN_DIR . 'includes/form.php';
        return ob_get_clean();
    }
    
    public function handleRegistration() {
        require_once ZIPPICKS_PLUGIN_DIR . 'handlers/register-handler.php';
        $handler = new ZipPicksRegisterHandler();
        $handler->process();
    }
    
    private function handleVerification() {
        require_once ZIPPICKS_PLUGIN_DIR . 'handlers/verify-handler.php';
        $handler = new ZipPicksVerifyHandler();
        $handler->process();
    }
    
    public function adminInit() {
        // Add custom user meta fields for admin editing
        add_action('user_new_form', array($this, 'addUserFormFields'));
        add_action('user_register', array($this, 'saveUserFormFields'));
    }
    
    public function addUserColumns($columns) {
        $columns['zippicks_zip'] = 'ZIP Code';
        $columns['zippicks_user_type'] = 'User Type';
        $columns['zippicks_verified'] = 'Email Verified';
        $columns['zippicks_approved'] = 'Approved';
        return $columns;
    }
    
    public function showUserColumns($value, $column_name, $user_id) {
        switch ($column_name) {
            case 'zippicks_zip':
                return get_user_meta($user_id, 'zippicks_zip', true);
            case 'zippicks_user_type':
                return ucfirst(get_user_meta($user_id, 'zippicks_user_type', true));
            case 'zippicks_verified':
                $verified = get_user_meta($user_id, 'zippicks_email_verified', true);
                return $verified ? '✅ Yes' : '❌ No';
            case 'zippicks_approved':
                $user_type = get_user_meta($user_id, 'zippicks_user_type', true);
                if ($user_type === 'zipper') {
                    return 'N/A';
                }
                $approved = get_user_meta($user_id, 'zippicks_approved', true);
                return $approved ? '✅ Yes' : '❌ Pending';
            default:
                return $value;
        }
    }
    
    public function showUserProfile($user) {
        $zip = get_user_meta($user->ID, 'zippicks_zip', true);
        $user_type = get_user_meta($user->ID, 'zippicks_user_type', true);
        $verified = get_user_meta($user->ID, 'zippicks_email_verified', true);
        $approved = get_user_meta($user->ID, 'zippicks_approved', true);
        ?>
        <h3>ZipPicks Information</h3>
        <table class="form-table">
            <tr>
                <th><label for="zippicks_zip">ZIP Code</label></th>
                <td>
                    <input type="text" name="zippicks_zip" id="zippicks_zip" 
                           value="<?php echo esc_attr($zip); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="zippicks_user_type">User Type</label></th>
                <td>
                    <select name="zippicks_user_type" id="zippicks_user_type">
                        <option value="zipper" <?php selected($user_type, 'zipper'); ?>>Zipper</option>
                        <option value="critic" <?php selected($user_type, 'critic'); ?>>Critic</option>
                        <option value="business_owner" <?php selected($user_type, 'business_owner'); ?>>Business Owner</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="zippicks_email_verified">Email Verified</label></th>
                <td>
                    <input type="checkbox" name="zippicks_email_verified" id="zippicks_email_verified" 
                           value="1" <?php checked($verified, '1'); ?> />
                    <label for="zippicks_email_verified">Email address is verified</label>
                </td>
            </tr>
            <?php if ($user_type !== 'zipper'): ?>
            <tr>
                <th><label for="zippicks_approved">Admin Approved</label></th>
                <td>
                    <input type="checkbox" name="zippicks_approved" id="zippicks_approved" 
                           value="1" <?php checked($approved, '1'); ?> />
                    <label for="zippicks_approved">User is approved for platform access</label>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }
    
    public function saveUserProfile($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        if (isset($_POST['zippicks_zip'])) {
            update_user_meta($user_id, 'zippicks_zip', sanitize_text_field($_POST['zippicks_zip']));
        }
        
        if (isset($_POST['zippicks_user_type'])) {
            $user_type = sanitize_text_field($_POST['zippicks_user_type']);
            update_user_meta($user_id, 'zippicks_user_type', $user_type);
            
            // Update WordPress role based on user type
            $user = get_user_by('id', $user_id);
            if ($user) {
                $this->assignUserRole($user, $user_type);
            }
        }
        
        if (isset($_POST['zippicks_email_verified'])) {
            update_user_meta($user_id, 'zippicks_email_verified', '1');
        } else {
            update_user_meta($user_id, 'zippicks_email_verified', '0');
        }
        
        if (isset($_POST['zippicks_approved'])) {
            update_user_meta($user_id, 'zippicks_approved', '1');
        } else {
            update_user_meta($user_id, 'zippicks_approved', '0');
        }
    }
    
    public function addUserFormFields() {
        ?>
        <h3>ZipPicks Information</h3>
        <table class="form-table">
            <tr>
                <th><label for="zippicks_zip">ZIP Code</label></th>
                <td><input type="text" name="zippicks_zip" id="zippicks_zip" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="zippicks_user_type">User Type</label></th>
                <td>
                    <select name="zippicks_user_type" id="zippicks_user_type">
                        <option value="zipper">Zipper</option>
                        <option value="critic">Critic</option>
                        <option value="business_owner">Business Owner</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function saveUserFormFields($user_id) {
        if (isset($_POST['zippicks_zip'])) {
            update_user_meta($user_id, 'zippicks_zip', sanitize_text_field($_POST['zippicks_zip']));
        }
        
        if (isset($_POST['zippicks_user_type'])) {
            $user_type = sanitize_text_field($_POST['zippicks_user_type']);
            update_user_meta($user_id, 'zippicks_user_type', $user_type);
        }
    }
    
    public function assignUserRole($user, $user_type) {
        // Remove all existing ZipPicks roles first
        $zippicks_roles = ['zippicks_zipper', 'zippicks_critic', 'zippicks_business_owner', 'subscriber'];
        foreach ($zippicks_roles as $role) {
            if ($user->has_cap($role)) {
                $user->remove_role($role);
            }
        }
        
        // Assign appropriate role based on user type
        switch ($user_type) {
            case 'zipper':
                $user->add_role('zippicks_zipper');
                break;
            case 'critic':
                $user->add_role('zippicks_critic');
                break;
            case 'business_owner':
                $user->add_role('zippicks_business_owner');
                break;
            default:
                $user->add_role('zippicks_zipper');
        }
    }
    
    public function loginRedirect($redirect_to, $request, $user) {
        // Only handle our custom redirects for ZipPicks users
        if (!is_wp_error($user) && isset($user->ID)) {
            $user_type = get_user_meta($user->ID, 'zippicks_user_type', true);
            $email_verified = get_user_meta($user->ID, 'zippicks_email_verified', true);
            
            if ($user_type && !$email_verified) {
                // Redirect unverified users to verification notice
                return home_url('/account-under-review/');
            } elseif ($user_type === 'zipper' && $email_verified) {
                // Redirect verified zippers to profile
                return home_url('/profile/');
            } elseif (in_array($user_type, ['critic', 'business_owner']) && $email_verified) {
                // Check if they need admin approval
                $approved = get_user_meta($user->ID, 'zippicks_approved', true);
                if (!$approved) {
                    return home_url('/account-under-review/');
                }
                // If approved, redirect to appropriate dashboard
                return $user_type === 'critic' ? home_url('/critic-dashboard/') : home_url('/business-dashboard/');
            }
        }
        
        return $redirect_to;
    }
}

// Initialize plugin
ZipPicksRegistration::getInstance();