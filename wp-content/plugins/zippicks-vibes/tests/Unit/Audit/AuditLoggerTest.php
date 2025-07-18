<?php
/**
 * Unit tests for AuditLogger
 *
 * @package ZipPicks_Vibes\Tests\Unit\Audit
 */

namespace ZipPicks\Vibes\Tests\Unit\Audit;

use ZipPicks\Vibes\Tests\TestCase;
use ZipPicks\Vibes\Audit\AuditLogger;
use ZipPicks\Vibes\Audit\AuditRepository;
use Psr\Log\LoggerInterface;

class AuditLoggerTest extends TestCase {
    
    /**
     * @var AuditLogger
     */
    private $auditLogger;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|AuditRepository
     */
    private $mockRepository;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface
     */
    private $mockLogger;
    
    public function setUp(): void {
        parent::setUp();
        
        // Create mocks
        $this->mockRepository = $this->createMock(AuditRepository::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        // Create audit logger
        $this->auditLogger = new AuditLogger($this->mockRepository, $this->mockLogger);
        
        // Set up test user
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
    }
    
    /**
     * Test logging a create event
     */
    public function test_log_create_event() {
        $resource = 'vibes';
        $resource_id = 123;
        $data = ['name' => 'New Vibe', 'status' => 'active'];
        
        // Mock repository save
        $this->mockRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function($event) use ($resource, $resource_id, $data) {
                return $event->getEventType() === 'CREATE' &&
                       $event->getEventCategory() === $resource &&
                       $event->getDetails()['resource_id'] === $resource_id &&
                       $event->getDetails()['data'] === $data;
            }))
            ->willReturn(true);
        
        // Execute
        $result = $this->auditLogger->logCreate($resource, $resource_id, $data);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test logging an update event
     */
    public function test_log_update_event() {
        $resource = 'vibes';
        $resource_id = 123;
        $old_data = ['name' => 'Old Name', 'status' => 'active'];
        $new_data = ['name' => 'New Name', 'status' => 'inactive'];
        
        // Mock repository save
        $this->mockRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function($event) use ($resource, $resource_id, $old_data, $new_data) {
                $details = $event->getDetails();
                return $event->getEventType() === 'UPDATE' &&
                       $event->getEventCategory() === $resource &&
                       $details['resource_id'] === $resource_id &&
                       $details['old_data'] === $old_data &&
                       $details['new_data'] === $new_data &&
                       isset($details['changes']);
            }))
            ->willReturn(true);
        
        // Execute
        $result = $this->auditLogger->logUpdate($resource, $resource_id, $old_data, $new_data);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test logging a delete event
     */
    public function test_log_delete_event() {
        $resource = 'vibes';
        $resource_id = 123;
        $data = ['name' => 'Deleted Vibe'];
        
        // Mock repository save
        $this->mockRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function($event) use ($resource, $resource_id, $data) {
                return $event->getEventType() === 'DELETE' &&
                       $event->getEventCategory() === $resource &&
                       $event->getDetails()['resource_id'] === $resource_id &&
                       $event->getDetails()['data'] === $data;
            }))
            ->willReturn(true);
        
        // Execute
        $result = $this->auditLogger->logDelete($resource, $resource_id, $data);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test logging a security event
     */
    public function test_log_security_event() {
        $action = 'invalid_login_attempt';
        $details = [
            'username' => 'test_user',
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0'
        ];
        
        // Mock repository save
        $this->mockRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function($event) use ($action, $details) {
                return $event->getEventType() === 'SECURITY' &&
                       $event->getEventCategory() === 'security' &&
                       $event->getSeverity() === 'warning' &&
                       $event->getMessage() === "Security event: $action" &&
                       $event->getDetails() === $details;
            }))
            ->willReturn(true);
        
        // Mock logger warning
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains($action));
        
        // Execute
        $result = $this->auditLogger->logSecurity($action, $details);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test logging an API event
     */
    public function test_log_api_event() {
        $endpoint = '/wp-json/zippicks/v2/vibes';
        $method = 'GET';
        $response_code = 200;
        $duration = 0.125;
        $details = ['query' => 'natural wine'];
        
        // Mock repository save
        $this->mockRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function($event) use ($endpoint, $method, $response_code, $duration, $details) {
                $eventDetails = $event->getDetails();
                return $event->getEventType() === 'API' &&
                       $event->getEventCategory() === 'api' &&
                       $eventDetails['endpoint'] === $endpoint &&
                       $eventDetails['method'] === $method &&
                       $eventDetails['response_code'] === $response_code &&
                       $eventDetails['duration'] === $duration &&
                       $eventDetails['request_details'] === $details;
            }))
            ->willReturn(true);
        
        // Execute
        $result = $this->auditLogger->logApi($endpoint, $method, $response_code, $duration, $details);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test logging a performance event
     */
    public function test_log_performance_event() {
        $metric = 'slow_query';
        $value = 2.5;
        $details = [
            'query' => 'SELECT * FROM wp_terms WHERE...',
            'caller' => 'VibeRepository::findAll'
        ];
        
        // Mock repository save
        $this->mockRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function($event) use ($metric, $value, $details) {
                $eventDetails = $event->getDetails();
                return $event->getEventType() === 'PERFORMANCE' &&
                       $event->getEventCategory() === 'system' &&
                       $event->getSeverity() === 'warning' &&
                       $eventDetails['metric'] === $metric &&
                       $eventDetails['value'] === $value &&
                       $eventDetails['additional'] === $details;
            }))
            ->willReturn(true);
        
        // Mock logger warning (since 2.5s is slow)
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains($metric));
        
        // Execute
        $result = $this->auditLogger->logPerformance($metric, $value, $details);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test querying audit logs
     */
    public function test_query_logs() {
        $filters = [
            'event_type' => 'CREATE',
            'category' => 'vibes',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31'
        ];
        
        $expected_results = [
            ['id' => 1, 'event_type' => 'CREATE', 'message' => 'Vibe created'],
            ['id' => 2, 'event_type' => 'CREATE', 'message' => 'Another vibe created']
        ];
        
        // Mock repository query
        $this->mockRepository->expects($this->once())
            ->method('query')
            ->with($filters)
            ->willReturn($expected_results);
        
        // Execute
        $result = $this->auditLogger->query($filters);
        
        // Assert
        $this->assertEquals($expected_results, $result);
    }
    
    /**
     * Test getting audit statistics
     */
    public function test_get_statistics() {
        $period = 'last_7_days';
        $expected_stats = [
            'total_events' => 150,
            'events_by_type' => [
                'CREATE' => 50,
                'UPDATE' => 60,
                'DELETE' => 10,
                'SECURITY' => 20,
                'API' => 10
            ],
            'events_by_severity' => [
                'info' => 100,
                'warning' => 40,
                'error' => 10
            ]
        ];
        
        // Mock repository getStatistics
        $this->mockRepository->expects($this->once())
            ->method('getStatistics')
            ->with($period)
            ->willReturn($expected_stats);
        
        // Execute
        $result = $this->auditLogger->getStatistics($period);
        
        // Assert
        $this->assertEquals($expected_stats, $result);
    }
    
    /**
     * Test cleaning old logs
     */
    public function test_clean_old_logs() {
        $days_to_keep = 30;
        $expected_deleted = 45;
        
        // Mock repository cleanOldLogs
        $this->mockRepository->expects($this->once())
            ->method('cleanOldLogs')
            ->with($days_to_keep)
            ->willReturn($expected_deleted);
        
        // Mock logger info
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with($this->stringContains("Cleaned $expected_deleted old audit logs"));
        
        // Execute
        $result = $this->auditLogger->cleanOldLogs($days_to_keep);
        
        // Assert
        $this->assertEquals($expected_deleted, $result);
    }
    
    /**
     * Test error handling when save fails
     */
    public function test_error_handling_on_save_failure() {
        $resource = 'vibes';
        $resource_id = 123;
        $data = ['name' => 'Test'];
        
        // Mock repository save failure
        $this->mockRepository->expects($this->once())
            ->method('save')
            ->willThrowException(new \Exception('Database error'));
        
        // Mock error logging
        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to save audit log'));
        
        // Execute
        $result = $this->auditLogger->logCreate($resource, $resource_id, $data);
        
        // Assert
        $this->assertFalse($result);
    }
    
    /**
     * Test audit event with anonymous user
     */
    public function test_audit_with_anonymous_user() {
        // Set current user to 0 (anonymous)
        wp_set_current_user(0);
        
        $resource = 'vibes';
        $resource_id = 123;
        
        // Mock repository save
        $this->mockRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function($event) {
                return $event->getUserId() === 0 &&
                       $event->getDetails()['user_info']['display_name'] === 'Anonymous';
            }))
            ->willReturn(true);
        
        // Execute
        $result = $this->auditLogger->logView($resource, $resource_id);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test severity level determination
     */
    public function test_severity_levels() {
        // Test critical security event
        $this->mockRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function($event) {
                return $event->getSeverity() === 'critical';
            }))
            ->willReturn(true);
        
        $this->auditLogger->logSecurity('brute_force_attack', ['attempts' => 100]);
        
        // Test warning performance event
        $this->mockRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function($event) {
                return $event->getSeverity() === 'warning';
            }))
            ->willReturn(true);
        
        $this->auditLogger->logPerformance('slow_query', 1.5);
        
        // Test info level for normal operations
        $this->mockRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function($event) {
                return $event->getSeverity() === 'info';
            }))
            ->willReturn(true);
        
        $this->auditLogger->logView('vibes', 123);
    }
}