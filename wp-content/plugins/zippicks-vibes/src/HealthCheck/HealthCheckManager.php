<?php
/**
 * Health Check Manager
 * 
 * Manages and executes health checks
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\HealthCheck;

/**
 * Class HealthCheckManager
 * 
 * Enhanced health check manager with error isolation, aggregation tags,
 * and external registration hooks for enterprise observability
 */
class HealthCheckManager {
    
    /**
     * Registered health checks
     * 
     * @var array<HealthCheckInterface>
     */
    private array $checks = [];
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger;
    
    /**
     * Cache instance
     * 
     * @var mixed
     */
    private $cache;
    
    /**
     * Constructor
     * 
     * @param mixed $logger
     * @param mixed $cache
     */
    public function __construct($logger = null, $cache = null) {
        $this->logger = $logger;
        $this->cache = $cache;
    }
    
    /**
     * Register a health check
     * 
     * @param HealthCheckInterface $check
     */
    public function register(HealthCheckInterface $check): void {
        $this->checks[] = $check;
        
        // Sort by priority
        usort($this->checks, function($a, $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }
    
    /**
     * Initialize external health check registration hook
     * 
     * Allows other plugins to register their health checks
     */
    public function initializeExternalRegistration(): void {
        /**
         * Action hook for external health check registration
         * 
         * @param HealthCheckManager $manager The health check manager instance
         */
        do_action('zippicks_register_health_check', $this);
    }
    
    /**
     * Execute all health checks with improved error isolation
     * 
     * @param bool $useCache Whether to use cached results (5-minute TTL)
     * @return array<HealthCheckResult>
     */
    public function runAll(bool $useCache = true): array {
        // Initialize external check registration if not done
        $this->initializeExternalRegistration();
        
        $cacheKey = 'zippicks_vibes_health_check_results';
        
        // Try cache first (extended to 5 minutes as per requirements)
        if ($useCache && $this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false && is_array($cached)) {
                return array_map(function($data) {
                    return new HealthCheckResult(
                        $data['name'],
                        $data['status'],
                        $data['message'],
                        $data['details'] ?? [],
                        $data['execution_time'] ?? 0.0,
                        $data['timestamp'] ?? time(),
                        $data['check_id'] ?? null
                    );
                }, $cached);
            }
        }
        
        $results = [];
        $aggregatedTags = [];
        
        // Isolate each check execution to prevent cascading failures
        foreach ($this->checks as $check) {
            $checkResult = $this->executeIndividualCheck($check);
            $results[] = $checkResult;
            
            // Collect tags for aggregation
            $category = method_exists($check, 'getCategory') ? $check->getCategory() : 'general';
            if (!isset($aggregatedTags[$category])) {
                $aggregatedTags[$category] = [];
            }
            $aggregatedTags[$category][] = $checkResult;
        }
        
        // Store aggregated results by tags for dashboard filtering
        $this->storeAggregatedResults($aggregatedTags);
        
        // Cache results for 5 minutes (as per requirements)
        if ($this->cache && !empty($results)) {
            $cacheData = array_map(function($result) {
                return $result->toArray();
            }, $results);
            
            // Extended cache TTL to 5 minutes (300 seconds)
            $this->cache->set($cacheKey, $cacheData, 300);
            
            // Also cache aggregated tags
            $this->cache->set($cacheKey . '_tags', $aggregatedTags, 300);
        }
        
        return $results;
    }
    
    /**
     * Run a specific health check
     * 
     * @param string $name
     * @return HealthCheckResult|null
     */
    public function run(string $name): ?HealthCheckResult {
        foreach ($this->checks as $check) {
            if ($check->getName() === $name) {
                try {
                    $startTime = microtime(true);
                    $result = $check->check();
                    $endTime = microtime(true);
                    
                    // Add execution time if not set
                    if ($result->getExecutionTime() === 0.0) {
                        $executionTime = ($endTime - $startTime) * 1000;
                        $result = new HealthCheckResult(
                            $result->getName(),
                            $result->getStatus(),
                            $result->getMessage(),
                            $result->getDetails(),
                            $executionTime
                        );
                    }
                    
                    return $result;
                } catch (\Exception $e) {
                    return HealthCheckResult::critical(
                        $check->getName(),
                        'Health check failed with exception: ' . $e->getMessage(),
                        [
                            'exception' => get_class($e),
                            'trace' => $e->getTraceAsString()
                        ]
                    );
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get overall health status
     * 
     * @param array<HealthCheckResult> $results
     * @return string
     */
    public function getOverallStatus(array $results): string {
        $hasCritical = false;
        $hasWarning = false;
        
        foreach ($results as $result) {
            if ($result->isCritical()) {
                $hasCritical = true;
                break;
            } elseif ($result->isWarning()) {
                $hasWarning = true;
            }
        }
        
        if ($hasCritical) {
            return HealthCheckResult::STATUS_CRITICAL;
        } elseif ($hasWarning) {
            return HealthCheckResult::STATUS_WARNING;
        }
        
        return HealthCheckResult::STATUS_HEALTHY;
    }
    
    /**
     * Get health summary
     * 
     * @param bool $useCache
     * @return array
     */
    public function getSummary(bool $useCache = true): array {
        $results = $this->runAll($useCache);
        
        $summary = [
            'status' => $this->getOverallStatus($results),
            'timestamp' => current_time('c'),
            'total_checks' => count($results),
            'healthy' => 0,
            'warning' => 0,
            'critical' => 0,
            'execution_time' => 0.0,
            'checks' => []
        ];
        
        foreach ($results as $result) {
            if ($result->isHealthy()) {
                $summary['healthy']++;
            } elseif ($result->isWarning()) {
                $summary['warning']++;
            } else {
                $summary['critical']++;
            }
            
            $summary['execution_time'] += $result->getExecutionTime();
            $summary['checks'][] = $result->toArray();
        }
        
        // Add aggregated summary by tags
        $summary['aggregated_by_tags'] = $this->getAggregatedSummary();
        
        return $summary;
    }
    
    /**
     * Execute individual health check with full isolation
     * 
     * @param HealthCheckInterface $check
     * @return HealthCheckResult
     */
    private function executeIndividualCheck(HealthCheckInterface $check): HealthCheckResult {
        try {
            $startTime = microtime(true);
            
            // Use new run() method if available, fallback to check()
            $result = method_exists($check, 'run') ? $check->run() : $check->check();
            
            $endTime = microtime(true);
            
            // Ensure execution time is recorded
            if ($result->getExecutionTime() === 0.0) {
                $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
                $result = new HealthCheckResult(
                    $result->getName(),
                    $result->getStatus(),
                    $result->getMessage(),
                    $result->getDetails(),
                    $executionTime,
                    time(),
                    $check->getName() . '_' . uniqid()
                );
            }
            
            // Enhanced logging for failures
            if (($result->isCritical() || $result->isFail()) && $this->logger) {
                $this->logger->critical('Health check failed', [
                    'check' => $result->getName(),
                    'check_id' => $result->getCheckId(),
                    'message' => $result->getMessage(),
                    'details' => $result->getDetails(),
                    'category' => method_exists($check, 'getCategory') ? $check->getCategory() : 'general',
                    'execution_time' => $result->getExecutionTime()
                ]);
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            // Enhanced exception handling with detailed context
            $errorResult = HealthCheckResult::critical(
                $check->getName(),
                'Health check failed with exception: ' . $e->getMessage(),
                [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'category' => method_exists($check, 'getCategory') ? $check->getCategory() : 'general',
                    'fallback_guidance' => 'Check logs for detailed exception information'
                ]
            );
            
            if ($this->logger) {
                $this->logger->error('Health check exception', [
                    'check' => $check->getName(),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            return $errorResult;
        }
    }
    
    /**
     * Store aggregated results by category tags
     * 
     * @param array $aggregatedTags
     */
    private function storeAggregatedResults(array $aggregatedTags): void {
        // Store aggregated results in a separate cache key for dashboard queries
        if ($this->cache) {
            $aggregatedSummary = [];
            foreach ($aggregatedTags as $category => $results) {
                $aggregatedSummary[$category] = [
                    'total' => count($results),
                    'healthy' => array_sum(array_map(fn($r) => $r->isHealthy() ? 1 : 0, $results)),
                    'warnings' => array_sum(array_map(fn($r) => $r->isWarning() ? 1 : 0, $results)),
                    'critical' => array_sum(array_map(fn($r) => $r->isCritical() ? 1 : 0, $results)),
                    'avg_execution_time' => array_sum(array_map(fn($r) => $r->getExecutionTime(), $results)) / count($results)
                ];
            }
            
            $this->cache->set('zippicks_vibes_health_aggregated', $aggregatedSummary, 300);
        }
    }
    
    /**
     * Get aggregated summary by tags
     * 
     * @return array
     */
    private function getAggregatedSummary(): array {
        if ($this->cache) {
            $cached = $this->cache->get('zippicks_vibes_health_aggregated');
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }
        }
        return [];
    }
    
    /**
     * Get registered checks with enhanced metadata
     * 
     * @return array
     */
    public function getChecks(): array {
        return array_map(function($check) {
            return [
                'name' => $check->getName(),
                'description' => $check->getDescription(),
                'priority' => $check->getPriority(),
                'critical' => $check->isCritical(),
                'category' => method_exists($check, 'getCategory') ? $check->getCategory() : 'general',
                'estimated_duration' => method_exists($check, 'getEstimatedDuration') ? $check->getEstimatedDuration() : 0,
                'monitoring_enabled' => method_exists($check, 'isMonitoringEnabled') ? $check->isMonitoringEnabled() : true
            ];
        }, $this->checks);
    }
    
    /**
     * Get health checks by category
     * 
     * @param string $category
     * @return array
     */
    public function getChecksByCategory(string $category): array {
        return array_filter($this->checks, function($check) use ($category) {
            return method_exists($check, 'getCategory') && $check->getCategory() === $category;
        });
    }
    
    /**
     * Run all health checks (legacy compatibility method)
     * 
     * This method provides compatibility for older calling code that expects
     * the method name run_all_checks instead of runAll.
     * 
     * @param bool $useCache Whether to use cached results (5-minute TTL)
     * @return array<HealthCheckResult>
     */
    public function run_all_checks(bool $useCache = true): array {
        return $this->runAll($useCache);
    }
}