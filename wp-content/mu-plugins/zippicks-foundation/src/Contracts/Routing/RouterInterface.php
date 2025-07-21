<?php
/**
 * Router Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Routing
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Routing;

use Closure;

interface RouterInterface
{
    /**
     * Register a GET route
     *
     * @param string $path
     * @param mixed $action
     * @return RouteInterface
     */
    public function get(string $path, mixed $action): RouteInterface;

    /**
     * Register a POST route
     *
     * @param string $path
     * @param mixed $action
     * @return RouteInterface
     */
    public function post(string $path, mixed $action): RouteInterface;

    /**
     * Register a route that responds to any HTTP method
     *
     * @param string $path
     * @param mixed $action
     * @return RouteInterface
     */
    public function any(string $path, mixed $action): RouteInterface;

    /**
     * Create a route group with shared attributes
     *
     * @param array<string, mixed> $attributes
     * @param Closure $callback
     * @return void
     */
    public function group(array $attributes, Closure $callback): void;

    /**
     * Add a route to the collection
     *
     * @param array<string>|string $methods
     * @param string $path
     * @param mixed $action
     * @return RouteInterface
     */
    public function addRoute(array|string $methods, string $path, mixed $action): RouteInterface;

    /**
     * Match the current request to a route
     *
     * @param string $method
     * @param string $path
     * @return ?RouteInterface
     */
    public function matchRoute(string $method, string $path): ?RouteInterface;

    /**
     * Dispatch the matched route
     *
     * @param RouteInterface $route
     * @param mixed $request
     * @return mixed
     */
    public function dispatch(RouteInterface $route, mixed $request): mixed;

    /**
     * Get all registered routes
     *
     * @return array<RouteInterface>
     */
    public function getRoutes(): array;

    /**
     * Get a route by name
     *
     * @param string $name
     * @return ?RouteInterface
     */
    public function getByName(string $name): ?RouteInterface;
}