<?php
/**
 * Service Container Implementation
 * 
 * @package ZipPicks\Foundation\Core
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Core;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Container\NotFoundException;
use ZipPicks\Foundation\Contracts\Container\ContainerException;

/**
 * PSR-11 compliant dependency injection container
 */
class Container implements ContainerInterface
{
    /**
     * Container bindings
     * 
     * @var array<string, mixed>
     */
    protected array $bindings = [];

    /**
     * Shared instances
     * 
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * Registered aliases
     * 
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * Currently resolving stack
     * 
     * @var string[]
     */
    protected array $resolvingStack = [];

    /**
     * {@inheritdoc}
     */
    public function get(string $id): mixed
    {
        try {
            return $this->resolve($id);
        } catch (\Throwable $e) {
            if ($this->has($id)) {
                throw new ContainerException(
                    "Error while resolving '{$id}': " . $e->getMessage(),
                    0,
                    $e
                );
            }

            throw new NotFoundException("Entry '{$id}' not found in container", 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || 
               isset($this->instances[$id]) || 
               isset($this->aliases[$id]);
    }

    /**
     * Bind a value into the container
     * 
     * @param string $id
     * @param mixed $concrete
     * @param bool $shared
     * 
     * @return void
     */
    public function bind(string $id, mixed $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $id;
        }

        $this->bindings[$id] = compact('concrete', 'shared');
    }

    /**
     * Bind a singleton into the container
     * 
     * @param string $id
     * @param mixed $concrete
     * 
     * @return void
     */
    public function singleton(string $id, mixed $concrete = null): void
    {
        $this->bind($id, $concrete, true);
    }

    /**
     * Register an existing instance
     * 
     * @param string $id
     * @param mixed $instance
     * 
     * @return void
     */
    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
        unset($this->bindings[$id]);
    }

    /**
     * Register an alias
     * 
     * @param string $alias
     * @param string $id
     * 
     * @return void
     */
    public function alias(string $alias, string $id): void
    {
        $this->aliases[$alias] = $id;
    }

    /**
     * Resolve a dependency from the container
     * 
     * @param string $id
     * @param array<string, mixed> $parameters
     * 
     * @return mixed
     * @throws ContainerException
     */
    protected function resolve(string $id, array $parameters = []): mixed
    {
        // Check for circular dependencies
        if (in_array($id, $this->resolvingStack, true)) {
            throw new ContainerException(
                'Circular dependency detected: ' . implode(' -> ', $this->resolvingStack) . ' -> ' . $id
            );
        }

        // Resolve aliases
        if (isset($this->aliases[$id])) {
            return $this->resolve($this->aliases[$id], $parameters);
        }

        // Return existing instance
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $this->resolvingStack[] = $id;

        try {
            $concrete = $this->getConcrete($id);
            $object = $this->build($concrete, $parameters);

            // Store as singleton if needed
            if ($this->isShared($id)) {
                $this->instances[$id] = $object;
            }

            return $object;
        } finally {
            array_pop($this->resolvingStack);
        }
    }

    /**
     * Get the concrete type for an id
     * 
     * @param string $id
     * 
     * @return mixed
     */
    protected function getConcrete(string $id): mixed
    {
        if (!isset($this->bindings[$id])) {
            return $id;
        }

        return $this->bindings[$id]['concrete'];
    }

    /**
     * Check if an id should be shared
     * 
     * @param string $id
     * 
     * @return bool
     */
    protected function isShared(string $id): bool
    {
        return isset($this->bindings[$id]['shared']) && $this->bindings[$id]['shared'];
    }

    /**
     * Build an instance of the given type
     * 
     * @param mixed $concrete
     * @param array<string, mixed> $parameters
     * 
     * @return mixed
     * @throws ContainerException
     */
    protected function build(mixed $concrete, array $parameters = []): mixed
    {
        // If concrete is a closure, execute it
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        // If concrete is not a class name, return it
        if (!is_string($concrete) || !class_exists($concrete)) {
            return $concrete;
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new ContainerException("Class '{$concrete}' does not exist", 0, $e);
        }

        // Check if class is instantiable
        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Class '{$concrete}' is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        // If there is no constructor, just instantiate the class
        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor dependencies
     * 
     * @param ReflectionParameter[] $parameters
     * @param array<string, mixed> $primitives
     * 
     * @return array<int, mixed>
     * @throws ContainerException
     */
    protected function resolveDependencies(array $parameters, array $primitives = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // If an override parameter was passed, use it
            if (array_key_exists($name, $primitives)) {
                $dependencies[] = $primitives[$name];
                continue;
            }

            // Resolve class dependencies
            $type = $parameter->getType();
            
            if ($type && !$type->isBuiltin()) {
                $className = $type->getName();
                
                try {
                    $dependencies[] = $this->resolve($className);
                } catch (\Throwable $e) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        throw new ContainerException(
                            "Unable to resolve dependency '{$className}' for parameter '{$name}'",
                            0,
                            $e
                        );
                    }
                }
                continue;
            }

            // Use default value if available
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // Unable to resolve dependency
            throw new ContainerException(
                "Unable to resolve non-class dependency for parameter '{$name}'"
            );
        }

        return $dependencies;
    }

    /**
     * Call a method with dependency injection
     * 
     * @param callable $callback
     * @param array<string, mixed> $parameters
     * 
     * @return mixed
     * @throws ContainerException
     */
    public function call(callable $callback, array $parameters = []): mixed
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback, 2);
            $callback = [$this->resolve($class), $method];
        }

        if (is_array($callback) && is_string($callback[0])) {
            $callback[0] = $this->resolve($callback[0]);
        }

        if (!is_callable($callback)) {
            throw new ContainerException('Callback is not callable');
        }

        return call_user_func_array($callback, $parameters);
    }

    /**
     * Remove a binding from the container
     * 
     * @param string $id
     * 
     * @return void
     */
    public function forget(string $id): void
    {
        unset($this->bindings[$id], $this->instances[$id], $this->aliases[$id]);
    }

    /**
     * Clear all bindings and instances
     * 
     * @return void
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->resolvingStack = [];
    }
}