<?php
/**
 * Request Interface for Middleware System
 * 
 * @package ZipPicks\Foundation\Contracts\Middleware
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Middleware;

interface RequestInterface
{
    /**
     * Get the request method (GET, POST, etc.)
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * Get the request URI
     *
     * @return string
     */
    public function getUri(): string;

    /**
     * Get all headers
     *
     * @return array<string, string|string[]>
     */
    public function getHeaders(): array;

    /**
     * Get a specific header value
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getHeader(string $name, mixed $default = null): mixed;

    /**
     * Get all query parameters
     *
     * @return array<string, mixed>
     */
    public function getQueryParams(): array;

    /**
     * Get all POST/body parameters
     *
     * @return array<string, mixed>
     */
    public function getBodyParams(): array;

    /**
     * Get a specific parameter from query or body
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Get WordPress-specific context
     * 
     * @return array<string, mixed>
     */
    public function getContext(): array;

    /**
     * Add or update context data
     * 
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function withContext(string $key, mixed $value): self;
}