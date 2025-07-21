<?php
/**
 * Cache Manager Implementation
 * 
 * @package ZipPicks\Foundation\Cache
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Cache;

use ZipPicks\Foundation\Contracts\Cache\CacheManagerInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheStoreInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheRepositoryInterface;
use ZipPicks\Foundation\Contracts\Cache\LockInterface;
use ZipPicks\Foundation\Cache\Stores\ArrayStore;
use ZipPicks\Foundation\Cache\Stores\DatabaseStore;
use ZipPicks\Foundation\Cache\Stores\RedisStore;

/**
 * Enterprise cache manager with multi-tier support
 */
class CacheManager implements CacheManagerInterface
{
    private array $stores = [];
    private array $drivers = [];
    private array $config;
    private string $defaultDriver;
    private ?CacheRepositoryInterface $tieredCache = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default' => 'wordpress',
            'stores' => [],
            'prefix' => 'zippicks_',
            'multi_tier' => [
                'enabled' => true,
                'tiers' => ['array', 'wordpress', 'redis', 'database'],
            ],
        ], $config);
        
        $this->defaultDriver = $this->config['default'];
        
        // Register default drivers
        $this->registerDefaultDrivers();
    }

    public function store(?string $name = null): CacheStoreInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        return $this->stores[$name] = $this->createStore($name);
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    public function setDefaultDriver(string $name): void
    {
        $this->defaultDriver = $name;
    }

    public function repository(CacheStoreInterface $store): CacheRepositoryInterface
    {
        return new CacheRepository($store);
    }

    public function getStores(): array
    {
        return $this->stores;
    }

    public function extend(string $driver, \Closure $callback): void
    {
        $this->drivers[$driver] = $callback;
    }

    public function hasDriver(string $driver): bool
    {
        return isset($this->drivers[$driver]);
    }

    public function warm(array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_callable($value)) {
                $value = $value();
            }
            
            $ttl = $value['ttl'] ?? 3600;
            $this->store()->put($key, $value['data'] ?? $value, $ttl);
        }
    }

    public function lock(string $name, int $seconds = 0, ?string $owner = null): LockInterface
    {
        $store = $this->store();
        
        if ($store instanceof RedisStore) {
            return $store->lock($name, $seconds, $owner);
        }
        
        return new FileLock($name, $seconds, $owner);
    }

    public function getStatistics(): array
    {
        $stats = [];
        
        foreach ($this->stores as $name => $store) {
            $stats[$name] = $store->getMetrics();
        }
        
        return $stats;
    }

    public function flushAll(): bool
    {
        $success = true;
        
        foreach ($this->stores as $store) {
            if (!$store->flush()) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Get multi-tier cache repository
     */
    public function tiered(): CacheRepositoryInterface
    {
        if ($this->tieredCache === null) {
            $this->tieredCache = $this->createTieredCache();
        }
        
        return $this->tieredCache;
    }

    /**
     * Create a cache store instance
     */
    private function createStore(string $name): CacheStoreInterface
    {
        if (isset($this->drivers[$name])) {
            return call_user_func($this->drivers[$name], $this->config);
        }

        $config = $this->config['stores'][$name] ?? [];
        
        return match($name) {
            'array' => new ArrayStore(
                $config['max_size'] ?? 1000,
                $config['serialize'] ?? false
            ),
            'wordpress' => new WPObjectCacheAdapter(
                $config['group'] ?? 'zippicks',
                $config['prefix'] ?? $this->config['prefix']
            ),
            'database' => new DatabaseStore(
                $config['prefix'] ?? $this->config['prefix']
            ),
            'redis' => $this->createRedisStore($config),
            default => throw new \InvalidArgumentException("Driver [{$name}] is not supported.")
        };
    }

    /**
     * Create Redis store instance
     */
    private function createRedisStore(array $config): RedisStore
    {
        $redis = new \Redis();
        
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;
        
        $redis->connect($host, $port);
        
        if ($password) {
            $redis->auth($password);
        }
        
        $redis->select($database);
        
        return new RedisStore(
            $redis,
            $config['prefix'] ?? $this->config['prefix']
        );
    }

    /**
     * Create multi-tier cache repository
     */
    private function createTieredCache(): CacheRepositoryInterface
    {
        $config = $this->config['multi_tier'];
        
        if (!$config['enabled']) {
            return $this->repository($this->store());
        }
        
        $tiers = $config['tiers'];
        $stores = [];
        
        foreach ($tiers as $tier) {
            try {
                $store = $this->store($tier);
                if ($store->isHealthy()) {
                    $stores[] = $store;
                }
            } catch (\Exception $e) {
                // Skip unavailable stores
                continue;
            }
        }
        
        if (empty($stores)) {
            throw new \RuntimeException('No cache stores are available');
        }
        
        return $this->repository(new MultiTierStore($stores));
    }

    /**
     * Register default cache drivers
     */
    private function registerDefaultDrivers(): void
    {
        // Allow custom drivers to be registered
        do_action('zippicks_register_cache_drivers', $this);
    }
}