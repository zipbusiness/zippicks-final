<?php
/**
 * ZipPicks API Rate Limiting Middleware
 * 
 * Applies tier-based rate limiting to API requests
 *
 * @package ZipPicks\Foundation\Api\Middleware
 */

namespace ZipPicks\Foundation\Api\Middleware;

use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Middleware\Middleware;
use ZipPicks\Foundation\RateLimiting\RateLimiter;
use ZipPicks\Foundation\Logging\Logger;

class ApiRateLimiting extends Middleware
{
    /**
     * Rate limiter instance
     *
     * @var RateLimiter
     */
    protected RateLimiter $rateLimiter;

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected Logger $logger;

    /**
     * Create new rate limiting middleware
     *
     * @param RateLimiter $rateLimiter
     * @param Logger $logger
     */
    public function __construct(RateLimiter $rateLimiter, Logger $logger)
    {
        $this->rateLimiter = $rateLimiter;
        $this->logger = $logger;
    }

    /**
     * Handle the request
     *
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // Get tier from authenticated API key
        $tier = $request->attributes->get('api_tier', 'free');
        $apiKeyId = $request->attributes->get('api_key_id');
        
        // Build rate limit key
        $key = $this->buildKey($request, $apiKeyId);
        
        // Get limits for tier
        $limits = $this->getTierLimits($tier);
        
        // Check rate limits
        foreach ($limits as $window => $limit) {
            if ($limit === null) {
                continue; // Unlimited
            }
            
            $result = $this->rateLimiter->attempt(
                "{$key}:{$window}",
                $limit,
                $this->getWindowDuration($window)
            );
            
            if (!$result->allowed) {
                $this->logger->warning('API rate limit exceeded', [
                    'api_key_id' => $apiKeyId,
                    'tier' => $tier,
                    'window' => $window,
                    'limit' => $limit,
                    'path' => $request->path()
                ]);
                
                return $this->rateLimitResponse($result, $window);
            }
        }
        
        // Process request
        $response = $next($request);
        
        // Add rate limit headers
        $this->addRateLimitHeaders($response, $key, $limits);
        
        return $response;
    }

    /**
     * Build rate limit key
     *
     * @param Request $request
     * @param int|null $apiKeyId
     * @return string
     */
    protected function buildKey(Request $request, ?int $apiKeyId): string
    {
        if ($apiKeyId) {
            return "api_key:{$apiKeyId}";
        }
        
        // Fall back to IP for unauthenticated requests
        return "api_ip:" . $request->ip();
    }

    /**
     * Get tier limits
     *
     * @param string $tier
     * @return array
     */
    protected function getTierLimits(string $tier): array
    {
        $limits = [
            'free' => [
                'minute' => 60,
                'hour' => 1000,
                'day' => 10000
            ],
            'starter' => [
                'minute' => 300,
                'hour' => 10000,
                'day' => 100000
            ],
            'growth' => [
                'minute' => 1000,
                'hour' => 50000,
                'day' => 500000
            ],
            'scale' => [
                'minute' => 5000,
                'hour' => 200000,
                'day' => 2000000
            ],
            'enterprise' => [
                'minute' => null,
                'hour' => null,
                'day' => null
            ]
        ];
        
        return $limits[$tier] ?? $limits['free'];
    }

    /**
     * Get window duration in seconds
     *
     * @param string $window
     * @return int
     */
    protected function getWindowDuration(string $window): int
    {
        $durations = [
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400
        ];
        
        return $durations[$window] ?? 60;
    }

    /**
     * Create rate limit exceeded response
     *
     * @param object $result
     * @param string $window
     * @return Response
     */
    protected function rateLimitResponse(object $result, string $window): Response
    {
        $retryAfter = $result->availableAt - time();
        
        return new Response([
            'error' => [
                'type' => 'rate_limit_exceeded',
                'message' => "API rate limit exceeded for {$window} window",
                'retry_after' => $retryAfter
            ]
        ], 429, [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $result->limit,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => $result->availableAt
        ]);
    }

    /**
     * Add rate limit headers to response
     *
     * @param Response $response
     * @param string $key
     * @param array $limits
     * @return void
     */
    protected function addRateLimitHeaders(Response $response, string $key, array $limits): void
    {
        // Use the most restrictive window for headers
        $window = 'minute';
        $limit = $limits[$window] ?? 1000;
        
        if ($limit === null) {
            // Unlimited tier
            $response->headers->set('X-RateLimit-Limit', 'unlimited');
            $response->headers->set('X-RateLimit-Remaining', 'unlimited');
            return;
        }
        
        $status = $this->rateLimiter->getStatus(
            "{$key}:{$window}",
            $limit,
            $this->getWindowDuration($window)
        );
        
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $status->remaining);
        $response->headers->set('X-RateLimit-Reset', (string) $status->resetsAt);
    }
}