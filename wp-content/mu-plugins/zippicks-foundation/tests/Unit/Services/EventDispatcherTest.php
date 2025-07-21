<?php
/**
 * Event Dispatcher Service Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use Closure;
use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface;
use ZipPicks\Foundation\Contracts\Events\ListenerInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Events\EventDispatcher;
use ZipPicks\Foundation\Events\Listeners\LoggingListener;
use ZipPicks\Foundation\Events\StoppableEvent;
use ZipPicks\Foundation\Services\EventServiceProvider;

class EventDispatcherTest extends TestCase
{
    protected ContainerInterface $container;
    protected EventDispatcher $dispatcher;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create container
        $this->container = new Container();
        
        // Mock logger
        $logger = $this->createMock(LoggerInterface::class);
        $this->container->singleton(LoggerInterface::class, fn() => $logger);
        
        // Create dispatcher
        $this->dispatcher = new EventDispatcher($this->container, $logger);
        
        // Mock WordPress functions
        if (!function_exists('do_action')) {
            function do_action($hook, ...$args) { return null; }
        }
    }
    
    public function testBasicDispatch(): void
    {
        $called = false;
        $receivedEvent = null;
        $receivedPayload = null;
        
        $this->dispatcher->listen('test.event', function ($event, $payload) use (&$called, &$receivedEvent, &$receivedPayload) {
            $called = true;
            $receivedEvent = $event;
            $receivedPayload = $payload;
        });
        
        $result = $this->dispatcher->dispatch('test.event', ['data' => 'test']);
        
        $this->assertTrue($called);
        $this->assertEquals('test.event', $receivedEvent);
        $this->assertEquals(['data' => 'test'], $receivedPayload);
        $this->assertEquals(['data' => 'test'], $result);
    }
    
    public function testMultipleListeners(): void
    {
        $calls = [];
        
        $this->dispatcher->listen('test.event', function ($event, $payload) use (&$calls) {
            $calls[] = 'listener1';
        });
        
        $this->dispatcher->listen('test.event', function ($event, $payload) use (&$calls) {
            $calls[] = 'listener2';
        });
        
        $this->dispatcher->listen('test.event', function ($event, $payload) use (&$calls) {
            $calls[] = 'listener3';
        });
        
        $this->dispatcher->dispatch('test.event');
        
        $this->assertEquals(['listener1', 'listener2', 'listener3'], $calls);
    }
    
    public function testListenerPriority(): void
    {
        $calls = [];
        
        $this->dispatcher->listen('test.event', function () use (&$calls) {
            $calls[] = 'low';
        }, -10);
        
        $this->dispatcher->listen('test.event', function () use (&$calls) {
            $calls[] = 'high';
        }, 10);
        
        $this->dispatcher->listen('test.event', function () use (&$calls) {
            $calls[] = 'normal';
        }, 0);
        
        $this->dispatcher->dispatch('test.event');
        
        $this->assertEquals(['high', 'normal', 'low'], $calls);
    }
    
    public function testStoppableEvent(): void
    {
        $calls = [];
        
        $this->dispatcher->listen('test.event', function ($event, $payload) use (&$calls) {
            $calls[] = 'listener1';
            if ($payload instanceof StoppableEvent) {
                $payload->stopPropagation();
            }
            return $payload;
        });
        
        $this->dispatcher->listen('test.event', function ($event, $payload) use (&$calls) {
            $calls[] = 'listener2';
            return $payload;
        });
        
        $event = new class extends StoppableEvent {
            public function __construct() {
                parent::__construct('test.event', ['data' => 'test']);
            }
        };
        
        $this->dispatcher->dispatch('test.event', $event);
        
        $this->assertEquals(['listener1'], $calls);
        $this->assertTrue($event->isPropagationStopped());
    }
    
    public function testClosureListener(): void
    {
        $called = false;
        
        $closure = function ($event, $payload) use (&$called) {
            $called = true;
            return 'closure result';
        };
        
        $this->dispatcher->listen('test.event', $closure);
        $result = $this->dispatcher->dispatch('test.event');
        
        $this->assertTrue($called);
        $this->assertEquals('closure result', $result);
    }
    
    public function testClassBasedListener(): void
    {
        $listener = new class implements ListenerInterface {
            public bool $called = false;
            public ?string $event = null;
            public mixed $payload = null;
            
            public function handle(string $event, mixed $payload = null): mixed
            {
                $this->called = true;
                $this->event = $event;
                $this->payload = $payload;
                return 'handled';
            }
            
            public function shouldQueue(): bool
            {
                return false;
            }
            
            public function subscribes(): array
            {
                return [
                    'test.event' => 'handle',
                    'another.event' => ['handle', 10]
                ];
            }
        };
        
        $this->dispatcher->subscribe($listener);
        
        $result = $this->dispatcher->dispatch('test.event', ['data' => 'test']);
        
        $this->assertTrue($listener->called);
        $this->assertEquals('test.event', $listener->event);
        $this->assertEquals(['data' => 'test'], $listener->payload);
        $this->assertEquals('handled', $result);
    }
    
    public function testStringListener(): void
    {
        // Register a test class in container
        $testListener = new class {
            public bool $called = false;
            
            public function __invoke($event, $payload)
            {
                $this->called = true;
                return 'invoked';
            }
        };
        
        $className = get_class($testListener);
        $this->container->singleton($className, fn() => $testListener);
        
        $this->dispatcher->listen('test.event', $className);
        $result = $this->dispatcher->dispatch('test.event');
        
        $this->assertTrue($testListener->called);
        $this->assertEquals('invoked', $result);
    }
    
    public function testArrayCallableListener(): void
    {
        $obj = new class {
            public bool $called = false;
            
            public function handleEvent($event, $payload)
            {
                $this->called = true;
                return 'method called';
            }
        };
        
        $this->dispatcher->listen('test.event', [$obj, 'handleEvent']);
        $result = $this->dispatcher->dispatch('test.event');
        
        $this->assertTrue($obj->called);
        $this->assertEquals('method called', $result);
    }
    
    public function testListenMany(): void
    {
        $calls = [];
        
        $listener = function ($event, $payload) use (&$calls) {
            $calls[] = $event;
        };
        
        $this->dispatcher->listenMany([
            'event.one' => $listener,
            'event.two' => [$listener, $listener],
            'event.three' => $listener
        ]);
        
        $this->dispatcher->dispatch('event.one');
        $this->dispatcher->dispatch('event.two');
        $this->dispatcher->dispatch('event.three');
        
        $this->assertEquals(['event.one', 'event.two', 'event.two', 'event.three'], $calls);
    }
    
    public function testHasListeners(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners('test.event'));
        
        $this->dispatcher->listen('test.event', fn() => null);
        
        $this->assertTrue($this->dispatcher->hasListeners('test.event'));
    }
    
    public function testGetListeners(): void
    {
        $listener1 = fn() => 'one';
        $listener2 = fn() => 'two';
        
        $this->dispatcher->listen('test.event', $listener1);
        $this->dispatcher->listen('test.event', $listener2);
        
        $listeners = $this->dispatcher->getListeners('test.event');
        
        $this->assertCount(2, $listeners);
        $this->assertSame($listener1, $listeners[0]);
        $this->assertSame($listener2, $listeners[1]);
    }
    
    public function testForget(): void
    {
        $this->dispatcher->listen('test.event', fn() => null);
        $this->dispatcher->listen('test.event', fn() => null);
        
        $this->assertTrue($this->dispatcher->hasListeners('test.event'));
        
        $this->dispatcher->forget('test.event');
        
        $this->assertFalse($this->dispatcher->hasListeners('test.event'));
    }
    
    public function testForgetListener(): void
    {
        $listener1 = fn() => 'one';
        $listener2 = fn() => 'two';
        
        $this->dispatcher->listen('test.event', $listener1);
        $this->dispatcher->listen('test.event', $listener2);
        
        $this->assertCount(2, $this->dispatcher->getListeners('test.event'));
        
        $this->dispatcher->forgetListener('test.event', $listener1);
        
        $listeners = $this->dispatcher->getListeners('test.event');
        $this->assertCount(1, $listeners);
        $this->assertSame($listener2, $listeners[0]);
    }
    
    public function testPush(): void
    {
        $calls = [];
        
        $this->dispatcher->listen('test.event', function () use (&$calls) {
            $calls[] = 'normal';
        });
        
        $this->dispatcher->push('test.event', function () use (&$calls) {
            $calls[] = 'pushed';
        });
        
        $this->dispatcher->dispatch('test.event');
        
        // Pushed listener should execute last (lowest priority)
        $this->assertEquals(['normal', 'pushed'], $calls);
    }
    
    public function testFlush(): void
    {
        $this->dispatcher->listen('event.one', fn() => null);
        $this->dispatcher->listen('event.two', fn() => null);
        $this->dispatcher->listen('event.three', fn() => null);
        
        $this->assertTrue($this->dispatcher->hasListeners('event.one'));
        $this->assertTrue($this->dispatcher->hasListeners('event.two'));
        $this->assertTrue($this->dispatcher->hasListeners('event.three'));
        
        $this->dispatcher->flush();
        
        $this->assertFalse($this->dispatcher->hasListeners('event.one'));
        $this->assertFalse($this->dispatcher->hasListeners('event.two'));
        $this->assertFalse($this->dispatcher->hasListeners('event.three'));
    }
    
    public function testGetDispatchedEvents(): void
    {
        $this->dispatcher->dispatch('event.one');
        $this->dispatcher->dispatch('event.two');
        $this->dispatcher->dispatch('event.one'); // Duplicate
        $this->dispatcher->dispatch('event.three');
        
        $dispatched = $this->dispatcher->getDispatchedEvents();
        
        $this->assertCount(3, $dispatched);
        $this->assertContains('event.one', $dispatched);
        $this->assertContains('event.two', $dispatched);
        $this->assertContains('event.three', $dispatched);
    }
    
    public function testWordPressMirroring(): void
    {
        $this->dispatcher->setWordPressMirroring(true);
        
        // This test just ensures the method exists and doesn't throw
        $this->dispatcher->dispatch('test.event', ['data' => 'test']);
        
        // In a real WordPress environment, this would trigger do_action('zippicks_test.event')
        $this->assertTrue(true);
    }
    
    public function testLoggingListener(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('[Event] test.event'),
                $this->arrayHasKey('event')
            );
        
        $listener = new LoggingListener($logger);
        $listener->handle('test.event', ['data' => 'test']);
    }
    
    public function testServiceProviderRegistration(): void
    {
        $provider = new EventServiceProvider($this->container);
        $provider->register();
        
        $this->assertTrue($this->container->has(EventDispatcherInterface::class));
        $this->assertTrue($this->container->has('events'));
        
        $dispatcher = $this->container->get('events');
        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        
        // Test singleton
        $dispatcher2 = $this->container->get('events');
        $this->assertSame($dispatcher, $dispatcher2);
    }
    
    public function testListenerExceptionPropagation(): void
    {
        $this->dispatcher->listen('test.event', function () {
            throw new \RuntimeException('Listener error');
        });
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Listener error');
        
        $this->dispatcher->dispatch('test.event');
    }
    
    public function testReturnValueChaining(): void
    {
        $this->dispatcher->listen('test.event', function ($event, $payload) {
            return $payload . ' - modified by listener 1';
        });
        
        $this->dispatcher->listen('test.event', function ($event, $payload) {
            return $payload . ' - modified by listener 2';
        });
        
        $result = $this->dispatcher->dispatch('test.event', 'initial');
        
        // Last listener's return value is returned
        $this->assertEquals('initial - modified by listener 2', $result);
    }
    
    public function testStoppableEventClass(): void
    {
        $event = new class('test.event', ['foo' => 'bar']) extends StoppableEvent {};
        
        $this->assertEquals('test.event', $event->getName());
        $this->assertEquals(['foo' => 'bar'], $event->getData());
        $this->assertEquals('bar', $event->getData('foo'));
        $this->assertNull($event->getData('nonexistent'));
        $this->assertEquals('default', $event->getData('nonexistent', 'default'));
        
        $event->setData('baz', 'qux');
        $this->assertEquals('qux', $event->getData('baz'));
        
        $event->mergeData(['new' => 'data']);
        $this->assertEquals('data', $event->getData('new'));
        
        $this->assertFalse($event->isPropagationStopped());
        $event->stopPropagation();
        $this->assertTrue($event->isPropagationStopped());
        
        $array = $event->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('propagation_stopped', $array);
    }
}