<?php
/**
 * WordPress Object Cache Adapter
 * 
 * @package ZipPicks\Foundation\Cache
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Cache;

use DateInterval;
use DateTime;
use Psr\SimpleCache\InvalidArgumentException;
use ZipPicks\Foundation\Contracts\Cache\CacheInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheStoreInterface;

/**
 * PSR-16 adapter for WordPress object cache
 */
class WPObjectCacheAdapter implements CacheInterface, CacheStoreInterface
{
    /**
     * Cache key prefix
     * 
     * @var string
     */
    protected string $prefix;

    /**
     * Cache group
     * 
     * @var string
     */
    protected string $group;

    /**
     * Default TTL in seconds
     * 
     * @var int
     */
    protected int $defaultTtl;

    /**
     * Cache metrics
     * 
     * @var array
     */
    protected array $metrics = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
    ];

    /**
     * Create a new cache adapter instance
     * 
     * @param string $prefix Key prefix
     * @param string $group Cache group
     * @param int $defaultTtl Default TTL in seconds
     */
    public function __construct(string $prefix = 'zippicks_', string $group = 'zippicks', int $defaultTtl = 3600)
    {
        $this->prefix = $prefix;
        $this->group = $group;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        
        $value = wp_cache_get($this->prefixKey($key), $this->group);
        
        if ($value === false) {
            $this->metrics['misses']++;
            return $default;
        }
        
        $this->metrics['hits']++;
        return $this->unserialize($value);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->validateKey($key);
        
        $ttl = $this->normalizeTtl($ttl);
        
        if ($ttl < 0) {
            return $this->delete($key);
        }
        
        $result = wp_cache_set(
            $this->prefixKey($key),
            $this->serialize($value),
            $this->group,
            $ttl
        );
        
        if ($result) {
            $this->metrics['writes']++;
        }
        
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);
        
        $result = wp_cache_delete($this->prefixKey($key), $this->group);
        
        if ($result) {
            $this->metrics['deletes']++;
        }
        
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        if (function_exists('wp_cache_flush_group')) {
            return wp_cache_flush_group($this->group);
        }
        
        // Fallback: flush entire cache
        return wp_cache_flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $success = true;
        
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);
        
        return wp_cache_get($this->prefixKey($key), $this->group) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 0);
    }

    /**
     * {@inheritdoc}
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->delete($key);
        
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->validateKey($key);
        
        if ($this->has($key)) {
            return false;
        }
        
        return $this->set($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function setWithTags(string $key, mixed $value, null|int|DateInterval $ttl = null, array $tags = []): bool
    {
        // For now, just set the value without tag support
        // Tag tracking will be implemented in a future sprint
        return $this->set($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTags(array $tags): bool
    {
        // Stub implementation for now
        // Always return true as requested
        return true;
    }

    /**
     * Validate a cache key
     * 
     * @param string $key
     * 
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateKey(string $key): void
    {
        if (empty($key)) {
            throw new CacheInvalidArgumentException('Cache key cannot be empty');
        }
        
        if (preg_match('/[{}()\/@:\\\\]/', $key)) {
            throw new CacheInvalidArgumentException(
                'Cache key contains invalid characters: ' . $key
            );
        }
    }

    /**
     * Prefix a cache key
     * 
     * @param string $key
     * 
     * @return string
     */
    protected function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Normalize TTL value
     * 
     * @param null|int|DateInterval $ttl
     * 
     * @return int TTL in seconds
     */
    protected function normalizeTtl(null|int|DateInterval $ttl): int
    {
        if ($ttl === null) {
            return $this->defaultTtl;
        }
        
        if ($ttl instanceof DateInterval) {
            $now = new DateTime();
            $future = clone $now;
            $future->add($ttl);
            
            return $future->getTimestamp() - $now->getTimestamp();
        }
        
        return (int) $ttl;
    }

    /**
     * Serialize a value for storage
     * 
     * @param mixed $value
     * 
     * @return mixed
     */
    protected function serialize(mixed $value): mixed
    {
        // WordPress object cache handles serialization
        // We just need to ensure proper type handling
        return $value;
    }

    /**
     * Unserialize a value from storage
     * 
     * @param mixed $value
     * 
     * @return mixed
     */
    protected function unserialize(mixed $value): mixed
    {
        // WordPress object cache handles unserialization
        return $value;
    }

    // CacheStoreInterface methods

    /**
     * {@inheritdoc}
     */
    public function many(array $keys): array
    {
        return $this->getMultiple($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $key, mixed $value, ?int $seconds = null): bool
    {
        return $this->set($key, $value, $seconds);
    }

    /**
     * {@inheritdoc}
     */
    public function putMany(array $values, ?int $seconds = null): bool
    {
        return $this->setMultiple($values, $seconds);
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        $current = $this->get($key, 0);
        
        if (!is_numeric($current)) {
            return false;
        }
        
        $newValue = (int)$current + $value;
        $this->put($key, $newValue);
        
        return $newValue;
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): bool
    {
        return $this->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function getMetrics(): array
    {
        global $wp_object_cache;
        
        $stats = [
            'driver_metrics' => $this->metrics,
            'cache_type' => 'wordpress_object_cache',
        ];
        
        if (method_exists($wp_object_cache, 'stats')) {
            $stats['wp_cache_stats'] = $wp_object_cache->stats();
        }
        
        return $stats;
    }

    /**
     * {@inheritdoc}
     */
    public function isHealthy(): bool
    {
        return function_exists('wp_cache_get');
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'wordpress';
    }
}

/**
 * PSR-16 InvalidArgumentException implementation
 */
class CacheInvalidArgumentException extends \InvalidArgumentException implements InvalidArgumentException
{
}