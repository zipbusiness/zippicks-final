<?php
/**
 * Settings Template - WordPress standards compliant
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access prohibited');
}

// Security validation
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'zippicks-vibes'));
}

// Initialize template helper
$helper = $controller->get_template_helper();

// Header data
$header_data = array(
    'title' => __('ZipPicks Vibes Settings', 'zippicks-vibes'),
    'subtitle' => __('Configure plugin settings', 'zippicks-vibes'),
    'icon' => 'dashicons-admin-settings'
);
?>

<div class="wrap zippicks-vibes-admin" role="main">
    <?php $controller->template_part('header', array('header_data' => $header_data)); ?>
    
    <form method="post" action="options.php" novalidate="novalidate">
        <?php
        // WordPress settings fields and nonce
        settings_fields('zippicks_vibes_options');
        do_settings_sections('zippicks_vibes_options');
        ?>
        
        <div class="settings-container">
            <div class="settings-main">
                
                <?php foreach ($sections as $section_id => $section_title): ?>
                    <div class="settings-section" id="section-<?php echo esc_attr($section_id); ?>">
                        <h2 class="settings-section-title">
                            <?php echo esc_html($section_title); ?>
                        </h2>
                        
                        <table class="form-table" role="presentation">
                            <tbody>
                                <?php do_settings_fields('zippicks_vibes_options', 'zippicks_vibes_' . $section_id); ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
                
            </div>
            
            <div class="settings-sidebar">
                <div class="card">
                    <h3><?php esc_html_e('Quick Actions', 'zippicks-vibes'); ?></h3>
                    <p>
                        <a href="<?php echo esc_url(wp_nonce_url(
                            admin_url('admin.php?action=zippicks_vibes_reset_settings'), 
                            'zippicks_vibes_reset_settings'
                        )); ?>" 
                           class="button button-secondary"
                           onclick="return confirm('<?php esc_attr_e('Are you sure you want to reset all settings to defaults?', 'zippicks-vibes'); ?>');">
                            <?php esc_html_e('Reset to Defaults', 'zippicks-vibes'); ?>
                        </a>
                    </p>
                </div>
                
                <div class="card">
                    <h3><?php esc_html_e('System Information', 'zippicks-vibes'); ?></h3>
                    <ul>
                        <li><?php printf(__('Plugin Version: %s', 'zippicks-vibes'), ZIPPICKS_VIBES_VERSION); ?></li>
                        <li><?php printf(__('WordPress Version: %s', 'zippicks-vibes'), get_bloginfo('version')); ?></li>
                        <li><?php printf(__('PHP Version: %s', 'zippicks-vibes'), PHP_VERSION); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="settings-footer">
            <?php submit_button(__('Save Settings', 'zippicks-vibes')); ?>
        </div>
        
    </form>
</div>

<style>
.settings-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.settings-main {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
}

.settings-section {
    margin-bottom: 30px;
}

.settings-section:last-child {
    margin-bottom: 0;
}

.settings-section-title {
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
    font-size: 1.2em;
}

.settings-sidebar .card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
}

.settings-sidebar .card h3 {
    margin: 0 0 10px 0;
    font-size: 1.1em;
}

.settings-sidebar ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.settings-sidebar li {
    padding: 5px 0;
    border-bottom: 1px solid #f0f0f1;
    font-size: 13px;
}

.settings-sidebar li:last-child {
    border-bottom: none;
}

.settings-footer {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

@media (max-width: 782px) {
    .settings-container {
        grid-template-columns: 1fr;
        gap: 15px;
    }
}
</style>