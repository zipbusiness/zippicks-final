<?php
/**
 * Configuration Service
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Services;

class ConfigService {
    
    /**
     * Configuration cache
     *
     * @var array
     */
    private $config = [];
    
    /**
     * Environment variables prefix
     *
     * @var string
     */
    private $env_prefix = 'ZIPPICKS_BI_';
    
    /**
     * WordPress options prefix
     *
     * @var string
     */
    private $option_prefix = 'zippicks_bi_';
    
    /**
     * Default configuration values
     *
     * @var array
     */
    private $defaults = [
        'api_url' => 'https://zipbusiness-api.onrender.com',
        'api_key' => '',
        'cache_ttl' => 3600,
        'debug_mode' => false,
        'rate_limit' => 60,
        'retry_attempts' => 3,
        'timeout' => 30,
        'redis_host' => '127.0.0.1',
        'redis_port' => 6379,
        'redis_timeout' => 2.0,
        'redis_password' => '',
        'redis_database' => 0,
        'enable_logging' => true,
        'log_retention_days' => 30,
        'batch_size' => 100,
        'enable_async' => true
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_configuration();
    }
    
    /**
     * Load configuration from various sources
     */
    private function load_configuration() {
        // Load defaults
        $this->config = $this->defaults;
        
        // Override with WordPress options - SIMPLE version
        foreach ($this->defaults as $key => $default) {
            $option_value = get_option($this->option_prefix . $key);
            if ($option_value !== false) {
                $this->config[$key] = $this->cast_value($option_value, $default);
            }
        }
        
        // Override with environment variables
        $this->load_env_file();
        foreach ($this->defaults as $key => $default) {
            $env_key = strtoupper($this->env_prefix . $key);
            $env_value = getenv($env_key);
            if ($env_value !== false) {
                $this->config[$key] = $this->cast_value($env_value, $default);
            }
        }
        
        // Override with constants if defined
        $this->load_constants();
    }
    
    /**
     * Load .env file if it exists
     */
    private function load_env_file() {
        $env_file = ZIPPICKS_BI_PLUGIN_DIR . '.env';
        
        if (!file_exists($env_file)) {
            return;
        }
        
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            
            $key = trim($parts[0]);
            $value = trim($parts[1], " \t\n\r\0\x0B\"'");
            
            putenv("{$key}={$value}");
        }
    }
    
    /**
     * Load configuration from PHP constants
     */
    private function load_constants() {
        $constant_mappings = [
            'ZIPPICKS_BI_API_URL' => 'api_url',
            'ZIPPICKS_BI_API_KEY' => 'api_key',
            'ZIPPICKS_BI_DEBUG' => 'debug_mode',
            'ZIPPICKS_BI_CACHE_TTL' => 'cache_ttl'
        ];
        
        foreach ($constant_mappings as $constant => $config_key) {
            if (defined($constant)) {
                $this->config[$config_key] = constant($constant);
            }
        }
    }
    
    /**
     * Get configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Set configuration value
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @param bool $persist Whether to persist to database
     * @return bool
     */
    public function set(string $key, $value, bool $persist = false): bool {
        $this->config[$key] = $value;
        
        if ($persist) {
            return update_option($this->option_prefix . $key, $value);
        }
        
        return true;
    }
    
    /**
     * Get all configuration values
     *
     * @return array
     */
    public function all(): array {
        return $this->config;
    }
    
    /**
     * Check if configuration key exists
     *
     * @param string $key Configuration key
     * @return bool
     */
    public function has(string $key): bool {
        return isset($this->config[$key]);
    }
    
    /**
     * Validate configuration
     *
     * @return array Array of validation errors
     */
    public function validate(): array {
        $errors = [];
        
        // API URL validation
        if (empty($this->config['api_url'])) {
            $errors[] = 'API URL is required';
        } elseif (!filter_var($this->config['api_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'API URL is invalid';
        }
        
        // API key validation
        if (empty($this->config['api_key'])) {
            $errors[] = 'API key is required';
        }
        
        // Numeric validations
        $numeric_fields = [
            'cache_ttl' => [60, 86400],
            'rate_limit' => [1, 1000],
            'retry_attempts' => [1, 10],
            'timeout' => [5, 300]
        ];
        
        foreach ($numeric_fields as $field => [$min, $max]) {
            $value = $this->config[$field];
            if (!is_numeric($value) || $value < $min || $value > $max) {
                $errors[] = "{$field} must be between {$min} and {$max}";
            }
        }
        
        return $errors;
    }
    
    /**
     * Check if configuration is valid
     *
     * @return bool
     */
    public function is_valid(): bool {
        return empty($this->validate());
    }
    
    /**
     * Check if API key is set
     *
     * @return bool
     */
    public function has_api_key(): bool {
        return !empty($this->config['api_key']);
    }
    
    /**
     * Reset configuration to defaults
     *
     * @param bool $persist Whether to persist to database
     */
    public function reset(bool $persist = false) {
        $this->config = $this->defaults;
        
        if ($persist) {
            foreach ($this->defaults as $key => $value) {
                delete_option($this->option_prefix . $key);
            }
        }
    }
    
    /**
     * Export configuration
     *
     * @param bool $include_sensitive Whether to include sensitive data
     * @return array
     */
    public function export(bool $include_sensitive = false): array {
        $export = $this->config;
        
        if (!$include_sensitive) {
            // Mask sensitive values
            if (isset($export['api_key'])) {
                $export['api_key'] = $this->mask_string($export['api_key']);
            }
            if (isset($export['redis_password'])) {
                $export['redis_password'] = $this->mask_string($export['redis_password']);
            }
        }
        
        return $export;
    }
    
    /**
     * Import configuration
     *
     * @param array $config Configuration array
     * @param bool $persist Whether to persist to database
     * @return bool
     */
    public function import(array $config, bool $persist = false): bool {
        foreach ($config as $key => $value) {
            if (array_key_exists($key, $this->defaults)) {
                $this->set($key, $value, $persist);
            }
        }
        
        return true;
    }
    
    /**
     * Get configuration for JavaScript
     *
     * @return array
     */
    public function get_js_config(): array {
        return [
            'api_url' => $this->config['api_url'],
            'debug_mode' => $this->config['debug_mode'],
            'cache_ttl' => $this->config['cache_ttl'],
            'nonce' => wp_create_nonce('zippicks_bi_ajax')
        ];
    }
    
    /**
     * Cast value to appropriate type
     *
     * @param mixed $value Value to cast
     * @param mixed $default Default value for type reference
     * @return mixed
     */
    private function cast_value($value, $default) {
        if (is_bool($default)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } elseif (is_int($default)) {
            return (int) $value;
        } elseif (is_float($default)) {
            return (float) $value;
        }
        
        return $value;
    }
    
    /**
     * Mask sensitive string
     *
     * @param string $string String to mask
     * @return string
     */
    private function mask_string(string $string): string {
        if (strlen($string) <= 8) {
            return str_repeat('*', strlen($string));
        }
        
        return substr($string, 0, 4) . str_repeat('*', strlen($string) - 8) . substr($string, -4);
    }
}