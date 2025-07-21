<?php

namespace ZipPicks\Foundation\RateLimiting\Algorithms;

use ZipPicks\Foundation\RateLimiting\RateLimiter;
use ZipPicks\Foundation\RateLimiting\Contracts\RateLimitStoreInterface;

/**
 * FixedWindowLimiter - Simple fixed time window rate limiting
 * 
 * Most efficient algorithm for basic API rate limiting.
 * Resets counts at fixed intervals (e.g., every minute).
 * Perfect for our free tier users with simple limits.
 */
class FixedWindowLimiter extends RateLimiter
{
    /**
     * @var int Window size in seconds
     */
    protected int $windowSize;

    /**
     * Constructor
     * 
     * @param RateLimitStoreInterface $store
     * @param int $windowSize Window size in seconds
     */
    public function __construct(RateLimitStoreInterface $store, int $windowSize = 60)
    {
        parent::__construct($store, 'fixed_window');
        $this->windowSize = $windowSize;
    }

    /**
     * {@inheritDoc}
     */
    public function hit(string $key, int $decayMinutes = 1, int $cost = 1): int
    {
        // Calculate window key based on current time
        $windowKey = $this->getWindowKey($key);
        
        // Use window size from constructor, not decay parameter
        return parent::hit($windowKey, (int) ceil($this->windowSize / 60), $cost);
    }

    /**
     * {@inheritDoc}
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $cost = 1): bool
    {
        $windowKey = $this->getWindowKey($key);
        return parent::tooManyAttempts($windowKey, $maxAttempts, $cost);
    }

    /**
     * {@inheritDoc}
     */
    public function availableIn(string $key): int
    {
        $windowKey = $this->getWindowKey($key);
        $ttl = parent::availableIn($windowKey);
        
        if ($ttl <= 0) {
            // Calculate time until next window
            $currentWindow = floor(time() / $this->windowSize);
            $nextWindow = ($currentWindow + 1) * $this->windowSize;
            return $nextWindow - time();
        }
        
        return $ttl;
    }

    /**
     * {@inheritDoc}
     */
    public function usage(string $key): array
    {
        $windowKey = $this->getWindowKey($key);
        $usage = parent::usage($windowKey);
        
        // Add window information
        $usage['window_start'] = $this->getCurrentWindowStart();
        $usage['window_end'] = $this->getCurrentWindowEnd();
        $usage['window_size'] = $this->windowSize;
        
        return $usage;
    }

    /**
     * Get window-specific key
     * 
     * @param string $key Base key
     * @return string Window key
     */
    protected function getWindowKey(string $key): string
    {
        $window = floor(time() / $this->windowSize);
        return $key . ':window:' . $window;
    }

    /**
     * Get current window start timestamp
     * 
     * @return int
     */
    protected function getCurrentWindowStart(): int
    {
        return floor(time() / $this->windowSize) * $this->windowSize;
    }

    /**
     * Get current window end timestamp
     * 
     * @return int
     */
    protected function getCurrentWindowEnd(): int
    {
        return $this->getCurrentWindowStart() + $this->windowSize - 1;
    }

    /**
     * Get window size
     * 
     * @return int
     */
    public function getWindowSize(): int
    {
        return $this->windowSize;
    }

    /**
     * Set window size
     * 
     * @param int $seconds
     * @return void
     */
    public function setWindowSize(int $seconds): void
    {
        $this->windowSize = max(1, $seconds);
    }
}