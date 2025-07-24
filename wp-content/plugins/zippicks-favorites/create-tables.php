<?php
/**
 * Manual table creation script for ZipPicks Favorites
 * 
 * Access this script directly to create the database tables.
 * URL: /wp-content/plugins/zippicks-favorites/create-tables.php
 */

// Find and load WordPress
$wp_load_path = '';
$depth = 0;
$max_depth = 10;

// Search for wp-load.php by going up directories
while ($depth < $max_depth) {
    $path = str_repeat('../', $depth) . 'wp-load.php';
    if (file_exists($path)) {
        $wp_load_path = $path;
        break;
    }
    $depth++;
}

if (empty($wp_load_path)) {
    die('Error: Could not find wp-load.php');
}

// Load WordPress
define('WP_USE_THEMES', false);
require_once($wp_load_path);

// Check permissions
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    header('Location: ' . wp_login_url($_SERVER['REQUEST_URI']));
    exit;
}

// Load plugin dependencies
require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';

// Output HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>ZipPicks Favorites - Create Tables</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 40px;
            background: #f0f0f1;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 { color: #1d2327; }
        .success { color: #00a32a; font-weight: bold; }
        .error { color: #d63638; font-weight: bold; }
        .exists { color: #3858e9; }
        pre {
            background: #f6f7f7;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #e1e1e1;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #2271b1;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            margin-top: 10px;
        }
        .button:hover {
            background: #135e96;
        }
        .sql-box {
            background: #282c34;
            color: #abb2bf;
            padding: 20px;
            border-radius: 4px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 14px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ZipPicks Favorites - Database Table Creation</h1>
        
        <?php
        global $wpdb;
        
        // Check current status
        echo "<h2>Current Table Status:</h2>";
        echo "<pre>";
        
        $tables = [
            'zippicks_favorites',
            'zippicks_favorites_meta',
            'zippicks_location_cache'
        ];
        
        $missing_tables = [];
        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            
            if ($exists) {
                echo "<span class='exists'>✓ $full_table_name exists</span>\n";
            } else {
                echo "<span class='error'>✗ $full_table_name missing</span>\n";
                $missing_tables[] = $table;
            }
        }
        echo "</pre>";
        
        // If tables are missing and user clicked create
        if (!empty($missing_tables) && isset($_POST['create_tables'])) {
            // Verify nonce
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'create_favorites_tables')) {
                echo '<p class="error">Security check failed!</p>';
            } else {
                echo "<h2>Creating Tables...</h2>";
                echo "<pre>";
                
                try {
                    // Create the tables
                    \ZipPicks\Favorites\Database::create_tables();
                    echo "<span class='success'>✓ Table creation completed!</span>\n";
                    
                    // Verify they were created
                    echo "\n<strong>Verification:</strong>\n";
                    foreach ($tables as $table) {
                        $full_table_name = $wpdb->prefix . $table;
                        $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
                        
                        if ($exists) {
                            echo "<span class='success'>✓ $full_table_name created successfully</span>\n";
                        } else {
                            echo "<span class='error'>✗ $full_table_name still missing</span>\n";
                        }
                    }
                } catch (Exception $e) {
                    echo "<span class='error'>Error: " . esc_html($e->getMessage()) . "</span>\n";
                }
                
                echo "</pre>";
            }
        }
        
        // Show action buttons or SQL
        if (!empty($missing_tables) && !isset($_POST['create_tables'])) {
            ?>
            <h2>Actions:</h2>
            
            <h3>Option 1: Create Tables Automatically</h3>
            <form method="post">
                <?php wp_nonce_field('create_favorites_tables'); ?>
                <input type="submit" name="create_tables" value="Create Tables Now" class="button">
            </form>
            
            <h3>Option 2: Create via Admin Action</h3>
            <p>Click this link to create tables via WordPress admin action:</p>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?action=zippicks_create_tables'), 'zippicks_create_tables'); ?>" class="button">
                Create Tables (Admin Action)
            </a>
            
            <h3>Option 3: Manual SQL</h3>
            <p>Run this SQL in phpMyAdmin:</p>
            <div class="sql-box">
<?php
$charset_collate = $wpdb->get_charset_collate();
$prefix = $wpdb->prefix;

// Output the SQL
echo htmlspecialchars("-- Create ZipPicks Favorites tables

-- Main favorites table
CREATE TABLE IF NOT EXISTS `{$prefix}zippicks_favorites` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `business_id` bigint(20) UNSIGNED NOT NULL,
    `saved_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `user_notes` text,
    `latitude` decimal(10, 8),
    `longitude` decimal(11, 8),
    `city` varchar(100),
    `state` varchar(50),
    `country` varchar(2) DEFAULT 'US',
    `neighborhood` varchar(100),
    `zip_code` varchar(10),
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_business` (`user_id`, `business_id`),
    KEY `user_id` (`user_id`),
    KEY `business_id` (`business_id`),
    KEY `location` (`latitude`, `longitude`),
    KEY `city_state` (`city`, `state`),
    KEY `saved_date` (`saved_date`),
    KEY `neighborhood` (`neighborhood`),
    KEY `zip_code` (`zip_code`)
) $charset_collate;

-- Favorites metadata table
CREATE TABLE IF NOT EXISTS `{$prefix}zippicks_favorites_meta` (
    `meta_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `favorite_id` bigint(20) UNSIGNED NOT NULL,
    `meta_key` varchar(255),
    `meta_value` longtext,
    PRIMARY KEY (`meta_id`),
    KEY `favorite_id` (`favorite_id`),
    KEY `meta_key` (`meta_key`)
) $charset_collate;

-- Location cache table
CREATE TABLE IF NOT EXISTS `{$prefix}zippicks_location_cache` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `location_key` varchar(255) NOT NULL,
    `latitude` decimal(10, 8) NOT NULL,
    `longitude` decimal(11, 8) NOT NULL,
    `city` varchar(100),
    `state` varchar(50),
    `country` varchar(2) DEFAULT 'US',
    `neighborhood` varchar(100),
    `zip_code` varchar(10),
    `formatted_address` text,
    `cached_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `location_key` (`location_key`),
    KEY `cached_date` (`cached_date`)
) $charset_collate;");
?>
            </div>
            <?php
        } elseif (empty($missing_tables)) {
            echo '<p class="success">All tables exist! The plugin should work correctly now.</p>';
            echo '<a href="' . admin_url('admin.php?page=zippicks-favorites') . '" class="button">Go to Favorites Admin</a>';
        }
        ?>
        
        <h2>System Information:</h2>
        <pre>
WordPress Version: <?php echo get_bloginfo('version'); ?>
PHP Version: <?php echo PHP_VERSION; ?>
MySQL Version: <?php echo $wpdb->db_version(); ?>
Table Prefix: <?php echo $wpdb->prefix; ?>
Current User: <?php echo wp_get_current_user()->user_login; ?>
        </pre>
    </div>
</body>
</html>