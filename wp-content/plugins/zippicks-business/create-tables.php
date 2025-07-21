<?php
/**
 * Manual table creation tool for ZipPicks Business
 *
 * This script can be accessed directly to create database tables
 * if automatic creation fails during plugin activation.
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

// Load plugin files
require_once(dirname(__FILE__) . '/includes/class-database.php');

$message = '';
$error = '';

// Handle form submission
if (isset($_POST['create_tables']) && wp_verify_nonce($_POST['_wpnonce'], 'create_business_tables')) {
    try {
        // Try to create tables
        $result = ZipPicks_Business_Database::create_tables_direct();
        
        if ($result) {
            $message = 'Tables created successfully!';
        } else {
            $error = 'Failed to create tables. Please check database permissions.';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Check current table status
$tables_exist = ZipPicks_Business_Database::verify_tables();
$table_status = array(
    'analytics' => $wpdb->get_var("SHOW TABLES LIKE '" . ZipPicks_Business_Database::get_analytics_table() . "'") ? true : false,
    'monetization' => $wpdb->get_var("SHOW TABLES LIKE '" . ZipPicks_Business_Database::get_monetization_table() . "'") ? true : false,
    'verification' => $wpdb->get_var("SHOW TABLES LIKE '" . ZipPicks_Business_Database::get_verification_table() . "'") ? true : false,
);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Create ZipPicks Business Tables</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
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
            margin-bottom: 30px;
        }
        .status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 4px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f9f9f9;
            font-weight: 600;
        }
        .status-icon {
            font-size: 18px;
        }
        .status-icon.success {
            color: #28a745;
        }
        .status-icon.error {
            color: #dc3545;
        }
        button {
            background: #0073aa;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
        }
        button:hover {
            background: #005a87;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .sql-container {
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .sql-container h3 {
            margin-top: 0;
        }
        .sql-container pre {
            background: white;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
            font-size: 13px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #0073aa;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create ZipPicks Business Tables</h1>
        
        <?php if ($message) : ?>
            <div class="status success"><?php echo esc_html($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error) : ?>
            <div class="status error"><?php echo esc_html($error); ?></div>
        <?php endif; ?>
        
        <h2>Table Status</h2>
        <table>
            <thead>
                <tr>
                    <th>Table Name</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo ZipPicks_Business_Database::get_analytics_table(); ?></td>
                    <td>
                        <?php if ($table_status['analytics']) : ?>
                            <span class="status-icon success">✓</span> Exists
                        <?php else : ?>
                            <span class="status-icon error">✗</span> Missing
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><?php echo ZipPicks_Business_Database::get_monetization_table(); ?></td>
                    <td>
                        <?php if ($table_status['monetization']) : ?>
                            <span class="status-icon success">✓</span> Exists
                        <?php else : ?>
                            <span class="status-icon error">✗</span> Missing
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><?php echo ZipPicks_Business_Database::get_verification_table(); ?></td>
                    <td>
                        <?php if ($table_status['verification']) : ?>
                            <span class="status-icon success">✓</span> Exists
                        <?php else : ?>
                            <span class="status-icon error">✗</span> Missing
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php if (!$tables_exist) : ?>
            <form method="post">
                <?php wp_nonce_field('create_business_tables'); ?>
                <button type="submit" name="create_tables">Create Missing Tables</button>
            </form>
        <?php else : ?>
            <div class="status success">All tables exist!</div>
        <?php endif; ?>
        
        <div class="sql-container">
            <h3>Manual SQL Creation</h3>
            <p>If automatic creation fails, you can run this SQL in phpMyAdmin:</p>
            <pre><?php echo esc_html(ZipPicks_Business_Database::get_schema_sql()); ?></pre>
        </div>
        
        <a href="<?php echo admin_url('admin.php?page=zippicks-business'); ?>" class="back-link">
            ← Back to ZipPicks Business Dashboard
        </a>
    </div>
</body>
</html>