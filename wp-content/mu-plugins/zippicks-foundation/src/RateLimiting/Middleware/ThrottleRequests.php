<?php

namespace ZipPicks\Foundation\RateLimiting\Middleware;

use Closure;
use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Middleware\MiddlewareInterface;
use ZipPicks\Foundation\RateLimiting\RateLimiterManager;
use ZipPicks\Foundation\RateLimiting\Exceptions\RateLimitExceededException;
use ZipPicks\Foundation\RateLimiting\Contracts\ThrottleableInterface;

/**
 * ThrottleRequests - HTTP middleware for rate limiting
 * 
 * Protects our REST API endpoints and web routes from abuse.
 * Supports tier-based limits for monetization and fair usage.
 */
class ThrottleRequests implements MiddlewareInterface
{
    /**
     * @var RateLimiterManager
     */
    protected RateLimiterManager $manager;

    /**
     * @var string Default limiter name
     */
    protected string $limiterName = 'api';

    /**
     * Constructor
     * 
     * @param RateLimiterManager $manager
     */
    public function __construct(RateLimiterManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Handle the request
     * 
     * @param Request $request
     * @param Closure $next
     * @param int|string $maxAttempts
     * @param int $decayMinutes
     * @param string|null $limiterName
     * @return Response
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, ?string $limiterName = null)
    {
        $limiterName = $limiterName ?? $this->limiterName;
        $key = $this->resolveRequestKey($request);
        $maxAttempts = $this->resolveMaxAttempts($request, $maxAttempts);
        
        $limiter = $this->getLimiterForRequest($request, $limiterName);
        
        try {
            $response = $limiter->attempt(
                $key,
                $maxAttempts,
                fn() => $next($request),
                $decayMinutes
            );
            
            return $this->addHeaders($response, $key, $maxAttempts, $limiter);
        } catch (RateLimitExceededException $e) {
            return $this->buildExceptionResponse($e, $request);
        }
    }

    /**
     * Resolve the rate limit key for the request
     * 
     * @param Request $request
     * @return string
     */
    protected function resolveRequestKey(Request $request): string
    {
        $user = $this->getUser($request);
        
        if ($user && $user instanceof ThrottleableInterface) {
            return $user->getThrottleKey('api');
        }
        
        // Fallback to IP-based limiting
        return 'ip:' . $this->getRequestIp($request) . ':api';
    }

    /**
     * Resolve max attempts based on user tier
     * 
     * @param Request $request
     * @param int|string $maxAttempts
     * @return int
     */
    protected function resolveMaxAttempts(Request $request, $maxAttempts): int
    {
        // Handle named limits (e.g., 'taste_graph', 'ai_scores')
        if (is_string($maxAttempts)) {
            $limits = $this->getNamedLimits();
            $maxAttempts = $limits[$maxAttempts] ?? 60;
        }
        
        $user = $this->getUser($request);
        
        if ($user && $user instanceof ThrottleableInterface) {
            // Check for unlimited access
            if ($user->hasUnlimitedAccess('api')) {
                return PHP_INT_MAX;
            }
            
            // Apply tier multiplier
            $multiplier = $user->getRateLimitMultiplier();
            return (int) round($maxAttempts * $multiplier);
        }
        
        return (int) $maxAttempts;
    }

    /**
     * Get limiter for the request
     * 
     * @param Request $request
     * @param string $limiterName
     * @return \ZipPicks\Foundation\RateLimiting\Contracts\RateLimiterInterface
     */
    protected function getLimiterForRequest(Request $request, string $limiterName)
    {
        $user = $this->getUser($request);
        
        if ($user && $user instanceof ThrottleableInterface) {
            $tier = $user->getRateLimitTier();
            return $this->manager->forTier($tier, $limiterName);
        }
        
        return $this->manager->limiter($limiterName);
    }

    /**
     * Add rate limit headers to response
     * 
     * @param Response $response
     * @param string $key
     * @param int $maxAttempts
     * @param \ZipPicks\Foundation\RateLimiting\Contracts\RateLimiterInterface $limiter
     * @return Response
     */
    protected function addHeaders($response, string $key, int $maxAttempts, $limiter)
    {
        $usage = $limiter->usage($key);
        
        $headers = [
            'X-RateLimit-Limit' => $usage['limit'] ?? $maxAttempts,
            'X-RateLimit-Remaining' => $usage['remaining'] ?? 0,
            'X-RateLimit-Reset' => $usage['reset_at'] ?? time(),
        ];
        
        if (isset($usage['tier']) && $usage['tier'] !== 'free') {
            $headers['X-RateLimit-Tier'] = $usage['tier'];
        }
        
        foreach ($headers as $name => $value) {
            $response->header($name, $value);
        }
        
        return $response;
    }

    /**
     * Build exception response
     * 
     * @param RateLimitExceededException $e
     * @param Request $request
     * @return Response
     */
    protected function buildExceptionResponse(RateLimitExceededException $e, Request $request)
    {
        $data = $e->render();
        
        // Add upgrade URL for better UX
        if ($data['upgrade_path']) {
            $data['upgrade_url'] = $this->getUpgradeUrl($data['upgrade_path']);
        }
        
        $response = new Response(json_encode($data), 429, [
            'Content-Type' => 'application/json',
        ]);
        
        // Add rate limit headers
        foreach ($data['headers'] as $name => $value) {
            $response->header($name, $value);
        }
        
        // Log for analytics
        $this->logRateLimitExceeded($e, $request);
        
        return $response;
    }

    /**
     * Get user from request
     * 
     * @param Request $request
     * @return mixed
     */
    protected function getUser(Request $request)
    {
        // Check if user is authenticated
        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            
            if ($user && $user->ID > 0) {
                // Wrap in our User model if available
                if (class_exists('\\ZipPicks\\Foundation\\Models\\User')) {
                    return new \ZipPicks\Foundation\Models\User($user);
                }
                
                return $user;
            }
        }
        
        return null;
    }

    /**
     * Get request IP address
     * 
     * @param Request $request
     * @return string
     */
    protected function getRequestIp(Request $request): string
    {
        // Check for proxied IPs
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'];
        
        foreach ($headers as $header) {
            if ($ip = $request->server($header)) {
                return explode(',', $ip)[0];
            }
        }
        
        return $request->server('REMOTE_ADDR') ?? '0.0.0.0';
    }

    /**
     * Get named limits configuration
     * 
     * @return array
     */
    protected function getNamedLimits(): array
    {
        return [
            'api' => 100,
            'taste_graph' => 10,
            'ai_scores' => 5,
            'vibe_matching' => 20,
            'search' => 100,
            'email' => 50,
        ];
    }

    /**
     * Get upgrade URL for a tier
     * 
     * @param string $tier
     * @return string
     */
    protected function getUpgradeUrl(string $tier): string
    {
        $base = site_url('/pricing');
        
        $tiers = [
            'ZipPicks Pro' => $base . '#pro',
            'ZipPicks Business' => $base . '#business',
            'ZipPicks Enterprise' => $base . '#enterprise',
        ];
        
        return $tiers[$tier] ?? $base;
    }

    /**
     * Log rate limit exceeded event
     * 
     * @param RateLimitExceededException $e
     * @param Request $request
     * @return void
     */
    protected function logRateLimitExceeded(RateLimitExceededException $e, Request $request): void
    {
        if (function_exists('zippicks_log')) {
            zippicks_log()->warning('Rate limit exceeded', array_merge(
                $e->getLogContext(),
                [
                    'ip' => $this->getRequestIp($request),
                    'user_agent' => $request->header('User-Agent'),
                    'endpoint' => $request->getPathInfo(),
                    'method' => $request->getMethod(),
                ]
            ));
        }
    }

    /**
     * Set default limiter name
     * 
     * @param string $name
     * @return void
     */
    public function setDefaultLimiter(string $name): void
    {
        $this->limiterName = $name;
    }
}