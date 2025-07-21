<?php
/**
 * Rate Limit Middleware
 * 
 * Prevents job flooding and ensures system stability by limiting
 * job processing rates.
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
 * Rate Limit Middleware
 * 
 * Limits job processing rate
 */
class RateLimitMiddleware implements JobMiddlewareInterface
{
    /**
     * Cache instance
     */
    protected CacheInterface $cache;
    
    /**
     * Rate limit key
     */
    protected string $key;
    
    /**
     * Maximum attempts
     */
    protected int $maxAttempts;
    
    /**
     * Time window in seconds
     */
    protected int $decaySeconds;
    
    /**
     * Create rate limit middleware
     * 
     * @param CacheInterface $cache Cache instance
     * @param string $key Rate limit key
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $decaySeconds Time window in seconds
     */
    public function __construct(
        CacheInterface $cache,
        string $key,
        int $maxAttempts = 60,
        int $decaySeconds = 60
    ) {
        $this->cache = $cache;
        $this->key = $key;
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
    }
    
    /**
     * {@inheritdoc}
     */
    public function handle(JobInterface $job, Closure $next)
    {
        $key = $this->resolveKey($job);
        
        if ($this->tooManyAttempts($key)) {
            // Release the job back to queue with delay
            $job->release($this->getTimeUntilNextRetry($key));
            
            return null;
        }
        
        $this->incrementAttempts($key);
        
        return $next($job);
    }
    
    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 10; // Run early in the middleware stack
    }
    
    /**
     * {@inheritdoc}
     */
    public function shouldRun(JobInterface $job): bool
    {
        return true;
    }
    
    /**
     * Resolve the rate limit key
     * 
     * @param JobInterface $job The job
     * @return string
     */
    protected function resolveKey(JobInterface $job): string
    {
        $key = str_replace('{job}', get_class($job), $this->key);
        
        // Replace any metadata placeholders
        $metadata = $job->metadata();
        foreach ($metadata as $name => $value) {
            if (is_scalar($value)) {
                $key = str_replace('{' . $name . '}', (string) $value, $key);
            }
        }
        
        return 'rate_limit:' . $key;
    }
    
    /**
     * Check if too many attempts
     * 
     * @param string $key Cache key
     * @return bool
     */
    protected function tooManyAttempts(string $key): bool
    {
        $attempts = (int) $this->cache->get($key, 0);
        
        return $attempts >= $this->maxAttempts;
    }
    
    /**
     * Increment attempts
     * 
     * @param string $key Cache key
     * @return void
     */
    protected function incrementAttempts(string $key): void
    {
        $attempts = (int) $this->cache->get($key, 0);
        
        $this->cache->put($key, $attempts + 1, $this->decaySeconds);
    }
    
    /**
     * Get time until next retry
     * 
     * @param string $key Cache key
     * @return int Seconds
     */
    protected function getTimeUntilNextRetry(string $key): int
    {
        // Get remaining TTL from cache
        // This would need cache driver support for TTL queries
        return $this->decaySeconds;
    }
}