<?php
/**
 * Stoppable Event Base Class
 * 
 * @package ZipPicks\Foundation\Events
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Events;

abstract class StoppableEvent
{
    /**
     * Whether propagation was stopped
     *
     * @var bool
     */
    protected bool $propagationStopped = false;

    /**
     * Event name
     *
     * @var string
     */
    protected string $name;

    /**
     * Event data
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Event timestamp
     *
     * @var float
     */
    protected float $timestamp;

    /**
     * Create stoppable event
     *
     * @param string $name
     * @param array<string, mixed> $data
     */
    public function __construct(string $name = '', array $data = [])
    {
        $this->name = $name ?: static::class;
        $this->data = $data;
        $this->timestamp = microtime(true);
    }

    /**
     * Stop event propagation
     *
     * @return void
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Check if propagation was stopped
     *
     * @return bool
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Get event name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get event data
     *
     * @param ?string $key
     * @param mixed $default
     * @return mixed
     */
    public function getData(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->data;
        }

        return $this->data[$key] ?? $default;
    }

    /**
     * Set event data
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setData(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Merge event data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public function mergeData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Get event timestamp
     *
     * @return float
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * Get elapsed time since event creation
     *
     * @return float
     */
    public function getElapsedTime(): float
    {
        return microtime(true) - $this->timestamp;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
            'propagation_stopped' => $this->propagationStopped
        ];
    }
}