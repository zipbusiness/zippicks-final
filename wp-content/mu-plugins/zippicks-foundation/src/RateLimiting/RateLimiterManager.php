<?php

namespace ZipPicks\Foundation\RateLimiting;

use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\RateLimiting\Contracts\RateLimiterInterface;
use ZipPicks\Foundation\RateLimiting\Contracts\RateLimitStoreInterface;
use ZipPicks\Foundation\RateLimiting\Stores\RedisStore;
use ZipPicks\Foundation\RateLimiting\Stores\DatabaseStore;
use ZipPicks\Foundation\RateLimiting\Stores\InMemoryStore;
use ZipPicks\Foundation\RateLimiting\Algorithms\FixedWindowLimiter;
use ZipPicks\Foundation\RateLimiting\Algorithms\SlidingWindowLimiter;
use ZipPicks\Foundation\RateLimiting\Algorithms\TokenBucketLimiter;
use ZipPicks\Foundation\RateLimiting\Algorithms\LeakyBucketLimiter;
use InvalidArgumentException;

/**
 * RateLimiterManager - Manages rate limiters for different use cases
 * 
 * Orchestrates multiple algorithms and stores to protect our $100B platform:
 * - Fixed Window: Simple API limits
 * - Sliding Window: Accurate Taste Graph metering  
 * - Token Bucket: Burst-friendly mobile access
 * - Leaky Bucket: Smooth email campaign delivery
 */
class RateLimiterManager
{
    /**
     * @var Container The IoC container
     */
    protected Container $container;

    /**
     * @var array Configuration
     */
    protected array $config;

    /**
     * @var array Created limiter instances
     */
    protected array $limiters = [];

    /**
     * @var array Created store instances
     */
    protected array $stores = [];

    /**
     * @var array Algorithm factories
     */
    protected array $algorithms = [];

    /**
     * @var array Store factories
     */
    protected array $storeDrivers = [];

    /**
     * @var string Default limiter name
     */
    protected string $defaultLimiter = 'api';

    /**
     * @var array Tier configurations for monetization
     */
    protected array $tiers = [
        'free' => [
            'multiplier' => 1.0,
            'cost_multiplier' => 1.0,
            'limits' => [
                'api' => 100,
                'taste_graph' => 10,
                'ai_scores' => 5,
                'email' => 50,
            ],
        ],
        'pro' => [
            'multiplier' => 10.0,
            'cost_multiplier' => 0.8,
            'limits' => [
                'api' => 10000,
                'taste_graph' => 1000,
                'ai_scores' => 500,
                'email' => 5000,
            ],
        ],
        'business' => [
            'multiplier' => 50.0,
            'cost_multiplier' => 0.5,
            'limits' => [
                'api' => 50000,
                'taste_graph' => 5000,
                'ai_scores' => 2000,
                'email' => 25000,
            ],
        ],
        'enterprise' => [
            'multiplier' => PHP_FLOAT_MAX,
            'cost_multiplier' => 0.1,
            'limits' => [], // Unlimited
        ],
    ];

    /**
     * Constructor
     * 
     * @param Container $container
     * @param array $config
     */
    public function __construct(Container $container, array $config = [])
    {
        $this->container = $container;
        $this->config = $config;
        
        $this->registerDefaultAlgorithms();
        $this->registerDefaultStores();
    }

    /**
     * Get a rate limiter instance
     * 
     * @param string|null $name
     * @return RateLimiterInterface
     */
    public function limiter(?string $name = null): RateLimiterInterface
    {
        $name = $name ?? $this->defaultLimiter;

        if (!isset($this->limiters[$name])) {
            $this->limiters[$name] = $this->createLimiter($name);
        }

        return $this->limiters[$name];
    }

    /**
     * Create a rate limiter for a specific user tier
     * 
     * @param string $tier
     * @param string|null $limiterName
     * @return RateLimiterInterface
     */
    public function forTier(string $tier, ?string $limiterName = null): RateLimiterInterface
    {
        $limiter = $this->limiter($limiterName);
        
        if (!isset($this->tiers[$tier])) {
            throw new InvalidArgumentException("Unknown tier: {$tier}");
        }

        $tierConfig = $this->tiers[$tier];
        
        // Create a wrapped limiter that applies tier multipliers
        return new TierAwareRateLimiter($limiter, $tierConfig);
    }

    /**
     * Register an algorithm factory
     * 
     * @param string $name
     * @param callable $factory
     * @return void
     */
    public function registerAlgorithm(string $name, callable $factory): void
    {
        $this->algorithms[$name] = $factory;
    }

    /**
     * Register a store driver factory
     * 
     * @param string $name
     * @param callable $factory
     * @return void
     */
    public function registerStore(string $name, callable $factory): void
    {
        $this->storeDrivers[$name] = $factory;
    }

    /**
     * Set tier configuration
     * 
     * @param string $tier
     * @param array $config
     * @return void
     */
    public function setTierConfig(string $tier, array $config): void
    {
        $this->tiers[$tier] = array_merge($this->tiers[$tier] ?? [], $config);
    }

    /**
     * Get tier configuration
     * 
     * @param string $tier
     * @return array
     */
    public function getTierConfig(string $tier): array
    {
        return $this->tiers[$tier] ?? $this->tiers['free'];
    }

    /**
     * Check rate limit for a throttleable entity
     * 
     * @param ThrottleableInterface $entity
     * @param string $action
     * @param int $maxAttempts
     * @param callable $callback
     * @param int $decayMinutes
     * @return mixed
     */
    public function throttle(
        ThrottleableInterface $entity,
        string $action,
        int $maxAttempts,
        callable $callback,
        int $decayMinutes = 1
    ) {
        $key = $entity->getThrottleKey($action);
        $tier = $entity->getRateLimitTier();
        $cost = $entity->getCostMultiplier();
        
        $limiter = $this->forTier($tier);
        
        try {
            return $limiter->attempt($key, $maxAttempts, $callback, $decayMinutes, $cost);
        } catch (RateLimitExceededException $e) {
            $entity->onRateLimitExceeded($action, $e->getContext());
            throw $e;
        }
    }

    /**
     * Get usage statistics for all limiters
     * 
     * @return array
     */
    public function getUsageStats(): array
    {
        $stats = [];
        
        foreach ($this->limiters as $name => $limiter) {
            $stats[$name] = [
                'algorithm' => $limiter->getAlgorithm(),
                'store' => $this->getStoreName($name),
                'active_keys' => $this->countActiveKeys($name),
            ];
        }
        
        return $stats;
    }

    /**
     * Clear all rate limits (dangerous - use with caution)
     * 
     * @param string|null $pattern Optional key pattern
     * @return void
     */
    public function clearAll(?string $pattern = null): void
    {
        foreach ($this->stores as $store) {
            if (method_exists($store, 'clearPattern')) {
                $store->clearPattern($pattern ?? '*');
            }
        }
    }

    /**
     * Create a limiter instance
     * 
     * @param string $name
     * @return RateLimiterInterface
     */
    protected function createLimiter(string $name): RateLimiterInterface
    {
        $config = $this->config['limiters'][$name] ?? [];
        
        if (!isset($config['algorithm'])) {
            throw new InvalidArgumentException("No algorithm configured for limiter: {$name}");
        }

        $store = $this->getStore($config['store'] ?? 'redis');
        $algorithm = $config['algorithm'];
        
        if (!isset($this->algorithms[$algorithm])) {
            throw new InvalidArgumentException("Unknown algorithm: {$algorithm}");
        }

        $factory = $this->algorithms[$algorithm];
        return $factory($store, $config);
    }

    /**
     * Get or create a store instance
     * 
     * @param string $name
     * @return RateLimitStoreInterface
     */
    protected function getStore(string $name): RateLimitStoreInterface
    {
        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->createStore($name);
        }

        return $this->stores[$name];
    }

    /**
     * Create a store instance
     * 
     * @param string $name
     * @return RateLimitStoreInterface
     */
    protected function createStore(string $name): RateLimitStoreInterface
    {
        $config = $this->config['stores'][$name] ?? [];
        $driver = $config['driver'] ?? $name;
        
        if (!isset($this->storeDrivers[$driver])) {
            throw new InvalidArgumentException("Unknown store driver: {$driver}");
        }

        $factory = $this->storeDrivers[$driver];
        return $factory($config);
    }

    /**
     * Register default algorithms
     * 
     * @return void
     */
    protected function registerDefaultAlgorithms(): void
    {
        // Fixed window - Simple and efficient
        $this->registerAlgorithm('fixed_window', function ($store, $config) {
            return new FixedWindowLimiter($store, $config['window'] ?? 60);
        });

        // Sliding window - More accurate
        $this->registerAlgorithm('sliding_window', function ($store, $config) {
            return new SlidingWindowLimiter($store, $config['window'] ?? 60);
        });

        // Token bucket - Allows bursts
        $this->registerAlgorithm('token_bucket', function ($store, $config) {
            return new TokenBucketLimiter(
                $store,
                $config['capacity'] ?? 100,
                $config['refill_rate'] ?? 1,
                $config['refill_period'] ?? 1
            );
        });

        // Leaky bucket - Smooth rate
        $this->registerAlgorithm('leaky_bucket', function ($store, $config) {
            return new LeakyBucketLimiter(
                $store,
                $config['capacity'] ?? 100,
                $config['leak_rate'] ?? 1
            );
        });
    }

    /**
     * Register default store drivers
     * 
     * @return void
     */
    protected function registerDefaultStores(): void
    {
        // Redis - Primary production store
        $this->registerStore('redis', function ($config) {
            return new RedisStore(
                $this->container->get('redis'),
                $config['prefix'] ?? 'rate_limit:',
                $config['connection'] ?? 'default'
            );
        });

        // Database - Fallback store
        $this->registerStore('database', function ($config) {
            return new DatabaseStore(
                $config['table'] ?? 'wp_zippicks_rate_limits',
                $config['prefix'] ?? ''
            );
        });

        // Memory - Development/testing
        $this->registerStore('memory', function ($config) {
            return new InMemoryStore();
        });
    }

    /**
     * Get store name for a limiter
     * 
     * @param string $limiterName
     * @return string
     */
    protected function getStoreName(string $limiterName): string
    {
        $config = $this->config['limiters'][$limiterName] ?? [];
        return $config['store'] ?? 'redis';
    }

    /**
     * Count active keys for a limiter
     * 
     * @param string $limiterName
     * @return int
     */
    protected function countActiveKeys(string $limiterName): int
    {
        // This would be implemented based on store capabilities
        // For now, return 0 as placeholder
        return 0;
    }
}