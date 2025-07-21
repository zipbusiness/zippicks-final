<?php

namespace ZipPicks\Foundation\RateLimiting\Stores;

use ZipPicks\Foundation\RateLimiting\Contracts\RateLimitStoreInterface;

/**
 * InMemoryStore - In-process memory storage for rate limiting
 * 
 * For development, testing, and single-server deployments.
 * Not suitable for distributed production use.
 */
class InMemoryStore implements RateLimitStoreInterface
{
    /**
     * @var array Storage array
     */
    protected array $storage = [];

    /**
     * @var array Expiration times
     */
    protected array $expirations = [];

    /**
     * @var array Metadata storage
     */
    protected array $metadata = [];

    /**
     * @var int Last cleanup time
     */
    protected int $lastCleanup = 0;

    /**
     * @var int Cleanup interval (seconds)
     */
    protected int $cleanupInterval = 60;

    /**
     * {@inheritDoc}
     */
    public function increment(string $key, int $decay, int $amount = 1): int
    {
        $this->cleanup();
        
        $now = time();
        $expiresAt = $now + $decay;
        
        if (!isset($this->storage[$key]) || $this->isExpired($key)) {
            $this->storage[$key] = 0;
        }
        
        $this->storage[$key] += $amount;
        $this->expirations[$key] = $expiresAt;
        
        return $this->storage[$key];
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): int
    {
        $this->cleanup();
        
        if ($this->isExpired($key)) {
            return 0;
        }
        
        return $this->storage[$key] ?? 0;
    }

    /**
     * {@inheritDoc}
     */
    public function reset(string $key): void
    {
        unset($this->storage[$key], $this->expirations[$key], $this->metadata[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function ttl(string $key): int
    {
        if (!isset($this->expirations[$key])) {
            return -1;
        }
        
        if ($this->isExpired($key)) {
            return -1;
        }
        
        $ttl = $this->expirations[$key] - time();
        return $ttl > 0 ? $ttl : -1;
    }

    /**
     * {@inheritDoc}
     */
    public function getBatch(array $keys): array
    {
        $this->cleanup();
        
        $result = [];
        
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function incrementWithLimit(string $key, int $limit, int $window, int $amount = 1): array
    {
        $current = $this->get($key);
        
        if ($current + $amount > $limit) {
            return [
                'allowed' => false,
                'current' => $current,
                'ttl' => $this->ttl($key),
            ];
        }
        
        $new = $this->increment($key, $window, $amount);
        
        return [
            'allowed' => true,
            'current' => $new,
            'ttl' => $window,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function setMetadata(string $key, array $metadata, int $ttl): void
    {
        $this->metadata[$key] = [
            'data' => $metadata,
            'expires_at' => time() + $ttl,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata(string $key): ?array
    {
        if (!isset($this->metadata[$key])) {
            return null;
        }
        
        $meta = $this->metadata[$key];
        
        if ($meta['expires_at'] < time()) {
            unset($this->metadata[$key]);
            return null;
        }
        
        return $meta['data'];
    }

    /**
     * {@inheritDoc}
     */
    public function isAvailable(): bool
    {
        return true; // Always available
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return 'memory';
    }

    /**
     * Clear keys matching a pattern
     * 
     * @param string $pattern
     * @return void
     */
    public function clearPattern(string $pattern): void
    {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
        
        foreach (array_keys($this->storage) as $key) {
            if (preg_match($regex, $key)) {
                $this->reset($key);
            }
        }
    }

    /**
     * Check if a key is expired
     * 
     * @param string $key
     * @return bool
     */
    protected function isExpired(string $key): bool
    {
        if (!isset($this->expirations[$key])) {
            return true;
        }
        
        return $this->expirations[$key] < time();
    }

    /**
     * Clean up expired entries
     * 
     * @return void
     */
    protected function cleanup(): void
    {
        $now = time();
        
        // Only cleanup periodically
        if ($now - $this->lastCleanup < $this->cleanupInterval) {
            return;
        }
        
        $this->lastCleanup = $now;
        
        // Remove expired entries
        foreach ($this->expirations as $key => $expiresAt) {
            if ($expiresAt < $now) {
                unset($this->storage[$key], $this->expirations[$key]);
            }
        }
        
        // Clean expired metadata
        foreach ($this->metadata as $key => $meta) {
            if ($meta['expires_at'] < $now) {
                unset($this->metadata[$key]);
            }
        }
    }

    /**
     * Get memory usage statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        $this->cleanup();
        
        return [
            'total_keys' => count($this->storage),
            'active_keys' => count(array_filter($this->expirations, fn($exp) => $exp > time())),
            'metadata_keys' => count($this->metadata),
            'memory_usage' => strlen(serialize($this->storage)) + strlen(serialize($this->expirations)) + strlen(serialize($this->metadata)),
        ];
    }

    /**
     * Clear all data
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->storage = [];
        $this->expirations = [];
        $this->metadata = [];
        $this->lastCleanup = 0;
    }
}