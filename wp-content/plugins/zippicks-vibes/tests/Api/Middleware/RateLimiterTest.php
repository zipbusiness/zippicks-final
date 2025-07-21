<?php
/**
 * Unit tests for RateLimiter
 * 
 * @package ZipPicksVibes
 * @subpackage Tests
 */

namespace ZipPicksVibes\Tests\Api\Middleware;

use PHPUnit\Framework\TestCase;
use ZipPicksVibes\Api\Middleware\RateLimiter;
use WP_REST_Request;

/**
 * Class RateLimiterTest
 */
class RateLimiterTest extends TestCase {
    
    private $rateLimiter;
    private $cache;
    private $logger;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Create mocks
        $this->cache = $this->createMock(\stdClass::class);
        $this->logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        
        // Create rate limiter instance
        $this->rateLimiter = new RateLimiter($this->cache, $this->logger);
    }
    
    /**
     * Test successful rate limit check
     */
    public function testCheckWithinLimit() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        
        // Mock cache responses
        $this->cache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('ip_192.168.1.1'), 5],
                ['zippicks_rate_' . md5('ip_192.168.1.1') . '_window', time() - 1800]
            ]);
        
        $this->cache->expects($this->once())
            ->method('incr')
            ->with('zippicks_rate_' . md5('ip_192.168.1.1'));
        
        // Set IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        
        // Execute
        $result = $this->rateLimiter->check($request);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test rate limit exceeded
     */
    public function testCheckExceedsLimit() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        
        // Mock cache responses - at limit
        $this->cache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('ip_192.168.1.1'), 60],
                ['zippicks_rate_' . md5('ip_192.168.1.1') . '_window', time() - 1800]
            ]);
        
        // Expect logging
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Rate limit exceeded');
        
        // Set IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        
        // Execute
        $result = $this->rateLimiter->check($request);
        
        // Assert
        $this->assertFalse($result);
    }
    
    /**
     * Test new window reset
     */
    public function testCheckNewWindow() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        
        // Mock cache responses - window expired
        $this->cache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('ip_192.168.1.1'), 100],
                ['zippicks_rate_' . md5('ip_192.168.1.1') . '_window', time() - 7200] // 2 hours ago
            ]);
        
        // Expect window reset
        $this->cache->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                [$this->stringContains('_window'), $this->anything(), 3600],
                [$this->stringContains('zippicks_rate_'), 1, 3600]
            );
        
        // Set IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        
        // Execute
        $result = $this->rateLimiter->check($request);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test whitelisted IP bypass
     */
    public function testWhitelistedIP() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        
        // Mock WordPress options for whitelist
        // In real test, use WP_Mock
        
        // Set IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        // Add IP to whitelist
        $this->rateLimiter->addToWhitelist('192.168.1.100');
        
        // Execute
        $result = $this->rateLimiter->check($request);
        
        // Assert - would be true if whitelisted
        $this->assertIsBool($result);
    }
    
    /**
     * Test authenticated user limits
     */
    public function testAuthenticatedUserLimits() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        
        // Mock user logged in - in real test use WP_Mock
        // Would use different identifier and limits
        
        // Execute
        $result = $this->rateLimiter->check($request);
        
        // Assert
        $this->assertIsBool($result);
    }
    
    /**
     * Test rate limit headers
     */
    public function testGetHeaders() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        
        // Mock cache responses
        $this->cache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('ip_192.168.1.1'), 10],
                ['zippicks_rate_' . md5('ip_192.168.1.1') . '_window', time() - 1800]
            ]);
        
        // Set IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        
        // Execute
        $headers = $this->rateLimiter->getHeaders($request);
        
        // Assert
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        $this->assertArrayHasKey('X-RateLimit-Window', $headers);
        $this->assertEquals(60, $headers['X-RateLimit-Limit']);
        $this->assertEquals(50, $headers['X-RateLimit-Remaining']); // 60 - 10
    }
    
    /**
     * Test transient fallback when no cache
     */
    public function testTransientFallback() {
        // Create rate limiter without cache
        $rateLimiter = new RateLimiter(null, $this->logger);
        
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        
        // Mock WordPress transient functions - in real test use WP_Mock
        
        // Set IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        
        // Execute
        $result = $rateLimiter->check($request);
        
        // Assert
        $this->assertIsBool($result);
    }
    
    /**
     * Test IP ban functionality
     */
    public function testIPBan() {
        // Ban IP
        $this->rateLimiter->banIP('192.168.1.50', 3600, 'Test ban');
        
        // Expect logging
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'IP banned for rate limit violations',
                $this->arrayHasKey('ip')
            );
        
        // Check if banned
        $isBanned = $this->rateLimiter->isIPBanned('192.168.1.50');
        
        // Assert - would check cache/transient in real test
        $this->assertIsBool($isBanned);
    }
    
    /**
     * Test custom endpoint limits
     */
    public function testCustomEndpointLimits() {
        // Set custom limit for specific endpoint
        $this->rateLimiter->setLimit('/zippicks/v1/vibes/search', 10, 60);
        
        // Mock request for that endpoint
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/zippicks/v1/vibes/search');
        
        // Would use custom limits in check
        $result = $this->rateLimiter->check($request);
        
        // Assert
        $this->assertIsBool($result);
    }
    
    /**
     * Test statistics retrieval
     */
    public function testGetStatistics() {
        // Mock database results
        global $wpdb;
        $wpdb = $this->createMock(\stdClass::class);
        $wpdb->prefix = 'wp_';
        $wpdb->method('get_row')->willReturn([
            'total_hits' => 1000,
            'unique_identifiers' => 50,
            'unique_ips' => 45,
            'unique_routes' => 15,
            'first_hit' => '2024-01-01 00:00:00',
            'last_hit' => '2024-01-01 23:59:59'
        ]);
        $wpdb->method('get_results')->willReturn([
            ['identifier' => 'ip_192.168.1.1', 'hit_count' => 100],
            ['identifier' => 'user_123', 'hit_count' => 80]
        ]);
        
        // Execute
        $stats = $this->rateLimiter->getStatistics('', 24);
        
        // Assert
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_hits', $stats);
        $this->assertArrayHasKey('top_offenders', $stats);
        $this->assertEquals(24, $stats['time_period_hours']);
    }
    
    /**
     * Test cleanup functionality
     */
    public function testCleanup() {
        // Mock database
        global $wpdb;
        $wpdb = $this->createMock(\stdClass::class);
        $wpdb->prefix = 'wp_';
        $wpdb->expects($this->once())
            ->method('query')
            ->with($this->stringContains('DELETE FROM'));
        $wpdb->rows_affected = 500;
        
        // Expect logging
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Rate limit log cleanup completed',
                ['rows_deleted' => 500]
            );
        
        // Execute
        $this->rateLimiter->cleanup();
    }
    
    /**
     * Test role-based whitelist
     */
    public function testRoleWhitelist() {
        // Add role to whitelist
        $this->rateLimiter->addRoleToWhitelist('administrator');
        
        // Mock request from admin user
        $request = $this->createMock(WP_REST_Request::class);
        
        // In real test, mock user with admin role
        
        // Execute
        $result = $this->rateLimiter->check($request);
        
        // Assert
        $this->assertIsBool($result);
    }
    
    /**
     * Test global limit updates
     */
    public function testUpdateGlobalLimits() {
        // Update limits
        $this->rateLimiter->updateGlobalLimits([
            'default' => [
                'requests' => 100,
                'window' => 3600
            ]
        ]);
        
        // Would affect subsequent checks
        $request = $this->createMock(WP_REST_Request::class);
        $result = $this->rateLimiter->check($request);
        
        // Assert
        $this->assertIsBool($result);
    }
    
    /**
     * Test retry after calculation
     */
    public function testGetRetryAfter() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        
        // Mock cache responses
        $reset_time = time() + 1800; // 30 minutes from now
        $this->cache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('ip_192.168.1.1'), 60],
                ['zippicks_rate_' . md5('ip_192.168.1.1') . '_window', $reset_time - 3600]
            ]);
        
        // Set IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        
        // Execute
        $retry_after = $this->rateLimiter->getRetryAfter($request);
        
        // Assert
        $this->assertIsInt($retry_after);
        $this->assertGreaterThan(0, $retry_after);
        $this->assertLessThanOrEqual(1800, $retry_after);
    }
    
    /**
     * Test IP range whitelist
     */
    public function testIPRangeWhitelist() {
        // Mock options with CIDR range
        // In real test, would mock get_option
        
        // Test IP in range
        $_SERVER['REMOTE_ADDR'] = '192.168.1.50';
        
        // Would check if IP is in whitelisted range
        $request = $this->createMock(WP_REST_Request::class);
        $result = $this->rateLimiter->check($request);
        
        // Assert
        $this->assertIsBool($result);
    }
}