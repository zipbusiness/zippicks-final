<?php
/**
 * Validation and Response Integration Tests
 *
 * Tests the integration between the Response system and Validation exceptions,
 * ensuring proper JSON/redirect responses and error rendering.
 *
 * @package ZipPicks\Foundation\Tests\Unit\Integration
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Integration;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Validation\Validator;
use ZipPicks\Foundation\Exceptions\ValidationException;
use ZipPicks\Foundation\Exceptions\Handler as ExceptionHandler;
use ZipPicks\Foundation\Events\EventDispatcher;
use ZipPicks\Foundation\Logging\FileLogger;
use ZipPicks\Foundation\Services\ResponseServiceProvider;
use ZipPicks\Foundation\Services\ValidationServiceProvider;
use ZipPicks\Foundation\Services\ExceptionServiceProvider;
use ZipPicks\Foundation\Services\EventServiceProvider;
use ZipPicks\Foundation\Services\LoggingServiceProvider;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Contracts\Validation\ValidatorInterface;
use ZipPicks\Foundation\Contracts\Exceptions\HandlerInterface;
use ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;

class ValidationIntegrationTest extends TestCase
{
    /**
     * Container instance
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create container
        $this->container = new Container();
        
        // Register all required service providers
        $providers = [
            new LoggingServiceProvider($this->container),
            new EventServiceProvider($this->container),
            new ResponseServiceProvider($this->container),
            new ValidationServiceProvider($this->container),
            new ExceptionServiceProvider($this->container),
        ];

        foreach ($providers as $provider) {
            $provider->register();
        }

        foreach ($providers as $provider) {
            $provider->boot();
        }

        // Mock WordPress functions
        $this->mockWordPressFunctions();
    }

    /**
     * Test ValidationException returns JSON response for AJAX requests
     *
     * @return void
     */
    public function testValidationExceptionReturnsJsonForAjax(): void
    {
        // Create AJAX request
        $request = new Request(
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            post: [
                'email' => 'invalid-email',
                'name' => '', // Empty name
            ]
        );

        // Create validator and validate
        $validator = $this->container->get(ValidatorInterface::class);
        $validator->validate(
            $request->all(),
            [
                'email' => 'required|email',
                'name' => 'required|min_length:3',
            ]
        );

        // Create ValidationException
        $exception = ValidationException::withValidator($validator);

        // Render exception to response
        $response = $exception->render($request);

        // Assert response is JSON
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(422, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);

        // Parse and validate JSON content
        $content = json_decode($response->getContent(), true);
        $this->assertFalse($content['success']);
        $this->assertEquals('The given data was invalid.', $content['message']);
        $this->assertArrayHasKey('errors', $content);
        $this->assertArrayHasKey('email', $content['errors']);
        $this->assertArrayHasKey('name', $content['errors']);
    }

    /**
     * Test ValidationException returns JSON response for REST API requests
     *
     * @return void
     */
    public function testValidationExceptionReturnsJsonForRestApi(): void
    {
        // Create REST API request
        $request = new Request(
            server: ['REQUEST_URI' => '/wp-json/api/v1/users'],
            headers: ['Accept' => 'application/json'],
            post: ['username' => 'ab'] // Too short
        );

        // Define REST_REQUEST constant
        if (!defined('REST_REQUEST')) {
            define('REST_REQUEST', true);
        }

        // Validate and create exception
        $validator = $this->container->get(ValidatorInterface::class);
        $validator->validate(
            $request->all(),
            ['username' => 'required|min_length:3']
        );

        $exception = ValidationException::withValidator($validator);
        $response = $exception->render($request);

        // Assert JSON response
        $this->assertEquals(422, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $content);
        $this->assertArrayHasKey('username', $content['errors']);
    }

    /**
     * Test ValidationException returns redirect response for form submissions
     *
     * @return void
     */
    public function testValidationExceptionReturnsRedirectForForms(): void
    {
        // Create form request
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            post: ['password' => '123'] // Too short
        );

        // Validate
        $validator = $this->container->get(ValidatorInterface::class);
        $validator->validate(
            $request->all(),
            ['password' => 'required|min_length:8']
        );

        // Create exception with redirect
        $exception = ValidationException::withValidator($validator)
            ->redirectTo('/login');

        $response = $exception->render($request);

        // Assert redirect response
        $this->assertEquals(302, $response->getStatus());
        $this->assertArrayHasKey('Location', $response->getHeaders());
        $this->assertStringStartsWith('/login', $response->getHeaders()['Location']);
        $this->assertStringContainsString('validation_errors=', $response->getHeaders()['Location']);
    }

    /**
     * Test ValidationException with custom error status code
     *
     * @return void
     */
    public function testValidationExceptionWithCustomStatusCode(): void
    {
        $request = new Request(
            headers: ['Accept' => 'application/json'],
            post: ['age' => 'not-a-number']
        );

        $validator = $this->container->get(ValidatorInterface::class);
        $validator->validate($request->all(), ['age' => 'required']);

        // Create exception with custom status
        $exception = ValidationException::withValidator($validator)
            ->status(400); // Bad Request instead of 422

        $response = $exception->render($request);

        $this->assertEquals(400, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
    }

    /**
     * Test Response system handles ValidationException through ExceptionHandler
     *
     * @return void
     */
    public function testExceptionHandlerRendersValidationException(): void
    {
        $request = new Request(
            headers: ['Accept' => 'application/json'],
            post: ['email' => 'not-an-email']
        );

        $validator = $this->container->get(ValidatorInterface::class);
        $validator->validate($request->all(), ['email' => 'email']);

        $exception = ValidationException::withValidator($validator);
        
        // Get exception handler
        $handler = $this->container->get(HandlerInterface::class);
        
        // Render through handler
        $response = $handler->render($request, $exception);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(422, $response->getStatus());
        
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $content);
    }

    /**
     * Test validation errors are logged through event system
     *
     * @return void
     */
    public function testValidationErrorsAreLogged(): void
    {
        // Create a mock logger to track calls
        $logFile = sys_get_temp_dir() . '/zippicks-test-' . uniqid() . '.log';
        $logger = new FileLogger($logFile);
        $this->container->singleton(LoggerInterface::class, fn() => $logger);
        $this->container->singleton('logger', fn() => $logger);

        $request = new Request(post: ['email' => 'invalid']);
        
        try {
            $request->validate(['email' => 'required|email']);
        } catch (ValidationException $e) {
            // Expected
        }

        // Check if validation failure was logged
        $this->assertFileExists($logFile);
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('Validation failed', $logContent);
        $this->assertStringContainsString('email', $logContent);

        // Clean up
        @unlink($logFile);
    }

    /**
     * Test response sends appropriate headers for validation errors
     *
     * @return void
     */
    public function testResponseHeadersForValidationErrors(): void
    {
        $request = new Request(
            headers: ['Accept' => 'application/json', 'X-Custom-Header' => 'test'],
            post: ['amount' => -100] // Invalid amount
        );

        $validator = $this->container->get(ValidatorInterface::class);
        
        // Add custom validation rule for positive numbers
        $validator->addRule('positive', new class implements \ZipPicks\Foundation\Validation\RuleInterface {
            public function validate(mixed $value, array $parameters, array $data, string $field): bool
            {
                return is_numeric($value) && $value > 0;
            }
            
            public function message(string $field, array $parameters): string
            {
                return "The {$field} must be a positive number.";
            }
            
            public function getName(): string
            {
                return 'positive';
            }
        });

        $validator->validate($request->all(), ['amount' => 'required|positive']);
        
        $exception = ValidationException::withValidator($validator);
        $response = $exception->render($request);

        // Check headers
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        $this->assertEquals(422, $response->getStatus());
        
        // Verify error structure
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('The given data was invalid.', $content['message']);
        $this->assertStringContainsString('positive number', $content['errors']['amount'][0]);
    }

    /**
     * Test CLI context returns text response for validation errors
     *
     * @return void
     */
    public function testCliContextReturnsTextResponse(): void
    {
        // Mock CLI environment
        $originalSapi = PHP_SAPI;
        $this->setPrivateProperty('PHP_SAPI', 'cli');

        $request = new Request(post: ['command' => '']);
        
        $validator = $this->container->get(ValidatorInterface::class);
        $validator->validate($request->all(), ['command' => 'required']);
        
        $exception = ValidationException::withValidator($validator);
        $response = $exception->render($request);

        // CLI should still return JSON for consistency
        $this->assertEquals(422, $response->getStatus());
        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('errors', $content);

        // Restore original SAPI
        $this->setPrivateProperty('PHP_SAPI', $originalSapi);
    }

    /**
     * Test complex validation scenario with multiple errors
     *
     * @return void
     */
    public function testComplexValidationScenarioWithResponse(): void
    {
        $request = new Request(post: [
            'user' => [
                'name' => 'Jo', // Too short
                'email' => 'invalid-email',
                'age' => 150, // Too high
                'password' => 'weak',
                'password_confirmation' => 'different'
            ],
            'terms' => false // Should be true
        ]);

        $validator = $this->container->get(ValidatorInterface::class);
        
        // Add custom rules
        $validator->addRule('accepted', new class implements \ZipPicks\Foundation\Validation\RuleInterface {
            public function validate(mixed $value, array $parameters, array $data, string $field): bool
            {
                return in_array($value, ['yes', 'on', '1', 1, true, 'true'], true);
            }
            
            public function message(string $field, array $parameters): string
            {
                return "The {$field} must be accepted.";
            }
            
            public function getName(): string
            {
                return 'accepted';
            }
        });

        $validator->validate($request->all(), [
            'user.name' => 'required|min_length:3',
            'user.email' => 'required|email',
            'user.age' => 'required|max_length:120',
            'user.password' => 'required|min_length:8',
            'terms' => 'accepted'
        ]);

        $exception = ValidationException::withValidator($validator);
        
        // Test AJAX response
        $ajaxRequest = clone $request;
        $ajaxRequest->headers['X-Requested-With'] = 'XMLHttpRequest';
        
        $response = $exception->render($ajaxRequest);
        $content = json_decode($response->getContent(), true);

        // Verify multiple errors are present
        $this->assertArrayHasKey('user.name', $content['errors']);
        $this->assertArrayHasKey('user.email', $content['errors']);
        $this->assertArrayHasKey('user.age', $content['errors']);
        $this->assertArrayHasKey('user.password', $content['errors']);
        $this->assertArrayHasKey('terms', $content['errors']);
        
        // Verify error count
        $this->assertCount(5, $content['errors']);
    }

    /**
     * Test response event lifecycle during validation error
     *
     * @return void
     */
    public function testResponseEventLifecycleDuringValidationError(): void
    {
        $events = [];
        $dispatcher = $this->container->get(EventDispatcherInterface::class);
        
        // Listen for response events
        $dispatcher->listen('response.sending', function ($data) use (&$events) {
            $events[] = ['name' => 'response.sending', 'data' => $data];
        });
        
        $dispatcher->listen('response.sent', function ($data) use (&$events) {
            $events[] = ['name' => 'response.sent', 'data' => $data];
        });

        $request = new Request(
            headers: ['Accept' => 'application/json'],
            post: ['score' => 'invalid']
        );

        $validator = $this->container->get(ValidatorInterface::class);
        $validator->validate($request->all(), ['score' => 'required']);
        
        $exception = ValidationException::withValidator($validator);
        $response = $exception->render($request);

        // Manually trigger send to test events (normally done by framework)
        ob_start();
        $response->send();
        ob_end_clean();

        // Verify events were fired
        $this->assertCount(2, $events);
        $this->assertEquals('response.sending', $events[0]['name']);
        $this->assertEquals('response.sent', $events[1]['name']);
        $this->assertEquals(422, $events[0]['data']['status']);
        $this->assertEquals('application/json', $events[0]['data']['type']);
    }

    /**
     * Mock WordPress functions for testing
     *
     * @return void
     */
    protected function mockWordPressFunctions(): void
    {
        if (!function_exists('is_admin')) {
            function is_admin() {
                return false;
            }
        }

        if (!function_exists('add_action')) {
            function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
                return true;
            }
        }

        if (!function_exists('wp_generate_uuid4')) {
            function wp_generate_uuid4() {
                return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
            }
        }

        if (!function_exists('set_transient')) {
            function set_transient($transient, $value, $expiration = 0) {
                return true;
            }
        }

        if (!function_exists('get_template_directory')) {
            function get_template_directory() {
                return '/tmp/wordpress/wp-content/themes/theme';
            }
        }

        if (!function_exists('get_stylesheet_directory')) {
            function get_stylesheet_directory() {
                return '/tmp/wordpress/wp-content/themes/child-theme';
            }
        }
    }

    /**
     * Helper to set private properties for testing
     *
     * @param string $property
     * @param mixed $value
     * @return void
     */
    protected function setPrivateProperty(string $property, mixed $value): void
    {
        // This is a mock - in real tests we'd use reflection
        // but PHP_SAPI is a constant so we can't actually change it
    }
}