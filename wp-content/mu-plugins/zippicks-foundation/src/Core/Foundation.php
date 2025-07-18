<?php
/**
 * Foundation Core Class
 * 
 * @package ZipPicks\Foundation\Core
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Core;

use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\ServiceProviderInterface;
use ZipPicks\Foundation\Exceptions\FoundationException;

/**
 * Core foundation class that bootstraps the entire system
 */
final class Foundation
{
    /**
     * Foundation instance
     * 
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Service container
     * 
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * Registered service providers
     * 
     * @var ServiceProviderInterface[]
     */
    private array $providers = [];

    /**
     * Booted service providers
     * 
     * @var array<class-string, bool>
     */
    private array $bootedProviders = [];

    /**
     * Foundation has been bootstrapped
     * 
     * @var bool
     */
    private bool $booted = false;

    /**
     * Foundation configuration
     * 
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * Private constructor to enforce singleton
     */
    private function __construct()
    {
        $this->container = new Container();
        $this->registerBaseBindings();
    }

    /**
     * Get the singleton instance
     * 
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserializing of the instance
     * 
     * @throws FoundationException
     */
    public function __wakeup()
    {
        throw new FoundationException('Cannot unserialize singleton');
    }

    /**
     * Boot the foundation
     * 
     * @return void
     * @throws FoundationException
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        try {
            // Load configuration
            $this->loadConfiguration();

            // Register core service providers
            $this->registerCoreProviders();

            // Boot all registered providers
            $this->bootProviders();

            // Validate all core services are available (skip in debug mode)
            if (!defined('ZIPPICKS_FOUNDATION_SKIP_VALIDATION') || !ZIPPICKS_FOUNDATION_SKIP_VALIDATION) {
                $this->validateServices();
            }

            // Mark as booted
            $this->booted = true;

            // Fire booted event
            \do_action('zippicks_foundation_booted', $this);

        } catch (\Throwable $e) {
            throw new FoundationException(
                'Failed to boot foundation: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Register base container bindings
     * 
     * @return void
     */
    private function registerBaseBindings(): void
    {
        // Bind the foundation instance
        $this->container->instance('foundation', $this);
        $this->container->instance(self::class, $this);

        // Bind the container
        $this->container->instance('container', $this->container);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(ContainerInterface::class, $this->container);
    }

    /**
     * Load configuration files
     * 
     * @return void
     */
    private function loadConfiguration(): void
    {
        // Use EnvironmentManager for configuration loading
        $envManager = new EnvironmentManager();
        $this->config = $envManager->load();

        // Bind environment manager to container
        $this->container->instance('env', $envManager);
        $this->container->instance(EnvironmentManager::class, $envManager);

        // Bind configuration to container
        $this->container->instance('config', $this->config);
    }

    /**
     * Register core service providers
     * 
     * @return void
     */
    private function registerCoreProviders(): void
    {
        $providers = $this->config['providers'] ?? [];

        foreach ($providers as $providerClass) {
            $this->register($providerClass);
        }
    }

    /**
     * Register a service provider
     * 
     * @param string|ServiceProviderInterface $provider
     * 
     * @return void
     * @throws FoundationException
     */
    public function register(string|ServiceProviderInterface $provider): void
    {
        if (is_string($provider)) {
            if (!class_exists($provider)) {
                throw new FoundationException("Provider class does not exist: {$provider}");
            }

            $provider = new $provider($this);
        }

        if (!$provider instanceof ServiceProviderInterface) {
            throw new FoundationException('Provider must implement ServiceProviderInterface');
        }

        // Register the provider
        $provider->register();
        
        // Store the provider
        $this->providers[] = $provider;

        // Boot the provider if foundation is already booted
        if ($this->booted) {
            $this->bootProvider($provider);
        }
    }

    /**
     * Boot all registered providers
     * 
     * @return void
     */
    private function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
            $this->bootProvider($provider);
        }
    }

    /**
     * Boot a single provider
     * 
     * @param ServiceProviderInterface $provider
     * 
     * @return void
     */
    private function bootProvider(ServiceProviderInterface $provider): void
    {
        $providerClass = get_class($provider);
        
        if (isset($this->bootedProviders[$providerClass])) {
            return;
        }

        $provider->boot();
        $this->bootedProviders[$providerClass] = true;
    }

    /**
     * Get the service container
     * 
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get configuration value
     * 
     * @param string $key
     * @param mixed $default
     * 
     * @return mixed
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Check if foundation has been booted
     * 
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Magic method to get services from container
     * 
     * @param string $name
     * 
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->container->get($name);
    }

    /**
     * Magic method to check if service exists in container
     * 
     * @param string $name
     * 
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->container->has($name);
    }

    /**
     * Validate that all core services are properly registered
     * 
     * @return void
     * @throws FoundationException
     */
    private function validateServices(): void
    {
        $requiredServices = [
            'config' => 'Configuration',
            \ZipPicks\Foundation\Contracts\Logging\LoggerInterface::class => 'Logger',
            \ZipPicks\Foundation\Settings\SettingsManager::class => 'Settings Manager',
            \ZipPicks\Foundation\Contracts\Cache\CacheInterface::class => 'Cache',
            \ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface::class => 'Event Dispatcher',
            \ZipPicks\Foundation\Contracts\Validation\ValidatorInterface::class => 'Validator',
            \ZipPicks\Foundation\Contracts\Auth\AuthManagerInterface::class => 'Authentication Manager',
            \ZipPicks\Foundation\Contracts\Exceptions\HandlerInterface::class => 'Exception Handler',
            \ZipPicks\Foundation\Contracts\Routing\RouterInterface::class => 'Router',
            \ZipPicks\Foundation\Contracts\Storage\FilesystemInterface::class => 'Storage',
        ];

        $missingServices = [];
        
        foreach ($requiredServices as $service => $name) {
            if (!$this->container->has($service)) {
                $missingServices[] = "{$name} ({$service})";
            }
        }

        if (!empty($missingServices)) {
            throw new FoundationException(
                'Required services are not registered: ' . implode(', ', $missingServices)
            );
        }
    }

    /**
     * Get a service from the container
     * 
     * @param string $abstract
     * @param array<string, mixed> $parameters
     * 
     * @return mixed
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->container->make($abstract, $parameters);
    }

    /**
     * Check if a service exists in the container
     * 
     * @param string $abstract
     * 
     * @return bool
     */
    public function has(string $abstract): bool
    {
        return $this->container->has($abstract);
    }
}