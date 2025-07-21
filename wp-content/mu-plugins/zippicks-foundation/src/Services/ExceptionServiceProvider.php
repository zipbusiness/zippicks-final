<?php
/**
 * Exception Service Provider
 *
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Contracts\Exceptions\HandlerInterface;
use ZipPicks\Foundation\Exceptions\Handler;

/**
 * Provides exception handling services
 *
 * @since 1.0.0
 */
class ExceptionServiceProvider extends ServiceProvider
{
    /**
     * Services provided by this provider
     *
     * @var array<int, string>
     */
    public array $provides = [
        HandlerInterface::class,
        'exception.handler',
    ];

    /**
     * Register exception handling services
     *
     * @return void
     */
    public function register(): void
    {
        // Register the exception handler as a singleton
        $this->singleton(HandlerInterface::class, function ($app) {
            return new Handler($app);
        });

        // Register alias
        $this->alias(HandlerInterface::class, 'exception.handler');
    }

    /**
     * Bootstrap exception handling services
     *
     * @return void
     */
    public function boot(): void
    {
        $handler = $this->get(HandlerInterface::class);

        // Register the exception handler
        $handler->register();

        // Register WordPress-specific error handling
        $this->registerWordPressHooks($handler);

        // Dispatch event that exception handler is ready
        if ($this->has('events')) {
            $this->get('events')->dispatch('exception.handler.registered', $handler);
        }
    }

    /**
     * Register WordPress-specific error hooks
     *
     * @param HandlerInterface $handler
     * @return void
     */
    protected function registerWordPressHooks(HandlerInterface $handler): void
    {
        // Handle WordPress AJAX errors
        add_action('wp_ajax_nopriv_zippicks_error', function () use ($handler) {
            $this->handleAjaxError($handler);
        });

        add_action('wp_ajax_zippicks_error', function () use ($handler) {
            $this->handleAjaxError($handler);
        });

        // Handle REST API errors
        add_filter('rest_request_after_callbacks', function ($response, $handler_obj, $request) use ($handler) {
            if (is_wp_error($response)) {
                return $this->handleRestError($response, $handler);
            }
            return $response;
        }, 10, 3);

        // Handle fatal errors in admin
        if (is_admin()) {
            add_action('admin_init', function () use ($handler) {
                // Additional admin-specific error handling can be added here
            });
        }

        // Log WordPress database errors
        add_action('wp_db_error', function ($error) use ($handler) {
            $exception = new \RuntimeException('WordPress Database Error: ' . $error);
            $handler->report($exception);
        });
    }

    /**
     * Handle AJAX errors
     *
     * @param HandlerInterface $handler
     * @return void
     */
    protected function handleAjaxError(HandlerInterface $handler): void
    {
        try {
            $error = $_POST['error'] ?? 'Unknown AJAX error';
            $exception = new \RuntimeException($error);
            
            $handler->report($exception);
            $response = $handler->render($exception);
            
            if ($response !== null) {
                $response->send();
            }
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => 'An error occurred while processing your request.'
            ], 500);
        }
        
        wp_die();
    }

    /**
     * Handle REST API errors
     *
     * @param \WP_Error $error
     * @param HandlerInterface $handler
     * @return \WP_REST_Response
     */
    protected function handleRestError(\WP_Error $error, HandlerInterface $handler): \WP_REST_Response
    {
        $exception = new \RuntimeException(
            $error->get_error_message(),
            $error->get_error_code() ? (int) $error->get_error_code() : 500
        );

        try {
            $handler->report($exception);
            $response = $handler->render($exception);
            
            if ($response !== null) {
                return new \WP_REST_Response(
                    json_decode($response->getContent(), true),
                    $response->getStatus()
                );
            }
        } catch (\Throwable $e) {
            // Fallback to basic error response
        }

        return new \WP_REST_Response([
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'data' => $error->get_error_data()
        ], 500);
    }
}