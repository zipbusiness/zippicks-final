<?php

namespace ZipPicks\Foundation\RateLimiting\Middleware;

use ZipPicks\Foundation\Queue\Contracts\JobInterface;
use ZipPicks\Foundation\Queue\Contracts\JobMiddlewareInterface;
use ZipPicks\Foundation\RateLimiting\RateLimiterManager;
use ZipPicks\Foundation\RateLimiting\Exceptions\RateLimitExceededException;

/**
 * ThrottleJobs - Queue job middleware for rate limiting
 * 
 * Protects our background jobs and external API calls.
 * Essential for managing costs when calling AI services,
 * sending emails, and processing Taste Graph calculations.
 */
class ThrottleJobs implements JobMiddlewareInterface
{
    /**
     * @var RateLimiterManager
     */
    protected RateLimiterManager $manager;

    /**
     * @var string Rate limit key
     */
    protected string $key;

    /**
     * @var int Maximum attempts
     */
    protected int $maxAttempts;

    /**
     * @var int Decay minutes
     */
    protected int $decayMinutes;

    /**
     * @var string|null Limiter name
     */
    protected ?string $limiterName;

    /**
     * @var bool Release job back to queue on rate limit
     */
    protected bool $releaseOnLimit;

    /**
     * @var int Release delay in seconds
     */
    protected int $releaseDelay;

    /**
     * Constructor
     * 
     * @param string $key Rate limit key
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $decayMinutes Time window in minutes
     * @param string|null $limiterName Specific limiter to use
     * @param bool $releaseOnLimit Release job back to queue if limited
     * @param int $releaseDelay Delay before releasing job
     */
    public function __construct(
        string $key,
        int $maxAttempts = 60,
        int $decayMinutes = 1,
        ?string $limiterName = null,
        bool $releaseOnLimit = true,
        int $releaseDelay = 60
    ) {
        $this->key = $key;
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
        $this->limiterName = $limiterName;
        $this->releaseOnLimit = $releaseOnLimit;
        $this->releaseDelay = $releaseDelay;
    }

    /**
     * Set the rate limiter manager
     * 
     * @param RateLimiterManager $manager
     * @return void
     */
    public function setManager(RateLimiterManager $manager): void
    {
        $this->manager = $manager;
    }

    /**
     * Process the job
     * 
     * @param JobInterface $job
     * @param callable $next
     * @return mixed
     */
    public function handle(JobInterface $job, callable $next)
    {
        if (!isset($this->manager)) {
            // Get manager from container if not set
            $this->manager = app(RateLimiterManager::class);
        }

        $key = $this->resolveKey($job);
        $limiter = $this->getLimiter($job);
        $cost = $this->getCost($job);
        
        try {
            return $limiter->attempt(
                $key,
                $this->maxAttempts,
                fn() => $next($job),
                $this->decayMinutes,
                $cost
            );
        } catch (RateLimitExceededException $e) {
            return $this->handleRateLimitExceeded($job, $e);
        }
    }

    /**
     * Resolve the rate limit key
     * 
     * @param JobInterface $job
     * @return string
     */
    protected function resolveKey(JobInterface $job): string
    {
        // Allow dynamic keys with placeholders
        $key = $this->key;
        
        // Replace {job} with job class name
        $key = str_replace('{job}', get_class($job), $key);
        
        // Replace {user} with user ID if available
        if (method_exists($job, 'getUserId')) {
            $key = str_replace('{user}', $job->getUserId(), $key);
        }
        
        // Replace {resource} with resource identifier
        if (method_exists($job, 'getResourceId')) {
            $key = str_replace('{resource}', $job->getResourceId(), $key);
        }
        
        return $key;
    }

    /**
     * Get limiter for the job
     * 
     * @param JobInterface $job
     * @return \ZipPicks\Foundation\RateLimiting\Contracts\RateLimiterInterface
     */
    protected function getLimiter(JobInterface $job)
    {
        // Check if job has tier information
        if (method_exists($job, 'getRateLimitTier')) {
            $tier = $job->getRateLimitTier();
            return $this->manager->forTier($tier, $this->limiterName);
        }
        
        return $this->manager->limiter($this->limiterName);
    }

    /**
     * Get cost for the job
     * 
     * @param JobInterface $job
     * @return int
     */
    protected function getCost(JobInterface $job): int
    {
        // Allow jobs to define their own cost
        if (method_exists($job, 'getRateLimitCost')) {
            return $job->getRateLimitCost();
        }
        
        // Default costs based on job type
        $costs = [
            'CalculateTasteGraphJob' => 10,
            'SendPersonalizedEmailJob' => 3,
            'ProcessBusinessAnalyticsJob' => 5,
            'GenerateAIScoreJob' => 25,
            'MatchVibesJob' => 5,
        ];
        
        $className = class_basename(get_class($job));
        
        return $costs[$className] ?? 1;
    }

    /**
     * Handle rate limit exceeded
     * 
     * @param JobInterface $job
     * @param RateLimitExceededException $e
     * @return mixed
     */
    protected function handleRateLimitExceeded(JobInterface $job, RateLimitExceededException $e)
    {
        // Log the event
        $this->logRateLimitExceeded($job, $e);
        
        if ($this->releaseOnLimit && method_exists($job, 'release')) {
            // Release job back to queue with delay
            $delay = max($this->releaseDelay, $e->getRetryAfter());
            $job->release($delay);
            
            return null;
        }
        
        // Otherwise, let the exception bubble up
        throw $e;
    }

    /**
     * Log rate limit exceeded for job
     * 
     * @param JobInterface $job
     * @param RateLimitExceededException $e
     * @return void
     */
    protected function logRateLimitExceeded(JobInterface $job, RateLimitExceededException $e): void
    {
        if (function_exists('zippicks_log')) {
            zippicks_log()->warning('Job rate limit exceeded', array_merge(
                $e->getLogContext(),
                [
                    'job_class' => get_class($job),
                    'job_id' => method_exists($job, 'getJobId') ? $job->getJobId() : null,
                    'queue' => method_exists($job, 'getQueue') ? $job->getQueue() : null,
                    'attempts' => method_exists($job, 'attempts') ? $job->attempts() : null,
                    'release_on_limit' => $this->releaseOnLimit,
                    'release_delay' => $this->releaseDelay,
                ]
            ));
        }
    }

    /**
     * Create a new instance with different parameters
     * 
     * @param array $params
     * @return self
     */
    public function with(array $params): self
    {
        return new self(
            $params['key'] ?? $this->key,
            $params['maxAttempts'] ?? $this->maxAttempts,
            $params['decayMinutes'] ?? $this->decayMinutes,
            $params['limiterName'] ?? $this->limiterName,
            $params['releaseOnLimit'] ?? $this->releaseOnLimit,
            $params['releaseDelay'] ?? $this->releaseDelay
        );
    }

    /**
     * Shorthand for external API limiting
     * 
     * @param string $api API name (e.g., 'openai', 'google')
     * @param int $maxAttempts
     * @return self
     */
    public static function forApi(string $api, int $maxAttempts = 100): self
    {
        return new self(
            "api:{$api}",
            $maxAttempts,
            60, // 1 hour window
            'api',
            true,
            300 // 5 minute delay
        );
    }

    /**
     * Shorthand for email limiting
     * 
     * @param int $maxEmails
     * @return self
     */
    public static function forEmail(int $maxEmails = 1000): self
    {
        return new self(
            'email:outbound',
            $maxEmails,
            60, // 1 hour window
            'email',
            true,
            60 // 1 minute delay
        );
    }

    /**
     * Shorthand for Taste Graph calculations
     * 
     * @param string $userId
     * @param int $maxCalculations
     * @return self
     */
    public static function forTasteGraph(string $userId, int $maxCalculations = 10): self
    {
        return new self(
            "user:{$userId}:taste_graph",
            $maxCalculations,
            60, // 1 hour window
            'taste_graph',
            true,
            120 // 2 minute delay
        );
    }
}