<?php
/**
 * Router Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Contracts\Routing\RouterInterface;
use ZipPicks\Foundation\Middleware\MiddlewarePipeline;
use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Routing\Router;

class RouterServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        // Register the router as a singleton
        $this->singleton(RouterInterface::class, function (ContainerInterface $container) {
            $pipeline = null;
            $logger = null;

            // Get middleware pipeline if available
            if ($container->has(MiddlewarePipeline::class)) {
                $pipeline = $container->get(MiddlewarePipeline::class);
            }

            // Get logger if available
            if ($container->has(LoggerInterface::class)) {
                $logger = $container->get(LoggerInterface::class);
                $logger->debug('[RouterServiceProvider] Registering router with middleware and logger');
            }

            return new Router($container, $pipeline, $logger);
        });

        // Create alias for easier access
        if (!$this->has('router')) {
            $this->alias('router', RouterInterface::class);
        }

        // Log successful registration
        if ($this->has(LoggerInterface::class)) {
            $logger = $this->get(LoggerInterface::class);
            $logger->info('[RouterServiceProvider] Router registered successfully');
        }
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        // Get the router instance
        /** @var RouterInterface $router */
        $router = $this->get('router');

        // Load routes if file exists
        $routesFile = ZIPPICKS_FOUNDATION_PATH . '/src/Routing/routes.php';
        if (file_exists($routesFile)) {
            require_once $routesFile;
        }

        // Hook into WordPress to handle routing
        $this->registerWordPressHooks($router);

        // Register example routes for demonstration
        $this->registerExampleRoutes($router);
    }

    /**
     * Register WordPress hooks for routing
     *
     * @param RouterInterface $router
     * @return void
     */
    protected function registerWordPressHooks(RouterInterface $router): void
    {
        // Frontend routing
        add_action('init', function () use ($router) {
            $this->handleWordPressRequest($router);
        }, 999);

        // REST API routing enhancement
        add_action('rest_api_init', function () use ($router) {
            $this->handleRestRequest($router);
        }, 999);

        // Admin routing
        add_action('admin_init', function () use ($router) {
            $this->handleAdminRequest($router);
        }, 999);

        // AJAX routing
        add_action('wp_ajax_zippicks_route', function () use ($router) {
            $this->handleAjaxRequest($router);
        });
        add_action('wp_ajax_nopriv_zippicks_route', function () use ($router) {
            $this->handleAjaxRequest($router);
        });
    }

    /**
     * Handle WordPress frontend requests
     *
     * @param RouterInterface $router
     * @return void
     */
    protected function handleWordPressRequest(RouterInterface $router): void
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        $this->dispatchRoute($router, $method, $path);
    }

    /**
     * Handle REST API requests
     *
     * @param RouterInterface $router
     * @return void
     */
    protected function handleRestRequest(RouterInterface $router): void
    {
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        $this->dispatchRoute($router, $method, $path);
    }

    /**
     * Handle admin requests
     *
     * @param RouterInterface $router
     * @return void
     */
    protected function handleAdminRequest(RouterInterface $router): void
    {
        if (!is_admin()) {
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        $this->dispatchRoute($router, $method, $path);
    }

    /**
     * Handle AJAX requests
     *
     * @param RouterInterface $router
     * @return void
     */
    protected function handleAjaxRequest(RouterInterface $router): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
        $path = $_POST['route'] ?? $_GET['route'] ?? '/';

        $this->dispatchRoute($router, $method, $path);
        wp_die();
    }

    /**
     * Dispatch a route
     *
     * @param RouterInterface $router
     * @param string $method
     * @param string $path
     * @return void
     */
    protected function dispatchRoute(RouterInterface $router, string $method, string $path): void
    {
        $route = $router->matchRoute($method, $path);
        
        if ($route === null) {
            return;
        }

        try {
            // Create WordPress request
            $request = $this->has('request') 
                ? $this->get('request')
                : new \ZipPicks\Foundation\Middleware\WordPressRequest();

            $response = $router->dispatch($route, $request);
            
            // Handle response
            $this->handleResponse($response);
        } catch (\Exception $e) {
            if ($this->has(LoggerInterface::class)) {
                $logger = $this->get(LoggerInterface::class);
                $logger->error('[RouterServiceProvider] Route dispatch failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Let WordPress handle the error
            if (!is_admin() && !wp_doing_ajax()) {
                wp_die($e->getMessage(), 'Route Error', ['response' => 500]);
            }
        }
    }

    /**
     * Handle route response
     *
     * @param ResponseInterface $response
     * @return void
     */
    protected function handleResponse(ResponseInterface $response): void
    {
        // Send the response
        if (!$response->isSent()) {
            $response->send();
        }
    }

    /**
     * Register example routes for demonstration
     *
     * @param RouterInterface $router
     * @return void
     */
    protected function registerExampleRoutes(RouterInterface $router): void
    {
        // Skip in production
        if (defined('WP_ENV') && WP_ENV === 'production') {
            return;
        }

        // Example: Basic GET route
        $router->get('/zippicks/test', function ($request) {
            return ['message' => 'Hello from ZipPicks Router!', 'method' => 'GET'];
        })->name('test.index');

        // Example: Route with middleware
        $router->get('/zippicks/protected', function ($request) {
            return ['message' => 'This route has middleware', 'user' => wp_get_current_user()->display_name];
        })->middleware(['auth'])->name('test.protected');

        // Example: Route group
        $router->group(['prefix' => '/zippicks/api', 'middleware' => ['api']], function ($router) {
            $router->get('/users', function ($request) {
                return ['users' => ['John', 'Jane', 'Bob']];
            })->name('api.users');

            $router->post('/users', function ($request) {
                return ['message' => 'User created', 'data' => $_POST];
            })->name('api.users.create');
        });
    }
}