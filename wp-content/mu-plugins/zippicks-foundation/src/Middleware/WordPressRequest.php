<?php
/**
 * WordPress Request Implementation
 * 
 * @package ZipPicks\Foundation\Middleware
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Middleware;

use ZipPicks\Foundation\Contracts\Middleware\RequestInterface;

class WordPressRequest implements RequestInterface
{
    /**
     * Request context data
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Create a new WordPress request instance
     *
     * @return self
     */
    public static function capture(): self
    {
        return new self();
    }

    /**
     * Get the request method
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Get the request URI
     *
     * @return string
     */
    public function getUri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Get all headers
     *
     * @return array<string, string|string[]>
     */
    public function getHeaders(): array
    {
        $headers = [];
        
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $name => $value) {
                if (str_starts_with($name, 'HTTP_')) {
                    $headerName = str_replace('_', '-', substr($name, 5));
                    $headers[$headerName] = $value;
                }
            }
        }
        
        return $headers;
    }

    /**
     * Get a specific header value
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        $headers = $this->getHeaders();
        $normalizedName = strtolower($name);
        
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $normalizedName) {
                return $value;
            }
        }
        
        return $default;
    }

    /**
     * Get all query parameters
     *
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $_GET;
    }

    /**
     * Get all POST/body parameters
     *
     * @return array<string, mixed>
     */
    public function getBodyParams(): array
    {
        return $_POST;
    }

    /**
     * Get a specific parameter from query or body
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }
        
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        
        return $default;
    }

    /**
     * Get WordPress-specific context
     * 
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return array_merge($this->context, [
            'is_admin' => is_admin(),
            'is_ajax' => wp_doing_ajax(),
            'is_rest' => defined('REST_REQUEST') && REST_REQUEST,
            'is_cron' => wp_doing_cron(),
            'current_user_id' => get_current_user_id(),
            'current_screen' => function_exists('get_current_screen') ? get_current_screen() : null,
        ]);
    }

    /**
     * Add or update context data
     * 
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function withContext(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->context[$key] = $value;
        return $clone;
    }
}