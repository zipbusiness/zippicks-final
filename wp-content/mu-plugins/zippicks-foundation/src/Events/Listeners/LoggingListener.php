<?php
/**
 * Example Logging Event Listener
 * 
 * @package ZipPicks\Foundation\Events\Listeners
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Events\Listeners;

use ZipPicks\Foundation\Contracts\Events\ListenerInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;

class LoggingListener implements ListenerInterface
{
    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Log level
     *
     * @var string
     */
    protected string $level = 'info';

    /**
     * Events to log
     *
     * @var array<string>
     */
    protected array $events = [];

    /**
     * Create logging listener
     *
     * @param LoggerInterface $logger
     * @param string $level
     * @param array<string> $events
     */
    public function __construct(LoggerInterface $logger, string $level = 'info', array $events = [])
    {
        $this->logger = $logger;
        $this->level = $level;
        $this->events = $events;
    }

    /**
     * Handle the event
     *
     * @param string $event
     * @param mixed $payload
     * @return mixed
     */
    public function handle(string $event, mixed $payload = null): mixed
    {
        // If specific events are configured, only log those
        if (!empty($this->events) && !in_array($event, $this->events)) {
            return $payload;
        }

        $context = [
            'event' => $event,
            'timestamp' => microtime(true)
        ];

        // Add payload info based on type
        if ($payload !== null) {
            if (is_object($payload)) {
                $context['payload_type'] = get_class($payload);
                
                if (method_exists($payload, 'toArray')) {
                    $context['payload_data'] = $payload->toArray();
                } elseif (method_exists($payload, '__toString')) {
                    $context['payload_data'] = (string) $payload;
                }
            } elseif (is_array($payload)) {
                $context['payload_data'] = $payload;
            } else {
                $context['payload_data'] = $payload;
            }
        }

        // Log at the specified level
        $this->logger->{$this->level}('[Event] ' . $event, $context);

        return $payload;
    }

    /**
     * Determine if the listener should be queued
     *
     * @return bool
     */
    public function shouldQueue(): bool
    {
        return false;
    }

    /**
     * Get the events this listener subscribes to
     *
     * @return array<string, string|array>
     */
    public function subscribes(): array
    {
        $subscribes = [];

        foreach ($this->events as $event) {
            $subscribes[$event] = 'handle';
        }

        return $subscribes;
    }

    /**
     * Set the log level
     *
     * @param string $level
     * @return self
     */
    public function setLevel(string $level): self
    {
        $this->level = $level;
        return $this;
    }

    /**
     * Add events to log
     *
     * @param array<string> $events
     * @return self
     */
    public function addEvents(array $events): self
    {
        $this->events = array_merge($this->events, $events);
        return $this;
    }

    /**
     * Log all events
     *
     * @return self
     */
    public function logAll(): self
    {
        $this->events = [];
        return $this;
    }
}