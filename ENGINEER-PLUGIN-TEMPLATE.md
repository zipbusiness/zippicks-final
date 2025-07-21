# ZipPicks Plugin Development Template

## CRITICAL: Your plugin MUST follow this structure or it will not work!

### Required Plugin Structure
```
plugins/zippicks-{feature}/
├── zippicks-{feature}.php      # Main file (use template below)
├── includes/
│   └── class-{feature}.php     # Main class
└── composer.json               # Optional
```

### Main Plugin File Template
```php
<?php
/**
 * Plugin Name: ZipPicks {Feature}
 * Plugin URI: https://zippicks.com
 * Description: {Description}
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Requires Plugins: zippicks-core
 * Author: ZipPicks
 * License: Proprietary
 * Text Domain: zippicks-{feature}
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('ZIPPICKS_{FEATURE}_VERSION', '1.0.0');
define('ZIPPICKS_{FEATURE}_PLUGIN_FILE', __FILE__);
define('ZIPPICKS_{FEATURE}_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZIPPICKS_{FEATURE}_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader (if needed)
spl_autoload_register(function ($class) {
    $prefix = 'ZipPicks\\{Feature}\\';
    $base_dir = ZIPPICKS_{FEATURE}_PLUGIN_DIR . 'src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// CRITICAL: Load after core (priority 25+)
add_action('plugins_loaded', 'zippicks_{feature}_init', 25);

function zippicks_{feature}_init() {
    // CRITICAL: Check dependencies
    if (!function_exists('zippicks')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('ZipPicks {Feature} requires ZipPicks Core to be activated.', 'zippicks-{feature}'); ?></p>
            </div>
            <?php
        });
        return;
    }
    
    // Bootstrap plugin
    require_once ZIPPICKS_{FEATURE}_PLUGIN_DIR . 'includes/class-{feature}.php';
    $plugin = new ZipPicks\{Feature}\{Feature}();
    $plugin->run();
}

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create tables if needed
    require_once ZIPPICKS_{FEATURE}_PLUGIN_DIR . 'includes/class-activator.php';
    ZipPicks\{Feature}\Activator::activate();
});
```

### Service Registration Pattern
```php
// In your main class
public function run() {
    // Register your service
    if (zippicks()->has('bind')) {
        zippicks()->bind('{feature}.manager', function() {
            return new {Feature}Manager();
        });
    }
    
    // Use other services with graceful degradation
    $this->cache = zippicks()->get('cache'); // May be null
    $this->logger = zippicks()->get('logger'); // May be null
}

// ALWAYS check before using
if ($this->cache) {
    $data = $this->cache->get('key');
}
```

### Database Tables
```php
// In Activator::activate()
global $wpdb;
$table_name = $wpdb->prefix . 'zippicks_{feature}_data';

$sql = "CREATE TABLE $table_name (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);
```

### REST API Endpoints
```php
add_action('rest_api_init', function() {
    register_rest_route('zippicks/v1', '/{feature}', [
        'methods' => 'GET',
        'callback' => 'handle_request',
        'permission_callback' => '__return_true', // Public
    ]);
});
```

### Critical Rules

1. **NEVER assume Foundation services exist**
   ```php
   // BAD - Will crash
   zippicks()->get('cache')->set('key', 'value');
   
   // GOOD - Graceful degradation
   $cache = zippicks()->get('cache');
   if ($cache) {
       $cache->set('key', 'value');
   }
   ```

2. **NEVER use global functions without prefix**
   ```php
   // BAD
   function get_vibes() {}
   
   // GOOD
   function zippicks_vibes_get_vibes() {}
   ```

3. **ALWAYS namespace your classes**
   ```php
   namespace ZipPicks\Vibes; // NOT ZipPicks\Core\Vibes
   ```

4. **ALWAYS check load order**
   - Foundation loads at priority 0 (MU plugin)
   - Core loads at priority 20
   - Your plugin loads at 25+

### Testing Checklist
- [ ] Plugin activates without fatal errors
- [ ] Shows error message if Core not active
- [ ] All database tables created
- [ ] No PHP warnings or notices
- [ ] Works with Simple Foundation
- [ ] REST endpoints accessible
- [ ] Admin pages load (if any)

### Common Errors & Fixes

**Error**: "Class 'ZipPicks\Core\Container' not found"
**Fix**: Check dependencies with `if (!function_exists('zippicks'))`

**Error**: "Call to undefined function zippicks()"
**Fix**: Your plugin is loading too early. Use priority 25+

**Error**: "Headers already sent"
**Fix**: Remove any whitespace/output before `<?php`

### Environment Info
- Using Simple Foundation (not Enterprise)
- Custom tables preferred over CPTs
- Services may or may not be available
- Always graceful degradation

### Questions?
1. Check if Core plugin has similar functionality
2. Copy patterns from Core plugin
3. Test with Simple Foundation only