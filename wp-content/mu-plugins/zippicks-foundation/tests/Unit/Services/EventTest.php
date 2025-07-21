<?php
/**
 * Event Dispatcher Unit Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Services
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface;
use ZipPicks\Foundation\Events\Dispatcher;
use ZipPicks\Foundation\Services\EventServiceProvider;

class EventTest extends TestCase
{
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new Dispatcher();
    }

    public function testCanRegisterAndDispatchEvent(): void
    {
        // Create a test event
        $event = new class {
            public string $message = 'original';
            public int $counter = 0;
        };

        // Register a listener
        $this->dispatcher->addListener(get_class($event), function($e) {
            $e->message = 'modified';
            $e->counter++;
        });

        // Dispatch the event
        $result = $this->dispatcher->dispatch($event);

        // Assert event was modified
        $this->assertSame($event, $result);
        $this->assertEquals('modified', $event->message);
        $this->assertEquals(1, $event->counter);
    }

    public function testEventIsMutatedAcrossMultipleListeners(): void
    {
        $event = new class {
            public array $modifications = [];
        };

        // Register multiple listeners
        $this->dispatcher->addListener(get_class($event), function($e) {
            $e->modifications[] = 'listener1';
        });

        $this->dispatcher->addListener(get_class($event), function($e) {
            $e->modifications[] = 'listener2';
        });

        $this->dispatcher->addListener(get_class($event), function($e) {
            $e->modifications[] = 'listener3';
        });

        // Dispatch the event
        $this->dispatcher->dispatch($event);

        // Assert all listeners were called in order
        $this->assertEquals(['listener1', 'listener2', 'listener3'], $event->modifications);
    }

    public function testGetListenersForEventReturnsCorrectListeners(): void
    {
        $event = new class {};
        $eventClass = get_class($event);

        // Register listeners
        $listener1 = function($e) {};
        $listener2 = function($e) {};

        $this->dispatcher->addListener($eventClass, $listener1);
        $this->dispatcher->addListener($eventClass, $listener2);

        // Get listeners
        $listeners = $this->dispatcher->getListenersForEvent($event);

        // Convert to array for comparison
        $listenersArray = iterator_to_array($listeners);

        $this->assertCount(2, $listenersArray);
        $this->assertContains($listener1, $listenersArray);
        $this->assertContains($listener2, $listenersArray);
    }

    public function testHasListenersWorksAccurately(): void
    {
        $eventClass = 'TestEvent';

        // Initially no listeners
        $this->assertFalse($this->dispatcher->hasListeners($eventClass));

        // Add a listener
        $this->dispatcher->addListener($eventClass, function($e) {});
        $this->assertTrue($this->dispatcher->hasListeners($eventClass));

        // Clear listeners
        $this->dispatcher->clearListenersForEvent($eventClass);
        $this->assertFalse($this->dispatcher->hasListeners($eventClass));
    }

    public function testRemoveListenerWorksAsExpected(): void
    {
        $event = new class {
            public int $count = 0;
        };
        $eventClass = get_class($event);

        // Create listeners
        $listener1 = function($e) { $e->count += 1; };
        $listener2 = function($e) { $e->count += 10; };

        // Add both listeners
        $this->dispatcher->addListener($eventClass, $listener1);
        $this->dispatcher->addListener($eventClass, $listener2);

        // Dispatch once
        $this->dispatcher->dispatch($event);
        $this->assertEquals(11, $event->count);

        // Remove first listener
        $this->dispatcher->removeListener($eventClass, $listener1);

        // Reset count and dispatch again
        $event->count = 0;
        $this->dispatcher->dispatch($event);
        $this->assertEquals(10, $event->count); // Only listener2 should run
    }

    public function testListenersCanBeClosures(): void
    {
        $event = new class {
            public string $value = '';
        };

        $closure = function($e) {
            $e->value = 'closure';
        };

        $this->dispatcher->addListener(get_class($event), $closure);
        $this->dispatcher->dispatch($event);

        $this->assertEquals('closure', $event->value);
    }

    public function testListenersCanBeInvokableObjects(): void
    {
        $event = new class {
            public string $value = '';
        };

        $invokable = new class {
            public function __invoke($event): void
            {
                $event->value = 'invokable';
            }
        };

        $this->dispatcher->addListener(get_class($event), $invokable);
        $this->dispatcher->dispatch($event);

        $this->assertEquals('invokable', $event->value);
    }

    public function testListenersCanBeCallableArrays(): void
    {
        $event = new class {
            public string $value = '';
        };

        $handler = new class {
            public function handle($event): void
            {
                $event->value = 'method';
            }

            public static function staticHandle($event): void
            {
                $event->value = 'static';
            }
        };

        // Instance method
        $this->dispatcher->addListener(get_class($event), [$handler, 'handle']);
        $this->dispatcher->dispatch($event);
        $this->assertEquals('method', $event->value);

        // Static method
        $this->dispatcher->clearListeners();
        $this->dispatcher->addListener(get_class($event), [get_class($handler), 'staticHandle']);
        $this->dispatcher->dispatch($event);
        $this->assertEquals('static', $event->value);
    }

    public function testInheritanceSupport(): void
    {
        // Create event hierarchy
        $parentEvent = new class {
            public array $calls = [];
        };

        $childEvent = new class extends \stdClass {
            public array $calls = [];
        };

        // Register listener for parent class
        $this->dispatcher->addListener(\stdClass::class, function($e) {
            $e->calls[] = 'parent_listener';
        });

        // Register listener for child class
        $this->dispatcher->addListener(get_class($childEvent), function($e) {
            $e->calls[] = 'child_listener';
        });

        // Dispatch child event should trigger both listeners
        $this->dispatcher->dispatch($childEvent);

        $this->assertContains('parent_listener', $childEvent->calls);
        $this->assertContains('child_listener', $childEvent->calls);
    }

    public function testClearListeners(): void
    {
        $event1 = new class { public $called = false; };
        $event2 = new class { public $called = false; };

        $this->dispatcher->addListener(get_class($event1), function($e) { $e->called = true; });
        $this->dispatcher->addListener(get_class($event2), function($e) { $e->called = true; });

        // Clear all listeners
        $this->dispatcher->clearListeners();

        // Dispatch events
        $this->dispatcher->dispatch($event1);
        $this->dispatcher->dispatch($event2);

        // Neither should be called
        $this->assertFalse($event1->called);
        $this->assertFalse($event2->called);
    }

    public function testGetRegisteredEventClasses(): void
    {
        $this->dispatcher->addListener('Event1', function() {});
        $this->dispatcher->addListener('Event2', function() {});
        $this->dispatcher->addListener('Event3', function() {});

        $classes = $this->dispatcher->getRegisteredEventClasses();

        $this->assertCount(3, $classes);
        $this->assertContains('Event1', $classes);
        $this->assertContains('Event2', $classes);
        $this->assertContains('Event3', $classes);
    }

    public function testCountListeners(): void
    {
        $this->assertEquals(0, $this->dispatcher->countListeners());

        $this->dispatcher->addListener('Event1', function() {});
        $this->dispatcher->addListener('Event1', function() {});
        $this->dispatcher->addListener('Event2', function() {});

        $this->assertEquals(3, $this->dispatcher->countListeners());
    }

    public function testServiceProviderRegistration(): void
    {
        // Define constants if not already defined
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }
        if (!defined('ZIPPICKS_FOUNDATION_VERSION')) {
            define('ZIPPICKS_FOUNDATION_VERSION', '1.0.0');
        }

        $container = new Container();

        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);
        $foundation->method('config')->willReturn([]);

        // Create and register the service provider
        $provider = new EventServiceProvider($foundation);
        $provider->register();

        // Test that event dispatcher is registered
        $this->assertTrue($container->has(EventDispatcherInterface::class));
        $this->assertTrue($container->has('events'));

        // Test that we can resolve the event dispatcher
        $events = $container->get('events');
        $this->assertInstanceOf(EventDispatcherInterface::class, $events);
        $this->assertInstanceOf(Dispatcher::class, $events);
    }

    public function testServiceProviderDoesNotOverwriteExistingAlias(): void
    {
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }

        $container = new Container();

        // Pre-register a custom events alias
        $customDispatcher = new Dispatcher();
        $container->instance('events', $customDispatcher);

        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);

        // Create and register the service provider
        $provider = new EventServiceProvider($foundation);
        $provider->register();

        // Test that the original events alias was not overwritten
        $resolvedDispatcher = $container->get('events');
        $this->assertSame($customDispatcher, $resolvedDispatcher);
    }

    public function testRemovingNonExistentListener(): void
    {
        $event = new class {};
        $eventClass = get_class($event);

        $listener = function($e) {};

        // Remove listener that was never added - should not throw
        $this->dispatcher->removeListener($eventClass, $listener);

        // Add and remove same listener twice
        $this->dispatcher->addListener($eventClass, $listener);
        $this->dispatcher->removeListener($eventClass, $listener);
        $this->dispatcher->removeListener($eventClass, $listener); // Should not throw

        $this->assertFalse($this->dispatcher->hasListeners($eventClass));
    }

    public function testEventReturnsSameInstance(): void
    {
        $event = new class {
            public string $id;
            public function __construct() {
                $this->id = uniqid();
            }
        };

        $originalId = $event->id;

        $this->dispatcher->addListener(get_class($event), function($e) {
            // Listener doesn't create new instance
        });

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertEquals($originalId, $result->id);
    }
}