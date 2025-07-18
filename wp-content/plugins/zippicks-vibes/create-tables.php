<?php
/**
 * Manual Database Table Creation Tool
 * 
 * Web-based interface for manually creating ZipPicks Vibes v2 tables
 * Also supports CLI execution with arguments
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

// Detect CLI mode
$is_cli = php_sapi_name() === 'cli';

// Load WordPress
if ($is_cli) {
    // CLI mode - look for wp-load.php in various locations
    $wp_load_paths = [
        dirname(__FILE__) . '/../../../wp-load.php',
        dirname(__FILE__) . '/../../../../wp-load.php',
        dirname(__FILE__) . '/../../../../../wp-load.php',
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
        die("Error: Could not find wp-load.php\n");
    }
} else {
    // Web mode
    require_once dirname(__FILE__) . '/../../../wp-load.php';
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
}

// Parse CLI arguments
$cli_args = [];
if ($is_cli) {
    // Parse command line arguments
    $options = getopt('', ['dry-run', 'prefix:', 'log-output', 'help']);
    $cli_args = [
        'dry_run' => isset($options['dry-run']),
        'prefix' => $options['prefix'] ?? null,
        'log_output' => isset($options['log-output']),
        'help' => isset($options['help'])
    ];
    
    // Show help if requested
    if ($cli_args['help']) {
        echo "ZipPicks Vibes - Database Table Creation Tool\n";
        echo "===============================================\n\n";
        echo "Usage: php create-tables.php [options]\n\n";
        echo "Options:\n";
        echo "  --dry-run       Show SQL without executing\n";
        echo "  --prefix=PREFIX Use custom table prefix\n";
        echo "  --log-output    Log all output to file\n";
        echo "  --help          Show this help message\n\n";
        echo "Example:\n";
        echo "  php create-tables.php --dry-run\n";
        echo "  php create-tables.php --prefix=wp_2_ --log-output\n";
        exit(0);
    }
}

// Set up logging if requested
$log_file = null;
if ($is_cli && $cli_args['log_output']) {
    $log_file = dirname(__FILE__) . '/logs/table-creation-' . date('Y-m-d-His') . '.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
}

// Helper function for output
function output($message, $type = 'info') {
    global $is_cli, $log_file;
    
    if ($is_cli) {
        $prefix = match($type) {
            'success' => '✅ ',
            'error' => '❌ ',
            'warning' => '⚠️  ',
            'info' => 'ℹ️  ',
            default => ''
        };
        
        $output = $prefix . $message . "\n";
        echo $output;
        
        if ($log_file) {
            file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $output, FILE_APPEND);
        }
    }
}

// Load schema from includes/schema.php
$schema_file = dirname(__FILE__) . '/includes/schema.php';
if (!file_exists($schema_file)) {
    if ($is_cli) {
        output("Schema file not found at: $schema_file", 'error');
        exit(1);
    } else {
        wp_die('Schema file not found. Please ensure includes/schema.php exists.');
    }
}

require_once $schema_file;

// Ensure schema constant is defined
if (!defined('ZIPPICKS_VIBES_SCHEMA_SQL')) {
    if ($is_cli) {
        output("Schema constant ZIPPICKS_VIBES_SCHEMA_SQL not defined", 'error');
        exit(1);
    } else {
        wp_die('Schema constant not defined. Please check includes/schema.php.');
    }
}

// Load the plugin's Installer class
require_once dirname(__FILE__) . '/src/Database/Installer.php';

use ZipPicksVibes\Database\Installer;

// Handle CLI execution
if ($is_cli) {
    output("ZipPicks Vibes - Database Table Creation", 'info');
    output(str_repeat('=', 50), 'info');
    
    // Determine table prefix
    global $wpdb;
    $table_prefix = $cli_args['prefix'] ?? $wpdb->prefix;
    output("Using table prefix: $table_prefix", 'info');
    
    // Get table list from schema
    $tables = ZipPicksVibes\get_table_definitions();
    
    // Check current status
    output("\nChecking current table status...", 'info');
    $missing_tables = [];
    
    foreach ($tables as $table => $description) {
        $full_table_name = $table_prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
        
        if ($exists) {
            output("Table $full_table_name exists ($description)", 'success');
        } else {
            output("Table $full_table_name is missing ($description)", 'warning');
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        output("\nAll tables already exist!", 'success');
        exit(0);
    }
    
    output("\nFound " . count($missing_tables) . " missing tables", 'warning');
    
    // Dry run mode
    if ($cli_args['dry_run']) {
        output("\nDRY RUN MODE - Showing SQL without executing:", 'info');
        echo "\n" . ZipPicksVibes\get_formatted_schema_sql($table_prefix) . "\n";
        exit(0);
    }
    
    // Create tables
    output("\nCreating missing tables...", 'info');
    
    try {
        // If custom prefix specified, we need to set it temporarily
        if ($cli_args['prefix']) {
            $original_prefix = $wpdb->prefix;
            $wpdb->set_prefix($cli_args['prefix']);
        }
        
        Installer::install();
        
        // Restore original prefix if changed
        if ($cli_args['prefix']) {
            $wpdb->set_prefix($original_prefix);
        }
        
        // Verify each table
        $success_count = 0;
        $fail_count = 0;
        
        output("\nVerifying table creation...", 'info');
        
        foreach ($missing_tables as $table) {
            $full_table_name = $table_prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
            
            if ($exists) {
                output("Created table: $full_table_name", 'success');
                $success_count++;
            } else {
                output("Failed to create table: $full_table_name", 'error');
                $fail_count++;
            }
        }
        
        output("\nSummary:", 'info');
        output("Successfully created: $success_count tables", 'success');
        if ($fail_count > 0) {
            output("Failed to create: $fail_count tables", 'error');
            exit(1);
        } else {
            output("All tables created successfully!", 'success');
            exit(0);
        }
        
    } catch (Exception $e) {
        output("\nError creating tables: " . $e->getMessage(), 'error');
        exit(1);
    }
}

// Web mode execution continues below...

// Handle form submission
$message = '';
$message_type = '';

if (isset($_POST['create_tables']) && wp_verify_nonce($_POST['_wpnonce'], 'zippicks_vibes_create_tables')) {
    try {
        // Create tables
        Installer::install();
        
        // Verify creation
        if (Installer::tables_exist()) {
            $message = 'All tables created successfully!';
            $message_type = 'success';
        } else {
            $message = 'Some tables failed to create. Please check the details below.';
            $message_type = 'error';
        }
    } catch (Exception $e) {
        $message = 'Error creating tables: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Check current table status
global $wpdb;
$tables = ZipPicksVibes\get_table_definitions();

$table_status = [];
foreach ($tables as $table => $description) {
    $full_table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
    $table_status[$table] = [
        'exists' => $exists,
        'name' => $full_table_name,
        'description' => $description
    ];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>ZipPicks Vibes v2 - Create Database Tables</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
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
            color: #23282d;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-weight: 600;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .table-status {
            margin: 30px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
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
        .status-icon {
            font-size: 20px;
            margin-right: 5px;
        }
        .exists { color: #28a745; }
        .missing { color: #dc3545; }
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
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
        .sql-code {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            overflow-x: auto;
            font-family: monospace;
            font-size: 14px;
        }
        .actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border: 1px solid #ffeaa7;
        }
        .cli-example {
            background: #282c34;
            color: #abb2bf;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 ZipPicks Vibes v2 - Database Table Creation</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo esc_attr($message_type); ?>">
                <?php echo esc_html($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="table-status">
            <h2>📊 Current Table Status</h2>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Table Name</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($table_status as $table => $info): ?>
                        <tr>
                            <td>
                                <span class="status-icon <?php echo $info['exists'] ? 'exists' : 'missing'; ?>">
                                    <?php echo $info['exists'] ? '✅' : '❌'; ?>
                                </span>
                                <?php echo $info['exists'] ? 'Exists' : 'Missing'; ?>
                            </td>
                            <td><code><?php echo esc_html($info['name']); ?></code></td>
                            <td><?php echo esc_html($info['description']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php
        $all_exist = true;
        $missing_count = 0;
        foreach ($table_status as $info) {
            if (!$info['exists']) {
                $all_exist = false;
                $missing_count++;
            }
        }
        ?>
        
        <?php if (!$all_exist): ?>
            <div class="warning">
                <strong>⚠️ Warning:</strong> <?php echo $missing_count; ?> table(s) are missing. Click the button below to create them.
            </div>
            
            <div class="actions">
                <h2>🚀 Create Missing Tables</h2>
                <p>Click the button below to create all missing database tables:</p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('zippicks_vibes_create_tables'); ?>
                    <button type="submit" name="create_tables" class="button">
                        Create Database Tables
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="message success">
                ✅ All database tables exist and are ready to use!
            </div>
        <?php endif; ?>
        
        <div class="actions">
            <h2>🖥️ CLI Usage</h2>
            <p>You can also run this script from the command line:</p>
            
            <div class="cli-example">
# Show help<br>
php create-tables.php --help<br><br>

# Dry run (show SQL without executing)<br>
php create-tables.php --dry-run<br><br>

# Create tables with custom prefix<br>
php create-tables.php --prefix=wp_site2_<br><br>

# Create tables and log output<br>
php create-tables.php --log-output
            </div>
        </div>
        
        <div class="actions">
            <h2>📋 Manual SQL Creation</h2>
            <p>If the automatic creation fails, you can copy the SQL below and run it in phpMyAdmin:</p>
            
            <div class="sql-code">
                <pre><?php echo esc_html(ZipPicksVibes\get_formatted_schema_sql()); ?></pre>
            </div>
            
            <a href="#" onclick="copySQL()" class="button secondary">Copy SQL to Clipboard</a>
        </div>
        
        <div class="actions">
            <h2>📍 Schema Location</h2>
            <p>The database schema is defined in: <code>includes/schema.php</code></p>
            <p>This centralized location makes it easy to maintain and update the schema.</p>
        </div>
        
        <div class="actions">
            <h2>🔗 Quick Links</h2>
            <a href="<?php echo admin_url('plugins.php'); ?>" class="button secondary">
                Back to Plugins
            </a>
            <a href="<?php echo admin_url('admin.php?page=zippicks-vibes'); ?>" class="button secondary">
                Vibes Admin
            </a>
            <a href="verify-tables.php" class="button secondary">
                Verify Tables
            </a>
        </div>
    </div>
    
    <script>
    function copySQL() {
        const sql = document.querySelector('.sql-code pre').textContent;
        const textarea = document.createElement('textarea');
        textarea.value = sql;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('SQL copied to clipboard!');
        return false;
    }
    </script>
</body>
</html>