# ZipPicks Vibes WordPress Plugin Fix

**Priority**: Fix the crash and make it work properly as a WordPress plugin  
**Time Required**: 2-3 hours

## The Real Problem

The plugin is trying to be too clever with service containers and Redis connections. It's creating multiple instances because it's using `bind()` instead of `singleton()` in the Foundation registration.

## Simple WordPress-Compatible Fixes

### Fix 1: Update ServiceProvider.php 

Replace the problematic `register_with_foundation()` method (starting at line 181):

```php
private function register_with_foundation(): void {
    try {
        // Cache Manager - Use WordPress transients as fallback
        if (!zippicks()->has('vibes.cache')) {
            zippicks()->singleton('vibes.cache', function() {
                static $instance = null;
                if ($instance === null) {
                    $logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
                    $instance = new CacheManager(null, [
                        'prefix' => 'zippicks_vibes_',
                        'default_ttl' => 300
                    ], $logger);
                }
                return $instance;
            });
        }
        
        // Repository - Standard WordPress pattern
        if (!zippicks()->has('vibes.repository')) {
            zippicks()->singleton('vibes.repository', function() {
                global $wpdb;
                $cache = zippicks()->get('vibes.cache');
                $logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
                return new VibeRepository($wpdb, $cache, $logger);
            });
        }
        
        // Service layer
        if (!zippicks()->has('vibes.service')) {
            zippicks()->singleton('vibes.service', function() {
                return new VibeService(
                    zippicks()->get('vibes.repository'),
                    zippicks()->has('logger') ? zippicks()->get('logger') : null,
                    zippicks()->get('vibes.cache')
                );
            });
        }
        
        // Continue for other services...
        
    } catch (Exception $e) {
        // Log error but don't crash
        error_log('ZipPicks Vibes: Service registration failed - ' . $e->getMessage());
    }
}
```

### Fix 2: Simplify CacheManager.php

Make it WordPress-friendly by using transients as fallback:

```php
public function __construct(?string $driver = null, array $config = [], $logger = null) {
    $this->logger = $logger;
    $this->prefix = $config['prefix'] ?? $this->prefix;
    $this->defaultTtl = $config['default_ttl'] ?? $this->defaultTtl;
    
    // Initialize adapters
    $this->initializeAdapters($driver);
}

private function initializeAdapters(?string $driver): void {
    $adapters = [];
    
    // Try Redis if available
    if (class_exists('Redis') && ($driver === null || $driver === 'redis')) {
        try {
            // Use existing Redis connection if available from object cache
            if (function_exists('wp_cache_get_redis_object')) {
                $redis = wp_cache_get_redis_object();
                if ($redis && $redis->ping()) {
                    $adapters[] = new RedisAdapter($redis, $this->prefix);
                }
            } else {
                // Try to create ONE connection
                static $redisConnection = null;
                if ($redisConnection === null) {
                    $redisConnection = new Redis();
                    if (@$redisConnection->connect('127.0.0.1', 6379, 2)) {
                        $adapters[] = new RedisAdapter($redisConnection, $this->prefix);
                    }
                }
            }
        } catch (Exception $e) {
            // Silent fail - use fallbacks
        }
    }
    
    // Always add WordPress transients as fallback
    $adapters[] = new TransientAdapter($this->prefix);
    
    // Set primary and fallbacks
    $this->adapter = array_shift($adapters);
    $this->fallbackAdapters = $adapters;
}
```

### Fix 3: Fix Plugin Initialization (zippicks-vibes.php)

Add safety checks at line 96:

```php
// Initialize plugin with safeguards
function zippicks_vibes_init() {
    // Prevent multiple initializations
    static $initialized = false;
    if ($initialized) {
        return ZipPicksVibes\VibesPlugin::get_instance();
    }
    $initialized = true;
    
    // Check dependencies
    if (!file_exists(ZIPPICKS_VIBES_DIR . 'src/class-vibes-plugin.php')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>ZipPicks Vibes: Missing required files. Please reinstall the plugin.</p></div>';
        });
        return null;
    }
    
    return ZipPicksVibes\VibesPlugin::get_instance();
}

// Use WordPress init hook properly
add_action('init', 'zippicks_vibes_init', 10);
```

### Fix 4: Remove Incompatible Object Cache

Create a mu-plugin to fix the object cache issue:

```php
<?php
// File: /wp-content/mu-plugins/fix-object-cache-compat.php

/**
 * Fix object cache compatibility issues
 */
if (class_exists('WP_Object_Cache') && version_compare(PHP_VERSION, '8.2', '>=')) {
    // Define the property properly for PHP 8.2+
    if (!property_exists('WP_Object_Cache', 'multisite')) {
        class WP_Object_Cache_Compat extends WP_Object_Cache {
            public $multisite;
            
            public function __construct() {
                $this->multisite = is_multisite();
                parent::__construct();
            }
        }
        
        // Replace the global
        $GLOBALS['wp_object_cache'] = new WP_Object_Cache_Compat();
    }
}
```

### Fix 5: Add WordPress Health Check

Add to the main plugin file:

```php
// Add to WordPress Site Health
add_filter('site_status_tests', function($tests) {
    $tests['direct']['zippicks_vibes'] = [
        'label' => __('ZipPicks Vibes Health Check'),
        'test' => 'zippicks_vibes_health_check'
    ];
    return $tests;
});

function zippicks_vibes_health_check() {
    $result = [
        'label' => __('ZipPicks Vibes is operational'),
        'status' => 'good',
        'badge' => [
            'label' => __('Performance'),
            'color' => 'green'
        ],
        'description' => sprintf(
            '<p>%s</p>',
            __('ZipPicks Vibes is working correctly.')
        ),
        'test' => 'zippicks_vibes'
    ];
    
    // Check if tables exist
    global $wpdb;
    $table = $wpdb->prefix . 'zippicks_vibes';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        $result['status'] = 'critical';
        $result['label'] = __('ZipPicks Vibes database tables missing');
        $result['badge']['color'] = 'red';
        $result['description'] = __('Required database tables are missing. Please deactivate and reactivate the plugin.');
    }
    
    return $result;
}
```

## WordPress Best Practices Applied

1. **Use WordPress APIs** - Transients, wp_cache functions, global $wpdb
2. **Follow WordPress Coding Standards** - Proper hooks, filters, and naming
3. **Graceful Degradation** - Works without Redis, falls back to transients
4. **WordPress Admin Integration** - Proper admin notices and health checks
5. **No Over-Engineering** - Simple, maintainable WordPress code

## Deployment Steps

1. **Backup the site** (always)
2. **Update the files**:
   ```bash
   # Update ServiceProvider.php with singleton pattern
   # Update CacheManager.php with WordPress fallbacks
   # Update main plugin file with safety checks
   ```
3. **Add the mu-plugin** for object cache compatibility
4. **Clear caches**:
   ```bash
   wp cache flush
   wp transient delete --all
   ```
5. **Reactivate the plugin**:
   ```bash
   wp plugin deactivate zippicks-vibes
   wp plugin activate zippicks-vibes
   ```

## Testing

```bash
# Check plugin status
wp plugin list --field=name,status | grep vibes

# Check for errors
tail -f wp-content/debug.log

# Test basic functionality
curl -I https://site.com/wp-json/zippicks/v1/vibes
```

## Long-term Improvements (Optional)

1. **Use WordPress Cron** instead of custom schedulers
2. **Integrate with WordPress REST API** properly
3. **Use WordPress nonces** for CSRF protection
4. **Follow WordPress database table naming** conventions
5. **Add uninstall.php** for clean removal

This is a practical WordPress solution that:
- Works with existing WordPress infrastructure
- Doesn't require Docker/Kubernetes/Redis clusters
- Can be implemented in hours, not weeks
- Follows WordPress best practices
- Actually fixes the problem