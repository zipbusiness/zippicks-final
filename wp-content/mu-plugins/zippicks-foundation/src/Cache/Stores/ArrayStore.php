<?php
/**
 * Array Cache Store
 * 
 * @package ZipPicks\Foundation\Cache\Stores
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Cache\Stores;

use ZipPicks\Foundation\Contracts\Cache\CacheStoreInterface;

/**
 * In-memory array cache store for L1 caching within request lifecycle
 */
class ArrayStore implements CacheStoreInterface
{
    private array $storage = [];
    private array $expirations = [];
    private array $metrics = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
        'evictions' => 0,
    ];
    private int $maxSize;
    private bool $serialize;

    public function __construct(int $maxSize = 1000, bool $serialize = false)
    {
        $this->maxSize = $maxSize;
        $this->serialize = $serialize;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->expired($key)) {
            $this->forget($key);
            $this->metrics['misses']++;
            return $default;
        }

        if (!array_key_exists($key, $this->storage)) {
            $this->metrics['misses']++;
            return $default;
        }

        $this->metrics['hits']++;
        return $this->serialize 
            ? unserialize($this->storage[$key]) 
            : $this->storage[$key];
    }

    public function many(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    public function put(string $key, mixed $value, ?int $seconds = null): bool
    {
        $this->ensureCapacity();

        $this->storage[$key] = $this->serialize ? serialize($value) : $value;
        
        if ($seconds !== null && $seconds > 0) {
            $this->expirations[$key] = time() + $seconds;
        } else {
            unset($this->expirations[$key]);
        }

        $this->metrics['writes']++;
        return true;
    }

    public function putMany(array $values, ?int $seconds = null): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }
        return true;
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $current = $this->get($key, 0);
        
        if (!is_numeric($current)) {
            return false;
        }

        $newValue = $current + $value;
        $this->put($key, $newValue);
        
        return $newValue;
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value);
    }

    public function forget(string $key): bool
    {
        if (array_key_exists($key, $this->storage)) {
            unset($this->storage[$key], $this->expirations[$key]);
            $this->metrics['deletes']++;
            return true;
        }
        return false;
    }

    public function flush(): bool
    {
        $this->storage = [];
        $this->expirations = [];
        return true;
    }

    public function getPrefix(): string
    {
        return '';
    }

    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'size' => count($this->storage),
            'max_size' => $this->maxSize,
            'memory_usage' => $this->getMemoryUsage(),
        ]);
    }

    public function isHealthy(): bool
    {
        return count($this->storage) < $this->maxSize;
    }

    public function getName(): string
    {
        return 'array';
    }

    private function expired(string $key): bool
    {
        if (!isset($this->expirations[$key])) {
            return false;
        }

        return time() > $this->expirations[$key];
    }

    private function ensureCapacity(): void
    {
        if (count($this->storage) >= $this->maxSize) {
            // Evict 10% of oldest entries
            $toEvict = (int) ($this->maxSize * 0.1);
            $keys = array_keys($this->storage);
            
            for ($i = 0; $i < $toEvict; $i++) {
                unset($this->storage[$keys[$i]], $this->expirations[$keys[$i]]);
                $this->metrics['evictions']++;
            }
        }
    }

    private function getMemoryUsage(): int
    {
        $serialized = serialize($this->storage);
        return strlen($serialized);
    }
}