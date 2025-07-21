<?php
/**
 * Request Implementation
 * 
 * @package ZipPicks\Foundation\Http
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Http;

use ZipPicks\Foundation\Contracts\Http\RequestInterface;
use ZipPicks\Foundation\Contracts\Validation\ValidatorInterface;
use ZipPicks\Foundation\Exceptions\ValidationException;

class Request implements RequestInterface
{
    /**
     * Query parameters
     *
     * @var array<string, mixed>
     */
    protected array $query = [];

    /**
     * POST parameters
     *
     * @var array<string, mixed>
     */
    protected array $post = [];

    /**
     * Cookies
     *
     * @var array<string, string>
     */
    protected array $cookies = [];

    /**
     * Headers
     *
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * Server variables
     *
     * @var array<string, mixed>
     */
    protected array $server = [];

    /**
     * Route parameters
     *
     * @var array<string, mixed>
     */
    protected array $routeParams = [];

    /**
     * Raw body content
     *
     * @var ?string
     */
    protected ?string $body = null;

    /**
     * Parsed JSON content
     *
     * @var mixed
     */
    protected mixed $json = null;

    /**
     * Whether JSON has been parsed
     *
     * @var bool
     */
    protected bool $jsonParsed = false;

    /**
     * All merged input
     *
     * @var ?array<string, mixed>
     */
    protected ?array $allInput = null;

    /**
     * Create a new request instance
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $cookies
     * @param array<string, string> $headers
     * @param array<string, mixed> $server
     * @param ?string $body
     */
    public function __construct(
        array $query = [],
        array $post = [],
        array $cookies = [],
        array $headers = [],
        array $server = [],
        ?string $body = null
    ) {
        $this->query = $this->sanitizeInput($query);
        $this->post = $this->sanitizeInput($post);
        $this->cookies = $this->sanitizeStringArray($cookies);
        $this->headers = $this->normalizeHeaders($headers);
        $this->server = $server;
        $this->body = $body;
    }

    /**
     * Create from PHP globals
     *
     * @return static
     */
    public static function capture(): static
    {
        return new static(
            $_GET,
            $_POST,
            $_COOKIE,
            static::captureHeaders(),
            $_SERVER,
            static::captureBody()
        );
    }

    /**
     * Get the request method
     *
     * @return string
     */
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Get the request URI
     *
     * @return string
     */
    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /**
     * Get the request path
     *
     * @return string
     */
    public function path(): string
    {
        $uri = $this->uri();
        $position = strpos($uri, '?');
        
        return $position === false ? $uri : substr($uri, 0, $position);
    }

    /**
     * Get the request URL
     *
     * @return string
     */
    public function url(): string
    {
        $protocol = $this->isSecure() ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        $path = $this->path();
        
        return "{$protocol}://{$host}{$path}";
    }

    /**
     * Get the full URL with query string
     *
     * @return string
     */
    public function fullUrl(): string
    {
        $url = $this->url();
        $queryString = $this->server['QUERY_STRING'] ?? '';
        
        return $queryString ? "{$url}?{$queryString}" : $url;
    }

    /**
     * Get a query parameter value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->getFromArray($this->query, $key, $default);
    }

    /**
     * Get all query parameters
     *
     * @return array<string, mixed>
     */
    public function queryAll(): array
    {
        return $this->query;
    }

    /**
     * Get a POST parameter value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->getFromArray($this->post, $key, $default);
    }

    /**
     * Get all POST parameters
     *
     * @return array<string, mixed>
     */
    public function postAll(): array
    {
        return $this->post;
    }

    /**
     * Get an input value from any source
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->getFromArray($this->all(), $key, $default);
    }

    /**
     * Get all input data
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->allInput === null) {
            $this->allInput = array_merge(
                $this->query,
                $this->post,
                $this->getJsonInput(),
                $this->routeParams
            );
        }
        
        return $this->allInput;
    }

    /**
     * Get only specified input keys
     *
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $results = [];
        $all = $this->all();
        
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $results[$key] = $all[$key];
            }
        }
        
        return $results;
    }

    /**
     * Get all input except specified keys
     *
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        $all = $this->all();
        
        foreach ($keys as $key) {
            unset($all[$key]);
        }
        
        return $all;
    }

    /**
     * Check if input has a key
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Check if input has all specified keys
     *
     * @param array<string> $keys
     * @return bool
     */
    public function hasAll(array $keys): bool
    {
        $all = $this->all();
        
        foreach ($keys as $key) {
            if (!array_key_exists($key, $all)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check if input has any of the specified keys
     *
     * @param array<string> $keys
     * @return bool
     */
    public function hasAny(array $keys): bool
    {
        $all = $this->all();
        
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get a header value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function header(string $key, mixed $default = null): mixed
    {
        $key = $this->normalizeHeaderKey($key);
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get all headers
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get a cookie value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Get all cookies
     *
     * @return array<string, string>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * Get the raw request body
     *
     * @return string
     */
    public function body(): string
    {
        if ($this->body === null) {
            $this->body = static::captureBody();
        }
        
        return $this->body;
    }

    /**
     * Get JSON decoded body
     *
     * @param bool $assoc
     * @return mixed
     */
    public function json(bool $assoc = true): mixed
    {
        if (!$this->jsonParsed) {
            $body = $this->body();
            
            if (empty($body)) {
                $this->json = $assoc ? [] : new \stdClass();
            } else {
                $this->json = json_decode($body, $assoc);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->json = $assoc ? [] : new \stdClass();
                }
            }
            
            $this->jsonParsed = true;
        }
        
        return $this->json;
    }

    /**
     * Check if request is JSON
     *
     * @return bool
     */
    public function isJson(): bool
    {
        $contentType = $this->header('Content-Type', '');
        return str_contains($contentType, 'application/json');
    }

    /**
     * Check if request expects JSON
     *
     * @return bool
     */
    public function expectsJson(): bool
    {
        $accept = $this->header('Accept', '');
        return str_contains($accept, 'application/json');
    }

    /**
     * Check if request is AJAX
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /**
     * Check if request is secure (HTTPS)
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        if (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }
        
        if (($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }
        
        return ($this->server['SERVER_PORT'] ?? 80) == 443;
    }

    /**
     * Check if request method matches
     *
     * @param string $method
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    /**
     * Check if request is GET
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    /**
     * Check if request is POST
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    /**
     * Check if request is PUT
     *
     * @return bool
     */
    public function isPut(): bool
    {
        return $this->isMethod('PUT');
    }

    /**
     * Check if request is DELETE
     *
     * @return bool
     */
    public function isDelete(): bool
    {
        return $this->isMethod('DELETE');
    }

    /**
     * Check if request is PATCH
     *
     * @return bool
     */
    public function isPatch(): bool
    {
        return $this->isMethod('PATCH');
    }

    /**
     * Get the client IP address
     *
     * @return string
     */
    public function ip(): string
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->server)) {
                $ip = $this->server[$key];
                
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get the user agent
     *
     * @return string
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Get route parameter value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Get all route parameters
     *
     * @return array<string, mixed>
     */
    public function routeParameters(): array
    {
        return $this->routeParams;
    }

    /**
     * Set route parameters
     *
     * @param array<string, mixed> $params
     * @return self
     */
    public function setRouteParameters(array $params): self
    {
        $this->routeParams = $params;
        $this->allInput = null; // Reset cached input
        return $this;
    }

    /**
     * Create a new request with merged input
     *
     * @param array<string, mixed> $input
     * @return self
     */
    public function merge(array $input): self
    {
        $clone = clone $this;
        $clone->allInput = array_merge($this->all(), $input);
        return $clone;
    }

    /**
     * Create a new request with replaced input
     *
     * @param array<string, mixed> $input
     * @return self
     */
    public function replace(array $input): self
    {
        $clone = clone $this;
        $clone->query = [];
        $clone->post = [];
        $clone->allInput = $this->sanitizeInput($input);
        return $clone;
    }

    /**
     * Get WordPress context information
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'is_admin' => $this->isAdmin(),
            'is_ajax' => function_exists('wp_doing_ajax') && wp_doing_ajax(),
            'is_rest' => $this->isRest(),
            'is_cron' => $this->isCron(),
            'is_cli' => $this->isCli(),
            'user_id' => function_exists('get_current_user_id') ? get_current_user_id() : 0,
        ];
    }

    /**
     * Check if in WordPress admin
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return function_exists('is_admin') && is_admin();
    }

    /**
     * Check if WordPress REST request
     *
     * @return bool
     */
    public function isRest(): bool
    {
        return defined('REST_REQUEST') && REST_REQUEST;
    }

    /**
     * Check if WordPress cron request
     *
     * @return bool
     */
    public function isCron(): bool
    {
        return function_exists('wp_doing_cron') && wp_doing_cron();
    }

    /**
     * Check if WordPress CLI request
     *
     * @return bool
     */
    public function isCli(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }

    /**
     * Capture headers from globals
     *
     * @return array<string, string>
     */
    protected static function captureHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = str_replace('_', '-', $key);
                $headers[$name] = $value;
            }
        }
        
        return $headers;
    }

    /**
     * Capture body content
     *
     * @return string
     */
    protected static function captureBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    /**
     * Get JSON input as array
     *
     * @return array<string, mixed>
     */
    protected function getJsonInput(): array
    {
        if (!$this->isJson()) {
            return [];
        }
        
        $json = $this->json(true);
        
        return is_array($json) ? $json : [];
    }

    /**
     * Sanitize input array
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function sanitizeInput(array $input): array
    {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            $key = $this->sanitizeKey($key);
            
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeInput($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize string array
     *
     * @param array<string, string> $input
     * @return array<string, string>
     */
    protected function sanitizeStringArray(array $input): array
    {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            $key = $this->sanitizeKey($key);
            $sanitized[$key] = $this->sanitizeString((string) $value);
        }
        
        return $sanitized;
    }

    /**
     * Sanitize a key
     *
     * @param string $key
     * @return string
     */
    protected function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $key);
    }

    /**
     * Sanitize a string value
     *
     * @param string $value
     * @return string
     */
    protected function sanitizeString(string $value): string
    {
        // Remove null bytes
        $value = str_replace(chr(0), '', $value);
        
        // Normalize line breaks
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        
        // Trim whitespace
        return trim($value);
    }

    /**
     * Normalize headers array
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        
        foreach ($headers as $key => $value) {
            $key = $this->normalizeHeaderKey($key);
            $normalized[$key] = (string) $value;
        }
        
        return $normalized;
    }

    /**
     * Normalize header key
     *
     * @param string $key
     * @return string
     */
    protected function normalizeHeaderKey(string $key): string
    {
        return str_replace('_', '-', ucwords(strtolower(str_replace('-', '_', $key)), '_'));
    }

    /**
     * Get value from array with dot notation
     *
     * @param array<string, mixed> $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getFromArray(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        
        if (!str_contains($key, '.')) {
            return $default;
        }
        
        // Support dot notation
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            
            $array = $array[$segment];
        }
        
        return $array;
    }

    /**
     * Validate the request data
     *
     * @param array<string, string|array<int, string>> $rules Validation rules
     * @param array<string, string>|null $messages Custom error messages
     * @param array<string, string>|null $attributes Custom attribute names
     * @return array<string, mixed> Validated data
     * @throws ValidationException
     */
    public function validate(
        array $rules,
        ?array $messages = null,
        ?array $attributes = null
    ): array {
        // Get validator instance from container
        $validator = foundation()->get(ValidatorInterface::class);
        
        // Get all input data
        $data = $this->all();
        
        // Set custom messages if provided
        if ($messages !== null) {
            $validator->setMessages($messages);
        }
        
        // Set custom attributes if provided
        if ($attributes !== null) {
            $validator->setAttributes($attributes);
        }
        
        // Perform validation
        $isValid = $validator->validate($data, $rules);
        
        if (!$isValid) {
            // Fire validation failed event
            if (foundation()->has('events')) {
                foundation()->get('events')->dispatch('validation.failed', [
                    'validator' => $validator,
                    'request' => $this,
                    'rules' => $rules,
                    'errors' => $validator->errors(),
                ]);
            }
            
            // Log validation failure
            if (foundation()->has('logger')) {
                foundation()->get('logger')->channel('validation')->notice('Validation failed', [
                    'url' => $this->fullUrl(),
                    'method' => $this->method(),
                    'errors' => $validator->errors(),
                    'input' => array_keys($data),
                ]);
            }
            
            // Throw validation exception
            throw ValidationException::withValidator($validator);
        }
        
        // Return validated data
        return $validator->validated();
    }
}