<?php
/**
 * API Health Check
 * 
 * Checks REST API availability and performance
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\HealthCheck\Checks;

use ZipPicksVibes\HealthCheck\HealthCheckInterface;
use ZipPicksVibes\HealthCheck\HealthCheckResult;

/**
 * Class ApiCheck
 * 
 * Enhanced API health check with performance metrics, detailed diagnostics,
 * and fallback guidance for REST API monitoring
 */
class ApiCheck implements HealthCheckInterface {
    
    /**
     * HTTP client instance
     * 
     * @var mixed
     */
    private $httpClient;
    
    /**
     * Constructor
     * 
     * @param mixed $httpClient
     */
    public function __construct($httpClient = null) {
        $this->httpClient = $httpClient;
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
     * Execute enhanced API health check
     * 
     * @return HealthCheckResult
     */
    public function run(): HealthCheckResult {
        $startTime = microtime(true);
        
        try {
            // Get API base URL
            $apiUrl = rest_url('zippicks/v2/vibes');
            
            // Test API endpoint
            $testStart = microtime(true);
            $response = wp_remote_get($apiUrl, [
                'timeout' => 5,
                'headers' => [
                    'X-WP-Nonce' => wp_create_nonce('wp_rest')
                ]
            ]);
            $responseTime = (microtime(true) - $testStart) * 1000;
            
            if (is_wp_error($response)) {
                return HealthCheckResult::fail(
                    $this->getName(),
                    'REST API endpoint is not accessible',
                    [
                        'status' => HealthCheckResult::FAIL,
                        'error' => $response->get_error_message(),
                        'error_code' => $response->get_error_code(),
                        'url' => $apiUrl,
                        'fallback_guidance' => 'Check permalink structure, REST API is enabled, and server configuration',
                        'troubleshooting_steps' => [
                            'Verify permalink structure is not "Plain"',
                            'Check if REST API is disabled by plugin',
                            'Verify server mod_rewrite is enabled',
                            'Test with different endpoint: ' . rest_url('wp/v2/posts')
                        ],
                        'check_category' => 'api',
                        'performance_impact' => 'high'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            }
            
            $statusCode = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            // Enhanced status code analysis
            if ($statusCode >= 500) {
                return HealthCheckResult::fail(
                    $this->getName(),
                    sprintf('REST API server error (HTTP %d)', $statusCode),
                    [
                        'status' => HealthCheckResult::FAIL,
                        'status_code' => $statusCode,
                        'response_preview' => substr($body, 0, 200),
                        'response_size' => strlen($body),
                        'response_time_ms' => $responseTime,
                        'fallback_guidance' => 'Check server error logs and PHP error reporting',
                        'troubleshooting_steps' => [
                            'Check WordPress error logs',
                            'Verify PHP memory limit',
                            'Test with simpler endpoint',
                            'Check for plugin conflicts'
                        ],
                        'check_category' => 'api',
                        'performance_impact' => 'critical'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            } elseif ($statusCode >= 400) {
                return HealthCheckResult::warn(
                    $this->getName(),
                    sprintf('REST API client error (HTTP %d)', $statusCode),
                    [
                        'status' => HealthCheckResult::WARN,
                        'status_code' => $statusCode,
                        'response_preview' => substr($body, 0, 200),
                        'response_time_ms' => $responseTime,
                        'fallback_guidance' => 'Check API authentication and endpoint configuration',
                        'check_category' => 'api'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            }
            
            // Enhanced response time analysis with performance metrics
            $performanceCategory = $this->categorizePerformance($responseTime);
            if ($responseTime > 2000) {
                return HealthCheckResult::fail(
                    $this->getName(),
                    sprintf('REST API is critically slow (%.2fms)', $responseTime),
                    [
                        'status' => HealthCheckResult::FAIL,
                        'response_time_ms' => $responseTime,
                        'performance_category' => $performanceCategory,
                        'status_code' => $statusCode,
                        'fallback_guidance' => 'API performance is unacceptable, investigate server load and optimization',
                        'performance_thresholds' => $this->getPerformanceThresholds(),
                        'optimization_suggestions' => [
                            'Enable caching for API responses',
                            'Optimize database queries',
                            'Consider CDN for static content',
                            'Review server resources'
                        ],
                        'check_category' => 'api',
                        'performance_impact' => 'critical'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            } elseif ($responseTime > 1000) {
                return HealthCheckResult::warn(
                    $this->getName(),
                    sprintf('REST API response time is slow (%.2fms)', $responseTime),
                    [
                        'status' => HealthCheckResult::WARN,
                        'response_time_ms' => $responseTime,
                        'performance_category' => $performanceCategory,
                        'status_code' => $statusCode,
                        'fallback_guidance' => 'Consider performance optimizations',
                        'performance_thresholds' => $this->getPerformanceThresholds(),
                        'check_category' => 'api'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            }
            
            // Enhanced JSON validation with detailed analysis
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return HealthCheckResult::fail(
                    $this->getName(),
                    'REST API returned malformed JSON response',
                    [
                        'status' => HealthCheckResult::FAIL,
                        'json_error' => json_last_error_msg(),
                        'json_error_code' => json_last_error(),
                        'status_code' => $statusCode,
                        'response_preview' => substr($body, 0, 300),
                        'response_length' => strlen($body),
                        'fallback_guidance' => 'Check API output for PHP errors or malformed JSON',
                        'troubleshooting_steps' => [
                            'Check for PHP warnings/errors in response',
                            'Verify output buffering is clean',
                            'Test API endpoint directly',
                            'Review recent plugin/theme changes'
                        ],
                        'check_category' => 'api',
                        'data_integrity' => 'compromised'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            }
            
            // Enhanced infrastructure and performance analysis
            $infrastructureChecks = $this->checkInfrastructure();
            $performanceMetrics = $this->analyzePerformance($responseTime, $body, $data);
            
            return HealthCheckResult::pass(
                $this->getName(),
                sprintf('REST API is healthy (%.2fms response)', $responseTime),
                [
                    'status' => HealthCheckResult::PASS,
                    'response_time_ms' => $responseTime,
                    'performance_category' => $this->categorizePerformance($responseTime),
                    'status_code' => $statusCode,
                    'response_size_bytes' => strlen($body),
                    'data_records' => is_array($data) ? count($data) : 1,
                    'infrastructure' => $infrastructureChecks,
                    'performance_metrics' => $performanceMetrics,
                    'health_score' => $this->calculateApiHealthScore($responseTime, $statusCode, $infrastructureChecks),
                    'last_api_ping' => date('Y-m-d H:i:s'),
                    'check_category' => 'api',
                    'api_endpoint' => $apiUrl
                ],
                (microtime(true) - $startTime) * 1000
            );
            
        } catch (\Exception $e) {
            return HealthCheckResult::fail(
                $this->getName(),
                'API health check failed with exception: ' . $e->getMessage(),
                [
                    'status' => HealthCheckResult::FAIL,
                    'exception' => get_class($e),
                    'error_message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'fallback_guidance' => 'Review API configuration and server connectivity',
                    'troubleshooting_steps' => [
                        'Check network connectivity',
                        'Verify REST API configuration',
                        'Review server error logs',
                        'Test basic WordPress REST endpoints'
                    ],
                    'check_category' => 'api'
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
        return 'api';
    }
    
    /**
     * Get check description
     * 
     * @return string
     */
    public function getDescription(): string {
        return 'Checks REST API availability and performance';
    }
    
    /**
     * Get check priority
     * 
     * @return int
     */
    public function getPriority(): int {
        return 80;
    }
    
    /**
     * Whether this check is critical
     * 
     * @return bool
     */
    public function isCritical(): bool {
        return true;
    }
    
    /**
     * Get check category for aggregation
     * 
     * @return string
     */
    public function getCategory(): string {
        return 'api';
    }
    
    /**
     * Get estimated execution duration
     * 
     * @return int
     */
    public function getEstimatedDuration(): int {
        return 800; // 800ms estimated (includes API call)
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
     * Check REST API infrastructure
     * 
     * @return array
     */
    private function checkInfrastructure(): array {
        $checks = [];
        
        // Check if REST API is enabled
        $checks['rest_enabled'] = rest_api_loaded();
        
        // Check permalink structure
        $checks['pretty_permalinks'] = get_option('permalink_structure') !== '';
        
        // Check registered routes
        $routes = rest_get_server()->get_routes();
        $vibeRoutes = array_filter(array_keys($routes), function($route) {
            return strpos($route, '/zippicks/v2') === 0;
        });
        $checks['registered_routes'] = count($vibeRoutes);
        
        // Check authentication
        $checks['authentication_available'] = is_user_logged_in() || !empty($_SERVER['HTTP_AUTHORIZATION']);
        
        return $checks;
    }
    
    /**
     * Categorize performance based on response time
     * 
     * @param float $responseTime
     * @return string
     */
    private function categorizePerformance(float $responseTime): string {
        if ($responseTime < 200) {
            return 'excellent';
        } elseif ($responseTime < 500) {
            return 'good';
        } elseif ($responseTime < 1000) {
            return 'acceptable';
        } elseif ($responseTime < 2000) {
            return 'slow';
        } else {
            return 'critical';
        }
    }
    
    /**
     * Get performance thresholds for reference
     * 
     * @return array
     */
    private function getPerformanceThresholds(): array {
        return [
            'excellent' => '< 200ms',
            'good' => '200-500ms',
            'acceptable' => '500ms-1s',
            'slow' => '1-2s',
            'critical' => '> 2s'
        ];
    }
    
    /**
     * Analyze performance metrics
     * 
     * @param float $responseTime
     * @param string $body
     * @param mixed $data
     * @return array
     */
    private function analyzePerformance(float $responseTime, string $body, $data): array {
        $metrics = [
            'response_time_ms' => $responseTime,
            'response_size_bytes' => strlen($body),
            'throughput_bps' => strlen($body) / ($responseTime / 1000), // bytes per second
            'data_efficiency' => is_array($data) ? strlen($body) / max(1, count($data)) : strlen($body)
        ];
        
        // Add performance ratings
        $metrics['response_time_rating'] = $this->rateResponseTime($responseTime);
        $metrics['size_efficiency_rating'] = $this->rateSizeEfficiency(strlen($body), $data);
        
        return $metrics;
    }
    
    /**
     * Rate response time performance
     * 
     * @param float $responseTime
     * @return string
     */
    private function rateResponseTime(float $responseTime): string {
        if ($responseTime < 200) return 'A+';
        if ($responseTime < 500) return 'A';
        if ($responseTime < 1000) return 'B';
        if ($responseTime < 2000) return 'C';
        return 'F';
    }
    
    /**
     * Rate size efficiency
     * 
     * @param int $size
     * @param mixed $data
     * @return string
     */
    private function rateSizeEfficiency(int $size, $data): string {
        $recordCount = is_array($data) ? count($data) : 1;
        $bytesPerRecord = $size / max(1, $recordCount);
        
        if ($bytesPerRecord < 500) return 'A+';
        if ($bytesPerRecord < 1000) return 'A';
        if ($bytesPerRecord < 2000) return 'B';
        if ($bytesPerRecord < 5000) return 'C';
        return 'F';
    }
    
    /**
     * Calculate overall API health score
     * 
     * @param float $responseTime
     * @param int $statusCode
     * @param array $infrastructure
     * @return int
     */
    private function calculateApiHealthScore(float $responseTime, int $statusCode, array $infrastructure): int {
        $score = 100;
        
        // Response time scoring
        if ($responseTime > 2000) $score -= 50;
        elseif ($responseTime > 1000) $score -= 30;
        elseif ($responseTime > 500) $score -= 15;
        elseif ($responseTime > 200) $score -= 5;
        
        // Status code scoring
        if ($statusCode >= 400) $score -= 25;
        
        // Infrastructure scoring
        if (!$infrastructure['rest_enabled']) $score -= 30;
        if (!$infrastructure['pretty_permalinks']) $score -= 20;
        if ($infrastructure['registered_routes'] === 0) $score -= 20;
        
        return max(0, min(100, $score));
    }
}