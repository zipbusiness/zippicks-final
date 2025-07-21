<?php
/**
 * Request Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Http
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Http;

interface RequestInterface
{
    /**
     * Get the request method
     *
     * @return string
     */
    public function method(): string;

    /**
     * Get the request URI
     *
     * @return string
     */
    public function uri(): string;

    /**
     * Get the request path
     *
     * @return string
     */
    public function path(): string;

    /**
     * Get the request URL
     *
     * @return string
     */
    public function url(): string;

    /**
     * Get the full URL with query string
     *
     * @return string
     */
    public function fullUrl(): string;

    /**
     * Get a query parameter value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function query(string $key, mixed $default = null): mixed;

    /**
     * Get all query parameters
     *
     * @return array<string, mixed>
     */
    public function queryAll(): array;

    /**
     * Get a POST parameter value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed;

    /**
     * Get all POST parameters
     *
     * @return array<string, mixed>
     */
    public function postAll(): array;

    /**
     * Get an input value from any source (POST, GET, JSON)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed;

    /**
     * Get all input data
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Get only specified input keys
     *
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array;

    /**
     * Get all input except specified keys
     *
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array;

    /**
     * Check if input has a key
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Check if input has all specified keys
     *
     * @param array<string> $keys
     * @return bool
     */
    public function hasAll(array $keys): bool;

    /**
     * Check if input has any of the specified keys
     *
     * @param array<string> $keys
     * @return bool
     */
    public function hasAny(array $keys): bool;

    /**
     * Get a header value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function header(string $key, mixed $default = null): mixed;

    /**
     * Get all headers
     *
     * @return array<string, string>
     */
    public function headers(): array;

    /**
     * Get a cookie value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function cookie(string $key, mixed $default = null): mixed;

    /**
     * Get all cookies
     *
     * @return array<string, string>
     */
    public function cookies(): array;

    /**
     * Get the raw request body
     *
     * @return string
     */
    public function body(): string;

    /**
     * Get JSON decoded body
     *
     * @param bool $assoc
     * @return mixed
     */
    public function json(bool $assoc = true): mixed;

    /**
     * Check if request is JSON
     *
     * @return bool
     */
    public function isJson(): bool;

    /**
     * Check if request expects JSON
     *
     * @return bool
     */
    public function expectsJson(): bool;

    /**
     * Check if request is AJAX
     *
     * @return bool
     */
    public function isAjax(): bool;

    /**
     * Check if request is secure (HTTPS)
     *
     * @return bool
     */
    public function isSecure(): bool;

    /**
     * Check if request method matches
     *
     * @param string $method
     * @return bool
     */
    public function isMethod(string $method): bool;

    /**
     * Check if request is GET
     *
     * @return bool
     */
    public function isGet(): bool;

    /**
     * Check if request is POST
     *
     * @return bool
     */
    public function isPost(): bool;

    /**
     * Check if request is PUT
     *
     * @return bool
     */
    public function isPut(): bool;

    /**
     * Check if request is DELETE
     *
     * @return bool
     */
    public function isDelete(): bool;

    /**
     * Check if request is PATCH
     *
     * @return bool
     */
    public function isPatch(): bool;

    /**
     * Get the client IP address
     *
     * @return string
     */
    public function ip(): string;

    /**
     * Get the user agent
     *
     * @return string
     */
    public function userAgent(): string;

    /**
     * Get route parameter value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function route(string $key, mixed $default = null): mixed;

    /**
     * Get all route parameters
     *
     * @return array<string, mixed>
     */
    public function routeParameters(): array;

    /**
     * Create a new request with merged input
     *
     * @param array<string, mixed> $input
     * @return self
     */
    public function merge(array $input): self;

    /**
     * Create a new request with replaced input
     *
     * @param array<string, mixed> $input
     * @return self
     */
    public function replace(array $input): self;

    /**
     * Get WordPress context information
     *
     * @return array<string, mixed>
     */
    public function context(): array;

    /**
     * Check if in WordPress admin
     *
     * @return bool
     */
    public function isAdmin(): bool;

    /**
     * Check if WordPress REST request
     *
     * @return bool
     */
    public function isRest(): bool;

    /**
     * Check if WordPress cron request
     *
     * @return bool
     */
    public function isCron(): bool;

    /**
     * Check if WordPress CLI request
     *
     * @return bool
     */
    public function isCli(): bool;
}