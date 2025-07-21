<?php
/**
 * ZipPicks API CORS Middleware
 * 
 * Handles Cross-Origin Resource Sharing for API requests
 *
 * @package ZipPicks\Foundation\Api\Middleware
 */

namespace ZipPicks\Foundation\Api\Middleware;

use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Middleware\Middleware;

class ApiCors extends Middleware
{
    /**
     * CORS configuration
     *
     * @var array
     */
    protected array $config;

    /**
     * Default CORS configuration
     *
     * @var array
     */
    protected array $defaults = [
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-API-Key', 'X-API-Version'],
        'exposed_headers' => ['X-API-Version', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'],
        'max_age' => 86400, // 24 hours
        'supports_credentials' => false
    ];

    /**
     * Create new CORS middleware
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaults, $config);
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
        // Handle preflight requests
        if ($this->isPreflightRequest($request)) {
            return $this->handlePreflight($request);
        }
        
        // Process request
        $response = $next($request);
        
        // Add CORS headers to response
        $this->addCorsHeaders($request, $response);
        
        return $response;
    }

    /**
     * Check if request is a preflight request
     *
     * @param Request $request
     * @return bool
     */
    protected function isPreflightRequest(Request $request): bool
    {
        return $request->method() === 'OPTIONS' &&
               $request->headers->has('Access-Control-Request-Method');
    }

    /**
     * Handle preflight request
     *
     * @param Request $request
     * @return Response
     */
    protected function handlePreflight(Request $request): Response
    {
        $response = new Response('', 204);
        
        // Add CORS headers
        $this->addCorsHeaders($request, $response);
        
        // Add preflight-specific headers
        $requestMethod = $request->headers->get('Access-Control-Request-Method');
        if ($this->isMethodAllowed($requestMethod)) {
            $response->headers->set(
                'Access-Control-Allow-Methods',
                implode(', ', $this->config['allowed_methods'])
            );
        }
        
        $requestHeaders = $request->headers->get('Access-Control-Request-Headers');
        if ($requestHeaders) {
            $response->headers->set(
                'Access-Control-Allow-Headers',
                implode(', ', $this->config['allowed_headers'])
            );
        }
        
        $response->headers->set(
            'Access-Control-Max-Age',
            (string) $this->config['max_age']
        );
        
        return $response;
    }

    /**
     * Add CORS headers to response
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    protected function addCorsHeaders(Request $request, Response $response): void
    {
        $origin = $request->headers->get('Origin');
        
        if (!$origin) {
            return;
        }
        
        // Check if origin is allowed
        if ($this->isOriginAllowed($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            
            if ($this->config['supports_credentials']) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
            
            if (!empty($this->config['exposed_headers'])) {
                $response->headers->set(
                    'Access-Control-Expose-Headers',
                    implode(', ', $this->config['exposed_headers'])
                );
            }
        }
    }

    /**
     * Check if origin is allowed
     *
     * @param string $origin
     * @return bool
     */
    protected function isOriginAllowed(string $origin): bool
    {
        // Allow all origins
        if (in_array('*', $this->config['allowed_origins'])) {
            return true;
        }
        
        // Check exact match
        if (in_array($origin, $this->config['allowed_origins'])) {
            return true;
        }
        
        // Check wildcard patterns
        foreach ($this->config['allowed_origins'] as $pattern) {
            if ($this->matchesPattern($origin, $pattern)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if method is allowed
     *
     * @param string $method
     * @return bool
     */
    protected function isMethodAllowed(string $method): bool
    {
        return in_array(strtoupper($method), $this->config['allowed_methods']);
    }

    /**
     * Check if origin matches pattern
     *
     * @param string $origin
     * @param string $pattern
     * @return bool
     */
    protected function matchesPattern(string $origin, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = str_replace(
            ['*', '.'],
            ['.*', '\.'],
            $pattern
        );
        
        return preg_match('/^' . $regex . '$/', $origin) === 1;
    }
}