<?php
/**
 * Auth Middleware Tests
 *
 * @package ZipPicks\Foundation\Tests\Unit\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ZipPicks\Foundation\Middleware\Auth\Authenticate;
use ZipPicks\Foundation\Middleware\Auth\Authorize;
use ZipPicks\Foundation\Contracts\Auth\AuthManagerInterface;
use ZipPicks\Foundation\Contracts\Auth\GuardInterface;
use ZipPicks\Foundation\Contracts\Middleware\RequestInterface;
use ZipPicks\Foundation\Auth\UnauthenticatedException;
use ZipPicks\Foundation\Auth\UnauthorizedException;
use ZipPicks\Foundation\Models\User;

/**
 * Auth middleware test suite
 *
 * @since 1.0.0
 */
class MiddlewareAuthTest extends TestCase
{
    /**
     * Auth manager mock
     *
     * @var MockObject&AuthManagerInterface
     */
    private AuthManagerInterface $authManager;

    /**
     * Guard mock
     *
     * @var MockObject&GuardInterface
     */
    private GuardInterface $guard;

    /**
     * Request mock
     *
     * @var MockObject&RequestInterface
     */
    private RequestInterface $request;

    /**
     * Set up test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->authManager = $this->createMock(AuthManagerInterface::class);
        $this->guard = $this->createMock(GuardInterface::class);
        $this->request = $this->createMock(RequestInterface::class);

        // Configure request
        $this->request->method('fullUrl')->willReturn('https://example.com/admin');
    }

    /**
     * Test authenticate middleware with authenticated user
     *
     * @return void
     */
    public function testAuthenticateMiddlewareWithAuthenticatedUser(): void
    {
        $middleware = new Authenticate($this->authManager);

        // Configure auth manager to return authenticated
        $this->authManager->method('check')->willReturn(true);

        $nextCalled = false;
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return 'response';
        };

        $result = $middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('response', $result);
    }

    /**
     * Test authenticate middleware with unauthenticated user
     *
     * @return void
     */
    public function testAuthenticateMiddlewareWithUnauthenticatedUser(): void
    {
        $middleware = new Authenticate($this->authManager);

        // Configure auth manager to return unauthenticated
        $this->authManager->method('check')->willReturn(false);

        $this->expectException(UnauthenticatedException::class);
        $this->expectExceptionMessage('Authentication required.');

        $next = function ($request) {
            return 'response';
        };

        $middleware->handle($this->request, $next);
    }

    /**
     * Test authenticate middleware with specific guards
     *
     * @return void
     */
    public function testAuthenticateMiddlewareWithSpecificGuards(): void
    {
        $middleware = new Authenticate($this->authManager);
        $middleware->guards(['api', 'web']);

        // Mock guard instances
        $apiGuard = $this->createMock(AuthManagerInterface::class);
        $webGuard = $this->createMock(AuthManagerInterface::class);

        $apiGuard->method('check')->willReturn(false);
        $webGuard->method('check')->willReturn(true);

        $this->authManager->method('guard')
            ->willReturnMap([
                ['api', $apiGuard],
                ['web', $webGuard],
            ]);

        $nextCalled = false;
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return 'response';
        };

        $result = $middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('response', $result);
    }

    /**
     * Test authenticate middleware guards method
     *
     * @return void
     */
    public function testAuthenticateMiddlewareGuardsMethod(): void
    {
        $middleware = new Authenticate($this->authManager);
        
        // Test with string
        $result = $middleware->guards('api');
        $this->assertSame($middleware, $result);

        // Test with array
        $result = $middleware->guards(['api', 'web']);
        $this->assertSame($middleware, $result);
    }

    /**
     * Test authorize middleware with allowed ability
     *
     * @return void
     */
    public function testAuthorizeMiddlewareWithAllowedAbility(): void
    {
        $middleware = new Authorize($this->guard);
        $middleware->ability('edit_posts');

        $this->guard->method('hasUser')->willReturn(true);
        $this->guard->method('denies')->with('edit_posts')->willReturn(false);

        $nextCalled = false;
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return 'response';
        };

        $result = $middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('response', $result);
    }

    /**
     * Test authorize middleware with denied ability
     *
     * @return void
     */
    public function testAuthorizeMiddlewareWithDeniedAbility(): void
    {
        $middleware = new Authorize($this->guard);
        $middleware->ability('manage_options');

        $this->guard->method('hasUser')->willReturn(true);
        $this->guard->method('denies')->with('manage_options')->willReturn(true);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('You do not have permission to access this resource.');

        $next = function ($request) {
            return 'response';
        };

        $middleware->handle($this->request, $next);
    }

    /**
     * Test authorize middleware without user
     *
     * @return void
     */
    public function testAuthorizeMiddlewareWithoutUser(): void
    {
        $middleware = new Authorize($this->guard);
        $middleware->ability('edit_posts');

        $this->guard->method('hasUser')->willReturn(false);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Authentication required to access this resource.');

        $next = function ($request) {
            return 'response';
        };

        $middleware->handle($this->request, $next);
    }

    /**
     * Test authorize middleware helper methods
     *
     * @return void
     */
    public function testAuthorizeMiddlewareHelperMethods(): void
    {
        $middleware = new Authorize($this->guard);

        // Test role method
        $result = $middleware->role('administrator');
        $this->assertSame($middleware, $result);

        // Test anyRole method
        $result = $middleware->anyRole(['editor', 'author']);
        $this->assertSame($middleware, $result);

        // Test admin method
        $result = $middleware->admin();
        $this->assertSame($middleware, $result);

        // Test critic method
        $result = $middleware->critic();
        $this->assertSame($middleware, $result);

        // Test businessOwner method
        $result = $middleware->businessOwner();
        $this->assertSame($middleware, $result);

        // Test canManageReviews method
        $result = $middleware->canManageReviews();
        $this->assertSame($middleware, $result);

        // Test canManageBusinesses method
        $result = $middleware->canManageBusinesses();
        $this->assertSame($middleware, $result);
    }

    /**
     * Test authorize middleware with arguments
     *
     * @return void
     */
    public function testAuthorizeMiddlewareWithArguments(): void
    {
        $middleware = new Authorize($this->guard);
        $middleware->ability('owns_post', 123);

        $this->guard->method('hasUser')->willReturn(true);
        $this->guard->method('denies')
            ->with('owns_post', 123)
            ->willReturn(false);

        $nextCalled = false;
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return 'response';
        };

        $result = $middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('response', $result);
    }

    /**
     * Test unauthenticated exception rendering
     *
     * @return void
     */
    public function testUnauthenticatedExceptionRendering(): void
    {
        $exception = new UnauthenticatedException(
            'Please log in.',
            ['web'],
            'https://example.com/login'
        );

        // Test JSON rendering
        $this->request->method('expectsJson')->willReturn(true);
        $this->request->method('isAjax')->willReturn(false);

        $response = $exception->render($this->request);

        $this->assertNotNull($response);
        $this->assertEquals(401, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);

        $content = json_decode($response->getContent(), true);
        $this->assertFalse($content['success']);
        $this->assertEquals('Please log in.', $content['message']);
        $this->assertEquals(['web'], $content['guards']);

        // Test redirect rendering
        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('expectsJson')->willReturn(false);
        $this->request->method('isAjax')->willReturn(false);
        $this->request->method('fullUrl')->willReturn('https://example.com/admin');

        $response = $exception->redirectTo('https://example.com/custom-login')->render($this->request);

        $this->assertNotNull($response);
        $this->assertEquals(302, $response->getStatus());
        $this->assertEquals('https://example.com/custom-login', $response->getHeaders()['Location']);
    }

    /**
     * Test unauthorized exception rendering
     *
     * @return void
     */
    public function testUnauthorizedExceptionRendering(): void
    {
        $exception = new UnauthorizedException(
            'Access denied.',
            'manage_options'
        );

        // Test JSON rendering
        $this->request->method('expectsJson')->willReturn(true);
        $this->request->method('isAjax')->willReturn(false);

        $response = $exception->render($this->request);

        $this->assertNotNull($response);
        $this->assertEquals(403, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);

        $content = json_decode($response->getContent(), true);
        $this->assertFalse($content['success']);
        $this->assertEquals('Access denied.', $content['message']);
        $this->assertEquals('manage_options', $content['ability']);

        // Test HTML rendering
        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('expectsJson')->willReturn(false);
        $this->request->method('isAjax')->willReturn(false);

        $response = $exception->render($this->request);

        $this->assertNotNull($response);
        $this->assertEquals(403, $response->getStatus());
        $this->assertEquals('text/html', $response->getHeaders()['Content-Type']);
        $this->assertStringContainsString('403', $response->getContent());
        $this->assertStringContainsString('Access denied.', $response->getContent());
    }

    /**
     * Test exception report methods
     *
     * @return void
     */
    public function testExceptionReportMethods(): void
    {
        $unauthenticated = new UnauthenticatedException();
        $this->assertFalse($unauthenticated->report());

        $unauthorized = new UnauthorizedException();
        $this->assertFalse($unauthorized->report());
    }
}