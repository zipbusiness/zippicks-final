<?php
/**
 * Tracing Middleware for Automatic Instrumentation
 * 
 * @package ZipPicks\Foundation\Observability
 * @since 2.0.0
 */

namespace ZipPicks\Foundation\Observability;

use ZipPicks\Foundation\Contracts\Middleware\MiddlewareInterface;
use ZipPicks\Foundation\Contracts\Http\RequestInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Core\Foundation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

class TracingMiddleware implements MiddlewareInterface
{
    /**
     * @var OpenTelemetryService
     */
    protected OpenTelemetryService $telemetry;
    
    /**
     * @var array Request attributes to capture
     */
    protected array $requestAttributes = [
        'http.method',
        'http.url',
        'http.target',
        'http.host',
        'http.scheme',
        'http.user_agent',
        'http.client_ip',
        'http.request_content_length'
    ];
    
    /**
     * @var array Response attributes to capture
     */
    protected array $responseAttributes = [
        'http.status_code',
        'http.response_content_length'
    ];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $container = Foundation::getInstance()->getContainer();
        
        if ($container->has('telemetry')) {
            $this->telemetry = $container->get('telemetry');
        } else {
            $this->telemetry = new OpenTelemetryService();
        }
    }
    
    /**
     * Handle the request
     * 
     * @param RequestInterface $request
     * @param \Closure $next
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        if (!$this->telemetry->isEnabled()) {
            return $next($request);
        }
        
        // Generate span name
        $spanName = $this->generateSpanName($request);
        
        // Extract request attributes
        $attributes = $this->extractRequestAttributes($request);
        
        // Start span
        $span = $this->telemetry->startSpan($spanName, $attributes, SpanKind::KIND_SERVER);
        
        if (!$span) {
            return $next($request);
        }
        
        try {
            // Add request ID if available
            if ($requestId = $request->getHeader('X-Request-ID')) {
                $span->setAttribute('http.request_id', $requestId);
            }
            
            // Record request received event
            $this->telemetry->addEvent('request.received', [
                'timestamp' => microtime(true)
            ]);
            
            // Process request
            $response = $next($request);
            
            // Extract response attributes
            $this->extractResponseAttributes($response, $span);
            
            // Record response sent event
            $this->telemetry->addEvent('response.sent', [
                'timestamp' => microtime(true)
            ]);
            
            // Determine status based on response code
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $span->setStatus(StatusCode::STATUS_ERROR, 'HTTP ' . $statusCode);
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }
            
            return $response;
            
        } catch (\Throwable $e) {
            // Record exception
            $this->telemetry->recordException($e);
            
            // Re-throw exception
            throw $e;
            
        } finally {
            // End span
            $this->telemetry->endSpan($spanName);
        }
    }
    
    /**
     * Generate span name from request
     * 
     * @param RequestInterface $request
     * @return string
     */
    protected function generateSpanName(RequestInterface $request): string
    {
        $method = $request->getMethod();
        $path = $request->getUri();
        
        // Try to match route pattern
        if ($route = $this->matchRoute($path)) {
            return sprintf('%s %s', $method, $route);
        }
        
        // Fallback to generic path
        return sprintf('%s %s', $method, $this->normalizePath($path));
    }
    
    /**
     * Extract request attributes
     * 
     * @param RequestInterface $request
     * @return array
     */
    protected function extractRequestAttributes(RequestInterface $request): array
    {
        $attributes = [
            'http.method' => $request->getMethod(),
            'http.url' => $request->getFullUrl(),
            'http.target' => $request->getUri(),
            'http.host' => $request->getHost(),
            'http.scheme' => $request->getScheme()
        ];
        
        // Add user agent
        if ($userAgent = $request->getHeader('User-Agent')) {
            $attributes['http.user_agent'] = $userAgent;
        }
        
        // Add client IP
        if ($clientIp = $this->getClientIp($request)) {
            $attributes['http.client_ip'] = $clientIp;
        }
        
        // Add content length
        if ($contentLength = $request->getHeader('Content-Length')) {
            $attributes['http.request_content_length'] = (int) $contentLength;
        }
        
        // Add WordPress specific attributes
        if (is_user_logged_in()) {
            $attributes['wp.user_id'] = get_current_user_id();
            $attributes['wp.user_role'] = implode(',', wp_get_current_user()->roles);
        }
        
        // Add API key info if present
        if ($apiKey = $request->getHeader('X-API-Key')) {
            $attributes['api.key_prefix'] = substr($apiKey, 0, 8) . '...';
        }
        
        return $attributes;
    }
    
    /**
     * Extract response attributes
     * 
     * @param ResponseInterface $response
     * @param mixed $span
     */
    protected function extractResponseAttributes(ResponseInterface $response, $span): void
    {
        $span->setAttribute('http.status_code', $response->getStatusCode());
        
        // Add content length if available
        if ($contentLength = $response->getHeader('Content-Length')) {
            $span->setAttribute('http.response_content_length', (int) $contentLength);
        }
        
        // Add content type
        if ($contentType = $response->getHeader('Content-Type')) {
            $span->setAttribute('http.response_content_type', $contentType);
        }
        
        // Add cache status
        if ($cacheStatus = $response->getHeader('X-Cache-Status')) {
            $span->setAttribute('http.cache_status', $cacheStatus);
        }
    }
    
    /**
     * Match route pattern
     * 
     * @param string $path
     * @return string|null
     */
    protected function matchRoute(string $path): ?string
    {
        // Check for API routes
        if (preg_match('#^/api/v\d+/(\w+)(?:/(\d+))?#', $path, $matches)) {
            $resource = $matches[1];
            $hasId = isset($matches[2]);
            
            return $hasId ? "/api/v1/{$resource}/{id}" : "/api/v1/{$resource}";
        }
        
        // Check for WordPress routes
        if (preg_match('#^/wp-json/(\w+)/v\d+/(.+)#', $path, $matches)) {
            return "/wp-json/{$matches[1]}/v1/{route}";
        }
        
        // Check for admin routes
        if (strpos($path, '/wp-admin/') === 0) {
            return '/wp-admin/{page}';
        }
        
        return null;
    }
    
    /**
     * Normalize path for span name
     * 
     * @param string $path
     * @return string
     */
    protected function normalizePath(string $path): string
    {
        // Remove query string
        $path = strtok($path, '?');
        
        // Remove trailing slash
        $path = rtrim($path, '/');
        
        // Default to root if empty
        return $path ?: '/';
    }
    
    /**
     * Get client IP address
     * 
     * @param RequestInterface $request
     * @return string|null
     */
    protected function getClientIp(RequestInterface $request): ?string
    {
        // Check for forwarded IP
        $forwardedFor = $request->getHeader('X-Forwarded-For');
        if ($forwardedFor) {
            $ips = explode(',', $forwardedFor);
            return trim($ips[0]);
        }
        
        // Check for real IP
        if ($realIp = $request->getHeader('X-Real-IP')) {
            return $realIp;
        }
        
        // Fallback to remote address
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}