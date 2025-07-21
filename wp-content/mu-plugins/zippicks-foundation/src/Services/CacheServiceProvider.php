<?php
/**
 * Cache Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Contracts\Cache\CacheInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheManagerInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheRepositoryInterface;
use ZipPicks\Foundation\Cache\WPObjectCacheAdapter;
use ZipPicks\Foundation\Cache\CacheManager;

/**
 * Provides caching services to the foundation
 */
class CacheServiceProvider extends ServiceProvider
{
    /**
     * Register the cache services
     * 
     * @return void
     */
    public function register(): void
    {
        // Register cache manager as singleton
        $this->singleton(CacheManagerInterface::class, function() {
            // Get cache configuration
            $config = $this->getCacheConfig();
            
            return new CacheManager($config);
        });

        // Register cache repository (multi-tier by default)
        $this->singleton(CacheRepositoryInterface::class, function() {
            $manager = $this->get(CacheManagerInterface::class);
            
            // Use multi-tier cache if enabled
            if ($this->getCacheConfig()['multi_tier']['enabled'] ?? true) {
                return $manager->tiered();
            }
            
            return $manager->repository($manager->store());
        });

        // Register primary cache interface
        $this->singleton(CacheInterface::class, function() {
            $manager = $this->get(CacheManagerInterface::class);
            return $manager->store();
        });

        // Register aliases
        $container = $this->foundation->getContainer();
        $container->alias('cache', CacheRepositoryInterface::class);
        $container->alias('cache.manager', CacheManagerInterface::class);
        $container->alias('cache.store', CacheInterface::class);
    }

    /**
     * Bootstrap the cache services
     * 
     * @return void
     */
    public function boot(): void
    {
        // Log cache service initialization if logging is available
        if ($this->has('logger')) {
            $logger = $this->get('logger');
            $manager = $this->get(CacheManagerInterface::class);
            
            $logger->channel('cache')->info('Cache service initialized', [
                'default_driver' => $manager->getDefaultDriver(),
                'has_object_cache' => function_exists('wp_cache_get'),
                'has_redis' => extension_loaded('redis'),
                'multi_tier_enabled' => $this->getCacheConfig()['multi_tier']['enabled'] ?? true,
            ]);
        }

        // Register health check
        $this->registerHealthCheck();
    }

    /**
     * Get cache configuration
     * 
     * @return array
     */
    private function getCacheConfig(): array
    {
        $config = [];
        
        // Load from config file if exists
        $configFile = ZIPPICKS_FOUNDATION_PATH . '/config/cache.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        }
        
        // Override with settings if available
        if ($this->foundation->getContainer()->has('settings')) {
            $settings = $this->foundation->getContainer()->get('settings');
            
            // Merge settings into config
            $config = array_merge($config, [
                'default' => $settings->get('cache.default', $config['default'] ?? 'wordpress'),
                'prefix' => $settings->get('cache.prefix', $config['prefix'] ?? 'zippicks_'),
            ]);
        }
        
        // Set defaults for multi-tier caching
        $config['multi_tier'] = array_merge([
            'enabled' => true,
            'tiers' => ['array', 'wordpress'],
        ], $config['multi_tier'] ?? []);
        
        // Add Redis to tiers if available
        if (extension_loaded('redis') && class_exists('\Redis')) {
            $redisConfig = $config['stores']['redis'] ?? [];
            if ($this->isRedisAvailable($redisConfig)) {
                array_splice($config['multi_tier']['tiers'], 2, 0, ['redis']);
            }
        }
        
        return $config;
    }

    /**
     * Check if Redis is available
     * 
     * @param array $config
     * @return bool
     */
    private function isRedisAvailable(array $config): bool
    {
        try {
            $redis = new \Redis();
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 6379;
            
            if (@$redis->connect($host, $port, 0.5)) {
                $redis->close();
                return true;
            }
        } catch (\Exception $e) {
            // Redis not available
        }
        
        return false;
    }

    /**
     * Register cache health check
     * 
     * @return void
     */
    private function registerHealthCheck(): void
    {
        if ($this->has('health')) {
            $health = $this->get('health');
            
            $health->registerCheck('cache', function() {
                $manager = $this->get(CacheManagerInterface::class);
                $testKey = 'health_check_' . time();
                $testValue = 'test_' . wp_generate_uuid4();
                
                try {
                    // Test write
                    $cache = $this->get('cache');
                    $cache->put($testKey, $testValue, 10);
                    
                    // Test read
                    $retrieved = $cache->get($testKey);
                    
                    // Cleanup
                    $cache->forget($testKey);
                    
                    if ($retrieved === $testValue) {
                        return [
                            'status' => 'healthy',
                            'message' => 'Cache is functioning properly',
                            'metadata' => $manager->getStatistics(),
                        ];
                    } else {
                        return [
                            'status' => 'degraded',
                            'message' => 'Cache read/write test failed',
                        ];
                    }
                } catch (\Exception $e) {
                    return [
                        'status' => 'unhealthy',
                        'message' => 'Cache error: ' . $e->getMessage(),
                    ];
                }
            }, [
                'critical' => false,
                'description' => 'Tests cache read/write operations',
            ]);
        }
    }
}