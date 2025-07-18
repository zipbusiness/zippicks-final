<?php
/**
 * File-based Lock Implementation
 * 
 * @package ZipPicks\Foundation\Cache
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Cache;

use ZipPicks\Foundation\Contracts\Cache\LockInterface;

/**
 * Simple file-based lock for cache stampede prevention
 */
class FileLock implements LockInterface
{
    private string $path;
    private string $name;
    private int $seconds;
    private ?string $owner;
    private ?resource $handle = null;

    public function __construct(string $name, int $seconds = 0, ?string $owner = null)
    {
        $this->name = $name;
        $this->seconds = $seconds;
        $this->owner = $owner ?: $this->generateOwner();
        
        $lockDir = wp_upload_dir()['basedir'] . '/zippicks-cache-locks';
        if (!is_dir($lockDir)) {
            wp_mkdir_p($lockDir);
        }
        
        $this->path = $lockDir . '/' . md5($name) . '.lock';
    }

    public function acquire(): bool
    {
        if ($this->handle !== null) {
            return true;
        }

        $this->handle = @fopen($this->path, 'c');
        
        if (!$this->handle) {
            return false;
        }

        if (!flock($this->handle, LOCK_EX | LOCK_NB)) {
            fclose($this->handle);
            $this->handle = null;
            return false;
        }

        // Write owner and expiration
        fwrite($this->handle, json_encode([
            'owner' => $this->owner,
            'expires' => $this->seconds > 0 ? time() + $this->seconds : 0,
        ]));
        fflush($this->handle);

        return true;
    }

    public function release(): bool
    {
        if ($this->handle === null) {
            return false;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;

        @unlink($this->path);
        return true;
    }

    public function owner(): ?string
    {
        if (!file_exists($this->path)) {
            return null;
        }

        $content = @file_get_contents($this->path);
        if (!$content) {
            return null;
        }

        $data = json_decode($content, true);
        return $data['owner'] ?? null;
    }

    public function isHeld(): bool
    {
        if (!file_exists($this->path)) {
            return false;
        }

        $handle = @fopen($this->path, 'r');
        if (!$handle) {
            return false;
        }

        $locked = !flock($handle, LOCK_EX | LOCK_NB);
        if (!$locked) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        return $locked;
    }

    public function withLock(callable $callback, ?int $timeout = null): mixed
    {
        $start = time();
        $timeout = $timeout ?? 5;

        while (!$this->acquire()) {
            if (time() - $start >= $timeout) {
                throw new \RuntimeException("Failed to acquire lock [{$this->name}] after {$timeout} seconds.");
            }

            usleep(100000); // 100ms
        }

        try {
            return $callback();
        } finally {
            $this->release();
        }
    }

    public function forceRelease(): bool
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }

        return @unlink($this->path);
    }

    private function generateOwner(): string
    {
        return sprintf(
            '%s:%s:%s',
            gethostname(),
            getmypid(),
            bin2hex(random_bytes(8))
        );
    }

    public function __destruct()
    {
        $this->release();
    }
}