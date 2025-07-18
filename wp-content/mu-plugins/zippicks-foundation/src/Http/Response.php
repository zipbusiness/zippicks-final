<?php
/**
 * Response Implementation
 * 
 * @package ZipPicks\Foundation\Http
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Http;

use ZipPicks\Foundation\Contracts\Http\ResponseInterface;

class Response implements ResponseInterface
{
    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode = 200;

    /**
     * HTTP status text
     *
     * @var string
     */
    protected string $statusText = '';

    /**
     * Response headers
     *
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * Response content
     *
     * @var mixed
     */
    protected mixed $content = '';

    /**
     * Cookies to send
     *
     * @var array<array<string, mixed>>
     */
    protected array $cookies = [];

    /**
     * Whether response has been sent
     *
     * @var bool
     */
    protected bool $sent = false;

    /**
     * HTTP status texts
     *
     * @var array<int, string>
     */
    protected static array $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    /**
     * Create a new response instance
     *
     * @param mixed $content
     * @param int $status
     * @param array<string, string> $headers
     */
    public function __construct(mixed $content = '', int $status = 200, array $headers = [])
    {
        $this->setContent($content);
        $this->setStatus($status);
        
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
    }

    /**
     * Set the HTTP status code
     *
     * @param int $code
     * @param string $text
     * @return self
     */
    public function setStatus(int $code, string $text = ''): self
    {
        $this->statusCode = $code;
        $this->statusText = $text ?: (self::$statusTexts[$code] ?? '');
        return $this;
    }

    /**
     * Get the HTTP status code
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->statusCode;
    }

    /**
     * Set a header value
     *
     * @param string $name
     * @param string $value
     * @param bool $replace
     * @return self
     */
    public function header(string $name, string $value, bool $replace = true): self
    {
        $normalizedName = $this->normalizeHeaderName($name);
        
        if ($replace || !isset($this->headers[$normalizedName])) {
            $this->headers[$normalizedName] = $value;
        }
        
        return $this;
    }

    /**
     * Get all headers
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set the response content
     *
     * @param mixed $content
     * @return self
     */
    public function setContent(mixed $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get the response content
     *
     * @return mixed
     */
    public function getContent(): mixed
    {
        return $this->content;
    }

    /**
     * Send the response to the browser
     *
     * @return void
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        $this->sendHeaders();
        $this->sendCookies();
        $this->sendContent();
        
        $this->sent = true;
        
        // Flush output buffers
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    /**
     * Create a JSON response
     *
     * @param mixed $data
     * @param int $status
     * @param array<string, string> $headers
     * @return self
     */
    public function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            $json = json_encode(['error' => 'JSON encoding failed']);
        }
        
        $this->setContent($json);
        $this->setStatus($status);
        $this->header('Content-Type', 'application/json');
        
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
        
        return $this;
    }

    /**
     * Create an HTML response
     *
     * @param string $html
     * @param int $status
     * @param array<string, string> $headers
     * @return self
     */
    public function html(string $html, int $status = 200, array $headers = []): self
    {
        $this->setContent($html);
        $this->setStatus($status);
        $this->header('Content-Type', 'text/html; charset=utf-8');
        
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
        
        return $this;
    }

    /**
     * Create a text response
     *
     * @param string $text
     * @param int $status
     * @param array<string, string> $headers
     * @return self
     */
    public function text(string $text, int $status = 200, array $headers = []): self
    {
        $this->setContent($text);
        $this->setStatus($status);
        $this->header('Content-Type', 'text/plain; charset=utf-8');
        
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
        
        return $this;
    }

    /**
     * Create a redirect response
     *
     * @param string $url
     * @param int $status
     * @param array<string, string> $headers
     * @return self
     */
    public function redirect(string $url, int $status = 302, array $headers = []): self
    {
        $this->setContent('');
        $this->setStatus($status);
        $this->header('Location', $url);
        
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
        
        return $this;
    }

    /**
     * Create a download response
     *
     * @param string $content
     * @param string $filename
     * @param array<string, string> $headers
     * @return self
     */
    public function download(string $content, string $filename, array $headers = []): self
    {
        $this->setContent($content);
        $this->setStatus(200);
        $this->header('Content-Type', 'application/octet-stream');
        $this->header('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        $this->header('Content-Length', (string) strlen($content));
        
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
        
        return $this;
    }

    /**
     * Create an error response
     *
     * @param string $message
     * @param int $status
     * @param array<string, mixed> $errors
     * @return self
     */
    public function error(string $message, int $status = 400, array $errors = []): self
    {
        $data = [
            'success' => false,
            'message' => $message,
        ];
        
        if (!empty($errors)) {
            $data['errors'] = $errors;
        }
        
        return $this->json($data, $status);
    }

    /**
     * Create an empty response
     *
     * @param int $status
     * @return self
     */
    public function noContent(int $status = 204): self
    {
        $this->setContent('');
        $this->setStatus($status);
        return $this;
    }

    /**
     * Check if response has been sent
     *
     * @return bool
     */
    public function isSent(): bool
    {
        return $this->sent;
    }

    /**
     * Set a cookie
     *
     * @param string $name
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @return self
     */
    public function cookie(
        string $name,
        string $value = '',
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = true
    ): self {
        $this->cookies[] = [
            'name' => $name,
            'value' => $value,
            'expire' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
        ];
        
        return $this;
    }


    /**
     * Normalize header name
     *
     * @param string $name
     * @return string
     */
    protected function normalizeHeaderName(string $name): string
    {
        return str_replace('_', '-', ucwords(strtolower(str_replace('-', '_', $name)), '_'));
    }

    /**
     * Create a new response instance
     *
     * @param mixed $content
     * @param int $status
     * @param array<string, string> $headers
     * @return static
     */
    public static function make(mixed $content = '', int $status = 200, array $headers = []): static
    {
        return new static($content, $status, $headers);
    }

    /**
     * Create a success JSON response
     *
     * @param mixed $data
     * @param string $message
     * @param int $status
     * @return static
     */
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): static
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return static::make()->json($response, $status);
    }

    /**
     * Create a view response
     *
     * @param string $view View file path or name
     * @param array<string, mixed> $data Data to pass to view
     * @param int $status HTTP status code
     * @param array<string, string> $headers Additional headers
     * @return self
     */
    public function view(string $view, array $data = [], int $status = 200, array $headers = []): self
    {
        $content = $this->renderView($view, $data);
        return $this->html($content, $status, $headers);
    }

    /**
     * Set status (alias for setStatus)
     *
     * @param int $code
     * @param string $text
     * @return self
     */
    public function status(int $code, string $text = ''): self
    {
        return $this->setStatus($code, $text);
    }

    /**
     * Render a view file
     *
     * @param string $view View file path or name
     * @param array<string, mixed> $data Data to pass to view
     * @return string
     * @throws \RuntimeException If view file not found
     */
    protected function renderView(string $view, array $data = []): string
    {
        // Extract data to variables
        extract($data, EXTR_SKIP);

        // Start output buffering
        ob_start();

        // Try different view paths
        $viewPaths = $this->getViewPaths($view);
        $viewFound = false;

        foreach ($viewPaths as $viewPath) {
            if (file_exists($viewPath)) {
                $viewFound = true;
                include $viewPath;
                break;
            }
        }

        if (!$viewFound) {
            ob_end_clean();
            throw new \RuntimeException("View file not found: {$view}");
        }

        // Get contents and clean buffer
        $content = ob_get_clean();

        return $content ?: '';
    }

    /**
     * Get possible view file paths
     *
     * @param string $view
     * @return array<int, string>
     */
    protected function getViewPaths(string $view): array
    {
        // Remove .php extension if provided
        $view = preg_replace('/\.php$/i', '', $view);
        
        $paths = [];

        // Check if absolute path
        if (str_starts_with($view, '/')) {
            $paths[] = $view . '.php';
            return $paths;
        }

        // Get theme directory paths
        $themeDir = get_template_directory();
        $childThemeDir = get_stylesheet_directory();
        
        // Plugin views directory
        $pluginViewsDir = dirname(__DIR__, 2) . '/views';
        
        // Build possible paths
        $viewFile = str_replace('.', '/', $view) . '.php';
        
        // Check child theme first
        if ($childThemeDir !== $themeDir) {
            $paths[] = $childThemeDir . '/zippicks/views/' . $viewFile;
            $paths[] = $childThemeDir . '/views/' . $viewFile;
        }
        
        // Parent theme
        $paths[] = $themeDir . '/zippicks/views/' . $viewFile;
        $paths[] = $themeDir . '/views/' . $viewFile;
        
        // Plugin views
        $paths[] = $pluginViewsDir . '/' . $viewFile;
        
        // WordPress includes directory
        $paths[] = ABSPATH . 'wp-content/views/' . $viewFile;

        return $paths;
    }

    /**
     * Detect if running in CLI
     *
     * @return bool
     */
    protected function isCli(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }

    /**
     * Send headers with CLI check
     *
     * @return void
     */
    protected function sendHeaders(): void
    {
        if ($this->isCli() || headers_sent()) {
            return;
        }

        // Send status header
        if ($this->statusText) {
            header(sprintf('HTTP/1.1 %d %s', $this->statusCode, $this->statusText), true, $this->statusCode);
        } else {
            http_response_code($this->statusCode);
        }

        // Send other headers
        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value), true);
        }
    }

    /**
     * Send cookies with CLI check
     *
     * @return void
     */
    protected function sendCookies(): void
    {
        if ($this->isCli()) {
            return;
        }

        foreach ($this->cookies as $cookie) {
            setcookie(
                $cookie['name'],
                $cookie['value'],
                $cookie['expire'],
                $cookie['path'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['httponly']
            );
        }
    }

    /**
     * Send content with context awareness
     *
     * @return void
     */
    protected function sendContent(): void
    {
        // Fire event before sending
        if (foundation()->has('events')) {
            foundation()->get('events')->dispatch('response.sending', [
                'response' => $this,
                'status' => $this->statusCode,
                'type' => $this->getContentType(),
            ]);
        }

        echo $this->content;

        // Fire event after sending
        if (foundation()->has('events')) {
            foundation()->get('events')->dispatch('response.sent', [
                'response' => $this,
                'status' => $this->statusCode,
                'type' => $this->getContentType(),
                'size' => strlen((string) $this->content),
            ]);
        }
    }

    /**
     * Get content type from headers
     *
     * @return string
     */
    protected function getContentType(): string
    {
        foreach ($this->headers as $name => $value) {
            if (strtolower($name) === 'content-type') {
                return explode(';', $value)[0];
            }
        }
        
        return 'text/html';
    }
}