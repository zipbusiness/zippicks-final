<?php
/**
 * Redis Cache Store
 * 
 * @package ZipPicks\Foundation\Cache\Stores
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Cache\Stores;

use ZipPicks\Foundation\Contracts\Cache\CacheStoreInterface;
use ZipPicks\Foundation\Contracts\Cache\LockInterface;
use ZipPicks\Foundation\Core\CircuitBreaker;

/**
 * Enterprise Redis cache store with clustering and connection pooling
 */
class RedisStore implements CacheStoreInterface
{
    private $connection;
    private string $prefix;
    private CircuitBreaker $circuitBreaker;
    private array $metrics = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
        'errors' => 0,
    ];

    public function __construct($connection, string $prefix = '', ?CircuitBreaker $circuitBreaker = null)
    {
        $this->connection = $connection;
        $this->prefix = $prefix;
        $this->circuitBreaker = $circuitBreaker ?: new CircuitBreaker(
            'redis_cache',
            failureThreshold: 5,
            recoveryTime: 60,
            successThreshold: 2
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return $default;
        }

        try {
            $value = $this->connection->get($this->prefix . $key);
            
            if ($value === false || $value === null) {
                $this->metrics['misses']++;
                $this->circuitBreaker->recordSuccess();
                return $default;
            }

            $this->metrics['hits']++;
            $this->circuitBreaker->recordSuccess();
            
            return $this->unserialize($value);
        } catch (\Throwable $e) {
            $this->handleError($e);
            return $default;
        }
    }

    public function many(array $keys): array
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return array_fill_keys($keys, null);
        }

        try {
            $prefixedKeys = array_map(fn($key) => $this->prefix . $key, $keys);
            $values = $this->connection->mget($prefixedKeys);
            
            $result = [];
            foreach ($keys as $index => $key) {
                $value = $values[$index] ?? false;
                $result[$key] = ($value === false || $value === null) 
                    ? null 
                    : $this->unserialize($value);
            }

            $this->circuitBreaker->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return array_fill_keys($keys, null);
        }
    }

    public function put(string $key, mixed $value, ?int $seconds = null): bool
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            $serialized = $this->serialize($value);
            
            if ($seconds === null) {
                $result = $this->connection->set($this->prefix . $key, $serialized);
            } elseif ($seconds > 0) {
                $result = $this->connection->setex($this->prefix . $key, $seconds, $serialized);
            } else {
                return $this->forget($key);
            }

            if ($result) {
                $this->metrics['writes']++;
                $this->circuitBreaker->recordSuccess();
            }

            return (bool) $result;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    public function putMany(array $values, ?int $seconds = null): bool
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            $pipeline = $this->connection->pipeline();
            
            foreach ($values as $key => $value) {
                $serialized = $this->serialize($value);
                
                if ($seconds === null) {
                    $pipeline->set($this->prefix . $key, $serialized);
                } elseif ($seconds > 0) {
                    $pipeline->setex($this->prefix . $key, $seconds, $serialized);
                }
            }

            $results = $pipeline->exec();
            $this->metrics['writes'] += count($values);
            $this->circuitBreaker->recordSuccess();
            
            return !in_array(false, $results, true);
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            $result = $this->connection->incrby($this->prefix . $key, $value);
            $this->circuitBreaker->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            $result = $this->connection->decrby($this->prefix . $key, $value);
            $this->circuitBreaker->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value);
    }

    public function forget(string $key): bool
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            $result = $this->connection->del($this->prefix . $key);
            if ($result) {
                $this->metrics['deletes']++;
                $this->circuitBreaker->recordSuccess();
            }
            return (bool) $result;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    public function flush(): bool
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            // Use SCAN to delete keys with our prefix to avoid FLUSHDB
            $cursor = 0;
            $pattern = $this->prefix . '*';
            
            do {
                [$cursor, $keys] = $this->connection->scan($cursor, 'MATCH', $pattern, 'COUNT', 1000);
                
                if (!empty($keys)) {
                    $this->connection->del(...$keys);
                }
            } while ($cursor != 0);

            $this->circuitBreaker->recordSuccess();
            return true;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'circuit_breaker' => $this->circuitBreaker->getMetrics(),
            'connection_info' => $this->getConnectionInfo(),
        ]);
    }

    public function isHealthy(): bool
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            $this->connection->ping();
            $this->circuitBreaker->recordSuccess();
            return true;
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure();
            return false;
        }
    }

    public function getName(): string
    {
        return 'redis';
    }

    public function lock(string $name, int $seconds = 0, ?string $owner = null): LockInterface
    {
        return new RedisLock($this->connection, $this->prefix . 'lock:' . $name, $seconds, $owner);
    }

    private function serialize(mixed $value): string
    {
        return serialize($value);
    }

    private function unserialize(string $value): mixed
    {
        return unserialize($value);
    }

    private function handleError(\Throwable $e): void
    {
        $this->metrics['errors']++;
        $this->circuitBreaker->recordFailure();
        
        if (function_exists('zippicks_foundation')) {
            $foundation = zippicks_foundation();
            if ($foundation && $foundation->getContainer()->has('logger')) {
                $foundation->getContainer()->get('logger')->error('Redis cache error', [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    private function getConnectionInfo(): array
    {
        try {
            $info = $this->connection->info('server');
            return [
                'version' => $info['redis_version'] ?? 'unknown',
                'mode' => $info['redis_mode'] ?? 'standalone',
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unavailable'];
        }
    }
}