<?php
/**
 * Middleware Service Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use Closure;
use Exception;
use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Contracts\Middleware\MiddlewareInterface;
use ZipPicks\Foundation\Contracts\Middleware\RequestInterface;
use ZipPicks\Foundation\Middleware\MiddlewarePipeline;
use ZipPicks\Foundation\Middleware\WordPressRequest;
use ZipPicks\Foundation\Middleware\Example\LoggingMiddleware;
use ZipPicks\Foundation\Services\MiddlewareServiceProvider;

class MiddlewareTest extends TestCase
{
    protected ContainerInterface $container;
    protected MiddlewarePipeline $pipeline;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create container and logger mock
        $this->container = new Container();
        
        $logger = $this->createMock(LoggerInterface::class);
        $this->container->singleton(LoggerInterface::class, fn() => $logger);
        
        // Create pipeline
        $this->pipeline = new MiddlewarePipeline($this->container, $logger);
        
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
    
    public function testBasicChainExecution(): void
    {
        $executed = [];
        
        $middleware1 = function (RequestInterface $request, Closure $next) use (&$executed) {
            $executed[] = 'before1';
            $result = $next($request);
            $executed[] = 'after1';
            return $result;
        };
        
        $middleware2 = function (RequestInterface $request, Closure $next) use (&$executed) {
            $executed[] = 'before2';
            $result = $next($request);
            $executed[] = 'after2';
            return $result;
        };
        
        $destination = function (RequestInterface $request) use (&$executed) {
            $executed[] = 'destination';
            return 'response';
        };
        
        $request = new WordPressRequest();
        $result = $this->pipeline->process($request, $destination, [$middleware1, $middleware2]);
        
        $this->assertEquals('response', $result);
        $this->assertEquals(['before1', 'before2', 'destination', 'after2', 'after1'], $executed);
    }
    
    public function testEarlyTermination(): void
    {
        $executed = [];
        
        $middleware1 = function (RequestInterface $request, Closure $next) use (&$executed) {
            $executed[] = 'middleware1';
            return 'early-response'; // Don't call $next
        };
        
        $middleware2 = function (RequestInterface $request, Closure $next) use (&$executed) {
            $executed[] = 'middleware2';
            return $next($request);
        };
        
        $destination = function (RequestInterface $request) use (&$executed) {
            $executed[] = 'destination';
            return 'response';
        };
        
        $request = new WordPressRequest();
        $result = $this->pipeline->process($request, $destination, [$middleware1, $middleware2]);
        
        $this->assertEquals('early-response', $result);
        $this->assertEquals(['middleware1'], $executed);
    }
    
    public function testExceptionPropagation(): void
    {
        $middleware = function (RequestInterface $request, Closure $next) {
            throw new Exception('Middleware error');
        };
        
        $destination = function (RequestInterface $request) {
            return 'response';
        };
        
        $request = new WordPressRequest();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Middleware error');
        
        $this->pipeline->process($request, $destination, [$middleware]);
    }
    
    public function testContainerResolvedMiddleware(): void
    {
        // Create a test middleware class
        $testMiddleware = new class implements MiddlewareInterface {
            public static bool $executed = false;
            
            public function handle(RequestInterface $request, Closure $next): mixed
            {
                self::$executed = true;
                return $next($request);
            }
        };
        
        $className = get_class($testMiddleware);
        $this->container->singleton($className, fn() => $testMiddleware);
        
        $destination = fn($request) => 'response';
        $request = new WordPressRequest();
        
        $result = $this->pipeline->process($request, $destination, [$className]);
        
        $this->assertEquals('response', $result);
        $this->assertTrue($testMiddleware::$executed);
    }
    
    public function testClosureBasedMiddleware(): void
    {
        $executed = false;
        
        $middleware = function (RequestInterface $request, Closure $next) use (&$executed) {
            $executed = true;
            return $next($request);
        };
        
        $destination = fn($request) => 'response';
        $request = new WordPressRequest();
        
        $result = $this->pipeline->process($request, $destination, [$middleware]);
        
        $this->assertEquals('response', $result);
        $this->assertTrue($executed);
    }
    
    public function testGlobalMiddleware(): void
    {
        $executed = [];
        
        $globalMiddleware = function (RequestInterface $request, Closure $next) use (&$executed) {
            $executed[] = 'global';
            return $next($request);
        };
        
        $routeMiddleware = function (RequestInterface $request, Closure $next) use (&$executed) {
            $executed[] = 'route';
            return $next($request);
        };
        
        $this->pipeline->global([$globalMiddleware]);
        
        $destination = fn($request) => 'response';
        $request = new WordPressRequest();
        
        $result = $this->pipeline->process($request, $destination, [$routeMiddleware]);
        
        $this->assertEquals('response', $result);
        $this->assertEquals(['global', 'route'], $executed);
    }
    
    public function testMiddlewareGroups(): void
    {
        $executed = [];
        
        $middleware1 = function (RequestInterface $request, Closure $next) use (&$executed) {
            $executed[] = 'middleware1';
            return $next($request);
        };
        
        $middleware2 = function (RequestInterface $request, Closure $next) use (&$executed) {
            $executed[] = 'middleware2';
            return $next($request);
        };
        
        $this->pipeline->group('web', [$middleware1, $middleware2]);
        
        $destination = fn($request) => 'response';
        $request = new WordPressRequest();
        
        $result = $this->pipeline->process($request, $destination, ['web']);
        
        $this->assertEquals('response', $result);
        $this->assertEquals(['middleware1', 'middleware2'], $executed);
    }
    
    public function testWordPressRequestImplementation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/test/path';
        $_GET['foo'] = 'bar';
        $_POST['baz'] = 'qux';
        
        $request = WordPressRequest::capture();
        
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/test/path', $request->getUri());
        $this->assertEquals('bar', $request->get('foo'));
        $this->assertEquals('qux', $request->get('baz'));
        $this->assertNull($request->get('nonexistent'));
        
        $context = $request->getContext();
        $this->assertArrayHasKey('is_admin', $context);
        $this->assertArrayHasKey('is_ajax', $context);
        $this->assertArrayHasKey('is_rest', $context);
    }
    
    public function testRequestContextModification(): void
    {
        $request = new WordPressRequest();
        
        $modifiedRequest = $request->withContext('custom_key', 'custom_value');
        
        $this->assertNotSame($request, $modifiedRequest);
        $this->assertEquals('custom_value', $modifiedRequest->getContext()['custom_key']);
    }
    
    public function testLoggingMiddleware(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                [$this->stringContains('Incoming request')],
                [$this->stringContains('Request completed')]
            );
        
        $middleware = new LoggingMiddleware($loggerMock);
        $request = new WordPressRequest();
        $destination = fn($request) => 'response';
        
        $result = $middleware->handle($request, $destination);
        
        $this->assertEquals('response', $result);
    }
    
    public function testLoggingMiddlewareWithException(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Incoming request'));
        $loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Request failed'));
        
        $middleware = new LoggingMiddleware($loggerMock);
        $request = new WordPressRequest();
        $destination = function ($request) {
            throw new Exception('Test error');
        };
        
        $this->expectException(Exception::class);
        
        $middleware->handle($request, $destination);
    }
    
    public function testServiceProviderRegistration(): void
    {
        $provider = new MiddlewareServiceProvider($this->container);
        $provider->register();
        
        $this->assertTrue($this->container->has(MiddlewarePipeline::class));
        $this->assertTrue($this->container->has('middleware'));
        
        $pipeline = $this->container->get('middleware');
        $this->assertInstanceOf(MiddlewarePipeline::class, $pipeline);
        
        // Test that it's a singleton
        $pipeline2 = $this->container->get('middleware');
        $this->assertSame($pipeline, $pipeline2);
    }
    
    public function testRouteMiddleware(): void
    {
        $executed = false;
        
        $middleware = function (RequestInterface $request, Closure $next) use (&$executed) {
            $executed = true;
            return $next($request);
        };
        
        $this->pipeline->route('/admin/users', [$middleware]);
        
        $destination = fn($request) => 'response';
        $request = new WordPressRequest();
        
        $result = $this->pipeline->processRoute('/admin/users', $request, $destination);
        
        $this->assertEquals('response', $result);
        $this->assertTrue($executed);
    }
    
    public function testInvalidMiddlewareClass(): void
    {
        $destination = fn($request) => 'response';
        $request = new WordPressRequest();
        
        // Use a class that doesn't implement MiddlewareInterface
        $invalidClass = \stdClass::class;
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must implement');
        
        $this->pipeline->process($request, $destination, [$invalidClass]);
    }
}