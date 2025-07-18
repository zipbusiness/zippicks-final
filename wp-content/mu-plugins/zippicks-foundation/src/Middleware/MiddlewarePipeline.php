<?php
/**
 * Middleware Pipeline
 * 
 * @package ZipPicks\Foundation\Middleware
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Middleware;

use Closure;
use Exception;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Contracts\Middleware\MiddlewareInterface;
use ZipPicks\Foundation\Contracts\Middleware\RequestInterface;

class MiddlewarePipeline
{
    /**
     * Container instance
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Logger instance
     *
     * @var ?LoggerInterface
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Global middleware stack
     *
     * @var array<string|Closure>
     */
    protected array $globalMiddleware = [];

    /**
     * Route-specific middleware groups
     *
     * @var array<string, array<string|Closure>>
     */
    protected array $routeMiddleware = [];

    /**
     * Middleware groups
     *
     * @var array<string, array<string|Closure>>
     */
    protected array $middlewareGroups = [];

    /**
     * Create a new middleware pipeline
     *
     * @param ContainerInterface $container
     * @param ?LoggerInterface $logger
     */
    public function __construct(ContainerInterface $container, ?LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * Register global middleware
     *
     * @param array<string|Closure> $middleware
     * @return self
     */
    public function global(array $middleware): self
    {
        $this->globalMiddleware = array_merge($this->globalMiddleware, $middleware);
        return $this;
    }

    /**
     * Register route-specific middleware
     *
     * @param string $route
     * @param array<string|Closure> $middleware
     * @return self
     */
    public function route(string $route, array $middleware): self
    {
        $this->routeMiddleware[$route] = $middleware;
        return $this;
    }

    /**
     * Register a middleware group
     *
     * @param string $name
     * @param array<string|Closure> $middleware
     * @return self
     */
    public function group(string $name, array $middleware): self
    {
        $this->middlewareGroups[$name] = $middleware;
        return $this;
    }

    /**
     * Process a request through the middleware pipeline
     *
     * @param RequestInterface $request
     * @param Closure $destination
     * @param array<string|Closure> $middleware
     * @return mixed
     * @throws Exception
     */
    public function process(RequestInterface $request, Closure $destination, array $middleware = []): mixed
    {
        $this->logDebug('Starting middleware pipeline', [
            'uri' => $request->getUri(),
            'method' => $request->getMethod(),
            'middleware_count' => count($middleware)
        ]);

        // Merge global middleware with provided middleware
        $pipeline = array_merge($this->globalMiddleware, $middleware);
        
        // Expand middleware groups
        $pipeline = $this->expandMiddlewareGroups($pipeline);
        
        // Build the onion from the inside out
        $callback = $destination;
        
        foreach (array_reverse($pipeline) as $pipe) {
            $callback = $this->createLayer($pipe, $callback);
        }
        
        try {
            $result = $callback($request);
            $this->logDebug('Middleware pipeline completed successfully');
            return $result;
        } catch (Exception $e) {
            $this->logError('Exception in middleware pipeline', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Process a route through its middleware
     *
     * @param string $route
     * @param RequestInterface $request
     * @param Closure $destination
     * @return mixed
     * @throws Exception
     */
    public function processRoute(string $route, RequestInterface $request, Closure $destination): mixed
    {
        $middleware = $this->routeMiddleware[$route] ?? [];
        return $this->process($request, $destination, $middleware);
    }

    /**
     * Create a middleware layer
     *
     * @param string|Closure $pipe
     * @param Closure $next
     * @return Closure
     */
    protected function createLayer(string|Closure $pipe, Closure $next): Closure
    {
        return function (RequestInterface $request) use ($pipe, $next) {
            if ($pipe instanceof Closure) {
                $this->logDebug('Executing closure middleware');
                return $pipe($request, $next);
            }
            
            try {
                $middleware = $this->resolveMiddleware($pipe);
                $this->logDebug('Executing middleware', ['class' => $pipe]);
                return $middleware->handle($request, $next);
            } catch (Exception $e) {
                $this->logError('Failed to resolve middleware', [
                    'class' => $pipe,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        };
    }

    /**
     * Resolve middleware from container
     *
     * @param string $class
     * @return MiddlewareInterface
     * @throws Exception
     */
    protected function resolveMiddleware(string $class): MiddlewareInterface
    {
        if ($this->container->has($class)) {
            $middleware = $this->container->get($class);
        } else {
            $middleware = new $class();
        }
        
        if (!$middleware instanceof MiddlewareInterface) {
            throw new Exception(sprintf(
                'Middleware %s must implement %s',
                $class,
                MiddlewareInterface::class
            ));
        }
        
        return $middleware;
    }

    /**
     * Expand middleware groups in the pipeline
     *
     * @param array<string|Closure> $pipeline
     * @return array<string|Closure>
     */
    protected function expandMiddlewareGroups(array $pipeline): array
    {
        $expanded = [];
        
        foreach ($pipeline as $pipe) {
            if (is_string($pipe) && isset($this->middlewareGroups[$pipe])) {
                $expanded = array_merge($expanded, $this->middlewareGroups[$pipe]);
            } else {
                $expanded[] = $pipe;
            }
        }
        
        return $expanded;
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->debug('[MiddlewarePipeline] ' . $message, $context);
        }
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error('[MiddlewarePipeline] ' . $message, $context);
        }
    }
}