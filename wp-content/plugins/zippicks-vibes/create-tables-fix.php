<?php
/**
 * Manual Database Table Creation Tool for ZipPicks Vibes
 * 
 * This tool provides a web-based interface for manually creating database tables
 * in case automatic creation fails. This follows CLAUDE.md database pattern requirements.
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

// Load WordPress environment
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die('Error: Cannot find wp-load.php. Please ensure this file is in the correct plugin directory.');
}
require_once($wp_load_path);

// Security check - must be admin
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access. You must be an administrator to use this tool.');
}

// Include plugin files
require_once(plugin_dir_path(__FILE__) . 'src/Database/Installer.php');

use ZipPicksVibes\Database\Installer;

// Handle form submission
$message = '';
$error = '';
if (isset($_POST['create_tables']) && wp_verify_nonce($_POST['_wpnonce'], 'zippicks_create_tables')) {
    try {
        // Load WordPress upgrade functions
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables
        Installer::install();
        
        // Verify creation
        if (Installer::tables_exist()) {
            $message = 'All tables created successfully!';
        } else {
            $error = 'Some tables could not be created. Please check the SQL below and create manually.';
        }
    } catch (Exception $e) {
        $error = 'Error creating tables: ' . esc_html($e->getMessage());
    }
}

// Check current table status
global $wpdb;
$tables = [
    'zippicks_vibes' => 'Vibes',
    'zippicks_vibe_categories' => 'Vibe Categories',
    'zippicks_vibe_category_assignments' => 'Category Assignments',
    'zippicks_waitlist' => 'Waitlist',
    'zippicks_scrape_log' => 'Scrape Log',
    'zippicks_blocked_ips' => 'Blocked IPs',
    'zippicks_security_log' => 'Security Log',
    'zippicks_rate_limit_log' => 'Rate Limit Log',
    'zippicks_security_events' => 'Security Events',
    'zippicks_audit_log' => 'Audit Log',
    'zippicks_performance_metrics' => 'Performance Metrics'
];

$table_status = [];
foreach ($tables as $table => $name) {
    $full_table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
    $table_status[$table] = [
        'name' => $name,
        'exists' => $exists,
        'full_name' => $full_table_name
    ];
}

// Get schema SQL
$schema_sql = Installer::get_schema_sql();
?>
<!DOCTYPE html>
<html>
<head>
    <title>ZipPicks Vibes - Database Table Creation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007cba;
            padding-bottom: 10px;
        }
        .notice {
            padding: 12px;
            margin: 20px 0;
            border-radius: 4px;
            border-left: 4px solid;
        }
        .notice-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .notice-error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .status-exists {
            color: #28a745;
            font-weight: 600;
        }
        .status-missing {
            color: #dc3545;
            font-weight: 600;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .button:hover {
            background: #005a87;
        }
        .button-danger {
            background: #dc3545;
        }
        .button-danger:hover {
            background: #c82333;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #dee2e6;
        }
        .section {
            margin: 30px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ZipPicks Vibes - Database Table Creation Tool</h1>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><?php echo esc_html($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="notice notice-error"><?php echo esc_html($error); ?></div>
        <?php endif; ?>
        
        <div class="section">
            <h2>Current Table Status</h2>
            <table>
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Full Name</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($table_status as $table => $info): ?>
                    <tr>
                        <td><?php echo esc_html($info['name']); ?></td>
                        <td><code><?php echo esc_html($info['full_name']); ?></code></td>
                        <td class="<?php echo $info['exists'] ? 'status-exists' : 'status-missing'; ?>">
                            <?php echo $info['exists'] ? '✓ Exists' : '✗ Missing'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php
        $all_exist = true;
        foreach ($table_status as $info) {
            if (!$info['exists']) {
                $all_exist = false;
                break;
            }
        }
        ?>
        
        <?php if (!$all_exist): ?>
        <div class="section">
            <h2>Create Missing Tables</h2>
            <p>Click the button below to attempt automatic table creation:</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('zippicks_create_tables'); ?>
                <button type="submit" name="create_tables" class="button">Create Missing Tables</button>
            </form>
            
            <div class="warning">
                <strong>Note:</strong> If automatic creation fails, you can use the SQL below in phpMyAdmin or your database management tool.
            </div>
        </div>
        <?php else: ?>
        <div class="notice notice-success">
            <strong>All tables exist!</strong> Your database is properly configured.
        </div>
        <?php endif; ?>
        
        <div class="section">
            <h2>Manual SQL Creation</h2>
            <p>If automatic creation fails, copy and run this SQL in your database management tool:</p>
            
            <h3>Missing Tables SQL:</h3>
            <pre><?php 
            // Generate SQL only for missing tables
            $missing_sql = [];
            $charset_collate = $wpdb->get_charset_collate();
            
            // Check each table and add SQL if missing
            if (!$table_status['blocked_ips']['exists']) {
                $missing_sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_blocked_ips (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    reason TEXT,
    blocked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    blocked_by BIGINT(20) UNSIGNED,
    PRIMARY KEY (id),
    UNIQUE KEY idx_ip (ip_address),
    INDEX idx_expires (expires_at)
) $charset_collate;";
            }
            
            // Add other missing tables from the full schema
            echo esc_html(implode("\n\n", $missing_sql) ?: "-- All tables exist");
            ?></pre>
            
            <details>
                <summary><strong>View Complete Schema SQL</strong></summary>
                <pre><?php echo esc_html($schema_sql); ?></pre>
            </details>
        </div>
        
        <div class="section">
            <h2>Additional Options</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=zippicks-vibes'); ?>" class="button">Back to Admin</a>
                <a href="<?php echo admin_url('plugins.php'); ?>" class="button">Back to Plugins</a>
            </p>
        </div>
    </div>
</body>
</html>