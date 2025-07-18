<?php
/**
 * Cache Health Check
 * 
 * Checks cache system availability and performance
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\HealthCheck\Checks;

use ZipPicksVibes\HealthCheck\HealthCheckInterface;
use ZipPicksVibes\HealthCheck\HealthCheckResult;

/**
 * Class CacheCheck
 * 
 * Enhanced cache health check with hit ratio tracking, detailed performance analysis,
 * and comprehensive cache system monitoring
 */
class CacheCheck implements HealthCheckInterface {
    
    /**
     * Cache instance
     * 
     * @var mixed
     */
    private $cache;
    
    /**
     * Constructor
     * 
     * @param mixed $cache
     */
    public function __construct($cache = null) {
        $this->cache = $cache;
    }
    
    /**
     * Execute health check (legacy method)
     * 
     * @return HealthCheckResult
     */
    public function check(): HealthCheckResult {
        return $this->run();
    }
    
    /**
     * Execute enhanced cache health check
     * 
     * @return HealthCheckResult
     */
    public function run(): HealthCheckResult {
        $startTime = microtime(true);
        
        // Enhanced cache service availability check
        if (!$this->cache) {
            return HealthCheckResult::warn(
                $this->getName(),
                'Cache service is not available (graceful degradation)',
                [
                    'status' => HealthCheckResult::WARN,
                    'message' => 'Cache service is not registered with the service container',
                    'fallback_guidance' => 'System will function but performance may be degraded. Consider Redis or Memcached setup.',
                    'degradation_impact' => 'Performance reduced, increased database load',
                    'setup_recommendations' => [
                        'Install Redis or Memcached',
                        'Configure WordPress object cache',
                        'Verify cache service registration in Foundation',
                        'Check server memory allocation'
                    ],
                    'check_category' => 'cache',
                    'system_impact' => 'medium'
                ],
                (microtime(true) - $startTime) * 1000
            );
        }
        
        try {
            // Test cache operations
            $testKey = 'zippicks_vibes_health_check_test_' . uniqid();
            $testValue = ['test' => true, 'timestamp' => time()];
            
            // Test set operation
            $setStart = microtime(true);
            $setResult = $this->cache->set($testKey, $testValue, 60);
            $setTime = (microtime(true) - $setStart) * 1000;
            
            if (!$setResult) {
                return HealthCheckResult::fail(
                    $this->getName(),
                    'Cache write operation failed - data persistence compromised',
                    [
                        'status' => HealthCheckResult::FAIL,
                        'operation' => 'set',
                        'test_key' => $testKey,
                        'test_data_size' => strlen(serialize($testValue)),
                        'operation_time_ms' => $setTime,
                        'fallback_guidance' => 'Cache write failures indicate storage issues. Check Redis/Memcached connectivity.',
                        'troubleshooting_steps' => [
                            'Check cache server status (Redis/Memcached)',
                            'Verify network connectivity to cache server',
                            'Check available memory/storage',
                            'Review cache server logs'
                        ],
                        'check_category' => 'cache',
                        'data_integrity_risk' => 'high'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            }
            
            // Test get operation
            $getStart = microtime(true);
            $getValue = $this->cache->get($testKey);
            $getTime = (microtime(true) - $getStart) * 1000;
            
            if ($getValue !== $testValue) {
                return HealthCheckResult::fail(
                    $this->getName(),
                    'Cache data integrity failure - read/write mismatch detected',
                    [
                        'status' => HealthCheckResult::FAIL,
                        'operation' => 'get',
                        'expected_data' => $testValue,
                        'received_data' => $getValue,
                        'data_match' => false,
                        'read_time_ms' => $getTime,
                        'write_time_ms' => $setTime,
                        'fallback_guidance' => 'Data corruption detected. Flush cache and investigate serialization issues.',
                        'troubleshooting_steps' => [
                            'Flush all cache data immediately',
                            'Check cache serialization/deserialization',
                            'Verify cache server data integrity',
                            'Review recent cache configuration changes'
                        ],
                        'check_category' => 'cache',
                        'data_integrity_risk' => 'critical',
                        'recommended_action' => 'immediate_cache_flush'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            }
            
            // Test delete operation with timing
            $deleteStart = microtime(true);
            $deleteResult = $this->cache->delete($testKey);
            $deleteTime = (microtime(true) - $deleteStart) * 1000;
            
            if (!$deleteResult) {
                return HealthCheckResult::warn(
                    $this->getName(),
                    'Cache delete operation failed - cleanup issues detected',
                    [
                        'status' => HealthCheckResult::WARN,
                        'operation' => 'delete',
                        'test_key' => $testKey,
                        'delete_time_ms' => $deleteTime,
                        'fallback_guidance' => 'Cache cleanup failing may lead to stale data. Monitor cache memory usage.',
                        'monitoring_suggestions' => [
                            'Monitor cache memory usage',
                            'Set up automated cache cleanup',
                            'Review TTL configurations',
                            'Check for cache key naming conflicts'
                        ],
                        'check_category' => 'cache',
                        'memory_leak_risk' => 'medium'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            }
            
            // Enhanced cache statistics and performance analysis
            $stats = $this->getCacheStatistics();
            $adapterInfo = $this->getAdapterInformation();
            $performanceMetrics = $this->analyzePerformanceMetrics($setTime, $getTime, $deleteTime);
            $hitRatioAnalysis = $this->calculateHitRatioMetrics($stats);
            
            // Enhanced performance threshold analysis
            $totalTime = (microtime(true) - $startTime) * 1000;
            $performanceIssues = $this->identifyPerformanceIssues($setTime, $getTime, $deleteTime);
            
            if (!empty($performanceIssues)) {
                $severity = $performanceIssues['severity'];
                $resultMethod = $severity === 'critical' ? 'fail' : 'warn';
                $status = $severity === 'critical' ? HealthCheckResult::FAIL : HealthCheckResult::WARN;
                
                return HealthCheckResult::$resultMethod(
                    $this->getName(),
                    sprintf('Cache performance issues detected (%s)', $severity),
                    [
                        'status' => $status,
                        'performance_issues' => $performanceIssues,
                        'operation_times' => [
                            'set_ms' => $setTime,
                            'get_ms' => $getTime,
                            'delete_ms' => $deleteTime
                        ],
                        'performance_metrics' => $performanceMetrics,
                        'hit_ratio_analysis' => $hitRatioAnalysis,
                        'adapter_info' => $adapterInfo,
                        'cache_statistics' => $stats,
                        'fallback_guidance' => $this->getPerformanceGuidance($performanceIssues),
                        'check_category' => 'cache',
                        'optimization_priority' => $severity
                    ],
                    $totalTime
                );
            }
            
            return HealthCheckResult::pass(
                $this->getName(),
                sprintf('Cache system is optimal (%.2fms avg operation time)', ($setTime + $getTime + $deleteTime) / 3),
                [
                    'status' => HealthCheckResult::PASS,
                    'operation_times' => [
                        'set_ms' => $setTime,
                        'get_ms' => $getTime,
                        'delete_ms' => $deleteTime,
                        'average_ms' => ($setTime + $getTime + $deleteTime) / 3
                    ],
                    'performance_metrics' => $performanceMetrics,
                    'hit_ratio_analysis' => $hitRatioAnalysis,
                    'cache_health_score' => $this->calculateCacheHealthScore($setTime, $getTime, $deleteTime, $stats),
                    'adapter_info' => $adapterInfo,
                    'cache_statistics' => $stats,
                    'data_integrity' => 'verified',
                    'last_test_timestamp' => date('Y-m-d H:i:s'),
                    'check_category' => 'cache',
                    'system_efficiency' => $this->calculateEfficiencyRating($performanceMetrics)
                ],
                $totalTime
            );
            
        } catch (\Exception $e) {
            return HealthCheckResult::fail(
                $this->getName(),
                'Cache health check failed with exception: ' . $e->getMessage(),
                [
                    'status' => HealthCheckResult::FAIL,
                    'exception' => get_class($e),
                    'error_message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'fallback_guidance' => 'Cache system error requires immediate attention. Check cache service status.',
                    'emergency_steps' => [
                        'Verify cache service is running (Redis/Memcached)',
                        'Check network connectivity to cache server',
                        'Review cache configuration in Foundation',
                        'Consider temporary cache disabling if critical'
                    ],
                    'check_category' => 'cache',
                    'system_stability_risk' => 'high'
                ],
                (microtime(true) - $startTime) * 1000
            );
        }
    }
    
    /**
     * Get check name
     * 
     * @return string
     */
    public function getName(): string {
        return 'cache';
    }
    
    /**
     * Get check description
     * 
     * @return string
     */
    public function getDescription(): string {
        return 'Checks cache system availability and performance';
    }
    
    /**
     * Get check priority
     * 
     * @return int
     */
    public function getPriority(): int {
        return 90; // High priority, but not as critical as database
    }
    
    /**
     * Whether this check is critical
     * 
     * @return bool
     */
    public function isCritical(): bool {
        return false; // Cache is important but not critical
    }
    
    /**
     * Get check category for aggregation
     * 
     * @return string
     */
    public function getCategory(): string {
        return 'cache';
    }
    
    /**
     * Get estimated execution duration
     * 
     * @return int
     */
    public function getEstimatedDuration(): int {
        return 300; // 300ms estimated
    }
    
    /**
     * Check if monitoring is enabled
     * 
     * @return bool
     */
    public function isMonitoringEnabled(): bool {
        return true;
    }
    
    /**
     * Get comprehensive cache statistics
     * 
     * @return array
     */
    private function getCacheStatistics(): array {
        $stats = [];
        
        if ($this->cache && method_exists($this->cache, 'stats')) {
            $stats = $this->cache->stats();
        }
        
        // Add default structure if stats not available
        return array_merge([
            'hits' => 0,
            'misses' => 0,
            'hit_ratio' => 0,
            'memory_usage' => 0,
            'key_count' => 0,
            'uptime' => 0
        ], $stats);
    }
    
    /**
     * Get detailed adapter information
     * 
     * @return array
     */
    private function getAdapterInformation(): array {
        $info = ['type' => 'unknown'];
        
        if ($this->cache && method_exists($this->cache, 'getAdapter')) {
            $adapter = $this->cache->getAdapter();
            $info = [
                'type' => get_class($adapter),
                'backend' => $this->identifyBackendType($adapter),
                'configuration' => $this->getAdapterConfig($adapter)
            ];
        }
        
        return $info;
    }
    
    /**
     * Analyze performance metrics
     * 
     * @param float $setTime
     * @param float $getTime
     * @param float $deleteTime
     * @return array
     */
    private function analyzePerformanceMetrics(float $setTime, float $getTime, float $deleteTime): array {
        $avgTime = ($setTime + $getTime + $deleteTime) / 3;
        
        return [
            'average_operation_time_ms' => $avgTime,
            'set_performance_rating' => $this->rateOperationTime($setTime),
            'get_performance_rating' => $this->rateOperationTime($getTime),
            'delete_performance_rating' => $this->rateOperationTime($deleteTime),
            'overall_performance_rating' => $this->rateOperationTime($avgTime),
            'performance_consistency' => $this->calculateConsistency([$setTime, $getTime, $deleteTime]),
            'throughput_ops_per_second' => 1000 / max(1, $avgTime)
        ];
    }
    
    /**
     * Calculate hit ratio metrics
     * 
     * @param array $stats
     * @return array
     */
    private function calculateHitRatioMetrics(array $stats): array {
        $hits = $stats['hits'] ?? 0;
        $misses = $stats['misses'] ?? 0;
        $total = $hits + $misses;
        
        $hitRatio = $total > 0 ? ($hits / $total) * 100 : 0;
        
        return [
            'hit_ratio_percent' => $hitRatio,
            'total_requests' => $total,
            'cache_hits' => $hits,
            'cache_misses' => $misses,
            'hit_ratio_rating' => $this->rateHitRatio($hitRatio),
            'efficiency_score' => $this->calculateEfficiencyScore($hitRatio, $stats)
        ];
    }
    
    /**
     * Identify performance issues
     * 
     * @param float $setTime
     * @param float $getTime
     * @param float $deleteTime
     * @return array
     */
    private function identifyPerformanceIssues(float $setTime, float $getTime, float $deleteTime): array {
        $issues = [];
        $severity = 'none';
        
        if ($setTime > 100 || $getTime > 100 || $deleteTime > 100) {
            $severity = 'critical';
            $issues[] = 'Cache operations exceeding 100ms threshold';
        } elseif ($setTime > 50 || $getTime > 50 || $deleteTime > 50) {
            $severity = 'warning';
            $issues[] = 'Cache operations slower than optimal (>50ms)';
        }
        
        if (abs($setTime - $getTime) > 20) {
            $issues[] = 'Inconsistent operation times detected';
            if ($severity === 'none') $severity = 'warning';
        }
        
        return [
            'severity' => $severity,
            'issues' => $issues,
            'thresholds' => [
                'optimal' => '<= 10ms',
                'good' => '<= 25ms',
                'acceptable' => '<= 50ms',
                'slow' => '<= 100ms',
                'critical' => '> 100ms'
            ]
        ];
    }
    
    /**
     * Get performance guidance based on issues
     * 
     * @param array $issues
     * @return string
     */
    private function getPerformanceGuidance(array $issues): string {
        $severity = $issues['severity'];
        
        switch ($severity) {
            case 'critical':
                return 'Immediate optimization required. Check cache server resources, network latency, and consider cache configuration tuning.';
            case 'warning':
                return 'Performance can be improved. Consider cache server optimization, data structure review, or upgrading cache backend.';
            default:
                return 'Performance is within acceptable limits.';
        }
    }
    
    /**
     * Rate operation time performance
     * 
     * @param float $time
     * @return string
     */
    private function rateOperationTime(float $time): string {
        if ($time <= 10) return 'A+';
        if ($time <= 25) return 'A';
        if ($time <= 50) return 'B';
        if ($time <= 100) return 'C';
        return 'F';
    }
    
    /**
     * Rate hit ratio performance
     * 
     * @param float $hitRatio
     * @return string
     */
    private function rateHitRatio(float $hitRatio): string {
        if ($hitRatio >= 95) return 'A+';
        if ($hitRatio >= 85) return 'A';
        if ($hitRatio >= 75) return 'B';
        if ($hitRatio >= 60) return 'C';
        return 'F';
    }
    
    /**
     * Calculate performance consistency
     * 
     * @param array $times
     * @return string
     */
    private function calculateConsistency(array $times): string {
        $avg = array_sum($times) / count($times);
        $variance = array_sum(array_map(fn($t) => pow($t - $avg, 2), $times)) / count($times);
        $stdDev = sqrt($variance);
        
        if ($stdDev <= 5) return 'excellent';
        if ($stdDev <= 15) return 'good';
        if ($stdDev <= 30) return 'fair';
        return 'poor';
    }
    
    /**
     * Calculate cache health score
     * 
     * @param float $setTime
     * @param float $getTime
     * @param float $deleteTime
     * @param array $stats
     * @return int
     */
    private function calculateCacheHealthScore(float $setTime, float $getTime, float $deleteTime, array $stats): int {
        $score = 100;
        
        // Performance scoring
        $avgTime = ($setTime + $getTime + $deleteTime) / 3;
        if ($avgTime > 100) $score -= 40;
        elseif ($avgTime > 50) $score -= 20;
        elseif ($avgTime > 25) $score -= 10;
        elseif ($avgTime > 10) $score -= 5;
        
        // Hit ratio scoring
        $hitRatio = $this->calculateHitRatioMetrics($stats)['hit_ratio_percent'];
        if ($hitRatio < 60) $score -= 30;
        elseif ($hitRatio < 75) $score -= 20;
        elseif ($hitRatio < 85) $score -= 10;
        elseif ($hitRatio < 95) $score -= 5;
        
        return max(0, min(100, $score));
    }
    
    /**
     * Calculate efficiency rating
     * 
     * @param array $metrics
     * @return string
     */
    private function calculateEfficiencyRating(array $metrics): string {
        $rating = $metrics['overall_performance_rating'];
        
        switch ($rating) {
            case 'A+': return 'excellent';
            case 'A': return 'very_good';
            case 'B': return 'good';
            case 'C': return 'fair';
            default: return 'poor';
        }
    }
    
    /**
     * Calculate efficiency score
     * 
     * @param float $hitRatio
     * @param array $stats
     * @return int
     */
    private function calculateEfficiencyScore(float $hitRatio, array $stats): int {
        $baseScore = min(100, $hitRatio);
        
        // Bonus for high request volume (indicates active usage)
        $totalRequests = ($stats['hits'] ?? 0) + ($stats['misses'] ?? 0);
        if ($totalRequests > 1000) $baseScore += 5;
        
        return min(100, $baseScore);
    }
    
    /**
     * Identify backend type
     * 
     * @param mixed $adapter
     * @return string
     */
    private function identifyBackendType($adapter): string {
        $class = get_class($adapter);
        
        if (stripos($class, 'redis') !== false) return 'redis';
        if (stripos($class, 'memcached') !== false) return 'memcached';
        if (stripos($class, 'file') !== false) return 'file';
        if (stripos($class, 'array') !== false) return 'memory';
        
        return 'unknown';
    }
    
    /**
     * Get adapter configuration
     * 
     * @param mixed $adapter
     * @return array
     */
    private function getAdapterConfig($adapter): array {
        $config = [];
        
        if (method_exists($adapter, 'getConfig')) {
            $config = $adapter->getConfig();
        }
        
        return $config;
    }
}