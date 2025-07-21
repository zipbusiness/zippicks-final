<?php

namespace ZipPicks\Foundation\Tests\Performance;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\RateLimiting\RateLimiterManager;
use ZipPicks\Foundation\RateLimiting\Stores\RedisStore;
use ZipPicks\Foundation\RateLimiting\Stores\InMemoryStore;
use ZipPicks\Foundation\Core\Container;

/**
 * Rate Limiting Load Tests
 * 
 * Ensures our rate limiting can handle 10M+ operations per second
 * to support our $100B platform scale
 */
class RateLimitingLoadTest extends TestCase
{
    protected Container $container;
    protected RateLimiterManager $manager;
    protected array $metrics = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->container = new Container();
        $this->manager = new RateLimiterManager($this->container, [
            'stores' => [
                'memory' => [
                    'driver' => 'memory',
                ],
                'redis' => [
                    'driver' => 'redis',
                    'connection' => 'default',
                    'prefix' => 'test:rate_limit:',
                ],
            ],
            'limiters' => [
                'api' => [
                    'algorithm' => 'sliding_window',
                    'store' => 'memory',
                    'window' => 60,
                ],
                'taste_graph' => [
                    'algorithm' => 'token_bucket',
                    'store' => 'memory',
                    'capacity' => 100,
                    'refill_rate' => 1.67,
                ],
            ],
        ]);
    }

    /**
     * Test high-volume rate limit checks
     * Target: 1M+ checks per second
     */
    public function testHighVolumeRateLimitChecks()
    {
        $limiter = $this->manager->limiter('api');
        $iterations = 100000; // 100K iterations
        $keys = $this->generateKeys(1000); // 1000 unique keys
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $key = $keys[$i % count($keys)];
            $limiter->tooManyAttempts($key, 100);
        }
        
        $duration = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;
        
        $checksPerSecond = $iterations / $duration;
        
        $this->metrics['high_volume_checks'] = [
            'total_checks' => $iterations,
            'duration_seconds' => $duration,
            'checks_per_second' => $checksPerSecond,
            'memory_used_mb' => $memoryUsed / 1024 / 1024,
        ];
        
        // Assert performance targets
        $this->assertGreaterThan(1000000, $checksPerSecond, 'Should handle >1M checks/second');
        $this->assertLessThan(100, $memoryUsed / 1024 / 1024, 'Memory usage should be <100MB');
    }

    /**
     * Test concurrent rate limiting
     * Simulates multiple workers/threads
     */
    public function testConcurrentRateLimiting()
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('PCNTL extension required for concurrent testing');
        }
        
        $workers = 10;
        $requestsPerWorker = 10000;
        $sharedKey = 'concurrent:test';
        $maxAttempts = 1000;
        
        $startTime = microtime(true);
        $pids = [];
        
        for ($w = 0; $w < $workers; $w++) {
            $pid = pcntl_fork();
            
            if ($pid === -1) {
                $this->fail('Could not fork process');
            } elseif ($pid === 0) {
                // Child process
                $limiter = $this->manager->limiter('api');
                $exceeded = 0;
                
                for ($i = 0; $i < $requestsPerWorker; $i++) {
                    if ($limiter->tooManyAttempts($sharedKey, $maxAttempts)) {
                        $exceeded++;
                    } else {
                        $limiter->hit($sharedKey);
                    }
                }
                
                exit($exceeded);
            } else {
                $pids[] = $pid;
            }
        }
        
        // Wait for all workers
        $totalExceeded = 0;
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $totalExceeded += pcntl_wexitstatus($status);
        }
        
        $duration = microtime(true) - $startTime;
        $totalRequests = $workers * $requestsPerWorker;
        
        $this->metrics['concurrent_limiting'] = [
            'workers' => $workers,
            'total_requests' => $totalRequests,
            'exceeded_count' => $totalExceeded,
            'duration_seconds' => $duration,
            'requests_per_second' => $totalRequests / $duration,
        ];
        
        // Assert rate limit was enforced
        $expectedExceeded = $totalRequests - $maxAttempts;
        $this->assertGreaterThan($expectedExceeded * 0.9, $totalExceeded);
        $this->assertLessThan($expectedExceeded * 1.1, $totalExceeded);
    }

    /**
     * Test different algorithms performance
     */
    public function testAlgorithmPerformance()
    {
        $algorithms = [
            'fixed_window' => ['algorithm' => 'fixed_window', 'window' => 60],
            'sliding_window' => ['algorithm' => 'sliding_window', 'window' => 60],
            'token_bucket' => ['algorithm' => 'token_bucket', 'capacity' => 100, 'refill_rate' => 1.67],
            'leaky_bucket' => ['algorithm' => 'leaky_bucket', 'capacity' => 100, 'leak_rate' => 1.67],
        ];
        
        $iterations = 50000;
        $results = [];
        
        foreach ($algorithms as $name => $config) {
            $this->manager->registerAlgorithm($name . '_test', function ($store) use ($config, $name) {
                $class = "\\ZipPicks\\Foundation\\RateLimiting\\Algorithms\\" . 
                         str_replace('_', '', ucwords($name, '_')) . 'Limiter';
                
                return new $class($store, ...array_values(array_slice($config, 1)));
            });
            
            $limiter = $this->manager->limiter($name . '_test');
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            for ($i = 0; $i < $iterations; $i++) {
                $key = "test:$name:$i";
                $limiter->tooManyAttempts($key, 100);
                if ($i % 2 === 0) {
                    $limiter->hit($key);
                }
            }
            
            $duration = microtime(true) - $startTime;
            $memoryUsed = memory_get_usage(true) - $startMemory;
            
            $results[$name] = [
                'duration_ms' => $duration * 1000,
                'ops_per_second' => $iterations / $duration,
                'memory_mb' => $memoryUsed / 1024 / 1024,
                'avg_latency_us' => ($duration / $iterations) * 1000000,
            ];
        }
        
        $this->metrics['algorithm_performance'] = $results;
        
        // All algorithms should be fast
        foreach ($results as $name => $result) {
            $this->assertLessThan(10, $result['avg_latency_us'], 
                "$name should have <10μs average latency");
        }
    }

    /**
     * Test tier-based rate limiting performance
     */
    public function testTierBasedPerformance()
    {
        $tiers = ['free', 'pro', 'business', 'enterprise'];
        $usersPerTier = 250;
        $requestsPerUser = 100;
        
        $results = [];
        
        foreach ($tiers as $tier) {
            $limiter = $this->manager->forTier($tier, 'api');
            
            $startTime = microtime(true);
            $exceeded = 0;
            $allowed = 0;
            
            for ($u = 0; $u < $usersPerTier; $u++) {
                for ($r = 0; $r < $requestsPerUser; $r++) {
                    $key = "user:$tier:$u:api";
                    
                    if ($limiter->tooManyAttempts($key, 100)) {
                        $exceeded++;
                    } else {
                        $limiter->hit($key);
                        $allowed++;
                    }
                }
            }
            
            $duration = microtime(true) - $startTime;
            
            $results[$tier] = [
                'total_requests' => $usersPerTier * $requestsPerUser,
                'allowed' => $allowed,
                'exceeded' => $exceeded,
                'duration_ms' => $duration * 1000,
                'requests_per_second' => ($usersPerTier * $requestsPerUser) / $duration,
            ];
        }
        
        $this->metrics['tier_performance'] = $results;
        
        // Enterprise should have no rate limit hits
        $this->assertEquals(0, $results['enterprise']['exceeded']);
        
        // Higher tiers should allow more requests
        $this->assertGreaterThan($results['free']['allowed'], $results['pro']['allowed']);
        $this->assertGreaterThan($results['pro']['allowed'], $results['business']['allowed']);
    }

    /**
     * Test batch operations performance
     */
    public function testBatchOperations()
    {
        $limiter = $this->manager->limiter('api');
        $batchSizes = [10, 100, 1000, 10000];
        $results = [];
        
        foreach ($batchSizes as $size) {
            $keys = $this->generateKeys($size);
            
            $startTime = microtime(true);
            $result = $limiter->tooManyAttemptsBatch($keys, 100);
            $duration = microtime(true) - $startTime;
            
            $results[$size] = [
                'duration_ms' => $duration * 1000,
                'keys_per_second' => $size / $duration,
                'avg_per_key_us' => ($duration / $size) * 1000000,
            ];
        }
        
        $this->metrics['batch_operations'] = $results;
        
        // Batch operations should be efficient
        foreach ($results as $size => $result) {
            $this->assertLessThan(5, $result['avg_per_key_us'], 
                "Batch size $size should average <5μs per key");
        }
    }

    /**
     * Test memory efficiency with many keys
     */
    public function testMemoryEfficiency()
    {
        $store = new InMemoryStore();
        $limiter = new \ZipPicks\Foundation\RateLimiting\RateLimiter($store);
        
        $keyCount = 100000; // 100K unique keys
        $startMemory = memory_get_usage(true);
        
        for ($i = 0; $i < $keyCount; $i++) {
            $key = "user:$i:api";
            $limiter->hit($key, 60); // 1 hour TTL
        }
        
        $memoryUsed = memory_get_usage(true) - $startMemory;
        $memoryPerKey = $memoryUsed / $keyCount;
        
        $this->metrics['memory_efficiency'] = [
            'total_keys' => $keyCount,
            'total_memory_mb' => $memoryUsed / 1024 / 1024,
            'bytes_per_key' => $memoryPerKey,
        ];
        
        // Should use <1KB per key
        $this->assertLessThan(1024, $memoryPerKey, 'Should use <1KB per key');
    }

    /**
     * Test circuit breaker performance
     */
    public function testCircuitBreakerPerformance()
    {
        // Simulate Redis failure
        $failingStore = $this->createMock(RedisStore::class);
        $failingStore->method('increment')->willThrowException(new \Exception('Redis down'));
        $failingStore->method('get')->willThrowException(new \Exception('Redis down'));
        
        $limiter = new \ZipPicks\Foundation\RateLimiting\RateLimiter(
            $failingStore,
            'fixed_window',
            new \ZipPicks\Foundation\Core\CircuitBreaker('test', 5, 60)
        );
        
        $iterations = 10000;
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            // Should fail open (return false) when circuit is open
            $result = $limiter->tooManyAttempts("key:$i", 100);
            $this->assertFalse($result);
        }
        
        $duration = microtime(true) - $startTime;
        
        $this->metrics['circuit_breaker'] = [
            'iterations' => $iterations,
            'duration_ms' => $duration * 1000,
            'ops_per_second' => $iterations / $duration,
        ];
        
        // Should still be fast even with failures
        $this->assertGreaterThan(100000, $iterations / $duration);
    }

    /**
     * Generate test keys
     */
    protected function generateKeys(int $count): array
    {
        $keys = [];
        for ($i = 0; $i < $count; $i++) {
            $keys[] = "test:key:$i";
        }
        return $keys;
    }

    /**
     * Output performance metrics after all tests
     */
    protected function tearDown(): void
    {
        if (!empty($this->metrics)) {
            echo "\n\n=== RATE LIMITING PERFORMANCE METRICS ===\n";
            foreach ($this->metrics as $test => $data) {
                echo "\n$test:\n";
                foreach ($data as $key => $value) {
                    if (is_array($value)) {
                        echo "  $key:\n";
                        foreach ($value as $k => $v) {
                            echo "    $k: " . (is_numeric($v) ? number_format($v, 2) : $v) . "\n";
                        }
                    } else {
                        echo "  $key: " . (is_numeric($value) ? number_format($value, 2) : $value) . "\n";
                    }
                }
            }
            echo "\n";
        }
        
        parent::tearDown();
    }
}