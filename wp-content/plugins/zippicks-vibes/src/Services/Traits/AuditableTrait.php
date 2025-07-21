<?php
/**
 * Auditable Trait for Services
 * 
 * Provides audit logging functionality for service classes
 * 
 * @package ZipPicksVibes
 * @subpackage Services\Traits
 * @since 2.0.0
 */

namespace ZipPicksVibes\Services\Traits;

use ZipPicksVibes\Audit\AuditLogger;

/**
 * AuditableTrait
 * 
 * Add audit logging capabilities to any service
 */
trait AuditableTrait {
    
    /**
     * Audit logger instance
     * 
     * @var AuditLogger|null
     */
    protected ?AuditLogger $auditLogger = null;
    
    /**
     * Set audit logger
     * 
     * @param AuditLogger|null $auditLogger
     * @return self
     */
    public function setAuditLogger(?AuditLogger $auditLogger): self {
        $this->auditLogger = $auditLogger;
        return $this;
    }
    
    /**
     * Get audit logger
     * 
     * @return AuditLogger|null
     */
    protected function getAuditLogger(): ?AuditLogger {
        if (!$this->auditLogger && function_exists('zippicks') && zippicks()->has('vibes.audit_logger')) {
            $this->auditLogger = zippicks()->get('vibes.audit_logger');
        }
        
        return $this->auditLogger;
    }
    
    /**
     * Log a create operation
     * 
     * @param string $objectType Object type
     * @param int $objectId Object ID
     * @param array $data Created data
     * @param string $category Event category
     * @return bool
     */
    protected function auditCreate(string $objectType, int $objectId, array $data = [], string $category = AuditLogger::CATEGORY_SYSTEM): bool {
        $logger = $this->getAuditLogger();
        if (!$logger) {
            return false;
        }
        
        return $logger->logCreate($objectType, $objectId, $data, $category);
    }
    
    /**
     * Log an update operation
     * 
     * @param string $objectType Object type
     * @param int $objectId Object ID
     * @param array $changes Changes made
     * @param string $category Event category
     * @return bool
     */
    protected function auditUpdate(string $objectType, int $objectId, array $changes = [], string $category = AuditLogger::CATEGORY_SYSTEM): bool {
        $logger = $this->getAuditLogger();
        if (!$logger) {
            return false;
        }
        
        return $logger->logUpdate($objectType, $objectId, $changes, $category);
    }
    
    /**
     * Log a delete operation
     * 
     * @param string $objectType Object type
     * @param int $objectId Object ID
     * @param array $data Deleted data
     * @param string $category Event category
     * @return bool
     */
    protected function auditDelete(string $objectType, int $objectId, array $data = [], string $category = AuditLogger::CATEGORY_SYSTEM): bool {
        $logger = $this->getAuditLogger();
        if (!$logger) {
            return false;
        }
        
        return $logger->logDelete($objectType, $objectId, $data, $category);
    }
    
    /**
     * Log a security event
     * 
     * @param string $action Security action
     * @param array $data Event data
     * @param string $severity Severity level
     * @return bool
     */
    protected function auditSecurity(string $action, array $data = [], string $severity = AuditLogger::SEVERITY_WARNING): bool {
        $logger = $this->getAuditLogger();
        if (!$logger) {
            return false;
        }
        
        return $logger->logSecurity($action, $data, $severity);
    }
    
    /**
     * Log a performance event
     * 
     * @param string $operation Operation name
     * @param float $startTime Start timestamp
     * @param array $context Additional context
     * @return bool
     */
    protected function auditPerformance(string $operation, float $startTime, array $context = []): bool {
        $logger = $this->getAuditLogger();
        if (!$logger) {
            return false;
        }
        
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        
        return $logger->logPerformance($operation, $duration, $context);
    }
    
    /**
     * Log an API operation
     * 
     * @param string $endpoint Endpoint
     * @param string $method HTTP method
     * @param array $data Request/response data
     * @param int $responseCode Response code
     * @param float $startTime Start timestamp
     * @return bool
     */
    protected function auditApi(string $endpoint, string $method, array $data = [], int $responseCode = 200, float $startTime = 0): bool {
        $logger = $this->getAuditLogger();
        if (!$logger) {
            return false;
        }
        
        $duration = $startTime > 0 ? (microtime(true) - $startTime) * 1000 : 0;
        
        return $logger->logApi($endpoint, $method, $data, $responseCode, $duration);
    }
    
    /**
     * Compare data for changes
     * 
     * @param array $oldData Old data
     * @param array $newData New data
     * @return array Changes array
     */
    protected function compareDataForAudit(array $oldData, array $newData): array {
        $changes = [];
        
        // Find modified fields
        foreach ($newData as $key => $newValue) {
            if (!array_key_exists($key, $oldData)) {
                // New field
                $changes[$key] = [
                    'old' => null,
                    'new' => $newValue
                ];
            } elseif ($oldData[$key] !== $newValue) {
                // Changed field
                $changes[$key] = [
                    'old' => $oldData[$key],
                    'new' => $newValue
                ];
            }
        }
        
        // Find removed fields
        foreach ($oldData as $key => $oldValue) {
            if (!array_key_exists($key, $newData)) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => null
                ];
            }
        }
        
        return $changes;
    }
}