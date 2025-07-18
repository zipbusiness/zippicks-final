<?php
/**
 * ZipPicks Vibes - Database Table Verification Tool
 * 
 * This script verifies the existence and integrity of all required database tables.
 * It provides detailed information about table structure and offers repair options.
 * Supports query string parameters for targeted checks and logging to file.
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}

// Get database instance
global $wpdb;

// Check for query string parameters
$check_specific_table = isset($_GET['check']) ? sanitize_text_field($_GET['check']) : '';
$log_to_file = isset($_GET['log']) && $_GET['log'] === '1';
$show_structure = isset($_GET['structure']) && $_GET['structure'] === '1';
$return_json = isset($_GET['format']) && $_GET['format'] === 'json';

// Initialize log content
$log_content = [];
$log_content[] = "ZipPicks Vibes - Database Verification Report";
$log_content[] = "Generated: " . date('Y-m-d H:i:s');
$log_content[] = "WordPress Version: " . get_bloginfo('version');
$log_content[] = "PHP Version: " . PHP_VERSION;
$log_content[] = "Database Version: " . $wpdb->db_version();
$log_content[] = "Table Prefix: " . $wpdb->prefix;
if (is_multisite()) {
    $log_content[] = "Multisite: Yes (Blog ID: " . get_current_blog_id() . ")";
} else {
    $log_content[] = "Multisite: No";
}
$log_content[] = str_repeat('=', 50);

// Define expected tables and their structures
$expected_tables = [
    'zippicks_vibes' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'name' => 'varchar(255) NOT NULL',
            'slug' => 'varchar(255) NOT NULL',
            'description' => 'text',
            'icon' => 'varchar(255) DEFAULT \'default\'',
            'color' => 'varchar(7) DEFAULT \'#000000\'',
            'order_position' => 'int(11) DEFAULT 0',
            'is_active' => 'tinyint(1) DEFAULT 1',
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'PRIMARY' => 'PRIMARY KEY (id)',
            'slug' => 'UNIQUE KEY slug (slug)',
            'is_active' => 'KEY is_active (is_active)',
            'order_position' => 'KEY order_position (order_position)'
        ]
    ],
    'zippicks_vibe_categories' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'name' => 'varchar(255) NOT NULL',
            'slug' => 'varchar(255) NOT NULL',
            'description' => 'text',
            'parent_id' => 'int(11) DEFAULT 0',
            'order_position' => 'int(11) DEFAULT 0',
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'PRIMARY' => 'PRIMARY KEY (id)',
            'slug' => 'UNIQUE KEY slug (slug)',
            'parent_id' => 'KEY parent_id (parent_id)'
        ]
    ],
    'zippicks_vibe_category_assignments' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'vibe_id' => 'int(11) NOT NULL',
            'category_id' => 'int(11) NOT NULL',
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'PRIMARY' => 'PRIMARY KEY (id)',
            'vibe_category' => 'UNIQUE KEY vibe_category (vibe_id, category_id)',
            'vibe_id' => 'KEY vibe_id (vibe_id)',
            'category_id' => 'KEY category_id (category_id)'
        ]
    ],
    'zippicks_waitlist' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'vibe_id' => 'int(11) NOT NULL',
            'zip_code' => 'varchar(10) NOT NULL',
            'user_id' => 'bigint(20) DEFAULT NULL',
            'email' => 'varchar(255) DEFAULT NULL',
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'PRIMARY' => 'PRIMARY KEY (id)',
            'vibe_zip' => 'KEY vibe_zip (vibe_id, zip_code)',
            'user_id' => 'KEY user_id (user_id)'
        ]
    ],
    'zippicks_scrape_log' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'ip_address' => 'varchar(45) NOT NULL',
            'request_path' => 'varchar(255) NOT NULL',
            'user_agent' => 'text',
            'referrer' => 'varchar(255) DEFAULT NULL',
            'session_id' => 'varchar(255) DEFAULT NULL',
            'is_bot' => 'tinyint(1) DEFAULT 0',
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'PRIMARY' => 'PRIMARY KEY (id)',
            'ip_date' => 'KEY ip_date (ip_address, created_at)',
            'session_id' => 'KEY session_id (session_id)'
        ]
    ],
    'zippicks_security_log' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'event_type' => 'varchar(50) NOT NULL',
            'ip_address' => 'varchar(45) NOT NULL',
            'user_id' => 'bigint(20) DEFAULT NULL',
            'details' => 'text',
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'PRIMARY' => 'PRIMARY KEY (id)',
            'event_date' => 'KEY event_date (event_type, created_at)',
            'ip_address' => 'KEY ip_address (ip_address)'
        ]
    ],
    'zippicks_rate_limit_log' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'identifier' => 'varchar(255) NOT NULL',
            'endpoint' => 'varchar(255) NOT NULL',
            'count' => 'int(11) DEFAULT 1',
            'window_start' => 'datetime NOT NULL',
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'PRIMARY' => 'PRIMARY KEY (id)',
            'identifier_endpoint' => 'UNIQUE KEY identifier_endpoint (identifier, endpoint, window_start)',
            'window_start' => 'KEY window_start (window_start)'
        ]
    ],
    'zippicks_security_events' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'event_type' => 'varchar(50) NOT NULL',
            'severity' => 'varchar(20) NOT NULL',
            'source' => 'varchar(100) NOT NULL',
            'details' => 'text',
            'ip_address' => 'varchar(45) DEFAULT NULL',
            'user_id' => 'bigint(20) DEFAULT NULL',
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'PRIMARY' => 'PRIMARY KEY (id)',
            'event_type' => 'KEY event_type (event_type)',
            'severity' => 'KEY severity (severity)',
            'created_at' => 'KEY created_at (created_at)'
        ]
    ],
    'zippicks_audit_log' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'action' => 'varchar(100) NOT NULL',
            'object_type' => 'varchar(50) NOT NULL',
            'object_id' => 'int(11) NOT NULL',
            'user_id' => 'bigint(20) NOT NULL',
            'old_value' => 'text',
            'new_value' => 'text',
            'ip_address' => 'varchar(45) NOT NULL',
            'user_agent' => 'text',
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'PRIMARY' => 'PRIMARY KEY (id)',
            'action' => 'KEY action (action)',
            'object' => 'KEY object (object_type, object_id)',
            'user_id' => 'KEY user_id (user_id)',
            'created_at' => 'KEY created_at (created_at)'
        ]
    ],
    'zippicks_performance_metrics' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'metric_name' => 'varchar(100) NOT NULL',
            'value' => 'float NOT NULL',
            'context' => 'varchar(255) DEFAULT NULL',
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP'
        ],
        'indexes' => [
            'PRIMARY' => 'PRIMARY KEY (id)',
            'metric_name' => 'KEY metric_name (metric_name)',
            'created_at' => 'KEY created_at (created_at)'
        ]
    ]
];

// If checking specific table, filter the list
if ($check_specific_table && isset($expected_tables[$check_specific_table])) {
    $expected_tables = [$check_specific_table => $expected_tables[$check_specific_table]];
} elseif ($check_specific_table) {
    // Return error for invalid table name
    if ($return_json) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => "Table '$check_specific_table' is not in the expected tables list.",
            'valid_tables' => array_keys($expected_tables)
        ]);
        exit;
    } else {
        wp_die("Table '$check_specific_table' is not in the expected tables list. Valid tables: " . implode(', ', array_keys($expected_tables)));
    }
}

// Function to check if table exists
function table_exists($table_name) {
    global $wpdb;
    $full_table_name = $wpdb->prefix . $table_name;
    return $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
}

// Function to get table columns
function get_table_columns($table_name) {
    global $wpdb;
    $full_table_name = $wpdb->prefix . $table_name;
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $full_table_name");
    $column_info = [];
    foreach ($columns as $column) {
        $column_info[$column->Field] = [
            'type' => $column->Type,
            'null' => $column->Null,
            'key' => $column->Key,
            'default' => $column->Default,
            'extra' => $column->Extra
        ];
    }
    return $column_info;
}

// Function to get table indexes
function get_table_indexes($table_name) {
    global $wpdb;
    $full_table_name = $wpdb->prefix . $table_name;
    $indexes = $wpdb->get_results("SHOW INDEXES FROM $full_table_name");
    $index_info = [];
    foreach ($indexes as $index) {
        if (!isset($index_info[$index->Key_name])) {
            $index_info[$index->Key_name] = [];
        }
        $index_info[$index->Key_name][] = $index->Column_name;
    }
    return $index_info;
}

// Function to get table status
function get_table_status($table_name) {
    global $wpdb;
    $full_table_name = $wpdb->prefix . $table_name;
    $status = $wpdb->get_row("SHOW TABLE STATUS LIKE '$full_table_name'");
    return $status;
}

// Function to calculate structure hash
function calculate_structure_hash($columns, $indexes) {
    $structure_string = json_encode(['columns' => $columns, 'indexes' => $indexes]);
    return md5($structure_string);
}

// Perform verification
$verification_results = [];
$all_tables_exist = true;
$has_warnings = false;
$missing_tables = [];
$tables_with_issues = [];

foreach ($expected_tables as $table_name => $expected_structure) {
    $result = [
        'name' => $table_name,
        'full_name' => $wpdb->prefix . $table_name,
        'exists' => false,
        'status' => 'missing',
        'issues' => [],
        'row_count' => 0,
        'size' => 0,
        'engine' => 'N/A',
        'structure_hash' => null
    ];
    
    if (table_exists($table_name)) {
        $result['exists'] = true;
        $actual_columns = get_table_columns($table_name);
        $actual_indexes = get_table_indexes($table_name);
        $table_status = get_table_status($table_name);
        
        // Calculate structure hash
        $result['structure_hash'] = calculate_structure_hash($actual_columns, $actual_indexes);
        
        // Get table stats
        if ($table_status) {
            $result['row_count'] = $table_status->Rows;
            $result['size'] = $table_status->Data_length + $table_status->Index_length;
            $result['engine'] = $table_status->Engine;
        }
        
        // Check for missing columns
        foreach ($expected_structure['columns'] as $col_name => $col_type) {
            if (!isset($actual_columns[$col_name])) {
                $result['issues'][] = "Missing column: $col_name";
                $has_warnings = true;
            }
        }
        
        // Check for missing indexes
        foreach ($expected_structure['indexes'] as $idx_name => $idx_def) {
            if (!isset($actual_indexes[$idx_name])) {
                $result['issues'][] = "Missing index: $idx_name";
                $has_warnings = true;
            }
        }
        
        if (empty($result['issues'])) {
            $result['status'] = 'ok';
        } else {
            $result['status'] = 'warning';
            $tables_with_issues[] = $table_name;
        }
    } else {
        $all_tables_exist = false;
        $missing_tables[] = $table_name;
    }
    
    $verification_results[$table_name] = $result;
    
    // Add to log
    if ($log_to_file) {
        $log_content[] = "\nTable: " . $result['full_name'];
        $log_content[] = "Status: " . $result['status'];
        if ($result['exists']) {
            $log_content[] = "Rows: " . number_format($result['row_count']);
            $log_content[] = "Size: " . size_format($result['size']);
            $log_content[] = "Engine: " . $result['engine'];
            $log_content[] = "Structure Hash: " . $result['structure_hash'];
            
            if ($show_structure) {
                $log_content[] = "Columns:";
                foreach ($actual_columns as $col_name => $col_info) {
                    $log_content[] = "  - $col_name: " . $col_info['type'];
                }
                $log_content[] = "Indexes:";
                foreach ($actual_indexes as $idx_name => $idx_cols) {
                    $log_content[] = "  - $idx_name: " . implode(', ', $idx_cols);
                }
            }
        }
        if (!empty($result['issues'])) {
            $log_content[] = "Issues:";
            foreach ($result['issues'] as $issue) {
                $log_content[] = "  - " . $issue;
            }
        }
    }
}

// Save log file if requested
if ($log_to_file) {
    $log_dir = wp_content_dir() . '/zippicks-logs';
    if (!is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
    }
    $log_file = $log_dir . '/table-verification-' . date('Y-m-d-His') . '.log';
    $log_content[] = "\n" . str_repeat('=', 50);
    $log_content[] = "Summary:";
    $log_content[] = "Total tables expected: " . count($expected_tables);
    $log_content[] = "Tables found: " . (count($expected_tables) - count($missing_tables));
    $log_content[] = "Tables missing: " . count($missing_tables);
    $log_content[] = "Tables with issues: " . count($tables_with_issues);
    
    file_put_contents($log_file, implode("\n", $log_content));
}

// Return JSON if requested
if ($return_json) {
    header('Content-Type: application/json');
    echo json_encode([
        'timestamp' => date('c'),
        'all_tables_exist' => $all_tables_exist,
        'has_warnings' => $has_warnings,
        'missing_tables' => $missing_tables,
        'tables_with_issues' => $tables_with_issues,
        'results' => $verification_results,
        'log_file' => isset($log_file) ? $log_file : null
    ], JSON_PRETTY_PRINT);
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>ZipPicks Vibes - Database Verification</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            max-width: 1200px;
            margin: 0 auto;
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
            color: #333;
            border-bottom: 3px solid #007cba;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        .status-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        .table-card {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            background: #fafafa;
        }
        .table-card.exists {
            border-color: #4CAF50;
            background: #f1f8f4;
        }
        .table-card.missing {
            border-color: #f44336;
            background: #fef5f5;
        }
        .table-card.warning {
            border-color: #ff9800;
            background: #fff8f1;
        }
        .table-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }
        .status.ok {
            background: #4CAF50;
            color: white;
        }
        .status.missing {
            background: #f44336;
            color: white;
        }
        .status.warning {
            background: #ff9800;
            color: white;
        }
        .details {
            margin-top: 15px;
            font-size: 14px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px dotted #ddd;
        }
        .detail-label {
            font-weight: 500;
            color: #666;
        }
        .columns-list, .indexes-list {
            margin-top: 10px;
            padding-left: 20px;
            font-size: 13px;
            color: #666;
        }
        .action-buttons {
            margin-top: 30px;
            text-align: center;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 10px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .button:hover {
            background: #005a87;
        }
        .button.secondary {
            background: #666;
        }
        .button.secondary:hover {
            background: #444;
        }
        .summary {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: center;
        }
        .summary.all-good {
            background: #e8f5e9;
        }
        .summary.has-issues {
            background: #fff3e0;
        }
        .code-block {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
            margin-top: 10px;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .query-options {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .structure-hash {
            font-family: monospace;
            font-size: 12px;
            color: #666;
        }
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 ZipPicks Vibes - Database Verification</h1>
        
        <p>This tool verifies the existence and integrity of all database tables required by the ZipPicks Vibes plugin.</p>
        
        <div class="query-options">
            <h3>🔧 Query Options</h3>
            <p>You can use these URL parameters:</p>
            <ul>
                <li><code>?check=table_name</code> - Check specific table only</li>
                <li><code>?structure=1</code> - Show detailed structure information</li>
                <li><code>?log=1</code> - Save results to log file in wp-content</li>
                <li><code>?format=json</code> - Return results as JSON</li>
            </ul>
            <p>Example: <code>verify-tables.php?check=zippicks_vibes&structure=1&log=1</code></p>
        </div>
        
        <?php if ($check_specific_table): ?>
            <div class="warning-box">
                <strong>Note:</strong> Checking only table: <code><?php echo esc_html($check_specific_table); ?></code>
                <a href="?" style="float: right;">Check all tables</a>
            </div>
        <?php endif; ?>
        
        <div class="status-grid">
            <?php foreach ($verification_results as $table_name => $result): ?>
                <div class="table-card <?php echo $result['exists'] ? ($result['status'] === 'ok' ? 'exists' : 'warning') : 'missing'; ?>">
                    <div class="table-name">
                        <?php echo esc_html($result['full_name']); ?>
                        <span class="status <?php echo esc_attr($result['status']); ?>">
                            <?php echo $result['status'] === 'ok' ? 'OK' : ($result['status'] === 'warning' ? 'Needs Repair' : 'Missing'); ?>
                        </span>
                    </div>
                    
                    <?php if ($result['exists']): ?>
                        <div class="details">
                            <div class="detail-row">
                                <span class="detail-label">Rows:</span>
                                <span><?php echo number_format($result['row_count']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Size:</span>
                                <span><?php echo size_format($result['size']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Engine:</span>
                                <span><?php echo esc_html($result['engine']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Structure Hash:</span>
                                <span class="structure-hash"><?php echo esc_html($result['structure_hash']); ?></span>
                            </div>
                            
                            <?php if (!empty($result['issues'])): ?>
                                <div class="warning-box">
                                    <strong>Issues found:</strong>
                                    <ul class="columns-list">
                                        <?php foreach ($result['issues'] as $issue): ?>
                                            <li><?php echo esc_html($issue); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($show_structure): ?>
                                <details>
                                    <summary><strong>Table Structure</strong></summary>
                                    <div class="code-block">
                                        <strong>Columns:</strong><br>
                                        <?php
                                        $columns = get_table_columns($table_name);
                                        foreach ($columns as $col_name => $col_info) {
                                            echo esc_html($col_name) . ': ' . esc_html($col_info['type']);
                                            if ($col_info['key'] === 'PRI') echo ' [PRIMARY]';
                                            elseif ($col_info['key'] === 'UNI') echo ' [UNIQUE]';
                                            elseif ($col_info['key'] === 'MUL') echo ' [INDEX]';
                                            echo "<br>";
                                        }
                                        ?>
                                        <br><strong>Indexes:</strong><br>
                                        <?php
                                        $indexes = get_table_indexes($table_name);
                                        foreach ($indexes as $idx_name => $idx_cols) {
                                            echo esc_html($idx_name) . ': ' . esc_html(implode(', ', $idx_cols)) . "<br>";
                                        }
                                        ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="details">
                            <p>This table needs to be created.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($all_tables_exist && !$has_warnings): ?>
            <div class="summary all-good">
                <h2>✅ All tables verified successfully!</h2>
                <p>Your database is properly configured for ZipPicks Vibes.</p>
            </div>
        <?php else: ?>
            <div class="summary has-issues">
                <h2>⚠️ Database issues detected</h2>
                <?php if (!empty($missing_tables)): ?>
                    <p><strong>Missing tables:</strong> <?php echo esc_html(implode(', ', $missing_tables)); ?></p>
                <?php endif; ?>
                <?php if (!empty($tables_with_issues)): ?>
                    <p><strong>Tables needing repair:</strong> <?php echo esc_html(implode(', ', $tables_with_issues)); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <h2>📊 Verification Summary</h2>
        <ul>
            <li>Total tables expected: <strong><?php echo count($expected_tables); ?></strong></li>
            <li>Tables found: <strong><?php echo count($expected_tables) - count($missing_tables); ?></strong></li>
            <li>Tables missing: <strong><?php echo count($missing_tables); ?></strong></li>
            <li>Tables with issues: <strong><?php echo count($tables_with_issues); ?></strong></li>
        </ul>
        
        <?php if ($log_to_file && isset($log_file)): ?>
            <div class="success-box">
                <strong>✅ Log saved:</strong> <?php echo esc_html($log_file); ?><br>
                <small>File location: <code>wp-content/zippicks-logs/</code></small>
            </div>
        <?php endif; ?>
        
        <?php if (!$all_tables_exist || $has_warnings): ?>
            <h2>🔧 Repair Options</h2>
            
            <div class="warning-box">
                <strong>⚠️ Important:</strong> Always backup your database before making structural changes!
            </div>
            
            <h3>Option 1: Automatic Repair</h3>
            <p>Use the plugin's built-in installer to create or repair tables:</p>
            <div class="code-block">
                <?php
                // Load plugin's installer class
                require_once(__DIR__ . '/src/Database/Installer.php');
                ?>
                // In your WordPress admin or via WP-CLI:
                \ZipPicksVibes\Database\Installer::install();
            </div>
            
            <h3>Option 2: Manual SQL</h3>
            <p>Copy and run this SQL in phpMyAdmin or your database tool:</p>
            <div class="code-block">
                <?php 
                // Load schema from includes/schema.php if available
                $schema_file = __DIR__ . '/includes/schema.php';
                if (file_exists($schema_file)) {
                    require_once $schema_file;
                    echo htmlspecialchars(\ZipPicksVibes\get_formatted_schema_sql());
                } else {
                    echo htmlspecialchars(\ZipPicksVibes\Database\Installer::get_schema_sql());
                }
                ?>
            </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <a href="<?php echo plugins_url('create-tables.php', __FILE__); ?>" class="button">
                Create Tables Tool
            </a>
            <a href="<?php echo admin_url('plugins.php'); ?>" class="button secondary">
                Back to Plugins
            </a>
            <a href="javascript:location.reload();" class="button secondary">
                Refresh Verification
            </a>
            <?php if (!$log_to_file): ?>
                <a href="?log=1<?php echo $check_specific_table ? '&check=' . urlencode($check_specific_table) : ''; ?><?php echo $show_structure ? '&structure=1' : ''; ?>" class="button secondary">
                    Save to Log
                </a>
            <?php endif; ?>
            <?php if (!$show_structure): ?>
                <a href="?structure=1<?php echo $check_specific_table ? '&check=' . urlencode($check_specific_table) : ''; ?><?php echo $log_to_file ? '&log=1' : ''; ?>" class="button secondary">
                    Show Structure
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>