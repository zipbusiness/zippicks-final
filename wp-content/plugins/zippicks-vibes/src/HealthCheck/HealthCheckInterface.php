<?php
/**
 * Health Check Interface
 * 
 * Contract for health check implementations
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\HealthCheck;

/**
 * Interface HealthCheckInterface
 * 
 * Enhanced interface with typed return signatures and standardized method contracts
 * for enterprise-grade health check implementations
 */
interface HealthCheckInterface {
    
    /**
     * Execute health check and return standardized result
     * 
     * @return HealthCheckResult Typed result with status, timing, and diagnostic data
     */
    public function run(): HealthCheckResult;
    
    /**
     * Legacy method for backward compatibility
     * 
     * @return HealthCheckResult
     * @deprecated Use run() instead
     */
    public function check(): HealthCheckResult;
    
    /**
     * Get unique check identifier/name
     * 
     * @return string Unique identifier for this health check
     */
    public function getName(): string;
    
    /**
     * Get human-readable check description
     * 
     * @return string Description explaining what this check validates
     */
    public function getDescription(): string;
    
    /**
     * Get check execution priority (higher values run first)
     * 
     * @return int Priority value (typically 1-100, where 100 is highest priority)
     */
    public function getPriority(): int;
    
    /**
     * Determine if this check is system-critical
     * 
     * Critical checks indicate system-breaking issues when they fail
     * 
     * @return bool True if check failure indicates critical system problem
     */
    public function isCritical(): bool;
    
    /**
     * Get check category/tag for grouping and filtering
     * 
     * @return string Category tag (e.g., 'system', 'database', 'api', 'security')
     */
    public function getCategory(): string;
    
    /**
     * Get estimated execution time in milliseconds
     * 
     * @return int Expected execution time for performance planning
     */
    public function getEstimatedDuration(): int;
    
    /**
     * Check if this health check should be included in automated monitoring
     * 
     * @return bool True if check should run in automated health monitoring
     */
    public function isMonitoringEnabled(): bool;
}