<?php
/**
 * Unit tests for RequestValidator
 *
 * @package ZipPicks_Vibes\Tests\Unit\Security
 */

namespace ZipPicks\Vibes\Tests\Unit\Security;

use ZipPicks\Vibes\Tests\TestCase;
use ZipPicks\Vibes\Security\RequestValidator;
use ZipPicks\Vibes\Audit\AuditLogger;
use WP_REST_Request;

class RequestValidatorTest extends TestCase {
    
    /**
     * @var RequestValidator
     */
    private $validator;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|AuditLogger
     */
    private $mockAuditLogger;
    
    /**
     * @var string
     */
    private $secretKey = 'test_secret_key_123';
    
    public function setUp(): void {
        parent::setUp();
        
        // Create mock
        $this->mockAuditLogger = $this->createMock(AuditLogger::class);
        
        // Create validator
        $this->validator = new RequestValidator($this->secretKey, $this->mockAuditLogger);
    }
    
    /**
     * Test validating a properly signed request
     */
    public function test_validate_properly_signed_request() {
        $timestamp = time();
        $method = 'GET';
        $uri = '/wp-json/zippicks/v2/vibes';
        $body = '';
        
        // Generate valid signature
        $signature = $this->generateSignature($method, $uri, $timestamp, $body);
        
        // Create request
        $request = new WP_REST_Request($method, $uri);
        $request->set_header('X-ZipPicks-Timestamp', $timestamp);
        $request->set_header('X-ZipPicks-Signature', $signature);
        
        // Validate
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test rejecting request with invalid signature
     */
    public function test_reject_invalid_signature() {
        $timestamp = time();
        $method = 'GET';
        $uri = '/wp-json/zippicks/v2/vibes';
        
        // Create request with invalid signature
        $request = new WP_REST_Request($method, $uri);
        $request->set_header('X-ZipPicks-Timestamp', $timestamp);
        $request->set_header('X-ZipPicks-Signature', 'invalid_signature');
        
        // Mock audit logging
        $this->mockAuditLogger->expects($this->once())
            ->method('logSecurity')
            ->with(
                $this->equalTo('invalid_signature'),
                $this->anything()
            );
        
        // Validate
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_signature', $result->get_error_code());
    }
    
    /**
     * Test rejecting expired request
     */
    public function test_reject_expired_request() {
        $timestamp = time() - 350; // 5+ minutes old
        $method = 'GET';
        $uri = '/wp-json/zippicks/v2/vibes';
        
        // Generate valid signature
        $signature = $this->generateSignature($method, $uri, $timestamp, '');
        
        // Create request
        $request = new WP_REST_Request($method, $uri);
        $request->set_header('X-ZipPicks-Timestamp', $timestamp);
        $request->set_header('X-ZipPicks-Signature', $signature);
        
        // Mock audit logging
        $this->mockAuditLogger->expects($this->once())
            ->method('logSecurity')
            ->with(
                $this->equalTo('expired_request'),
                $this->anything()
            );
        
        // Validate
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('expired_request', $result->get_error_code());
    }
    
    /**
     * Test rejecting request from future
     */
    public function test_reject_future_request() {
        $timestamp = time() + 350; // 5+ minutes in future
        $method = 'GET';
        $uri = '/wp-json/zippicks/v2/vibes';
        
        // Generate valid signature
        $signature = $this->generateSignature($method, $uri, $timestamp, '');
        
        // Create request
        $request = new WP_REST_Request($method, $uri);
        $request->set_header('X-ZipPicks-Timestamp', $timestamp);
        $request->set_header('X-ZipPicks-Signature', $signature);
        
        // Validate
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('expired_request', $result->get_error_code());
    }
    
    /**
     * Test rejecting request without timestamp
     */
    public function test_reject_missing_timestamp() {
        $request = new WP_REST_Request('GET', '/wp-json/zippicks/v2/vibes');
        $request->set_header('X-ZipPicks-Signature', 'some_signature');
        
        // Validate
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_headers', $result->get_error_code());
    }
    
    /**
     * Test rejecting request without signature
     */
    public function test_reject_missing_signature() {
        $request = new WP_REST_Request('GET', '/wp-json/zippicks/v2/vibes');
        $request->set_header('X-ZipPicks-Timestamp', time());
        
        // Validate
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_headers', $result->get_error_code());
    }
    
    /**
     * Test replay attack prevention
     */
    public function test_prevent_replay_attack() {
        $timestamp = time();
        $method = 'POST';
        $uri = '/wp-json/zippicks/v2/vibes';
        $body = json_encode(['name' => 'Test Vibe']);
        
        // Generate valid signature
        $signature = $this->generateSignature($method, $uri, $timestamp, $body);
        
        // Create request
        $request = new WP_REST_Request($method, $uri);
        $request->set_header('X-ZipPicks-Timestamp', $timestamp);
        $request->set_header('X-ZipPicks-Signature', $signature);
        $request->set_body($body);
        
        // First request should succeed
        $result1 = $this->validator->validate($request);
        $this->assertTrue($result1);
        
        // Same request again should fail (replay attack)
        $result2 = $this->validator->validate($request);
        $this->assertInstanceOf(\WP_Error::class, $result2);
        $this->assertEquals('replay_attack', $result2->get_error_code());
    }
    
    /**
     * Test IP whitelist validation
     */
    public function test_ip_whitelist_validation() {
        // Set up IP whitelist
        update_option('zippicks_api_ip_whitelist', ['192.168.1.100', '10.0.0.0/24']);
        
        // Create validator with whitelist enabled
        $validator = new RequestValidator($this->secretKey, $this->mockAuditLogger, true);
        
        // Test allowed IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $request = $this->createValidRequest();
        $result = $validator->validate($request);
        $this->assertTrue($result);
        
        // Test blocked IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.101';
        $request = $this->createValidRequest();
        
        // Mock audit logging for blocked IP
        $this->mockAuditLogger->expects($this->once())
            ->method('logSecurity')
            ->with(
                $this->equalTo('ip_not_whitelisted'),
                $this->anything()
            );
        
        $result = $validator->validate($request);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('ip_not_whitelisted', $result->get_error_code());
    }
    
    /**
     * Test request body validation
     */
    public function test_request_body_validation() {
        $timestamp = time();
        $method = 'POST';
        $uri = '/wp-json/zippicks/v2/vibes';
        $body = json_encode(['name' => 'Test Vibe']);
        
        // Generate signature with different body
        $signature = $this->generateSignature($method, $uri, $timestamp, '{"name":"Different"}');
        
        // Create request
        $request = new WP_REST_Request($method, $uri);
        $request->set_header('X-ZipPicks-Timestamp', $timestamp);
        $request->set_header('X-ZipPicks-Signature', $signature);
        $request->set_body($body);
        
        // Validate - should fail due to body mismatch
        $result = $this->validator->validate($request);
        
        // Assert
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_signature', $result->get_error_code());
    }
    
    /**
     * Test custom time window
     */
    public function test_custom_time_window() {
        // Create validator with 1 minute window
        $validator = new RequestValidator($this->secretKey, $this->mockAuditLogger, false, 60);
        
        // Test request 90 seconds old (should fail)
        $timestamp = time() - 90;
        $method = 'GET';
        $uri = '/wp-json/zippicks/v2/vibes';
        
        $signature = $this->generateSignature($method, $uri, $timestamp, '');
        
        $request = new WP_REST_Request($method, $uri);
        $request->set_header('X-ZipPicks-Timestamp', $timestamp);
        $request->set_header('X-ZipPicks-Signature', $signature);
        
        $result = $validator->validate($request);
        
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('expired_request', $result->get_error_code());
    }
    
    /**
     * Helper method to generate valid signature
     */
    private function generateSignature($method, $uri, $timestamp, $body) {
        $message = $method . "\n" . $uri . "\n" . $timestamp . "\n" . $body;
        return hash_hmac('sha256', $message, $this->secretKey);
    }
    
    /**
     * Helper method to create valid request
     */
    private function createValidRequest() {
        $timestamp = time();
        $method = 'GET';
        $uri = '/wp-json/zippicks/v2/vibes';
        
        $signature = $this->generateSignature($method, $uri, $timestamp, '');
        
        $request = new WP_REST_Request($method, $uri);
        $request->set_header('X-ZipPicks-Timestamp', $timestamp);
        $request->set_header('X-ZipPicks-Signature', $signature);
        
        return $request;
    }
}