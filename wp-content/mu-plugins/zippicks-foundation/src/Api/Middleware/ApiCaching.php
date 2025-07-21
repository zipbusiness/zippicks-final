<?php
/**
 * ZipPicks API Caching Middleware
 * 
 * Handles response caching for improved performance
 *
 * @package ZipPicks\Foundation\Api\Middleware
 */

namespace ZipPicks\Foundation\Api\Middleware;

use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Middleware\Middleware;
use ZipPicks\Foundation\Cache\CacheManager;
use ZipPicks\Foundation\Logging\Logger;

class ApiCaching extends Middleware
{
    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected CacheManager $cache;

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected Logger $logger;

    /**
     * Default cache duration (seconds)
     *
     * @var int
     */
    protected int $defaultTTL = 300; // 5 minutes

    /**
     * Cacheable methods
     *
     * @var array
     */
    protected array $cacheableMethods = ['GET', 'HEAD'];

    /**
     * Create new caching middleware
     *
     * @param CacheManager $cache
     * @param Logger $logger
     */
    public function __construct(CacheManager $cache, Logger $logger)
    {
        $this->cache = $cache;
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
        // Only cache safe methods
        if (!$this->isCacheable($request)) {
            return $next($request);
        }
        
        // Generate cache key
        $cacheKey = $this->generateCacheKey($request);
        
        // Check cache
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            $this->logger->debug('API cache hit', [
                'path' => $request->path(),
                'key' => $cacheKey
            ]);
            
            // Reconstruct response from cache
            $response = new Response(
                $cached['content'],
                $cached['status'],
                $cached['headers']
            );
            
            // Add cache headers
            $response->headers->set('X-Cache', 'HIT');
            $response->headers->set('X-Cache-Key', substr($cacheKey, 0, 16));
            
            return $response;
        }
        
        // Process request
        $response = $next($request);
        
        // Cache successful responses
        if ($this->shouldCache($request, $response)) {
            $ttl = $this->getCacheTTL($request, $response);
            
            $this->cache->put($cacheKey, [
                'content' => $response->getContent(),
                'status' => $response->status(),
                'headers' => $response->headers->all()
            ], $ttl);
            
            $this->logger->debug('API response cached', [
                'path' => $request->path(),
                'key' => $cacheKey,
                'ttl' => $ttl
            ]);
            
            // Add cache headers
            $response->headers->set('X-Cache', 'MISS');
            $response->headers->set('X-Cache-TTL', (string) $ttl);
            $response->headers->set('Cache-Control', "public, max-age={$ttl}");
        }
        
        return $response;
    }

    /**
     * Check if request is cacheable
     *
     * @param Request $request
     * @return bool
     */
    protected function isCacheable(Request $request): bool
    {
        // Check method
        if (!in_array($request->method(), $this->cacheableMethods)) {
            return false;
        }
        
        // Don't cache if no-cache header is present
        if ($request->headers->get('Cache-Control') === 'no-cache') {
            return false;
        }
        
        // Don't cache authenticated requests by default
        if ($request->attributes->has('user_id')) {
            // Unless it's a public endpoint
            $publicEndpoints = [
                '/api/v1/businesses',
                '/api/v1/vibes',
                '/api/v1/search'
            ];
            
            $path = $request->path();
            $isPublic = false;
            
            foreach ($publicEndpoints as $endpoint) {
                if (str_starts_with($path, $endpoint)) {
                    $isPublic = true;
                    break;
                }
            }
            
            if (!$isPublic) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check if response should be cached
     *
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    protected function shouldCache(Request $request, Response $response): bool
    {
        // Only cache successful responses
        if ($response->status() !== 200) {
            return false;
        }
        
        // Don't cache if explicitly disabled
        if ($response->headers->get('Cache-Control') === 'no-cache') {
            return false;
        }
        
        return true;
    }

    /**
     * Generate cache key
     *
     * @param Request $request
     * @return string
     */
    protected function generateCacheKey(Request $request): string
    {
        $parts = [
            'api_cache',
            $request->attributes->get('api_version', 'v1'),
            $request->method(),
            $request->path(),
            md5(json_encode($request->query->all()))
        ];
        
        // Include tier for tier-specific caching
        if ($tier = $request->attributes->get('api_tier')) {
            $parts[] = $tier;
        }
        
        return implode(':', $parts);
    }

    /**
     * Get cache TTL
     *
     * @param Request $request
     * @param Response $response
     * @return int
     */
    protected function getCacheTTL(Request $request, Response $response): int
    {
        // Check response headers
        if ($maxAge = $response->headers->get('Cache-Control')) {
            if (preg_match('/max-age=(\d+)/', $maxAge, $matches)) {
                return (int) $matches[1];
            }
        }
        
        // Endpoint-specific TTLs
        $path = $request->path();
        
        $ttls = [
            '/api/v1/businesses' => 600,    // 10 minutes
            '/api/v1/vibes' => 3600,        // 1 hour
            '/api/v1/search' => 300,        // 5 minutes
            '/api/v1/taste-graph' => 60,    // 1 minute (personalized)
        ];
        
        foreach ($ttls as $endpoint => $ttl) {
            if (str_starts_with($path, $endpoint)) {
                return $ttl;
            }
        }
        
        return $this->defaultTTL;
    }
}