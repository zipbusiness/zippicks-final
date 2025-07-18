<?php
/**
 * Table verification script for ZipPicks Business
 *
 * This script verifies that all required database tables exist
 * and displays their current status.
 */

// Load WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die('WordPress not found. Please adjust the path to wp-load.php');
}
require_once($wp_load_path);

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to access this page.');
}

// Load plugin database class
require_once(dirname(__FILE__) . '/includes/class-database.php');

// Get table status
global $wpdb;

$tables = array(
    'Analytics' => ZipPicks_Business_Database::get_analytics_table(),
    'Monetization' => ZipPicks_Business_Database::get_monetization_table(),
    'Verification' => ZipPicks_Business_Database::get_verification_table(),
    'Scrape Log' => ZipPicks_Business_Database::get_scrape_log_table()
);

$table_status = array();
$all_exist = true;

foreach ($tables as $name => $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    
    if ($exists) {
        // Get table info
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $size = $wpdb->get_var("
            SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb 
            FROM information_schema.TABLES 
            WHERE table_schema = '" . DB_NAME . "' 
            AND table_name = '$table'"
        );
        
        $table_status[$name] = array(
            'exists' => true,
            'table_name' => $table,
            'row_count' => $count,
            'size_mb' => $size ?: '0.00'
        );
    } else {
        $table_status[$name] = array(
            'exists' => false,
            'table_name' => $table,
            'row_count' => 0,
            'size_mb' => '0.00'
        );
        $all_exist = false;
    }
}

// Check if tables need repair
$repair_needed = false;
if ($all_exist) {
    foreach ($tables as $name => $table) {
        $check = $wpdb->get_row("CHECK TABLE $table");
        if ($check && $check->Msg_text !== 'OK') {
            $repair_needed = true;
            $table_status[$name]['needs_repair'] = true;
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>ZipPicks Business - Table Verification</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #23282d;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .status-card {
            background: #f9f9f9;
            border: 1px solid #e1e1e1;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
        }
        .status-card.success {
            border-color: #46b450;
            background: #f0f8f0;
        }
        .status-card.error {
            border-color: #dc3232;
            background: #fef5f5;
        }
        .status-card h3 {
            margin: 0 0 10px;
            font-size: 18px;
        }
        .status-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .status-icon.success {
            color: #46b450;
        }
        .status-icon.error {
            color: #dc3232;
        }
        .table-details {
            margin: 10px 0;
            font-size: 14px;
            color: #666;
        }
        .table-name {
            font-family: monospace;
            font-size: 12px;
            color: #999;
            word-break: break-all;
        }
        .summary {
            background: #f0f8ff;
            border: 1px solid #b8d4e8;
            border-radius: 6px;
            padding: 20px;
            margin: 30px 0;
        }
        .summary.error {
            background: #fef5f5;
            border-color: #f5c6cb;
        }
        .summary h2 {
            margin: 0 0 10px;
            font-size: 20px;
        }
        .action-buttons {
            margin-top: 30px;
            text-align: center;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 5px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
        }
        .button:hover {
            background: #005a87;
        }
        .button.secondary {
            background: #666;
        }
        .button.secondary:hover {
            background: #555;
        }
        .debug-info {
            margin-top: 40px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 6px;
            font-family: monospace;
            font-size: 13px;
        }
        .debug-info h3 {
            margin-top: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        pre {
            background: white;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ZipPicks Business - Table Verification</h1>
        <p class="subtitle">Database table status and health check</p>
        
        <div class="status-grid">
            <?php foreach ($table_status as $name => $status) : ?>
                <div class="status-card <?php echo $status['exists'] ? 'success' : 'error'; ?>">
                    <div class="status-icon <?php echo $status['exists'] ? 'success' : 'error'; ?>">
                        <?php echo $status['exists'] ? '✓' : '✗'; ?>
                    </div>
                    <h3><?php echo esc_html($name); ?> Table</h3>
                    <div class="table-details">
                        <?php if ($status['exists']) : ?>
                            <p><strong><?php echo number_format($status['row_count']); ?></strong> rows</p>
                            <p><strong><?php echo $status['size_mb']; ?> MB</strong> size</p>
                            <?php if (!empty($status['needs_repair'])) : ?>
                                <p style="color: #f56e28;">⚠️ Needs repair</p>
                            <?php endif; ?>
                        <?php else : ?>
                            <p style="color: #dc3232;">Table does not exist</p>
                        <?php endif; ?>
                    </div>
                    <div class="table-name"><?php echo esc_html($status['table_name']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($all_exist) : ?>
            <div class="summary">
                <h2>✅ All Tables Verified</h2>
                <p>All required ZipPicks Business database tables exist and are accessible.</p>
                <?php if ($repair_needed) : ?>
                    <p style="color: #f56e28;">⚠️ Some tables may need repair. Consider running database optimization.</p>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="summary error">
                <h2>❌ Missing Tables Detected</h2>
                <p>Some required database tables are missing. The plugin may not function correctly.</p>
                <p>Please use the button below to create the missing tables.</p>
            </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <?php if (!$all_exist) : ?>
                <a href="<?php echo admin_url('admin.php?page=zippicks-business-create-tables'); ?>" class="button">
                    Create Missing Tables
                </a>
            <?php endif; ?>
            
            <a href="<?php echo admin_url('admin.php?page=zippicks-business'); ?>" class="button secondary">
                Back to Dashboard
            </a>
        </div>
        
        <div class="debug-info">
            <h3>Debug Information</h3>
            <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
            <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
            <p><strong>MySQL Version:</strong> <?php echo $wpdb->db_version(); ?></p>
            <p><strong>Table Prefix:</strong> <?php echo $wpdb->prefix; ?></p>
            <p><strong>Database Name:</strong> <?php echo DB_NAME; ?></p>
            <p><strong>Plugin Version:</strong> <?php echo defined('ZIPPICKS_BUSINESS_VERSION') ? ZIPPICKS_BUSINESS_VERSION : 'Not loaded'; ?></p>
            
            <?php if (!$all_exist) : ?>
                <h4>SQL for Missing Tables</h4>
                <p>You can also create tables manually in phpMyAdmin using this SQL:</p>
                <pre><?php echo esc_html(ZipPicks_Business_Database::get_schema_sql()); ?></pre>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>