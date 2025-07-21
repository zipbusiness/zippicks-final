<?php
/**
 * Observability Service Provider
 * 
 * Registers OpenTelemetry and instrumentation services
 * 
 * @package ZipPicks\Foundation\Services
 * @since 2.0.0
 */

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Observability\OpenTelemetryService;
use ZipPicks\Foundation\Observability\TracingMiddleware;
use ZipPicks\Foundation\Observability\InstrumentedDatabase;
use ZipPicks\Foundation\Observability\InstrumentedCache;

class ObservabilityServiceProvider extends ServiceProvider
{
    /**
     * Register services
     * 
     * @return void
     */
    public function register(): void
    {
        // Register OpenTelemetry service
        $this->container->singleton('telemetry', function($container) {
            return new OpenTelemetryService();
        });
        
        // Alias for convenience
        $this->container->alias('telemetry', OpenTelemetryService::class);
        
        // Register tracing middleware
        $this->container->singleton(TracingMiddleware::class, function($container) {
            return new TracingMiddleware();
        });
        
        // Register instrumented database
        $this->container->singleton('observability.database', function($container) {
            return new InstrumentedDatabase();
        });
        
        // Wrap existing cache with instrumentation
        $this->container->extend('cache', function($cache, $container) {
            if ($container->get('env')->get('monitoring.opentelemetry.enabled', false)) {
                return new InstrumentedCache($cache);
            }
            return $cache;
        });
    }
    
    /**
     * Boot services
     * 
     * @return void
     */
    public function boot(): void
    {
        $telemetry = $this->container->get('telemetry');
        
        if (!$telemetry->isEnabled()) {
            return;
        }
        
        // Register tracing middleware globally
        if ($this->container->has('middleware')) {
            $this->container->get('middleware')->add(TracingMiddleware::class);
        }
        
        // Initialize database instrumentation
        $this->container->get('observability.database');
        
        // Register WordPress hooks instrumentation
        $this->instrumentWordPressHooks();
        
        // Register API instrumentation
        $this->instrumentApi();
        
        // Register queue instrumentation
        $this->instrumentQueue();
        
        // Add telemetry headers to responses
        add_action('send_headers', [$this, 'addTelemetryHeaders']);
        
        // Register shutdown handler for trace flushing
        add_action('shutdown', [$telemetry, 'shutdown'], 999);
    }
    
    /**
     * Instrument WordPress hooks
     */
    protected function instrumentWordPressHooks(): void
    {
        $telemetry = $this->container->get('telemetry');
        
        // Instrument critical WordPress actions
        $criticalActions = [
            'init',
            'wp_loaded',
            'template_redirect',
            'wp_head',
            'wp_footer',
            'admin_init',
            'admin_menu',
            'save_post',
            'wp_insert_post',
            'wp_ajax_*'
        ];
        
        foreach ($criticalActions as $action) {
            add_action($action, function() use ($telemetry, $action) {
                $telemetry->addEvent('wordpress.action', [
                    'action' => $action,
                    'priority' => current_priority()
                ]);
            }, 1);
        }
        
        // Instrument slow queries
        add_filter('query', function($query) use ($telemetry) {
            if (strlen($query) > 1000) {
                $telemetry->addEvent('database.slow_query_risk', [
                    'query_length' => strlen($query)
                ]);
            }
            return $query;
        });
        
        // Instrument plugin activation/deactivation
        add_action('activated_plugin', function($plugin) use ($telemetry) {
            $telemetry->addEvent('plugin.activated', ['plugin' => $plugin]);
        });
        
        add_action('deactivated_plugin', function($plugin) use ($telemetry) {
            $telemetry->addEvent('plugin.deactivated', ['plugin' => $plugin]);
        });
    }
    
    /**
     * Instrument API requests
     */
    protected function instrumentApi(): void
    {
        $telemetry = $this->container->get('telemetry');
        
        // Instrument REST API requests
        add_filter('rest_pre_dispatch', function($result, $server, $request) use ($telemetry) {
            $route = $request->get_route();
            $method = $request->get_method();
            
            $telemetry->startSpan("REST API {$method} {$route}", [
                'http.method' => $method,
                'http.route' => $route,
                'api.version' => $this->extractApiVersion($route)
            ]);
            
            return $result;
        }, 10, 3);
        
        // Complete REST API span
        add_filter('rest_post_dispatch', function($response, $server, $request) use ($telemetry) {
            $route = $request->get_route();
            $method = $request->get_method();
            
            if ($response instanceof \WP_REST_Response) {
                $telemetry->setAttribute('http.status_code', $response->get_status());
            }
            
            $telemetry->endSpan("REST API {$method} {$route}");
            
            return $response;
        }, 10, 3);
        
        // Instrument API authentication
        add_filter('rest_authentication_errors', function($errors) use ($telemetry) {
            if (is_wp_error($errors)) {
                $telemetry->addEvent('api.authentication_failed', [
                    'error_code' => $errors->get_error_code(),
                    'error_message' => $errors->get_error_message()
                ]);
            }
            return $errors;
        });
    }
    
    /**
     * Instrument queue operations
     */
    protected function instrumentQueue(): void
    {
        if (!$this->container->has('queue')) {
            return;
        }
        
        $telemetry = $this->container->get('telemetry');
        
        // Instrument job processing
        add_action('zippicks_queue_before_job', function($job) use ($telemetry) {
            $jobClass = get_class($job);
            $telemetry->startSpan("Queue Job {$jobClass}", [
                'queue.job_class' => $jobClass,
                'queue.job_id' => $job->id ?? null
            ]);
        });
        
        add_action('zippicks_queue_after_job', function($job) use ($telemetry) {
            $jobClass = get_class($job);
            $telemetry->endSpan("Queue Job {$jobClass}");
        });
        
        add_action('zippicks_queue_job_failed', function($job, $exception) use ($telemetry) {
            $telemetry->recordException($exception, [
                'queue.job_class' => get_class($job),
                'queue.job_id' => $job->id ?? null
            ]);
        }, 10, 2);
    }
    
    /**
     * Add telemetry headers to HTTP responses
     */
    public function addTelemetryHeaders(): void
    {
        $telemetry = $this->container->get('telemetry');
        $span = $telemetry->getCurrentSpan();
        
        if (!$span) {
            return;
        }
        
        // Add trace ID header for correlation
        $context = $span->getContext();
        $traceId = $context->getTraceId();
        $spanId = $context->getSpanId();
        
        if ($traceId) {
            header('X-Trace-Id: ' . $traceId);
        }
        
        if ($spanId) {
            header('X-Span-Id: ' . $spanId);
        }
        
        // Add server timing header
        $timing = $this->generateServerTimingHeader();
        if ($timing) {
            header('Server-Timing: ' . $timing);
        }
    }
    
    /**
     * Generate server timing header
     * 
     * @return string|null
     */
    protected function generateServerTimingHeader(): ?string
    {
        global $wpdb;
        
        $timings = [];
        
        // Database timing
        if (isset($wpdb->queries)) {
            $dbTime = 0;
            foreach ($wpdb->queries as $query) {
                $dbTime += $query[1] ?? 0;
            }
            $timings[] = sprintf('db;dur=%.1f;desc="Database"', $dbTime * 1000);
        }
        
        // Cache timing (if available)
        if ($this->container->has('cache')) {
            $cache = $this->container->get('cache');
            if (method_exists($cache, 'getStats')) {
                $stats = $cache->getStats();
                if (isset($stats['total_time'])) {
                    $timings[] = sprintf('cache;dur=%.1f;desc="Cache"', $stats['total_time']);
                }
            }
        }
        
        // Total execution time
        if (defined('WP_START_TIMESTAMP')) {
            $total = (microtime(true) - WP_START_TIMESTAMP) * 1000;
            $timings[] = sprintf('total;dur=%.1f;desc="Total"', $total);
        }
        
        return !empty($timings) ? implode(', ', $timings) : null;
    }
    
    /**
     * Extract API version from route
     * 
     * @param string $route
     * @return string|null
     */
    protected function extractApiVersion(string $route): ?string
    {
        if (preg_match('#/v(\d+)/#', $route, $matches)) {
            return 'v' . $matches[1];
        }
        return null;
    }
}