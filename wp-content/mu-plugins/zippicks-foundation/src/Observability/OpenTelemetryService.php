<?php
/**
 * OpenTelemetry Service for Distributed Tracing
 * 
 * Enterprise-grade observability for the $100B ZipPicks platform
 * 
 * @package ZipPicks\Foundation\Observability
 * @since 2.0.0
 */

namespace ZipPicks\Foundation\Observability;

use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Core\EnvironmentManager;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter as OtlpSpanExporter;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;

class OpenTelemetryService
{
    /**
     * @var TracerProvider
     */
    protected TracerProvider $tracerProvider;
    
    /**
     * @var EnvironmentManager
     */
    protected EnvironmentManager $env;
    
    /**
     * @var array Active spans
     */
    protected array $activeSpans = [];
    
    /**
     * @var bool Service initialized
     */
    protected bool $initialized = false;
    
    /**
     * @var array Service configuration
     */
    protected array $config;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->env = Foundation::getInstance()->getContainer()->get('env');
        $this->config = $this->env->get('monitoring.opentelemetry', []);
        
        if ($this->config['enabled'] ?? false) {
            $this->initialize();
        }
    }
    
    /**
     * Initialize OpenTelemetry
     */
    protected function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        
        try {
            // Create resource info
            $resource = ResourceInfoFactory::defaultResource()->merge(
                ResourceInfo::create(Attributes::create([
                    'service.name' => 'zippicks-foundation',
                    'service.version' => ZIPPICKS_FOUNDATION_VERSION ?? '2.0.0',
                    'service.environment' => $this->env->getEnvironment(),
                    'service.namespace' => 'zippicks',
                    'deployment.environment' => $this->env->getEnvironment(),
                    'telemetry.sdk.name' => 'opentelemetry-php',
                    'telemetry.sdk.language' => 'php',
                    'telemetry.sdk.version' => '1.0.0'
                ]))
            );
            
            // Create span exporter
            $exporter = $this->createSpanExporter();
            
            // Create batch span processor
            $spanProcessor = new BatchSpanProcessor(
                $exporter,
                null,
                $this->config['traces']['batch_size'] ?? 512,
                $this->config['traces']['export_timeout'] ?? 30000,
                5000, // Schedule delay
                2048  // Max queue size
            );
            
            // Create sampler
            $sampler = $this->createSampler();
            
            // Create tracer provider
            $this->tracerProvider = new TracerProvider(
                [$spanProcessor],
                $sampler,
                $resource
            );
            
            // Register shutdown handler
            register_shutdown_function([$this, 'shutdown']);
            
            $this->initialized = true;
            
        } catch (\Exception $e) {
            error_log('OpenTelemetry initialization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Create span exporter based on configuration
     * 
     * @return mixed
     */
    protected function createSpanExporter()
    {
        if ($this->env->isDevelopment()) {
            return new ConsoleSpanExporter();
        }
        
        $endpoint = $this->config['endpoint'] ?? null;
        if (!$endpoint) {
            throw new \Exception('OpenTelemetry endpoint not configured');
        }
        
        $headers = $this->config['headers'] ?? [];
        
        $transport = (new OtlpHttpTransportFactory())->create(
            $endpoint . '/v1/traces',
            'application/x-protobuf',
            $headers
        );
        
        return new OtlpSpanExporter($transport);
    }
    
    /**
     * Create sampler based on configuration
     * 
     * @return mixed
     */
    protected function createSampler()
    {
        $sampleRate = $this->config['traces']['sample_rate'] ?? 1.0;
        
        if ($sampleRate >= 1.0) {
            return new AlwaysOnSampler();
        }
        
        return new TraceIdRatioBasedSampler($sampleRate);
    }
    
    /**
     * Start a new span
     * 
     * @param string $name Span name
     * @param array $attributes Span attributes
     * @param int $kind Span kind
     * @return SpanInterface|null
     */
    public function startSpan(string $name, array $attributes = [], int $kind = SpanKind::KIND_INTERNAL): ?SpanInterface
    {
        if (!$this->initialized) {
            return null;
        }
        
        try {
            $tracer = $this->tracerProvider->getTracer(
                'zippicks-foundation',
                ZIPPICKS_FOUNDATION_VERSION ?? '2.0.0'
            );
            
            $spanBuilder = $tracer->spanBuilder($name)
                ->setSpanKind($kind);
            
            // Add attributes
            foreach ($attributes as $key => $value) {
                $spanBuilder->setAttribute($key, $value);
            }
            
            // Add default attributes
            $spanBuilder->setAttribute('environment', $this->env->getEnvironment());
            $spanBuilder->setAttribute('php.version', PHP_VERSION);
            
            // Start span
            $span = $spanBuilder->startSpan();
            
            // Make span active
            $scope = $span->activate();
            
            // Store active span
            $this->activeSpans[$name] = [
                'span' => $span,
                'scope' => $scope,
                'start_time' => microtime(true)
            ];
            
            return $span;
            
        } catch (\Exception $e) {
            error_log('Failed to start span: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * End a span
     * 
     * @param string $name Span name
     * @param int $status Status code
     * @param string|null $description Status description
     */
    public function endSpan(string $name, int $status = StatusCode::STATUS_OK, ?string $description = null): void
    {
        if (!isset($this->activeSpans[$name])) {
            return;
        }
        
        $spanData = $this->activeSpans[$name];
        $span = $spanData['span'];
        $scope = $spanData['scope'];
        
        // Set status
        $span->setStatus($status, $description);
        
        // Add duration
        $duration = (microtime(true) - $spanData['start_time']) * 1000;
        $span->setAttribute('duration_ms', $duration);
        
        // End span
        $span->end();
        
        // Detach scope
        $scope->detach();
        
        // Remove from active spans
        unset($this->activeSpans[$name]);
    }
    
    /**
     * Record an exception in the current span
     * 
     * @param \Throwable $exception
     * @param array $attributes Additional attributes
     */
    public function recordException(\Throwable $exception, array $attributes = []): void
    {
        $span = $this->getCurrentSpan();
        if (!$span) {
            return;
        }
        
        $span->recordException($exception, $attributes);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
    }
    
    /**
     * Add event to current span
     * 
     * @param string $name Event name
     * @param array $attributes Event attributes
     */
    public function addEvent(string $name, array $attributes = []): void
    {
        $span = $this->getCurrentSpan();
        if (!$span) {
            return;
        }
        
        $span->addEvent($name, $attributes);
    }
    
    /**
     * Set attribute on current span
     * 
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     */
    public function setAttribute(string $key, $value): void
    {
        $span = $this->getCurrentSpan();
        if (!$span) {
            return;
        }
        
        $span->setAttribute($key, $value);
    }
    
    /**
     * Get current active span
     * 
     * @return SpanInterface|null
     */
    public function getCurrentSpan(): ?SpanInterface
    {
        $context = Context::getCurrent();
        return $context->get('span');
    }
    
    /**
     * Trace HTTP request
     * 
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param callable $callback Request callback
     * @return mixed
     */
    public function traceHttpRequest(string $method, string $url, callable $callback)
    {
        $parsedUrl = parse_url($url);
        $spanName = sprintf('HTTP %s %s', $method, $parsedUrl['path'] ?? '/');
        
        $span = $this->startSpan($spanName, [
            'http.method' => $method,
            'http.url' => $url,
            'http.scheme' => $parsedUrl['scheme'] ?? 'http',
            'http.host' => $parsedUrl['host'] ?? 'unknown',
            'http.target' => $parsedUrl['path'] ?? '/',
            'net.peer.name' => $parsedUrl['host'] ?? 'unknown',
            'net.peer.port' => $parsedUrl['port'] ?? ($parsedUrl['scheme'] === 'https' ? 443 : 80)
        ], SpanKind::KIND_CLIENT);
        
        if (!$span) {
            return $callback();
        }
        
        try {
            $result = $callback();
            
            // Add response attributes if available
            if (is_array($result) && isset($result['status_code'])) {
                $span->setAttribute('http.status_code', $result['status_code']);
                
                if ($result['status_code'] >= 400) {
                    $span->setStatus(StatusCode::STATUS_ERROR, 'HTTP ' . $result['status_code']);
                }
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->recordException($e);
            throw $e;
        } finally {
            $this->endSpan($spanName);
        }
    }
    
    /**
     * Trace database query
     * 
     * @param string $query SQL query
     * @param callable $callback Query callback
     * @return mixed
     */
    public function traceDatabaseQuery(string $query, callable $callback)
    {
        // Extract operation from query
        $operation = strtoupper(strtok($query, ' '));
        $spanName = 'DB ' . $operation;
        
        $span = $this->startSpan($spanName, [
            'db.system' => 'mysql',
            'db.operation' => $operation,
            'db.statement' => $this->sanitizeQuery($query)
        ], SpanKind::KIND_CLIENT);
        
        if (!$span) {
            return $callback();
        }
        
        try {
            $startTime = microtime(true);
            $result = $callback();
            $duration = (microtime(true) - $startTime) * 1000;
            
            $span->setAttribute('db.duration_ms', $duration);
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->recordException($e);
            throw $e;
        } finally {
            $this->endSpan($spanName);
        }
    }
    
    /**
     * Trace cache operation
     * 
     * @param string $operation Cache operation (get, set, delete)
     * @param string $key Cache key
     * @param callable $callback Operation callback
     * @return mixed
     */
    public function traceCacheOperation(string $operation, string $key, callable $callback)
    {
        $spanName = sprintf('Cache %s', $operation);
        
        $span = $this->startSpan($spanName, [
            'cache.operation' => $operation,
            'cache.key' => $this->sanitizeCacheKey($key),
            'cache.system' => $this->env->get('cache.default', 'unknown')
        ], SpanKind::KIND_CLIENT);
        
        if (!$span) {
            return $callback();
        }
        
        try {
            $result = $callback();
            
            // Add hit/miss for get operations
            if ($operation === 'get') {
                $span->setAttribute('cache.hit', $result !== null);
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->recordException($e);
            throw $e;
        } finally {
            $this->endSpan($spanName);
        }
    }
    
    /**
     * Trace queue job
     * 
     * @param string $jobClass Job class name
     * @param callable $callback Job callback
     * @return mixed
     */
    public function traceQueueJob(string $jobClass, callable $callback)
    {
        $spanName = sprintf('Queue Job %s', basename(str_replace('\\', '/', $jobClass)));
        
        $span = $this->startSpan($spanName, [
            'queue.job_class' => $jobClass,
            'queue.connection' => $this->env->get('queue.default', 'unknown')
        ], SpanKind::KIND_CONSUMER);
        
        if (!$span) {
            return $callback();
        }
        
        try {
            $result = $callback();
            return $result;
            
        } catch (\Throwable $e) {
            $this->recordException($e);
            throw $e;
        } finally {
            $this->endSpan($spanName);
        }
    }
    
    /**
     * Create WordPress action/filter tracer
     * 
     * @param string $hook Hook name
     * @param callable $callback Hook callback
     * @return callable
     */
    public function traceWordPressHook(string $hook, callable $callback): callable
    {
        return function(...$args) use ($hook, $callback) {
            $spanName = sprintf('WP Hook %s', $hook);
            
            $span = $this->startSpan($spanName, [
                'wp.hook' => $hook,
                'wp.type' => current_filter() === $hook ? 'action' : 'filter',
                'wp.priority' => has_filter($hook, $callback) ?: 10
            ]);
            
            if (!$span) {
                return $callback(...$args);
            }
            
            try {
                $result = $callback(...$args);
                return $result;
                
            } catch (\Throwable $e) {
                $this->recordException($e);
                throw $e;
            } finally {
                $this->endSpan($spanName);
            }
        };
    }
    
    /**
     * Sanitize SQL query for telemetry
     * 
     * @param string $query
     * @return string
     */
    protected function sanitizeQuery(string $query): string
    {
        // Remove specific values but keep structure
        $query = preg_replace('/\b\d+\b/', '?', $query);
        $query = preg_replace("/'[^']*'/", '?', $query);
        $query = preg_replace('/"[^"]*"/', '?', $query);
        
        // Limit length
        if (strlen($query) > 1000) {
            $query = substr($query, 0, 1000) . '...';
        }
        
        return $query;
    }
    
    /**
     * Sanitize cache key for telemetry
     * 
     * @param string $key
     * @return string
     */
    protected function sanitizeCacheKey(string $key): string
    {
        // Remove potentially sensitive data
        if (strlen($key) > 100) {
            return substr($key, 0, 50) . '...' . substr($key, -47);
        }
        
        return $key;
    }
    
    /**
     * Shutdown handler
     */
    public function shutdown(): void
    {
        // End any remaining spans
        foreach ($this->activeSpans as $name => $spanData) {
            $this->endSpan($name, StatusCode::STATUS_ERROR, 'Span not properly closed');
        }
        
        // Flush remaining spans
        if ($this->initialized && $this->tracerProvider) {
            $this->tracerProvider->shutdown();
        }
    }
    
    /**
     * Check if service is enabled
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->initialized;
    }
}