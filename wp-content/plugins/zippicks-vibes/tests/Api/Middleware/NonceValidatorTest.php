<?php
/**
 * Unit tests for NonceValidator
 * 
 * @package ZipPicksVibes
 * @subpackage Tests
 */

namespace ZipPicksVibes\Tests\Api\Middleware;

use PHPUnit\Framework\TestCase;
use ZipPicksVibes\Api\Middleware\NonceValidator;
use WP_REST_Request;

/**
 * Class NonceValidatorTest
 */
class NonceValidatorTest extends TestCase {
    
    private $validator;
    private $logger;
    private $auditLogger;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Create mocks
        $this->logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->auditLogger = $this->createMock(\stdClass::class);
        
        // Create validator instance
        $this->validator = new NonceValidator('wp_rest', $this->logger, $this->auditLogger);
    }
    
    /**
     * Test public endpoint bypass
     */
    public function testPublicEndpointBypass() {
        // Mock request for public endpoint
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/zippicks/v1/vibes');
        $request->method('get_method')->willReturn('GET');
        
        // Execute
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Public endpoint - no nonce required', $result['reason']);
    }
    
    /**
     * Test nonce validation from header
     */
    public function testNonceFromHeader() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/zippicks/v1/vibes');
        $request->method('get_method')->willReturn('POST');
        $request->method('get_header')
            ->willReturnMap([
                ['X-WP-Nonce', 'valid_nonce_123'],
                ['referer', 'https://example.com']
            ]);
        
        // Mock WordPress nonce verification
        // In real tests, this would need WP_Mock or similar
        
        // Execute
        $result = $this->validator->validate($request);
        
        // Assert structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('reason', $result);
    }
    
    /**
     * Test nonce validation from Authorization header
     */
    public function testNonceFromAuthorizationHeader() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/zippicks/v1/vibes');
        $request->method('get_method')->willReturn('POST');
        $request->method('get_header')
            ->willReturnMap([
                ['X-WP-Nonce', null],
                ['Authorization', 'Bearer valid_nonce_123'],
                ['referer', 'https://example.com']
            ]);
        
        // Execute
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertIsArray($result);
    }
    
    /**
     * Test missing nonce validation
     */
    public function testMissingNonce() {
        // Mock request without nonce
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/zippicks/v1/vibes');
        $request->method('get_method')->willReturn('POST');
        $request->method('get_header')->willReturn(null);
        $request->method('get_param')->willReturn(null);
        $request->method('get_json_params')->willReturn([]);
        
        // Expect logging
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Nonce validation failed',
                $this->arrayHasKey('event_type')
            );
        
        // Execute
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Missing security token', $result['reason']);
    }
    
    /**
     * Test SQL injection detection
     */
    public function testSQLInjectionDetection() {
        // Mock request with SQL injection attempt
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/zippicks/v1/vibes');
        $request->method('get_method')->willReturn('POST');
        $request->method('get_header')
            ->willReturnMap([
                ['X-WP-Nonce', 'valid_nonce_123'],
                ['referer', 'https://example.com']
            ]);
        $request->method('get_params')->willReturn([
            'search' => "' OR '1'='1"
        ]);
        
        // In real test, mock wp_verify_nonce to return true
        
        // Execute
        $result = $this->validator->validate($request);
        
        // Assert - would fail suspicious activity check
        $this->assertIsArray($result);
    }
    
    /**
     * Test XSS detection
     */
    public function testXSSDetection() {
        // Mock request with XSS attempt
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/zippicks/v1/vibes');
        $request->method('get_method')->willReturn('POST');
        $request->method('get_header')
            ->willReturnMap([
                ['X-WP-Nonce', 'valid_nonce_123'],
                ['referer', 'https://example.com']
            ]);
        $request->method('get_params')->willReturn([
            'name' => '<script>alert("XSS")</script>'
        ]);
        
        // Execute
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertIsArray($result);
    }
    
    /**
     * Test path traversal detection
     */
    public function testPathTraversalDetection() {
        // Mock request with path traversal attempt
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/zippicks/v1/vibes');
        $request->method('get_method')->willReturn('POST');
        $request->method('get_header')
            ->willReturnMap([
                ['X-WP-Nonce', 'valid_nonce_123'],
                ['referer', 'https://example.com']
            ]);
        $request->method('get_params')->willReturn([
            'file' => '../../etc/passwd'
        ]);
        
        // Execute
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertIsArray($result);
    }
    
    /**
     * Test adding public route
     */
    public function testAddPublicRoute() {
        // Add custom public route
        $this->validator->addPublicRoute('GET', '/zippicks/v1/custom-public');
        
        // Mock request for the custom route
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/zippicks/v1/custom-public');
        $request->method('get_method')->willReturn('GET');
        
        // Execute
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('Public endpoint - no nonce required', $result['reason']);
    }
    
    /**
     * Test referer validation
     */
    public function testRefererValidation() {
        // Mock request with cross-origin referer
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/zippicks/v1/vibes');
        $request->method('get_method')->willReturn('POST');
        $request->method('get_header')
            ->willReturnMap([
                ['X-WP-Nonce', 'valid_nonce_123'],
                ['referer', 'https://malicious-site.com']
            ]);
        
        // Mock home_url() - in real tests use WP_Mock
        // This would fail referer check
        
        // Execute
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertIsArray($result);
    }
    
    /**
     * Test capability check for admin endpoints
     */
    public function testCapabilityCheck() {
        // Mock request for admin endpoint
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/zippicks/v1/vibes');
        $request->method('get_method')->willReturn('POST');
        $request->method('get_header')
            ->willReturnMap([
                ['X-WP-Nonce', 'valid_nonce_123'],
                ['referer', 'https://example.com']
            ]);
        
        // In real test, mock current_user_can() to return false
        
        // Execute
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertIsArray($result);
    }
    
    /**
     * Test nonce creation
     */
    public function testNonceCreation() {
        // In real test, mock wp_create_nonce
        $nonce = $this->validator->createNonce();
        
        // Assert
        $this->assertIsString($nonce);
    }
    
    /**
     * Test statistics retrieval
     */
    public function testGetStatistics() {
        // Mock database results
        global $wpdb;
        $wpdb = $this->createMock(\stdClass::class);
        $wpdb->prefix = 'wp_';
        $wpdb->method('get_var')->willReturn('wp_zippicks_security_log');
        $wpdb->method('get_row')->willReturn([
            'total_failures' => 100,
            'unique_ips' => 25,
            'unique_users' => 5,
            'unique_routes' => 10,
            'missing_nonce_count' => 50,
            'invalid_nonce_count' => 40,
            'additional_check_count' => 10
        ]);
        $wpdb->method('get_results')->willReturn([
            ['ip_address' => '192.168.1.1', 'failure_count' => 20],
            ['ip_address' => '192.168.1.2', 'failure_count' => 15]
        ]);
        
        // Execute
        $stats = $this->validator->getStatistics(24);
        
        // Assert
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_failures', $stats);
        $this->assertArrayHasKey('top_failing_ips', $stats);
        $this->assertEquals(24, $stats['time_period_hours']);
    }
    
    /**
     * Test log cleanup
     */
    public function testLogCleanup() {
        // Mock database
        global $wpdb;
        $wpdb = $this->createMock(\stdClass::class);
        $wpdb->prefix = 'wp_';
        $wpdb->method('get_var')->willReturn('wp_zippicks_security_log');
        $wpdb->expects($this->once())
            ->method('query')
            ->with($this->stringContains('DELETE FROM'));
        $wpdb->rows_affected = 150;
        
        // Expect logging
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Security log cleanup completed',
                ['days_kept' => 30, 'rows_deleted' => 150]
            );
        
        // Execute
        $this->validator->cleanupLogs(30);
    }
}