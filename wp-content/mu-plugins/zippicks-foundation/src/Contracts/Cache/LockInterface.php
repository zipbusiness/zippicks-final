<?php
/**
 * Cache Lock Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Cache
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Cache;

/**
 * Contract for distributed locking to prevent cache stampedes
 */
interface LockInterface
{
    /**
     * Attempt to acquire the lock
     */
    public function acquire(): bool;

    /**
     * Release the lock
     */
    public function release(): bool;

    /**
     * Get the current owner of the lock
     */
    public function owner(): ?string;

    /**
     * Determine if the lock is currently held
     */
    public function isHeld(): bool;

    /**
     * Execute callback while holding the lock
     */
    public function withLock(callable $callback, ?int $timeout = null): mixed;

    /**
     * Force release the lock
     */
    public function forceRelease(): bool;
}