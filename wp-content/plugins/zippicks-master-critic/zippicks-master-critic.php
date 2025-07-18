<?php
/**
 * Plugin Name:       ZipPicks Master Critic
 * Plugin URI:        https://zippicks.com/
 * Description:       AI-powered business ranking generator using Claude or GPT-4 for ANY business category
 * Version:           1.0.0
 * Author:            ZipPicks
 * Author URI:        https://zippicks.com/
 * License:           Proprietary
 * Text Domain:       zippicks-master-critic
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('ZIPPICKS_MASTER_CRITIC_VERSION', '1.0.0');
define('ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZIPPICKS_MASTER_CRITIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZIPPICKS_MASTER_CRITIC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('ZIPPICKS_MASTER_CRITIC_PLUGIN_FILE', __FILE__);

/**
 * The code that runs during plugin activation.
 */
function activate_zippicks_master_critic() {
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-installer.php';
    ZipPicks_Master_Critic_Installer::install();
    
    // Run Socrata cleanup
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-socrata-cleanup.php';
    ZipPicks_Master_Critic_Socrata_Cleanup::run_on_activation();
    
    // Flush rewrite rules for new routing
    flush_rewrite_rules();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_zippicks_master_critic() {
    // Nothing to do on deactivation for now
}

register_activation_hook(__FILE__, 'activate_zippicks_master_critic');
register_deactivation_hook(__FILE__, 'deactivate_zippicks_master_critic');

/**
 * Show admin notice about activation status
 */
function zippicks_master_critic_activation_notice() {
    $activation_result = get_option('zippicks_master_critic_activation_result');
    
    if ($activation_result) {
        // Check if all tables exist
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-installer.php';
        $tables_exist = ZipPicks_Master_Critic_Installer::tables_exist();
        
        if (!$tables_exist) {
            ?>
            <div class="notice notice-error">
                <p><strong>ZipPicks Master Critic:</strong> Some database tables are missing. 
                <a href="<?php echo admin_url('admin.php?page=zippicks-master-critic&tab=tools'); ?>">Click here to create them</a>.</p>
            </div>
            <?php
        }
        
        // Clear the activation result
        delete_option('zippicks_master_critic_activation_result');
    }
}
add_action('admin_notices', 'zippicks_master_critic_activation_notice');

/**
 * The core plugin class
 */
require ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-master-critic.php';

/**
 * Check if all dependencies are met
 */
function zippicks_master_critic_check_dependencies() {
    $errors = [];
    
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        $errors[] = sprintf(__('ZipPicks Master Critic requires PHP 8.0 or higher. You are running PHP %s.', 'zippicks-master-critic'), PHP_VERSION);
    }
    
    // No plugin dependencies - removed for flexibility
    
    return $errors;
}

/**
 * Initialize error handling if available
 */
function zippicks_master_critic_init_error_handling() {
    if (file_exists(ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-error-handler.php')) {
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-error-handler.php';
        if (class_exists('ZipPicks_Master_Critic_Error_Handler')) {
            ZipPicks_Master_Critic_Error_Handler::init();
        }
    }
}

/**
 * Begins execution of the plugin.
 */
function run_zippicks_master_critic() {
    // Load required classes
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-schema-generator.php';
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-schema-hooks.php';
    
    // Load anti-scraping protection
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-scrape-protection.php';
    
    // Load REST API endpoints
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-rest-api.php';
    
    // Load service wrapper classes first (required by hybrid components)
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/services/class-cache-manager.php';
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/services/class-ai-service.php';
    
    // Load and register Hybrid Service Provider
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/hybrid/class-hybrid-service-provider.php';
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/hybrid/class-smart-router.php';
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/hybrid/class-confidence-engine.php';
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/hybrid/class-data-aggregator.php';
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/hybrid/class-paid-api-manager.php';
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/hybrid/class-cost-optimizer.php';
    
    // Register hybrid services
    \ZipPicks\MasterCritic\Hybrid\HybridServiceProvider::register();
    
    // Initialize error handling
    zippicks_master_critic_init_error_handling();
    
    // Use enterprise version if available and enabled
    $use_enterprise = apply_filters('zippicks_use_enterprise_features', true);
    
    if ($use_enterprise && file_exists(ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-master-critic-enterprise.php')) {
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-master-critic-enterprise.php';
        $plugin = ZipPicks_Master_Critic_Enterprise::get_instance();
    } else {
        // Fall back to standard version
        $plugin = new ZipPicks_Master_Critic();
        $plugin->run();
    }
}

// Check dependencies and run plugin
add_action('plugins_loaded', function() {
    $dependency_errors = zippicks_master_critic_check_dependencies();
    
    if (!empty($dependency_errors)) {
        // Show admin notices for missing dependencies
        add_action('admin_notices', function() use ($dependency_errors) {
            ?>
            <div class="notice notice-error">
                <p><strong><?php _e('ZipPicks Master Critic cannot be activated:', 'zippicks-master-critic'); ?></strong></p>
                <ul>
                    <?php foreach ($dependency_errors as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        });
        return;
    }
    
    // Check if Foundation is available
    if (!function_exists('zippicks')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e('ZipPicks Master Critic is running with limited functionality. ZipPicks Foundation is not active.', 'zippicks-master-critic'); ?></p>
            </div>
            <?php
        });
    }
    
    // Run the plugin
    run_zippicks_master_critic();
}, 5);

/**
 * Update default prompt template in database
 */
function zippicks_update_default_prompt_template() {
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-database.php';
    global $wpdb;
    
    $templates_table = $wpdb->prefix . 'zippicks_prompt_templates';
    
    // New prompt template
    $new_template = '🚨 CRITICAL LOCATION REQUIREMENT 🚨
You MUST ONLY include {business_category_plural} that are PHYSICALLY LOCATED in {location}.

STRICT GEOGRAPHIC RULES:
1. Every single {business_category} MUST be located within the boundaries of {location}
2. If a {business_category} has multiple locations, ONLY count the one in {location}
3. Do NOT include {business_category_plural} from nearby cities, suburbs, or metro areas unless they are within {location} proper
4. Do NOT include {business_category_plural} you\'re unsure about - when in doubt, leave it out
5. If you cannot find enough {business_category_plural} in {location}, return fewer results rather than including ones from other locations

LOCATION VERIFICATION CHECKLIST:
✓ Is this {business_category} physically located in {location}? (NOT nearby cities)
✓ Does this {business_category} have a street address in {location}?
✓ Is this a real, currently operating {business_category} in {location}?

You are the Master Critic AI for ZipPicks — a location-specific discovery platform. Your SOLE task is to evaluate {business_category_plural} that exist ONLY in {location}.

TASK: Generate a list of the best {topic} that are LOCATED IN {location}

Only include {business_category_plural} that:
- Are PHYSICALLY LOCATED in {location} (not nearby areas)
- Currently exist and are operational
- Genuinely deserve recognition
- Can be verified as being in {location}

You may list up to 10 businesses, but ONLY if they are ALL in {location}. Return fewer if necessary.

Every output must follow this exact structure:

[
  {
    "rank": 1,
    "name": "{business_category} Name",
    "score": 9.6,
    "review_count": 1200,
    "price_tier": "$$",
    "summary": "Editorial description of the {business_category}, written in a stylish and trustworthy tone. Avoid generic phrases. Capture the emotional essence of experiencing this {business_category}. Mention highlights and who it\'s best for.",
    "top_dishes": ["{unit} 1", "{unit} 2"],
    "pillar_scores": {
      "{category_pillar_1}": 9.8,
      "{category_pillar_2}": 9.4,
      "{category_pillar_3}": 9.5,
      "{category_pillar_4}": 9.2,
      "{category_pillar_5}": 9.4,
      "{category_pillar_6}": 9.6
    },
    "vibes": ["Vibe 1", "Vibe 2", "Vibe 3"]
  }
]

LOCATION ENFORCEMENT:
• Every {business_category} MUST be in {location} - NO EXCEPTIONS
• Do NOT include famous {business_category_plural} from other cities
• Do NOT include {business_category_plural} from neighboring towns
• Each result must be verifiably located in {location}

Scoring Guidelines:
• All scores must be on a 0–10 scale, not 1–5
• Base scores on local reputation within {location}
• Consider how each {business_category} compares to others in {location} specifically

Editorial Voice:
• Write like a LOCAL expert who knows {location} intimately
• Reference {location}-specific details when relevant
• Focus on what makes each {business_category} special within {location}\'s scene

FINAL REMINDER: You are generating:
Top {topic} in {location}

This means ONLY {business_category_plural} that are INSIDE {location}\'s boundaries.
NOT nearby cities. NOT metro areas. ONLY {location}.

Now return only the valid JSON array with {business_category_plural} from {location}. No preamble. No closing statements. No formatting errors.';
    
    // Check if table exists (secure query)
    $table_check = $wpdb->prepare("SHOW TABLES LIKE %s", $templates_table);
    if ($wpdb->get_var($table_check) === $templates_table) {
        // Update existing default template
        $updated = $wpdb->update(
            $templates_table,
            array(
                'prompt_template' => $new_template,
                'updated_at' => current_time('mysql')
            ),
            array(
                'business_category' => 'all',
                'is_default' => 1
            ),
            array('%s', '%s'),
            array('%s', '%d')
        );
        
        // If no rows updated, insert new template
        if ($updated === 0) {
            ZipPicks_Master_Critic_Database::save_prompt_template(array(
                'name' => 'Master Critic Universal Template',
                'business_category' => 'all',
                'prompt_template' => $new_template,
                'is_default' => 1
            ));
        }
    }
}

// Run update on admin init
add_action('admin_init', 'zippicks_update_default_prompt_template');