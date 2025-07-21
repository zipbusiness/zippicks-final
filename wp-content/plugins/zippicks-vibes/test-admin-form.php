<?php
/**
 * Test Admin Form - Direct test of form submission
 * 
 * Visit this in browser: /wp-content/plugins/zippicks-vibes/test-admin-form.php
 */

// WordPress bootstrap
require_once '../../../wp-config.php';

// Ensure we're logged in as admin
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('You must be logged in as an administrator to view this page.');
}

// Set admin context
define('WP_ADMIN', true);

// Initialize plugin
$plugin = zippicks_vibes_init();

// Enqueue scripts for testing
wp_enqueue_script('jquery');

// Check if we need to enqueue our admin assets
$admin_controller = null;
try {
    if (method_exists($plugin, 'get_admin_controller')) {
        $admin_controller = $plugin->get_admin_controller();
        if (method_exists($admin_controller, 'enqueue_admin_assets')) {
            $admin_controller->enqueue_admin_assets('toplevel_page_zippicks-vibes-add');
        }
    }
} catch (Exception $e) {
    // Silent fail
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ZipPicks Vibes - Form Test</title>
    <?php wp_head(); ?>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; }
        .wrap { max-width: 800px; }
        .form-table th { width: 150px; }
        .button-primary { background: #2271b1; color: white; padding: 8px 16px; border: none; border-radius: 3px; cursor: pointer; }
        .button { background: #f0f0f1; color: #2c3338; padding: 8px 16px; border: 1px solid #8c8f94; border-radius: 3px; text-decoration: none; display: inline-block; cursor: pointer; }
        .notice { padding: 10px; margin: 10px 0; border-left: 4px solid #00a32a; background: #f0f8f0; }
        .notice.error { border-color: #d63638; background: #fcf0f1; }
        .console-log { background: #000; color: #0f0; font-family: monospace; padding: 10px; margin: 10px 0; height: 200px; overflow-y: auto; }
        .debug-info { background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc; }
    </style>
</head>
<body class="wp-admin">

<div class="wrap zippicks-vibes-admin">
    <h1>ZipPicks Vibes - Form Test</h1>
    
    <div class="debug-info">
        <h3>Debug Information</h3>
        <p><strong>WordPress Admin Context:</strong> <?php echo defined('WP_ADMIN') && WP_ADMIN ? '✅ Yes' : '❌ No'; ?></p>
        <p><strong>Current User ID:</strong> <?php echo get_current_user_id(); ?></p>
        <p><strong>User Can Manage Options:</strong> <?php echo current_user_can('manage_options') ? '✅ Yes' : '❌ No'; ?></p>
        <p><strong>Plugin Active:</strong> <?php echo function_exists('zippicks_vibes_init') ? '✅ Yes' : '❌ No'; ?></p>
        <p><strong>AJAX URL:</strong> <code><?php echo admin_url('admin-ajax.php'); ?></code></p>
        <p><strong>Sample Nonce:</strong> <code><?php echo wp_create_nonce('zippicks_vibes_admin'); ?></code></p>
    </div>
    
    <div id="console-container">
        <h3>JavaScript Console Log</h3>
        <div id="console-log" class="console-log"></div>
        <button type="button" onclick="clearConsoleLog()">Clear Log</button>
    </div>
    
    <div id="message-container"></div>
    
    <form id="vibe-form" class="vibe-form" method="post" novalidate>
        <?php 
        wp_nonce_field('zippicks_vibes_admin', 'nonce', true, true);
        wp_nonce_field('zippicks_vibes_admin', '_wpnonce', false, true);
        wp_nonce_field('zippicks_vibes_admin', 'security', false, true);
        ?>
        <input type="hidden" name="vibe_id" value="0" id="vibe_id_hidden">
        <input type="hidden" name="action" value="zippicks_vibes_save" id="form_action_hidden">
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="vibe_name">
                            Name <span class="required">*</span>
                        </label>
                    </th>
                    <td>
                        <input type="text" 
                               id="vibe_name" 
                               name="vibe_name" 
                               value="Test Vibe" 
                               class="regular-text" 
                               required
                               maxlength="100">
                        <p class="description">The display name for this vibe.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="vibe_description">Description</label>
                    </th>
                    <td>
                        <textarea id="vibe_description" 
                                  name="vibe_description" 
                                  rows="3" 
                                  class="large-text"
                                  maxlength="500">Test description for debugging</textarea>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="vibe_color">Color</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="vibe_color" 
                               name="vibe_color" 
                               value="#194FAD" 
                               class="color-picker">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Status</th>
                    <td>
                        <label for="vibe_status">
                            <input type="checkbox" 
                                   id="vibe_status"
                                   name="vibe_status" 
                                   value="1" 
                                   checked>
                            Active
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary" id="submit-vibe">
                <span class="submit-text">Create Test Vibe</span>
            </button>
            <button type="button" class="button" onclick="testDirectAjax()">Test Direct AJAX</button>
        </p>
    </form>
</div>

<script>
// Console logging to HTML
const originalConsoleLog = console.log;
const originalConsoleError = console.error;
const originalConsoleWarn = console.warn;

function appendToLog(type, message) {
    const consoleDiv = document.getElementById('console-log');
    const timestamp = new Date().toLocaleTimeString();
    const logEntry = document.createElement('div');
    logEntry.innerHTML = `[${timestamp}] ${type.toUpperCase()}: ${message}`;
    logEntry.style.color = type === 'error' ? '#f00' : type === 'warn' ? '#ff0' : '#0f0';
    consoleDiv.appendChild(logEntry);
    consoleDiv.scrollTop = consoleDiv.scrollHeight;
}

console.log = function(...args) {
    originalConsoleLog.apply(console, args);
    appendToLog('log', args.join(' '));
};

console.error = function(...args) {
    originalConsoleError.apply(console, args);
    appendToLog('error', args.join(' '));
};

console.warn = function(...args) {
    originalConsoleWarn.apply(console, args);
    appendToLog('warn', args.join(' '));
};

function clearConsoleLog() {
    document.getElementById('console-log').innerHTML = '';
}

function testDirectAjax() {
    console.log('🧪 Testing direct AJAX call...');
    
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'POST',
        data: {
            action: 'zippicks_vibes_save',
            nonce: '<?php echo wp_create_nonce('zippicks_vibes_admin'); ?>',
            vibe_name: 'Direct AJAX Test',
            vibe_description: 'Testing direct AJAX call',
            vibe_color: '#FF0000',
            vibe_id: 0
        },
        success: function(response) {
            console.log('✅ Direct AJAX Success:', response);
        },
        error: function(xhr, status, error) {
            console.error('❌ Direct AJAX Error:', {xhr, status, error});
        }
    });
}

// Test basic functionality
console.log('🚀 Test page loaded');
console.log('📍 jQuery available:', typeof jQuery !== 'undefined');
console.log('📍 ZipPicks Admin available:', typeof window.zippicksVibesAdmin !== 'undefined');

if (typeof window.zippicksVibesAdmin !== 'undefined') {
    console.log('📋 ZipPicks Admin data:', window.zippicksVibesAdmin);
}

// Check for form
jQuery(document).ready(function($) {
    console.log('📋 DOM ready');
    const form = $('#vibe-form');
    console.log('📋 Form found:', form.length > 0);
    console.log('📋 Form element:', form[0]);
});
</script>

<?php wp_footer(); ?>

</body>
</html>