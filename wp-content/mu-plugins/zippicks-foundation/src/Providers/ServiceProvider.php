<?php
/**
 * Abstract Service Provider
 * 
 * @package ZipPicks\Foundation\Providers
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Providers;

use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Contracts\ServiceProviderInterface;

/**
 * Abstract base class for service providers
 */
abstract class ServiceProvider implements ServiceProviderInterface
{
    /**
     * The foundation instance
     * 
     * @var Foundation
     */
    protected Foundation $foundation;

    /**
     * Create a new service provider instance
     * 
     * @param Foundation $foundation
     */
    public function __construct(Foundation $foundation)
    {
        $this->foundation = $foundation;
    }

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        // Override in child classes if needed
    }

    /**
     * Register a binding in the container
     * 
     * @param string $id
     * @param mixed $concrete
     * @param bool $shared
     * 
     * @return void
     */
    protected function bind(string $id, mixed $concrete = null, bool $shared = false): void
    {
        $this->foundation->getContainer()->bind($id, $concrete, $shared);
    }

    /**
     * Register a singleton in the container
     * 
     * @param string $id
     * @param mixed $concrete
     * 
     * @return void
     */
    protected function singleton(string $id, mixed $concrete = null): void
    {
        $this->foundation->getContainer()->singleton($id, $concrete);
    }

    /**
     * Register an instance in the container
     * 
     * @param string $id
     * @param mixed $instance
     * 
     * @return void
     */
    protected function instance(string $id, mixed $instance): void
    {
        $this->foundation->getContainer()->instance($id, $instance);
    }

    /**
     * Register an alias in the container
     * 
     * @param string $alias
     * @param string $id
     * 
     * @return void
     */
    protected function alias(string $alias, string $id): void
    {
        $this->foundation->getContainer()->alias($alias, $id);
    }

    /**
     * Get a value from the container
     * 
     * @param string $id
     * 
     * @return mixed
     */
    protected function get(string $id): mixed
    {
        return $this->foundation->getContainer()->get($id);
    }

    /**
     * Check if container has a binding
     * 
     * @param string $id
     * 
     * @return bool
     */
    protected function has(string $id): bool
    {
        return $this->foundation->getContainer()->has($id);
    }

    /**
     * Get configuration value
     * 
     * @param string $key
     * @param mixed $default
     * 
     * @return mixed
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->foundation->config($key, $default);
    }

    /**
     * Register WordPress hooks
     * 
     * @param string $hook
     * @param callable $callback
     * @param int $priority
     * @param int $acceptedArgs
     * 
     * @return void
     */
    protected function hook(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Register WordPress action
     * 
     * @param string $action
     * @param callable $callback
     * @param int $priority
     * @param int $acceptedArgs
     * 
     * @return void
     */
    protected function action(string $action, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_action($action, $callback, $priority, $acceptedArgs);
    }

    /**
     * Register WordPress filter
     * 
     * @param string $filter
     * @param callable $callback
     * @param int $priority
     * @param int $acceptedArgs
     * 
     * @return void
     */
    protected function filter(string $filter, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_filter($filter, $callback, $priority, $acceptedArgs);
    }
}