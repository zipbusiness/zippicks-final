<?php
/**
 * Instrumented Cache Wrapper for Automatic Cache Operation Tracing
 * 
 * @package ZipPicks\Foundation\Observability
 * @since 2.0.0
 */

namespace ZipPicks\Foundation\Observability;

use ZipPicks\Foundation\Contracts\Cache\CacheInterface;
use ZipPicks\Foundation\Core\Foundation;
use OpenTelemetry\API\Trace\SpanKind;

class InstrumentedCache implements CacheInterface
{
    /**
     * @var CacheInterface Original cache implementation
     */
    protected CacheInterface $cache;
    
    /**
     * @var OpenTelemetryService
     */
    protected OpenTelemetryService $telemetry;
    
    /**
     * @var bool Instrumentation enabled
     */
    protected bool $enabled = false;
    
    /**
     * @var array Cache statistics
     */
    protected array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'flushes' => 0
    ];
    
    /**
     * Constructor
     * 
     * @param CacheInterface $cache
     */
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
        
        $container = Foundation::getInstance()->getContainer();
        
        if ($container->has('telemetry')) {
            $this->telemetry = $container->get('telemetry');
            $this->enabled = $this->telemetry->isEnabled();
        }
    }
    
    /**
     * Get item from cache
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null): mixed
    {
        if (!$this->enabled) {
            return $this->cache->get($key, $default);
        }
        
        $attributes = [
            'cache.operation' => 'get',
            'cache.key' => $this->sanitizeKey($key),
            'cache.driver' => $this->getCacheDriver()
        ];
        
        $span = $this->telemetry->startSpan('Cache GET', $attributes, SpanKind::KIND_CLIENT);
        
        try {
            $startTime = microtime(true);
            $value = $this->cache->get($key, $default);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $hit = $value !== $default;
            
            if ($span) {
                $span->setAttribute('cache.hit', $hit);
                $span->setAttribute('cache.duration_ms', $duration);
                
                if (!$hit) {
                    $span->setAttribute('cache.default_used', true);
                }
            }
            
            // Update stats
            if ($hit) {
                $this->stats['hits']++;
            } else {
                $this->stats['misses']++;
            }
            
            // Add event
            $this->telemetry->addEvent('cache.accessed', [
                'key' => $this->sanitizeKey($key),
                'hit' => $hit
            ]);
            
            return $value;
            
        } catch (\Throwable $e) {
            $this->telemetry->recordException($e);
            throw $e;
        } finally {
            $this->telemetry->endSpan('Cache GET');
        }
    }
    
    /**
     * Set item in cache
     * 
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        if (!$this->enabled) {
            return $this->cache->set($key, $value, $ttl);
        }
        
        $attributes = [
            'cache.operation' => 'set',
            'cache.key' => $this->sanitizeKey($key),
            'cache.driver' => $this->getCacheDriver()
        ];
        
        if ($ttl !== null) {
            $attributes['cache.ttl'] = $ttl;
        }
        
        // Estimate value size
        $attributes['cache.value_size'] = $this->estimateSize($value);
        
        $span = $this->telemetry->startSpan('Cache SET', $attributes, SpanKind::KIND_CLIENT);
        
        try {
            $startTime = microtime(true);
            $result = $this->cache->set($key, $value, $ttl);
            $duration = (microtime(true) - $startTime) * 1000;
            
            if ($span) {
                $span->setAttribute('cache.success', $result);
                $span->setAttribute('cache.duration_ms', $duration);
            }
            
            // Update stats
            if ($result) {
                $this->stats['sets']++;
            }
            
            // Add event
            $this->telemetry->addEvent('cache.written', [
                'key' => $this->sanitizeKey($key),
                'success' => $result,
                'ttl' => $ttl
            ]);
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->telemetry->recordException($e);
            throw $e;
        } finally {
            $this->telemetry->endSpan('Cache SET');
        }
    }
    
    /**
     * Delete item from cache
     * 
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        if (!$this->enabled) {
            return $this->cache->delete($key);
        }
        
        $attributes = [
            'cache.operation' => 'delete',
            'cache.key' => $this->sanitizeKey($key),
            'cache.driver' => $this->getCacheDriver()
        ];
        
        $span = $this->telemetry->startSpan('Cache DELETE', $attributes, SpanKind::KIND_CLIENT);
        
        try {
            $startTime = microtime(true);
            $result = $this->cache->delete($key);
            $duration = (microtime(true) - $startTime) * 1000;
            
            if ($span) {
                $span->setAttribute('cache.success', $result);
                $span->setAttribute('cache.duration_ms', $duration);
            }
            
            // Update stats
            if ($result) {
                $this->stats['deletes']++;
            }
            
            // Add event
            $this->telemetry->addEvent('cache.deleted', [
                'key' => $this->sanitizeKey($key),
                'success' => $result
            ]);
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->telemetry->recordException($e);
            throw $e;
        } finally {
            $this->telemetry->endSpan('Cache DELETE');
        }
    }
    
    /**
     * Clear cache
     * 
     * @return bool
     */
    public function clear(): bool
    {
        if (!$this->enabled) {
            return $this->cache->clear();
        }
        
        $attributes = [
            'cache.operation' => 'clear',
            'cache.driver' => $this->getCacheDriver()
        ];
        
        $span = $this->telemetry->startSpan('Cache CLEAR', $attributes, SpanKind::KIND_CLIENT);
        
        try {
            $startTime = microtime(true);
            $result = $this->cache->clear();
            $duration = (microtime(true) - $startTime) * 1000;
            
            if ($span) {
                $span->setAttribute('cache.success', $result);
                $span->setAttribute('cache.duration_ms', $duration);
            }
            
            // Update stats
            if ($result) {
                $this->stats['flushes']++;
            }
            
            // Add event
            $this->telemetry->addEvent('cache.cleared', [
                'success' => $result
            ]);
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->telemetry->recordException($e);
            throw $e;
        } finally {
            $this->telemetry->endSpan('Cache CLEAR');
        }
    }
    
    /**
     * Get multiple items
     * 
     * @param array $keys
     * @param mixed $default
     * @return array
     */
    public function getMultiple(array $keys, $default = null): array
    {
        if (!$this->enabled) {
            return $this->cache->getMultiple($keys, $default);
        }
        
        $attributes = [
            'cache.operation' => 'get_multiple',
            'cache.key_count' => count($keys),
            'cache.driver' => $this->getCacheDriver()
        ];
        
        $span = $this->telemetry->startSpan('Cache GET_MULTIPLE', $attributes, SpanKind::KIND_CLIENT);
        
        try {
            $startTime = microtime(true);
            $values = $this->cache->getMultiple($keys, $default);
            $duration = (microtime(true) - $startTime) * 1000;
            
            // Calculate hit rate
            $hits = 0;
            foreach ($values as $value) {
                if ($value !== $default) {
                    $hits++;
                }
            }
            
            $hitRate = count($keys) > 0 ? ($hits / count($keys)) : 0;
            
            if ($span) {
                $span->setAttribute('cache.hits', $hits);
                $span->setAttribute('cache.misses', count($keys) - $hits);
                $span->setAttribute('cache.hit_rate', $hitRate);
                $span->setAttribute('cache.duration_ms', $duration);
            }
            
            // Update stats
            $this->stats['hits'] += $hits;
            $this->stats['misses'] += (count($keys) - $hits);
            
            return $values;
            
        } catch (\Throwable $e) {
            $this->telemetry->recordException($e);
            throw $e;
        } finally {
            $this->telemetry->endSpan('Cache GET_MULTIPLE');
        }
    }
    
    /**
     * Set multiple items
     * 
     * @param array $values
     * @param int|null $ttl
     * @return bool
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        if (!$this->enabled) {
            return $this->cache->setMultiple($values, $ttl);
        }
        
        $attributes = [
            'cache.operation' => 'set_multiple',
            'cache.key_count' => count($values),
            'cache.driver' => $this->getCacheDriver()
        ];
        
        if ($ttl !== null) {
            $attributes['cache.ttl'] = $ttl;
        }
        
        $span = $this->telemetry->startSpan('Cache SET_MULTIPLE', $attributes, SpanKind::KIND_CLIENT);
        
        try {
            $startTime = microtime(true);
            $result = $this->cache->setMultiple($values, $ttl);
            $duration = (microtime(true) - $startTime) * 1000;
            
            if ($span) {
                $span->setAttribute('cache.success', $result);
                $span->setAttribute('cache.duration_ms', $duration);
            }
            
            // Update stats
            if ($result) {
                $this->stats['sets'] += count($values);
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->telemetry->recordException($e);
            throw $e;
        } finally {
            $this->telemetry->endSpan('Cache SET_MULTIPLE');
        }
    }
    
    /**
     * Delete multiple items
     * 
     * @param array $keys
     * @return bool
     */
    public function deleteMultiple(array $keys): bool
    {
        if (!$this->enabled) {
            return $this->cache->deleteMultiple($keys);
        }
        
        $attributes = [
            'cache.operation' => 'delete_multiple',
            'cache.key_count' => count($keys),
            'cache.driver' => $this->getCacheDriver()
        ];
        
        $span = $this->telemetry->startSpan('Cache DELETE_MULTIPLE', $attributes, SpanKind::KIND_CLIENT);
        
        try {
            $startTime = microtime(true);
            $result = $this->cache->deleteMultiple($keys);
            $duration = (microtime(true) - $startTime) * 1000;
            
            if ($span) {
                $span->setAttribute('cache.success', $result);
                $span->setAttribute('cache.duration_ms', $duration);
            }
            
            // Update stats
            if ($result) {
                $this->stats['deletes'] += count($keys);
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->telemetry->recordException($e);
            throw $e;
        } finally {
            $this->telemetry->endSpan('Cache DELETE_MULTIPLE');
        }
    }
    
    /**
     * Check if key exists
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        if (!$this->enabled) {
            return $this->cache->has($key);
        }
        
        $attributes = [
            'cache.operation' => 'has',
            'cache.key' => $this->sanitizeKey($key),
            'cache.driver' => $this->getCacheDriver()
        ];
        
        $span = $this->telemetry->startSpan('Cache HAS', $attributes, SpanKind::KIND_CLIENT);
        
        try {
            $startTime = microtime(true);
            $exists = $this->cache->has($key);
            $duration = (microtime(true) - $startTime) * 1000;
            
            if ($span) {
                $span->setAttribute('cache.exists', $exists);
                $span->setAttribute('cache.duration_ms', $duration);
            }
            
            return $exists;
            
        } catch (\Throwable $e) {
            $this->telemetry->recordException($e);
            throw $e;
        } finally {
            $this->telemetry->endSpan('Cache HAS');
        }
    }
    
    /**
     * Get cache driver name
     * 
     * @return string
     */
    protected function getCacheDriver(): string
    {
        $className = get_class($this->cache);
        
        if (strpos($className, 'Redis') !== false) {
            return 'redis';
        } elseif (strpos($className, 'Memcache') !== false) {
            return 'memcached';
        } elseif (strpos($className, 'Database') !== false) {
            return 'database';
        } elseif (strpos($className, 'File') !== false) {
            return 'file';
        } elseif (strpos($className, 'Array') !== false) {
            return 'array';
        }
        
        return 'unknown';
    }
    
    /**
     * Sanitize cache key for telemetry
     * 
     * @param string $key
     * @return string
     */
    protected function sanitizeKey(string $key): string
    {
        // Remove potentially sensitive data
        if (strlen($key) > 100) {
            return substr($key, 0, 50) . '...' . substr($key, -47);
        }
        
        return $key;
    }
    
    /**
     * Estimate value size
     * 
     * @param mixed $value
     * @return int
     */
    protected function estimateSize($value): int
    {
        if (is_string($value)) {
            return strlen($value);
        }
        
        if (is_array($value) || is_object($value)) {
            return strlen(serialize($value));
        }
        
        return 0;
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) : 0;
        
        return array_merge($this->stats, [
            'hit_rate' => $hitRate,
            'total_operations' => array_sum($this->stats),
            'driver' => $this->getCacheDriver(),
            'instrumentation_enabled' => $this->enabled
        ]);
    }
}