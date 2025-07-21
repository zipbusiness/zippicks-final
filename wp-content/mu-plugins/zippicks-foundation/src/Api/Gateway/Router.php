<?php
/**
 * ZipPicks API Router
 * 
 * High-performance routing engine for API endpoints
 * Supports versioning, resource routing, and middleware
 *
 * @package ZipPicks\Foundation\Api\Gateway
 */

namespace ZipPicks\Foundation\Api\Gateway;

use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Api\Exceptions\RouteNotFoundException;

class Router
{
    /**
     * The service container
     *
     * @var Container
     */
    protected Container $container;

    /**
     * All registered routes
     *
     * @var array
     */
    protected array $routes = [];

    /**
     * Route groups
     *
     * @var array
     */
    protected array $groups = [];

    /**
     * Current group prefix
     *
     * @var string
     */
    protected string $groupPrefix = '';

    /**
     * Current group middleware
     *
     * @var array
     */
    protected array $groupMiddleware = [];

    /**
     * Resource route names
     *
     * @var array
     */
    protected array $resourceDefaults = [
        'index' => ['GET', ''],
        'create' => ['GET', '/create'],
        'store' => ['POST', ''],
        'show' => ['GET', '/{id}'],
        'edit' => ['GET', '/{id}/edit'],
        'update' => ['PUT', '/{id}'],
        'destroy' => ['DELETE', '/{id}']
    ];

    /**
     * Create a new router instance
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register a GET route
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return Route
     */
    public function get(string $path, $handler, array $options = []): Route
    {
        return $this->addRoute('GET', $path, $handler, $options);
    }

    /**
     * Register a POST route
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return Route
     */
    public function post(string $path, $handler, array $options = []): Route
    {
        return $this->addRoute('POST', $path, $handler, $options);
    }

    /**
     * Register a PUT route
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return Route
     */
    public function put(string $path, $handler, array $options = []): Route
    {
        return $this->addRoute('PUT', $path, $handler, $options);
    }

    /**
     * Register a PATCH route
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return Route
     */
    public function patch(string $path, $handler, array $options = []): Route
    {
        return $this->addRoute('PATCH', $path, $handler, $options);
    }

    /**
     * Register a DELETE route
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return Route
     */
    public function delete(string $path, $handler, array $options = []): Route
    {
        return $this->addRoute('DELETE', $path, $handler, $options);
    }

    /**
     * Register a route that responds to multiple HTTP verbs
     *
     * @param array $methods
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return Route
     */
    public function match(array $methods, string $path, $handler, array $options = []): Route
    {
        $route = null;
        foreach ($methods as $method) {
            $route = $this->addRoute(strtoupper($method), $path, $handler, $options);
        }
        return $route;
    }

    /**
     * Register a route that responds to all HTTP verbs
     *
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return Route
     */
    public function any(string $path, $handler, array $options = []): Route
    {
        return $this->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $path, $handler, $options);
    }

    /**
     * Register a resource controller
     *
     * @param string $name
     * @param string $controller
     * @param array $options
     * @return void
     */
    public function resource(string $name, string $controller, array $options = []): void
    {
        $only = $options['only'] ?? array_keys($this->resourceDefaults);
        $except = $options['except'] ?? [];
        
        foreach ($this->resourceDefaults as $action => [$method, $path]) {
            if (in_array($action, $only) && !in_array($action, $except)) {
                $this->addRoute($method, "/{$name}{$path}", [$controller, $action], [
                    'as' => "{$name}.{$action}"
                ]);
            }
        }
    }

    /**
     * Create a route group
     *
     * @param array $attributes
     * @param callable $routes
     * @return void
     */
    public function group(array $attributes, callable $routes): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;
        
        // Apply group attributes
        if (isset($attributes['prefix'])) {
            $this->groupPrefix = trim($previousPrefix . '/' . trim($attributes['prefix'], '/'), '/');
        }
        
        if (isset($attributes['middleware'])) {
            $this->groupMiddleware = array_merge($previousMiddleware, (array) $attributes['middleware']);
        }
        
        // Execute routes within group
        $routes($this);
        
        // Restore previous group state
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Add a route to the collection
     *
     * @param string $method
     * @param string $path
     * @param mixed $handler
     * @param array $options
     * @return Route
     */
    protected function addRoute(string $method, string $path, $handler, array $options = []): Route
    {
        // Apply group prefix
        if ($this->groupPrefix) {
            $path = '/' . trim($this->groupPrefix . '/' . trim($path, '/'), '/');
        }
        
        // Create route instance
        $route = new Route($method, $path, $handler, $this->container);
        
        // Apply group middleware
        if ($this->groupMiddleware) {
            $route->middleware($this->groupMiddleware);
        }
        
        // Apply route options
        if (isset($options['middleware'])) {
            $route->middleware($options['middleware']);
        }
        
        if (isset($options['as'])) {
            $route->name($options['as']);
        }
        
        // Store route
        $this->routes[$method][] = $route;
        
        return $route;
    }

    /**
     * Match a request to a route
     *
     * @param Request $request
     * @param string $version
     * @return Route|null
     */
    public function matchRequest(Request $request, string $version): ?Route
    {
        $method = $request->method();
        $path = $request->path();
        
        // Remove API prefix and version from path
        $path = preg_replace("#^/api/{$version}#", '', $path) ?: '/';
        
        if (!isset($this->routes[$method])) {
            return null;
        }
        
        foreach ($this->routes[$method] as $route) {
            if ($route->matches($path)) {
                // Bind request parameters to route
                $route->bind($request);
                return $route;
            }
        }
        
        return null;
    }

    /**
     * Get all routes
     *
     * @return array
     */
    public function getRoutes(): array
    {
        $allRoutes = [];
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                $allRoutes[] = [
                    'method' => $method,
                    'path' => $route->getPath(),
                    'name' => $route->getName(),
                    'middleware' => $route->getMiddleware(),
                    'handler' => $route->getHandler()
                ];
            }
        }
        return $allRoutes;
    }

    /**
     * Get routes for a specific method
     *
     * @param string $method
     * @return array
     */
    public function getRoutesByMethod(string $method): array
    {
        return $this->routes[strtoupper($method)] ?? [];
    }

    /**
     * Check if a route exists
     *
     * @param string $method
     * @param string $path
     * @return bool
     */
    public function hasRoute(string $method, string $path): bool
    {
        if (!isset($this->routes[strtoupper($method)])) {
            return false;
        }
        
        foreach ($this->routes[strtoupper($method)] as $route) {
            if ($route->matches($path)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Clear all routes
     *
     * @return void
     */
    public function clear(): void
    {
        $this->routes = [];
        $this->groups = [];
        $this->groupPrefix = '';
        $this->groupMiddleware = [];
    }
}