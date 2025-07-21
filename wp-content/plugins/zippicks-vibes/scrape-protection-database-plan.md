# ScrapeProtection Database Implementation Plan

## Overview
This document outlines the implementation plan to bring the ScrapeProtection service into compliance with the CLAUDE.md database table creation pattern.

## Current State
- Tables are created in the constructor using `dbDelta()`
- Basic auto-recovery exists
- No separate Database/Installer classes
- No Foundation integration
- No manual creation tools

## Required Components

### 1. Database Class (`includes/class-scrape-protection-database.php`)

```php
<?php
namespace ZipPicksVibesV2\Database;

class ScrapeProtectionDatabase {
    const SCRAPE_LOG_TABLE = 'zippicks_scrape_log';
    const BLOCKED_IPS_TABLE = 'zippicks_blocked_ips';
    
    public static function get_scrape_log_table() {
        global $wpdb;
        return $wpdb->prefix . self::SCRAPE_LOG_TABLE;
    }
    
    public static function get_blocked_ips_table() {
        global $wpdb;
        return $wpdb->prefix . self::BLOCKED_IPS_TABLE;
    }
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = [];
        
        // Scrape log table
        $sql[] = "CREATE TABLE " . self::get_scrape_log_table() . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            request_path TEXT,
            referrer TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            type VARCHAR(50),
            user_id BIGINT(20) UNSIGNED,
            data TEXT,
            error_message TEXT,
            context VARCHAR(100),
            request_limit INT,
            requests_count INT,
            PRIMARY KEY (id),
            INDEX idx_ip_time (ip_address, timestamp),
            INDEX idx_type (type),
            INDEX idx_user (user_id),
            INDEX idx_timestamp (timestamp)
        ) $charset_collate;";
        
        // Blocked IPs table
        $sql[] = "CREATE TABLE " . self::get_blocked_ips_table() . " (
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($sql as $query) {
            dbDelta($query);
        }
        
        return self::verify_tables();
    }
    
    public static function verify_tables() {
        global $wpdb;
        
        $tables_exist = true;
        
        $required_tables = [
            self::get_scrape_log_table(),
            self::get_blocked_ips_table()
        ];
        
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                $tables_exist = false;
                break;
            }
        }
        
        return $tables_exist;
    }
    
    public static function get_schema_sql() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        return [
            'scrape_log' => "CREATE TABLE " . self::get_scrape_log_table() . " (...)",
            'blocked_ips' => "CREATE TABLE " . self::get_blocked_ips_table() . " (...)"
        ];
    }
}
```

### 2. Installer Class (`includes/class-scrape-protection-installer.php`)

```php
<?php
namespace ZipPicksVibesV2\Installer;

use ZipPicksVibesV2\Database\ScrapeProtectionDatabase;

class ScrapeProtectionInstaller {
    const VERSION_OPTION = 'zippicks_scrape_protection_db_version';
    const CURRENT_VERSION = '1.0.0';
    
    public static function install() {
        // Create tables
        $tables_created = ScrapeProtectionDatabase::create_tables();
        
        if (!$tables_created) {
            throw new \Exception('Failed to create scrape protection tables');
        }
        
        // Update version
        update_option(self::VERSION_OPTION, self::CURRENT_VERSION);
        
        // Set default options
        self::set_default_options();
        
        // Create capabilities
        self::create_capabilities();
        
        return true;
    }
    
    public static function tables_exist() {
        return ScrapeProtectionDatabase::verify_tables();
    }
    
    private static function set_default_options() {
        $defaults = [
            'zippicks_scrape_protection_enabled' => true,
            'zippicks_scrape_rate_limits' => [
                'requests_per_minute' => 20,
                'requests_per_hour' => 300,
                'requests_per_day' => 2000
            ],
            'zippicks_scrape_alert_threshold' => 10
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }
    
    private static function create_capabilities() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_scrape_protection');
            $admin->add_cap('view_scrape_logs');
        }
    }
}
```

### 3. Manual Creation Tool (`admin/create-scrape-tables.php`)

```php
<?php
/**
 * Manual table creation tool for Scrape Protection
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

// Handle form submission
if (isset($_POST['create_tables'])) {
    check_admin_referer('create_scrape_tables');
    
    try {
        require_once dirname(__FILE__) . '/../includes/class-scrape-protection-database.php';
        $result = \ZipPicksVibesV2\Database\ScrapeProtectionDatabase::create_tables();
        
        if ($result) {
            $message = 'Tables created successfully!';
            $type = 'success';
        } else {
            $message = 'Failed to create tables. Check error logs.';
            $type = 'error';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $type = 'error';
    }
}

// Check current status
require_once dirname(__FILE__) . '/../includes/class-scrape-protection-database.php';
$tables_exist = \ZipPicksVibesV2\Database\ScrapeProtectionDatabase::verify_tables();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Scrape Protection Tables</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; color: #0c5460; }
        button { padding: 10px 20px; font-size: 16px; }
        .sql-box { background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; margin: 20px 0; }
        pre { white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>Scrape Protection Table Creation</h1>
    
    <?php if (isset($message)): ?>
        <div class="status <?php echo $type; ?>">
            <?php echo esc_html($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="status info">
        <strong>Current Status:</strong> 
        <?php if ($tables_exist): ?>
            ✅ All tables exist
        <?php else: ?>
            ❌ Tables are missing
        <?php endif; ?>
    </div>
    
    <?php if (!$tables_exist): ?>
        <form method="post">
            <?php wp_nonce_field('create_scrape_tables'); ?>
            <p>Click the button below to create the required database tables:</p>
            <button type="submit" name="create_tables">Create Tables Now</button>
        </form>
        
        <h2>Alternative: Manual SQL</h2>
        <p>If the automatic creation fails, you can run this SQL in phpMyAdmin:</p>
        <div class="sql-box">
            <pre><?php 
                $schema = \ZipPicksVibesV2\Database\ScrapeProtectionDatabase::get_schema_sql();
                foreach ($schema as $table => $sql) {
                    echo "-- $table\n";
                    echo htmlspecialchars($sql) . "\n\n";
                }
            ?></pre>
        </div>
    <?php endif; ?>
    
    <p><a href="<?php echo admin_url('admin.php?page=zippicks-vibes-v2'); ?>">← Back to Vibes Admin</a></p>
</body>
</html>
```

### 4. Update Main Service Constructor

```php
public function __construct($logger = null, $cache = null) {
    global $wpdb;
    
    $this->logger = $logger;
    $this->cache = $cache;
    
    // Use the Database class for table names
    require_once dirname(__FILE__) . '/../Database/ScrapeProtectionDatabase.php';
    $this->logTable = Database\ScrapeProtectionDatabase::get_scrape_log_table();
    $this->blockedTable = Database\ScrapeProtectionDatabase::get_blocked_ips_table();
    
    // Auto-create tables if missing
    if (!Database\ScrapeProtectionDatabase::verify_tables()) {
        try {
            Database\ScrapeProtectionDatabase::create_tables();
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to create scrape protection tables', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
```

### 5. Update Plugin Initialization

```php
// In the main plugin file or initialization
add_action('init', function() {
    // Check and create tables if missing
    if (!ZipPicksVibesV2\Installer\ScrapeProtectionInstaller::tables_exist()) {
        try {
            ZipPicksVibesV2\Installer\ScrapeProtectionInstaller::install();
        } catch (Exception $e) {
            // Add admin notice
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><strong>ZipPicks Scrape Protection:</strong> Failed to create database tables. 
                    <a href="<?php echo admin_url('admin.php?page=zippicks-scrape-tables'); ?>">Create manually</a></p>
                </div>
                <?php
            });
        }
    }
});

// Register with Foundation if available
if (function_exists('zippicks') && zippicks()->has('database.installer')) {
    $installer = zippicks()->get('database.installer');
    $installer->register_schema('scrape-protection', function() {
        return ZipPicksVibesV2\Database\ScrapeProtectionDatabase::get_schema_sql();
    }, '1.0.0');
}
```

## Implementation Steps

1. **Phase 1: Create Database Classes**
   - Create the Database class with table management
   - Create the Installer class with version tracking
   - Test table creation and verification

2. **Phase 2: Add Manual Tools**
   - Create the admin page for manual table creation
   - Add SQL export functionality
   - Test manual creation flow

3. **Phase 3: Update Service**
   - Refactor constructor to use Database class
   - Add auto-recovery on init
   - Test graceful degradation

4. **Phase 4: Foundation Integration**
   - Register schema with Foundation
   - Test with both Simple and Enterprise foundations
   - Verify backward compatibility

5. **Phase 5: Testing & Documentation**
   - Create comprehensive tests
   - Document the new structure
   - Update deployment guides

## Benefits

1. **Multiple Recovery Options**: Auto-creation, manual tools, SQL export
2. **Foundation Compatible**: Works with or without Foundation
3. **Version Tracking**: Enables future migrations
4. **Better Organization**: Separates concerns properly
5. **Enterprise Ready**: Follows best practices

## Testing Checklist

- [ ] Tables create on plugin activation
- [ ] Tables auto-create when missing
- [ ] Manual creation tool works
- [ ] SQL export is accurate
- [ ] Admin notices appear when needed
- [ ] Foundation registration works
- [ ] Service continues to function
- [ ] No errors with missing tables