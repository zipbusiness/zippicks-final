<?php
/**
 * Settings Page View
 *
 * @package ZipPicks\BusinessIntelligence
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Business Intelligence Settings', 'zippicks-business-intelligence'); ?></h1>
    
    <?php if ($settings_saved): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully.', 'zippicks-business-intelligence'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($validation_errors)): ?>
        <div class="notice notice-error">
            <p><strong><?php _e('Configuration Issues:', 'zippicks-business-intelligence'); ?></strong></p>
            <ul>
                <?php foreach ($validation_errors as $error): ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('zippicks_bi_settings');
        do_settings_sections('zippicks-bi-settings');
        submit_button();
        ?>
    </form>
    
    <!-- Configuration Info -->
    <div class="card">
        <h2><?php _e('Configuration Information', 'zippicks-business-intelligence'); ?></h2>
        <p><?php _e('You can also configure this plugin using environment variables or constants in your wp-config.php file:', 'zippicks-business-intelligence'); ?></p>
        
        <h3><?php _e('Environment Variables', 'zippicks-business-intelligence'); ?></h3>
        <pre>
ZIPPICKS_BI_API_URL=https://api.zipbusiness.ai/v1
ZIPPICKS_BI_API_KEY=your-api-key
ZIPPICKS_BI_CACHE_TTL=3600
ZIPPICKS_BI_DEBUG_MODE=true
</pre>
        
        <h3><?php _e('PHP Constants', 'zippicks-business-intelligence'); ?></h3>
        <pre>
define('ZIPPICKS_BI_API_URL', 'https://api.zipbusiness.ai/v1');
define('ZIPPICKS_BI_API_KEY', 'your-api-key');
define('ZIPPICKS_BI_CACHE_TTL', 3600);
define('ZIPPICKS_BI_DEBUG', true);
</pre>
        
        <p><em><?php _e('Note: Environment variables and constants take precedence over settings saved in the database.', 'zippicks-business-intelligence'); ?></em></p>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-top: 20px;
}

.card h2 {
    margin-top: 0;
}

.card pre {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
}
</style>