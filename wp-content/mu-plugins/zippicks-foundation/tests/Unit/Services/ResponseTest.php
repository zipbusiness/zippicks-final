<?php
/**
 * Response Service Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Routing\Router;
use ZipPicks\Foundation\Services\ResponseServiceProvider;

class ResponseTest extends TestCase
{
    protected ContainerInterface $container;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create container
        $this->container = new Container();
        
        // Mock logger
        $logger = $this->createMock(LoggerInterface::class);
        $this->container->singleton(LoggerInterface::class, fn() => $logger);
        
        // Mock WordPress functions
        if (!function_exists('wp_send_json')) {
            function wp_send_json($data) {
                echo json_encode($data);
                exit;
            }
        }
        if (!function_exists('wp_die')) {
            function wp_die($message = '', $title = '', $args = []) {
                echo $message;
                exit;
            }
        }
    }
    
    public function testBasicResponse(): void
    {
        $response = new Response('Hello World', 200);
        
        $this->assertEquals('Hello World', $response->getContent());
        $this->assertEquals(200, $response->getStatus());
        $this->assertFalse($response->isSent());
    }
    
    public function testJsonResponse(): void
    {
        $data = ['message' => 'Success', 'data' => ['id' => 1]];
        $response = new Response();
        $response->json($data);
        
        $this->assertEquals(json_encode($data), $response->getContent());
        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
    }
    
    public function testHtmlResponse(): void
    {
        $html = '<h1>Hello World</h1>';
        $response = new Response();
        $response->html($html);
        
        $this->assertEquals($html, $response->getContent());
        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaders()['Content-Type']);
    }
    
    public function testTextResponse(): void
    {
        $text = 'Plain text response';
        $response = new Response();
        $response->text($text);
        
        $this->assertEquals($text, $response->getContent());
        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('text/plain; charset=utf-8', $response->getHeaders()['Content-Type']);
    }
    
    public function testRedirectResponse(): void
    {
        $response = new Response();
        $response->redirect('https://example.com', 301);
        
        $this->assertEquals('', $response->getContent());
        $this->assertEquals(301, $response->getStatus());
        $this->assertEquals('https://example.com', $response->getHeaders()['Location']);
    }
    
    public function testDownloadResponse(): void
    {
        $content = 'File content';
        $filename = 'test.txt';
        $response = new Response();
        $response->download($content, $filename);
        
        $this->assertEquals($content, $response->getContent());
        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('application/octet-stream', $response->getHeaders()['Content-Type']);
        $this->assertEquals('attachment; filename="test.txt"', $response->getHeaders()['Content-Disposition']);
        $this->assertEquals('12', $response->getHeaders()['Content-Length']);
    }
    
    public function testErrorResponse(): void
    {
        $response = new Response();
        $response->error('Validation failed', 422, ['email' => 'Invalid email']);
        
        $expected = [
            'success' => false,
            'message' => 'Validation failed',
            'errors' => ['email' => 'Invalid email']
        ];
        
        $this->assertEquals(json_encode($expected), $response->getContent());
        $this->assertEquals(422, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
    }
    
    public function testNoContentResponse(): void
    {
        $response = new Response();
        $response->noContent();
        
        $this->assertEquals('', $response->getContent());
        $this->assertEquals(204, $response->getStatus());
    }
    
    public function testStatusCodes(): void
    {
        $response = new Response();
        
        $response->setStatus(404, 'Not Found');
        $this->assertEquals(404, $response->getStatus());
        
        $response->setStatus(500);
        $this->assertEquals(500, $response->getStatus());
    }
    
    public function testHeaders(): void
    {
        $response = new Response();
        
        $response->header('X-Custom-Header', 'value');
        $response->header('X-Another-Header', 'another');
        
        $headers = $response->getHeaders();
        $this->assertEquals('value', $headers['X-Custom-Header']);
        $this->assertEquals('another', $headers['X-Another-Header']);
        
        // Test replace
        $response->header('X-Custom-Header', 'new-value');
        $headers = $response->getHeaders();
        $this->assertEquals('new-value', $headers['X-Custom-Header']);
        
        // Test no replace
        $response->header('X-Custom-Header', 'ignored', false);
        $headers = $response->getHeaders();
        $this->assertEquals('new-value', $headers['X-Custom-Header']);
    }
    
    public function testCookies(): void
    {
        $response = new Response();
        
        $response->cookie('session', 'abc123', time() + 3600);
        $response->cookie('remember', 'yes', time() + 86400, '/', '', true, true);
        
        // Can't test actual cookie setting without output, but verify method chaining
        $this->assertInstanceOf(Response::class, $response);
    }
    
    public function testMethodChaining(): void
    {
        $response = new Response();
        
        $result = $response
            ->setStatus(201)
            ->header('X-Test', 'value')
            ->setContent('Created')
            ->cookie('test', 'value');
        
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(201, $response->getStatus());
        $this->assertEquals('value', $response->getHeaders()['X-Test']);
        $this->assertEquals('Created', $response->getContent());
    }
    
    public function testStaticFactoryMethods(): void
    {
        $response = Response::make('content', 200, ['X-Header' => 'value']);
        
        $this->assertEquals('content', $response->getContent());
        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('value', $response->getHeaders()['X-Header']);
    }
    
    public function testSuccessHelper(): void
    {
        $response = Response::success(['id' => 1], 'Created', 201);
        
        $expected = [
            'success' => true,
            'message' => 'Created',
            'data' => ['id' => 1]
        ];
        
        $this->assertEquals(json_encode($expected), $response->getContent());
        $this->assertEquals(201, $response->getStatus());
    }
    
    public function testRouterResponseNormalization(): void
    {
        $router = new Router($this->container);
        
        // Test array normalization
        $router->get('/json', fn() => ['key' => 'value']);
        $route = $router->matchRoute('GET', '/json');
        $response = $router->dispatch($route, new \stdClass());
        
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('{"key":"value"}', $response->getContent());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        
        // Test string normalization
        $router->get('/html', fn() => '<h1>Hello</h1>');
        $route = $router->matchRoute('GET', '/html');
        $response = $router->dispatch($route, new \stdClass());
        
        $this->assertEquals('<h1>Hello</h1>', $response->getContent());
        
        // Test boolean normalization
        $router->get('/bool', fn() => true);
        $route = $router->matchRoute('GET', '/bool');
        $response = $router->dispatch($route, new \stdClass());
        
        $this->assertEquals('{"success":true}', $response->getContent());
        
        // Test null normalization
        $router->get('/null', fn() => null);
        $route = $router->matchRoute('GET', '/null');
        $response = $router->dispatch($route, new \stdClass());
        
        $this->assertEquals('', $response->getContent());
        
        // Test Response object passthrough
        $router->get('/response', fn() => (new Response())->json(['test' => 'data']));
        $route = $router->matchRoute('GET', '/response');
        $response = $router->dispatch($route, new \stdClass());
        
        $this->assertEquals('{"test":"data"}', $response->getContent());
    }
    
    public function testServiceProviderRegistration(): void
    {
        $provider = new ResponseServiceProvider($this->container);
        $provider->register();
        
        $this->assertTrue($this->container->has('response'));
        $this->assertTrue($this->container->has(ResponseInterface::class));
        
        // Test factory
        $factory = $this->container->get('response');
        $response = $factory('test', 201, ['X-Custom' => 'header']);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('test', $response->getContent());
        $this->assertEquals(201, $response->getStatus());
        $this->assertEquals('header', $response->getHeaders()['X-Custom']);
    }
    
    public function testJsonEncodingError(): void
    {
        $response = new Response();
        
        // Create a recursive reference that can't be encoded
        $data = [];
        $data['self'] = &$data;
        
        $response->json($data);
        
        // Should fallback to error message
        $this->assertEquals('{"error":"JSON encoding failed"}', $response->getContent());
    }
    
    public function testHeaderNormalization(): void
    {
        $response = new Response();
        
        $response->header('content-type', 'text/html');
        $response->header('X-CUSTOM-HEADER', 'value');
        $response->header('x_another_header', 'another');
        
        $headers = $response->getHeaders();
        
        // All should be normalized to proper case
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('X-Custom-Header', $headers);
        $this->assertArrayHasKey('X_Another_Header', $headers);
    }
    
    public function testComplexJsonResponse(): void
    {
        $data = [
            'users' => [
                ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Jane']
            ],
            'meta' => [
                'total' => 2,
                'page' => 1
            ]
        ];
        
        $response = Response::make()->json($data, 200, ['X-Total-Count' => '2']);
        
        $this->assertEquals(json_encode($data), $response->getContent());
        $this->assertEquals('2', $response->getHeaders()['X-Total-Count']);
    }
    
    public function testMultipleStatusChanges(): void
    {
        $response = new Response();
        
        $response->setStatus(200);
        $this->assertEquals(200, $response->getStatus());
        
        $response->json(['error' => 'Not found'], 404);
        $this->assertEquals(404, $response->getStatus());
        
        $response->error('Server error', 500);
        $this->assertEquals(500, $response->getStatus());
    }
    
    public function testStatusAliasMethod(): void
    {
        $response = new Response();
        
        // Test status() method as alias for setStatus()
        $response->status(403, 'Forbidden');
        $this->assertEquals(403, $response->getStatus());
        
        // Test method chaining
        $result = $response->status(200)->header('X-Test', 'Value');
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(200, $response->getStatus());
    }
    
    public function testViewResponse(): void
    {
        // Create a test view file
        $viewDir = sys_get_temp_dir() . '/zippicks_test_views';
        mkdir($viewDir, 0777, true);
        $viewPath = $viewDir . '/test-view.php';
        file_put_contents($viewPath, '<?php echo "Hello, " . $name . "!"; ?>');
        
        // Mock the getViewPaths method to return our test path
        $response = $this->getMockBuilder(Response::class)
            ->onlyMethods(['getViewPaths'])
            ->getMock();
        
        $response->method('getViewPaths')
            ->willReturn([$viewPath]);
        
        $response->view('test-view', ['name' => 'World']);
        
        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaders()['Content-Type']);
        $this->assertEquals('Hello, World!', $response->getContent());
        
        // Clean up
        unlink($viewPath);
        rmdir($viewDir);
    }
    
    public function testViewNotFound(): void
    {
        $response = new Response();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('View file not found: non-existent-view');
        
        $response->view('non-existent-view');
    }
    
    public function testCliContextDetection(): void
    {
        // Create a partial mock that overrides isCli()
        $response = $this->getMockBuilder(Response::class)
            ->onlyMethods(['isCli'])
            ->getMock();
        
        // Test CLI mode - headers should not be sent
        $response->method('isCli')->willReturn(true);
        
        // In CLI mode, headers and cookies should not cause errors
        $response->header('Content-Type', 'text/plain');
        $response->cookie('test', 'value');
        
        // Verify content is still sent
        ob_start();
        $response->text('CLI output')->send();
        $output = ob_get_clean();
        
        $this->assertEquals('CLI output', $output);
    }
    
    public function testSendPreventsMultipleCalls(): void
    {
        $response = new Response();
        $response->text('Test content');
        
        // First send
        ob_start();
        $response->send();
        $output1 = ob_get_clean();
        
        $this->assertTrue($response->isSent());
        $this->assertEquals('Test content', $output1);
        
        // Second send should not output anything
        ob_start();
        $response->send();
        $output2 = ob_get_clean();
        
        $this->assertEquals('', $output2);
    }
    
    public function testEventDispatchingOnSend(): void
    {
        // Mock event dispatcher
        $events = $this->createMock(\ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface::class);
        $events->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                ['response.sending', $this->callback(function($data) {
                    return $data['status'] === 200 && 
                           $data['type'] === 'application/json';
                })],
                ['response.sent', $this->callback(function($data) {
                    return $data['status'] === 200 && 
                           $data['type'] === 'application/json' &&
                           $data['size'] === 13; // Length of {"test":true}
                })]
            );
        
        $this->container->singleton('events', fn() => $events);
        
        // Set global container for foundation() helper
        global $testContainer;
        $testContainer = $this->container;
        
        $response = new Response();
        $response->json(['test' => true]);
        
        ob_start();
        $response->send();
        ob_end_clean();
    }
    
    public function testGetContentType(): void
    {
        $response = new Response();
        
        // Default content type
        $response->text('plain');
        ob_start();
        $response->send();
        ob_end_clean();
        
        // JSON content type
        $response = new Response();
        $response->json(['data' => 'test']);
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        
        // HTML content type
        $response = new Response();
        $response->html('<p>Test</p>');
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaders()['Content-Type']);
    }
    
    public function testResponseServiceProviderWithLogging(): void
    {
        // Mock logger
        $logger = $this->createMock(LoggerInterface::class);
        $loggerChannel = $this->createMock(LoggerInterface::class);
        
        $logger->expects($this->any())
            ->method('channel')
            ->with('response')
            ->willReturn($loggerChannel);
        
        $loggerChannel->expects($this->once())
            ->method('info')
            ->with('Response service initialized', $this->anything());
        
        $this->container->singleton('logger', fn() => $logger);
        
        // Mock events for response logging
        $events = $this->createMock(\ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface::class);
        $events->expects($this->exactly(2))
            ->method('listen')
            ->withConsecutive(
                ['response.sending', $this->isType('callable')],
                ['response.sent', $this->isType('callable')]
            );
        
        $this->container->singleton('events', fn() => $events);
        
        // Register provider
        $provider = new ResponseServiceProvider($this->container);
        $provider->register();
        $provider->boot();
    }
    
    public function testViewPathResolution(): void
    {
        $response = new Response();
        
        // Use reflection to test protected method
        $reflection = new \ReflectionMethod($response, 'getViewPaths');
        $reflection->setAccessible(true);
        
        // Test absolute path
        $paths = $reflection->invoke($response, '/absolute/path/view');
        $this->assertEquals(['/absolute/path/view.php'], $paths);
        
        // Test relative path
        $paths = $reflection->invoke($response, 'admin.dashboard');
        $this->assertGreaterThan(3, count($paths)); // Should have multiple paths
        $this->assertStringContainsString('admin/dashboard.php', $paths[0]);
    }
}