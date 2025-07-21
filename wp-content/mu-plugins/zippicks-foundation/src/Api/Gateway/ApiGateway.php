<?php
/**
 * ZipPicks API Gateway
 * 
 * Enterprise-grade API gateway for the $100B Taste Layer of the Internet
 * Handles 10M+ requests/day with <50ms latency
 *
 * @package ZipPicks\Foundation\Api\Gateway
 */

namespace ZipPicks\Foundation\Api\Gateway;

use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Middleware\Pipeline;
use ZipPicks\Foundation\Api\Exceptions\ApiException;
use ZipPicks\Foundation\Api\Exceptions\ApiVersionException;
use ZipPicks\Foundation\Logging\Logger;
use ZipPicks\Foundation\Cache\CacheManager;

class ApiGateway
{
    /**
     * The service container
     *
     * @var Container
     */
    protected Container $container;

    /**
     * The API router
     *
     * @var Router
     */
    protected Router $router;

    /**
     * Version manager
     *
     * @var VersionManager
     */
    protected VersionManager $versions;

    /**
     * Response transformer
     *
     * @var ResponseTransformer
     */
    protected ResponseTransformer $transformer;

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected Logger $logger;

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected CacheManager $cache;

    /**
     * API middleware stack
     *
     * @var array
     */
    protected array $middleware = [
        'api.auth',
        'api.rate_limit',
        'api.version',
        'api.cache',
        'api.validation',
        'api.cors',
        'api.compression'
    ];

    /**
     * Create a new API gateway instance
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->router = $container->make(Router::class);
        $this->versions = $container->make(VersionManager::class);
        $this->transformer = $container->make(ResponseTransformer::class);
        $this->logger = $container->make(Logger::class);
        $this->cache = $container->make(CacheManager::class);
    }

    /**
     * Handle an incoming API request
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $startTime = microtime(true);
        $traceId = $this->generateTraceId();
        
        try {
            // Add trace ID to request
            $request->attributes->set('trace_id', $traceId);
            
            // Log incoming request
            $this->logRequest($request, $traceId);
            
            // Detect API version
            $version = $this->versions->detect($request);
            if (!$version) {
                throw new ApiVersionException('Invalid or missing API version');
            }
            
            // Add version to request context
            $request->attributes->set('api_version', $version);
            
            // Match route
            $route = $this->router->match($request, $version);
            if (!$route) {
                return $this->handleNotFound($request, $version);
            }
            
            // Build middleware pipeline
            $pipeline = $this->buildPipeline($request);
            
            // Execute request through middleware and route handler
            $response = $pipeline->send($request)->through($this->middleware)->then(function ($request) use ($route) {
                return $route->dispatch($request);
            });
            
            // Transform response based on version and content type
            $response = $this->transformer->transform($response, $version, $request);
            
            // Add standard headers
            $response = $this->addStandardHeaders($response, $traceId, $version);
            
            // Log response
            $this->logResponse($request, $response, $traceId, microtime(true) - $startTime);
            
            return $response;
            
        } catch (ApiException $e) {
            return $this->handleApiException($e, $request, $traceId, $version ?? 'v1');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $traceId, $version ?? 'v1');
        }
    }

    /**
     * Build the middleware pipeline
     *
     * @param Request $request
     * @return Pipeline
     */
    protected function buildPipeline(Request $request): Pipeline
    {
        $pipeline = new Pipeline($this->container);
        
        // Add dynamic middleware based on request
        if ($request->headers->has('X-API-Key')) {
            array_unshift($this->middleware, 'api.key_auth');
        }
        
        if ($request->headers->has('Authorization')) {
            array_unshift($this->middleware, 'api.oauth');
        }
        
        return $pipeline;
    }

    /**
     * Handle route not found
     *
     * @param Request $request
     * @param string $version
     * @return Response
     */
    protected function handleNotFound(Request $request, string $version): Response
    {
        return $this->transformer->error(
            'Resource not found',
            404,
            ['path' => $request->path(), 'method' => $request->method()],
            $version
        );
    }

    /**
     * Handle API exceptions
     *
     * @param ApiException $e
     * @param Request $request
     * @param string $traceId
     * @param string $version
     * @return Response
     */
    protected function handleApiException(ApiException $e, Request $request, string $traceId, string $version): Response
    {
        $this->logger->warning('API exception', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'trace_id' => $traceId,
            'path' => $request->path(),
            'method' => $request->method()
        ]);
        
        return $this->transformer->error(
            $e->getMessage(),
            $e->getCode() ?: 400,
            $e->getContext(),
            $version
        );
    }

    /**
     * Handle general exceptions
     *
     * @param \Exception $e
     * @param Request $request
     * @param string $traceId
     * @param string $version
     * @return Response
     */
    protected function handleException(\Exception $e, Request $request, string $traceId, string $version): Response
    {
        $this->logger->error('Unhandled API exception', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'trace_id' => $traceId,
            'path' => $request->path(),
            'method' => $request->method()
        ]);
        
        // Don't expose internal errors in production
        $message = $this->container->get('app.debug') ? $e->getMessage() : 'Internal server error';
        
        return $this->transformer->error($message, 500, ['trace_id' => $traceId], $version);
    }

    /**
     * Add standard API headers
     *
     * @param Response $response
     * @param string $traceId
     * @param string $version
     * @return Response
     */
    protected function addStandardHeaders(Response $response, string $traceId, string $version): Response
    {
        return $response
            ->header('X-API-Version', $version)
            ->header('X-Trace-ID', $traceId)
            ->header('X-RateLimit-Limit', $response->headers->get('X-RateLimit-Limit', '1000'))
            ->header('X-RateLimit-Remaining', $response->headers->get('X-RateLimit-Remaining', '999'))
            ->header('X-RateLimit-Reset', $response->headers->get('X-RateLimit-Reset', time() + 3600))
            ->header('X-Response-Time', round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000) . 'ms')
            ->header('X-Powered-By', 'ZipPicks API ' . $version);
    }

    /**
     * Generate a unique trace ID
     *
     * @return string
     */
    protected function generateTraceId(): string
    {
        return sprintf(
            '%s-%s',
            bin2hex(random_bytes(8)),
            substr(md5(uniqid('', true)), 0, 8)
        );
    }

    /**
     * Log incoming request
     *
     * @param Request $request
     * @param string $traceId
     */
    protected function logRequest(Request $request, string $traceId): void
    {
        $this->logger->info('API request', [
            'trace_id' => $traceId,
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => $request->query->all(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
    }

    /**
     * Log outgoing response
     *
     * @param Request $request
     * @param Response $response
     * @param string $traceId
     * @param float $duration
     */
    protected function logResponse(Request $request, Response $response, string $traceId, float $duration): void
    {
        $this->logger->info('API response', [
            'trace_id' => $traceId,
            'status' => $response->status(),
            'duration_ms' => round($duration * 1000, 2),
            'path' => $request->path(),
            'method' => $request->method(),
            'cache_hit' => $response->headers->has('X-Cache') ? $response->headers->get('X-Cache') : 'MISS'
        ]);
    }

    /**
     * Sanitize headers for logging
     *
     * @param array $headers
     * @return array
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'x-api-key', 'cookie'];
        
        foreach ($sensitive as $key) {
            if (isset($headers[$key])) {
                $headers[$key] = '[REDACTED]';
            }
        }
        
        return $headers;
    }

    /**
     * Register API routes
     *
     * @param string $version
     * @param callable $routes
     * @return void
     */
    public function version(string $version, callable $routes): void
    {
        $this->router->group(['prefix' => "/api/{$version}"], $routes);
    }

    /**
     * Get the router instance
     *
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get the version manager
     *
     * @return VersionManager
     */
    public function getVersionManager(): VersionManager
    {
        return $this->versions;
    }
}