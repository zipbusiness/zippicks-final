<?php
/**
 * Middleware Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Middleware\MiddlewarePipeline;
use ZipPicks\Foundation\Providers\ServiceProvider;

class MiddlewareServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        // Register the middleware pipeline as a singleton
        $this->singleton(MiddlewarePipeline::class, function (ContainerInterface $container) {
            $logger = null;
            
            if ($container->has(LoggerInterface::class)) {
                $logger = $container->get(LoggerInterface::class);
                $logger->debug('[MiddlewareServiceProvider] Registering middleware pipeline with logger');
            }
            
            return new MiddlewarePipeline($container, $logger);
        });
        
        // Create alias for easier access
        if (!$this->has('middleware')) {
            $this->alias('middleware', MiddlewarePipeline::class);
        }
        
        // Register default middleware groups
        $this->registerDefaultGroups();
        
        // Log successful registration
        if ($this->has(LoggerInterface::class)) {
            $logger = $this->get(LoggerInterface::class);
            $logger->info('[MiddlewareServiceProvider] Middleware system registered successfully');
        }
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        // Register WordPress-specific middleware if applicable
        if (is_admin()) {
            $this->registerAdminMiddleware();
        }
        
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $this->registerRestMiddleware();
        }
        
        if (wp_doing_ajax()) {
            $this->registerAjaxMiddleware();
        }
    }

    /**
     * Register default middleware groups
     *
     * @return void
     */
    protected function registerDefaultGroups(): void
    {
        /** @var MiddlewarePipeline $pipeline */
        $pipeline = $this->get('middleware');
        
        // Web middleware group (typical frontend requests)
        $pipeline->group('web', [
            // Add web-specific middleware here
        ]);
        
        // API middleware group
        $pipeline->group('api', [
            // Add API-specific middleware here
        ]);
        
        // Admin middleware group
        $pipeline->group('admin', [
            // Add admin-specific middleware here
        ]);
    }

    /**
     * Register admin-specific middleware
     *
     * @return void
     */
    protected function registerAdminMiddleware(): void
    {
        /** @var MiddlewarePipeline $pipeline */
        $pipeline = $this->get('middleware');
        
        // Add global admin middleware
        $pipeline->global([
            // Add admin authentication checks, etc.
        ]);
    }

    /**
     * Register REST API middleware
     *
     * @return void
     */
    protected function registerRestMiddleware(): void
    {
        /** @var MiddlewarePipeline $pipeline */
        $pipeline = $this->get('middleware');
        
        // Add REST-specific middleware
        $pipeline->global([
            // Add CORS, rate limiting, etc.
        ]);
    }

    /**
     * Register AJAX middleware
     *
     * @return void
     */
    protected function registerAjaxMiddleware(): void
    {
        /** @var MiddlewarePipeline $pipeline */
        $pipeline = $this->get('middleware');
        
        // Add AJAX-specific middleware
        $pipeline->global([
            // Add nonce verification, etc.
        ]);
    }
}