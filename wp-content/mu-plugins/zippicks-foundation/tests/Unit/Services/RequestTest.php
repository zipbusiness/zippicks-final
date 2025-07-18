<?php
/**
 * Request Service Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Http\RequestInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Services\RequestServiceProvider;

class RequestTest extends TestCase
{
    protected ContainerInterface $container;
    protected array $originalServer = [];
    protected array $originalGet = [];
    protected array $originalPost = [];
    protected array $originalCookie = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Save original superglobals
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;
        $this->originalPost = $_POST;
        $this->originalCookie = $_COOKIE;
        
        // Reset superglobals
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_PORT' => 80,
            'HTTP_HOST' => 'localhost',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        
        // Create container
        $this->container = new Container();
        
        // Mock logger
        $logger = $this->createMock(LoggerInterface::class);
        $this->container->singleton(LoggerInterface::class, fn() => $logger);
        
        // Mock WordPress functions
        if (!function_exists('is_admin')) {
            function is_admin() { return false; }
        }
        if (!function_exists('wp_doing_ajax')) {
            function wp_doing_ajax() { return false; }
        }
        if (!function_exists('wp_doing_cron')) {
            function wp_doing_cron() { return false; }
        }
        if (!function_exists('get_current_user_id')) {
            function get_current_user_id() { return 0; }
        }
    }
    
    protected function tearDown(): void
    {
        // Restore original superglobals
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
        $_POST = $this->originalPost;
        $_COOKIE = $this->originalCookie;
        
        parent::tearDown();
    }
    
    public function testBasicRequest(): void
    {
        $request = new Request();
        
        $this->assertEquals('GET', $request->method());
        $this->assertEquals('/', $request->uri());
        $this->assertEquals('/', $request->path());
        $this->assertEquals('http://localhost/', $request->url());
        $this->assertEquals('http://localhost/', $request->fullUrl());
    }
    
    public function testRequestWithQueryParameters(): void
    {
        $_GET = ['foo' => 'bar', 'baz' => 'qux'];
        $_SERVER['REQUEST_URI'] = '/test?foo=bar&baz=qux';
        $_SERVER['QUERY_STRING'] = 'foo=bar&baz=qux';
        
        $request = Request::capture();
        
        $this->assertEquals('/test?foo=bar&baz=qux', $request->uri());
        $this->assertEquals('/test', $request->path());
        $this->assertEquals('bar', $request->query('foo'));
        $this->assertEquals('qux', $request->query('baz'));
        $this->assertNull($request->query('nonexistent'));
        $this->assertEquals('default', $request->query('nonexistent', 'default'));
        
        $all = $request->queryAll();
        $this->assertEquals(['foo' => 'bar', 'baz' => 'qux'], $all);
    }
    
    public function testRequestWithPostData(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['name' => 'John Doe', 'email' => 'john@example.com'];
        
        $request = Request::capture();
        
        $this->assertEquals('POST', $request->method());
        $this->assertEquals('John Doe', $request->post('name'));
        $this->assertEquals('john@example.com', $request->post('email'));
        
        $all = $request->postAll();
        $this->assertEquals(['name' => 'John Doe', 'email' => 'john@example.com'], $all);
    }
    
    public function testInputMethod(): void
    {
        $_GET = ['get_param' => 'value1'];
        $_POST = ['post_param' => 'value2'];
        
        $request = Request::capture();
        
        $this->assertEquals('value1', $request->input('get_param'));
        $this->assertEquals('value2', $request->input('post_param'));
        
        $all = $request->all();
        $this->assertArrayHasKey('get_param', $all);
        $this->assertArrayHasKey('post_param', $all);
    }
    
    public function testJsonRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        
        $json = json_encode(['name' => 'John', 'age' => 30]);
        $request = new Request([], [], [], ['Content-Type' => 'application/json'], $_SERVER, $json);
        
        $this->assertTrue($request->isJson());
        $this->assertEquals(['name' => 'John', 'age' => 30], $request->json());
        $this->assertEquals('John', $request->input('name'));
        $this->assertEquals(30, $request->input('age'));
    }
    
    public function testHeaders(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Custom-Header' => 'custom-value',
            'Authorization' => 'Bearer token123'
        ];
        
        $request = new Request([], [], [], $headers);
        
        $this->assertEquals('application/json', $request->header('Content-Type'));
        $this->assertEquals('custom-value', $request->header('X-Custom-Header'));
        $this->assertEquals('Bearer token123', $request->header('Authorization'));
        $this->assertNull($request->header('Nonexistent'));
        
        $all = $request->headers();
        $this->assertArrayHasKey('Content-Type', $all);
        $this->assertArrayHasKey('X-Custom-Header', $all);
    }
    
    public function testCookies(): void
    {
        $cookies = [
            'session_id' => 'abc123',
            'remember_token' => 'xyz789'
        ];
        
        $request = new Request([], [], $cookies);
        
        $this->assertEquals('abc123', $request->cookie('session_id'));
        $this->assertEquals('xyz789', $request->cookie('remember_token'));
        $this->assertNull($request->cookie('nonexistent'));
        
        $all = $request->cookies();
        $this->assertEquals($cookies, $all);
    }
    
    public function testMethodChecks(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = Request::capture();
        
        $this->assertTrue($request->isPost());
        $this->assertFalse($request->isGet());
        $this->assertFalse($request->isPut());
        $this->assertFalse($request->isDelete());
        $this->assertFalse($request->isPatch());
        $this->assertTrue($request->isMethod('POST'));
        $this->assertTrue($request->isMethod('post')); // Case insensitive
    }
    
    public function testAjaxDetection(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $request = Request::capture();
        
        $this->assertTrue($request->isAjax());
        
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $request = Request::capture();
        
        $this->assertFalse($request->isAjax());
    }
    
    public function testSecureDetection(): void
    {
        // HTTPS on
        $_SERVER['HTTPS'] = 'on';
        $request = Request::capture();
        $this->assertTrue($request->isSecure());
        
        // HTTPS off
        $_SERVER['HTTPS'] = 'off';
        $request = Request::capture();
        $this->assertFalse($request->isSecure());
        
        // X-Forwarded-Proto
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $request = Request::capture();
        $this->assertTrue($request->isSecure());
        
        // Port 443
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        $_SERVER['SERVER_PORT'] = 443;
        $request = Request::capture();
        $this->assertTrue($request->isSecure());
    }
    
    public function testIpAddressDetection(): void
    {
        // Direct IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $request = Request::capture();
        $this->assertEquals('192.168.1.100', $request->ip());
        
        // X-Forwarded-For
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 192.168.1.100';
        $request = Request::capture();
        $this->assertEquals('192.168.1.100', $request->ip()); // Should get first non-private IP
        
        // Client IP
        $_SERVER['HTTP_CLIENT_IP'] = '203.0.113.0';
        $request = Request::capture();
        $this->assertEquals('203.0.113.0', $request->ip());
    }
    
    public function testUserAgent(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Test Browser)';
        $request = Request::capture();
        
        $this->assertEquals('Mozilla/5.0 (Test Browser)', $request->userAgent());
        
        unset($_SERVER['HTTP_USER_AGENT']);
        $request = Request::capture();
        
        $this->assertEquals('', $request->userAgent());
    }
    
    public function testRouteParameters(): void
    {
        $request = new Request();
        
        $this->assertEquals([], $request->routeParameters());
        $this->assertNull($request->route('id'));
        
        $request->setRouteParameters(['id' => '123', 'slug' => 'test-post']);
        
        $this->assertEquals('123', $request->route('id'));
        $this->assertEquals('test-post', $request->route('slug'));
        $this->assertEquals(['id' => '123', 'slug' => 'test-post'], $request->routeParameters());
    }
    
    public function testOnlyAndExceptMethods(): void
    {
        $_GET = ['foo' => 'bar', 'baz' => 'qux'];
        $_POST = ['name' => 'John', 'email' => 'john@example.com'];
        
        $request = Request::capture();
        
        $only = $request->only(['foo', 'name']);
        $this->assertEquals(['foo' => 'bar', 'name' => 'John'], $only);
        
        $except = $request->except(['baz', 'email']);
        $this->assertEquals(['foo' => 'bar', 'name' => 'John'], $except);
    }
    
    public function testHasMethods(): void
    {
        $_GET = ['foo' => 'bar'];
        $_POST = ['name' => 'John', 'email' => 'john@example.com'];
        
        $request = Request::capture();
        
        $this->assertTrue($request->has('foo'));
        $this->assertTrue($request->has('name'));
        $this->assertFalse($request->has('nonexistent'));
        
        $this->assertTrue($request->hasAll(['foo', 'name']));
        $this->assertFalse($request->hasAll(['foo', 'nonexistent']));
        
        $this->assertTrue($request->hasAny(['foo', 'nonexistent']));
        $this->assertFalse($request->hasAny(['nonexistent', 'another']));
    }
    
    public function testMergeAndReplace(): void
    {
        $_GET = ['foo' => 'bar'];
        $request = Request::capture();
        
        $merged = $request->merge(['baz' => 'qux', 'foo' => 'overridden']);
        
        $this->assertNotSame($request, $merged); // Immutable
        $this->assertEquals('bar', $request->input('foo')); // Original unchanged
        $this->assertEquals('overridden', $merged->input('foo'));
        $this->assertEquals('qux', $merged->input('baz'));
        
        $replaced = $request->replace(['new' => 'data']);
        
        $this->assertNotSame($request, $replaced); // Immutable
        $this->assertEquals('bar', $request->input('foo')); // Original unchanged
        $this->assertNull($replaced->input('foo'));
        $this->assertEquals('data', $replaced->input('new'));
    }
    
    public function testExpectsJson(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $request = Request::capture();
        $this->assertTrue($request->expectsJson());
        
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/json';
        $request = Request::capture();
        $this->assertTrue($request->expectsJson());
        
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $request = Request::capture();
        $this->assertFalse($request->expectsJson());
    }
    
    public function testWordPressContext(): void
    {
        $request = Request::capture();
        $context = $request->context();
        
        $this->assertArrayHasKey('is_admin', $context);
        $this->assertArrayHasKey('is_ajax', $context);
        $this->assertArrayHasKey('is_rest', $context);
        $this->assertArrayHasKey('is_cron', $context);
        $this->assertArrayHasKey('is_cli', $context);
        $this->assertArrayHasKey('user_id', $context);
        
        $this->assertFalse($request->isAdmin());
        $this->assertFalse($request->isRest());
        $this->assertFalse($request->isCron());
        $this->assertFalse($request->isCli());
    }
    
    public function testServiceProviderRegistration(): void
    {
        $provider = new RequestServiceProvider($this->container);
        $provider->register();
        
        $this->assertTrue($this->container->has(RequestInterface::class));
        $this->assertTrue($this->container->has('request'));
        
        $request = $this->container->get('request');
        $this->assertInstanceOf(RequestInterface::class, $request);
        
        // Test singleton
        $request2 = $this->container->get('request');
        $this->assertSame($request, $request2);
    }
    
    public function testDotNotationAccess(): void
    {
        $_POST = [
            'user' => [
                'name' => 'John',
                'profile' => [
                    'age' => 30,
                    'city' => 'New York'
                ]
            ]
        ];
        
        $request = Request::capture();
        
        $this->assertEquals('John', $request->input('user.name'));
        $this->assertEquals(30, $request->input('user.profile.age'));
        $this->assertEquals('New York', $request->input('user.profile.city'));
        $this->assertNull($request->input('user.profile.country'));
        $this->assertEquals('USA', $request->input('user.profile.country', 'USA'));
    }
    
    public function testInputSanitization(): void
    {
        $_GET = [
            'clean' => 'normal value',
            'dirty' => "value\0with\0null\0bytes",
            'whitespace' => "  trimmed  \r\n",
            'nested' => [
                'clean' => 'nested value',
                'dirty' => "nested\0value"
            ]
        ];
        
        $request = Request::capture();
        
        $this->assertEquals('normal value', $request->query('clean'));
        $this->assertEquals('valuewithnullbytes', $request->query('dirty'));
        $this->assertEquals('trimmed', $request->query('whitespace'));
        $this->assertEquals('nested value', $request->query('nested.clean'));
        $this->assertEquals('nestedvalue', $request->query('nested.dirty'));
    }
    
    public function testJsonParsingErrors(): void
    {
        $request = new Request([], [], [], ['Content-Type' => 'application/json'], [], 'invalid json');
        
        $this->assertTrue($request->isJson());
        $this->assertEquals([], $request->json(true));
        $this->assertEquals(new \stdClass(), $request->json(false));
    }
    
    public function testFullUrlWithQueryString(): void
    {
        $_SERVER['REQUEST_URI'] = '/test/path?foo=bar&baz=qux';
        $_SERVER['QUERY_STRING'] = 'foo=bar&baz=qux';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTPS'] = 'on';
        
        $request = Request::capture();
        
        $this->assertEquals('/test/path', $request->path());
        $this->assertEquals('https://example.com/test/path', $request->url());
        $this->assertEquals('https://example.com/test/path?foo=bar&baz=qux', $request->fullUrl());
    }
}