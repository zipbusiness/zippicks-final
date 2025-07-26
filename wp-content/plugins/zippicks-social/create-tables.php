<?php
/**
 * Manual table creation tool for ZipPicks Social
 * 
 * Access this file directly via browser if automatic table creation fails
 * URL: /wp-content/plugins/zippicks-social/create-tables.php
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 */

// Load WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../../../wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Error: Could not load WordPress. Please check the file path.');
}

// Check permissions
if (!current_user_can('manage_options')) {
    die('Error: You must be logged in as an administrator to run this script.');
}

// Load required files
require_once __DIR__ . '/includes/class-database.php';
require_once __DIR__ . '/includes/class-database-migrator.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>ZipPicks Social - Create Tables</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .container {
            background: #fff;
            padding: 30px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 { color: #1d2327; }
        .status { 
            padding: 10px; 
            margin: 10px 0; 
            border-radius: 4px;
            font-weight: 500;
        }
        .success { 
            background: #d4edda; 
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error { 
            background: #f8d7da; 
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info { 
            background: #d1ecf1; 
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .warning { 
            background: #fff3cd; 
            color: #856404;
            border: 1px solid #ffeeba;
        }
        pre {
            background: #f6f7f7;
            padding: 15px;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.5;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #0073aa;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .button:hover {
            background: #005a87;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #dcdcde;
        }
        th {
            background: #f6f7f7;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ZipPicks Social - Database Setup</h1>
        
        <?php
        // Check migration status
        $migration_status = ZipPicks_Social_Database_Migrator::get_migration_status();
        $tables_exist = ZipPicks_Social_Database::verify_tables();
        
        echo '<div class="status info">';
        echo '<strong>Current Database Version:</strong> ' . esc_html($migration_status['current_version']) . '<br>';
        echo '<strong>Target Database Version:</strong> ' . esc_html($migration_status['target_version']) . '<br>';
        echo '<strong>Tables Exist:</strong> ' . ($tables_exist ? 'Yes' : 'No');
        echo '</div>';
        
        // Handle actions
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'migrate':
                    echo '<h2>Running Migration...</h2>';
                    $result = ZipPicks_Social_Database_Migrator::run_migrations();
                    
                    if ($result['status'] === 'success') {
                        echo '<div class="status success">Migration completed successfully!</div>';
                        echo '<pre>' . print_r($result, true) . '</pre>';
                    } elseif ($result['status'] === 'up_to_date') {
                        echo '<div class="status info">Database is already up to date.</div>';
                    } else {
                        echo '<div class="status error">Migration failed: ' . esc_html($result['error'] ?? 'Unknown error') . '</div>';
                        echo '<pre>' . print_r($result, true) . '</pre>';
                    }
                    break;
                    
                case 'create':
                    echo '<h2>Creating Tables...</h2>';
                    $results = ZipPicks_Social_Database::create_tables();
                    
                    echo '<div class="status success">Table creation completed!</div>';
                    echo '<pre>' . print_r($results, true) . '</pre>';
                    break;
                    
                case 'verify':
                    echo '<h2>Verifying Tables...</h2>';
                    global $wpdb;
                    
                    $tables = [
                        'follows' => $wpdb->prefix . 'zippicks_follows',
                        'follow_stats' => $wpdb->prefix . 'zippicks_follow_stats',
                        'activities' => $wpdb->prefix . 'zippicks_activities',
                        'suggestions' => $wpdb->prefix . 'zippicks_follow_suggestions'
                    ];
                    
                    echo '<table>';
                    echo '<thead><tr><th>Table</th><th>Name</th><th>Status</th><th>Rows</th></tr></thead>';
                    echo '<tbody>';
                    
                    foreach ($tables as $key => $table_name) {
                        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
                        $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}") : 0;
                        
                        echo '<tr>';
                        echo '<td>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</td>';
                        echo '<td><code>' . esc_html($table_name) . '</code></td>';
                        echo '<td>' . ($exists ? '<span style="color: #00a32a;">✓ Exists</span>' : '<span style="color: #d63638;">✗ Missing</span>') . '</td>';
                        echo '<td>' . ($exists ? number_format($count) : '-') . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table>';
                    break;
            }
        }
        ?>
        
        <h2>Available Actions</h2>
        
        <?php if ($migration_status['needs_migration']): ?>
            <div class="status warning">
                Database migration is required. This is the recommended method.
            </div>
            <a href="?action=migrate" class="button">Run Migration</a>
        <?php endif; ?>
        
        <?php if (!$tables_exist): ?>
            <div class="status warning">
                Tables are missing. You can create them manually.
            </div>
            <a href="?action=create" class="button">Create Tables</a>
        <?php endif; ?>
        
        <a href="?action=verify" class="button">Verify Tables</a>
        
        <h2>Manual SQL</h2>
        <p>If the automatic methods fail, you can run these SQL statements manually in phpMyAdmin:</p>
        
        <details>
            <summary style="cursor: pointer; color: #0073aa;">Show SQL Statements</summary>
            <pre><?php
            $schemas = ZipPicks_Social_Database::get_schema_sql();
            foreach ($schemas as $name => $sql) {
                echo "-- " . esc_html(ucwords(str_replace('_', ' ', $name))) . "\n";
                echo esc_html($sql) . "\n\n";
            }
            ?></pre>
        </details>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #dcdcde;">
            <p><a href="<?php echo admin_url('admin.php?page=zippicks-social'); ?>">← Back to Plugin Settings</a></p>
        </div>
    </div>
</body>
</html>