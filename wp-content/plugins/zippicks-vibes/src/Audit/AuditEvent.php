<?php
/**
 * Audit Event Model for ZipPicks Vibes
 * 
 * Represents an audit event with comprehensive validation, type safety,
 * and serialization capabilities.
 * 
 * @package ZipPicksVibes
 * @subpackage Audit
 * @since 2.0.0
 * @version 2.1.0
 */

namespace ZipPicksVibes\Audit;

/**
 * AuditEvent Class
 * 
 * Fully typed data model for audit events with validation and serialization
 * 
 * @implements \JsonSerializable
 */
class AuditEvent implements \JsonSerializable {
    
    /**
     * Required fields for validation
     */
    private const REQUIRED_FIELDS = ['event_type', 'event_action'];
    
    /**
     * Valid severity levels
     */
    private const VALID_SEVERITIES = [
        AuditLogger::SEVERITY_INFO,
        AuditLogger::SEVERITY_WARNING,
        AuditLogger::SEVERITY_ERROR,
        AuditLogger::SEVERITY_CRITICAL
    ];
    
    /**
     * Valid event categories
     */
    private const VALID_CATEGORIES = [
        AuditLogger::CATEGORY_VIBES,
        AuditLogger::CATEGORY_ADMIN,
        AuditLogger::CATEGORY_API,
        AuditLogger::CATEGORY_SECURITY,
        AuditLogger::CATEGORY_USER,
        AuditLogger::CATEGORY_SYSTEM,
        AuditLogger::CATEGORY_COMPLIANCE,
        AuditLogger::CATEGORY_PERFORMANCE,
        AuditLogger::CATEGORY_MONETIZATION
    ];
    
    /**
     * Event ID
     * 
     * @var int|null
     */
    private ?int $id = null;
    
    /**
     * Event type
     * 
     * @var string
     */
    private string $event_type;
    
    /**
     * Event action
     * 
     * @var string
     */
    private string $event_action;
    
    /**
     * Event category
     * 
     * @var string
     */
    private string $event_category = 'general';
    
    /**
     * User ID
     * 
     * @var int|null
     */
    private ?int $user_id = null;
    
    /**
     * IP address
     * 
     * @var string
     */
    private string $ip_address;
    
    /**
     * User agent
     * 
     * @var string
     */
    private string $user_agent = '';
    
    /**
     * Object type
     * 
     * @var string|null
     */
    private ?string $object_type = null;
    
    /**
     * Object ID
     * 
     * @var int|null
     */
    private ?int $object_id = null;
    
    /**
     * Changes array
     * 
     * @var array|null
     */
    private ?array $changes = null;
    
    /**
     * Metadata array
     * 
     * @var array
     */
    private array $metadata = [];
    
    /**
     * Severity level
     * 
     * @var string
     */
    private string $severity = AuditLogger::SEVERITY_INFO;
    
    /**
     * Status
     * 
     * @var string
     */
    private string $status = 'success';
    
    /**
     * Duration in milliseconds
     * 
     * @var float|null
     */
    private ?float $duration_ms = null;
    
    /**
     * Created timestamp
     * 
     * @var string
     */
    private string $created_at;
    
    /**
     * Event fingerprint for integrity verification
     * 
     * @var string|null
     */
    private ?string $event_fingerprint = null;
    
    /**
     * Compliance flags for regulatory requirements
     * 
     * @var array
     */
    private array $compliance_flags = [];
    
    /**
     * Risk score for security events
     * 
     * @var int|null
     */
    private ?int $risk_score = null;
    
    /**
     * Geographic location data
     * 
     * @var array|null
     */
    private ?array $geo_location = null;
    
    /**
     * Event correlation ID for distributed tracing
     * 
     * @var string|null
     */
    private ?string $correlation_id = null;
    
    /**
     * Event tags for advanced filtering
     * 
     * @var array
     */
    private array $tags = [];
    
    /**
     * Constructor with validation
     * 
     * @param string $event_type Event type (required)
     * @param string $event_action Event action (required)
     * @param array $data Additional event data
     * @throws \InvalidArgumentException When required fields are missing or invalid
     */
    public function __construct(string $event_type, string $event_action, array $data = []) {
        // Validate required fields
        $this->validateRequiredFields(['event_type' => $event_type, 'event_action' => $event_action]);
        
        // Set core properties
        $this->event_type = $this->sanitizeString($event_type);
        $this->event_action = $this->sanitizeString($event_action);
        $this->created_at = current_time('mysql');
        $this->ip_address = $this->getClientIpAddress();
        $this->user_agent = $this->sanitizeString($_SERVER['HTTP_USER_AGENT'] ?? '');
        $this->user_id = get_current_user_id() ?: null;
        
        // Set optional properties from data with validation
        if (isset($data['event_category'])) {
            $this->setEventCategory($data['event_category']);
        }
        
        if (isset($data['severity'])) {
            $this->setSeverity($data['severity']);
        }
        
        if (isset($data['user_id'])) {
            $this->setUserId($data['user_id']);
        }
        
        if (isset($data['object_type'])) {
            $this->setObjectType($data['object_type']);
        }
        
        if (isset($data['object_id'])) {
            $this->setObjectId($data['object_id']);
        }
        
        if (isset($data['changes'])) {
            $this->setChanges($data['changes']);
        }
        
        if (isset($data['metadata'])) {
            $this->setMetadata($data['metadata']);
        }
        
        if (isset($data['status'])) {
            $this->setStatus($data['status']);
        }
        
        if (isset($data['duration_ms'])) {
            $this->setDurationMs($data['duration_ms']);
        }
        
        // Set enterprise properties
        if (isset($data['compliance_flags'])) {
            $this->setComplianceFlags($data['compliance_flags']);
        }
        
        if (isset($data['risk_score'])) {
            $this->setRiskScore($data['risk_score']);
        }
        
        if (isset($data['geo_location'])) {
            $this->setGeoLocation($data['geo_location']);
        }
        
        if (isset($data['correlation_id'])) {
            $this->setCorrelationId($data['correlation_id']);
        }
        
        if (isset($data['tags'])) {
            $this->setTags($data['tags']);
        }
        
        // Generate event fingerprint for integrity
        $this->generateEventFingerprint();
    }
    
    /**
     * Create from array with validation
     * 
     * @param array $data Event data
     * @return self
     * @throws \InvalidArgumentException When required fields are missing or invalid
     */
    public static function fromArray(array $data): self {
        // Validate required fields exist
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing or empty");
            }
        }
        
        $event = new self($data['event_type'], $data['event_action']);
        
        // Set properties from array with validation
        if (isset($data['id'])) $event->setId($data['id']);
        if (isset($data['event_category'])) $event->setEventCategory($data['event_category']);
        if (isset($data['user_id'])) $event->setUserId($data['user_id']);
        if (isset($data['ip_address'])) $event->setIpAddress($data['ip_address']);
        if (isset($data['user_agent'])) $event->setUserAgent($data['user_agent']);
        if (isset($data['object_type'])) $event->setObjectType($data['object_type']);
        if (isset($data['object_id'])) $event->setObjectId($data['object_id']);
        if (isset($data['changes'])) $event->setChanges($data['changes']);
        if (isset($data['metadata'])) $event->setMetadata($data['metadata']);
        if (isset($data['severity'])) $event->setSeverity($data['severity']);
        if (isset($data['status'])) $event->setStatus($data['status']);
        if (isset($data['duration_ms'])) $event->setDurationMs($data['duration_ms']);
        if (isset($data['created_at'])) $event->created_at = $data['created_at'];
        if (isset($data['event_fingerprint'])) $event->event_fingerprint = $data['event_fingerprint'];
        if (isset($data['compliance_flags'])) $event->setComplianceFlags($data['compliance_flags']);
        if (isset($data['risk_score'])) $event->setRiskScore($data['risk_score']);
        if (isset($data['geo_location'])) $event->setGeoLocation($data['geo_location']);
        if (isset($data['correlation_id'])) $event->setCorrelationId($data['correlation_id']);
        if (isset($data['tags'])) $event->setTags($data['tags']);
        
        return $event;
    }
    
    /**
     * Convert to array
     * 
     * @return array<string, mixed> Array representation of the audit event
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'event_action' => $this->event_action,
            'event_category' => $this->event_category,
            'user_id' => $this->user_id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'object_type' => $this->object_type,
            'object_id' => $this->object_id,
            'changes' => $this->changes,
            'metadata' => $this->metadata,
            'severity' => $this->severity,
            'status' => $this->status,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at,
            'event_fingerprint' => $this->event_fingerprint,
            'compliance_flags' => $this->compliance_flags,
            'risk_score' => $this->risk_score,
            'geo_location' => $this->geo_location,
            'correlation_id' => $this->correlation_id,
            'tags' => $this->tags
        ];
    }
    
    /**
     * JSON serialization implementation
     * 
     * @return array<string, mixed> Data to be serialized to JSON
     */
    public function jsonSerialize(): array {
        return $this->toArray();
    }
    
    /**
     * Validate the audit event data
     * 
     * @return bool True if valid
     * @throws \InvalidArgumentException If validation fails
     */
    public function validate(): bool {
        // Validate required fields
        $this->validateRequiredFields([
            'event_type' => $this->event_type,
            'event_action' => $this->event_action
        ]);
        
        // Validate event category
        if (!in_array($this->event_category, self::VALID_CATEGORIES, true)) {
            throw new \InvalidArgumentException("Invalid event category: {$this->event_category}");
        }
        
        // Validate severity
        if (!in_array($this->severity, self::VALID_SEVERITIES, true)) {
            throw new \InvalidArgumentException("Invalid severity level: {$this->severity}");
        }
        
        // Validate user ID if set
        if ($this->user_id !== null && (!is_int($this->user_id) || $this->user_id < 0)) {
            throw new \InvalidArgumentException("User ID must be a positive integer or null");
        }
        
        // Validate object ID if set
        if ($this->object_id !== null && (!is_int($this->object_id) || $this->object_id < 0)) {
            throw new \InvalidArgumentException("Object ID must be a positive integer or null");
        }
        
        // Validate duration if set
        if ($this->duration_ms !== null && (!is_numeric($this->duration_ms) || $this->duration_ms < 0)) {
            throw new \InvalidArgumentException("Duration must be a positive number or null");
        }
        
        // Validate risk score if set
        if ($this->risk_score !== null && ($this->risk_score < 0 || $this->risk_score > 100)) {
            throw new \InvalidArgumentException("Risk score must be between 0 and 100");
        }
        
        // Validate compliance flags
        if (!empty($this->compliance_flags) && !is_array($this->compliance_flags)) {
            throw new \InvalidArgumentException("Compliance flags must be an array");
        }
        
        // Validate geo location format
        if ($this->geo_location !== null) {
            $required_geo_fields = ['latitude', 'longitude'];
            foreach ($required_geo_fields as $field) {
                if (!isset($this->geo_location[$field])) {
                    throw new \InvalidArgumentException("Geo location must include {$field}");
                }
            }
        }
        
        // Validate tags array
        if (!empty($this->tags) && !is_array($this->tags)) {
            throw new \InvalidArgumentException("Tags must be an array");
        }
        
        return true;
    }
    
    /**
     * Get formatted message
     * 
     * @return string
     */
    public function getMessage(): string {
        $user = $this->user_id ? get_user_by('id', $this->user_id) : null;
        $username = $user ? $user->display_name : 'Guest';
        
        $message = sprintf(
            '%s %s',
            $username,
            str_replace('_', ' ', $this->event_action)
        );
        
        if ($this->object_type && $this->object_id) {
            $message .= sprintf(' %s #%d', $this->object_type, $this->object_id);
        }
        
        return $message;
    }
    
    /**
     * Check if event is error
     * 
     * @return bool
     */
    public function isError(): bool {
        return $this->status === 'error' || 
               in_array($this->severity, [AuditLogger::SEVERITY_ERROR, AuditLogger::SEVERITY_CRITICAL]);
    }
    
    /**
     * Check if event is security related
     * 
     * @return bool
     */
    public function isSecurityEvent(): bool {
        return $this->event_type === AuditLogger::EVENT_SECURITY ||
               $this->event_category === AuditLogger::CATEGORY_SECURITY;
    }
    
    // Getters and Setters
    
    public function getId(): ?int {
        return $this->id;
    }
    
    public function setId(int $id): self {
        $this->id = $id;
        return $this;
    }
    
    public function getEventType(): string {
        return $this->event_type;
    }
    
    public function getEventAction(): string {
        return $this->event_action;
    }
    
    public function getEventCategory(): string {
        return $this->event_category;
    }
    
    public function setEventCategory(string $event_category): self {
        if (!in_array($event_category, self::VALID_CATEGORIES, true)) {
            throw new \InvalidArgumentException("Invalid event category: {$event_category}");
        }
        $this->event_category = $event_category;
        return $this;
    }
    
    public function getUserId(): ?int {
        return $this->user_id;
    }
    
    public function setUserId(?int $user_id): self {
        if ($user_id !== null && $user_id < 0) {
            throw new \InvalidArgumentException("User ID must be a positive integer or null");
        }
        $this->user_id = $user_id;
        return $this;
    }
    
    public function getIpAddress(): string {
        return $this->ip_address;
    }
    
    public function setIpAddress(string $ip_address): self {
        // Validate IP address format
        if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("Invalid IP address format: {$ip_address}");
        }
        $this->ip_address = $ip_address;
        return $this;
    }
    
    public function getUserAgent(): string {
        return $this->user_agent;
    }
    
    public function setUserAgent(string $user_agent): self {
        $this->user_agent = $this->sanitizeString($user_agent);
        return $this;
    }
    
    public function getObjectType(): ?string {
        return $this->object_type;
    }
    
    public function setObjectType(?string $object_type): self {
        $this->object_type = $object_type ? $this->sanitizeString($object_type) : null;
        return $this;
    }
    
    public function getObjectId(): ?int {
        return $this->object_id;
    }
    
    public function setObjectId(?int $object_id): self {
        if ($object_id !== null && $object_id < 0) {
            throw new \InvalidArgumentException("Object ID must be a positive integer or null");
        }
        $this->object_id = $object_id;
        return $this;
    }
    
    public function getChanges(): ?array {
        return $this->changes;
    }
    
    public function setChanges(?array $changes): self {
        $this->changes = $changes;
        return $this;
    }
    
    public function addChange(string $field, $old_value, $new_value): self {
        if (!is_array($this->changes)) {
            $this->changes = [];
        }
        
        $this->changes[$field] = [
            'old' => $old_value,
            'new' => $new_value
        ];
        
        return $this;
    }
    
    public function getMetadata(): array {
        return $this->metadata;
    }
    
    public function setMetadata(array $metadata): self {
        $this->metadata = $metadata;
        return $this;
    }
    
    public function addMetadata(string $key, $value): self {
        $this->metadata[$key] = $value;
        return $this;
    }
    
    public function getSeverity(): string {
        return $this->severity;
    }
    
    public function setSeverity(string $severity): self {
        if (!in_array($severity, self::VALID_SEVERITIES, true)) {
            throw new \InvalidArgumentException("Invalid severity level: {$severity}");
        }
        $this->severity = $severity;
        return $this;
    }
    
    public function getStatus(): string {
        return $this->status;
    }
    
    public function setStatus(string $status): self {
        $this->status = $this->sanitizeString($status);
        return $this;
    }
    
    public function getDurationMs(): ?float {
        return $this->duration_ms;
    }
    
    public function setDurationMs(?float $duration_ms): self {
        if ($duration_ms !== null && $duration_ms < 0) {
            throw new \InvalidArgumentException("Duration must be a positive number or null");
        }
        $this->duration_ms = $duration_ms;
        return $this;
    }
    
    public function getCreatedAt(): string {
        return $this->created_at;
    }
    
    public function getEventFingerprint(): ?string {
        return $this->event_fingerprint;
    }
    
    public function getComplianceFlags(): array {
        return $this->compliance_flags;
    }
    
    public function setComplianceFlags(array $compliance_flags): self {
        $this->compliance_flags = $compliance_flags;
        return $this;
    }
    
    public function addComplianceFlag(string $flag, $value = true): self {
        $this->compliance_flags[$flag] = $value;
        return $this;
    }
    
    public function hasComplianceFlag(string $flag): bool {
        return isset($this->compliance_flags[$flag]) && $this->compliance_flags[$flag];
    }
    
    public function getRiskScore(): ?int {
        return $this->risk_score;
    }
    
    public function setRiskScore(?int $risk_score): self {
        if ($risk_score !== null && ($risk_score < 0 || $risk_score > 100)) {
            throw new \InvalidArgumentException("Risk score must be between 0 and 100");
        }
        $this->risk_score = $risk_score;
        return $this;
    }
    
    public function getGeoLocation(): ?array {
        return $this->geo_location;
    }
    
    public function setGeoLocation(?array $geo_location): self {
        if ($geo_location !== null) {
            $required_fields = ['latitude', 'longitude'];
            foreach ($required_fields as $field) {
                if (!isset($geo_location[$field])) {
                    throw new \InvalidArgumentException("Geo location must include {$field}");
                }
            }
        }
        $this->geo_location = $geo_location;
        return $this;
    }
    
    public function getCorrelationId(): ?string {
        return $this->correlation_id;
    }
    
    public function setCorrelationId(?string $correlation_id): self {
        $this->correlation_id = $correlation_id;
        return $this;
    }
    
    public function getTags(): array {
        return $this->tags;
    }
    
    public function setTags(array $tags): self {
        $this->tags = $tags;
        return $this;
    }
    
    public function addTag(string $tag, $value = true): self {
        $this->tags[$tag] = $value;
        return $this;
    }
    
    public function hasTag(string $tag): bool {
        return isset($this->tags[$tag]);
    }
    
    public function removeTag(string $tag): self {
        unset($this->tags[$tag]);
        return $this;
    }
    
    /**
     * Validate required fields
     * 
     * @param array<string, mixed> $data Data to validate
     * @throws \InvalidArgumentException If required fields are missing
     */
    private function validateRequiredFields(array $data): void {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing or empty");
            }
        }
    }
    
    /**
     * Sanitize string input
     * 
     * @param string $input Input string
     * @return string Sanitized string
     */
    private function sanitizeString(string $input): string {
        return trim(strip_tags($input));
    }
    
    /**
     * Get client IP address with proxy support
     * 
     * @return string Client IP address
     */
    private function getClientIpAddress(): string {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated list of IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Generate event fingerprint for integrity verification
     * 
     * @return void
     */
    private function generateEventFingerprint(): void {
        $fingerprint_data = [
            'event_type' => $this->event_type,
            'event_action' => $this->event_action,
            'user_id' => $this->user_id,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
            'object_type' => $this->object_type,
            'object_id' => $this->object_id
        ];
        
        $this->event_fingerprint = hash('sha256', serialize($fingerprint_data));
    }
}