<?php
/**
 * Exception Handler
 *
 * @package ZipPicks\Foundation\Exceptions
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Exceptions;

use Throwable;
use ZipPicks\Foundation\Contracts\Exceptions\HandlerInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Contracts\Http\RequestInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Http\Request;

/**
 * Central exception handler for the foundation
 *
 * @since 1.0.0
 */
class Handler implements HandlerInterface
{
    /**
     * Logger instance
     *
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Event dispatcher instance
     *
     * @var EventDispatcherInterface|null
     */
    protected ?EventDispatcherInterface $events = null;

    /**
     * Container instance
     *
     * @var ContainerInterface|null
     */
    protected ?ContainerInterface $container = null;

    /**
     * Exception types that should not be reported
     *
     * @var array<int, class-string<Throwable>>
     */
    protected array $dontReport = [];

    /**
     * Exception types that should not be flashed to session
     *
     * @var array<int, class-string<Throwable>>
     */
    protected array $internalDontReport = [
        \Psr\Log\InvalidArgumentException::class,
    ];

    /**
     * Constructor
     *
     * @param ContainerInterface|null $container
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;

        if ($container !== null) {
            if ($container->has(LoggerInterface::class)) {
                $this->logger = $container->get(LoggerInterface::class);
            }

            if ($container->has(EventDispatcherInterface::class)) {
                $this->events = $container->get(EventDispatcherInterface::class);
            }
        }
    }

    /**
     * Report or log an exception
     *
     * @param Throwable $e
     * @return void
     * @throws Throwable
     */
    public function report(Throwable $e): void
    {
        if (!$this->shouldReport($e)) {
            return;
        }

        // Dispatch exception.reporting event
        if ($this->events !== null && $this->events->dispatch('exception.reporting', $e) === false) {
            return;
        }

        // Try custom report method on exception
        if (method_exists($e, 'report')) {
            if ($e->report() === false) {
                return;
            }
        }

        // Build context
        $context = $this->buildExceptionContext($e);

        // Log the exception
        if ($this->logger !== null) {
            $this->logger->channel('exceptions')->error(
                $e->getMessage(),
                array_merge($context, ['exception' => $e])
            );
        }

        // Dispatch exception.reported event
        if ($this->events !== null) {
            $this->events->dispatch('exception.reported', [
                'exception' => $e,
                'context' => $context
            ]);
        }
    }

    /**
     * Render an exception into an HTTP response
     *
     * @param Throwable $e
     * @return ResponseInterface|null
     * @throws Throwable
     */
    public function render(Throwable $e): ?ResponseInterface
    {
        // Get current request
        $request = $this->getRequest();

        // Dispatch exception.rendering event
        if ($this->events !== null) {
            $response = $this->events->dispatch('exception.rendering', [
                'exception' => $e,
                'request' => $request
            ]);

            if ($response instanceof ResponseInterface) {
                return $response;
            }
        }

        // Check if exception is renderable
        if ($e instanceof RenderableExceptionInterface) {
            $response = $e->render($request);
            if ($response !== null) {
                return $response;
            }
        }

        // Prepare response based on request type
        $response = new Response();

        if ($request->expectsJson() || $request->isAjax()) {
            return $this->renderJsonResponse($e, $response);
        }

        return $this->renderHtmlResponse($e, $response);
    }

    /**
     * Determine if the exception should be reported
     *
     * @param Throwable $e
     * @return bool
     */
    public function shouldReport(Throwable $e): bool
    {
        $dontReport = array_merge($this->dontReport, $this->internalDontReport);

        foreach ($dontReport as $type) {
            if ($e instanceof $type) {
                return false;
            }
        }

        return true;
    }

    /**
     * Register the exception handler
     *
     * @return void
     */
    public function register(): void
    {
        set_exception_handler([$this, 'handle']);
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle an uncaught exception
     *
     * @param Throwable $e
     * @return void
     */
    public function handle(Throwable $e): void
    {
        try {
            $this->report($e);
        } catch (Throwable $reportException) {
            // If we can't report, log to error_log as fallback
            error_log('Exception handler failed to report: ' . $reportException->getMessage());
            error_log('Original exception: ' . $e->getMessage());
        }

        try {
            $response = $this->render($e);
            if ($response !== null && !$response->isSent()) {
                $response->send();
            }
        } catch (Throwable $renderException) {
            // If we can't render, output basic error
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain');
            }
            echo 'An error occurred while processing your request.';
        }
    }

    /**
     * Convert PHP errors to exceptions
     *
     * @param int $level
     * @param string $message
     * @param string $file
     * @param int $line
     * @return bool
     * @throws \ErrorException
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }

        return false;
    }

    /**
     * Handle PHP shutdown errors
     *
     * @return void
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && $this->isFatal($error['type'])) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );

            $this->handle($exception);
        }
    }

    /**
     * Build exception context for logging
     *
     * @param Throwable $e
     * @return array<string, mixed>
     */
    protected function buildExceptionContext(Throwable $e): array
    {
        $request = $this->getRequest();

        $context = [
            'exception_class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
        ];

        // Add request context
        if ($request !== null) {
            $context['url'] = $request->fullUrl();
            $context['method'] = $request->method();
            $context['ip'] = $request->ip();
            $context['user_agent'] = $request->userAgent();

            // Add WordPress context
            $wpContext = $request->context();
            if (!empty($wpContext)) {
                $context['wp_context'] = $wpContext;
            }
        }

        // Add exception metadata if available
        if ($e instanceof FoundationException) {
            $metadata = $e->getContext();
            if (!empty($metadata)) {
                $context['metadata'] = $metadata;
            }
        }

        return $context;
    }

    /**
     * Render JSON error response
     *
     * @param Throwable $e
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function renderJsonResponse(Throwable $e, ResponseInterface $response): ResponseInterface
    {
        $status = $this->getStatusCode($e);
        $errors = [];

        if ($this->shouldShowDetails()) {
            $errors = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }

        return $response->error($e->getMessage(), $status, $errors);
    }

    /**
     * Render HTML error response
     *
     * @param Throwable $e
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function renderHtmlResponse(Throwable $e, ResponseInterface $response): ResponseInterface
    {
        $status = $this->getStatusCode($e);
        $message = $e->getMessage();

        if (!$this->shouldShowDetails()) {
            $message = 'An error occurred while processing your request.';
        }

        $html = $this->renderErrorPage($status, $message, $e);

        return $response->html($html, $status);
    }

    /**
     * Render error page HTML
     *
     * @param int $status
     * @param string $message
     * @param Throwable $e
     * @return string
     */
    protected function renderErrorPage(int $status, string $message, Throwable $e): string
    {
        $title = "Error {$status}";
        $details = '';

        if ($this->shouldShowDetails()) {
            $class = get_class($e);
            $file = $e->getFile();
            $line = $e->getLine();
            $trace = htmlspecialchars($e->getTraceAsString());

            $details = <<<HTML
<div style="margin-top: 2rem; padding: 1rem; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;">
    <h3>Exception Details:</h3>
    <p><strong>Type:</strong> {$class}</p>
    <p><strong>File:</strong> {$file}</p>
    <p><strong>Line:</strong> {$line}</p>
    <details style="margin-top: 1rem;">
        <summary>Stack Trace</summary>
        <pre style="overflow: auto; padding: 1rem; background: #fff; border: 1px solid #ddd;">{$trace}</pre>
    </details>
</div>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 2rem;
            background: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            margin: 0 0 1rem 0;
            color: #e74c3c;
        }
        p {
            margin: 0.5rem 0;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{$title}</h1>
        <p>{$message}</p>
        {$details}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get HTTP status code for exception
     *
     * @param Throwable $e
     * @return int
     */
    protected function getStatusCode(Throwable $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        if ($e instanceof \InvalidArgumentException) {
            return 400;
        }

        if ($e instanceof \RuntimeException) {
            return 500;
        }

        return 500;
    }

    /**
     * Get current request instance
     *
     * @return RequestInterface
     */
    protected function getRequest(): RequestInterface
    {
        if ($this->container !== null && $this->container->has(RequestInterface::class)) {
            return $this->container->get(RequestInterface::class);
        }

        return Request::capture();
    }

    /**
     * Determine if error details should be shown
     *
     * @return bool
     */
    protected function shouldShowDetails(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Check if error type is fatal
     *
     * @param int $type
     * @return bool
     */
    protected function isFatal(int $type): bool
    {
        return in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]);
    }

    /**
     * Add exception type to don't report list
     *
     * @param string|array<int, class-string<Throwable>> $exceptions
     * @return void
     */
    public function dontReport(string|array $exceptions): void
    {
        $exceptions = is_array($exceptions) ? $exceptions : [$exceptions];

        $this->dontReport = array_merge($this->dontReport, $exceptions);
    }
}