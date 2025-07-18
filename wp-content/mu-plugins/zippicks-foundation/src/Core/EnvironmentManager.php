<?php
/**
 * Environment Manager
 * 
 * Manages multi-environment configuration loading and merging
 * for the ZipPicks Foundation platform.
 * 
 * @package ZipPicks\Foundation\Core
 * @since 2.0.0
 */

namespace ZipPicks\Foundation\Core;

use ZipPicks\Foundation\Exceptions\FoundationException;

class EnvironmentManager
{
    /**
     * Supported environments
     */
    const ENV_DEVELOPMENT = 'development';
    const ENV_STAGING = 'staging';
    const ENV_PRODUCTION = 'production';
    
    /**
     * @var string Current environment
     */
    protected string $environment;
    
    /**
     * @var array Loaded configuration
     */
    protected array $config = [];
    
    /**
     * @var array Environment-specific overrides
     */
    protected array $overrides = [];
    
    /**
     * @var bool Configuration loaded flag
     */
    protected bool $loaded = false;
    
    /**
     * @var string Configuration base path
     */
    protected string $configPath;
    
    /**
     * Constructor
     * 
     * @param string|null $environment Force specific environment
     */
    public function __construct(?string $environment = null)
    {
        $this->configPath = ZIPPICKS_FOUNDATION_PATH . '/config';
        $this->environment = $this->detectEnvironment($environment);
        $this->validateEnvironment();
    }
    
    /**
     * Detect current environment
     * 
     * @param string|null $forced Forced environment
     * @return string
     */
    protected function detectEnvironment(?string $forced = null): string
    {
        // Priority order:
        // 1. Forced environment (for testing)
        if ($forced !== null) {
            return $forced;
        }
        
        // 2. Environment variable
        if ($env = getenv('ZIPPICKS_ENV')) {
            return $env;
        }
        
        // 3. WordPress environment constant
        if (defined('WP_ENVIRONMENT_TYPE')) {
            return $this->mapWordPressEnvironment(WP_ENVIRONMENT_TYPE);
        }
        
        // 4. Server variable
        if (isset($_SERVER['ZIPPICKS_ENV'])) {
            return $_SERVER['ZIPPICKS_ENV'];
        }
        
        // 5. Domain-based detection
        if (isset($_SERVER['HTTP_HOST'])) {
            return $this->detectByDomain($_SERVER['HTTP_HOST']);
        }
        
        // 6. Default to development
        return self::ENV_DEVELOPMENT;
    }
    
    /**
     * Map WordPress environment types to our environments
     * 
     * @param string $wpEnv WordPress environment type
     * @return string
     */
    protected function mapWordPressEnvironment(string $wpEnv): string
    {
        $mapping = [
            'local' => self::ENV_DEVELOPMENT,
            'development' => self::ENV_DEVELOPMENT,
            'staging' => self::ENV_STAGING,
            'production' => self::ENV_PRODUCTION
        ];
        
        return $mapping[$wpEnv] ?? self::ENV_DEVELOPMENT;
    }
    
    /**
     * Detect environment by domain
     * 
     * @param string $domain
     * @return string
     */
    protected function detectByDomain(string $domain): string
    {
        if (strpos($domain, 'localhost') !== false || strpos($domain, '.local') !== false) {
            return self::ENV_DEVELOPMENT;
        }
        
        if (strpos($domain, 'staging') !== false || strpos($domain, 'stage') !== false) {
            return self::ENV_STAGING;
        }
        
        return self::ENV_PRODUCTION;
    }
    
    /**
     * Validate environment
     * 
     * @throws FoundationException
     */
    protected function validateEnvironment(): void
    {
        $valid = [
            self::ENV_DEVELOPMENT,
            self::ENV_STAGING,
            self::ENV_PRODUCTION
        ];
        
        if (!in_array($this->environment, $valid, true)) {
            throw new FoundationException(
                "Invalid environment: {$this->environment}. Valid environments: " . implode(', ', $valid)
            );
        }
    }
    
    /**
     * Load configuration
     * 
     * @return array
     * @throws FoundationException
     */
    public function load(): array
    {
        if ($this->loaded) {
            return $this->config;
        }
        
        // Load base configuration
        $this->loadBaseConfig();
        
        // Load environment-specific configuration
        $this->loadEnvironmentConfig();
        
        // Apply overrides
        $this->applyOverrides();
        
        // Validate configuration
        $this->validateConfig();
        
        $this->loaded = true;
        
        return $this->config;
    }
    
    /**
     * Load base configuration files
     */
    protected function loadBaseConfig(): void
    {
        $files = [
            'app', 'auth', 'cache', 'logging', 
            'queue', 'rate_limiting', 'settings', 
            'storage', 'validation', 'providers'
        ];
        
        foreach ($files as $file) {
            $path = "{$this->configPath}/{$file}.php";
            if (file_exists($path)) {
                $this->config[$file] = require $path;
            }
        }
    }
    
    /**
     * Load environment-specific configuration
     * 
     * @throws FoundationException
     */
    protected function loadEnvironmentConfig(): void
    {
        $envFile = "{$this->configPath}/environments/{$this->environment}.php";
        
        if (!file_exists($envFile)) {
            throw new FoundationException(
                "Environment configuration file not found: {$envFile}"
            );
        }
        
        $envConfig = require $envFile;
        
        // Deep merge with base configuration
        $this->config = $this->mergeDeep($this->config, $envConfig);
    }
    
    /**
     * Apply runtime overrides
     */
    protected function applyOverrides(): void
    {
        // Apply any registered overrides
        if (!empty($this->overrides)) {
            $this->config = $this->mergeDeep($this->config, $this->overrides);
        }
        
        // Apply environment variable overrides
        $this->applyEnvOverrides();
    }
    
    /**
     * Apply environment variable overrides
     */
    protected function applyEnvOverrides(): void
    {
        // Database overrides
        if ($dbHost = getenv('DB_HOST')) {
            $this->config['database']['connections']['mysql']['host'] = $dbHost;
        }
        
        // Redis overrides
        if ($redisHost = getenv('REDIS_HOST')) {
            if (isset($this->config['cache']['stores']['redis']['connection'])) {
                $this->config['cache']['stores']['redis']['connection']['host'] = $redisHost;
            }
        }
        
        // API URL override
        if ($apiUrl = getenv('APP_URL')) {
            $this->config['app']['url'] = $apiUrl;
        }
    }
    
    /**
     * Validate configuration
     * 
     * @throws FoundationException
     */
    protected function validateConfig(): void
    {
        // Ensure critical configuration exists
        $required = [
            'app.env',
            'database.default',
            'cache.default',
            'logging.default'
        ];
        
        foreach ($required as $key) {
            if ($this->get($key) === null) {
                throw new FoundationException(
                    "Required configuration missing: {$key}"
                );
            }
        }
        
        // Validate encryption key in production
        if ($this->isProduction() && empty($this->get('security.encryption.key'))) {
            throw new FoundationException(
                'Encryption key must be set in production environment'
            );
        }
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Dot notation key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (!$this->loaded) {
            $this->load();
        }
        
        return $this->arrayGet($this->config, $key, $default);
    }
    
    /**
     * Set configuration value
     * 
     * @param string $key Dot notation key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, $value): void
    {
        if (!$this->loaded) {
            $this->load();
        }
        
        $this->arraySet($this->config, $key, $value);
    }
    
    /**
     * Override configuration values
     * 
     * @param array $overrides
     * @return void
     */
    public function override(array $overrides): void
    {
        $this->overrides = $this->mergeDeep($this->overrides, $overrides);
        
        // Reload configuration if already loaded
        if ($this->loaded) {
            $this->loaded = false;
            $this->config = [];
            $this->load();
        }
    }
    
    /**
     * Get current environment
     * 
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }
    
    /**
     * Check if production environment
     * 
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->environment === self::ENV_PRODUCTION;
    }
    
    /**
     * Check if staging environment
     * 
     * @return bool
     */
    public function isStaging(): bool
    {
        return $this->environment === self::ENV_STAGING;
    }
    
    /**
     * Check if development environment
     * 
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return $this->environment === self::ENV_DEVELOPMENT;
    }
    
    /**
     * Check if debugging is enabled
     * 
     * @return bool
     */
    public function isDebug(): bool
    {
        return (bool) $this->get('app.debug', false);
    }
    
    /**
     * Get all configuration
     * 
     * @return array
     */
    public function all(): array
    {
        if (!$this->loaded) {
            $this->load();
        }
        
        return $this->config;
    }
    
    /**
     * Deep merge arrays
     * 
     * @param array $array1
     * @param array $array2
     * @return array
     */
    protected function mergeDeep(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = $this->mergeDeep($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        
        return $array1;
    }
    
    /**
     * Get array value using dot notation
     * 
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function arrayGet(array $array, string $key, $default = null)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }
        
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            
            $array = $array[$segment];
        }
        
        return $array;
    }
    
    /**
     * Set array value using dot notation
     * 
     * @param array &$array
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function arraySet(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        
        while (count($keys) > 1) {
            $key = array_shift($keys);
            
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            
            $array = &$array[$key];
        }
        
        $array[array_shift($keys)] = $value;
    }
}

/**
 * Helper function to get environment manager instance
 * 
 * @return EnvironmentManager
 */
function env_manager(): EnvironmentManager
{
    static $instance = null;
    
    if ($instance === null) {
        $instance = new EnvironmentManager();
    }
    
    return $instance;
}

/**
 * Helper function to get config value
 * 
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function config(string $key, $default = null)
{
    return env_manager()->get($key, $default);
}

/**
 * Helper function to check if production
 * 
 * @return bool
 */
function is_production(): bool
{
    return env_manager()->isProduction();
}