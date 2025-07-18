<?php
/**
 * Validation Service Unit Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Services
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Contracts\Validation\ValidatorInterface;
use ZipPicks\Foundation\Validation\Validator;
use ZipPicks\Foundation\Validation\RuleInterface;
use ZipPicks\Foundation\Services\ValidationServiceProvider;

class ValidationTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Validator();
    }

    public function testRequiredRule(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => '',
            'age' => null,
        ];

        $rules = [
            'name' => 'required',
            'email' => 'required',
            'age' => 'required',
            'missing' => 'required',
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);
        $this->assertTrue($this->validator->hasErrors());
        
        $errors = $this->validator->errors();
        $this->assertArrayNotHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('age', $errors);
        $this->assertArrayHasKey('missing', $errors);
        
        $this->assertEquals('The email field is required.', $this->validator->first('email'));
    }

    public function testEmailRule(): void
    {
        $data = [
            'valid_email' => 'test@example.com',
            'invalid_email' => 'not-an-email',
            'empty_email' => '',
            'another_valid' => 'user+tag@domain.co.uk',
        ];

        $rules = [
            'valid_email' => 'email',
            'invalid_email' => 'email',
            'empty_email' => 'email',
            'another_valid' => 'email',
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);
        
        $errors = $this->validator->errors();
        $this->assertArrayNotHasKey('valid_email', $errors);
        $this->assertArrayHasKey('invalid_email', $errors);
        $this->assertArrayNotHasKey('empty_email', $errors); // Empty is allowed
        $this->assertArrayNotHasKey('another_valid', $errors);
        
        $this->assertEquals('The invalid_email must be a valid email address.', $this->validator->first('invalid_email'));
    }

    public function testMinLengthRule(): void
    {
        $data = [
            'short' => 'ab',
            'exact' => 'abc',
            'long' => 'abcdef',
            'empty' => '',
            'array' => ['a', 'b', 'c'],
            'short_array' => ['a'],
        ];

        $rules = [
            'short' => 'min_length:3',
            'exact' => 'min_length:3',
            'long' => 'min_length:3',
            'empty' => 'min_length:3',
            'array' => 'min_length:2',
            'short_array' => 'min_length:2',
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);
        
        $errors = $this->validator->errors();
        $this->assertArrayHasKey('short', $errors);
        $this->assertArrayNotHasKey('exact', $errors);
        $this->assertArrayNotHasKey('long', $errors);
        $this->assertArrayNotHasKey('empty', $errors); // Empty is allowed
        $this->assertArrayNotHasKey('array', $errors);
        $this->assertArrayHasKey('short_array', $errors);
        
        $this->assertEquals('The short must be at least 3 characters.', $this->validator->first('short'));
    }

    public function testMultipleRules(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'abc',
        ];

        $rules = [
            'email' => 'required|email',
            'password' => 'required|min_length:8',
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);
        
        $errors = $this->validator->errors();
        $this->assertArrayNotHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertEquals('The password must be at least 8 characters.', $this->validator->first('password'));
    }

    public function testValidatedData(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'extra' => 'not-in-rules',
        ];

        $rules = [
            'name' => 'required',
            'email' => 'required|email',
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
        
        $validated = $this->validator->validated();
        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayHasKey('email', $validated);
        $this->assertArrayNotHasKey('extra', $validated);
        $this->assertEquals('John Doe', $validated['name']);
        $this->assertEquals('john@example.com', $validated['email']);
    }

    public function testCustomMessages(): void
    {
        $this->validator->setMessages([
            'email.required' => 'Please provide your email address.',
            'email.email' => 'The email address format is invalid.',
        ]);

        $data = [
            'email' => 'invalid',
        ];

        $rules = [
            'email' => 'required|email',
        ];

        $this->validator->validate($data, $rules);

        $this->assertEquals('The email address format is invalid.', $this->validator->first('email'));
    }

    public function testCustomAttributes(): void
    {
        $this->validator->setAttributes([
            'email' => 'email address',
            'fname' => 'first name',
        ]);

        $data = [
            'email' => '',
            'fname' => '',
        ];

        $rules = [
            'email' => 'required',
            'fname' => 'required',
        ];

        $this->validator->validate($data, $rules);

        $this->assertEquals('The email address field is required.', $this->validator->first('email'));
        $this->assertEquals('The first name field is required.', $this->validator->first('fname'));
    }

    public function testCustomRule(): void
    {
        // Create a custom uppercase rule
        $uppercaseRule = new class implements RuleInterface {
            public function validate(mixed $value, array $parameters, array $data, string $field): bool
            {
                if (!is_string($value) || $value === '') {
                    return true;
                }
                return $value === strtoupper($value);
            }

            public function message(string $field, array $parameters): string
            {
                return "The {$field} must be uppercase.";
            }

            public function getName(): string
            {
                return 'uppercase';
            }
        };

        $this->validator->addRule('uppercase', $uppercaseRule);

        $data = [
            'code1' => 'ABC',
            'code2' => 'abc',
        ];

        $rules = [
            'code1' => 'uppercase',
            'code2' => 'uppercase',
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);
        $this->assertArrayNotHasKey('code1', $this->validator->errors());
        $this->assertArrayHasKey('code2', $this->validator->errors());
        $this->assertEquals('The code2 must be uppercase.', $this->validator->first('code2'));
    }

    public function testHasRule(): void
    {
        $this->assertTrue($this->validator->hasRule('required'));
        $this->assertTrue($this->validator->hasRule('email'));
        $this->assertTrue($this->validator->hasRule('min_length'));
        $this->assertFalse($this->validator->hasRule('nonexistent'));
    }

    public function testAllErrors(): void
    {
        $data = [
            'name' => '',
            'email' => 'invalid',
        ];

        $rules = [
            'name' => 'required',
            'email' => 'required|email',
        ];

        $this->validator->validate($data, $rules);

        $allErrors = $this->validator->allErrors();
        $this->assertCount(2, $allErrors);
        $this->assertContains('The name field is required.', $allErrors);
        $this->assertContains('The email must be a valid email address.', $allErrors);
    }

    public function testStaticMake(): void
    {
        $validator = Validator::make(
            ['email' => 'invalid'],
            ['email' => 'email']
        );

        $this->assertTrue($validator->fails());
        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->hasError('email'));
    }

    public function testGetErrors(): void
    {
        $data = ['email' => ''];
        $rules = ['email' => 'required|email|min_length:5'];

        $this->validator->validate($data, $rules);

        $emailErrors = $this->validator->getErrors('email');
        $this->assertCount(1, $emailErrors); // Only required should fail
        $this->assertEquals('The email field is required.', $emailErrors[0]);

        $nonExistentErrors = $this->validator->getErrors('nonexistent');
        $this->assertEmpty($nonExistentErrors);
    }

    public function testArrayRulesFormat(): void
    {
        $data = ['email' => 'test@example.com'];
        $rules = ['email' => ['required', 'email']];

        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testInvalidRuleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Validation rule 'nonexistent' does not exist.");

        $this->validator->validate(['field' => 'value'], ['field' => 'nonexistent']);
    }

    public function testServiceProviderRegistration(): void
    {
        // Define constants if not already defined
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }

        $container = new Container();

        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);

        // Create and register the service provider
        $provider = new ValidationServiceProvider($foundation);
        $provider->register();

        // Test that validator is registered
        $this->assertTrue($container->has(ValidatorInterface::class));
        $this->assertTrue($container->has('validator'));

        // Test that we can resolve the validator
        $validator = $container->get('validator');
        $this->assertInstanceOf(ValidatorInterface::class, $validator);
        $this->assertInstanceOf(Validator::class, $validator);

        // Test that we get a new instance each time (not singleton)
        $validator2 = $container->get('validator');
        $this->assertNotSame($validator, $validator2);
    }

    public function testServiceProviderDoesNotOverwriteExistingAlias(): void
    {
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }

        $container = new Container();

        // Pre-register a custom validator alias
        $customValidator = new Validator();
        $container->instance('validator', $customValidator);

        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);

        // Create and register the service provider
        $provider = new ValidationServiceProvider($foundation);
        $provider->register();

        // Test that the original validator alias was not overwritten
        $resolvedValidator = $container->get('validator');
        $this->assertSame($customValidator, $resolvedValidator);
    }

    public function testNumericValueValidation(): void
    {
        $data = [
            'age' => 25,
            'year' => 2024,
        ];

        $rules = [
            'age' => 'min_length:2',
            'year' => 'min_length:4',
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testEmptyArrayValidation(): void
    {
        $data = [
            'tags' => [],
        ];

        $rules = [
            'tags' => 'required',
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result);
        $this->assertTrue($this->validator->hasError('tags'));
        $this->assertEquals('The tags field is required.', $this->validator->first('tags'));
    }
}