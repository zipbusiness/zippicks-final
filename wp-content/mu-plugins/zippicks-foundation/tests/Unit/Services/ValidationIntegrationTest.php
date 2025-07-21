<?php
/**
 * Validation Integration Tests
 *
 * @package ZipPicks\Foundation\Tests\Unit\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Validation\Validator;
use ZipPicks\Foundation\Validation\Rules\MaxLengthRule;
use ZipPicks\Foundation\Validation\Rules\CustomRule;
use ZipPicks\Foundation\Exceptions\ValidationException;
use ZipPicks\Foundation\Contracts\Validation\ValidatorInterface;
use ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;

/**
 * Integration tests for validation system
 *
 * @since 1.0.0
 */
class ValidationIntegrationTest extends TestCase
{
    /**
     * Container instance
     *
     * @var Container
     */
    private Container $container;

    /**
     * Set up test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        $this->container->bind(ValidatorInterface::class, Validator::class);
        
        // Mock foundation() helper
        if (!function_exists('foundation')) {
            function foundation() {
                global $testContainer;
                return $testContainer;
            }
        }
        
        global $testContainer;
        $testContainer = $this->container;
    }

    /**
     * Test MaxLengthRule validation
     *
     * @return void
     */
    public function testMaxLengthRule(): void
    {
        $rule = new MaxLengthRule();

        // Test string validation
        $this->assertTrue($rule->validate('test', [], [], 'field'));
        $this->assertTrue($rule->validate('hello', [10], [], 'field'));
        $this->assertFalse($rule->validate('hello world', [5], [], 'field'));

        // Test array validation
        $this->assertTrue($rule->validate([1, 2, 3], [5], [], 'field'));
        $this->assertFalse($rule->validate([1, 2, 3, 4, 5, 6], [5], [], 'field'));

        // Test numeric validation
        $this->assertTrue($rule->validate(50, [100], [], 'field'));
        $this->assertFalse($rule->validate(150, [100], [], 'field'));

        // Test file size validation
        $this->assertTrue($rule->validate(['size' => 1024], [2048], [], 'field'));
        $this->assertFalse($rule->validate(['size' => 3072], [2048], [], 'field'));

        // Test empty values (should pass)
        $this->assertTrue($rule->validate(null, [10], [], 'field'));
        $this->assertTrue($rule->validate('', [10], [], 'field'));

        // Test error message
        $this->assertEquals(
            'The test must not exceed 10.',
            $rule->message('test', [10])
        );
    }

    /**
     * Test CustomRule with static factories
     *
     * @return void
     */
    public function testCustomRuleFactories(): void
    {
        // Test "in" rule
        $inRule = CustomRule::createInRule();
        $this->assertTrue($inRule->validate('active', ['active', 'pending'], ['status' => 'active'], 'status'));
        $this->assertFalse($inRule->validate('deleted', ['active', 'pending'], ['status' => 'deleted'], 'status'));
        $this->assertEquals('in', $inRule->getName());
        $this->assertStringContainsString('must be one of', $inRule->message('status', ['active', 'pending']));

        // Test "confirmed" rule
        $confirmedRule = CustomRule::createConfirmedRule();
        $data = ['password' => 'secret123', 'password_confirmation' => 'secret123'];
        $this->assertTrue($confirmedRule->validate('secret123', [], $data, 'password'));
        
        $data = ['password' => 'secret123', 'password_confirmation' => 'different'];
        $this->assertFalse($confirmedRule->validate('secret123', [], $data, 'password'));

        // Test "different" rule
        $differentRule = CustomRule::createDifferentRule();
        $data = ['email' => 'test@example.com', 'username' => 'testuser'];
        $this->assertTrue($differentRule->validate('test@example.com', ['username'], $data, 'email'));
        
        $data = ['email' => 'same', 'username' => 'same'];
        $this->assertFalse($differentRule->validate('same', ['username'], $data, 'email'));

        // Test "regex" rule
        $regexRule = CustomRule::createRegexRule();
        $this->assertTrue($regexRule->validate('test123', ['/^[a-z0-9]+$/'], [], 'field'));
        $this->assertFalse($regexRule->validate('Test-123', ['/^[a-z0-9]+$/'], [], 'field'));
    }

    /**
     * Test Request validate method
     *
     * @return void
     */
    public function testRequestValidateMethod(): void
    {
        $request = new Request(
            query: ['page' => '1'],
            post: [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'age' => '25'
            ]
        );

        // Test successful validation
        $validated = $request->validate([
            'name' => 'required|min_length:3',
            'email' => 'required|email',
            'age' => 'required'
        ]);

        $this->assertEquals([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => '25'
        ], $validated);
    }

    /**
     * Test Request validate method with failure
     *
     * @return void
     */
    public function testRequestValidateMethodWithFailure(): void
    {
        $request = new Request(
            query: [],
            post: [
                'name' => 'Jo', // Too short
                'email' => 'invalid-email',
                'password' => 'short'
            ]
        );

        // Mock event dispatcher
        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with('validation.failed', $this->anything());
        
        $this->container->singleton('events', fn() => $events);

        // Mock logger
        $logger = $this->createMock(LoggerInterface::class);
        $loggerChannel = $this->createMock(LoggerInterface::class);
        
        $logger->expects($this->once())
            ->method('channel')
            ->with('validation')
            ->willReturn($loggerChannel);
        
        $loggerChannel->expects($this->once())
            ->method('notice')
            ->with('Validation failed', $this->anything());
        
        $this->container->singleton('logger', fn() => $logger);

        $this->expectException(ValidationException::class);

        $request->validate([
            'name' => 'required|min_length:3',
            'email' => 'required|email',
            'password' => 'required|min_length:8'
        ]);
    }

    /**
     * Test validate helper function
     *
     * @return void
     */
    public function testValidateHelperFunction(): void
    {
        // Load helpers if not already loaded
        $helpersFile = __DIR__ . '/../../../src/Http/helpers.php';
        if (file_exists($helpersFile)) {
            require_once $helpersFile;
        }

        if (!function_exists('validate')) {
            $this->markTestSkipped('validate() helper function not available');
        }

        // Test successful validation
        $data = [
            'username' => 'johndoe',
            'email' => 'john@example.com'
        ];

        $validated = validate($data, [
            'username' => 'required|min_length:5',
            'email' => 'required|email'
        ]);

        $this->assertEquals($data, $validated);

        // Test with custom messages
        $this->expectException(ValidationException::class);
        
        validate(
            ['email' => 'invalid'],
            ['email' => 'email'],
            ['email.email' => 'Please provide a valid email address.']
        );
    }

    /**
     * Test ValidationException rendering
     *
     * @return void
     */
    public function testValidationExceptionRendering(): void
    {
        $validator = new Validator();
        $validator->validate(
            ['email' => 'invalid'],
            ['email' => 'required|email']
        );

        $exception = ValidationException::withValidator($validator);
        
        // Test basic properties
        $this->assertEquals(422, $exception->getCode());
        $this->assertEquals('The given data was invalid.', $exception->getMessage());
        $this->assertFalse($exception->report());
        
        // Test errors
        $errors = $exception->errors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertCount(1, $errors['email']);
        
        // Test JSON rendering
        $request = $this->createMock(\ZipPicks\Foundation\Contracts\Http\RequestInterface::class);
        $request->method('expectsJson')->willReturn(true);
        $request->method('isAjax')->willReturn(false);
        
        $response = $exception->render($request);
        
        $this->assertNotNull($response);
        $this->assertEquals(422, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        
        $content = json_decode($response->getContent(), true);
        $this->assertFalse($content['success']);
        $this->assertEquals('The given data was invalid.', $content['message']);
        $this->assertArrayHasKey('errors', $content);
        $this->assertArrayHasKey('email', $content['errors']);
    }

    /**
     * Test ValidationException redirect rendering
     *
     * @return void
     */
    public function testValidationExceptionRedirectRendering(): void
    {
        $validator = new Validator();
        $validator->validate(
            ['name' => ''],
            ['name' => 'required']
        );

        $exception = ValidationException::withValidator($validator)
            ->redirectTo('https://example.com/form');
        
        // Test redirect rendering
        $request = $this->createMock(\ZipPicks\Foundation\Contracts\Http\RequestInterface::class);
        $request->method('expectsJson')->willReturn(false);
        $request->method('isAjax')->willReturn(false);
        $request->method('fullUrl')->willReturn('https://example.com/submit');
        $request->method('all')->willReturn(['name' => '']);
        
        // Mock WordPress functions
        if (!function_exists('wp_generate_uuid4')) {
            function wp_generate_uuid4() {
                return 'test-uuid-' . time();
            }
        }
        
        if (!function_exists('set_transient')) {
            function set_transient($key, $value, $expiration) {
                return true;
            }
        }
        
        $response = $exception->render($request);
        
        $this->assertNotNull($response);
        $this->assertEquals(302, $response->getStatus());
        $this->assertArrayHasKey('Location', $response->getHeaders());
        $this->assertStringStartsWith('https://example.com/form', $response->getHeaders()['Location']);
        $this->assertStringContainsString('validation_errors=', $response->getHeaders()['Location']);
    }

    /**
     * Test integrating custom rules with validator
     *
     * @return void
     */
    public function testAddingCustomRulesToValidator(): void
    {
        $validator = new Validator();
        
        // Add custom "in" rule
        $validator->addRule('in', CustomRule::createInRule());
        
        // Test validation with custom rule
        $isValid = $validator->validate(
            ['status' => 'active'],
            ['status' => 'required|in:active,pending,completed']
        );
        
        $this->assertTrue($isValid);
        
        // Test failure
        $isValid = $validator->validate(
            ['status' => 'deleted'],
            ['status' => 'required|in:active,pending,completed']
        );
        
        $this->assertFalse($isValid);
        $this->assertArrayHasKey('status', $validator->errors());
    }

    /**
     * Test max_length rule in validator
     *
     * @return void
     */
    public function testMaxLengthRuleInValidator(): void
    {
        $validator = new Validator();
        
        // Test string max length
        $isValid = $validator->validate(
            ['name' => 'John Doe'],
            ['name' => 'required|max_length:20']
        );
        $this->assertTrue($isValid);
        
        // Test exceeding max length
        $isValid = $validator->validate(
            ['name' => 'This is a very long name that exceeds the limit'],
            ['name' => 'required|max_length:20']
        );
        $this->assertFalse($isValid);
        
        // Test array max length
        $isValid = $validator->validate(
            ['tags' => ['php', 'laravel', 'wordpress']],
            ['tags' => 'max_length:5']
        );
        $this->assertTrue($isValid);
        
        $isValid = $validator->validate(
            ['tags' => ['one', 'two', 'three', 'four', 'five', 'six']],
            ['tags' => 'max_length:5']
        );
        $this->assertFalse($isValid);
    }

    /**
     * Test validation with all new features combined
     *
     * @return void
     */
    public function testCompleteValidationFlow(): void
    {
        // Create request with mixed valid/invalid data
        $request = new Request(
            post: [
                'username' => 'john_doe_123',
                'email' => 'john@example.com',
                'password' => 'secretpassword',
                'password_confirmation' => 'secretpassword',
                'age' => 25,
                'bio' => 'A short bio about me',
                'status' => 'active'
            ]
        );

        // Add custom rules to validator
        $validator = $this->container->get(ValidatorInterface::class);
        $validator->addRule('in', CustomRule::createInRule());
        $validator->addRule('confirmed', CustomRule::createConfirmedRule());

        // Perform validation with mixed rules
        $validated = $request->validate([
            'username' => 'required|min_length:3|max_length:20',
            'email' => 'required|email|max_length:100',
            'password' => 'required|min_length:8|confirmed',
            'age' => 'required',
            'bio' => 'max_length:200',
            'status' => 'required|in:active,pending,suspended'
        ]);

        // Assert all data passed validation
        $this->assertArrayHasKey('username', $validated);
        $this->assertArrayHasKey('email', $validated);
        $this->assertArrayHasKey('password', $validated);
        $this->assertEquals('john_doe_123', $validated['username']);
        $this->assertEquals('john@example.com', $validated['email']);
    }
}