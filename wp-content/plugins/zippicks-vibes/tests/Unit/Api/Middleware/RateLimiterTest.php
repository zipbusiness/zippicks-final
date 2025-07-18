<?php
/**
 * Unit tests for RateLimiter Middleware
 * 
 * Tests rate limiting algorithms, user tiers, distributed limiting
 *
 * @package ZipPicksVibes\Tests\Unit\Api\Middleware
 */

namespace ZipPicksVibes\Tests\Unit\Api\Middleware;

use ZipPicksVibes\Tests\TestCase;
use ZipPicksVibes\Api\Middleware\RateLimiter;
use PHPUnit\Framework\MockObject\MockObject;
use WP_REST_Request;
use WP_Error;

class RateLimiterTest extends TestCase {
    
    /**
     * @var RateLimiter
     */
    private $rateLimiter;
    
    /**
     * @var MockObject
     */
    private $mockCache;
    
    /**
     * @var MockObject
     */
    private $mockLogger;
    
    public function setUp(): void {
        parent::setUp();
        
        // Create mocks
        $this->mockCache = $this->createMock(\stdClass::class);
        $this->mockLogger = $this->createMock(\stdClass::class);
        
        // Create rate limiter with mocks
        $this->rateLimiter = new RateLimiter($this->mockCache, $this->mockLogger);
        
        // Mock $_SERVER variables
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['HTTP_USER_AGENT'] = 'Test User Agent';
    }
    
    /**
     * Test basic rate limiting with cache
     * 
     * @group ratelimit
     */
    public function test_basic_rate_limiting_with_cache() {
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        // First request should pass
        $this->mockCache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('ip_192.168.1.100'), 0],
                ['zippicks_rate_' . md5('ip_192.168.1.100') . '_window', time() - 1800]
            ]);
        
        $this->mockCache->expects($this->once())
            ->method('incr')
            ->with('zippicks_rate_' . md5('ip_192.168.1.100'));
        
        $result = $this->rateLimiter->check($request);
        $this->assertTrue($result);
    }
    
    /**
     * Test rate limit exceeded
     * 
     * @group ratelimit
     */
    public function test_rate_limit_exceeded() {
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        // Simulate limit exceeded
        $this->mockCache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('ip_192.168.1.100'), 60], // At limit
                ['zippicks_rate_' . md5('ip_192.168.1.100') . '_window', time() - 1800]
            ]);
        
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with('Rate limit exceeded');
        
        $result = $this->rateLimiter->check($request);
        $this->assertFalse($result);
    }
    
    /**
     * Test different user tiers
     * 
     * @group ratelimit
     * @group tiers
     */
    public function test_different_user_tiers() {
        // Test authenticated user
        $this->loginAsUser();
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        $this->mockCache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('user_1'), 100], // Under authenticated limit (200)
                ['zippicks_rate_' . md5('user_1') . '_window', time() - 1800]
            ]);
        
        $result = $this->rateLimiter->check($request);
        $this->assertTrue($result);
        
        // Test admin user
        $this->loginAsAdmin();
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        $this->mockCache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('user_1'), 500], // Under admin limit (1000)
                ['zippicks_rate_' . md5('user_1') . '_window', time() - 1800]
            ]);
        
        $result = $this->rateLimiter->check($request);
        $this->assertTrue($result);
    }
    
    /**
     * Test window reset
     * 
     * @group ratelimit
     * @group window
     */
    public function test_window_reset() {
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        // Simulate expired window
        $this->mockCache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('ip_192.168.1.100'), 100],
                ['zippicks_rate_' . md5('ip_192.168.1.100') . '_window', time() - 7200] // 2 hours ago
            ]);
        
        // Expect window reset
        $this->mockCache->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                [$this->stringContains('_window'), $this->anything(), 3600],
                [$this->stringContains('zippicks_rate_'), 1, 3600]
            );
        
        $result = $this->rateLimiter->check($request);
        $this->assertTrue($result);
    }
    
    /**
     * Test IP whitelisting
     * 
     * @group ratelimit
     * @group whitelist
     */
    public function test_ip_whitelisting() {
        // Add IP to whitelist
        $this->rateLimiter->addToWhitelist('192.168.1.100');
        
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        // Should not check rate limit for whitelisted IP
        $this->mockCache->expects($this->never())
            ->method('get');
        
        $result = $this->rateLimiter->check($request);
        $this->assertTrue($result);
    }
    
    /**
     * Test CIDR range whitelisting
     * 
     * @group ratelimit
     * @group whitelist
     */
    public function test_cidr_range_whitelisting() {
        // Mock option with CIDR range
        update_option('zippicks_rate_limit_whitelist', [
            'ips' => [],
            'ranges' => ['192.168.1.0/24'],
            'roles' => []
        ]);
        
        $rateLimiter = new RateLimiter($this->mockCache, $this->mockLogger);
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        $result = $rateLimiter->check($request);
        $this->assertTrue($result);
    }
    
    /**
     * Test role-based whitelisting
     * 
     * @group ratelimit
     * @group whitelist
     */
    public function test_role_based_whitelisting() {
        // Add editor role to whitelist
        $this->rateLimiter->addRoleToWhitelist('editor');
        
        // Login as editor
        $this->loginAsUser(['editor']);
        
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        // Should not check rate limit for whitelisted role
        $this->mockCache->expects($this->never())
            ->method('get');
        
        $result = $this->rateLimiter->check($request);
        $this->assertTrue($result);
    }
    
    /**
     * Test endpoint-specific limits
     * 
     * @group ratelimit
     * @group endpoints
     */
    public function test_endpoint_specific_limits() {
        // Set custom limit for specific endpoint
        $this->rateLimiter->setLimit('/zippicks/v1/vibes/search', 10, 300);
        
        $request = $this->createMockRequest('/zippicks/v1/vibes/search', 'GET');
        
        $this->mockCache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('ip_192.168.1.100'), 9], // Under custom limit
                ['zippicks_rate_' . md5('ip_192.168.1.100') . '_window', time() - 150]
            ]);
        
        $result = $this->rateLimiter->check($request);
        $this->assertTrue($result);
    }
    
    /**
     * Test rate limit headers
     * 
     * @group ratelimit
     * @group headers
     */
    public function test_rate_limit_headers() {
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        $this->mockCache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('ip_192.168.1.100'), 30],
                ['zippicks_rate_' . md5('ip_192.168.1.100') . '_window', time() - 1800]
            ]);
        
        $headers = $this->rateLimiter->getHeaders($request);
        
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        $this->assertArrayHasKey('X-RateLimit-Window', $headers);
        
        $this->assertEquals(60, $headers['X-RateLimit-Limit']);
        $this->assertEquals(30, $headers['X-RateLimit-Remaining']);
    }
    
    /**
     * Test retry after calculation
     * 
     * @group ratelimit
     */
    public function test_retry_after_calculation() {
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        $window_start = time() - 1800;
        $this->mockCache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('ip_192.168.1.100'), 60],
                ['zippicks_rate_' . md5('ip_192.168.1.100') . '_window', $window_start]
            ]);
        
        $retryAfter = $this->rateLimiter->getRetryAfter($request);
        
        // Should be approximately 1800 seconds (remaining window time)
        $this->assertGreaterThan(1700, $retryAfter);
        $this->assertLessThan(1900, $retryAfter);
    }
    
    /**
     * Test error response generation
     * 
     * @group ratelimit
     */
    public function test_error_response_generation() {
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        $window_start = time() - 3000;
        $this->mockCache->method('get')
            ->willReturnMap([
                ['zippicks_rate_' . md5('ip_192.168.1.100'), 60],
                ['zippicks_rate_' . md5('ip_192.168.1.100') . '_window', $window_start]
            ]);
        
        $error = $this->rateLimiter->getErrorResponse($request);
        
        $this->assertInstanceOf(WP_Error::class, $error);
        $this->assertEquals('rate_limit_exceeded', $error->get_error_code());
        $this->assertStringContainsString('Rate limit exceeded', $error->get_error_message());
        
        $data = $error->get_error_data();
        $this->assertEquals(429, $data['status']);
        $this->assertArrayHasKey('retry_after', $data);
    }
    
    /**
     * Test rate limiting without cache (using transients)
     * 
     * @group ratelimit
     * @group transients
     */
    public function test_rate_limiting_without_cache() {
        // Create rate limiter without cache
        $rateLimiter = new RateLimiter(null, $this->mockLogger);
        
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        // First request should pass
        $result1 = $rateLimiter->check($request);
        $this->assertTrue($result1);
        
        // Simulate multiple requests
        $key = 'zippicks_rate_' . md5('ip_192.168.1.100');
        set_transient($key, [
            'count' => 59,
            'window_start' => time()
        ], 3600);
        
        // 60th request should pass
        $result2 = $rateLimiter->check($request);
        $this->assertTrue($result2);
        
        // 61st request should fail
        $data = get_transient($key);
        $data['count'] = 60;
        set_transient($key, $data, 3600);
        
        $result3 = $rateLimiter->check($request);
        $this->assertFalse($result3);
    }
    
    /**
     * Test client IP detection with various headers
     * 
     * @group ratelimit
     * @group ip
     */
    public function test_client_ip_detection() {
        // Test Cloudflare header
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '10.0.0.1';
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->rateLimiter);
        $method = $reflection->getMethod('getClientIP');
        $method->setAccessible(true);
        
        $ip = $method->invoke($this->rateLimiter, $request);
        $this->assertEquals('10.0.0.1', $ip);
        
        // Test X-Forwarded-For
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.2, 10.0.0.3';
        
        $ip = $method->invoke($this->rateLimiter, $request);
        $this->assertEquals('10.0.0.2', $ip);
    }
    
    /**
     * Test IP ban functionality
     * 
     * @group ratelimit
     * @group ban
     */
    public function test_ip_ban_functionality() {
        // Ban IP
        $this->rateLimiter->banIP('192.168.1.100', 3600, 'Test ban');
        
        // Check if banned
        $banned = $this->rateLimiter->isIPBanned('192.168.1.100');
        $this->assertTrue($banned);
        
        // Check different IP
        $notBanned = $this->rateLimiter->isIPBanned('192.168.1.101');
        $this->assertFalse($notBanned);
    }
    
    /**
     * Test statistics generation
     * 
     * @group ratelimit
     * @group statistics
     */
    public function test_statistics_generation() {
        // Create test table
        $this->createRateLimitLogTable();
        
        // Add test data
        $this->addRateLimitLogEntry('ip_192.168.1.100', '/zippicks/v1/vibes');
        $this->addRateLimitLogEntry('ip_192.168.1.100', '/zippicks/v1/vibes');
        $this->addRateLimitLogEntry('ip_192.168.1.101', '/zippicks/v1/vibes');
        
        $stats = $this->rateLimiter->getStatistics('', 24);
        
        $this->assertArrayHasKey('total_hits', $stats);
        $this->assertArrayHasKey('unique_ips', $stats);
        $this->assertArrayHasKey('top_offenders', $stats);
        
        $this->assertEquals(3, $stats['total_hits']);
        $this->assertEquals(2, $stats['unique_identifiers']);
    }
    
    /**
     * Test cleanup functionality
     * 
     * @group ratelimit
     * @group cleanup
     */
    public function test_cleanup_functionality() {
        // Create test table
        $this->createRateLimitLogTable();
        
        // Add old entry
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_rate_limit_log';
        $wpdb->insert($table, [
            'identifier' => 'test',
            'ip_address' => '192.168.1.100',
            'route' => '/test',
            'method' => 'GET',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-10 days'))
        ]);
        
        // Run cleanup
        $this->rateLimiter->cleanup();
        
        // Verify old entry was deleted
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $this->assertEquals(0, $count);
    }
    
    /**
     * Test global limits update
     * 
     * @group ratelimit
     * @group config
     */
    public function test_global_limits_update() {
        $newLimits = [
            'default' => [
                'requests' => 100,
                'window' => 3600
            ]
        ];
        
        $this->rateLimiter->updateGlobalLimits($newLimits);
        
        // Verify limits were saved
        $config = get_option('zippicks_rate_limits');
        $this->assertEquals(100, $config['limits']['default']['requests']);
    }
    
    /**
     * Test distributed rate limiting
     * 
     * @group ratelimit
     * @group distributed
     */
    public function test_distributed_rate_limiting() {
        // Simulate multiple servers checking same user
        $request = $this->createMockRequest('/zippicks/v1/vibes', 'GET');
        
        // Server 1 increments
        $this->mockCache->expects($this->at(0))
            ->method('get')
            ->with('zippicks_rate_' . md5('ip_192.168.1.100'))
            ->willReturn(30);
        
        // Server 2 increments
        $this->mockCache->expects($this->at(1))
            ->method('get')
            ->with('zippicks_rate_' . md5('ip_192.168.1.100'))
            ->willReturn(31);
        
        // Both should see the shared count
        $this->mockCache->expects($this->exactly(2))
            ->method('incr');
        
        $result1 = $this->rateLimiter->check($request);
        $result2 = $this->rateLimiter->check($request);
        
        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }
    
    /**
     * Helper method to create mock request
     */
    private function createMockRequest($route, $method) {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn($route);
        $request->method('get_method')->willReturn($method);
        $request->method('get_header')->willReturn(null);
        return $request;
    }
    
    /**
     * Helper method to create rate limit log table
     */
    private function createRateLimitLogTable() {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_rate_limit_log';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            identifier VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            route VARCHAR(255) NOT NULL,
            method VARCHAR(10) NOT NULL,
            user_agent TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $wpdb->query($sql);
    }
    
    /**
     * Helper method to add rate limit log entry
     */
    private function addRateLimitLogEntry($identifier, $route) {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_rate_limit_log';
        
        $wpdb->insert($table, [
            'identifier' => $identifier,
            'ip_address' => '192.168.1.100',
            'route' => $route,
            'method' => 'GET',
            'user_agent' => 'Test',
            'timestamp' => current_time('mysql')
        ]);
    }
}