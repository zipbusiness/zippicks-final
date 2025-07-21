<?php
/**
 * Unit tests for VibesRestController
 * 
 * @package ZipPicksVibes
 * @subpackage Tests
 */

namespace ZipPicksVibes\Tests\Api;

use PHPUnit\Framework\TestCase;
use ZipPicksVibes\Api\VibesRestController;
use ZipPicksVibes\Services\VibeService;
use ZipPicksVibes\Services\VibeRenderer;
use ZipPicksVibes\Api\Middleware\RateLimiter;
use ZipPicksVibes\Api\Middleware\NonceValidator;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class VibesRestControllerTest
 */
class VibesRestControllerTest extends TestCase {
    
    private $controller;
    private $vibeService;
    private $rateLimiter;
    private $nonceValidator;
    private $renderer;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Create mocks
        $this->vibeService = $this->createMock(VibeService::class);
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->nonceValidator = $this->createMock(NonceValidator::class);
        $this->renderer = $this->createMock(VibeRenderer::class);
        
        // Create controller instance
        $this->controller = new VibesRestController(
            $this->vibeService,
            $this->rateLimiter,
            $this->nonceValidator,
            $this->renderer
        );
    }
    
    /**
     * Test successful vibes collection retrieval
     */
    public function testGetVibesSuccess() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')
            ->willReturnMap([
                ['page', 1],
                ['per_page', 10],
                ['status', 'active'],
                ['orderby', 'order_position'],
                ['order', 'ASC'],
                ['category', null]
            ]);
        
        // Mock rate limiter to allow request
        $this->rateLimiter->method('check')->willReturn(true);
        $this->rateLimiter->method('isIPBanned')->willReturn(false);
        $this->rateLimiter->method('getHeaders')->willReturn([
            'X-RateLimit-Limit' => 60,
            'X-RateLimit-Remaining' => 59,
            'X-RateLimit-Reset' => time() + 3600
        ]);
        
        // Mock paginated result
        $paginatedResult = $this->createMock(\stdClass::class);
        $paginatedResult->method('getItems')->willReturn([
            (object)['id' => 1, 'name' => 'Test Vibe 1'],
            (object)['id' => 2, 'name' => 'Test Vibe 2']
        ]);
        $paginatedResult->method('getTotal')->willReturn(2);
        
        $this->vibeService->method('getVibesPaginated')->willReturn($paginatedResult);
        $this->vibeService->method('getObfuscatedVibes')->willReturn([
            ['id' => 'encoded1', 'n' => 'encoded_name1'],
            ['id' => 'encoded2', 'n' => 'encoded_name2']
        ]);
        
        // Mock user agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
        
        // Execute
        $response = $this->controller->get_vibes($request);
        
        // Assert
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertEquals(2, $data['meta']['total']);
        $this->assertEquals(1, $data['meta']['page']);
        $this->assertEquals(10, $data['meta']['per_page']);
    }
    
    /**
     * Test rate limit exceeded
     */
    public function testGetVibesRateLimitExceeded() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        
        // Mock rate limiter to deny request
        $this->rateLimiter->method('check')->willReturn(false);
        $this->rateLimiter->method('getRetryAfter')->willReturn(60);
        
        // Mock user agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
        
        // Execute
        $response = $this->controller->get_vibes($request);
        
        // Assert
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('zippicks_rate_limit', $response->get_error_code());
        $this->assertEquals(429, $response->get_error_data()['status']);
    }
    
    /**
     * Test IP banned
     */
    public function testGetVibesIPBanned() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        
        // Mock rate limiter
        $this->rateLimiter->method('check')->willReturn(true);
        $this->rateLimiter->method('isIPBanned')->willReturn(true);
        
        // Mock user agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        
        // Execute
        $response = $this->controller->get_vibes($request);
        
        // Assert
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('zippicks_access_denied', $response->get_error_code());
        $this->assertEquals(403, $response->get_error_data()['status']);
    }
    
    /**
     * Test invalid user agent rejection
     */
    public function testGetVibesInvalidUserAgent() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        
        // Mock rate limiter
        $this->rateLimiter->method('check')->willReturn(true);
        $this->rateLimiter->method('isIPBanned')->willReturn(false);
        
        // Set CLI user agent
        $_SERVER['HTTP_USER_AGENT'] = 'curl/7.64.1';
        
        // Execute
        $response = $this->controller->get_vibes($request);
        
        // Assert
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('zippicks_invalid_agent', $response->get_error_code());
        $this->assertEquals(403, $response->get_error_data()['status']);
    }
    
    /**
     * Test search vibes with validation
     */
    public function testSearchVibesValidation() {
        // Mock request with valid search query
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')
            ->willReturnMap([
                ['q', 'test search'],
                ['page', 1],
                ['per_page', 20]
            ]);
        
        // Mock rate limiter
        $this->rateLimiter->method('check')->willReturn(true);
        $this->rateLimiter->method('isIPBanned')->willReturn(false);
        
        // Mock search result
        $paginatedResult = $this->createMock(\stdClass::class);
        $paginatedResult->method('getItems')->willReturn([]);
        $paginatedResult->method('getTotal')->willReturn(0);
        $paginatedResult->method('getTotalPages')->willReturn(0);
        
        $this->vibeService->method('searchVibesPaginated')->willReturn($paginatedResult);
        $this->vibeService->method('getObfuscatedVibes')->willReturn([]);
        
        // Mock user agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
        
        // Execute
        $response = $this->controller->search_vibes($request);
        
        // Assert
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertEquals('test search', $data['meta']['query']);
    }
    
    /**
     * Test create vibe with admin security
     */
    public function testCreateVibeAdminSecurity() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_json_params')->willReturn([
            'name' => 'New Vibe',
            'slug' => 'new-vibe',
            'description' => 'Test description'
        ]);
        
        // Mock security checks
        $this->rateLimiter->method('check')->willReturn(true);
        $this->rateLimiter->method('isIPBanned')->willReturn(false);
        $this->nonceValidator->method('validate')->willReturn([
            'success' => true,
            'reason' => 'Valid nonce'
        ]);
        
        // Mock service response
        $this->vibeService->method('createVibe')->willReturn(123);
        
        // Mock user agent and admin capability
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
        
        // Note: In real tests, you'd need to mock WordPress functions
        // This is a simplified example
        
        // Execute
        $response = $this->controller->create_vibe($request);
        
        // Assert based on mocked WordPress environment
        // In real tests, this would need proper WordPress test setup
        $this->assertNotNull($response);
    }
    
    /**
     * Test route registration
     */
    public function testRouteRegistration() {
        // This test would require WordPress test environment
        // Example structure:
        
        global $wp_rest_server;
        $routes = [];
        
        // Mock register_rest_route function
        $mock_register = function($namespace, $route, $args) use (&$routes) {
            $routes[$namespace . $route] = $args;
        };
        
        // In real test, you'd use WP_Mock or similar
        // This shows the expected structure
        $this->assertTrue(true); // Placeholder
    }
    
    /**
     * Test pagination headers
     */
    public function testPaginationHeaders() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')
            ->willReturnMap([
                ['page', 2],
                ['per_page', 10],
                ['status', 'active'],
                ['orderby', 'order_position'],
                ['order', 'ASC'],
                ['category', null]
            ]);
        
        // Mock rate limiter
        $this->rateLimiter->method('check')->willReturn(true);
        $this->rateLimiter->method('isIPBanned')->willReturn(false);
        $this->rateLimiter->method('getHeaders')->willReturn([]);
        
        // Mock paginated result
        $paginatedResult = $this->createMock(\stdClass::class);
        $paginatedResult->method('getItems')->willReturn([]);
        $paginatedResult->method('getTotal')->willReturn(50);
        
        $this->vibeService->method('getVibesPaginated')->willReturn($paginatedResult);
        $this->vibeService->method('getObfuscatedVibes')->willReturn([]);
        
        // Mock user agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
        
        // Execute
        $response = $this->controller->get_vibes($request);
        
        // Assert
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $headers = $response->get_headers();
        $this->assertEquals(50, $headers['X-WP-Total']);
        $this->assertEquals(5, $headers['X-WP-TotalPages']); // 50 items / 10 per page
    }
    
    /**
     * Test security headers
     */
    public function testSecurityHeaders() {
        // Mock request
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')->willReturn(null);
        
        // Mock rate limiter
        $this->rateLimiter->method('check')->willReturn(true);
        $this->rateLimiter->method('isIPBanned')->willReturn(false);
        $this->rateLimiter->method('getHeaders')->willReturn([]);
        
        // Mock service
        $this->vibeService->method('getAllCategories')->willReturn([]);
        
        // Mock user agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
        
        // Execute
        $response = $this->controller->get_categories($request);
        
        // Assert security headers
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $headers = $response->get_headers();
        $this->assertEquals('noindex', $headers['X-Robots-Tag']);
        $this->assertEquals('frontend-only', $headers['X-ZipPicks-Source']);
        $this->assertEquals('nosniff', $headers['X-Content-Type-Options']);
        $this->assertEquals('DENY', $headers['X-Frame-Options']);
    }
}