<?php
/**
 * API Service Provider
 * 
 * Registers and bootstraps the enterprise API Gateway system
 * Handles 10M+ requests/day for the $100B Taste Layer platform
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheInterface;
use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Api\Gateway\ApiGateway;
use ZipPicks\Foundation\Api\Gateway\Router;
use ZipPicks\Foundation\Api\Gateway\VersionManager;
use ZipPicks\Foundation\Api\Gateway\ResponseTransformer;
use ZipPicks\Foundation\Api\Keys\ApiKeyManager;
use ZipPicks\Foundation\Api\Keys\ApiKeyRepository;
use ZipPicks\Foundation\Api\Documentation\OpenApiGenerator;
use ZipPicks\Foundation\Api\Documentation\SwaggerUI;
use ZipPicks\Foundation\Api\Middleware\ApiAuthentication;
use ZipPicks\Foundation\Api\Middleware\ApiRateLimiting;
use ZipPicks\Foundation\Api\Middleware\ApiVersioning;
use ZipPicks\Foundation\Api\Middleware\ApiCaching;
use ZipPicks\Foundation\Api\Middleware\ApiValidation;
use ZipPicks\Foundation\Api\Middleware\ApiCors;
use ZipPicks\Foundation\Api\Middleware\ApiCompression;
use ZipPicks\Foundation\Http\Request;

class ApiServiceProvider extends ServiceProvider
{
    /**
     * API base path
     *
     * @var string
     */
    protected string $apiBasePath = '/api';

    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerCore();
        $this->registerMiddleware();
        $this->registerDocumentation();
        $this->registerKeyManagement();
        $this->registerRouteAliases();
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        $this->bootDatabaseTables();
        $this->bootApiRoutes();
        $this->bootWordPressHooks();
        $this->bootAdminPages();
        
        // Log successful boot
        if ($this->has(LoggerInterface::class)) {
            $logger = $this->get(LoggerInterface::class);
            $logger->info('[ApiServiceProvider] API Gateway booted successfully');
        }
    }

    /**
     * Register core API services
     *
     * @return void
     */
    protected function registerCore(): void
    {
        // API Gateway (Singleton)
        $this->singleton(ApiGateway::class, function (ContainerInterface $container) {
            return new ApiGateway($container);
        });
        $this->alias('api.gateway', ApiGateway::class);

        // API Router (Singleton)
        $this->singleton(Router::class, function (ContainerInterface $container) {
            return new Router($container);
        });
        $this->alias('api.router', Router::class);

        // Version Manager (Singleton)
        $this->singleton(VersionManager::class, function (ContainerInterface $container) {
            return new VersionManager($container);
        });
        $this->alias('api.versions', VersionManager::class);

        // Response Transformer (Singleton)
        $this->singleton(ResponseTransformer::class, function (ContainerInterface $container) {
            return new ResponseTransformer($container);
        });
        $this->alias('api.transformer', ResponseTransformer::class);
    }

    /**
     * Register API middleware
     *
     * @return void
     */
    protected function registerMiddleware(): void
    {
        // Authentication middleware
        $this->bind('api.auth', function (ContainerInterface $container) {
            return new ApiAuthentication(
                $container->get('auth'),
                $container->get(ApiKeyManager::class),
                $container->get(LoggerInterface::class)
            );
        });

        // Rate limiting middleware
        $this->bind('api.rate_limit', function (ContainerInterface $container) {
            return new ApiRateLimiting(
                $container->get('rate_limiter'),
                $container->get(LoggerInterface::class)
            );
        });

        // Versioning middleware
        $this->bind('api.version', function (ContainerInterface $container) {
            return new ApiVersioning(
                $container->get(VersionManager::class),
                $container->get(LoggerInterface::class)
            );
        });

        // Caching middleware
        $this->bind('api.cache', function (ContainerInterface $container) {
            return new ApiCaching(
                $container->get(CacheInterface::class),
                $container->get(LoggerInterface::class)
            );
        });

        // Validation middleware
        $this->bind('api.validation', function (ContainerInterface $container) {
            return new ApiValidation(
                $container->get('validator'),
                $container->get(LoggerInterface::class)
            );
        });

        // CORS middleware
        $this->bind('api.cors', function (ContainerInterface $container) {
            return new ApiCors($container->get('config')->get('api.cors', []));
        });

        // Compression middleware
        $this->bind('api.compression', function (ContainerInterface $container) {
            return new ApiCompression();
        });
    }

    /**
     * Register documentation services
     *
     * @return void
     */
    protected function registerDocumentation(): void
    {
        // OpenAPI Generator
        $this->singleton(OpenApiGenerator::class, function (ContainerInterface $container) {
            return new OpenApiGenerator(
                $container->get(Router::class),
                $container->get(VersionManager::class)
            );
        });
        $this->alias('api.openapi', OpenApiGenerator::class);

        // Swagger UI
        $this->singleton(SwaggerUI::class, function (ContainerInterface $container) {
            return new SwaggerUI(
                $container->get(OpenApiGenerator::class)
            );
        });
        $this->alias('api.swagger', SwaggerUI::class);
    }

    /**
     * Register API key management
     *
     * @return void
     */
    protected function registerKeyManagement(): void
    {
        // API Key Manager
        $this->singleton(ApiKeyManager::class, function (ContainerInterface $container) {
            return new ApiKeyManager(
                $container->get(ApiKeyRepository::class),
                $container->get(LoggerInterface::class)
            );
        });
        $this->alias('api.keys', ApiKeyManager::class);

        // API Key Repository
        $this->singleton(ApiKeyRepository::class, function (ContainerInterface $container) {
            global $wpdb;
            return new ApiKeyRepository($wpdb, $container->get(CacheInterface::class));
        });
    }

    /**
     * Register route aliases
     *
     * @return void
     */
    protected function registerRouteAliases(): void
    {
        // Make API gateway accessible via app container
        if (!$this->has('api')) {
            $this->alias('api', ApiGateway::class);
        }
    }

    /**
     * Boot database tables
     *
     * @return void
     */
    protected function bootDatabaseTables(): void
    {
        add_action('init', function () {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();

            // API Keys table
            $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_api_keys (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                key_hash VARCHAR(64) NOT NULL UNIQUE,
                key_prefix VARCHAR(8) NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                tier VARCHAR(50) DEFAULT 'free',
                permissions JSON,
                rate_limits JSON,
                last_used_at TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_key_prefix (key_prefix),
                INDEX idx_user_id (user_id),
                INDEX idx_tier (tier)
            ) $charset_collate;";

            // API Key Usage table
            $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_api_key_usage (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                api_key_id BIGINT UNSIGNED NOT NULL,
                endpoint VARCHAR(255) NOT NULL,
                requests INT DEFAULT 0,
                errors INT DEFAULT 0,
                latency_sum BIGINT DEFAULT 0,
                date DATE NOT NULL,
                UNIQUE KEY idx_key_endpoint_date (api_key_id, endpoint, date),
                FOREIGN KEY (api_key_id) REFERENCES {$wpdb->prefix}zippicks_api_keys(id) ON DELETE CASCADE
            ) $charset_collate;";

            // API Webhooks table
            $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_api_webhooks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                api_key_id BIGINT UNSIGNED NOT NULL,
                url VARCHAR(500) NOT NULL,
                events JSON NOT NULL,
                secret VARCHAR(255) NOT NULL,
                active BOOLEAN DEFAULT TRUE,
                failures INT DEFAULT 0,
                last_triggered_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_api_key (api_key_id),
                INDEX idx_active (active),
                FOREIGN KEY (api_key_id) REFERENCES {$wpdb->prefix}zippicks_api_keys(id) ON DELETE CASCADE
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        });
    }

    /**
     * Boot API routes
     *
     * @return void
     */
    protected function bootApiRoutes(): void
    {
        add_action('init', function () {
            /** @var ApiGateway $api */
            $api = $this->get('api.gateway');
            
            // Register API v1 routes
            $api->version('v1', function ($router) {
                $this->registerV1Routes($router);
            });
            
            // Register API v2 routes (beta)
            $api->version('v2', function ($router) {
                $this->registerV2Routes($router);
            });
        });
    }

    /**
     * Register v1 API routes
     *
     * @param Router $router
     * @return void
     */
    protected function registerV1Routes(Router $router): void
    {
        // Health check
        $router->get('/health', function () {
            return [
                'status' => 'healthy',
                'timestamp' => time(),
                'version' => 'v1'
            ];
        });

        // Businesses
        $router->resource('businesses', 'ZipPicks\Foundation\Api\Controllers\V1\BusinessController');
        
        // Reviews
        $router->resource('reviews', 'ZipPicks\Foundation\Api\Controllers\V1\ReviewController');
        
        // Taste Graph
        $router->get('/taste-graph/profile/{user_id}', ['ZipPicks\Foundation\Api\Controllers\V1\TasteGraphController', 'profile']);
        $router->get('/taste-graph/recommendations', ['ZipPicks\Foundation\Api\Controllers\V1\TasteGraphController', 'recommendations']);
        $router->post('/taste-graph/preferences', ['ZipPicks\Foundation\Api\Controllers\V1\TasteGraphController', 'updatePreferences']);
        
        // Vibes
        $router->get('/vibes', ['ZipPicks\Foundation\Api\Controllers\V1\VibeController', 'index']);
        $router->get('/vibes/{vibe}/businesses', ['ZipPicks\Foundation\Api\Controllers\V1\VibeController', 'businesses']);
        
        // Search
        $router->get('/search', ['ZipPicks\Foundation\Api\Controllers\V1\SearchController', 'search']);
        $router->get('/search/autocomplete', ['ZipPicks\Foundation\Api\Controllers\V1\SearchController', 'autocomplete']);
    }

    /**
     * Register v2 API routes (includes v1 + new features)
     *
     * @param Router $router
     * @return void
     */
    protected function registerV2Routes(Router $router): void
    {
        // Include all v1 routes
        $this->registerV1Routes($router);
        
        // Additional v2 features
        $router->get('/analytics/insights', ['ZipPicks\Foundation\Api\Controllers\V2\AnalyticsController', 'insights']);
        $router->get('/analytics/trends', ['ZipPicks\Foundation\Api\Controllers\V2\AnalyticsController', 'trends']);
        
        // Enhanced recommendations
        $router->get('/recommendations/ai', ['ZipPicks\Foundation\Api\Controllers\V2\RecommendationController', 'aiPowered']);
        $router->post('/recommendations/feedback', ['ZipPicks\Foundation\Api\Controllers\V2\RecommendationController', 'feedback']);
    }

    /**
     * Boot WordPress hooks
     *
     * @return void
     */
    protected function bootWordPressHooks(): void
    {
        // Handle API requests
        add_action('init', function () {
            $this->handleApiRequest();
        }, 1);

        // Add rewrite rules
        add_action('init', function () {
            add_rewrite_rule('^api/(.*)/?', 'index.php?zippicks_api=1&zippicks_api_route=$matches[1]', 'top');
        });

        // Add query vars
        add_filter('query_vars', function ($vars) {
            $vars[] = 'zippicks_api';
            $vars[] = 'zippicks_api_route';
            return $vars;
        });

        // Handle template redirect
        add_action('template_redirect', function () {
            if (get_query_var('zippicks_api')) {
                $this->handleApiRequest();
                exit;
            }
        });
    }

    /**
     * Handle API request
     *
     * @return void
     */
    protected function handleApiRequest(): void
    {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check if this is an API request
        if (!str_starts_with($path, $this->apiBasePath)) {
            return;
        }

        try {
            /** @var ApiGateway $api */
            $api = $this->get('api.gateway');
            
            // Create request from globals
            $request = $this->has('request') 
                ? $this->get('request')
                : \ZipPicks\Foundation\Http\Request::createFromGlobals();
            
            // Handle request
            $response = $api->handle($request);
            
            // Send response
            $response->send();
            exit;
            
        } catch (\Exception $e) {
            if ($this->has(LoggerInterface::class)) {
                $logger = $this->get(LoggerInterface::class);
                $logger->error('[ApiServiceProvider] API request failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Send error response
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => [
                    'message' => 'Internal server error',
                    'code' => 500
                ]
            ]);
            exit;
        }
    }

    /**
     * Boot admin pages
     *
     * @return void
     */
    protected function bootAdminPages(): void
    {
        add_action('admin_menu', function () {
            // API Dashboard
            add_submenu_page(
                'zippicks-foundation',
                'API Gateway',
                'API Gateway',
                'manage_options',
                'zippicks-api',
                [$this, 'renderApiDashboard']
            );
            
            // API Documentation
            add_submenu_page(
                'zippicks-foundation',
                'API Documentation',
                'API Docs',
                'manage_options',
                'zippicks-api-docs',
                [$this, 'renderApiDocumentation']
            );
            
            // API Keys
            add_submenu_page(
                'zippicks-foundation',
                'API Keys',
                'API Keys',
                'manage_options',
                'zippicks-api-keys',
                [$this, 'renderApiKeys']
            );
        });
    }

    /**
     * Render API dashboard
     *
     * @return void
     */
    public function renderApiDashboard(): void
    {
        $viewPath = ZIPPICKS_FOUNDATION_PATH . '/admin/views/api/dashboard.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo '<div class="wrap"><h1>API Dashboard</h1><p>Dashboard view not found.</p></div>';
        }
    }

    /**
     * Render API documentation
     *
     * @return void
     */
    public function renderApiDocumentation(): void
    {
        /** @var SwaggerUI $swagger */
        $swagger = $this->get('api.swagger');
        $swagger->render();
    }

    /**
     * Render API keys management
     *
     * @return void
     */
    public function renderApiKeys(): void
    {
        $viewPath = ZIPPICKS_FOUNDATION_PATH . '/admin/views/api/keys.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo '<div class="wrap"><h1>API Keys</h1><p>Keys view not found.</p></div>';
        }
    }
}