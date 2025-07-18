<?php
/**
 * Request Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Http\RequestInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Providers\ServiceProvider;

class RequestServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        // Register request as singleton to capture once per request
        $this->singleton(RequestInterface::class, function (ContainerInterface $container) {
            $request = Request::capture();
            
            // Log request details if logger available
            if ($container->has(LoggerInterface::class)) {
                $logger = $container->get(LoggerInterface::class);
                $logger->debug('[RequestServiceProvider] Request captured', [
                    'method' => $request->method(),
                    'uri' => $request->uri(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
            }
            
            return $request;
        });

        // Create alias for easier access
        if (!$this->has('request')) {
            $this->alias('request', RequestInterface::class);
        }

        // Log successful registration
        if ($this->has(LoggerInterface::class)) {
            $logger = $this->get(LoggerInterface::class);
            $logger->info('[RequestServiceProvider] Request system registered successfully');
        }
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        // Hook into WordPress to enhance request with route parameters
        add_action('zippicks_route_matched', function ($route, $parameters) {
            if ($this->has('request')) {
                /** @var Request $request */
                $request = $this->get('request');
                $request->setRouteParameters($parameters);
            }
        }, 10, 2);
    }
}