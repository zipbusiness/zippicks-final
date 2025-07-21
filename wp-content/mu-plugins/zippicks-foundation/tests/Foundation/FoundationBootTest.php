<?php
/**
 * Foundation Boot Test
 * 
 * @package ZipPicks\Foundation\Tests\Foundation
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Foundation;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Auth\AuthManagerInterface;
use ZipPicks\Foundation\Contracts\Auth\GuardInterface;
use ZipPicks\Foundation\Contracts\Cache\CacheInterface;
use ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface;
use ZipPicks\Foundation\Contracts\Exceptions\HandlerInterface;
use ZipPicks\Foundation\Contracts\Http\RequestInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Contracts\Queue\QueueableInterface;
use ZipPicks\Foundation\Contracts\Routing\RouterInterface;
use ZipPicks\Foundation\Contracts\Storage\FilesystemInterface;
use ZipPicks\Foundation\Contracts\Validation\ValidatorInterface;
use ZipPicks\Foundation\Settings\SettingsManager;

class FoundationBootTest extends TestCase
{
    private Foundation $foundation;
    private ContainerInterface $container;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Define constants if not already defined
        if (!defined('ZIPPICKS_FOUNDATION_VERSION')) {
            define('ZIPPICKS_FOUNDATION_VERSION', '1.0.0');
        }
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/var/www/html/');
        }
        
        // Get foundation instance
        $this->foundation = Foundation::getInstance();
        $this->container = $this->foundation->getContainer();
        
        // Boot foundation if not already booted
        if (!$this->foundation->isBooted()) {
            $this->foundation->boot();
        }
    }
    
    public function testFoundationIsSingleton(): void
    {
        $instance1 = Foundation::getInstance();
        $instance2 = Foundation::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }
    
    public function testFoundationIsBooted(): void
    {
        $this->assertTrue($this->foundation->isBooted());
    }
    
    public function testContainerIsAvailable(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, $this->container);
        $this->assertInstanceOf(Container::class, $this->container);
    }
    
    public function testFoundationBindings(): void
    {
        // Test foundation binding
        $this->assertTrue($this->container->has('foundation'));
        $this->assertSame($this->foundation, $this->container->get('foundation'));
        $this->assertSame($this->foundation, $this->container->get(Foundation::class));
        
        // Test container binding
        $this->assertTrue($this->container->has('container'));
        $this->assertSame($this->container, $this->container->get('container'));
        $this->assertSame($this->container, $this->container->get(Container::class));
        $this->assertSame($this->container, $this->container->get(ContainerInterface::class));
    }
    
    public function testConfigurationIsLoaded(): void
    {
        $this->assertTrue($this->container->has('config'));
        $config = $this->container->get('config');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('app', $config);
        $this->assertArrayHasKey('providers', $config);
        $this->assertArrayHasKey('settings', $config);
    }
    
    public function testAllServicesAreRegistered(): void
    {
        // Core services that should be registered
        $services = [
            LoggerInterface::class => 'Logger service',
            SettingsManager::class => 'Settings service',
            CacheInterface::class => 'Cache service',
            EventDispatcherInterface::class => 'Event dispatcher',
            ValidationInterface::class => 'Validation service',
            AuthManagerInterface::class => 'Auth manager',
            HandlerInterface::class => 'Exception handler',
            RouterInterface::class => 'Router service',
            FilesystemInterface::class => 'Storage service',
        ];
        
        foreach ($services as $interface => $description) {
            $this->assertTrue(
                $this->container->has($interface),
                "Failed asserting that {$description} ({$interface}) is registered"
            );
        }
    }
    
    public function testLoggerServiceIsAccessible(): void
    {
        $this->assertTrue($this->container->has(LoggerInterface::class));
        $logger = $this->container->get(LoggerInterface::class);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }
    
    public function testAuthServiceIsAccessible(): void
    {
        $this->assertTrue($this->container->has(AuthManagerInterface::class));
        $auth = $this->container->get(AuthManagerInterface::class);
        $this->assertInstanceOf(AuthManagerInterface::class, $auth);
    }
    
    public function testValidationServiceIsAccessible(): void
    {
        $this->assertTrue($this->container->has(ValidatorInterface::class));
        $validator = $this->container->get(ValidatorInterface::class);
        $this->assertInstanceOf(ValidatorInterface::class, $validator);
    }
    
    public function testCacheServiceIsAccessible(): void
    {
        $this->assertTrue($this->container->has(CacheInterface::class));
        $cache = $this->container->get(CacheInterface::class);
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }
    
    public function testEventServiceIsAccessible(): void
    {
        $this->assertTrue($this->container->has(EventDispatcherInterface::class));
        $events = $this->container->get(EventDispatcherInterface::class);
        $this->assertInstanceOf(EventDispatcherInterface::class, $events);
    }
    
    public function testSettingsServiceIsAccessible(): void
    {
        $this->assertTrue($this->container->has(SettingsManager::class));
        $settings = $this->container->get(SettingsManager::class);
        $this->assertInstanceOf(SettingsManager::class, $settings);
    }
    
    public function testHelperFunctionsWork(): void
    {
        // Test foundation() helper
        $this->assertSame($this->container, foundation());
        $this->assertSame($this->container->get('config'), foundation('config'));
        
        // Test config() helper
        $this->assertIsArray(config());
        $this->assertEquals('ZipPicks Foundation', config('app.name'));
        $this->assertEquals('default-value', config('non.existent.key', 'default-value'));
        
        // Test logger() helper
        $this->assertInstanceOf(LoggerInterface::class, logger());
        
        // Test auth() helper
        $this->assertInstanceOf(AuthManagerInterface::class, auth());
        
        // Test validate() helper exists
        $this->assertTrue(function_exists('validate'));
        
        // Test response() helper exists
        $this->assertTrue(function_exists('response'));
        
        // Test can() and cannot() helpers exist
        $this->assertTrue(function_exists('can'));
        $this->assertTrue(function_exists('cannot'));
        
        // Test report() and render() helpers exist
        $this->assertTrue(function_exists('report'));
        $this->assertTrue(function_exists('render'));
        
        // Test env() helper
        $this->assertTrue(function_exists('env'));
        $_ENV['TEST_VAR'] = 'test-value';
        $this->assertEquals('test-value', env('TEST_VAR'));
        $this->assertEquals('default', env('NON_EXISTENT_VAR', 'default'));
        
        // Test setting() helper
        $this->assertTrue(function_exists('setting'));
        
        // Test cache() helper
        $this->assertTrue(function_exists('cache'));
        
        // Test event() helper
        $this->assertTrue(function_exists('event'));
    }
    
    public function testServiceAliases(): void
    {
        // Common aliases that should work
        $aliases = [
            'logger' => LoggerInterface::class,
            'cache' => CacheInterface::class,
            'events' => EventDispatcherInterface::class,
            'validator' => ValidatorInterface::class,
            'auth' => AuthManagerInterface::class,
            'router' => RouterInterface::class,
            'storage' => FilesystemInterface::class,
            'settings' => SettingsManager::class,
        ];
        
        foreach ($aliases as $alias => $interface) {
            if ($this->container->has($alias)) {
                $this->assertInstanceOf(
                    $interface,
                    $this->container->get($alias),
                    "Alias '{$alias}' should resolve to {$interface}"
                );
            }
        }
    }
    
    public function testConfigurationDefaults(): void
    {
        // Test app configuration
        $this->assertNotEmpty(config('app.name'));
        $this->assertNotEmpty(config('app.version'));
        $this->assertIsBool(config('app.debug'));
        $this->assertNotEmpty(config('app.env'));
        $this->assertNotEmpty(config('app.timezone'));
        $this->assertNotEmpty(config('app.locale'));
        
        // Test default service configurations
        $this->assertIsArray(config('app.defaults'));
        $this->assertArrayHasKey('logging', config('app.defaults'));
        $this->assertArrayHasKey('auth', config('app.defaults'));
        $this->assertArrayHasKey('cache', config('app.defaults'));
        $this->assertArrayHasKey('queue', config('app.defaults'));
        
        // Test providers configuration
        $this->assertIsArray(config('app.providers'));
        $this->assertNotEmpty(config('app.providers'));
    }
    
    public function testServiceProviderLoadOrder(): void
    {
        $providers = config('app.providers');
        
        // Ensure critical providers are loaded in correct order
        $expectedOrder = [
            'ExceptionServiceProvider',
            'LoggingServiceProvider',
            'SettingsServiceProvider',
            'CacheServiceProvider',
            'EventServiceProvider',
        ];
        
        foreach ($expectedOrder as $index => $expectedProvider) {
            $this->assertStringContains(
                $expectedProvider,
                $providers[$index] ?? '',
                "Provider at index {$index} should be {$expectedProvider}"
            );
        }
    }
    
    public function testMagicMethodsWork(): void
    {
        // Test __get magic method
        if ($this->container->has('config')) {
            $this->assertIsArray($this->foundation->config);
        }
        
        // Test __isset magic method
        $this->assertTrue(isset($this->foundation->container));
    }
    
    public function testExceptionHandlingIntegration(): void
    {
        // Test that exception handler is registered
        $this->assertTrue($this->container->has(HandlerInterface::class));
        
        // Test report helper integration
        $exception = new \Exception('Test exception');
        
        // This should not throw an exception
        $this->assertNull(report($exception));
        
        // Test render helper integration
        $response = render($exception);
        if ($response !== null) {
            $this->assertInstanceOf(ResponseInterface::class, $response);
        }
    }
    
    public function testEnvironmentHelpers(): void
    {
        // Test various env() value conversions
        $_ENV['TEST_TRUE'] = 'true';
        $_ENV['TEST_FALSE'] = 'false';
        $_ENV['TEST_NULL'] = 'null';
        $_ENV['TEST_EMPTY'] = 'empty';
        $_ENV['TEST_QUOTED'] = '"quoted value"';
        
        $this->assertTrue(env('TEST_TRUE'));
        $this->assertFalse(env('TEST_FALSE'));
        $this->assertNull(env('TEST_NULL'));
        $this->assertEquals('', env('TEST_EMPTY'));
        $this->assertEquals('quoted value', env('TEST_QUOTED'));
    }
    
    public function testServiceIntegration(): void
    {
        // Test that services can interact with each other
        
        // Logger should be able to use events
        $logger = logger();
        $this->assertInstanceOf(LoggerInterface::class, $logger);
        
        // Auth should be able to use cache
        $auth = auth();
        $this->assertInstanceOf(AuthManagerInterface::class, $auth);
        
        // Validator should be able to use logger and events
        $validator = foundation(ValidatorInterface::class);
        $this->assertInstanceOf(ValidatorInterface::class, $validator);
    }
    
    protected function tearDown(): void
    {
        // Clean up test environment variables
        unset(
            $_ENV['TEST_VAR'],
            $_ENV['TEST_TRUE'],
            $_ENV['TEST_FALSE'],
            $_ENV['TEST_NULL'],
            $_ENV['TEST_EMPTY'],
            $_ENV['TEST_QUOTED']
        );
        
        parent::tearDown();
    }
}