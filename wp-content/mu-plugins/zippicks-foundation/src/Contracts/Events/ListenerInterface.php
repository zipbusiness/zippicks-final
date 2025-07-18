<?php
/**
 * Event Listener Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Events
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Events;

interface ListenerInterface
{
    /**
     * Handle the event
     *
     * @param string $event
     * @param mixed $payload
     * @return mixed
     */
    public function handle(string $event, mixed $payload = null): mixed;

    /**
     * Determine if the listener should be queued
     *
     * @return bool
     */
    public function shouldQueue(): bool;

    /**
     * Get the events this listener subscribes to
     *
     * @return array<string, string|array>
     */
    public function subscribes(): array;
}