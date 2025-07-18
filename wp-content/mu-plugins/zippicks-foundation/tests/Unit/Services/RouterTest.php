<?php
/**
 * Router Service Tests
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
use ZipPicks\Foundation\Contracts\Middleware\RequestInterface;
use ZipPicks\Foundation\Contracts\Routing\RouteInterface;
use ZipPicks\Foundation\Contracts\Routing\RouterInterface;
use ZipPicks\Foundation\Middleware\MiddlewarePipeline;
use ZipPicks\Foundation\Middleware\WordPressRequest;
use ZipPicks\Foundation\Routing\Controllers\TestController;
use ZipPicks\Foundation\Routing\Route;
use ZipPicks\Foundation\Routing\Router;
use ZipPicks\Foundation\Services\RouterServiceProvider;

class RouterTest extends TestCase
{
    protected ContainerInterface $container;
    protected Router $router;
    protected ?MiddlewarePipeline $pipeline = null;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create container and logger mock
        $this->container = new Container();
        
        $logger = $this->createMock(LoggerInterface::class);
        $this->container->singleton(LoggerInterface::class, fn() => $logger);
        
        // Create middleware pipeline
        $this->pipeline = new MiddlewarePipeline($this->container, $logger);
        $this->container->singleton(MiddlewarePipeline::class, fn() => $this->pipeline);
        
        // Create router
        $this->router = new Router($this->container, $this->pipeline, $logger);
        
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
        if (!function_exists('wp_get_current_user')) {
            function wp_get_current_user() { 
                return (object) ['display_name' => 'Test User'];
            }
        }
    }
    
    public function testRouteRegistration(): void
    {
        $route = $this->router->get('/test', fn() => 'response');
        
        $this->assertInstanceOf(RouteInterface::class, $route);
        $this->assertEquals(['GET'], $route->getMethod());
        $this->assertEquals('/test', $route->getPath());
        
        $routes = $this->router->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame($route, $routes[0]);
    }
    
    public function testPostRouteRegistration(): void
    {
        $route = $this->router->post('/submit', fn() => 'submitted');
        
        $this->assertEquals(['POST'], $route->getMethod());
        $this->assertEquals('/submit', $route->getPath());
    }
    
    public function testAnyRouteRegistration(): void
    {
        $route = $this->router->any('/flexible', fn() => 'any method');
        
        $this->assertEquals(['ANY'], $route->getMethod());
        $this->assertTrue($route->matches('GET', '/flexible'));
        $this->assertTrue($route->matches('POST', '/flexible'));
        $this->assertTrue($route->matches('PUT', '/flexible'));
    }
    
    public function testMiddlewareAssignment(): void
    {
        $route = $this->router->get('/protected', fn() => 'protected')
            ->middleware(['auth', 'admin']);
        
        $this->assertEquals(['auth', 'admin'], $route->getMiddleware());
    }
    
    public function testRouteNaming(): void
    {
        $route = $this->router->get('/home', fn() => 'home')->name('home');
        
        $this->assertEquals('home', $route->getName());
        $this->assertSame($route, $this->router->getByName('home'));
    }
    
    public function testRouteMatching(): void
    {
        $this->router->get('/users', fn() => 'users');
        $this->router->post('/users', fn() => 'create user');
        $this->router->get('/users/{id}', fn() => 'show user');
        
        $route1 = $this->router->matchRoute('GET', '/users');
        $this->assertNotNull($route1);
        $this->assertEquals('/users', $route1->getPath());
        
        $route2 = $this->router->matchRoute('POST', '/users');
        $this->assertNotNull($route2);
        $this->assertEquals(['POST'], $route2->getMethod());
        
        $route3 = $this->router->matchRoute('GET', '/users/123');
        $this->assertNotNull($route3);
        $this->assertEquals('/users/{id}', $route3->getPath());
        
        $route4 = $this->router->matchRoute('GET', '/unknown');
        $this->assertNull($route4);
    }
    
    public function testRouteParameters(): void
    {
        $route = new Route('GET', '/users/{id}/posts/{post}', fn() => 'post');
        
        $params = $route->getParameters('/users/123/posts/456');
        $this->assertEquals(['id' => '123', 'post' => '456'], $params);
    }
    
    public function testGroupLevelMiddleware(): void
    {
        $this->router->group(['middleware' => ['auth']], function ($router) {
            $router->get('/profile', fn() => 'profile')->name('profile');
            $router->get('/settings', fn() => 'settings')->name('settings');
        });
        
        $profile = $this->router->getByName('profile');
        $settings = $this->router->getByName('settings');
        
        $this->assertEquals(['auth'], $profile->getMiddleware());
        $this->assertEquals(['auth'], $settings->getMiddleware());
    }
    
    public function testGroupPrefix(): void
    {
        $this->router->group(['prefix' => '/admin'], function ($router) {
            $router->get('/users', fn() => 'admin users')->name('admin.users');
            $router->get('/posts', fn() => 'admin posts')->name('admin.posts');
        });
        
        $users = $this->router->getByName('admin.users');
        $posts = $this->router->getByName('admin.posts');
        
        $this->assertEquals('/admin/users', $users->getPath());
        $this->assertEquals('/admin/posts', $posts->getPath());
    }
    
    public function testNestedGroups(): void
    {
        $this->router->group(['prefix' => '/api', 'middleware' => ['api']], function ($router) {
            $router->group(['prefix' => '/v1'], function ($router) {
                $router->get('/users', fn() => 'api users')
                    ->middleware(['throttle'])
                    ->name('api.v1.users');
            });
        });
        
        $route = $this->router->getByName('api.v1.users');
        
        $this->assertEquals('/api/v1/users', $route->getPath());
        $this->assertEquals(['api', 'throttle'], $route->getMiddleware());
    }
    
    public function testClosureExecution(): void
    {
        $route = $this->router->get('/test', fn($request) => 'test response');
        $request = new WordPressRequest();
        
        $response = $this->router->dispatch($route, $request);
        
        $this->assertEquals('test response', $response);
    }
    
    public function testInvokableControllerExecution(): void
    {
        $route = $this->router->get('/test', TestController::class);
        $request = new WordPressRequest();
        
        $response = $this->router->dispatch($route, $request);
        
        $this->assertIsArray($response);
        $this->assertEquals('Response from TestController', $response['message']);
    }
    
    public function testControllerMethodExecution(): void
    {
        $route = $this->router->get('/test', TestController::class . '@index');
        $request = new WordPressRequest();
        
        $response = $this->router->dispatch($route, $request);
        
        $this->assertIsArray($response);
        $this->assertEquals('TestController@index', $response['message']);
    }
    
    public function testControllerArrayExecution(): void
    {
        $route = $this->router->get('/test', [TestController::class, 'show']);
        $request = new WordPressRequest();
        
        $response = $this->router->dispatch($route, $request);
        
        $this->assertIsArray($response);
        $this->assertEquals('TestController@show', $response['message']);
    }
    
    public function testRouteParametersInRequest(): void
    {
        $route = $this->router->get('/users/{id}', TestController::class . '@show');
        $_SERVER['REQUEST_URI'] = '/users/123';
        
        $request = WordPressRequest::capture();
        $matchedRoute = $this->router->matchRoute('GET', '/users/123');
        
        $response = $this->router->dispatch($matchedRoute, $request);
        
        $this->assertEquals('123', $response['id']);
    }
    
    public function testMiddlewareIntegration(): void
    {
        $executed = [];
        
        $middleware = function (RequestInterface $request, Closure $next) use (&$executed) {
            $executed[] = 'middleware';
            return $next($request);
        };
        
        $this->pipeline->global([$middleware]);
        
        $route = $this->router->get('/test', function () use (&$executed) {
            $executed[] = 'route';
            return 'response';
        })->middleware(['test']);
        
        $request = new WordPressRequest();
        $response = $this->router->dispatch($route, $request);
        
        $this->assertEquals('response', $response);
        $this->assertEquals(['middleware', 'route'], $executed);
    }
    
    public function testErrorHandlingForMissingController(): void
    {
        $route = $this->router->get('/test', 'NonExistentController');
        $request = new WordPressRequest();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not exist');
        
        $this->router->dispatch($route, $request);
    }
    
    public function testErrorHandlingForMissingMethod(): void
    {
        $route = $this->router->get('/test', TestController::class . '@nonExistentMethod');
        $request = new WordPressRequest();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not exist on controller');
        
        $this->router->dispatch($route, $request);
    }
    
    public function testServiceProviderRegistration(): void
    {
        $provider = new RouterServiceProvider($this->container);
        $provider->register();
        
        $this->assertTrue($this->container->has(RouterInterface::class));
        $this->assertTrue($this->container->has('router'));
        
        $router = $this->container->get('router');
        $this->assertInstanceOf(RouterInterface::class, $router);
        
        // Test that it's a singleton
        $router2 = $this->container->get('router');
        $this->assertSame($router, $router2);
    }
    
    public function testMultipleMethodRoute(): void
    {
        $route = $this->router->addRoute(['GET', 'POST'], '/contact', fn() => 'contact');
        
        $this->assertTrue($route->matches('GET', '/contact'));
        $this->assertTrue($route->matches('POST', '/contact'));
        $this->assertFalse($route->matches('PUT', '/contact'));
    }
    
    public function testRouteMetadata(): void
    {
        $route = $this->router->get('/test', fn() => 'test')
            ->meta('permission', 'edit_posts')
            ->meta('cache', true);
        
        $this->assertEquals('edit_posts', $route->getMetadata('permission'));
        $this->assertTrue($route->getMetadata('cache'));
        $this->assertNull($route->getMetadata('nonexistent'));
        
        $allMeta = $route->getMetadata();
        $this->assertIsArray($allMeta);
        $this->assertArrayHasKey('permission', $allMeta);
        $this->assertArrayHasKey('cache', $allMeta);
    }
    
    public function testOptionalRouteParameters(): void
    {
        $route = new Route('GET', '/posts/{year?}/{month?}', fn() => 'posts');
        
        $this->assertTrue($route->matches('GET', '/posts'));
        $this->assertTrue($route->matches('GET', '/posts/2024'));
        $this->assertTrue($route->matches('GET', '/posts/2024/01'));
        
        $params1 = $route->getParameters('/posts');
        $this->assertEquals(['year' => '', 'month' => ''], $params1);
        
        $params2 = $route->getParameters('/posts/2024');
        $this->assertEquals(['year' => '2024', 'month' => ''], $params2);
        
        $params3 = $route->getParameters('/posts/2024/01');
        $this->assertEquals(['year' => '2024', 'month' => '01'], $params3);
    }
    
    public function testGetCurrentRoute(): void
    {
        $route1 = $this->router->get('/test1', fn() => 'test1');
        $route2 = $this->router->get('/test2', fn() => 'test2');
        
        $this->assertNull($this->router->getCurrentRoute());
        
        $this->router->matchRoute('GET', '/test2');
        $this->assertSame($route2, $this->router->getCurrentRoute());
    }
}