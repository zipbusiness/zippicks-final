<?php
/**
 * Log Driver Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Logging
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Logging;

use ZipPicks\Foundation\Logging\LogEntry;

/**
 * Interface for log drivers
 */
interface LogDriverInterface
{
    /**
     * Write a log entry
     * 
     * @param LogEntry $entry
     * @return void
     */
    public function write(LogEntry $entry): void;

    /**
     * Write multiple log entries
     * 
     * @param LogEntry[] $entries
     * @return void
     */
    public function writeBatch(array $entries): void;

    /**
     * Check if the driver is healthy
     * 
     * @return bool
     */
    public function isHealthy(): bool;

    /**
     * Get driver name
     * 
     * @return string
     */
    public function getName(): string;

    /**
     * Get driver metrics
     * 
     * @return array
     */
    public function getMetrics(): array;

    /**
     * Flush any buffered entries
     * 
     * @return void
     */
    public function flush(): void;
}