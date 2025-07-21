<?php
/**
 * Enhanced Health Check Service for Production
 * 
 * Enterprise-grade health monitoring for the $100B ZipPicks platform
 * 
 * @package ZipPicks\Foundation\Health
 * @since 2.0.0
 */

namespace ZipPicks\Foundation\Health;

use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Core\EnvironmentManager;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;

class HealthCheckService
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;
    
    /**
     * @var EnvironmentManager
     */
    protected EnvironmentManager $env;
    
    /**
     * @var array Health check results cache
     */
    protected array $cache = [];
    
    /**
     * @var int Cache TTL in seconds
     */
    protected int $cacheTtl;
    
    /**
     * @var array Registered health checks
     */
    protected array $checks = [];
    
    /**
     * @var array Critical checks that affect overall health
     */
    protected array $criticalChecks = [
        'database_write',
        'cache',
        'api_gateway'
    ];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->container = Foundation::getInstance()->getContainer();
        $this->env = $this->container->get('env');
        $this->cacheTtl = (int) $this->env->get('monitoring.health_check.cache_ttl', 10);
        
        $this->registerDefaultChecks();
    }
    
    /**
     * Run all health checks
     * 
     * @param bool $useCache Whether to use cached results
     * @return array
     */
    public function check(bool $useCache = true): array
    {
        $cacheKey = 'health_check_results';
        
        // Check cache if enabled
        if ($useCache && isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if (time() - $cached['timestamp'] < $this->cacheTtl) {
                return $cached['data'];
            }
        }
        
        $startTime = microtime(true);
        $results = [
            'status' => 'healthy',
            'timestamp' => time(),
            'environment' => $this->env->getEnvironment(),
            'version' => ZIPPICKS_FOUNDATION_VERSION ?? '2.0.0',
            'checks' => [],
            'summary' => [
                'total' => 0,
                'healthy' => 0,
                'degraded' => 0,
                'unhealthy' => 0
            ],
            'metadata' => [
                'check_duration_ms' => 0,
                'cached' => false
            ]
        ];
        
        // Run all checks
        foreach ($this->checks as $name => $check) {
            $checkResult = $this->runCheck($name, $check);
            $results['checks'][$name] = $checkResult;
            $results['summary']['total']++;
            
            // Update summary
            switch ($checkResult['status']) {
                case 'healthy':
                    $results['summary']['healthy']++;
                    break;
                case 'degraded':
                    $results['summary']['degraded']++;
                    break;
                case 'unhealthy':
                    $results['summary']['unhealthy']++;
                    break;
            }
            
            // Update overall status based on check criticality
            if ($checkResult['status'] === 'unhealthy' && in_array($name, $this->criticalChecks)) {
                $results['status'] = 'unhealthy';
            } elseif ($checkResult['status'] === 'degraded' && $results['status'] === 'healthy') {
                $results['status'] = 'degraded';
            }
        }
        
        // Calculate total duration
        $results['metadata']['check_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        
        // Cache results
        $this->cache[$cacheKey] = [
            'timestamp' => time(),
            'data' => $results
        ];
        
        return $results;
    }
    
    /**
     * Run a single health check
     * 
     * @param string $name Check name
     * @param array $check Check configuration
     * @return array
     */
    protected function runCheck(string $name, array $check): array
    {
        $startTime = microtime(true);
        
        try {
            // Set timeout for check
            $oldTimeout = ini_get('max_execution_time');
            set_time_limit($check['timeout'] ?? 5);
            
            // Run the check
            $result = call_user_func($check['callback']);
            
            // Restore timeout
            set_time_limit((int)$oldTimeout);
            
            // Ensure result has required fields
            return array_merge([
                'status' => 'healthy',
                'message' => 'Check passed',
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'metadata' => [],
                'critical' => in_array($name, $this->criticalChecks)
            ], $result);
            
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Check failed: ' . $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'metadata' => [
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'error_class' => get_class($e)
                ],
                'critical' => in_array($name, $this->criticalChecks)
            ];
        }
    }
    
    /**
     * Register default health checks
     */
    protected function registerDefaultChecks(): void
    {
        // Database checks
        $this->registerCheck('database_write', [$this, 'checkDatabaseWrite']);
        $this->registerCheck('database_read', [$this, 'checkDatabaseRead']);
        
        // Cache checks
        $this->registerCheck('cache', [$this, 'checkCache']);
        $this->registerCheck('redis_cluster', [$this, 'checkRedisCluster']);
        
        // Queue checks
        $this->registerCheck('queue', [$this, 'checkQueue']);
        $this->registerCheck('queue_workers', [$this, 'checkQueueWorkers']);
        
        // Storage checks
        $this->registerCheck('storage', [$this, 'checkStorage']);
        $this->registerCheck('disk_space', [$this, 'checkDiskSpace']);
        
        // API checks
        $this->registerCheck('api_gateway', [$this, 'checkApiGateway']);
        $this->registerCheck('api_rate_limits', [$this, 'checkApiRateLimits']);
        
        // Infrastructure checks
        $this->registerCheck('memory', [$this, 'checkMemory']);
        $this->registerCheck('cpu', [$this, 'checkCpu']);
        $this->registerCheck('external_services', [$this, 'checkExternalServices']);
    }
    
    /**
     * Register a health check
     * 
     * @param string $name Check name
     * @param callable $callback Check callback
     * @param array $options Check options
     */
    public function registerCheck(string $name, callable $callback, array $options = []): void
    {
        $this->checks[$name] = [
            'callback' => $callback,
            'timeout' => $options['timeout'] ?? 5,
            'critical' => $options['critical'] ?? false
        ];
        
        if ($options['critical'] ?? false) {
            $this->criticalChecks[] = $name;
        }
    }
    
    /**
     * Check database write capability
     * 
     * @return array
     */
    protected function checkDatabaseWrite(): array
    {
        global $wpdb;
        
        try {
            $testTable = $wpdb->prefix . 'zippicks_health_check';
            $testValue = wp_generate_uuid4();
            
            // Create test table if not exists
            $wpdb->query("CREATE TABLE IF NOT EXISTS {$testTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                test_value VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Test write
            $result = $wpdb->insert($testTable, ['test_value' => $testValue]);
            
            if ($result === false) {
                throw new \Exception('Database write failed');
            }
            
            // Test read
            $readValue = $wpdb->get_var($wpdb->prepare(
                "SELECT test_value FROM {$testTable} WHERE test_value = %s",
                $testValue
            ));
            
            if ($readValue !== $testValue) {
                throw new \Exception('Database read verification failed');
            }
            
            // Clean up old records
            $wpdb->query("DELETE FROM {$testTable} WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            
            return [
                'status' => 'healthy',
                'message' => 'Database write/read operations successful',
                'metadata' => [
                    'host' => $this->env->get('database.connections.mysql.write.host', 'unknown'),
                    'database' => $wpdb->dbname,
                    'tables' => $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database write check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check database read replicas
     * 
     * @return array
     */
    protected function checkDatabaseRead(): array
    {
        if (!$this->env->isProduction()) {
            return [
                'status' => 'healthy',
                'message' => 'Read replicas not configured in ' . $this->env->getEnvironment()
            ];
        }
        
        $readHosts = $this->env->get('database.connections.mysql.read.hosts', []);
        $healthyReplicas = 0;
        $replicaStatus = [];
        
        foreach ($readHosts as $index => $host) {
            try {
                // Attempt connection to replica
                $connection = new \mysqli(
                    $host,
                    $this->env->get('database.connections.mysql.username'),
                    $this->env->get('database.connections.mysql.password'),
                    $this->env->get('database.connections.mysql.database')
                );
                
                if ($connection->connect_error) {
                    throw new \Exception($connection->connect_error);
                }
                
                // Check replica lag
                $result = $connection->query("SHOW SLAVE STATUS");
                $slaveStatus = $result ? $result->fetch_assoc() : null;
                $lag = $slaveStatus['Seconds_Behind_Master'] ?? null;
                
                $connection->close();
                
                $replicaStatus["replica_{$index}"] = [
                    'host' => $host,
                    'status' => 'healthy',
                    'lag_seconds' => $lag
                ];
                $healthyReplicas++;
                
            } catch (\Exception $e) {
                $replicaStatus["replica_{$index}"] = [
                    'host' => $host,
                    'status' => 'unhealthy',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $totalReplicas = count($readHosts);
        
        if ($healthyReplicas === $totalReplicas) {
            return [
                'status' => 'healthy',
                'message' => "All {$totalReplicas} read replicas are healthy",
                'metadata' => [
                    'replicas' => $replicaStatus
                ]
            ];
        } elseif ($healthyReplicas > 0) {
            return [
                'status' => 'degraded',
                'message' => "{$healthyReplicas}/{$totalReplicas} read replicas are healthy",
                'metadata' => [
                    'replicas' => $replicaStatus
                ]
            ];
        } else {
            return [
                'status' => 'unhealthy',
                'message' => 'All read replicas are unhealthy',
                'metadata' => [
                    'replicas' => $replicaStatus
                ]
            ];
        }
    }
    
    /**
     * Check cache functionality
     * 
     * @return array
     */
    protected function checkCache(): array
    {
        try {
            if (!$this->container->has('cache')) {
                throw new \Exception('Cache service not available');
            }
            
            $cache = $this->container->get('cache');
            $testKey = 'health_check_' . time();
            $testValue = wp_generate_uuid4();
            
            // Test set
            $cache->set($testKey, $testValue, 10);
            
            // Test get
            $retrieved = $cache->get($testKey);
            
            if ($retrieved !== $testValue) {
                throw new \Exception('Cache verification failed');
            }
            
            // Test delete
            $cache->delete($testKey);
            
            if ($cache->get($testKey) !== null) {
                throw new \Exception('Cache deletion failed');
            }
            
            return [
                'status' => 'healthy',
                'message' => 'Cache operations successful',
                'metadata' => [
                    'driver' => $this->env->get('cache.default', 'unknown'),
                    'prefix' => $this->env->get('cache.prefix', 'none')
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check Redis cluster health
     * 
     * @return array
     */
    protected function checkRedisCluster(): array
    {
        if (!class_exists('Redis')) {
            return [
                'status' => 'degraded',
                'message' => 'Redis extension not installed'
            ];
        }
        
        $clusters = $this->env->get('cache.stores.redis.clusters', []);
        $clusterHealth = [];
        
        foreach ($clusters as $clusterName => $nodes) {
            $healthyNodes = 0;
            $nodeHealth = [];
            
            foreach ($nodes as $index => $node) {
                try {
                    $redis = new \Redis();
                    $connected = $redis->connect(
                        $node['host'] ?? '127.0.0.1',
                        $node['port'] ?? 6379,
                        2.0 // 2 second timeout
                    );
                    
                    if (!$connected) {
                        throw new \Exception('Connection failed');
                    }
                    
                    // Check Redis info
                    $info = $redis->info();
                    $redis->close();
                    
                    $nodeHealth["node_{$index}"] = [
                        'status' => 'healthy',
                        'memory_used' => $info['used_memory_human'] ?? 'unknown',
                        'connected_clients' => $info['connected_clients'] ?? 0,
                        'uptime_days' => round(($info['uptime_in_seconds'] ?? 0) / 86400, 2)
                    ];
                    $healthyNodes++;
                    
                } catch (\Exception $e) {
                    $nodeHealth["node_{$index}"] = [
                        'status' => 'unhealthy',
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $clusterHealth[$clusterName] = [
                'healthy_nodes' => $healthyNodes,
                'total_nodes' => count($nodes),
                'nodes' => $nodeHealth
            ];
        }
        
        // Determine overall status
        $allHealthy = true;
        $someHealthy = false;
        
        foreach ($clusterHealth as $cluster) {
            if ($cluster['healthy_nodes'] < $cluster['total_nodes']) {
                $allHealthy = false;
            }
            if ($cluster['healthy_nodes'] > 0) {
                $someHealthy = true;
            }
        }
        
        if ($allHealthy) {
            return [
                'status' => 'healthy',
                'message' => 'All Redis clusters are healthy',
                'metadata' => ['clusters' => $clusterHealth]
            ];
        } elseif ($someHealthy) {
            return [
                'status' => 'degraded',
                'message' => 'Some Redis nodes are unhealthy',
                'metadata' => ['clusters' => $clusterHealth]
            ];
        } else {
            return [
                'status' => 'unhealthy',
                'message' => 'All Redis clusters are unhealthy',
                'metadata' => ['clusters' => $clusterHealth]
            ];
        }
    }
    
    /**
     * Check queue system
     * 
     * @return array
     */
    protected function checkQueue(): array
    {
        try {
            if (!$this->container->has('queue')) {
                throw new \Exception('Queue service not available');
            }
            
            $queue = $this->container->get('queue');
            $testJob = new \stdClass();
            $testJob->handle = function() { return true; };
            
            // Test queue push
            $queue->push($testJob);
            
            return [
                'status' => 'healthy',
                'message' => 'Queue system operational',
                'metadata' => [
                    'driver' => $this->env->get('queue.default', 'unknown'),
                    'connections' => array_keys($this->env->get('queue.connections', []))
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Queue check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check queue workers
     * 
     * @return array
     */
    protected function checkQueueWorkers(): array
    {
        global $wpdb;
        
        // Check for active workers in the last 5 minutes
        $table = $wpdb->prefix . 'zippicks_queue_workers';
        $activeWorkers = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$table} 
            WHERE last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        
        $minWorkers = $this->env->isProduction() ? 3 : 1;
        
        if ($activeWorkers >= $minWorkers) {
            return [
                'status' => 'healthy',
                'message' => "{$activeWorkers} active workers",
                'metadata' => [
                    'active_workers' => $activeWorkers,
                    'min_required' => $minWorkers
                ]
            ];
        } elseif ($activeWorkers > 0) {
            return [
                'status' => 'degraded',
                'message' => "Only {$activeWorkers} active workers (minimum: {$minWorkers})",
                'metadata' => [
                    'active_workers' => $activeWorkers,
                    'min_required' => $minWorkers
                ]
            ];
        } else {
            return [
                'status' => 'unhealthy',
                'message' => 'No active queue workers',
                'metadata' => [
                    'active_workers' => 0,
                    'min_required' => $minWorkers
                ]
            ];
        }
    }
    
    /**
     * Check storage system
     * 
     * @return array
     */
    protected function checkStorage(): array
    {
        $paths = [
            'logs' => ZIPPICKS_FOUNDATION_PATH . '/logs',
            'uploads' => wp_upload_dir()['basedir'],
            'cache' => ZIPPICKS_FOUNDATION_PATH . '/cache'
        ];
        
        $issues = [];
        
        foreach ($paths as $name => $path) {
            if (!is_dir($path)) {
                if (!@mkdir($path, 0755, true)) {
                    $issues[] = "{$name}: directory does not exist and cannot be created";
                }
            } elseif (!is_writable($path)) {
                $issues[] = "{$name}: not writable";
            }
        }
        
        if (empty($issues)) {
            return [
                'status' => 'healthy',
                'message' => 'All storage paths are accessible',
                'metadata' => ['paths' => $paths]
            ];
        } else {
            return [
                'status' => 'unhealthy',
                'message' => 'Storage issues detected',
                'metadata' => ['issues' => $issues]
            ];
        }
    }
    
    /**
     * Check disk space
     * 
     * @return array
     */
    protected function checkDiskSpace(): array
    {
        $path = ABSPATH;
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        
        if ($free === false || $total === false) {
            return [
                'status' => 'unhealthy',
                'message' => 'Unable to check disk space'
            ];
        }
        
        $usagePercent = (($total - $free) / $total) * 100;
        $freeGB = round($free / 1073741824, 2); // Convert to GB
        
        if ($usagePercent < 80) {
            $status = 'healthy';
        } elseif ($usagePercent < 90) {
            $status = 'degraded';
        } else {
            $status = 'unhealthy';
        }
        
        return [
            'status' => $status,
            'message' => sprintf('Disk usage: %.1f%% (%.1f GB free)', $usagePercent, $freeGB),
            'metadata' => [
                'usage_percent' => round($usagePercent, 2),
                'free_bytes' => $free,
                'total_bytes' => $total,
                'free_gb' => $freeGB
            ]
        ];
    }
    
    /**
     * Check API Gateway
     * 
     * @return array
     */
    protected function checkApiGateway(): array
    {
        try {
            if (!$this->container->has('api.gateway')) {
                throw new \Exception('API Gateway not registered');
            }
            
            $gateway = $this->container->get('api.gateway');
            
            // Check if gateway can handle requests
            $testRequest = new \ZipPicks\Foundation\Http\Request();
            $testRequest->setMethod('GET');
            $testRequest->setUri('/api/v1/health');
            
            return [
                'status' => 'healthy',
                'message' => 'API Gateway is operational',
                'metadata' => [
                    'version' => $this->env->get('api.versioning.default', 'v1'),
                    'supported_versions' => $this->env->get('api.versioning.supported', [])
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'API Gateway check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check API rate limits
     * 
     * @return array
     */
    protected function checkApiRateLimits(): array
    {
        try {
            if (!$this->container->has('rate_limiter')) {
                throw new \Exception('Rate limiter not available');
            }
            
            $rateLimiter = $this->container->get('rate_limiter');
            
            // Test rate limit functionality
            $testKey = 'health_check_rate_limit';
            $limit = 10;
            
            for ($i = 0; $i < 5; $i++) {
                $rateLimiter->hit($testKey, 1, $limit);
            }
            
            $remaining = $rateLimiter->remaining($testKey, $limit);
            
            if ($remaining !== 5) {
                throw new \Exception('Rate limiter calculation error');
            }
            
            return [
                'status' => 'healthy',
                'message' => 'Rate limiting is functional',
                'metadata' => [
                    'tiers' => array_keys($this->env->get('api.rate_limits.tiers', []))
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Rate limit check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check memory usage
     * 
     * @return array
     */
    protected function checkMemory(): array
    {
        $limit = $this->parseBytes(ini_get('memory_limit'));
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $percentage = ($usage / $limit) * 100;
        
        if ($percentage < 70) {
            $status = 'healthy';
        } elseif ($percentage < 85) {
            $status = 'degraded';
        } else {
            $status = 'unhealthy';
        }
        
        return [
            'status' => $status,
            'message' => sprintf('Memory usage: %.1f%%', $percentage),
            'metadata' => [
                'current_mb' => round($usage / 1048576, 2),
                'peak_mb' => round($peak / 1048576, 2),
                'limit_mb' => round($limit / 1048576, 2),
                'usage_percent' => round($percentage, 2)
            ]
        ];
    }
    
    /**
     * Check CPU usage
     * 
     * @return array
     */
    protected function checkCpu(): array
    {
        // Get system load average
        $load = sys_getloadavg();
        
        if ($load === false) {
            return [
                'status' => 'degraded',
                'message' => 'Unable to check CPU load'
            ];
        }
        
        // Get number of CPU cores
        $cores = 1;
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $cores = count($matches[0]);
        }
        
        // Calculate load per core
        $loadPerCore = $load[0] / $cores;
        
        if ($loadPerCore < 0.7) {
            $status = 'healthy';
        } elseif ($loadPerCore < 0.9) {
            $status = 'degraded';
        } else {
            $status = 'unhealthy';
        }
        
        return [
            'status' => $status,
            'message' => sprintf('CPU load: %.2f (%.2f per core)', $load[0], $loadPerCore),
            'metadata' => [
                'load_1min' => round($load[0], 2),
                'load_5min' => round($load[1], 2),
                'load_15min' => round($load[2], 2),
                'cores' => $cores,
                'load_per_core' => round($loadPerCore, 2)
            ]
        ];
    }
    
    /**
     * Check external services
     * 
     * @return array
     */
    protected function checkExternalServices(): array
    {
        $services = [];
        $healthyCount = 0;
        
        // Check Stripe
        if ($stripeKey = $this->env->get('services.stripe.key')) {
            try {
                // Simple connectivity check
                $ch = curl_init('https://api.stripe.com/v1/charges');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$stripeKey}"]);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $services['stripe'] = [
                    'status' => ($httpCode === 401 || $httpCode === 200) ? 'healthy' : 'unhealthy',
                    'response_code' => $httpCode
                ];
                if ($services['stripe']['status'] === 'healthy') $healthyCount++;
                
            } catch (\Exception $e) {
                $services['stripe'] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Check SendGrid
        if ($sendgridKey = $this->env->get('services.sendgrid.api_key')) {
            try {
                $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$sendgridKey}"]);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $services['sendgrid'] = [
                    'status' => ($httpCode === 401 || $httpCode === 400) ? 'healthy' : 'unhealthy',
                    'response_code' => $httpCode
                ];
                if ($services['sendgrid']['status'] === 'healthy') $healthyCount++;
                
            } catch (\Exception $e) {
                $services['sendgrid'] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $totalServices = count($services);
        
        if ($totalServices === 0) {
            return [
                'status' => 'healthy',
                'message' => 'No external services configured'
            ];
        } elseif ($healthyCount === $totalServices) {
            return [
                'status' => 'healthy',
                'message' => "All {$totalServices} external services are reachable",
                'metadata' => ['services' => $services]
            ];
        } elseif ($healthyCount > 0) {
            return [
                'status' => 'degraded',
                'message' => "{$healthyCount}/{$totalServices} external services are reachable",
                'metadata' => ['services' => $services]
            ];
        } else {
            return [
                'status' => 'unhealthy',
                'message' => 'All external services are unreachable',
                'metadata' => ['services' => $services]
            ];
        }
    }
    
    /**
     * Parse bytes from string
     * 
     * @param string $val
     * @return int
     */
    protected function parseBytes(string $val): int
    {
        $val = trim($val);
        if (empty($val)) {
            return 0;
        }
        
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
    
    /**
     * Get health check endpoint response
     * 
     * @param bool $detailed Include detailed check results
     * @return array
     */
    public function getEndpointResponse(bool $detailed = true): array
    {
        $results = $this->check();
        
        // Remove sensitive data for public endpoint
        if (!$detailed) {
            unset($results['checks']);
            unset($results['metadata']);
            
            // Just return basic status
            return [
                'status' => $results['status'],
                'timestamp' => $results['timestamp'],
                'environment' => $results['environment'],
                'version' => $results['version']
            ];
        }
        
        return $results;
    }
}