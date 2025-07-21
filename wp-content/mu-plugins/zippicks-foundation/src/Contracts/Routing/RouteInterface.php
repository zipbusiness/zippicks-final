<?php
/**
 * Route Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Routing
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Routing;

interface RouteInterface
{
    /**
     * Get the HTTP method(s) this route responds to
     *
     * @return array<string>
     */
    public function getMethod(): array;

    /**
     * Get the route path pattern
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Get the route action
     *
     * @return mixed
     */
    public function getAction(): mixed;

    /**
     * Get the middleware assigned to this route
     *
     * @return array<string|object>
     */
    public function getMiddleware(): array;

    /**
     * Get the route name
     *
     * @return ?string
     */
    public function getName(): ?string;

    /**
     * Get route metadata
     *
     * @param ?string $key
     * @return mixed
     */
    public function getMetadata(?string $key = null): mixed;

    /**
     * Set route middleware
     *
     * @param array<string|object> $middleware
     * @return self
     */
    public function middleware(array $middleware): self;

    /**
     * Set the route name
     *
     * @param string $name
     * @return self
     */
    public function name(string $name): self;

    /**
     * Set route metadata
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function meta(string $key, mixed $value): self;

    /**
     * Get the route prefix
     *
     * @return string
     */
    public function getPrefix(): string;

    /**
     * Check if route matches the given method and path
     *
     * @param string $method
     * @param string $path
     * @return bool
     */
    public function matches(string $method, string $path): bool;

    /**
     * Get route parameters from path
     *
     * @param string $path
     * @return array<string, string>
     */
    public function getParameters(string $path): array;
}