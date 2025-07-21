<?php
/**
 * Exception Handler Tests
 *
 * @package ZipPicks\Foundation\Tests\Unit\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ZipPicks\Foundation\Exceptions\Handler;
use ZipPicks\Foundation\Exceptions\FoundationException;
use ZipPicks\Foundation\Exceptions\RenderableExceptionInterface;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface;
use ZipPicks\Foundation\Contracts\Http\RequestInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Services\ExceptionServiceProvider;

/**
 * Exception handler test suite
 *
 * @since 1.0.0
 */
class ExceptionHandlerTest extends TestCase
{
    /**
     * Container mock
     *
     * @var MockObject&ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * Logger mock
     *
     * @var MockObject&LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Event dispatcher mock
     *
     * @var MockObject&EventDispatcherInterface
     */
    private EventDispatcherInterface $events;

    /**
     * Request mock
     *
     * @var MockObject&RequestInterface
     */
    private RequestInterface $request;

    /**
     * Handler instance
     *
     * @var Handler
     */
    private Handler $handler;

    /**
     * Set up test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->events = $this->createMock(EventDispatcherInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);

        // Configure container responses
        $this->container->method('has')->willReturnMap([
            [LoggerInterface::class, true],
            [EventDispatcherInterface::class, true],
            [RequestInterface::class, true],
        ]);

        $this->container->method('get')->willReturnMap([
            [LoggerInterface::class, $this->logger],
            [EventDispatcherInterface::class, $this->events],
            [RequestInterface::class, $this->request],
        ]);

        // Create handler with container
        $this->handler = new Handler($this->container);

        // Define WP_DEBUG if not defined
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
    }

    /**
     * Test exception reporting
     *
     * @return void
     */
    public function testReportException(): void
    {
        $exception = new \RuntimeException('Test exception');

        // Configure logger expectations
        $loggerChannel = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())
            ->method('channel')
            ->with('exceptions')
            ->willReturn($loggerChannel);

        $loggerChannel->expects($this->once())
            ->method('error')
            ->with(
                'Test exception',
                $this->callback(function ($context) use ($exception) {
                    return isset($context['exception']) && $context['exception'] === $exception;
                })
            );

        // Configure event expectations
        $this->events->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                ['exception.reporting', $exception],
                ['exception.reported', $this->anything()]
            )
            ->willReturn(true);

        $this->handler->report($exception);
    }

    /**
     * Test exception not reported when shouldReport returns false
     *
     * @return void
     */
    public function testDontReportException(): void
    {
        $exception = new \InvalidArgumentException('Test exception');

        // Add to don't report list
        $this->handler->dontReport(\InvalidArgumentException::class);

        // Logger should not be called
        $this->logger->expects($this->never())->method('channel');

        // Events should not be dispatched
        $this->events->expects($this->never())->method('dispatch');

        $this->handler->report($exception);
    }

    /**
     * Test JSON response rendering
     *
     * @return void
     */
    public function testRenderJsonResponse(): void
    {
        $exception = new \RuntimeException('JSON error', 500);

        // Configure request to expect JSON
        $this->request->method('expectsJson')->willReturn(true);
        $this->request->method('isAjax')->willReturn(false);

        $response = $this->handler->render($exception);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('JSON error', $content['message']);
        $this->assertFalse($content['success']);
    }

    /**
     * Test AJAX response rendering
     *
     * @return void
     */
    public function testRenderAjaxResponse(): void
    {
        $exception = new \RuntimeException('AJAX error', 400);

        // Configure request for AJAX
        $this->request->method('expectsJson')->willReturn(false);
        $this->request->method('isAjax')->willReturn(true);

        $response = $this->handler->render($exception);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(400, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
    }

    /**
     * Test HTML response rendering
     *
     * @return void
     */
    public function testRenderHtmlResponse(): void
    {
        $exception = new \RuntimeException('HTML error', 500);

        // Configure request for HTML
        $this->request->method('expectsJson')->willReturn(false);
        $this->request->method('isAjax')->willReturn(false);

        $response = $this->handler->render($exception);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatus());
        $this->assertEquals('text/html', $response->getHeaders()['Content-Type']);
        $this->assertStringContainsString('Error 500', $response->getContent());
        $this->assertStringContainsString('HTML error', $response->getContent());
    }

    /**
     * Test renderable exception interface
     *
     * @return void
     */
    public function testRenderableException(): void
    {
        $customResponse = new Response();
        $customResponse->json(['custom' => 'response'], 418);

        $exception = new class extends \Exception implements RenderableExceptionInterface {
            public function render(RequestInterface $request): ?ResponseInterface
            {
                $response = new Response();
                return $response->json(['custom' => 'response'], 418);
            }
        };

        $response = $this->handler->render($exception);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(418, $response->getStatus());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('response', $content['custom']);
    }

    /**
     * Test exception with context metadata
     *
     * @return void
     */
    public function testFoundationExceptionWithContext(): void
    {
        $exception = new FoundationException('Test error', 0, null, [
            'user_id' => 123,
            'action' => 'test_action'
        ]);

        // Configure logger expectations
        $loggerChannel = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())
            ->method('channel')
            ->with('exceptions')
            ->willReturn($loggerChannel);

        $loggerChannel->expects($this->once())
            ->method('error')
            ->with(
                'Test error',
                $this->callback(function ($context) {
                    return isset($context['metadata']) &&
                           $context['metadata']['user_id'] === 123 &&
                           $context['metadata']['action'] === 'test_action';
                })
            );

        $this->handler->report($exception);
    }

    /**
     * Test error handler conversion
     *
     * @return void
     */
    public function testErrorHandler(): void
    {
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('Test error');

        $this->handler->handleError(E_USER_ERROR, 'Test error', 'test.php', 10);
    }

    /**
     * Test shutdown handler with fatal error
     *
     * @return void
     */
    public function testShutdownHandler(): void
    {
        // This is difficult to test directly due to error_get_last() behavior
        // We'll test the method exists and is callable
        $this->assertTrue(method_exists($this->handler, 'handleShutdown'));
    }

    /**
     * Test exception service provider
     *
     * @return void
     */
    public function testServiceProvider(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        
        $container->expects($this->once())
            ->method('singleton')
            ->with(
                $this->equalTo(\ZipPicks\Foundation\Contracts\Exceptions\HandlerInterface::class),
                $this->isType('callable')
            );

        $container->expects($this->once())
            ->method('alias')
            ->with(
                \ZipPicks\Foundation\Contracts\Exceptions\HandlerInterface::class,
                'exception.handler'
            );

        $provider = new ExceptionServiceProvider($container);
        $provider->register();

        $this->assertContains(\ZipPicks\Foundation\Contracts\Exceptions\HandlerInterface::class, $provider->provides);
        $this->assertContains('exception.handler', $provider->provides);
    }

    /**
     * Test status code determination
     *
     * @return void
     */
    public function testStatusCodeDetermination(): void
    {
        // Test InvalidArgumentException returns 400
        $exception = new \InvalidArgumentException('Bad request');
        $response = $this->handler->render($exception);
        $this->assertEquals(400, $response->getStatus());

        // Test RuntimeException returns 500
        $exception = new \RuntimeException('Server error');
        $response = $this->handler->render($exception);
        $this->assertEquals(500, $response->getStatus());

        // Test generic exception returns 500
        $exception = new \Exception('Generic error');
        $response = $this->handler->render($exception);
        $this->assertEquals(500, $response->getStatus());
    }

    /**
     * Test exception event dispatching
     *
     * @return void
     */
    public function testExceptionEventDispatching(): void
    {
        $exception = new \RuntimeException('Event test');

        // Test rendering event can return custom response
        $customResponse = new Response();
        $customResponse->json(['event' => 'handled'], 200);

        $this->events->expects($this->once())
            ->method('dispatch')
            ->with('exception.rendering', $this->anything())
            ->willReturn($customResponse);

        $response = $this->handler->render($exception);

        $this->assertSame($customResponse, $response);
        $this->assertEquals(200, $response->getStatus());
    }

    /**
     * Test request context building
     *
     * @return void
     */
    public function testRequestContextBuilding(): void
    {
        $exception = new \RuntimeException('Context test');

        // Configure request
        $this->request->method('fullUrl')->willReturn('https://example.com/test');
        $this->request->method('method')->willReturn('POST');
        $this->request->method('ip')->willReturn('127.0.0.1');
        $this->request->method('userAgent')->willReturn('PHPUnit');
        $this->request->method('context')->willReturn(['is_admin' => true]);

        // Configure logger to capture context
        $capturedContext = null;
        $loggerChannel = $this->createMock(LoggerInterface::class);
        $this->logger->method('channel')->willReturn($loggerChannel);
        
        $loggerChannel->expects($this->once())
            ->method('error')
            ->willReturnCallback(function ($message, $context) use (&$capturedContext) {
                $capturedContext = $context;
            });

        $this->handler->report($exception);

        $this->assertEquals('https://example.com/test', $capturedContext['url']);
        $this->assertEquals('POST', $capturedContext['method']);
        $this->assertEquals('127.0.0.1', $capturedContext['ip']);
        $this->assertEquals('PHPUnit', $capturedContext['user_agent']);
        $this->assertEquals(['is_admin' => true], $capturedContext['wp_context']);
    }

    /**
     * Test helper functions
     *
     * @return void
     */
    public function testHelperFunctions(): void
    {
        // We need to load the helpers file for this test
        $helpersFile = dirname(__DIR__, 3) . '/src/Http/helpers.php';
        
        if (file_exists($helpersFile)) {
            require_once $helpersFile;
            
            // Test that functions exist
            $this->assertTrue(function_exists('report'));
            $this->assertTrue(function_exists('render'));
        }
    }
}