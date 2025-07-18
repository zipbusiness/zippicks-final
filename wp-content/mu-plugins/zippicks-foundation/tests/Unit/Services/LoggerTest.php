<?php
/**
 * Logger Unit Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Services
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Logging\FileLogger;
use ZipPicks\Foundation\Services\LoggingServiceProvider;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class LoggerTest extends TestCase
{
    private vfsStreamDirectory $filesystem;
    private string $logPath;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up virtual filesystem for testing
        $this->filesystem = vfsStream::setup('logs');
        $this->logPath = vfsStream::url('logs');

        // Create container
        $this->container = new Container();

        // Define constants for tests
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 3));
        }
        if (!defined('ZIPPICKS_FOUNDATION_VERSION')) {
            define('ZIPPICKS_FOUNDATION_VERSION', '1.0.0');
        }
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
    }

    public function testLoggerCanBeResolvedFromContainer(): void
    {
        // Register the logger manually (simulating service provider)
        $this->container->singleton(LoggerInterface::class, function() {
            return new FileLogger($this->logPath);
        });

        $logger = $this->container->get(LoggerInterface::class);

        $this->assertInstanceOf(LoggerInterface::class, $logger);
        $this->assertInstanceOf(FileLogger::class, $logger);
    }

    public function testLogFilesAreCreated(): void
    {
        $logger = new FileLogger($this->logPath);
        
        $logger->info('Test message');

        $expectedFile = date('Y-m-d') . '.log';
        $this->assertTrue($this->filesystem->hasChild($expectedFile));
    }

    public function testMultipleChannelsWork(): void
    {
        $logger = new FileLogger($this->logPath);
        
        $logger->channel('error')->error('Error message');
        $logger->channel('info')->info('Info message');

        $logFile = $this->logPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('[error] ERROR: Error message', $content);
        $this->assertStringContainsString('[info] INFO: Info message', $content);
    }

    public function testLogFormatIsCorrect(): void
    {
        $logger = new FileLogger($this->logPath);
        
        $logger->channel('test')->warning('Test warning message');

        $logFile = $this->logPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        // Check format: [timestamp] [channel] level: message
        $pattern = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[test\] WARNING: Test warning message/';
        $this->assertMatchesRegularExpression($pattern, $content);
    }

    public function testContextInterpolation(): void
    {
        $logger = new FileLogger($this->logPath);
        
        $logger->info('User {username} logged in from {ip}', [
            'username' => 'john_doe',
            'ip' => '192.168.1.1'
        ]);

        $logFile = $this->logPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('User john_doe logged in from 192.168.1.1', $content);
    }

    public function testContextIsLogged(): void
    {
        $logger = new FileLogger($this->logPath);
        
        $logger->error('Database error', [
            'query' => 'SELECT * FROM users',
            'error_code' => 1054
        ]);

        $logFile = $this->logPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('"query":"SELECT * FROM users"', $content);
        $this->assertStringContainsString('"error_code":1054', $content);
    }

    public function testAllLogLevelsWork(): void
    {
        $logger = new FileLogger($this->logPath);
        
        $logger->emergency('Emergency message');
        $logger->alert('Alert message');
        $logger->critical('Critical message');
        $logger->error('Error message');
        $logger->warning('Warning message');
        $logger->notice('Notice message');
        $logger->info('Info message');
        $logger->debug('Debug message');

        $logFile = $this->logPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('EMERGENCY: Emergency message', $content);
        $this->assertStringContainsString('ALERT: Alert message', $content);
        $this->assertStringContainsString('CRITICAL: Critical message', $content);
        $this->assertStringContainsString('ERROR: Error message', $content);
        $this->assertStringContainsString('WARNING: Warning message', $content);
        $this->assertStringContainsString('NOTICE: Notice message', $content);
        $this->assertStringContainsString('INFO: Info message', $content);
        $this->assertStringContainsString('DEBUG: Debug message', $content);
    }

    public function testSetAndGetContext(): void
    {
        $logger = new FileLogger($this->logPath);
        
        $context = ['user_id' => 123, 'session_id' => 'abc123'];
        $logger->setContext($context);
        
        $this->assertEquals($context, $logger->getContext());
    }

    public function testChannelReturnsDifferentInstance(): void
    {
        $logger = new FileLogger($this->logPath);
        $errorLogger = $logger->channel('error');
        
        $this->assertNotSame($logger, $errorLogger);
        $this->assertInstanceOf(FileLogger::class, $errorLogger);
    }

    public function testServiceProviderRegistration(): void
    {
        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($this->container);

        // Create and register the service provider
        $provider = new LoggingServiceProvider($foundation);
        $provider->register();

        // Test that logger is registered
        $this->assertTrue($this->container->has(LoggerInterface::class));
        $this->assertTrue($this->container->has('logger'));

        // Test that we can resolve the logger
        $logger = $this->container->get('logger');
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testServiceProviderDoesNotOverwriteExistingAlias(): void
    {
        // Pre-register a custom logger alias
        $customLogger = new FileLogger($this->logPath);
        $this->container->instance('logger', $customLogger);

        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($this->container);

        // Create and register the service provider
        $provider = new LoggingServiceProvider($foundation);
        $provider->register();

        // Test that the original logger alias was not overwritten
        $resolvedLogger = $this->container->get('logger');
        $this->assertSame($customLogger, $resolvedLogger);
    }

    public function testLogDirectoryIsCreatedIfMissing(): void
    {
        $nonExistentPath = vfsStream::url('logs/subdir');
        
        $logger = new FileLogger($nonExistentPath);
        $logger->info('Test message');

        $this->assertTrue($this->filesystem->hasChild('subdir'));
        $this->assertTrue($this->filesystem->getChild('subdir')->hasChild(date('Y-m-d') . '.log'));
    }

    public function testExceptionWhenLogDirectoryCannotBeCreated(): void
    {
        // Make the virtual filesystem read-only
        $this->filesystem->chmod(0444);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to create log directory');

        new FileLogger(vfsStream::url('logs/cannot-create'));
    }

    public function testServiceProviderBootHandlesDirectoryCreationFailure(): void
    {
        // Create a mock filesystem that simulates directory creation failure
        $failedPath = vfsStream::url('logs-fail');
        vfsStream::newDirectory('logs-fail', 0000)->at(vfsStream::setup('root'));

        // Override ZIPPICKS_FOUNDATION_PATH for this test
        $originalPath = ZIPPICKS_FOUNDATION_PATH;
        $this->setConstantValue('ZIPPICKS_FOUNDATION_PATH', $failedPath);

        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($this->container);

        // Create the service provider
        $provider = new LoggingServiceProvider($foundation);
        $provider->register();

        // Boot should catch the exception and use error_log
        $this->expectOutputRegex('/LoggingServiceProvider boot failed/');
        
        try {
            $provider->boot();
        } catch (\Throwable $e) {
            // In debug mode, exception is re-thrown
            $this->assertStringContainsString('Failed to create log directory', $e->getMessage());
        }

        // Restore original path
        $this->setConstantValue('ZIPPICKS_FOUNDATION_PATH', $originalPath);
    }

    public function testFlushMethodIsCalled(): void
    {
        $logger = new FileLogger($this->logPath);
        
        // This should not throw an exception
        $logger->flush();
        
        // Write a log entry and flush
        $logger->info('Test message');
        $logger->flush();
        
        // Verify the log was written
        $expectedFile = date('Y-m-d') . '.log';
        $this->assertTrue($this->filesystem->hasChild($expectedFile));
    }

    /**
     * Helper method to set constant value for testing
     */
    private function setConstantValue(string $name, mixed $value): void
    {
        if (defined($name)) {
            runkit_constant_redefine($name, $value);
        } else {
            define($name, $value);
        }
    }
}

// Mock wp_mkdir_p function for tests
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        return @mkdir($target, 0755, true);
    }
}

// Mock error_log to capture output in tests
if (!function_exists('error_log')) {
    function error_log($message) {
        echo $message;
    }
}