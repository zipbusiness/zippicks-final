<?php
/**
 * Cache Repository Implementation
 * 
 * @package ZipPicks\Foundation\Cache
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Cache;

use DateTimeInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheRepositoryInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheStoreInterface;
use ZipPicks\Foundation\Contracts\Cache\TaggedCacheInterface;
use ZipPicks\Foundation\Contracts\Cache\LockInterface;
use ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface;

/**
 * High-level cache repository with enterprise features
 */
class CacheRepository implements CacheRepositoryInterface
{
    private CacheStoreInterface $store;
    private ?EventDispatcherInterface $events = null;
    private array $locks = [];

    public function __construct(CacheStoreInterface $store)
    {
        $this->store = $store;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->fireEvent('cache:hit', compact('key'));
        return $this->store->get($key, $default);
    }

    public function many(array $keys): array
    {
        $this->fireEvent('cache:many', compact('keys'));
        return $this->store->many($keys);
    }

    public function put(string $key, mixed $value, ?int $seconds = null): bool
    {
        $result = $this->store->put($key, $value, $seconds);
        $this->fireEvent('cache:write', compact('key', 'seconds'));
        return $result;
    }

    public function putMany(array $values, ?int $seconds = null): bool
    {
        $result = $this->store->putMany($values, $seconds);
        $this->fireEvent('cache:write_many', ['keys' => array_keys($values), 'seconds' => $seconds]);
        return $result;
    }

    public function add(string $key, mixed $value, ?int $seconds = null): bool
    {
        if ($this->store->get($key) !== null) {
            return false;
        }
        return $this->put($key, $value, $seconds);
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $result = $this->store->increment($key, $value);
        $this->fireEvent('cache:increment', compact('key', 'value'));
        return $result;
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        $result = $this->store->decrement($key, $value);
        $this->fireEvent('cache:decrement', compact('key', 'value'));
        return $result;
    }

    public function forever(string $key, mixed $value): bool
    {
        $result = $this->store->forever($key, $value);
        $this->fireEvent('cache:forever', compact('key'));
        return $result;
    }

    public function forget(string $key): bool
    {
        $result = $this->store->forget($key);
        $this->fireEvent('cache:forget', compact('key'));
        return $result;
    }

    public function flush(): bool
    {
        $result = $this->store->flush();
        $this->fireEvent('cache:flush');
        return $result;
    }

    public function remember(string $key, int $seconds, \Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $seconds);

        return $value;
    }

    public function rememberForever(string $key, \Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->forever($key, $value);

        return $value;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    public function putUntil(string $key, mixed $value, DateTimeInterface $expiration): bool
    {
        $seconds = max(0, $expiration->getTimestamp() - time());
        return $this->put($key, $value, $seconds);
    }

    public function stampedeSafe(string $key, int $seconds, \Closure $callback): mixed
    {
        // Try to get from cache first
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        // Acquire lock to prevent stampede
        $lockKey = $key . ':lock';
        $lock = $this->getLock($lockKey, $seconds);

        if (!$lock->acquire()) {
            // Another process is regenerating, wait and retry
            $attempts = 0;
            while ($attempts < 10) {
                usleep(100000); // 100ms
                $value = $this->get($key);
                if ($value !== null) {
                    return $value;
                }
                $attempts++;
            }
            
            // Fallback: generate without lock
            $value = $callback();
            $this->put($key, $value, $seconds);
            return $value;
        }

        try {
            // Double-check after acquiring lock
            $value = $this->get($key);
            if ($value !== null) {
                return $value;
            }

            // Generate new value
            $value = $callback();
            $this->put($key, $value, $seconds);

            return $value;
        } finally {
            $lock->release();
        }
    }

    public function tags(string|array $tags): TaggedCacheInterface
    {
        if (!$this->store instanceof TaggedCacheInterface) {
            throw new \RuntimeException('This cache store does not support tagging.');
        }

        return $this->store->tags($tags);
    }

    public function getPrefix(): string
    {
        return $this->store->getPrefix();
    }

    public function getMetrics(): array
    {
        return $this->store->getMetrics();
    }

    public function isHealthy(): bool
    {
        return $this->store->isHealthy();
    }

    public function getName(): string
    {
        return $this->store->getName();
    }

    public function getStore(): CacheStoreInterface
    {
        return $this->store;
    }

    public function setEventDispatcher(EventDispatcherInterface $events): void
    {
        $this->events = $events;
    }

    private function fireEvent(string $event, array $payload = []): void
    {
        if ($this->events) {
            $this->events->dispatch($event, $payload);
        }
    }

    private function getLock(string $name, int $seconds): LockInterface
    {
        if (!isset($this->locks[$name])) {
            if ($this->store instanceof \ZipPicks\Foundation\Cache\Stores\RedisStore) {
                $this->locks[$name] = $this->store->lock($name, $seconds);
            } else {
                // Fallback to file-based lock
                $this->locks[$name] = new FileLock($name, $seconds);
            }
        }

        return $this->locks[$name];
    }
}