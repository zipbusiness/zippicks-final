<?php
/**
 * Queue Service Unit Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Services
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Queue\SyncQueueDispatcher;
use ZipPicks\Foundation\Contracts\Queue\QueueableInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Services\QueueServiceProvider;
use Exception;

class QueueTest extends TestCase
{
    private SyncQueueDispatcher $dispatcher;
    private MockLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new MockLogger();
        $this->dispatcher = new SyncQueueDispatcher($this->logger);
    }

    public function testJobDispatchingExecutesImmediately(): void
    {
        $job = new TestJob();
        $this->assertFalse($job->wasExecuted());

        $this->dispatcher->dispatch($job);

        $this->assertTrue($job->wasExecuted());
    }

    public function testJobMetadataIsStored(): void
    {
        $job = new TestJob();
        $this->dispatcher->dispatch($job);

        $metadata = $this->dispatcher->getAllJobMetadata();
        $this->assertCount(1, $metadata);

        $jobMetadata = array_values($metadata)[0];
        $this->assertEquals(TestJob::class, $jobMetadata['class']);
        $this->assertEquals(['test', 'unit'], $jobMetadata['tags']);
        $this->assertNull($jobMetadata['delay']);
        $this->assertEquals('completed', $jobMetadata['status']);
        $this->assertArrayHasKey('duration_ms', $jobMetadata);
    }

    public function testJobWithDelayStoresDelayMetadata(): void
    {
        $job = new DelayedTestJob();
        $this->dispatcher->dispatch($job);

        $metadata = $this->dispatcher->getAllJobMetadata();
        $jobMetadata = array_values($metadata)[0];
        
        $this->assertEquals(60, $jobMetadata['delay']);
        $this->assertEquals('completed', $jobMetadata['status']);
    }

    public function testJobDispatchingIsLogged(): void
    {
        $job = new TestJob();
        $this->dispatcher->dispatch($job);

        $logs = $this->logger->getLogs();
        
        // Should have dispatch and completion logs
        $this->assertCount(2, $logs);
        
        // Check dispatch log
        $dispatchLog = $logs[0];
        $this->assertEquals('info', $dispatchLog['level']);
        $this->assertEquals('queue', $dispatchLog['channel']);
        $this->assertStringContainsString('Job dispatched', $dispatchLog['message']);
        $this->assertEquals(['test', 'unit'], $dispatchLog['context']['tags']);

        // Check completion log
        $completionLog = $logs[1];
        $this->assertEquals('info', $completionLog['level']);
        $this->assertEquals('queue', $completionLog['channel']);
        $this->assertStringContainsString('Job completed', $completionLog['message']);
        $this->assertArrayHasKey('duration_ms', $completionLog['context']);
    }

    public function testFailingJobIsLogged(): void
    {
        $job = new FailingTestJob();
        
        try {
            $this->dispatcher->dispatch($job);
        } catch (Exception $e) {
            // Expected when no logger
        }

        $logs = $this->logger->getLogs();
        
        // Should have dispatch and failure logs
        $this->assertCount(2, $logs);
        
        // Check failure log
        $failureLog = $logs[1];
        $this->assertEquals('error', $failureLog['level']);
        $this->assertEquals('queue', $failureLog['channel']);
        $this->assertStringContainsString('Job failed', $failureLog['message']);
        $this->assertEquals('Test job failure', $failureLog['context']['error']);
    }

    public function testFailingJobThrowsExceptionWhenNoLogger(): void
    {
        $dispatcher = new SyncQueueDispatcher(null);
        $job = new FailingTestJob();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Test job failure');

        $dispatcher->dispatch($job);
    }

    public function testExecutionHistory(): void
    {
        $job1 = new TestJob();
        $job2 = new DelayedTestJob();
        
        $this->dispatcher->dispatch($job1);
        $this->dispatcher->dispatch($job2);

        $history = $this->dispatcher->getExecutionHistory();
        $this->assertCount(2, $history);

        // Check first job
        $this->assertEquals(TestJob::class, $history[0]['class']);
        $this->assertEquals(['test', 'unit'], $history[0]['tags']);
        $this->assertArrayHasKey('duration_ms', $history[0]);

        // Test limited history
        $limitedHistory = $this->dispatcher->getExecutionHistory(1);
        $this->assertCount(1, $limitedHistory);
        $this->assertEquals(DelayedTestJob::class, $limitedHistory[0]['class']);
    }

    public function testGetJobMetadata(): void
    {
        $job = new TestJob();
        $this->dispatcher->dispatch($job);

        $allMetadata = $this->dispatcher->getAllJobMetadata();
        $jobId = array_keys($allMetadata)[0];

        $metadata = $this->dispatcher->getJobMetadata($jobId);
        $this->assertNotNull($metadata);
        $this->assertEquals(TestJob::class, $metadata['class']);

        // Non-existent job
        $this->assertNull($this->dispatcher->getJobMetadata('non-existent'));
    }

    public function testClearMetadataAndHistory(): void
    {
        $job = new TestJob();
        $this->dispatcher->dispatch($job);

        $this->assertNotEmpty($this->dispatcher->getAllJobMetadata());
        $this->assertNotEmpty($this->dispatcher->getExecutionHistory());

        $this->dispatcher->clear();

        $this->assertEmpty($this->dispatcher->getAllJobMetadata());
        $this->assertEmpty($this->dispatcher->getExecutionHistory());
    }

    public function testStatistics(): void
    {
        // Dispatch some jobs
        $this->dispatcher->dispatch(new TestJob());
        $this->dispatcher->dispatch(new TestJob());
        $this->dispatcher->dispatch(new DelayedTestJob());
        
        try {
            $this->dispatcher->dispatch(new FailingTestJob());
        } catch (Exception $e) {
            // Expected
        }

        $stats = $this->dispatcher->getStatistics();

        $this->assertEquals(4, $stats['total_jobs']);
        $this->assertEquals(3, $stats['completed']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(0, $stats['pending']);
        $this->assertEquals(0, $stats['processing']);
        $this->assertEquals(75.0, $stats['success_rate']);
        $this->assertGreaterThan(0, $stats['average_duration_ms']);
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
        $provider = new QueueServiceProvider($foundation);
        $provider->register();

        // Test that queue dispatcher is registered
        $this->assertTrue($container->has(SyncQueueDispatcher::class));
        $this->assertTrue($container->has('queue'));

        // Test that we can resolve the queue dispatcher
        $queue = $container->get('queue');
        $this->assertInstanceOf(SyncQueueDispatcher::class, $queue);
    }

    public function testServiceProviderDoesNotOverwriteExistingAlias(): void
    {
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }

        $container = new Container();

        // Pre-register a custom queue alias
        $customDispatcher = new SyncQueueDispatcher();
        $container->instance('queue', $customDispatcher);

        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);

        // Create and register the service provider
        $provider = new QueueServiceProvider($foundation);
        $provider->register();

        // Test that the original queue alias was not overwritten
        $resolvedDispatcher = $container->get('queue');
        $this->assertSame($customDispatcher, $resolvedDispatcher);
    }

    public function testJobIdGeneration(): void
    {
        $job1 = new TestJob();
        $job2 = new TestJob();

        $this->dispatcher->dispatch($job1);
        $this->dispatcher->dispatch($job2);

        $metadata = $this->dispatcher->getAllJobMetadata();
        $jobIds = array_keys($metadata);

        // Job IDs should be unique
        $this->assertCount(2, $jobIds);
        $this->assertNotEquals($jobIds[0], $jobIds[1]);

        // Job IDs should contain class name
        foreach ($jobIds as $jobId) {
            $this->assertStringContainsString('TestJob', $jobId);
        }
    }

    public function testSetLogger(): void
    {
        $dispatcher = new SyncQueueDispatcher();
        $newLogger = new MockLogger();

        $dispatcher->setLogger($newLogger);

        $job = new TestJob();
        $dispatcher->dispatch($job);

        $this->assertCount(2, $newLogger->getLogs());
    }
}

/**
 * Mock logger for testing
 */
class MockLogger implements LoggerInterface
{
    private array $logs = [];
    private array $context = [];

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
            'channel' => $this->currentChannel ?? 'default',
        ];
    }

    public function channel(string $channel): self
    {
        $clone = clone $this;
        $clone->currentChannel = $channel;
        return $clone;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    private string $currentChannel = 'default';
}

/**
 * Test job implementation
 */
class TestJob implements QueueableInterface
{
    private bool $executed = false;

    public function handle(): void
    {
        $this->executed = true;
    }

    public function tags(): array
    {
        return ['test', 'unit'];
    }

    public function delay(): ?int
    {
        return null;
    }

    public function wasExecuted(): bool
    {
        return $this->executed;
    }
}

/**
 * Test job with delay
 */
class DelayedTestJob implements QueueableInterface
{
    private bool $executed = false;

    public function handle(): void
    {
        $this->executed = true;
    }

    public function tags(): array
    {
        return ['test', 'delayed'];
    }

    public function delay(): ?int
    {
        return 60; // 60 seconds delay
    }

    public function wasExecuted(): bool
    {
        return $this->executed;
    }
}

/**
 * Test job that fails
 */
class FailingTestJob implements QueueableInterface
{
    public function handle(): void
    {
        throw new Exception('Test job failure');
    }

    public function tags(): array
    {
        return ['test', 'failing'];
    }

    public function delay(): ?int
    {
        return null;
    }
}