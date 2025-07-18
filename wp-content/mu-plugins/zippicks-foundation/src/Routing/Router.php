<?php
/**
 * Router Implementation
 * 
 * @package ZipPicks\Foundation\Routing
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Routing;

use Closure;
use Exception;
use ReflectionClass;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Contracts\Middleware\RequestInterface;
use ZipPicks\Foundation\Contracts\Routing\RouteInterface;
use ZipPicks\Foundation\Contracts\Routing\RouterInterface;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Middleware\MiddlewarePipeline;

class Router implements RouterInterface
{
    /**
     * Container instance
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Middleware pipeline
     *
     * @var ?MiddlewarePipeline
     */
    protected ?MiddlewarePipeline $pipeline = null;

    /**
     * Logger instance
     *
     * @var ?LoggerInterface
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Registered routes
     *
     * @var array<RouteInterface>
     */
    protected array $routes = [];

    /**
     * Named routes
     *
     * @var array<string, RouteInterface>
     */
    protected array $namedRoutes = [];

    /**
     * Route group stack
     *
     * @var array<array<string, mixed>>
     */
    protected array $groupStack = [];

    /**
     * Current matched route
     *
     * @var ?RouteInterface
     */
    protected ?RouteInterface $currentRoute = null;

    /**
     * Create a new router instance
     *
     * @param ContainerInterface $container
     * @param ?MiddlewarePipeline $pipeline
     * @param ?LoggerInterface $logger
     */
    public function __construct(
        ContainerInterface $container,
        ?MiddlewarePipeline $pipeline = null,
        ?LoggerInterface $logger = null
    ) {
        $this->container = $container;
        $this->pipeline = $pipeline;
        $this->logger = $logger;
    }

    /**
     * Register a GET route
     *
     * @param string $path
     * @param mixed $action
     * @return RouteInterface
     */
    public function get(string $path, mixed $action): RouteInterface
    {
        return $this->addRoute('GET', $path, $action);
    }

    /**
     * Register a POST route
     *
     * @param string $path
     * @param mixed $action
     * @return RouteInterface
     */
    public function post(string $path, mixed $action): RouteInterface
    {
        return $this->addRoute('POST', $path, $action);
    }

    /**
     * Register a route that responds to any HTTP method
     *
     * @param string $path
     * @param mixed $action
     * @return RouteInterface
     */
    public function any(string $path, mixed $action): RouteInterface
    {
        return $this->addRoute('ANY', $path, $action);
    }

    /**
     * Create a route group with shared attributes
     *
     * @param array<string, mixed> $attributes
     * @param Closure $callback
     * @return void
     */
    public function group(array $attributes, Closure $callback): void
    {
        $this->groupStack[] = $attributes;

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * Add a route to the collection
     *
     * @param array<string>|string $methods
     * @param string $path
     * @param mixed $action
     * @return RouteInterface
     */
    public function addRoute(array|string $methods, string $path, mixed $action): RouteInterface
    {
        $route = new Route($methods, $path, $action);

        // Apply group attributes
        $this->applyGroupAttributes($route);

        // Add to routes collection
        $this->routes[] = $route;

        // Register named route
        if ($route->getName() !== null) {
            $this->namedRoutes[$route->getName()] = $route;
        }

        $this->logDebug('Route registered', [
            'methods' => $route->getMethod(),
            'path' => $route->getPath(),
            'action' => $this->getActionDescription($action)
        ]);

        return $route;
    }

    /**
     * Match the current request to a route
     *
     * @param string $method
     * @param string $path
     * @return ?RouteInterface
     */
    public function matchRoute(string $method, string $path): ?RouteInterface
    {
        $this->logDebug('Matching route', [
            'method' => $method,
            'path' => $path
        ]);

        foreach ($this->routes as $route) {
            if ($route->matches($method, $path)) {
                $this->currentRoute = $route;
                $this->logDebug('Route matched', [
                    'route' => $route->getPath(),
                    'name' => $route->getName()
                ]);
                return $route;
            }
        }

        $this->logDebug('No route matched');
        return null;
    }

    /**
     * Dispatch the matched route
     *
     * @param RouteInterface $route
     * @param mixed $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function dispatch(RouteInterface $route, mixed $request): ResponseInterface
    {
        $this->logDebug('Dispatching route', [
            'path' => $route->getPath(),
            'middleware_count' => count($route->getMiddleware())
        ]);

        // Extract route parameters based on request type
        $parameters = [];
        
        if ($request instanceof \ZipPicks\Foundation\Contracts\Middleware\RequestInterface) {
            // Old middleware request interface
            $parameters = $route->getParameters($request->getUri());
            foreach ($parameters as $key => $value) {
                $request = $request->withContext('route.' . $key, $value);
            }
        } elseif ($request instanceof \ZipPicks\Foundation\Contracts\Http\RequestInterface) {
            // New HTTP request interface
            $parameters = $route->getParameters($request->path());
            if ($request instanceof \ZipPicks\Foundation\Http\Request) {
                $request->setRouteParameters($parameters);
            }
        }
        
        // Fire event for route matched
        do_action('zippicks_route_matched', $route, $parameters);

        // Create destination closure that normalizes response
        $destination = function ($request) use ($route) {
            $result = $this->runRouteAction($route, $request);
            return $this->normalizeResponse($result);
        };

        // Run through middleware pipeline if available
        if ($this->pipeline !== null && !empty($route->getMiddleware())) {
            $result = $this->pipeline->process($request, $destination, $route->getMiddleware());
        } else {
            // Run directly without middleware
            $result = $destination($request);
        }

        // Ensure we always return a Response
        return $this->normalizeResponse($result);
    }

    /**
     * Get all registered routes
     *
     * @return array<RouteInterface>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get a route by name
     *
     * @param string $name
     * @return ?RouteInterface
     */
    public function getByName(string $name): ?RouteInterface
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Get the current matched route
     *
     * @return ?RouteInterface
     */
    public function getCurrentRoute(): ?RouteInterface
    {
        return $this->currentRoute;
    }

    /**
     * Apply group attributes to a route
     *
     * @param RouteInterface $route
     * @return void
     */
    protected function applyGroupAttributes(RouteInterface $route): void
    {
        if (empty($this->groupStack)) {
            return;
        }

        $attributes = $this->mergeGroupAttributes();

        // Apply prefix
        if (isset($attributes['prefix'])) {
            $route->setPrefix($attributes['prefix']);
        }

        // Apply middleware
        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware']) 
                ? $attributes['middleware'] 
                : [$attributes['middleware']];
            $route->middleware($middleware);
        }

        // Apply name prefix
        if (isset($attributes['as']) && $route->getName() !== null) {
            $route->name($attributes['as'] . $route->getName());
        }

        // Apply metadata
        foreach ($attributes as $key => $value) {
            if (!in_array($key, ['prefix', 'middleware', 'as'])) {
                $route->meta($key, $value);
            }
        }
    }

    /**
     * Merge group attributes from the stack
     *
     * @return array<string, mixed>
     */
    protected function mergeGroupAttributes(): array
    {
        $attributes = [];

        foreach ($this->groupStack as $group) {
            // Merge prefixes
            if (isset($group['prefix'])) {
                $attributes['prefix'] = ($attributes['prefix'] ?? '') . '/' . trim($group['prefix'], '/');
            }

            // Merge middleware
            if (isset($group['middleware'])) {
                $existing = $attributes['middleware'] ?? [];
                $new = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $attributes['middleware'] = array_merge($existing, $new);
            }

            // Merge name prefixes
            if (isset($group['as'])) {
                $attributes['as'] = ($attributes['as'] ?? '') . $group['as'];
            }

            // Merge other attributes
            foreach ($group as $key => $value) {
                if (!in_array($key, ['prefix', 'middleware', 'as'])) {
                    $attributes[$key] = $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * Run the route action
     *
     * @param RouteInterface $route
     * @param mixed $request
     * @return mixed
     * @throws Exception
     */
    protected function runRouteAction(RouteInterface $route, mixed $request): mixed
    {
        $action = $route->getAction();

        // Closure
        if ($action instanceof Closure) {
            return $action($request);
        }

        // Class@method syntax
        if (is_string($action) && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);
            $controller = $this->resolveController($class);
            
            if (!method_exists($controller, $method)) {
                throw new Exception(sprintf(
                    'Method %s does not exist on controller %s',
                    $method,
                    $class
                ));
            }

            return $controller->$method($request);
        }

        // Invokable class
        if (is_string($action) && class_exists($action)) {
            $controller = $this->resolveController($action);
            
            if (!is_callable($controller)) {
                throw new Exception(sprintf(
                    'Controller %s is not invokable',
                    $action
                ));
            }

            return $controller($request);
        }

        // Callable array [class, method]
        if (is_array($action) && count($action) === 2) {
            [$class, $method] = $action;
            
            if (is_string($class)) {
                $class = $this->resolveController($class);
            }

            return $class->$method($request);
        }

        throw new Exception('Invalid route action type');
    }

    /**
     * Resolve a controller from the container
     *
     * @param string $class
     * @return object
     * @throws Exception
     */
    protected function resolveController(string $class): object
    {
        if ($this->container->has($class)) {
            return $this->container->get($class);
        }

        if (!class_exists($class)) {
            throw new Exception(sprintf(
                'Controller class %s does not exist',
                $class
            ));
        }

        // Use reflection to check if we can instantiate
        $reflection = new ReflectionClass($class);
        
        if (!$reflection->isInstantiable()) {
            throw new Exception(sprintf(
                'Controller class %s is not instantiable',
                $class
            ));
        }

        // Try to instantiate with container support
        $constructor = $reflection->getConstructor();
        
        if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
            return new $class();
        }

        throw new Exception(sprintf(
            'Unable to resolve controller %s dependencies',
            $class
        ));
    }

    /**
     * Normalize a response value into a Response object
     *
     * @param mixed $response
     * @return ResponseInterface
     */
    protected function normalizeResponse(mixed $response): ResponseInterface
    {
        // Already a response
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        // Array or object - convert to JSON
        if (is_array($response) || is_object($response)) {
            return (new Response())->json($response);
        }

        // Boolean - convert to JSON success response
        if (is_bool($response)) {
            return (new Response())->json(['success' => $response]);
        }

        // Null - empty response
        if ($response === null) {
            return new Response();
        }

        // String or numeric - convert to HTML response
        return new Response((string) $response);
    }

    /**
     * Get action description for logging
     *
     * @param mixed $action
     * @return string
     */
    protected function getActionDescription(mixed $action): string
    {
        if ($action instanceof Closure) {
            return 'Closure';
        }

        if (is_string($action)) {
            return $action;
        }

        if (is_array($action) && count($action) === 2) {
            $class = is_object($action[0]) ? get_class($action[0]) : $action[0];
            return $class . '@' . $action[1];
        }

        return 'Unknown';
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->debug('[Router] ' . $message, $context);
        }
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error('[Router] ' . $message, $context);
        }
    }
}