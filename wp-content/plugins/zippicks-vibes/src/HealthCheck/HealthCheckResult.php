<?php
/**
 * Health Check Result
 * 
 * Represents the result of a health check with enhanced observability
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\HealthCheck;

use JsonSerializable;

/**
 * Class HealthCheckResult
 * 
 * Enterprise-grade health check result with full type safety and observability features
 */
class HealthCheckResult implements JsonSerializable {
    
    /**
     * Status constants - standardized across all health checks
     */
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_WARNING = 'warning';
    public const STATUS_CRITICAL = 'critical';
    
    /**
     * New standardized status constants for dashboard compatibility
     */
    public const PASS = 'pass';
    public const FAIL = 'fail';
    public const WARN = 'warn';
    
    /**
     * Check name
     * 
     * @var string
     */
    private string $name;
    
    /**
     * Status
     * 
     * @var string
     */
    private string $status;
    
    /**
     * Message
     * 
     * @var string
     */
    private string $message;
    
    /**
     * Additional details
     * 
     * @var array
     */
    private array $details;
    
    /**
     * Execution time in milliseconds
     * 
     * @var float
     */
    private float $executionTime;
    
    /**
     * Check timestamp (when the check was executed)
     * 
     * @var int
     */
    private int $timestamp;
    
    /**
     * Duration in milliseconds (alternative to executionTime for consistency)
     * 
     * @var float
     */
    private float $duration_ms;
    
    /**
     * Check source/ID for traceability
     * 
     * @var string
     */
    private string $check_id;
    
    /**
     * Constructor with enhanced typed properties
     * 
     * @param string $name Check name/identifier
     * @param string $status Status using class constants
     * @param string $message Human-readable status message
     * @param array $details Additional diagnostic data
     * @param float $executionTime Execution time in milliseconds
     * @param int|null $timestamp When check was executed (defaults to current time)
     * @param string|null $check_id Unique identifier for this check instance
     */
    public function __construct(
        string $name,
        string $status,
        string $message,
        array $details = [],
        float $executionTime = 0.0,
        ?int $timestamp = null,
        ?string $check_id = null
    ) {
        $this->name = $name;
        $this->status = $status;
        $this->message = $message;
        $this->details = $details;
        $this->executionTime = $executionTime;
        $this->duration_ms = $executionTime; // For consistency
        $this->timestamp = $timestamp ?? time();
        $this->check_id = $check_id ?? $this->generateCheckId($name);
    }
    
    /**
     * Generate unique check ID
     * 
     * @param string $name
     * @return string
     */
    private function generateCheckId(string $name): string {
        return $name . '_' . uniqid();
    }
    
    /**
     * Create healthy result
     * 
     * @param string $name
     * @param string $message
     * @param array $details
     * @param float $executionTime
     * @return self
     */
    public static function healthy(
        string $name,
        string $message,
        array $details = [],
        float $executionTime = 0.0
    ): self {
        return new self($name, self::STATUS_HEALTHY, $message, $details, $executionTime);
    }
    
    /**
     * Create warning result
     * 
     * @param string $name
     * @param string $message
     * @param array $details
     * @param float $executionTime
     * @return self
     */
    public static function warning(
        string $name,
        string $message,
        array $details = [],
        float $executionTime = 0.0
    ): self {
        return new self($name, self::STATUS_WARNING, $message, $details, $executionTime);
    }
    
    /**
     * Create critical result
     * 
     * @param string $name
     * @param string $message
     * @param array $details
     * @param float $executionTime
     * @return self
     */
    public static function critical(
        string $name,
        string $message,
        array $details = [],
        float $executionTime = 0.0
    ): self {
        return new self($name, self::STATUS_CRITICAL, $message, $details, $executionTime);
    }
    
    /**
     * Create PASS result (new standardized status)
     * 
     * @param string $name
     * @param string $message
     * @param array $details
     * @param float $executionTime
     * @return self
     */
    public static function pass(
        string $name,
        string $message,
        array $details = [],
        float $executionTime = 0.0
    ): self {
        return new self($name, self::PASS, $message, $details, $executionTime);
    }
    
    /**
     * Create FAIL result (new standardized status)
     * 
     * @param string $name
     * @param string $message
     * @param array $details
     * @param float $executionTime
     * @return self
     */
    public static function fail(
        string $name,
        string $message,
        array $details = [],
        float $executionTime = 0.0
    ): self {
        return new self($name, self::FAIL, $message, $details, $executionTime);
    }
    
    /**
     * Create WARN result (new standardized status)
     * 
     * @param string $name
     * @param string $message
     * @param array $details
     * @param float $executionTime
     * @return self
     */
    public static function warn(
        string $name,
        string $message,
        array $details = [],
        float $executionTime = 0.0
    ): self {
        return new self($name, self::WARN, $message, $details, $executionTime);
    }
    
    /**
     * Get name
     * 
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }
    
    /**
     * Get status
     * 
     * @return string
     */
    public function getStatus(): string {
        return $this->status;
    }
    
    /**
     * Get message
     * 
     * @return string
     */
    public function getMessage(): string {
        return $this->message;
    }
    
    /**
     * Get details
     * 
     * @return array
     */
    public function getDetails(): array {
        return $this->details;
    }
    
    /**
     * Get execution time
     * 
     * @return float
     */
    public function getExecutionTime(): float {
        return $this->executionTime;
    }
    
    /**
     * Get timestamp
     * 
     * @return int
     */
    public function getTimestamp(): int {
        return $this->timestamp;
    }
    
    /**
     * Get duration in milliseconds
     * 
     * @return float
     */
    public function getDurationMs(): float {
        return $this->duration_ms;
    }
    
    /**
     * Get check ID
     * 
     * @return string
     */
    public function getCheckId(): string {
        return $this->check_id;
    }
    
    /**
     * Check if healthy
     * 
     * @return bool
     */
    public function isHealthy(): bool {
        return $this->status === self::STATUS_HEALTHY;
    }
    
    /**
     * Check if warning
     * 
     * @return bool
     */
    public function isWarning(): bool {
        return $this->status === self::STATUS_WARNING;
    }
    
    /**
     * Check if critical
     * 
     * @return bool
     */
    public function isCritical(): bool {
        return $this->status === self::STATUS_CRITICAL;
    }
    
    /**
     * Check if status is PASS
     * 
     * @return bool
     */
    public function isPass(): bool {
        return $this->status === self::PASS;
    }
    
    /**
     * Check if status is FAIL
     * 
     * @return bool
     */
    public function isFail(): bool {
        return $this->status === self::FAIL;
    }
    
    /**
     * Check if status is WARN
     * 
     * @return bool
     */
    public function isWarn(): bool {
        return $this->status === self::WARN;
    }
    
    /**
     * Convert to array (enhanced with new properties)
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'message' => $this->message,
            'details' => $this->details,
            'execution_time' => $this->executionTime,
            'timestamp' => $this->timestamp,
            'duration_ms' => $this->duration_ms,
            'check_id' => $this->check_id,
            // Additional dashboard-friendly fields
            'formatted_time' => date('Y-m-d H:i:s', $this->timestamp),
            'performance_category' => $this->getPerformanceCategory(),
            'is_healthy' => $this->isHealthy(),
            'is_warning' => $this->isWarning(),
            'is_critical' => $this->isCritical()
        ];
    }
    
    /**
     * JsonSerializable implementation
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return $this->toArray();
    }
    
    /**
     * Get performance category based on execution time
     * 
     * @return string
     */
    private function getPerformanceCategory(): string {
        if ($this->executionTime < 100) {
            return 'fast';
        } elseif ($this->executionTime < 500) {
            return 'medium';
        } elseif ($this->executionTime < 1000) {
            return 'slow';
        } else {
            return 'very_slow';
        }
    }
}