<?php
/**
 * Health Check System
 * 
 * @package ZipPicks\Foundation\Health
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Health;

use ZipPicks\Foundation\Core\Foundation;

/**
 * Comprehensive health check system for the platform
 */
class HealthCheck
{
    private array $checks = [];
    private array $results = [];
    private float $lastCheck = 0;
    private int $cacheTime = 60; // Cache results for 60 seconds

    public function registerCheck(string $name, callable $check, array $config = []): void
    {
        $this->checks[$name] = [
            'callback' => $check,
            'critical' => $config['critical'] ?? false,
            'timeout' => $config['timeout'] ?? 5,
            'description' => $config['description'] ?? '',
        ];
    }

    public function runChecks(bool $useCache = true): array
    {
        if ($useCache && (time() - $this->lastCheck) < $this->cacheTime) {
            return $this->results;
        }

        $this->results = [
            'status' => 'healthy',
            'timestamp' => time(),
            'checks' => [],
            'summary' => [
                'total' => 0,
                'healthy' => 0,
                'degraded' => 0,
                'unhealthy' => 0,
            ],
        ];

        foreach ($this->checks as $name => $check) {
            $result = $this->runCheck($name, $check);
            $this->results['checks'][$name] = $result;
            $this->results['summary']['total']++;
            
            switch ($result['status']) {
                case 'healthy':
                    $this->results['summary']['healthy']++;
                    break;
                case 'degraded':
                    $this->results['summary']['degraded']++;
                    if ($this->results['status'] === 'healthy') {
                        $this->results['status'] = 'degraded';
                    }
                    break;
                case 'unhealthy':
                    $this->results['summary']['unhealthy']++;
                    if ($check['critical']) {
                        $this->results['status'] = 'unhealthy';
                    } elseif ($this->results['status'] === 'healthy') {
                        $this->results['status'] = 'degraded';
                    }
                    break;
            }
        }

        $this->lastCheck = time();
        return $this->results;
    }

    private function runCheck(string $name, array $check): array
    {
        $start = microtime(true);
        
        try {
            // Set execution timeout
            $oldTimeout = ini_get('max_execution_time');
            set_time_limit($check['timeout']);
            
            $result = call_user_func($check['callback']);
            
            // Restore timeout
            set_time_limit((int)$oldTimeout);
            
            return [
                'status' => $result['status'] ?? 'healthy',
                'message' => $result['message'] ?? 'Check passed',
                'duration' => microtime(true) - $start,
                'metadata' => $result['metadata'] ?? [],
                'critical' => $check['critical'],
                'description' => $check['description'],
            ];
            
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Check failed: ' . $e->getMessage(),
                'duration' => microtime(true) - $start,
                'metadata' => [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
                'critical' => $check['critical'],
                'description' => $check['description'],
            ];
        }
    }

    public function getDefaultChecks(): array
    {
        return [
            'database' => function() {
                global $wpdb;
                
                try {
                    $result = $wpdb->get_var("SELECT 1");
                    if ($result === '1') {
                        return [
                            'status' => 'healthy',
                            'message' => 'Database connection is active',
                            'metadata' => [
                                'tables' => $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"),
                            ],
                        ];
                    }
                } catch (\Exception $e) {
                    return [
                        'status' => 'unhealthy',
                        'message' => 'Database connection failed',
                    ];
                }
            },

            'filesystem' => function() {
                $paths = [
                    'logs' => ZIPPICKS_FOUNDATION_PATH . '/logs',
                    'uploads' => wp_upload_dir()['basedir'],
                ];
                
                $issues = [];
                foreach ($paths as $name => $path) {
                    if (!is_writable($path)) {
                        $issues[] = "{$name} ({$path})";
                    }
                }
                
                if (empty($issues)) {
                    return [
                        'status' => 'healthy',
                        'message' => 'All paths are writable',
                        'metadata' => ['paths' => $paths],
                    ];
                } else {
                    return [
                        'status' => 'degraded',
                        'message' => 'Some paths are not writable',
                        'metadata' => ['issues' => $issues],
                    ];
                }
            },

            'memory' => function() {
                $limit = $this->parseBytes(ini_get('memory_limit'));
                $usage = memory_get_usage(true);
                $percentage = ($usage / $limit) * 100;
                
                if ($percentage < 70) {
                    $status = 'healthy';
                } elseif ($percentage < 90) {
                    $status = 'degraded';
                } else {
                    $status = 'unhealthy';
                }
                
                return [
                    'status' => $status,
                    'message' => sprintf('Memory usage: %.1f%%', $percentage),
                    'metadata' => [
                        'usage' => $usage,
                        'limit' => $limit,
                        'percentage' => $percentage,
                    ],
                ];
            },

            'logging' => function() {
                $foundation = Foundation::getInstance();
                $container = $foundation->getContainer();
                
                if (!$container->has('logger')) {
                    return [
                        'status' => 'unhealthy',
                        'message' => 'Logger not available',
                    ];
                }
                
                $logger = $container->get('logger');
                $health = $logger->getHealthStatus();
                
                $healthy = 0;
                $total = count($health);
                
                foreach ($health as $driver => $status) {
                    if ($status['healthy']) {
                        $healthy++;
                    }
                }
                
                if ($healthy === $total) {
                    return [
                        'status' => 'healthy',
                        'message' => 'All log drivers are healthy',
                        'metadata' => $health,
                    ];
                } elseif ($healthy > 0) {
                    return [
                        'status' => 'degraded',
                        'message' => "{$healthy}/{$total} log drivers are healthy",
                        'metadata' => $health,
                    ];
                } else {
                    return [
                        'status' => 'unhealthy',
                        'message' => 'No log drivers are healthy',
                        'metadata' => $health,
                    ];
                }
            },

            'cache' => function() {
                if (!function_exists('wp_cache_get')) {
                    return [
                        'status' => 'degraded',
                        'message' => 'Object cache not available',
                    ];
                }
                
                $testKey = 'health_check_' . time();
                $testValue = wp_generate_uuid4();
                
                wp_cache_set($testKey, $testValue, 'health', 10);
                $retrieved = wp_cache_get($testKey, 'health');
                wp_cache_delete($testKey, 'health');
                
                if ($retrieved === $testValue) {
                    return [
                        'status' => 'healthy',
                        'message' => 'Cache is functioning properly',
                        'metadata' => [
                            'type' => class_exists('Redis') ? 'redis' : 'default',
                        ],
                    ];
                } else {
                    return [
                        'status' => 'unhealthy',
                        'message' => 'Cache test failed',
                    ];
                }
            },

            'plugins' => function() {
                $requiredPlugins = [
                    'zippicks-foundation/zippicks-foundation.php',
                ];
                
                $activePlugins = get_option('active_plugins', []);
                $missing = [];
                
                foreach ($requiredPlugins as $plugin) {
                    if (!in_array($plugin, $activePlugins)) {
                        $missing[] = $plugin;
                    }
                }
                
                if (empty($missing)) {
                    return [
                        'status' => 'healthy',
                        'message' => 'All required plugins are active',
                        'metadata' => [
                            'active_count' => count($activePlugins),
                        ],
                    ];
                } else {
                    return [
                        'status' => 'unhealthy',
                        'message' => 'Required plugins are missing',
                        'metadata' => [
                            'missing' => $missing,
                        ],
                    ];
                }
            },
        ];
    }

    private function parseBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }

    public function registerEndpoint(): void
    {
        add_action('rest_api_init', function() {
            register_rest_route('zippicks/v1', '/health', [
                'methods' => 'GET',
                'callback' => [$this, 'handleHealthEndpoint'],
                'permission_callback' => function() {
                    // Public endpoint but can be restricted
                    return apply_filters('zippicks_health_check_access', true);
                },
            ]);
        });
    }

    public function handleHealthEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $results = $this->runChecks();
        
        $httpStatus = match($results['status']) {
            'healthy' => 200,
            'degraded' => 200,
            'unhealthy' => 503,
            default => 500,
        };
        
        return new \WP_REST_Response($results, $httpStatus);
    }
}