<?php
/**
 * Event Dispatcher Implementation
 * 
 * @package ZipPicks\Foundation\Events
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Events;

use Closure;
use ReflectionClass;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface;
use ZipPicks\Foundation\Contracts\Events\ListenerInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;

class EventDispatcher implements EventDispatcherInterface
{
    /**
     * Container instance
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Logger instance
     *
     * @var ?LoggerInterface
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Registered event listeners
     *
     * @var array<string, array<int, array{listener: mixed, priority: int}>>
     */
    protected array $listeners = [];

    /**
     * Sorted listener cache
     *
     * @var array<string, array<mixed>>
     */
    protected array $sorted = [];

    /**
     * Dispatched events log
     *
     * @var array<string>
     */
    protected array $dispatched = [];

    /**
     * WordPress action mirroring
     *
     * @var bool
     */
    protected bool $mirrorToWordPress = false;

    /**
     * Create event dispatcher
     *
     * @param ContainerInterface $container
     * @param ?LoggerInterface $logger
     */
    public function __construct(ContainerInterface $container, ?LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * Dispatch an event with optional payload
     *
     * @param string $event
     * @param mixed $payload
     * @return mixed
     */
    public function dispatch(string $event, mixed $payload = null): mixed
    {
        $this->dispatched[] = $event;
        
        $this->logDebug('Dispatching event', [
            'event' => $event,
            'has_payload' => $payload !== null,
            'listener_count' => count($this->getListeners($event))
        ]);

        // Mirror to WordPress if enabled
        if ($this->mirrorToWordPress && function_exists('do_action')) {
            do_action('zippicks_' . $event, $payload);
        }

        $listeners = $this->getListeners($event);
        $lastResponse = null;

        foreach ($listeners as $listener) {
            $response = $this->callListener($listener, $event, $payload);
            
            // Check if event propagation was stopped
            if ($this->isPropagationStopped($response)) {
                $this->logDebug('Event propagation stopped', ['event' => $event]);
                return $response;
            }
            
            $lastResponse = $response;
        }

        return $lastResponse ?? $payload;
    }

    /**
     * Register a listener for an event
     *
     * @param string $event
     * @param mixed $listener
     * @param int $priority
     * @return void
     */
    public function listen(string $event, mixed $listener, int $priority = 0): void
    {
        $this->listeners[$event][] = [
            'listener' => $listener,
            'priority' => $priority
        ];
        
        // Clear sorted cache
        unset($this->sorted[$event]);

        $this->logDebug('Listener registered', [
            'event' => $event,
            'listener' => $this->getListenerDescription($listener),
            'priority' => $priority
        ]);
    }

    /**
     * Register multiple events with listeners
     *
     * @param array<string, mixed> $events
     * @return void
     */
    public function listenMany(array $events): void
    {
        foreach ($events as $event => $listeners) {
            if (!is_array($listeners)) {
                $listeners = [$listeners];
            }
            
            foreach ($listeners as $listener) {
                $this->listen($event, $listener);
            }
        }
    }

    /**
     * Check if event has listeners
     *
     * @param string $event
     * @return bool
     */
    public function hasListeners(string $event): bool
    {
        return isset($this->listeners[$event]) && count($this->listeners[$event]) > 0;
    }

    /**
     * Get all listeners for an event
     *
     * @param string $event
     * @return array<mixed>
     */
    public function getListeners(string $event): array
    {
        if (!isset($this->sorted[$event])) {
            $this->sortListeners($event);
        }
        
        return $this->sorted[$event] ?? [];
    }

    /**
     * Remove all listeners for an event
     *
     * @param string $event
     * @return void
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event], $this->sorted[$event]);
        
        $this->logDebug('All listeners removed', ['event' => $event]);
    }

    /**
     * Remove a specific listener from an event
     *
     * @param string $event
     * @param mixed $listener
     * @return void
     */
    public function forgetListener(string $event, mixed $listener): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        $listenerId = $this->getListenerId($listener);

        foreach ($this->listeners[$event] as $key => $item) {
            if ($this->getListenerId($item['listener']) === $listenerId) {
                unset($this->listeners[$event][$key]);
                unset($this->sorted[$event]);
                
                $this->logDebug('Listener removed', [
                    'event' => $event,
                    'listener' => $this->getListenerDescription($listener)
                ]);
                
                break;
            }
        }

        // Clean up empty arrays
        if (empty($this->listeners[$event])) {
            unset($this->listeners[$event]);
        }
    }

    /**
     * Subscribe an event subscriber
     *
     * @param object $subscriber
     * @return void
     */
    public function subscribe(object $subscriber): void
    {
        // Check if subscriber implements ListenerInterface
        if ($subscriber instanceof ListenerInterface) {
            $events = $subscriber->subscribes();
            
            foreach ($events as $event => $config) {
                if (is_string($config)) {
                    // Method name provided
                    $this->listen($event, [$subscriber, $config]);
                } elseif (is_array($config)) {
                    // Method and priority provided
                    $method = $config[0] ?? 'handle';
                    $priority = $config[1] ?? 0;
                    $this->listen($event, [$subscriber, $method], $priority);
                }
            }
            
            $this->logDebug('Subscriber registered', [
                'subscriber' => get_class($subscriber),
                'events' => array_keys($events)
            ]);
        }
    }

    /**
     * Push a listener onto the stack
     *
     * @param string $event
     * @param mixed $listener
     * @return void
     */
    public function push(string $event, mixed $listener): void
    {
        $this->listen($event, $listener, -PHP_INT_MAX);
    }

    /**
     * Flush all listeners
     *
     * @return void
     */
    public function flush(): void
    {
        $this->listeners = [];
        $this->sorted = [];
        
        $this->logDebug('All listeners flushed');
    }

    /**
     * Get dispatched events
     *
     * @return array<string>
     */
    public function getDispatchedEvents(): array
    {
        return array_unique($this->dispatched);
    }

    /**
     * Enable WordPress action mirroring
     *
     * @param bool $enable
     * @return void
     */
    public function setWordPressMirroring(bool $enable): void
    {
        $this->mirrorToWordPress = $enable;
    }

    /**
     * Sort listeners by priority
     *
     * @param string $event
     * @return void
     */
    protected function sortListeners(string $event): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        $listeners = $this->listeners[$event];
        
        // Sort by priority (higher priority first)
        usort($listeners, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        // Extract just the listeners
        $this->sorted[$event] = array_map(
            fn($item) => $item['listener'],
            $listeners
        );
    }

    /**
     * Call a listener
     *
     * @param mixed $listener
     * @param string $event
     * @param mixed $payload
     * @return mixed
     */
    protected function callListener(mixed $listener, string $event, mixed $payload): mixed
    {
        try {
            // Closure listener
            if ($listener instanceof Closure) {
                return $listener($event, $payload);
            }

            // String listener (resolve from container)
            if (is_string($listener)) {
                if ($this->container->has($listener)) {
                    $listener = $this->container->get($listener);
                } elseif (class_exists($listener)) {
                    $listener = new $listener();
                } else {
                    // Function name
                    return $listener($event, $payload);
                }
            }

            // Array callable [class, method]
            if (is_array($listener) && count($listener) === 2) {
                [$class, $method] = $listener;
                
                if (is_string($class)) {
                    if ($this->container->has($class)) {
                        $class = $this->container->get($class);
                    } elseif (class_exists($class)) {
                        $class = new $class();
                    }
                }
                
                return $class->$method($event, $payload);
            }

            // Object listener (invokable or ListenerInterface)
            if (is_object($listener)) {
                if ($listener instanceof ListenerInterface) {
                    return $listener->handle($event, $payload);
                }
                
                // Invokable object
                return $listener($event, $payload);
            }

            $this->logError('Invalid listener type', [
                'event' => $event,
                'listener' => $this->getListenerDescription($listener)
            ]);
            
            return null;
        } catch (\Throwable $e) {
            $this->logError('Listener execution failed', [
                'event' => $event,
                'listener' => $this->getListenerDescription($listener),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Check if event propagation was stopped
     *
     * @param mixed $response
     * @return bool
     */
    protected function isPropagationStopped(mixed $response): bool
    {
        if (is_object($response) && method_exists($response, 'isPropagationStopped')) {
            return $response->isPropagationStopped();
        }
        
        return false;
    }

    /**
     * Get listener ID for comparison
     *
     * @param mixed $listener
     * @return string
     */
    protected function getListenerId(mixed $listener): string
    {
        if (is_string($listener)) {
            return $listener;
        }
        
        if (is_array($listener)) {
            $class = is_object($listener[0]) ? get_class($listener[0]) : $listener[0];
            return $class . '@' . $listener[1];
        }
        
        if ($listener instanceof Closure) {
            return spl_object_hash($listener);
        }
        
        if (is_object($listener)) {
            return get_class($listener);
        }
        
        return serialize($listener);
    }

    /**
     * Get listener description for logging
     *
     * @param mixed $listener
     * @return string
     */
    protected function getListenerDescription(mixed $listener): string
    {
        if (is_string($listener)) {
            return $listener;
        }
        
        if (is_array($listener)) {
            $class = is_object($listener[0]) ? get_class($listener[0]) : $listener[0];
            return $class . '@' . $listener[1];
        }
        
        if ($listener instanceof Closure) {
            return 'Closure';
        }
        
        if (is_object($listener)) {
            return get_class($listener);
        }
        
        return 'Unknown';
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->debug('[EventDispatcher] ' . $message, $context);
        }
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error('[EventDispatcher] ' . $message, $context);
        }
    }
}