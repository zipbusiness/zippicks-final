<?php

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\RateLimiting\RateLimiterManager;
use ZipPicks\Foundation\RateLimiting\Middleware\ThrottleRequests;
use ZipPicks\Foundation\RateLimiting\Middleware\ThrottleJobs;
use ZipPicks\Foundation\Core\CircuitBreaker;

/**
 * RateLimitingServiceProvider - Registers rate limiting services
 * 
 * Sets up the rate limiting infrastructure that protects our $100B platform
 * and enables tier-based monetization through intelligent access control.
 */
class RateLimitingServiceProvider extends ServiceProvider
{
    /**
     * Register services
     * 
     * @return void
     */
    public function register(): void
    {
        $this->registerRateLimiterManager();
        $this->registerMiddleware();
        $this->registerDatabaseMigration();
    }

    /**
     * Bootstrap services
     * 
     * @return void
     */
    public function boot(): void
    {
        $this->registerWordPressHooks();
        $this->registerApiRoutes();
        $this->registerAdminPages();
    }

    /**
     * Register the rate limiter manager
     * 
     * @return void
     */
    protected function registerRateLimiterManager(): void
    {
        $this->container->singleton(RateLimiterManager::class, function ($container) {
            $config = $container->get('config')->get('rate_limiting', []);
            
            // Merge with default configuration
            $config = array_merge($this->getDefaultConfig(), $config);
            
            return new RateLimiterManager($container, $config);
        });
        
        // Alias for convenience
        $this->container->alias('rate_limiter', RateLimiterManager::class);
    }

    /**
     * Register middleware
     * 
     * @return void
     */
    protected function registerMiddleware(): void
    {
        // HTTP throttle middleware
        $this->container->singleton(ThrottleRequests::class, function ($container) {
            return new ThrottleRequests($container->get(RateLimiterManager::class));
        });
        
        // Queue throttle middleware factory
        $this->container->bind('queue.middleware.throttle', function ($container, $params) {
            $middleware = new ThrottleJobs(
                $params['key'] ?? 'default',
                $params['maxAttempts'] ?? 60,
                $params['decayMinutes'] ?? 1,
                $params['limiterName'] ?? null,
                $params['releaseOnLimit'] ?? true,
                $params['releaseDelay'] ?? 60
            );
            
            $middleware->setManager($container->get(RateLimiterManager::class));
            
            return $middleware;
        });
    }

    /**
     * Register database migration
     * 
     * @return void
     */
    protected function registerDatabaseMigration(): void
    {
        add_action('zippicks_install', function () {
            $this->createRateLimitTables();
        });
        
        // Also check on admin init
        add_action('admin_init', function () {
            $this->maybeCreateRateLimitTables();
        });
    }

    /**
     * Register WordPress hooks
     * 
     * @return void
     */
    protected function registerWordPressHooks(): void
    {
        // Add rate limiting to REST API
        add_filter('rest_pre_dispatch', [$this, 'checkRestApiRateLimit'], 10, 3);
        
        // Add rate limiting headers to REST responses
        add_filter('rest_post_dispatch', [$this, 'addRateLimitHeaders'], 10, 3);
        
        // Clean up expired rate limits periodically
        add_action('zippicks_daily_cleanup', [$this, 'cleanupExpiredLimits']);
        
        // Track rate limit events for analytics
        add_action('zippicks_rate_limit_exceeded', [$this, 'trackRateLimitEvent'], 10, 2);
    }

    /**
     * Register API routes for rate limit management
     * 
     * @return void
     */
    protected function registerApiRoutes(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('zippicks/v1', '/rate-limits/usage', [
                'methods' => 'GET',
                'callback' => [$this, 'getRateLimitUsage'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ]);
            
            register_rest_route('zippicks/v1', '/rate-limits/reset', [
                'methods' => 'POST',
                'callback' => [$this, 'resetRateLimit'],
                'permission_callback' => fn() => current_user_can('manage_options'),
                'args' => [
                    'key' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                ],
            ]);
        });
    }

    /**
     * Register admin pages
     * 
     * @return void
     */
    protected function registerAdminPages(): void
    {
        add_action('admin_menu', function () {
            add_submenu_page(
                'zippicks-foundation',
                'Rate Limiting',
                'Rate Limiting',
                'manage_options',
                'zippicks-rate-limiting',
                [$this, 'renderAdminPage']
            );
        });
    }

    /**
     * Check REST API rate limits
     * 
     * @param mixed $result
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public function checkRestApiRateLimit($result, $server, $request)
    {
        // Skip for internal requests
        if (defined('DOING_CRON') || defined('WP_CLI')) {
            return $result;
        }
        
        try {
            $middleware = $this->container->get(ThrottleRequests::class);
            
            // Convert WP request to our request object
            $foundationRequest = $this->convertWpRequest($request);
            
            // Check rate limit
            $middleware->handle($foundationRequest, function ($req) use ($result) {
                return $result;
            }, 100, 1, 'api'); // 100 requests per minute default
            
            return $result;
        } catch (\ZipPicks\Foundation\RateLimiting\Exceptions\RateLimitExceededException $e) {
            return new \WP_Error(
                'rate_limit_exceeded',
                $e->getMessage(),
                ['status' => 429]
            );
        }
    }

    /**
     * Add rate limit headers to REST response
     * 
     * @param \WP_REST_Response $response
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function addRateLimitHeaders($response, $server, $request)
    {
        $manager = $this->container->get(RateLimiterManager::class);
        $key = $this->resolveRequestKey($request);
        
        $limiter = $manager->limiter('api');
        $usage = $limiter->usage($key);
        
        $response->header('X-RateLimit-Limit', $usage['limit'] ?? 100);
        $response->header('X-RateLimit-Remaining', $usage['remaining'] ?? 0);
        $response->header('X-RateLimit-Reset', $usage['reset_at'] ?? time());
        
        return $response;
    }

    /**
     * Get rate limit usage via API
     * 
     * @param \WP_REST_Request $request
     * @return array
     */
    public function getRateLimitUsage($request)
    {
        $manager = $this->container->get(RateLimiterManager::class);
        
        return [
            'usage' => $manager->getUsageStats(),
            'tiers' => [
                'free' => $manager->getTierConfig('free'),
                'pro' => $manager->getTierConfig('pro'),
                'business' => $manager->getTierConfig('business'),
                'enterprise' => $manager->getTierConfig('enterprise'),
            ],
        ];
    }

    /**
     * Reset rate limit via API
     * 
     * @param \WP_REST_Request $request
     * @return array
     */
    public function resetRateLimit($request)
    {
        $key = $request->get_param('key');
        $manager = $this->container->get(RateLimiterManager::class);
        
        $limiter = $manager->limiter();
        $limiter->clear($key);
        
        return ['success' => true, 'message' => 'Rate limit cleared'];
    }

    /**
     * Clean up expired limits
     * 
     * @return void
     */
    public function cleanupExpiredLimits(): void
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_rate_limits';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE expires_at <= %d LIMIT 10000",
            time()
        ));
    }

    /**
     * Track rate limit event
     * 
     * @param string $key
     * @param array $context
     * @return void
     */
    public function trackRateLimitEvent(string $key, array $context): void
    {
        // Track in analytics for upgrade opportunities
        do_action('zippicks_analytics_event', 'rate_limit_exceeded', [
            'key' => $key,
            'tier' => $context['tier'] ?? 'unknown',
            'resource' => $context['resource'] ?? 'unknown',
            'upgrade_path' => $context['upgrade_path'] ?? null,
        ]);
    }

    /**
     * Render admin page
     * 
     * @return void
     */
    public function renderAdminPage(): void
    {
        $manager = $this->container->get(RateLimiterManager::class);
        
        include __DIR__ . '/../../admin/views/rate-limiting-dashboard.php';
    }

    /**
     * Create rate limit tables
     * 
     * @return void
     */
    protected function createRateLimitTables(): void
    {
        global $wpdb;
        
        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'zippicks_rate_limits';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            `key` VARCHAR(255) NOT NULL,
            `value` INT UNSIGNED NOT NULL DEFAULT 0,
            `expires_at` INT UNSIGNED NOT NULL,
            `metadata` TEXT NULL,
            PRIMARY KEY (`key`),
            INDEX idx_expires (`expires_at`)
        ) $charsetCollate";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        update_option('zippicks_rate_limit_db_version', '1.0');
    }

    /**
     * Maybe create rate limit tables
     * 
     * @return void
     */
    protected function maybeCreateRateLimitTables(): void
    {
        $current_version = get_option('zippicks_rate_limit_db_version', '0');
        
        if (version_compare($current_version, '1.0', '<')) {
            $this->createRateLimitTables();
        }
    }

    /**
     * Get default configuration
     * 
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'default' => 'redis',
            
            'limiters' => [
                'api' => [
                    'algorithm' => 'sliding_window',
                    'store' => 'redis',
                    'window' => 60,
                ],
                'taste_graph' => [
                    'algorithm' => 'token_bucket',
                    'store' => 'redis',
                    'capacity' => 100,
                    'refill_rate' => 1.67, // 100 per minute
                ],
                'email' => [
                    'algorithm' => 'leaky_bucket',
                    'store' => 'redis',
                    'capacity' => 1000,
                    'leak_rate' => 16.67, // 1000 per minute
                ],
            ],
            
            'stores' => [
                'redis' => [
                    'driver' => 'redis',
                    'connection' => 'default',
                    'prefix' => 'rate_limit:',
                ],
                'database' => [
                    'driver' => 'database',
                    'table' => 'wp_zippicks_rate_limits',
                ],
                'memory' => [
                    'driver' => 'memory',
                ],
            ],
        ];
    }

    /**
     * Convert WP request to Foundation request
     * 
     * @param \WP_REST_Request $wpRequest
     * @return \ZipPicks\Foundation\Http\Request
     */
    protected function convertWpRequest($wpRequest)
    {
        // Simple conversion - would be more complex in production
        $request = new \ZipPicks\Foundation\Http\Request();
        
        // Copy relevant data
        $request->setMethod($wpRequest->get_method());
        $request->setUri($wpRequest->get_route());
        
        return $request;
    }

    /**
     * Resolve request key
     * 
     * @param \WP_REST_Request $request
     * @return string
     */
    protected function resolveRequestKey($request): string
    {
        $user = wp_get_current_user();
        
        if ($user && $user->ID > 0) {
            return 'user:' . $user->ID . ':api';
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return 'ip:' . $ip . ':api';
    }
}