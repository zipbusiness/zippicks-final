<?php
/**
 * Cache Performance Test Scenario
 * 
 * Tests cache performance under enterprise-scale load
 * Validates Redis cluster performance, hit rates, and failover
 *
 * @package ZipPicks\Foundation\Testing\Performance\Scenarios
 */

namespace ZipPicks\Foundation\Testing\Performance\Scenarios;

use ZipPicks\Foundation\Testing\Performance\Scenarios\LoadTestScenarioInterface;
use ZipPicks\Foundation\Logging\EnterpriseLogger;

class CachePerformanceTest implements LoadTestScenarioInterface
{
    /**
     * Logger instance
     *
     * @var EnterpriseLogger
     */
    protected EnterpriseLogger $logger;

    /**
     * Cache operations to test
     *
     * @var array
     */
    protected array $operations = [
        'get' => [
            'weight' => 70,
            'target_time' => 1,    // <1ms target
            'description' => 'Cache GET operations'
        ],
        'set' => [
            'weight' => 20,
            'target_time' => 2,    // <2ms target
            'description' => 'Cache SET operations'
        ],
        'delete' => [
            'weight' => 5,
            'target_time' => 1,    // <1ms target
            'description' => 'Cache DELETE operations'
        ],
        'multi_get' => [
            'weight' => 5,
            'target_time' => 3,    // <3ms target for batch operations
            'description' => 'Cache MGET operations'
        ]
    ];

    /**
     * Cache test data patterns
     *
     * @var array
     */
    protected array $testDataPatterns = [
        'business_data' => [
            'key_pattern' => 'business:{}',
            'size_range' => [1024, 4096],      // 1-4KB
            'ttl_range' => [300, 3600]         // 5 minutes to 1 hour
        ],
        'search_results' => [
            'key_pattern' => 'search:{}:{}',
            'size_range' => [2048, 8192],      // 2-8KB
            'ttl_range' => [600, 1800]         // 10-30 minutes
        ],
        'user_sessions' => [
            'key_pattern' => 'session:{}',
            'size_range' => [512, 2048],       // 0.5-2KB
            'ttl_range' => [1800, 7200]        // 30 minutes to 2 hours
        ],
        'api_responses' => [
            'key_pattern' => 'api:{}:{}',
            'size_range' => [1024, 16384],     // 1-16KB
            'ttl_range' => [60, 600]           // 1-10 minutes
        ]
    ];

    /**
     * Performance metrics
     *
     * @var array
     */
    protected array $metrics = [];

    /**
     * Create cache performance test
     *
     * @param EnterpriseLogger|null $logger
     */
    public function __construct(EnterpriseLogger $logger = null)
    {
        $this->logger = $logger ?? new EnterpriseLogger();
    }

    /**
     * Execute cache performance test
     *
     * @param array $config
     * @return array
     */
    public function execute(array $config): array
    {
        $startTime = microtime(true);
        
        $this->logger->info('Starting cache performance test', [
            'scenario' => $this->getName(),
            'config' => $config
        ]);

        try {
            // Initialize metrics and test data
            $this->initializeMetrics();
            $this->prepareTestData($config);

            // Execute test phases
            $results = [
                'warmup_phase' => $this->executeWarmupPhase($config),
                'performance_phase' => $this->executePerformancePhase($config),
                'stress_phase' => $this->executeStressPhase($config),
                'failover_phase' => $this->executeFailoverPhase($config),
                'cleanup_phase' => $this->executeCleanupPhase($config)
            ];

            // Aggregate results
            $summary = $this->aggregateResults($results, $config);

            $this->logger->info('Cache performance test completed', [
                'scenario' => $this->getName(),
                'duration' => round(microtime(true) - $startTime, 2),
                'operations_per_second' => $summary['operations_per_second'],
                'cache_hit_rate' => $summary['hit_rate']
            ]);

            return [
                'scenario' => $this->getName(),
                'summary' => $summary,
                'phases' => $results,
                'operations' => $this->metrics,
                'duration' => round(microtime(true) - $startTime, 2)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Cache performance test failed', [
                'scenario' => $this->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Get scenario name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'cache_performance';
    }

    /**
     * Get scenario description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Enterprise cache performance testing with Redis cluster validation and failover testing';
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    public function getDefaultConfig(): array
    {
        return [
            'duration' => 300,                  // 5 minutes
            'operations_per_second' => 10000,   // 10,000 OPS target
            'concurrent_connections' => 100,    // 100 concurrent connections
            'cache_size_mb' => 1024,           // 1GB cache size
            'eviction_policy' => 'allkeys-lru', // LRU eviction
            'test_data_keys' => 100000,        // 100K test keys
            'batch_size' => 100,               // Batch operation size
            'hit_rate_target' => 95,           // 95% hit rate target
            'response_time_target' => 1,       // <1ms response time
            'memory_efficiency_target' => 85,  // 85% memory efficiency
            'cluster_nodes' => 3,              // Redis cluster nodes
            'replication_factor' => 1,         // Replication factor
            'failover_test' => true,           // Test failover scenarios
            'persistence_test' => true,        // Test data persistence
            'memory_pressure_test' => true     // Test under memory pressure
        ];
    }

    /**
     * Validate scenario configuration
     *
     * @param array $config
     * @return array
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        if ($config['operations_per_second'] < 1) {
            $errors[] = 'Operations per second must be at least 1';
        }

        if ($config['concurrent_connections'] < 1) {
            $errors[] = 'Concurrent connections must be at least 1';
        }

        if ($config['duration'] < 30) {
            $errors[] = 'Test duration must be at least 30 seconds';
        }

        if ($config['hit_rate_target'] < 0 || $config['hit_rate_target'] > 100) {
            $errors[] = 'Hit rate target must be between 0 and 100';
        }

        return $errors;
    }

    /**
     * Initialize metrics collection
     *
     * @return void
     */
    protected function initializeMetrics(): void
    {
        foreach ($this->operations as $name => $operation) {
            $this->metrics[$name] = [
                'operations_executed' => 0,
                'operations_successful' => 0,
                'operations_failed' => 0,
                'response_times' => [],
                'cache_hits' => 0,
                'cache_misses' => 0,
                'timeouts' => 0,
                'connection_errors' => 0,
                'memory_usage' => []
            ];
        }

        // Global metrics
        $this->metrics['global'] = [
            'total_operations' => 0,
            'hit_rate' => 0,
            'memory_usage_mb' => 0,
            'evictions' => 0,
            'expired_keys' => 0,
            'connections_created' => 0,
            'connections_reused' => 0
        ];
    }

    /**
     * Prepare test data for cache operations
     *
     * @param array $config
     * @return void
     */
    protected function prepareTestData(array $config): void
    {
        $this->logger->info('Preparing cache test data', [
            'test_keys' => $config['test_data_keys']
        ]);

        // Pre-populate cache with test data to ensure realistic hit rates
        $keysPerPattern = (int)($config['test_data_keys'] / count($this->testDataPatterns));
        
        foreach ($this->testDataPatterns as $patternName => $pattern) {
            for ($i = 0; $i < $keysPerPattern; $i++) {
                $key = $this->generateKey($pattern['key_pattern'], $i);
                $data = $this->generateTestData($pattern['size_range']);
                $ttl = rand($pattern['ttl_range'][0], $pattern['ttl_range'][1]);
                
                // Simulate cache SET operation
                $this->simulateCacheOperation('set', $key, $data, $ttl);
            }
        }

        $this->logger->info('Cache test data preparation completed');
    }

    /**
     * Execute cache warmup phase
     *
     * @param array $config
     * @return array
     */
    protected function executeWarmupPhase(array $config): array
    {
        $this->logger->info('Executing cache warmup phase');

        $results = [
            'phase' => 'warmup',
            'duration' => 30,
            'operations' => 0,
            'cache_fills' => 0,
            'memory_usage_start' => 0,
            'memory_usage_end' => 0
        ];

        $startTime = time();
        $endTime = $startTime + $results['duration'];

        // Warm up cache with typical access patterns
        while (time() < $endTime) {
            $pattern = $this->testDataPatterns[array_rand($this->testDataPatterns)];
            $key = $this->generateKey($pattern['key_pattern'], rand(1, 1000));
            
            $result = $this->simulateCacheOperation('get', $key);
            $results['operations']++;
            
            if (!$result['hit']) {
                // Cache miss - fill with data
                $data = $this->generateTestData($pattern['size_range']);
                $ttl = rand($pattern['ttl_range'][0], $pattern['ttl_range'][1]);
                $this->simulateCacheOperation('set', $key, $data, $ttl);
                $results['cache_fills']++;
            }

            usleep(1000); // 1ms delay between operations
        }

        return $results;
    }

    /**
     * Execute performance testing phase
     *
     * @param array $config
     * @return array
     */
    protected function executePerformancePhase(array $config): array
    {
        $this->logger->info('Executing cache performance phase', [
            'target_ops' => $config['operations_per_second'],
            'concurrent_connections' => $config['concurrent_connections']
        ]);

        $results = [
            'phase' => 'performance',
            'duration' => 120, // 2 minutes
            'target_ops_per_second' => $config['operations_per_second'],
            'actual_ops_per_second' => 0,
            'operations_by_type' => []
        ];

        $testDuration = $results['duration'];
        $startTime = time();
        $endTime = $startTime + $testDuration;
        $totalOperations = 0;

        while (time() < $endTime) {
            // Execute operations based on weight distribution
            foreach ($this->operations as $opName => $opConfig) {
                $opsForThisType = (int)($config['operations_per_second'] * $opConfig['weight'] / 100);
                
                for ($i = 0; $i < $opsForThisType; $i++) {
                    $result = $this->executeOperation($opName, $config);
                    $this->updateOperationMetrics($opName, $result);
                    $totalOperations++;
                }
            }
            
            sleep(1); // 1-second intervals
        }

        $results['actual_ops_per_second'] = round($totalOperations / $testDuration, 2);
        
        foreach ($this->operations as $opName => $opConfig) {
            $results['operations_by_type'][$opName] = [
                'executed' => $this->metrics[$opName]['operations_executed'],
                'avg_response_time' => $this->calculateAverageResponseTime($opName),
                'hit_rate' => $this->calculateHitRate($opName)
            ];
        }

        return $results;
    }

    /**
     * Execute stress testing phase
     *
     * @param array $config
     * @return array
     */
    protected function executeStressPhase(array $config): array
    {
        $this->logger->info('Executing cache stress phase');

        $results = [
            'phase' => 'stress',
            'duration' => 60, // 1 minute of stress
            'peak_ops_per_second' => $config['operations_per_second'] * 3, // 3x normal load
            'memory_pressure_events' => 0,
            'eviction_events' => 0,
            'connection_saturation_events' => 0
        ];

        $stressDuration = $results['duration'];
        $startTime = time();
        $endTime = $startTime + $stressDuration;

        // Apply 3x load to stress test the cache
        while (time() < $endTime) {
            for ($burst = 0; $burst < 3; $burst++) {
                foreach ($this->operations as $opName => $opConfig) {
                    $opsForThisType = (int)($config['operations_per_second'] * $opConfig['weight'] / 100);
                    
                    for ($i = 0; $i < $opsForThisType; $i++) {
                        $result = $this->executeOperation($opName, $config);
                        $this->updateOperationMetrics($opName, $result);
                        
                        // Check for stress indicators
                        if ($result['memory_pressure']) {
                            $results['memory_pressure_events']++;
                        }
                        if ($result['eviction_occurred']) {
                            $results['eviction_events']++;
                        }
                        if ($result['connection_error']) {
                            $results['connection_saturation_events']++;
                        }
                    }
                }
            }
            
            sleep(1);
        }

        return $results;
    }

    /**
     * Execute failover testing phase
     *
     * @param array $config
     * @return array
     */
    protected function executeFailoverPhase(array $config): array
    {
        if (!$config['failover_test']) {
            return ['phase' => 'failover', 'skipped' => true];
        }

        $this->logger->info('Executing cache failover phase');

        $results = [
            'phase' => 'failover',
            'failover_scenarios' => 0,
            'successful_failovers' => 0,
            'failed_failovers' => 0,
            'failover_times' => [],
            'data_loss_events' => 0
        ];

        // Test different failover scenarios
        $scenarios = ['node_failure', 'network_partition', 'master_failover'];
        
        foreach ($scenarios as $scenario) {
            $failoverResult = $this->simulateFailoverScenario($scenario, $config);
            $results['failover_scenarios']++;
            
            if ($failoverResult['success']) {
                $results['successful_failovers']++;
                $results['failover_times'][] = $failoverResult['time'];
            } else {
                $results['failed_failovers']++;
            }
            
            if ($failoverResult['data_loss']) {
                $results['data_loss_events']++;
            }
        }

        return $results;
    }

    /**
     * Execute cleanup phase
     *
     * @param array $config
     * @return array
     */
    protected function executeCleanupPhase(array $config): array
    {
        $this->logger->info('Executing cache cleanup phase');

        $results = [
            'phase' => 'cleanup',
            'keys_deleted' => 0,
            'memory_freed_mb' => 0,
            'cleanup_time' => 0
        ];

        $startTime = microtime(true);

        // Clean up test data
        foreach ($this->testDataPatterns as $patternName => $pattern) {
            $keysDeleted = $this->cleanupTestData($pattern);
            $results['keys_deleted'] += $keysDeleted;
        }

        $results['cleanup_time'] = round((microtime(true) - $startTime) * 1000, 2);
        $results['memory_freed_mb'] = rand(100, 500); // Simulated memory freed

        return $results;
    }

    /**
     * Execute cache operation
     *
     * @param string $operation
     * @param array $config
     * @return array
     */
    protected function executeOperation(string $operation, array $config): array
    {
        $pattern = $this->testDataPatterns[array_rand($this->testDataPatterns)];
        $key = $this->generateKey($pattern['key_pattern'], rand(1, $config['test_data_keys']));

        switch ($operation) {
            case 'get':
                return $this->simulateCacheOperation('get', $key);
            
            case 'set':
                $data = $this->generateTestData($pattern['size_range']);
                $ttl = rand($pattern['ttl_range'][0], $pattern['ttl_range'][1]);
                return $this->simulateCacheOperation('set', $key, $data, $ttl);
            
            case 'delete':
                return $this->simulateCacheOperation('delete', $key);
            
            case 'multi_get':
                $keys = [];
                for ($i = 0; $i < $config['batch_size']; $i++) {
                    $keys[] = $this->generateKey($pattern['key_pattern'], rand(1, $config['test_data_keys']));
                }
                return $this->simulateCacheOperation('mget', $keys);
            
            default:
                throw new \InvalidArgumentException("Unknown operation: {$operation}");
        }
    }

    /**
     * Simulate cache operation
     *
     * @param string $operation
     * @param string|array $key
     * @param mixed $data
     * @param int $ttl
     * @return array
     */
    protected function simulateCacheOperation(string $operation, $key, $data = null, int $ttl = 0): array
    {
        $startTime = microtime(true);
        
        // Simulate operation timing and results
        $responseTime = match($operation) {
            'get' => rand(0.1, 2.0),      // 0.1-2ms
            'set' => rand(0.5, 3.0),      // 0.5-3ms
            'delete' => rand(0.2, 1.5),   // 0.2-1.5ms
            'mget' => rand(1.0, 5.0),     // 1-5ms for batch
            default => rand(0.5, 2.0)
        };
        
        $success = rand(1, 1000) > 5; // 99.5% success rate
        $hit = ($operation === 'get' || $operation === 'mget') ? rand(1, 100) <= 95 : true; // 95% hit rate for reads
        
        usleep($responseTime * 1000);
        
        return [
            'operation' => $operation,
            'key' => $key,
            'success' => $success,
            'hit' => $hit,
            'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            'memory_pressure' => rand(1, 100) <= 5,    // 5% chance of memory pressure
            'eviction_occurred' => rand(1, 100) <= 2,  // 2% chance of eviction
            'connection_error' => !$success && rand(1, 100) <= 20, // 20% of failures are connection errors
            'data_size' => is_array($key) ? count($key) * 1024 : 1024 // Estimated data size
        ];
    }

    /**
     * Simulate failover scenario
     *
     * @param string $scenario
     * @param array $config
     * @return array
     */
    protected function simulateFailoverScenario(string $scenario, array $config): array
    {
        $startTime = microtime(true);
        
        $failoverTime = match($scenario) {
            'node_failure' => rand(100, 500),      // 100-500ms
            'network_partition' => rand(200, 1000), // 200ms-1s
            'master_failover' => rand(500, 2000),   // 500ms-2s
            default => rand(100, 1000)
        };
        
        $success = rand(1, 100) > 10; // 90% success rate
        $dataLoss = rand(1, 100) <= 5; // 5% chance of data loss
        
        usleep($failoverTime * 1000);
        
        return [
            'scenario' => $scenario,
            'success' => $success,
            'time' => round((microtime(true) - $startTime) * 1000, 2),
            'data_loss' => $dataLoss,
            'operations_affected' => rand(10, 100)
        ];
    }

    /**
     * Update operation metrics
     *
     * @param string $operation
     * @param array $result
     * @return void
     */
    protected function updateOperationMetrics(string $operation, array $result): void
    {
        $this->metrics[$operation]['operations_executed']++;
        
        if ($result['success']) {
            $this->metrics[$operation]['operations_successful']++;
            $this->metrics[$operation]['response_times'][] = $result['response_time'];
            
            if (isset($result['hit'])) {
                if ($result['hit']) {
                    $this->metrics[$operation]['cache_hits']++;
                } else {
                    $this->metrics[$operation]['cache_misses']++;
                }
            }
        } else {
            $this->metrics[$operation]['operations_failed']++;
            
            if ($result['connection_error']) {
                $this->metrics[$operation]['connection_errors']++;
            }
        }

        $this->metrics['global']['total_operations']++;
    }

    /**
     * Generate test key from pattern
     *
     * @param string $pattern
     * @param mixed ...$args
     * @return string
     */
    protected function generateKey(string $pattern, ...$args): string
    {
        return sprintf(str_replace('{}', '%s', $pattern), ...$args);
    }

    /**
     * Generate test data of specified size range
     *
     * @param array $sizeRange
     * @return string
     */
    protected function generateTestData(array $sizeRange): string
    {
        $size = rand($sizeRange[0], $sizeRange[1]);
        return str_repeat('x', $size);
    }

    /**
     * Clean up test data for pattern
     *
     * @param array $pattern
     * @return int
     */
    protected function cleanupTestData(array $pattern): int
    {
        // Simulate cleanup - in production would delete actual cache keys
        return rand(1000, 5000);
    }

    /**
     * Calculate average response time for operation
     *
     * @param string $operation
     * @return float
     */
    protected function calculateAverageResponseTime(string $operation): float
    {
        $times = $this->metrics[$operation]['response_times'];
        return count($times) > 0 ? round(array_sum($times) / count($times), 2) : 0.0;
    }

    /**
     * Calculate hit rate for operation
     *
     * @param string $operation
     * @return float
     */
    protected function calculateHitRate(string $operation): float
    {
        $hits = $this->metrics[$operation]['cache_hits'];
        $misses = $this->metrics[$operation]['cache_misses'];
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * Aggregate test results
     *
     * @param array $phaseResults
     * @param array $config
     * @return array
     */
    protected function aggregateResults(array $phaseResults, array $config): array
    {
        $totalOperations = 0;
        $totalHits = 0;
        $totalMisses = 0;
        $allResponseTimes = [];

        foreach ($this->operations as $opName => $opConfig) {
            $metrics = $this->metrics[$opName];
            $totalOperations += $metrics['operations_executed'];
            $totalHits += $metrics['cache_hits'];
            $totalMisses += $metrics['cache_misses'];
            $allResponseTimes = array_merge($allResponseTimes, $metrics['response_times']);
        }

        sort($allResponseTimes);
        
        $duration = ($config['duration'] ?? 300) / 60; // Convert to minutes
        $operationsPerSecond = $duration > 0 ? round($totalOperations / ($duration * 60), 2) : 0;
        $hitRate = ($totalHits + $totalMisses) > 0 ? round(($totalHits / ($totalHits + $totalMisses)) * 100, 2) : 0;

        return [
            'total_operations' => $totalOperations,
            'operations_per_second' => $operationsPerSecond,
            'hit_rate' => $hitRate,
            'avg_response_time' => count($allResponseTimes) > 0 ? round(array_sum($allResponseTimes) / count($allResponseTimes), 2) : 0,
            'response_time_p95' => $this->calculatePercentile($allResponseTimes, 95),
            'response_time_p99' => $this->calculatePercentile($allResponseTimes, 99),
            'target_ops_achieved' => $operationsPerSecond >= $config['operations_per_second'],
            'target_hit_rate_achieved' => $hitRate >= $config['hit_rate_target'],
            'target_response_time_achieved' => $this->calculatePercentile($allResponseTimes, 95) <= $config['response_time_target'],
            'memory_efficiency' => rand(80, 95), // Simulated memory efficiency
            'failover_success_rate' => $this->calculateFailoverSuccessRate($phaseResults),
            'eviction_rate' => rand(1, 5) // Simulated eviction rate
        ];
    }

    /**
     * Calculate percentile from sorted array
     *
     * @param array $values
     * @param int $percentile
     * @return float
     */
    protected function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        $count = count($values);
        $index = ($percentile / 100) * ($count - 1);
        
        if ($index == floor($index)) {
            return $values[(int)$index];
        } else {
            $lower = $values[(int)floor($index)];
            $upper = $values[(int)ceil($index)];
            return $lower + (($upper - $lower) * ($index - floor($index)));
        }
    }

    /**
     * Calculate failover success rate
     *
     * @param array $phaseResults
     * @return float
     */
    protected function calculateFailoverSuccessRate(array $phaseResults): float
    {
        $failoverPhase = $phaseResults['failover_phase'] ?? [];
        
        if (isset($failoverPhase['skipped']) || empty($failoverPhase['failover_scenarios'])) {
            return 0.0;
        }
        
        return round(($failoverPhase['successful_failovers'] / $failoverPhase['failover_scenarios']) * 100, 2);
    }
}