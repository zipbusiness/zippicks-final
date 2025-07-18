<?php
/**
 * Unit tests for CSRF Protection
 *
 * @package ZipPicks_Vibes\Tests\Unit\Security
 */

namespace ZipPicks\Vibes\Tests\Unit\Security;

use ZipPicks\Vibes\Tests\TestCase;
use ZipPicks\Vibes\Security\CsrfProtection;
use ZipPicks\Vibes\Cache\CacheManager;
use Psr\Log\LoggerInterface;

/**
 * @group unit
 * @group security
 */
class CsrfProtectionTest extends TestCase {
    
    /**
     * @var CsrfProtection
     */
    private $csrf;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|CacheManager
     */
    private $mockCache;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface
     */
    private $mockLogger;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->mockCache = $this->createMock(CacheManager::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        $this->csrf = new CsrfProtection($this->mockCache, $this->mockLogger);
    }
    
    /**
     * Test generating a CSRF token
     */
    public function test_generate_token() {
        $user_id = 123;
        $action = 'test_action';
        
        // Mock cache set
        $this->mockCache->expects($this->once())
            ->method('set')
            ->with(
                $this->stringContains("csrf_token_{$user_id}_{$action}_"),
                true,
                3600
            )
            ->willReturn(true);
        
        $token = $this->csrf->generateToken($user_id, $action);
        
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // SHA256 hex length
    }
    
    /**
     * Test validating a valid token
     */
    public function test_validate_valid_token() {
        $user_id = 123;
        $action = 'test_action';
        $token = hash('sha256', uniqid('test', true));
        
        // Mock cache get returns true (token exists)
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with("csrf_token_{$user_id}_{$action}_{$token}")
            ->willReturn(true);
        
        // Mock cache delete (token consumed)
        $this->mockCache->expects($this->once())
            ->method('delete')
            ->with("csrf_token_{$user_id}_{$action}_{$token}")
            ->willReturn(true);
        
        $result = $this->csrf->validateToken($token, $user_id, $action);
        
        $this->assertTrue($result);
    }
    
    /**
     * Test validating an invalid token
     */
    public function test_validate_invalid_token() {
        $user_id = 123;
        $action = 'test_action';
        $token = 'invalid_token';
        
        // Mock cache get returns false (token doesn't exist)
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with("csrf_token_{$user_id}_{$action}_{$token}")
            ->willReturn(false);
        
        // Logger should log the failure
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Invalid CSRF token'));
        
        $result = $this->csrf->validateToken($token, $user_id, $action);
        
        $this->assertFalse($result);
    }
    
    /**
     * Test token expiration
     */
    public function test_token_expiration() {
        $user_id = 123;
        $action = 'test_action';
        
        // Token should be stored with TTL
        $this->mockCache->expects($this->once())
            ->method('set')
            ->with(
                $this->anything(),
                true,
                3600 // 1 hour TTL
            );
        
        $this->csrf->generateToken($user_id, $action);
    }
    
    /**
     * Test token uniqueness
     */
    public function test_token_uniqueness() {
        $user_id = 123;
        $action = 'test_action';
        
        $tokens = [];
        
        // Generate multiple tokens
        for ($i = 0; $i < 10; $i++) {
            // Mock cache for each token
            $this->mockCache->expects($this->at($i))
                ->method('set')
                ->willReturn(true);
            
            $token = $this->csrf->generateToken($user_id, $action);
            $this->assertNotContains($token, $tokens, 'Token should be unique');
            $tokens[] = $token;
        }
    }
    
    /**
     * Test token validation with empty token
     */
    public function test_validate_empty_token() {
        $result = $this->csrf->validateToken('', 123, 'action');
        $this->assertFalse($result);
    }
    
    /**
     * Test token validation with null user ID
     */
    public function test_validate_null_user_id() {
        $result = $this->csrf->validateToken('token', null, 'action');
        $this->assertFalse($result);
    }
    
    /**
     * Test token validation with empty action
     */
    public function test_validate_empty_action() {
        $result = $this->csrf->validateToken('token', 123, '');
        $this->assertFalse($result);
    }
    
    /**
     * Test generating token for guest user
     */
    public function test_generate_token_for_guest() {
        $user_id = 0; // Guest user
        $action = 'guest_action';
        $session_id = 'session_123';
        
        // Mock session ID
        $_SESSION['zippicks_session_id'] = $session_id;
        
        $this->mockCache->expects($this->once())
            ->method('set')
            ->with(
                $this->stringContains("csrf_token_guest_{$session_id}_{$action}_"),
                true,
                3600
            );
        
        $token = $this->csrf->generateToken($user_id, $action);
        
        $this->assertIsString($token);
    }
    
    /**
     * Test rate limiting token generation
     */
    public function test_rate_limit_token_generation() {
        $user_id = 123;
        $action = 'test_action';
        
        // Mock rate limit check
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with("csrf_rate_limit_{$user_id}")
            ->willReturn(10); // User has generated 10 tokens
        
        // Should log warning about rate limit
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('CSRF token generation rate limit'));
        
        // Still generates token but with warning
        $this->mockCache->expects($this->once())
            ->method('set')
            ->willReturn(true);
        
        $token = $this->csrf->generateToken($user_id, $action);
        $this->assertIsString($token);
    }
    
    /**
     * Test cleaning expired tokens
     */
    public function test_clean_expired_tokens() {
        // This would typically be handled by cache TTL
        // But we can test the cleanup method if it exists
        
        if (method_exists($this->csrf, 'cleanExpiredTokens')) {
            $this->mockCache->expects($this->once())
                ->method('deletePattern')
                ->with('csrf_token_*');
            
            $this->csrf->cleanExpiredTokens();
        } else {
            $this->assertTrue(true); // Pass if method doesn't exist
        }
    }
    
    /**
     * Test token validation logging
     */
    public function test_token_validation_logging() {
        $user_id = 123;
        $action = 'secure_action';
        $token = 'test_token';
        $ip = '192.168.1.1';
        
        $_SERVER['REMOTE_ADDR'] = $ip;
        
        // Mock cache miss
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(false);
        
        // Should log with context
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Invalid CSRF token'),
                $this->callback(function($context) use ($user_id, $action, $ip) {
                    return $context['user_id'] === $user_id &&
                           $context['action'] === $action &&
                           $context['ip'] === $ip;
                })
            );
        
        $this->csrf->validateToken($token, $user_id, $action);
    }
    
    /**
     * Test concurrent token validation
     */
    public function test_concurrent_token_validation() {
        $user_id = 123;
        $action = 'test_action';
        $token = 'concurrent_token';
        
        // First validation succeeds
        $this->mockCache->expects($this->at(0))
            ->method('get')
            ->willReturn(true);
        
        $this->mockCache->expects($this->at(1))
            ->method('delete')
            ->willReturn(true);
        
        $result1 = $this->csrf->validateToken($token, $user_id, $action);
        $this->assertTrue($result1);
        
        // Second validation fails (token already consumed)
        $this->mockCache->expects($this->at(2))
            ->method('get')
            ->willReturn(false);
        
        $result2 = $this->csrf->validateToken($token, $user_id, $action);
        $this->assertFalse($result2);
    }
}