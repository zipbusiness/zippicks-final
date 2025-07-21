<?php
/**
 * Event Dispatcher Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Events
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Events;

interface EventDispatcherInterface
{
    /**
     * Dispatch an event with optional payload
     *
     * @param string $event
     * @param mixed $payload
     * @return mixed
     */
    public function dispatch(string $event, mixed $payload = null): mixed;

    /**
     * Register a listener for an event
     *
     * @param string $event
     * @param mixed $listener
     * @param int $priority
     * @return void
     */
    public function listen(string $event, mixed $listener, int $priority = 0): void;

    /**
     * Register multiple events with listeners
     *
     * @param array<string, mixed> $events
     * @return void
     */
    public function listenMany(array $events): void;

    /**
     * Check if event has listeners
     *
     * @param string $event
     * @return bool
     */
    public function hasListeners(string $event): bool;

    /**
     * Get all listeners for an event
     *
     * @param string $event
     * @return array<mixed>
     */
    public function getListeners(string $event): array;

    /**
     * Remove all listeners for an event
     *
     * @param string $event
     * @return void
     */
    public function forget(string $event): void;

    /**
     * Remove a specific listener from an event
     *
     * @param string $event
     * @param mixed $listener
     * @return void
     */
    public function forgetListener(string $event, mixed $listener): void;

    /**
     * Subscribe an event subscriber
     *
     * @param object $subscriber
     * @return void
     */
    public function subscribe(object $subscriber): void;

    /**
     * Push a listener onto the stack
     *
     * @param string $event
     * @param mixed $listener
     * @return void
     */
    public function push(string $event, mixed $listener): void;

    /**
     * Flush all listeners
     *
     * @return void
     */
    public function flush(): void;

    /**
     * Get dispatched events
     *
     * @return array<string>
     */
    public function getDispatchedEvents(): array;
}