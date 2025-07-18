<?php
/**
 * Base test case for ZipPicks Vibes performance tests
 *
 * @package ZipPicks_Vibes\Tests
 */

namespace ZipPicks\Vibes\Tests;

/**
 * Performance test case class
 */
abstract class PerformanceTestCase extends IntegrationTestCase {
    
    /**
     * @var array Performance benchmarks
     */
    protected $benchmarks = [];
    
    /**
     * @var int Default iterations for performance tests
     */
    protected $iterations = 100;
    
    /**
     * @var array Performance thresholds
     */
    protected $thresholds = [
        'api_response' => 200, // milliseconds
        'database_query' => 50, // milliseconds
        'cache_operation' => 5, // milliseconds
        'memory_usage' => 10 * 1024 * 1024, // 10MB
        'render_time' => 100, // milliseconds
        'ajax_request' => 150, // milliseconds
        'batch_operation' => 1000, // milliseconds
        'search_query' => 100, // milliseconds
    ];
    
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Disable query monitor and debug plugins
        add_filter('qm/process', '__return_false');
        add_filter('debug_bar_enable', '__return_false');
        
        // Reset benchmarks
        $this->benchmarks = [];
    }
    
    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        // Output performance report if any benchmarks were recorded
        if (!empty($this->benchmarks)) {
            $this->output_performance_report();
        }
        
        parent::tearDown();
    }
    
    /**
     * Benchmark a callable
     *
     * @param string $name Benchmark name
     * @param callable $callback Function to benchmark
     * @param int $iterations Number of iterations
     * @return array Benchmark results
     */
    protected function benchmark($name, callable $callback, $iterations = null) {
        $iterations = $iterations ?: $this->iterations;
        
        // Warm up (run once without measuring)
        $callback();
        
        // Collect samples
        $times = [];
        $memory = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start_time = microtime(true);
            $start_memory = memory_get_usage();
            
            $callback();
            
            $times[] = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
            $memory[] = memory_get_usage() - $start_memory;
        }
        
        // Calculate statistics
        $results = [
            'name' => $name,
            'iterations' => $iterations,
            'times' => [
                'min' => min($times),
                'max' => max($times),
                'avg' => array_sum($times) / count($times),
                'median' => $this->calculate_median($times),
                'p95' => $this->calculate_percentile($times, 95),
                'p99' => $this->calculate_percentile($times, 99),
            ],
            'memory' => [
                'min' => min($memory),
                'max' => max($memory),
                'avg' => array_sum($memory) / count($memory),
            ],
        ];
        
        $this->benchmarks[$name] = $results;
        
        return $results;
    }
    
    /**
     * Assert performance is within threshold
     *
     * @param string $benchmark_name Benchmark name
     * @param string $metric Metric to check (avg, p95, p99)
     * @param float $threshold Maximum allowed value
     * @param string $message Optional message
     */
    protected function assertPerformance($benchmark_name, $metric = 'avg', $threshold = null, $message = '') {
        $this->assertArrayHasKey($benchmark_name, $this->benchmarks, "Benchmark '$benchmark_name' not found");
        
        $benchmark = $this->benchmarks[$benchmark_name];
        $value = $benchmark['times'][$metric];
        
        if ($threshold === null) {
            $threshold = $this->thresholds['api_response'];
        }
        
        $this->assertLessThanOrEqual(
            $threshold,
            $value,
            $message ?: "Performance benchmark '$benchmark_name' ($metric: {$value}ms) exceeded threshold ({$threshold}ms)"
        );
    }
    
    /**
     * Assert memory usage is within threshold
     *
     * @param string $benchmark_name Benchmark name
     * @param int $threshold Maximum allowed bytes
     * @param string $message Optional message
     */
    protected function assertMemoryUsage($benchmark_name, $threshold = null, $message = '') {
        $this->assertArrayHasKey($benchmark_name, $this->benchmarks, "Benchmark '$benchmark_name' not found");
        
        $benchmark = $this->benchmarks[$benchmark_name];
        $value = $benchmark['memory']['max'];
        
        if ($threshold === null) {
            $threshold = $this->thresholds['memory_usage'];
        }
        
        $this->assertLessThanOrEqual(
            $threshold,
            $value,
            $message ?: sprintf(
                "Memory usage for '%s' (%.2fMB) exceeded threshold (%.2fMB)",
                $benchmark_name,
                $value / 1024 / 1024,
                $threshold / 1024 / 1024
            )
        );
    }
    
    /**
     * Measure database query performance
     *
     * @param callable $callback Query callback
     * @param string $name Query name
     * @return array Query statistics
     */
    protected function measure_query_performance(callable $callback, $name = 'query') {
        global $wpdb;
        
        // Enable query logging
        $wpdb->show_errors = false;
        $wpdb->suppress_errors = true;
        
        $start_queries = $wpdb->num_queries;
        
        $results = $this->benchmark($name, $callback);
        
        $query_count = $wpdb->num_queries - $start_queries;
        $results['query_count'] = $query_count / $results['iterations'];
        
        return $results;
    }
    
    /**
     * Calculate median value
     *
     * @param array $values
     * @return float
     */
    protected function calculate_median(array $values) {
        sort($values);
        $count = count($values);
        
        if ($count % 2 == 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        }
        
        return $values[floor($count / 2)];
    }
    
    /**
     * Calculate percentile value
     *
     * @param array $values
     * @param int $percentile
     * @return float
     */
    protected function calculate_percentile(array $values, $percentile) {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        
        if (floor($index) == $index) {
            return $values[$index];
        }
        
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;
        
        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }
    
    /**
     * Output performance report
     */
    protected function output_performance_report() {
        echo "\n\n=== Performance Report ===\n";
        
        foreach ($this->benchmarks as $benchmark) {
            echo "\n{$benchmark['name']}:\n";
            echo "  Iterations: {$benchmark['iterations']}\n";
            echo "  Time (ms):\n";
            echo "    Min: " . number_format($benchmark['times']['min'], 2) . "\n";
            echo "    Avg: " . number_format($benchmark['times']['avg'], 2) . "\n";
            echo "    P95: " . number_format($benchmark['times']['p95'], 2) . "\n";
            echo "    P99: " . number_format($benchmark['times']['p99'], 2) . "\n";
            echo "    Max: " . number_format($benchmark['times']['max'], 2) . "\n";
            echo "  Memory (MB):\n";
            echo "    Min: " . number_format($benchmark['memory']['min'] / 1024 / 1024, 2) . "\n";
            echo "    Avg: " . number_format($benchmark['memory']['avg'] / 1024 / 1024, 2) . "\n";
            echo "    Max: " . number_format($benchmark['memory']['max'] / 1024 / 1024, 2) . "\n";
            
            if (isset($benchmark['query_count'])) {
                echo "  Queries: " . number_format($benchmark['query_count'], 2) . "\n";
            }
        }
        
        echo "\n========================\n\n";
    }
    
    /**
     * Create large dataset for performance testing
     *
     * @param int $count Number of items to create
     * @return array Created item IDs
     */
    protected function create_large_dataset($count = 1000) {
        $ids = [];
        
        // Disable term counting for performance
        wp_defer_term_counting(true);
        
        for ($i = 0; $i < $count; $i++) {
            $vibe_id = $this->create_test_vibe([
                'name' => "Performance Test Vibe $i",
                'slug' => "perf-test-vibe-$i",
                'meta' => [
                    'icon' => '🎯',
                    'color' => '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT),
                    'priority' => mt_rand(1, 100),
                    'status' => 'active',
                ]
            ]);
            
            if (!is_wp_error($vibe_id)) {
                $ids[] = $vibe_id;
            }
        }
        
        // Re-enable term counting
        wp_defer_term_counting(false);
        
        return $ids;
    }
    
    /**
     * Measure cache performance
     *
     * @param callable $callback Cache operation callback
     * @param string $name Operation name
     * @return array Performance results
     */
    protected function measure_cache_performance(callable $callback, $name = 'cache_operation') {
        $cache = $this->get_service('cache');
        
        // Clear cache before testing
        if ($cache && method_exists($cache, 'flush')) {
            $cache->flush();
        }
        
        return $this->benchmark($name, $callback);
    }
    
    /**
     * Measure API endpoint performance
     *
     * @param string $endpoint Endpoint URL
     * @param string $method HTTP method
     * @param array $params Request parameters
     * @return array Performance results
     */
    protected function measure_api_performance($endpoint, $method = 'GET', $params = []) {
        $name = "{$method} {$endpoint}";
        
        return $this->benchmark($name, function() use ($endpoint, $method, $params) {
            $request = new \WP_REST_Request($method, $endpoint);
            
            if ($method === 'GET') {
                $request->set_query_params($params);
            } else {
                $request->set_body_params($params);
            }
            
            $response = rest_do_request($request);
            
            $this->assertNotWPError($response);
            $this->assertEquals(200, $response->get_status());
        });
    }
    
    /**
     * Stress test with concurrent operations
     *
     * @param callable $operation Operation to test
     * @param int $concurrency Number of concurrent operations
     * @param string $name Test name
     * @return array Performance results
     */
    protected function stress_test(callable $operation, $concurrency = 10, $name = 'stress_test') {
        $results = [];
        
        // Simulate concurrent operations
        for ($i = 0; $i < $concurrency; $i++) {
            $start = microtime(true);
            $operation();
            $results[] = (microtime(true) - $start) * 1000;
        }
        
        return [
            'name' => $name,
            'concurrency' => $concurrency,
            'total_time' => array_sum($results),
            'avg_time' => array_sum($results) / count($results),
            'max_time' => max($results),
            'throughput' => $concurrency / (array_sum($results) / 1000), // ops/sec
        ];
    }
    
    /**
     * Profile memory usage during operation
     *
     * @param callable $operation Operation to profile
     * @return array Memory profile
     */
    protected function profile_memory(callable $operation) {
        $profile = [
            'before' => memory_get_usage(true),
            'peak_before' => memory_get_peak_usage(true),
        ];
        
        $operation();
        
        $profile['after'] = memory_get_usage(true);
        $profile['peak_after'] = memory_get_peak_usage(true);
        $profile['used'] = $profile['after'] - $profile['before'];
        $profile['peak_increase'] = $profile['peak_after'] - $profile['peak_before'];
        
        return $profile;
    }
    
    /**
     * Assert response time is within threshold
     *
     * @param float $actual Actual response time in milliseconds
     * @param string $type Type of operation
     * @param string $message Optional message
     */
    protected function assertResponseTime($actual, $type = 'api_response', $message = '') {
        $threshold = $this->thresholds[$type] ?? $this->thresholds['api_response'];
        
        $this->assertLessThanOrEqual(
            $threshold,
            $actual,
            $message ?: "Response time ({$actual}ms) exceeded threshold ({$threshold}ms) for {$type}"
        );
    }
    
    /**
     * Generate performance summary
     *
     * @return array Performance summary
     */
    protected function generate_performance_summary() {
        $summary = [
            'total_benchmarks' => count($this->benchmarks),
            'passed' => 0,
            'failed' => 0,
            'warnings' => 0,
            'slowest' => null,
            'fastest' => null,
        ];
        
        foreach ($this->benchmarks as $benchmark) {
            $avg_time = $benchmark['times']['avg'];
            
            // Determine status
            if ($avg_time < $this->thresholds['api_response'] * 0.5) {
                $summary['passed']++;
            } elseif ($avg_time < $this->thresholds['api_response']) {
                $summary['warnings']++;
            } else {
                $summary['failed']++;
            }
            
            // Track slowest/fastest
            if (!$summary['slowest'] || $avg_time > $summary['slowest']['time']) {
                $summary['slowest'] = ['name' => $benchmark['name'], 'time' => $avg_time];
            }
            if (!$summary['fastest'] || $avg_time < $summary['fastest']['time']) {
                $summary['fastest'] = ['name' => $benchmark['name'], 'time' => $avg_time];
            }
        }
        
        return $summary;
    }
}