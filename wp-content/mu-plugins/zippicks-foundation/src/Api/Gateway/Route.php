<?php
/**
 * ZipPicks API Route
 * 
 * Represents a single API route with pattern matching and parameter binding
 *
 * @package ZipPicks\Foundation\Api\Gateway
 */

namespace ZipPicks\Foundation\Api\Gateway;

use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Core\Container;

class Route
{
    /**
     * HTTP method
     *
     * @var string
     */
    protected string $method;

    /**
     * Route path pattern
     *
     * @var string
     */
    protected string $path;

    /**
     * Route handler
     *
     * @var mixed
     */
    protected $handler;

    /**
     * Container instance
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Route middleware
     *
     * @var array
     */
    protected array $middleware = [];

    /**
     * Route name
     *
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * Compiled regex pattern
     *
     * @var string|null
     */
    protected ?string $regex = null;

    /**
     * Route parameters
     *
     * @var array
     */
    protected array $parameters = [];

    /**
     * Parameter names
     *
     * @var array
     */
    protected array $parameterNames = [];

    /**
     * Create a new route instance
     *
     * @param string $method
     * @param string $path
     * @param mixed $handler
     * @param Container $container
     */
    public function __construct(string $method, string $path, $handler, Container $container)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->handler = $handler;
        $this->container = $container;
        
        $this->compile();
    }

    /**
     * Compile the route pattern into a regex
     *
     * @return void
     */
    protected function compile(): void
    {
        $pattern = $this->path;
        
        // Extract parameter names
        preg_match_all('/\{([^}]+)\}/', $pattern, $matches);
        $this->parameterNames = $matches[1];
        
        // Convert route pattern to regex
        $pattern = preg_replace('/\//', '\\/', $pattern);
        $pattern = preg_replace('/\{([^}]+)\}/', '([^\/]+)', $pattern);
        $pattern = '/^' . $pattern . '$/';
        
        $this->regex = $pattern;
    }

    /**
     * Check if the route matches a given path
     *
     * @param string $path
     * @return bool
     */
    public function matches(string $path): bool
    {
        if (preg_match($this->regex, $path, $matches)) {
            // Remove the full match
            array_shift($matches);
            
            // Bind parameters
            $this->parameters = array_combine($this->parameterNames, $matches);
            
            return true;
        }
        
        return false;
    }

    /**
     * Dispatch the route handler
     *
     * @param Request $request
     * @return Response
     */
    public function dispatch(Request $request): Response
    {
        // Bind route parameters to request
        foreach ($this->parameters as $key => $value) {
            $request->attributes->set($key, $value);
        }
        
        // Resolve handler
        if (is_array($this->handler)) {
            [$controller, $method] = $this->handler;
            
            // Resolve controller from container
            if (is_string($controller)) {
                $controller = $this->container->make($controller);
            }
            
            // Call controller method
            $response = $controller->$method($request);
            
        } elseif (is_callable($this->handler)) {
            // Call closure or invokable
            $response = call_user_func($this->handler, $request);
            
        } else {
            throw new \RuntimeException('Invalid route handler');
        }
        
        // Ensure response is a Response object
        if (!$response instanceof Response) {
            $response = new Response($response);
        }
        
        return $response;
    }

    /**
     * Bind request data to route
     *
     * @param Request $request
     * @return void
     */
    public function bind(Request $request): void
    {
        // Additional binding logic if needed
    }

    /**
     * Add middleware to the route
     *
     * @param string|array $middleware
     * @return self
     */
    public function middleware($middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }

    /**
     * Set the route name
     *
     * @param string $name
     * @return self
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the HTTP method
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the route path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the route handler
     *
     * @return mixed
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * Get the route middleware
     *
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get the route name
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get route parameters
     *
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get a specific parameter
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function parameter(string $name, $default = null)
    {
        return $this->parameters[$name] ?? $default;
    }

    /**
     * Check if route has a parameter
     *
     * @param string $name
     * @return bool
     */
    public function hasParameter(string $name): bool
    {
        return isset($this->parameters[$name]);
    }
}