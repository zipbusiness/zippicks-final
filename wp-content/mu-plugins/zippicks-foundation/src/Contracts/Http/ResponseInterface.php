<?php
/**
 * Response Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Http
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Http;

interface ResponseInterface
{
    /**
     * Set the HTTP status code
     *
     * @param int $code
     * @param string $text
     * @return self
     */
    public function setStatus(int $code, string $text = ''): self;

    /**
     * Get the HTTP status code
     *
     * @return int
     */
    public function getStatus(): int;

    /**
     * Set a header value
     *
     * @param string $name
     * @param string $value
     * @param bool $replace
     * @return self
     */
    public function header(string $name, string $value, bool $replace = true): self;

    /**
     * Get all headers
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Set the response content
     *
     * @param mixed $content
     * @return self
     */
    public function setContent(mixed $content): self;

    /**
     * Get the response content
     *
     * @return mixed
     */
    public function getContent(): mixed;

    /**
     * Send the response to the browser
     *
     * @return void
     */
    public function send(): void;

    /**
     * Create a JSON response
     *
     * @param mixed $data
     * @param int $status
     * @param array<string, string> $headers
     * @return self
     */
    public function json(mixed $data, int $status = 200, array $headers = []): self;

    /**
     * Create an HTML response
     *
     * @param string $html
     * @param int $status
     * @param array<string, string> $headers
     * @return self
     */
    public function html(string $html, int $status = 200, array $headers = []): self;

    /**
     * Create a text response
     *
     * @param string $text
     * @param int $status
     * @param array<string, string> $headers
     * @return self
     */
    public function text(string $text, int $status = 200, array $headers = []): self;

    /**
     * Create a redirect response
     *
     * @param string $url
     * @param int $status
     * @param array<string, string> $headers
     * @return self
     */
    public function redirect(string $url, int $status = 302, array $headers = []): self;

    /**
     * Create a download response
     *
     * @param string $content
     * @param string $filename
     * @param array<string, string> $headers
     * @return self
     */
    public function download(string $content, string $filename, array $headers = []): self;

    /**
     * Create an error response
     *
     * @param string $message
     * @param int $status
     * @param array<string, mixed> $errors
     * @return self
     */
    public function error(string $message, int $status = 400, array $errors = []): self;

    /**
     * Create an empty response
     *
     * @param int $status
     * @return self
     */
    public function noContent(int $status = 204): self;

    /**
     * Check if response has been sent
     *
     * @return bool
     */
    public function isSent(): bool;

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
    ): self;

    /**
     * Create a view response
     *
     * @param string $view View file path or name
     * @param array<string, mixed> $data Data to pass to view
     * @param int $status HTTP status code
     * @param array<string, string> $headers Additional headers
     * @return self
     */
    public function view(string $view, array $data = [], int $status = 200, array $headers = []): self;

    /**
     * Set status (alias for setStatus)
     *
     * @param int $code
     * @param string $text
     * @return self
     */
    public function status(int $code, string $text = ''): self;
}