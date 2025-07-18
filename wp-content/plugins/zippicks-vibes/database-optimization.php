<?php
/**
 * Database Optimization Script for ZipPicks Vibes
 * 
 * Creates performance indexes and optimizes database structure
 * Run this script to improve query performance
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

// Check if user can manage options
if (!current_user_can('manage_options')) {
    die('Access denied');
}

// Set execution time limit
set_time_limit(300);

// Start output
?>
<!DOCTYPE html>
<html>
<head>
    <title>ZipPicks Vibes - Database Optimization</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            max-width: 1200px;
            margin: 40px auto;
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
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
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
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #3498db;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px;
        }
        .button:hover {
            background: #2980b9;
        }
        .button.danger {
            background: #e74c3c;
        }
        .button.danger:hover {
            background: #c0392b;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .sql-query {
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 ZipPicks Vibes - Database Optimization</h1>
        
        <?php
        global $wpdb;
        $errors = [];
        $successes = [];
        $warnings = [];
        
        // Check if optimization is requested
        $action = $_GET['action'] ?? '';
        $nonce = $_GET['_wpnonce'] ?? '';
        
        if ($action === 'optimize' && wp_verify_nonce($nonce, 'zippicks_db_optimize')) {
            
            echo '<div class="status info">Starting database optimization...</div>';
            
            // Define indexes to create
            $indexes = [
                // Vibes table indexes
                [
                    'table' => 'zippicks_vibes',
                    'indexes' => [
                        ['name' => 'idx_slug_active', 'columns' => '(slug, is_active)'],
                        ['name' => 'idx_order_position', 'columns' => '(order_position)'],
                        ['name' => 'idx_created_date', 'columns' => '(created_at)'],
                        ['name' => 'idx_is_featured', 'columns' => '(is_featured)'] // If column exists
                    ]
                ],
                // Scrape log indexes
                [
                    'table' => 'zippicks_scrape_log',
                    'indexes' => [
                        ['name' => 'idx_ip_created', 'columns' => '(ip_address, created_at)'],
                        ['name' => 'idx_endpoint_date', 'columns' => '(endpoint, created_at)'],
                        ['name' => 'idx_status_date', 'columns' => '(response_code, created_at)']
                    ]
                ],
                // Category assignments indexes
                [
                    'table' => 'zippicks_vibe_category_assignments',
                    'indexes' => [
                        ['name' => 'idx_category_vibe', 'columns' => '(category_id, vibe_id)'],
                        ['name' => 'idx_vibe_category', 'columns' => '(vibe_id, category_id)']
                    ]
                ],
                // Security log indexes
                [
                    'table' => 'zippicks_security_log',
                    'indexes' => [
                        ['name' => 'idx_event_time', 'columns' => '(event_type, created_at)'],
                        ['name' => 'idx_severity_time', 'columns' => '(severity, created_at)'],
                        ['name' => 'idx_user_time', 'columns' => '(user_id, created_at)']
                    ]
                ],
                // Rate limit log indexes
                [
                    'table' => 'zippicks_rate_limit_log',
                    'indexes' => [
                        ['name' => 'idx_ip_endpoint_window', 'columns' => '(ip_address, endpoint, window_start)']
                    ]
                ],
                // Security events indexes
                [
                    'table' => 'zippicks_security_events',
                    'indexes' => [
                        ['name' => 'idx_event_ip', 'columns' => '(event_type, ip_address)'],
                        ['name' => 'idx_created_event', 'columns' => '(created_at, event_type)']
                    ]
                ],
                // Waitlist indexes
                [
                    'table' => 'zippicks_waitlist',
                    'indexes' => [
                        ['name' => 'idx_email_vibe', 'columns' => '(user_email, vibe_id)'],
                        ['name' => 'idx_zip_created', 'columns' => '(zip_code, created_at)'],
                        ['name' => 'idx_city_state', 'columns' => '(city, state)']
                    ]
                ]
            ];
            
            // Create indexes
            foreach ($indexes as $table_config) {
                $table = $wpdb->prefix . $table_config['table'];
                
                // Check if table exists
                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                    $warnings[] = "Table $table does not exist, skipping indexes";
                    continue;
                }
                
                echo "<h3>Optimizing table: $table</h3>";
                echo "<table>";
                echo "<tr><th>Index</th><th>Status</th><th>Details</th></tr>";
                
                foreach ($table_config['indexes'] as $index) {
                    $index_name = $index['name'];
                    $columns = $index['columns'];
                    
                    // Check if index already exists
                    $existing = $wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = '$index_name'");
                    
                    if (!empty($existing)) {
                        echo "<tr><td>$index_name</td><td>✅ Exists</td><td>Index already exists</td></tr>";
                        continue;
                    }
                    
                    // Check if columns exist before creating index
                    preg_match_all('/\(([^)]+)\)/', $columns, $matches);
                    $column_list = explode(',', $matches[1][0]);
                    $columns_exist = true;
                    
                    foreach ($column_list as $col) {
                        $col = trim($col);
                        $col_exists = $wpdb->get_var("SHOW COLUMNS FROM $table LIKE '$col'");
                        if (!$col_exists) {
                            $columns_exist = false;
                            echo "<tr><td>$index_name</td><td>⚠️ Skipped</td><td>Column '$col' does not exist</td></tr>";
                            break;
                        }
                    }
                    
                    if (!$columns_exist) {
                        continue;
                    }
                    
                    // Create index
                    $sql = "ALTER TABLE $table ADD INDEX $index_name $columns";
                    $result = $wpdb->query($sql);
                    
                    if ($result === false) {
                        $error = $wpdb->last_error;
                        echo "<tr><td>$index_name</td><td>❌ Failed</td><td>$error</td></tr>";
                        $errors[] = "Failed to create index $index_name on $table: $error";
                    } else {
                        echo "<tr><td>$index_name</td><td>✅ Created</td><td>Index created successfully</td></tr>";
                        $successes[] = "Created index $index_name on $table";
                    }
                }
                
                echo "</table>";
            }
            
            // Analyze tables for optimization
            echo "<h3>Analyzing Tables</h3>";
            echo "<table>";
            echo "<tr><th>Table</th><th>Rows</th><th>Data Size</th><th>Index Size</th><th>Status</th></tr>";
            
            $tables = [
                'zippicks_vibes',
                'zippicks_vibe_categories',
                'zippicks_vibe_category_assignments',
                'zippicks_waitlist',
                'zippicks_scrape_log',
                'zippicks_security_log',
                'zippicks_rate_limit_log',
                'zippicks_security_events'
            ];
            
            foreach ($tables as $table_name) {
                $table = $wpdb->prefix . $table_name;
                
                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                    continue;
                }
                
                // Get table stats
                $stats = $wpdb->get_row("
                    SELECT 
                        table_rows as rows,
                        ROUND(data_length/1024/1024, 2) as data_mb,
                        ROUND(index_length/1024/1024, 2) as index_mb
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = '$table'
                ");
                
                // Optimize table
                $wpdb->query("OPTIMIZE TABLE $table");
                
                echo "<tr>";
                echo "<td>$table</td>";
                echo "<td>" . number_format($stats->rows ?? 0) . "</td>";
                echo "<td>{$stats->data_mb} MB</td>";
                echo "<td>{$stats->index_mb} MB</td>";
                echo "<td>✅ Optimized</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            // Display summary
            if (!empty($successes)) {
                echo '<div class="status success">';
                echo '<h3>✅ Optimization Successful</h3>';
                echo '<ul>';
                foreach ($successes as $success) {
                    echo "<li>$success</li>";
                }
                echo '</ul>';
                echo '</div>';
            }
            
            if (!empty($warnings)) {
                echo '<div class="status warning">';
                echo '<h3>⚠️ Warnings</h3>';
                echo '<ul>';
                foreach ($warnings as $warning) {
                    echo "<li>$warning</li>";
                }
                echo '</ul>';
                echo '</div>';
            }
            
            if (!empty($errors)) {
                echo '<div class="status error">';
                echo '<h3>❌ Errors</h3>';
                echo '<ul>';
                foreach ($errors as $error) {
                    echo "<li>$error</li>";
                }
                echo '</ul>';
                echo '</div>';
            }
            
        } else {
            // Show optimization options
            ?>
            <div class="status info">
                <h3>Database Optimization Overview</h3>
                <p>This tool will create performance indexes and optimize your ZipPicks Vibes database tables.</p>
                <p><strong>What will be optimized:</strong></p>
                <ul>
                    <li>Create missing performance indexes</li>
                    <li>Optimize table storage</li>
                    <li>Analyze table statistics</li>
                    <li>Improve query performance</li>
                </ul>
            </div>
            
            <h3>Current Database Status</h3>
            <table>
                <tr>
                    <th>Table</th>
                    <th>Exists</th>
                    <th>Rows</th>
                    <th>Indexes</th>
                </tr>
                <?php
                $tables = [
                    'zippicks_vibes' => 'Vibes',
                    'zippicks_vibe_categories' => 'Categories',
                    'zippicks_vibe_category_assignments' => 'Assignments',
                    'zippicks_waitlist' => 'Waitlist',
                    'zippicks_scrape_log' => 'Scrape Log',
                    'zippicks_security_log' => 'Security Log',
                    'zippicks_rate_limit_log' => 'Rate Limit Log',
                    'zippicks_security_events' => 'Security Events'
                ];
                
                foreach ($tables as $table_name => $display_name) {
                    $table = $wpdb->prefix . $table_name;
                    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
                    
                    echo "<tr>";
                    echo "<td>$display_name</td>";
                    
                    if ($exists) {
                        $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                        $index_count = $wpdb->get_var("SELECT COUNT(DISTINCT Key_name) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '$table' AND Key_name != 'PRIMARY'");
                        
                        echo "<td>✅ Yes</td>";
                        echo "<td>" . number_format($row_count) . "</td>";
                        echo "<td>$index_count</td>";
                    } else {
                        echo "<td>❌ No</td>";
                        echo "<td>-</td>";
                        echo "<td>-</td>";
                    }
                    
                    echo "</tr>";
                }
                ?>
            </table>
            
            <div class="status warning">
                <h3>⚠️ Important Notes</h3>
                <ul>
                    <li>This process may take several minutes depending on table sizes</li>
                    <li>Your site may be slower during optimization</li>
                    <li>Always backup your database before optimization</li>
                    <li>Indexes will only be created if they don't already exist</li>
                </ul>
            </div>
            
            <p>
                <a href="?action=optimize&_wpnonce=<?php echo wp_create_nonce('zippicks_db_optimize'); ?>" 
                   class="button" 
                   onclick="return confirm('Are you sure you want to optimize the database? This may take several minutes.');">
                    🚀 Start Optimization
                </a>
                <a href="<?php echo admin_url('admin.php?page=zippicks-vibes'); ?>" class="button">
                    ← Back to Admin
                </a>
            </p>
            <?php
        }
        ?>
        
        <hr style="margin: 40px 0;">
        
        <h3>Manual Index Creation</h3>
        <p>If automatic optimization fails, you can run these SQL queries manually in phpMyAdmin:</p>
        
        <pre class="sql-query">
-- Vibes table indexes
ALTER TABLE <?php echo $wpdb->prefix; ?>zippicks_vibes ADD INDEX idx_slug_active (slug, is_active);
ALTER TABLE <?php echo $wpdb->prefix; ?>zippicks_vibes ADD INDEX idx_order_position (order_position);
ALTER TABLE <?php echo $wpdb->prefix; ?>zippicks_vibes ADD INDEX idx_created_date (created_at);

-- Scrape log indexes
ALTER TABLE <?php echo $wpdb->prefix; ?>zippicks_scrape_log ADD INDEX idx_ip_created (ip_address, created_at);
ALTER TABLE <?php echo $wpdb->prefix; ?>zippicks_scrape_log ADD INDEX idx_endpoint_date (endpoint, created_at);

-- Category assignments indexes
ALTER TABLE <?php echo $wpdb->prefix; ?>zippicks_vibe_category_assignments ADD INDEX idx_category_vibe (category_id, vibe_id);
ALTER TABLE <?php echo $wpdb->prefix; ?>zippicks_vibe_category_assignments ADD INDEX idx_vibe_category (vibe_id, category_id);

-- Security indexes
ALTER TABLE <?php echo $wpdb->prefix; ?>zippicks_security_log ADD INDEX idx_event_time (event_type, created_at);
ALTER TABLE <?php echo $wpdb->prefix; ?>zippicks_security_events ADD INDEX idx_event_ip (event_type, ip_address);

-- Optimize all tables
OPTIMIZE TABLE <?php echo $wpdb->prefix; ?>zippicks_vibes;
OPTIMIZE TABLE <?php echo $wpdb->prefix; ?>zippicks_vibe_categories;
OPTIMIZE TABLE <?php echo $wpdb->prefix; ?>zippicks_vibe_category_assignments;
OPTIMIZE TABLE <?php echo $wpdb->prefix; ?>zippicks_waitlist;
OPTIMIZE TABLE <?php echo $wpdb->prefix; ?>zippicks_scrape_log;
OPTIMIZE TABLE <?php echo $wpdb->prefix; ?>zippicks_security_log;
OPTIMIZE TABLE <?php echo $wpdb->prefix; ?>zippicks_rate_limit_log;
OPTIMIZE TABLE <?php echo $wpdb->prefix; ?>zippicks_security_events;
        </pre>
    </div>
</body>
</html>