<?php
/**
 * Metrics Service Provider
 * 
 * Registers Prometheus metrics services and endpoints
 * 
 * @package ZipPicks\Foundation\Services
 * @since 2.0.0
 */

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Observability\Metrics\MetricsCollector;
use ZipPicks\Foundation\Observability\Metrics\PrometheusExporter;

class MetricsServiceProvider extends ServiceProvider
{
    /**
     * Register services
     * 
     * @return void
     */
    public function register(): void
    {
        // Register metrics collector
        $this->container->singleton('metrics', function($container) {
            return new MetricsCollector();
        });
        
        // Register Prometheus exporter
        $this->container->singleton('prometheus', function($container) {
            return new PrometheusExporter();
        });
        
        // Aliases for convenience
        $this->container->alias('metrics', MetricsCollector::class);
        $this->container->alias('prometheus', PrometheusExporter::class);
    }
    
    /**
     * Boot services
     * 
     * @return void
     */
    public function boot(): void
    {
        $env = $this->container->get('env');
        
        if (!$env->get('monitoring.prometheus.enabled', true)) {
            return;
        }
        
        // Register metrics endpoint
        $this->registerMetricsEndpoint();
        
        // Hook into WordPress to collect metrics
        $this->registerMetricsCollectors();
        
        // Add metrics to admin bar
        if (is_admin_bar_showing() && current_user_can('manage_options')) {
            add_action('admin_bar_menu', [$this, 'addMetricsToAdminBar'], 999);
        }
    }
    
    /**
     * Register metrics endpoint
     */
    protected function registerMetricsEndpoint(): void
    {
        $endpoint = $this->container->get('env')->get('monitoring.prometheus.endpoint', '/metrics');
        
        // Register REST API endpoint
        add_action('rest_api_init', function() use ($endpoint) {
            register_rest_route('zippicks/v1', $endpoint, [
                'methods' => 'GET',
                'callback' => [$this, 'handleMetricsEndpoint'],
                'permission_callback' => [$this, 'checkMetricsAccess']
            ]);
        });
        
        // Also handle direct requests
        add_action('init', function() use ($endpoint) {
            if ($_SERVER['REQUEST_URI'] === $endpoint) {
                $this->handleDirectMetricsRequest();
            }
        });
    }
    
    /**
     * Handle metrics endpoint request
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleMetricsEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $exporter = $this->container->get('prometheus');
        $metrics = $exporter->export();
        
        $response = new \WP_REST_Response($metrics, 200);
        $response->header('Content-Type', 'text/plain; version=0.0.4');
        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        
        return $response;
    }
    
    /**
     * Handle direct metrics request (not through REST API)
     */
    protected function handleDirectMetricsRequest(): void
    {
        // Check access
        if (!$this->checkMetricsAccess()) {
            header('HTTP/1.1 403 Forbidden');
            exit('Access denied');
        }
        
        $exporter = $this->container->get('prometheus');
        $metrics = $exporter->export();
        
        header('Content-Type: text/plain; version=0.0.4');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        
        echo $metrics;
        exit;
    }
    
    /**
     * Check metrics endpoint access
     * 
     * @return bool
     */
    public function checkMetricsAccess(): bool
    {
        // Allow localhost always
        if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
            return true;
        }
        
        // Check for bearer token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            $expectedToken = $this->container->get('env')->get('monitoring.prometheus.token');
            
            if ($expectedToken && hash_equals($expectedToken, $token)) {
                return true;
            }
        }
        
        // Check IP whitelist
        $whitelist = $this->container->get('env')->get('monitoring.prometheus.ip_whitelist', []);
        if (in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
            return true;
        }
        
        // Allow admins
        return current_user_can('manage_options');
    }
    
    /**
     * Register metrics collectors
     */
    protected function registerMetricsCollectors(): void
    {
        $metrics = $this->container->get('metrics');
        
        // Track HTTP requests
        add_action('parse_request', function($wp) use ($metrics) {
            $counter = $metrics->counter('http_requests_total');
            if ($counter) {
                $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
                $endpoint = $this->normalizeEndpoint($_SERVER['REQUEST_URI'] ?? '/');
                
                // We'll update status later
                $GLOBALS['zippicks_metrics_request'] = [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'start_time' => microtime(true)
                ];
            }
        });
        
        // Track response status
        add_action('wp', function() use ($metrics) {
            if (isset($GLOBALS['zippicks_metrics_request'])) {
                $request = $GLOBALS['zippicks_metrics_request'];
                $status = http_response_code() ?: 200;
                
                // Increment request counter
                $counter = $metrics->counter('http_requests_total');
                if ($counter) {
                    $counter->inc([
                        'method' => $request['method'],
                        'endpoint' => $request['endpoint'],
                        'status' => (string) $status
                    ]);
                }
                
                // Record request duration
                $histogram = $metrics->histogram('http_request_duration_seconds');
                if ($histogram) {
                    $duration = microtime(true) - $request['start_time'];
                    $histogram->observe([
                        'method' => $request['method'],
                        'endpoint' => $request['endpoint']
                    ], $duration);
                }
            }
        });
        
        // Track database queries
        add_filter('query', function($query) use ($metrics) {
            $GLOBALS['zippicks_db_query_start'] = microtime(true);
            
            $counter = $metrics->counter('database_queries_total');
            if ($counter) {
                $operation = $this->extractDbOperation($query);
                $table = $this->extractDbTable($query);
                
                $counter->inc([
                    'operation' => $operation,
                    'table' => $table
                ]);
            }
            
            return $query;
        });
        
        // Track query duration
        add_filter('query_results', function($results, $query) use ($metrics) {
            if (isset($GLOBALS['zippicks_db_query_start'])) {
                $duration = microtime(true) - $GLOBALS['zippicks_db_query_start'];
                unset($GLOBALS['zippicks_db_query_start']);
                
                $histogram = $metrics->histogram('database_query_duration_seconds');
                if ($histogram) {
                    $operation = $this->extractDbOperation($query);
                    $table = $this->extractDbTable($query);
                    
                    $histogram->observe([
                        'operation' => $operation,
                        'table' => $table
                    ], $duration);
                }
            }
            
            return $results;
        }, 10, 2);
        
        // Track user registrations
        add_action('user_register', function($userId) use ($metrics) {
            $counter = $metrics->counter('user_registrations_total');
            if ($counter) {
                $source = $_REQUEST['source'] ?? 'organic';
                $counter->inc(['source' => $source]);
            }
        });
        
        // Track active users
        add_action('wp_login', function($userLogin, $user) use ($metrics) {
            $gauge = $metrics->gauge('active_users');
            if ($gauge) {
                // This is simplified - in production you'd track sessions properly
                $activeUsers = get_transient('active_users_count') ?: 0;
                set_transient('active_users_count', $activeUsers + 1, HOUR_IN_SECONDS);
                $gauge->set([], $activeUsers + 1);
            }
        }, 10, 2);
        
        // Track API requests
        add_filter('rest_pre_dispatch', function($result, $server, $request) use ($metrics) {
            $route = $request->get_route();
            $method = $request->get_method();
            
            $GLOBALS['zippicks_api_request'] = [
                'route' => $route,
                'method' => $method,
                'version' => $this->extractApiVersion($route),
                'start_time' => microtime(true)
            ];
            
            return $result;
        }, 10, 3);
        
        // Track API response
        add_filter('rest_post_dispatch', function($response, $server, $request) use ($metrics) {
            if (isset($GLOBALS['zippicks_api_request'])) {
                $apiRequest = $GLOBALS['zippicks_api_request'];
                $status = $response->get_status();
                
                // Track request
                $counter = $metrics->counter('api_requests_total');
                if ($counter) {
                    $counter->inc([
                        'version' => $apiRequest['version'],
                        'endpoint' => $this->normalizeEndpoint($apiRequest['route']),
                        'method' => $apiRequest['method']
                    ]);
                }
                
                // Track errors
                if ($status >= 400) {
                    $errorCounter = $metrics->counter('api_errors_total');
                    if ($errorCounter) {
                        $errorCounter->inc([
                            'version' => $apiRequest['version'],
                            'endpoint' => $this->normalizeEndpoint($apiRequest['route']),
                            'error_code' => (string) $status
                        ]);
                    }
                }
                
                // Track duration
                $histogram = $metrics->histogram('api_request_duration_seconds');
                if ($histogram) {
                    $duration = microtime(true) - $apiRequest['start_time'];
                    $histogram->observe([
                        'version' => $apiRequest['version'],
                        'endpoint' => $this->normalizeEndpoint($apiRequest['route']),
                        'method' => $apiRequest['method']
                    ], $duration);
                }
                
                unset($GLOBALS['zippicks_api_request']);
            }
            
            return $response;
        }, 10, 3);
    }
    
    /**
     * Add metrics link to admin bar
     * 
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    public function addMetricsToAdminBar(\WP_Admin_Bar $wp_admin_bar): void
    {
        $wp_admin_bar->add_node([
            'id' => 'zippicks-metrics',
            'title' => '📊 Metrics',
            'href' => rest_url('zippicks/v1/metrics'),
            'meta' => [
                'target' => '_blank',
                'title' => 'View Prometheus Metrics'
            ]
        ]);
    }
    
    /**
     * Normalize endpoint for metrics
     * 
     * @param string $endpoint
     * @return string
     */
    protected function normalizeEndpoint(string $endpoint): string
    {
        // Remove query string
        $endpoint = strtok($endpoint, '?');
        
        // Remove IDs from endpoints
        $endpoint = preg_replace('/\/\d+/', '/{id}', $endpoint);
        
        // Remove UUIDs
        $endpoint = preg_replace('/\/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/', '/{uuid}', $endpoint);
        
        // Normalize common patterns
        $patterns = [
            '/\/page\/\d+/' => '/page/{n}',
            '/\/p\/\d+/' => '/p/{id}',
            '/\/tag\/[^\/]+/' => '/tag/{slug}',
            '/\/category\/[^\/]+/' => '/category/{slug}'
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $endpoint = preg_replace($pattern, $replacement, $endpoint);
        }
        
        return $endpoint ?: '/';
    }
    
    /**
     * Extract database operation from query
     * 
     * @param string $query
     * @return string
     */
    protected function extractDbOperation(string $query): string
    {
        $query = trim($query);
        $firstWord = strtoupper(strtok($query, ' '));
        
        $operations = [
            'SELECT' => 'select',
            'INSERT' => 'insert',
            'UPDATE' => 'update',
            'DELETE' => 'delete',
            'SHOW' => 'show',
            'DESCRIBE' => 'describe',
            'CREATE' => 'create',
            'ALTER' => 'alter',
            'DROP' => 'drop'
        ];
        
        return $operations[$firstWord] ?? 'other';
    }
    
    /**
     * Extract table name from query
     * 
     * @param string $query
     * @return string
     */
    protected function extractDbTable(string $query): string
    {
        global $wpdb;
        
        // Try to extract table name
        if (preg_match('/(?:FROM|INTO|UPDATE|TABLE)\s+`?(\w+)`?/i', $query, $matches)) {
            $table = $matches[1];
            
            // Remove WordPress prefix
            if (strpos($table, $wpdb->prefix) === 0) {
                $table = substr($table, strlen($wpdb->prefix));
            }
            
            return $table;
        }
        
        return 'unknown';
    }
    
    /**
     * Extract API version from route
     * 
     * @param string $route
     * @return string
     */
    protected function extractApiVersion(string $route): string
    {
        if (preg_match('#/v(\d+)/#', $route, $matches)) {
            return 'v' . $matches[1];
        }
        return 'unknown';
    }
}