<?php
/**
 * Route Implementation
 * 
 * @package ZipPicks\Foundation\Routing
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Routing;

use ZipPicks\Foundation\Contracts\Routing\RouteInterface;

class Route implements RouteInterface
{
    /**
     * HTTP methods
     *
     * @var array<string>
     */
    protected array $methods;

    /**
     * Route path pattern
     *
     * @var string
     */
    protected string $path;

    /**
     * Route action
     *
     * @var mixed
     */
    protected mixed $action;

    /**
     * Route middleware
     *
     * @var array<string|object>
     */
    protected array $middleware = [];

    /**
     * Route name
     *
     * @var ?string
     */
    protected ?string $name = null;

    /**
     * Route metadata
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Route prefix
     *
     * @var string
     */
    protected string $prefix = '';

    /**
     * Route parameters pattern
     *
     * @var ?string
     */
    protected ?string $pattern = null;

    /**
     * Create a new route instance
     *
     * @param array<string>|string $methods
     * @param string $path
     * @param mixed $action
     */
    public function __construct(array|string $methods, string $path, mixed $action)
    {
        $this->methods = is_array($methods) ? $methods : [$methods];
        $this->methods = array_map('strtoupper', $this->methods);
        $this->path = $this->normalizePath($path);
        $this->action = $action;
    }

    /**
     * Get the HTTP method(s) this route responds to
     *
     * @return array<string>
     */
    public function getMethod(): array
    {
        return $this->methods;
    }

    /**
     * Get the route path pattern
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->prefix . $this->path;
    }

    /**
     * Get the route action
     *
     * @return mixed
     */
    public function getAction(): mixed
    {
        return $this->action;
    }

    /**
     * Get the middleware assigned to this route
     *
     * @return array<string|object>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get the route name
     *
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get route metadata
     *
     * @param ?string $key
     * @return mixed
     */
    public function getMetadata(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    /**
     * Set route middleware
     *
     * @param array<string|object> $middleware
     * @return self
     */
    public function middleware(array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
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
     * Set route metadata
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function meta(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Get the route prefix
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Set the route prefix
     *
     * @param string $prefix
     * @return self
     */
    public function setPrefix(string $prefix): self
    {
        $this->prefix = $this->normalizePath($prefix);
        return $this;
    }

    /**
     * Check if route matches the given method and path
     *
     * @param string $method
     * @param string $path
     * @return bool
     */
    public function matches(string $method, string $path): bool
    {
        $method = strtoupper($method);
        
        // Check if method matches
        if (!in_array($method, $this->methods) && !in_array('ANY', $this->methods)) {
            return false;
        }

        // Check if path matches
        $pattern = $this->getPattern();
        return (bool) preg_match($pattern, $path);
    }

    /**
     * Get route parameters from path
     *
     * @param string $path
     * @return array<string, string>
     */
    public function getParameters(string $path): array
    {
        $pattern = $this->getPattern();
        $matches = [];
        
        if (!preg_match($pattern, $path, $matches)) {
            return [];
        }

        $parameters = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Get the regex pattern for this route
     *
     * @return string
     */
    protected function getPattern(): string
    {
        if ($this->pattern === null) {
            $this->pattern = $this->compilePattern($this->getPath());
        }

        return $this->pattern;
    }

    /**
     * Compile route path into regex pattern
     *
     * @param string $path
     * @return string
     */
    protected function compilePattern(string $path): string
    {
        // Escape special regex characters except for parameter placeholders
        $pattern = preg_quote($path, '#');
        
        // Replace {param} with named capture groups
        $pattern = preg_replace_callback(
            '/\\\{(\w+)\\\}/',
            fn($matches) => '(?P<' . $matches[1] . '>[^/]+)',
            $pattern
        );

        // Replace {param?} with optional named capture groups
        $pattern = preg_replace_callback(
            '/\\\{(\w+)\?\\\}/',
            fn($matches) => '(?P<' . $matches[1] . '>[^/]*)',
            $pattern
        );

        return '#^' . $pattern . '$#';
    }

    /**
     * Normalize path by ensuring it starts with /
     *
     * @param string $path
     * @return string
     */
    protected function normalizePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        return '/' . ltrim($path, '/');
    }
}