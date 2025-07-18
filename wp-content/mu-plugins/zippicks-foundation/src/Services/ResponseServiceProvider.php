<?php
/**
 * Response Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Providers\ServiceProvider;

class ResponseServiceProvider extends ServiceProvider
{
    /**
     * Services provided by this provider
     *
     * @var array<int, string>
     */
    public array $provides = [
        ResponseInterface::class,
        'response',
    ];
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        // Register response factory as singleton
        $this->singleton('response', function (ContainerInterface $container) {
            return function (mixed $content = '', int $status = 200, array $headers = []) {
                return new Response($content, $status, $headers);
            };
        });

        // Register response interface binding
        $this->bind(ResponseInterface::class, Response::class);

        // Log successful registration
        if ($this->has(LoggerInterface::class)) {
            $logger = $this->get(LoggerInterface::class);
            $logger->info('[ResponseServiceProvider] Response system registered successfully');
        }
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        // Log response service initialization
        if ($this->has('logger')) {
            $this->get('logger')->channel('response')->info('Response service initialized', [
                'service' => Response::class,
                'cli' => PHP_SAPI === 'cli',
                'context' => $this->detectContext(),
            ]);
        }

        // Register event listeners for response logging
        $this->registerResponseLogging();

        // Register shutdown function to handle unsent responses
        register_shutdown_function(function () {
            $this->handleShutdown();
        });

        // Hook into WordPress to ensure responses are sent
        add_action('shutdown', function () {
            $this->handleWordPressShutdown();
        }, 999);
    }

    /**
     * Handle PHP shutdown
     *
     * @return void
     */
    protected function handleShutdown(): void
    {
        // Check for fatal errors
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Create error response
            $response = new Response();
            $response->error('Internal Server Error', 500, [
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
            
            if (!$response->isSent()) {
                $response->send();
            }
        }
    }

    /**
     * Handle WordPress shutdown
     *
     * @return void
     */
    protected function handleWordPressShutdown(): void
    {
        // This ensures any pending responses are sent
        // before WordPress completes its execution
    }

    /**
     * Register response event logging
     *
     * @return void
     */
    protected function registerResponseLogging(): void
    {
        if (!$this->has('events') || !$this->has('logger')) {
            return;
        }

        $events = $this->get('events');
        $logger = $this->get('logger')->channel('response');

        // Log when response is being sent
        $events->listen('response.sending', function (array $data) use ($logger) {
            $logger->debug('Response sending', [
                'status' => $data['status'] ?? 'unknown',
                'type' => $data['type'] ?? 'unknown',
                'context' => $this->detectContext(),
            ]);
        });

        // Log after response is sent
        $events->listen('response.sent', function (array $data) use ($logger) {
            $logger->info('Response sent', [
                'status' => $data['status'] ?? 'unknown',
                'type' => $data['type'] ?? 'unknown',
                'size' => $data['size'] ?? 0,
                'context' => $this->detectContext(),
                'memory_peak' => memory_get_peak_usage(true),
            ]);
        });
    }

    /**
     * Detect current execution context
     *
     * @return array<string, bool>
     */
    protected function detectContext(): array
    {
        return [
            'cli' => PHP_SAPI === 'cli',
            'ajax' => defined('DOING_AJAX') && DOING_AJAX,
            'rest' => defined('REST_REQUEST') && REST_REQUEST,
            'cron' => defined('DOING_CRON') && DOING_CRON,
            'admin' => is_admin(),
            'xml_rpc' => defined('XMLRPC_REQUEST') && XMLRPC_REQUEST,
        ];
    }
}