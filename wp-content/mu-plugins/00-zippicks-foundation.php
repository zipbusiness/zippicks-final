<?php
/**
 * ZipPicks Foundation - Simple MVP Version
 * 
 * A lightweight foundation that provides just enough infrastructure
 * to get ZipPicks running. Can be swapped with enterprise version later.
 * 
 * @package ZipPicks
 * @version 1.0.0-simple
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define critical constants
define('ZIPPICKS_FOUNDATION_ACTIVE', true);
define('ZIPPICKS_FOUNDATION_PATH', __DIR__ . '/zippicks-foundation');
define('ZIPPICKS_FOUNDATION_VERSION', '1.0.0-simple');


/**
 * Simple autoloader for ZipPicks classes
 */
spl_autoload_register(function ($class) {
    // Handle ZipPicks namespace
    if (strpos($class, 'ZipPicks\\') === 0) {
        // Remove ZipPicks\ prefix and convert to path
        $relative_class = substr($class, 9);
        
        // Try Foundation path first
        if (strpos($relative_class, 'Foundation\\') === 0) {
            $file = ZIPPICKS_FOUNDATION_PATH . '/src/' . 
                    str_replace('\\', '/', substr($relative_class, 11)) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
        
        // Try Core plugin path
        if (strpos($relative_class, 'Core\\') === 0 && defined('ZIPPICKS_CORE_PLUGIN_DIR')) {
            $file = ZIPPICKS_CORE_PLUGIN_DIR . 'src/' . 
                    str_replace('\\', '/', substr($relative_class, 5)) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

/**
 * Simple Service Container
 * Provides basic dependency injection without complexity
 */
class ZipPicksContainer {
    private static $instance = null;
    private $services = [];
    private $singletons = [];
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Bind a service to the container
     */
    public function bind($key, $resolver) {
        $this->services[$key] = $resolver;
    }
    
    /**
     * Bind a singleton service
     */
    public function singleton($key, $resolver) {
        $this->services[$key] = function($container) use ($key, $resolver) {
            if (!isset($this->singletons[$key])) {
                $this->singletons[$key] = is_callable($resolver) 
                    ? $resolver($container) 
                    : $resolver;
            }
            return $this->singletons[$key];
        };
    }
    
    /**
     * Get a service from the container
     */
    public function get($key) {
        if (!isset($this->services[$key])) {
            return null;
        }
        
        $resolver = $this->services[$key];
        return is_callable($resolver) ? $resolver($this) : $resolver;
    }
    
    /**
     * Check if service exists
     */
    public function has($key) {
        return isset($this->services[$key]);
    }
    
    /**
     * Alias for get() method (Laravel compatibility)
     */
    public function make($key) {
        return $this->get($key);
    }
}

/**
 * Simple Foundation Class
 * Minimal implementation for MVP
 */
class ZipPicksFoundation {
    private static $instance = null;
    private $container;
    private $booted = false;
    
    private function __construct() {
        $this->container = ZipPicksContainer::getInstance();
        $this->registerBaseBindings();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register base bindings
     */
    private function registerBaseBindings() {
        // Bind foundation instance
        $this->container->singleton('foundation', function() {
            return $this;
        });
        
        // Bind container
        $this->container->singleton('container', function() {
            return $this->container;
        });
        
        // Simple config array
        $this->container->singleton('config', function() {
            return [
                'app' => [
                    'name' => 'ZipPicks',
                    'version' => ZIPPICKS_FOUNDATION_VERSION,
                    'debug' => WP_DEBUG,
                ],
                'cache' => [
                    'default' => 'array',
                    'prefix' => 'zippicks_',
                ],
            ];
        });
        
        // HTTP Client (using WordPress HTTP API)
        $this->container->singleton('http', function() {
            return new class {
                private $default_args = [
                    'timeout' => 30,
                    'headers' => [
                        'User-Agent' => 'ZipPicks/1.0'
                    ]
                ];
                
                public function get($url, $args = []) {
                    $args = array_merge($this->default_args, $args);
                    return wp_remote_get($url, $args);
                }
                
                public function post($url, $args = []) {
                    $args = array_merge($this->default_args, $args);
                    return wp_remote_post($url, $args);
                }
                
                public function request($url, $args = []) {
                    $args = array_merge($this->default_args, $args);
                    return wp_remote_request($url, $args);
                }
            };
        });
        
        // Storage Manager (using WordPress uploads)
        $this->container->singleton('storage', function() {
            return new class {
                private $upload_dir;
                
                public function __construct() {
                    $this->upload_dir = wp_upload_dir();
                }
                
                public function put($path, $contents) {
                    $file_path = $this->upload_dir['basedir'] . '/' . ltrim($path, '/');
                    wp_mkdir_p(dirname($file_path));
                    return file_put_contents($file_path, $contents) !== false;
                }
                
                public function get($path) {
                    $file_path = $this->upload_dir['basedir'] . '/' . ltrim($path, '/');
                    return file_exists($file_path) ? file_get_contents($file_path) : null;
                }
                
                public function delete($path) {
                    $file_path = $this->upload_dir['basedir'] . '/' . ltrim($path, '/');
                    return file_exists($file_path) ? unlink($file_path) : false;
                }
                
                public function exists($path) {
                    $file_path = $this->upload_dir['basedir'] . '/' . ltrim($path, '/');
                    return file_exists($file_path);
                }
                
                public function url($path) {
                    return $this->upload_dir['baseurl'] . '/' . ltrim($path, '/');
                }
            };
        });
        
        // Simple Cache Manager (array-based for MVP)
        $this->container->singleton('cache', function() {
            return new class {
                private $cache = [];
                
                public function get($key, $default = null) {
                    return $this->cache[$key] ?? $default;
                }
                
                public function put($key, $value, $ttl = 3600) {
                    $this->cache[$key] = $value;
                    return true;
                }
                
                public function forget($key) {
                    unset($this->cache[$key]);
                    return true;
                }
                
                public function flush() {
                    $this->cache = [];
                    return true;
                }
                
                public function remember($key, $ttl, $callback) {
                    if (!isset($this->cache[$key])) {
                        $this->cache[$key] = $callback();
                    }
                    return $this->cache[$key];
                }
                
                public function deletePattern($pattern) {
                    $pattern = str_replace('*', '.*', $pattern);
                    foreach (array_keys($this->cache) as $key) {
                        if (preg_match('/^' . $pattern . '$/', $key)) {
                            unset($this->cache[$key]);
                        }
                    }
                    return true;
                }
            };
        });
        
        // Simple Logger (uses error_log for MVP)
        $this->container->singleton('logger', function() {
            return new class {
                private $context = [];
                public function emergency($message, array $context = []): void {
                    $this->log('EMERGENCY', $message, $context);
                }
                
                public function alert($message, array $context = []): void {
                    $this->log('ALERT', $message, $context);
                }
                
                public function critical($message, array $context = []): void {
                    $this->log('CRITICAL', $message, $context);
                }
                
                public function error($message, array $context = []): void {
                    $this->log('ERROR', $message, $context);
                }
                
                public function warning($message, array $context = []): void {
                    $this->log('WARNING', $message, $context);
                }
                
                public function notice($message, array $context = []): void {
                    $this->log('NOTICE', $message, $context);
                }
                
                public function info($message, array $context = []): void {
                    $this->log('INFO', $message, $context);
                }
                
                public function debug($message, array $context = []): void {
                    if (WP_DEBUG) {
                        $this->log('DEBUG', $message, $context);
                    }
                }
                
                public function log($level, $message, array $context = []): void {
                    $formatted = sprintf(
                        '[ZipPicks] [%s] %s %s',
                        $level,
                        $message,
                        !empty($context) ? json_encode($context) : ''
                    );
                    error_log($formatted);
                }
                
                public function setContext(array $context): void {
                    $this->context = $context;
                }
                
                public function getContext(): array {
                    return $this->context;
                }
            };
        });
        
        // Simple Validator
        $this->container->singleton('validator', function() {
            return new class {
                public function make(array $data, array $rules) {
                    return $this;
                }
                
                public function validate(array $data, array $rules) {
                    // Simple validation for MVP
                    $errors = [];
                    
                    foreach ($rules as $field => $ruleSet) {
                        $fieldRules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
                        $value = $data[$field] ?? null;
                        
                        foreach ($fieldRules as $rule) {
                            if ($rule === 'required' && empty($value)) {
                                $errors[$field][] = "$field is required";
                            }
                            
                            if (strpos($rule, 'max:') === 0 && $value) {
                                $max = (int) substr($rule, 4);
                                if (strlen($value) > $max) {
                                    $errors[$field][] = "$field must not exceed $max characters";
                                }
                            }
                            
                            if ($rule === 'email' && $value && !is_email($value)) {
                                $errors[$field][] = "$field must be a valid email";
                            }
                            
                            if ($rule === 'numeric' && $value && !is_numeric($value)) {
                                $errors[$field][] = "$field must be numeric";
                            }
                        }
                    }
                    
                    if (!empty($errors)) {
                        throw new \Exception('Validation failed: ' . json_encode($errors));
                    }
                    
                    return true;
                }
                
                public function fails() {
                    return false;
                }
                
                public function errors() {
                    return [];
                }
            };
        });
        
        // Simple Events Dispatcher
        $this->container->singleton('events', function() {
            return new class {
                public function dispatch($event, $data = null) {
                    // Use WordPress hooks for events
                    do_action('zippicks_event_' . $event, $data);
                    return $data;
                }
                
                public function listen($event, $callback) {
                    add_action('zippicks_event_' . $event, $callback);
                }
            };
        });
        
        // WordPress Database Wrapper
        $this->container->singleton('database', function() {
            global $wpdb;
            return $wpdb;
        });
        
        // Add missing services that Core expects
        
        // Queue (synchronous for MVP)
        $this->container->singleton('queue', function() {
            return new class {
                public function push($job, $data = null) {
                    // Execute immediately in MVP
                    if (is_callable($job)) {
                        return $job($data);
                    }
                    return false;
                }
                
                public function later($delay, $job, $data = null) {
                    // Schedule with WP Cron
                    return wp_schedule_single_event(time() + $delay, 'zippicks_queued_job', [$job, $data]);
                }
            };
        });
        
        // Auth (using WordPress user system)
        $this->container->singleton('auth', function() {
            return new class {
                public function check() {
                    return is_user_logged_in();
                }
                
                public function user() {
                    return wp_get_current_user();
                }
                
                public function id() {
                    return get_current_user_id();
                }
                
                public function can($capability) {
                    return current_user_can($capability);
                }
            };
        });
        
        // Rate Limiter (basic implementation)
        $this->container->singleton('rate_limiter', function() {
            return new class {
                private $attempts = [];
                
                public function tooManyAttempts($key, $maxAttempts = 60) {
                    $this->clearOldAttempts($key);
                    return isset($this->attempts[$key]) && count($this->attempts[$key]) >= $maxAttempts;
                }
                
                public function hit($key, $decayMinutes = 1) {
                    if (!isset($this->attempts[$key])) {
                        $this->attempts[$key] = [];
                    }
                    $this->attempts[$key][] = time();
                }
                
                private function clearOldAttempts($key) {
                    if (!isset($this->attempts[$key])) {
                        return;
                    }
                    $cutoff = time() - 60; // 1 minute
                    $this->attempts[$key] = array_filter($this->attempts[$key], function($time) use ($cutoff) {
                        return $time > $cutoff;
                    });
                }
            };
        });
    }
    
    /**
     * Boot the foundation
     */
    public function boot() {
        if ($this->booted) {
            return;
        }
        
        // Fire booted action
        do_action('zippicks_foundation_booted', $this);
        
        $this->booted = true;
        
        // Log success if in debug mode
        if (WP_DEBUG) {
            error_log('[ZipPicks Foundation] Simple foundation booted successfully');
        }
    }
    
    /**
     * Get the container
     */
    public function get_container() {
        return $this->container;
    }
    
    /**
     * Check if booted
     */
    public function isBooted() {
        return $this->booted;
    }
    
    /**
     * Register a service provider
     * Simple implementation for compatibility
     */
    public function register($provider) {
        // If provider has a register method, call it
        if (method_exists($provider, 'register')) {
            $provider->register();
        }
        
        // If provider has a boot method and foundation is booted, call it
        if ($this->booted && method_exists($provider, 'boot')) {
            $provider->boot();
        }
        
        return $this;
    }
    
    /**
     * Get configuration value (compatibility method)
     */
    public function config($key, $default = null) {
        return config($key, $default);
    }
}

// Simple Foundation provides basic services without Enterprise complexity

/**
 * Global helper functions
 */
if (!function_exists('zippicks_foundation')) {
    function zippicks_foundation() {
        return ZipPicksFoundation::getInstance();
    }
}

if (!function_exists('zippicks')) {
    function zippicks() {
        return ZipPicksContainer::getInstance();
    }
}

if (!function_exists('config')) {
    function config($key = null, $default = null) {
        $config = zippicks()->get('config');
        
        if ($key === null) {
            return $config;
        }
        
        // Simple dot notation support
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }
}

if (!function_exists('app')) {
    function app($id = null) {
        $container = zippicks();
        
        if ($id === null) {
            return $container;
        }
        
        return $container->get($id);
    }
}

if (!function_exists('zippicks_logger')) {
    function zippicks_logger() {
        return zippicks()->get('logger');
    }
}

// Alias for backward compatibility, but avoid conflicts
if (!function_exists('zp_log')) {
    function zp_log() {
        return zippicks()->get('logger');
    }
}

if (!function_exists('data_get')) {
    function data_get($target, $key, $default = null) {
        if (!is_array($target)) {
            return $default;
        }
        
        $keys = explode('.', $key);
        $value = $target;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }
}

// Boot the foundation
try {
    $foundation = ZipPicksFoundation::getInstance();
    $foundation->boot();
} catch (Exception $e) {
    error_log('[ZipPicks Foundation] Boot error: ' . $e->getMessage());
    
    // Show admin notice
    add_action('admin_notices', function() use ($e) {
        echo '<div class="notice notice-error"><p><strong>ZipPicks Foundation Error:</strong> ' . 
             esc_html($e->getMessage()) . '</p></div>';
    });
}

// Add status to admin bar (debug mode only)
if (WP_DEBUG && is_admin()) {
    add_action('admin_bar_menu', function($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $wp_admin_bar->add_node([
            'id' => 'zippicks-status',
            'title' => '✅ ZipPicks Simple',
            'href' => admin_url('plugins.php'),
        ]);
    }, 999);
}