# ZipPicks Vibes Plugin Detection Analysis

## Root Cause Analysis

### 1. **Plugin File Naming Issue**

WordPress expects the main plugin file to match specific patterns for detection:

**Current Structure:**
```
/wp-content/plugins/zippicks-vibes/
└── zippicks-vibes-v2.php  # Main plugin file
```

**WordPress Plugin Detection Rules:**
1. Plugin folder name: `zippicks-vibes`
2. Main plugin file: `zippicks-vibes-v2.php`
3. Expected plugin identifier: `zippicks-vibes/zippicks-vibes-v2.php`

### 2. **Why WordPress Cannot Detect the Plugin**

WordPress scans for plugins using these criteria:
- Looks in `/wp-content/plugins/` directory
- Searches for PHP files with valid plugin headers
- Creates plugin identifier as `folder-name/main-file.php`

**The Issue:**
- The main file is named `zippicks-vibes-v2.php` (with "-v2")
- But references expect `zippicks-vibes.php` (without "-v2")
- This creates a mismatch in plugin detection

### 3. **Evidence from Code Analysis**

From `test-plugin-status.php` (line 120):
```php
$plugin_file = 'zippicks-vibes/zippicks-vibes-v2.php';
```

From `activate-plugin.php` (lines 28, 230):
```php
define('ZIPPICKS_VIBES_V2_FILE', ZIPPICKS_VIBES_V2_DIR . 'zippicks-vibes-v2.php');
$plugin_file = 'zippicks-vibes/zippicks-vibes-v2.php';
```

### 4. **Plugin Header Analysis**

The plugin header in `zippicks-vibes-v2.php` is correct:
```php
/**
 * Plugin Name: ZipPicks Vibes V2
 * Plugin URI: https://zippicks.com/plugins/vibes
 * Description: Enterprise-grade vibe discovery engine...
 * Version: 2.0.0
 * ...
 */
```

### 5. **Multisite Considerations**

The plugin has multisite support enabled:
```php
* Network: true
```

This may affect detection on multisite installations.

### 6. **Autoloader and Namespace Issues**

The plugin uses:
- Namespace: `ZipPicksVibesV2`
- PSR-4 autoloading
- Custom autoloader registration

These are working correctly based on the code analysis.

### 7. **Activation Hook Issues**

From ENGINEERING-HANDOFF-IMMEDIATE.md:
- Activation hooks were initially registered inside the class constructor
- This caused timing issues where hooks ran after WordPress processed them
- This has been FIXED by moving hooks outside the class

### 8. **Database Table Dependencies**

The plugin requires these tables:
- `wp_zippicks_vibes`
- `wp_zippicks_vibe_categories`
- `wp_zippicks_vibe_category_assignments`
- `wp_zippicks_waitlist`
- `wp_zippicks_scrape_log`
- `wp_zippicks_security_log`
- `wp_zippicks_rate_limit_log`
- `wp_zippicks_security_events`
- `wp_zippicks_audit_log`
- `wp_zippicks_performance_metrics`

Missing tables cause runtime errors but shouldn't prevent plugin detection.

## Solutions

### Option 1: Direct Activation (Recommended)

1. Use WordPress admin to manually activate:
   ```
   /wp-admin/plugins.php
   ```
   Look for "ZipPicks Vibes V2" and click "Activate"

2. Or use the manual activation script:
   ```
   /wp-content/plugins/zippicks-vibes/activate-plugin.php
   ```

### Option 2: Fix File Naming (Not Recommended)

Renaming the main file from `zippicks-vibes-v2.php` to `zippicks-vibes.php` would require:
- Updating all references in code
- Updating activation/deactivation hooks
- Updating all documentation
- Risk of breaking existing installations

### Option 3: WordPress Plugin API

Use WordPress functions to activate:
```php
activate_plugin('zippicks-vibes/zippicks-vibes-v2.php');
```

## Verification Steps

1. **Check Plugin List:**
   ```php
   $all_plugins = get_plugins();
   var_dump(array_keys($all_plugins));
   ```

2. **Check Active Plugins:**
   ```php
   $active_plugins = get_option('active_plugins');
   var_dump($active_plugins);
   ```

3. **Force Plugin Scan:**
   ```php
   wp_cache_delete('plugins', 'plugins');
   $plugins = get_plugins();
   ```

## Conclusion

The plugin detection issue is primarily due to:
1. The specific file naming convention used (`-v2` suffix)
2. References in code expecting different filenames
3. WordPress caching of plugin data

The plugin itself is correctly structured and should work once properly activated through WordPress admin or the manual activation script.