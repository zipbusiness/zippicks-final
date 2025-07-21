<?php
/**
 * Throttle Middleware
 * 
 * Throttles job processing to prevent overwhelming external services
 * or APIs used by ZipPicks.
 * 
 * @package ZipPicks\Foundation\Queue\Middleware
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue\Middleware;

use ZipPicks\Foundation\Contracts\Queue\JobMiddlewareInterface;
use ZipPicks\Foundation\Contracts\Queue\JobInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheInterface;
use Closure;

/**
 * Throttle Middleware
 * 
 * Throttles job execution based on time windows
 */
class ThrottleMiddleware implements JobMiddlewareInterface
{
    /**
     * Cache instance
     */
    protected CacheInterface $cache;
    
    /**
     * Throttle key
     */
    protected string $key;
    
    /**
     * Allow one job every X seconds
     */
    protected int $allowEverySeconds;
    
    /**
     * Create throttle middleware
     * 
     * @param CacheInterface $cache Cache instance
     * @param string $key Throttle key
     * @param int $allowEverySeconds Allow one job every X seconds
     */
    public function __construct(
        CacheInterface $cache,
        string $key = 'default',
        int $allowEverySeconds = 1
    ) {
        $this->cache = $cache;
        $this->key = $key;
        $this->allowEverySeconds = $allowEverySeconds;
    }
    
    /**
     * {@inheritdoc}
     */
    public function handle(JobInterface $job, Closure $next)
    {
        $key = $this->resolveKey($job);
        
        if (!$this->allowedToRun($key)) {
            // Calculate delay until next allowed time
            $delay = $this->getDelayUntilNextRun($key);
            
            // Release job back to queue with delay
            $job->release($delay);
            
            return null;
        }
        
        // Mark as running
        $this->markAsRunning($key);
        
        return $next($job);
    }
    
    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 20; // Run after rate limiting
    }
    
    /**
     * {@inheritdoc}
     */
    public function shouldRun(JobInterface $job): bool
    {
        // Only throttle specific job types if needed
        return true;
    }
    
    /**
     * Resolve the throttle key
     * 
     * @param JobInterface $job The job
     * @return string
     */
    protected function resolveKey(JobInterface $job): string
    {
        return 'throttle:' . $this->key . ':' . get_class($job);
    }
    
    /**
     * Check if allowed to run
     * 
     * @param string $key Cache key
     * @return bool
     */
    protected function allowedToRun(string $key): bool
    {
        return !$this->cache->has($key);
    }
    
    /**
     * Mark as running
     * 
     * @param string $key Cache key
     * @return void
     */
    protected function markAsRunning(string $key): void
    {
        $this->cache->put($key, time(), $this->allowEverySeconds);
    }
    
    /**
     * Get delay until next run
     * 
     * @param string $key Cache key
     * @return int Seconds
     */
    protected function getDelayUntilNextRun(string $key): int
    {
        $lastRun = (int) $this->cache->get($key, 0);
        $nextAllowedTime = $lastRun + $this->allowEverySeconds;
        $currentTime = time();
        
        return max(0, $nextAllowedTime - $currentTime);
    }
}