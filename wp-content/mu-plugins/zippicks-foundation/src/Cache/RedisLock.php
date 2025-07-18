<?php
/**
 * Redis Lock Implementation
 * 
 * @package ZipPicks\Foundation\Cache
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Cache;

use ZipPicks\Foundation\Contracts\Cache\LockInterface;

/**
 * Distributed lock implementation using Redis for cache stampede prevention
 */
class RedisLock implements LockInterface
{
    private $redis;
    private string $name;
    private int $seconds;
    private ?string $owner;
    private bool $acquired = false;

    public function __construct($redis, string $name, int $seconds = 0, ?string $owner = null)
    {
        $this->redis = $redis;
        $this->name = $name;
        $this->seconds = $seconds;
        $this->owner = $owner ?: $this->generateOwner();
    }

    public function acquire(): bool
    {
        if ($this->acquired) {
            return true;
        }

        $script = <<<LUA
            if redis.call("exists", KEYS[1]) == 0 or redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("set", KEYS[1], ARGV[1], "EX", ARGV[2]) and 1 or 0
            else
                return 0
            end
LUA;

        $result = $this->redis->eval(
            $script,
            1,
            $this->name,
            $this->owner,
            $this->seconds > 0 ? $this->seconds : 86400
        );

        $this->acquired = (bool) $result;
        return $this->acquired;
    }

    public function release(): bool
    {
        if (!$this->acquired) {
            return false;
        }

        $script = <<<LUA
            if redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("del", KEYS[1])
            else
                return 0
            end
LUA;

        $result = $this->redis->eval($script, 1, $this->name, $this->owner);
        
        if ($result) {
            $this->acquired = false;
        }

        return (bool) $result;
    }

    public function owner(): ?string
    {
        return $this->redis->get($this->name);
    }

    public function isHeld(): bool
    {
        return $this->redis->exists($this->name) > 0;
    }

    public function withLock(callable $callback, ?int $timeout = null): mixed
    {
        $start = time();
        $timeout = $timeout ?? 5;

        while (!$this->acquire()) {
            if (time() - $start >= $timeout) {
                throw new \RuntimeException("Failed to acquire lock [{$this->name}] after {$timeout} seconds.");
            }

            usleep(250000); // 250ms
        }

        try {
            return $callback();
        } finally {
            $this->release();
        }
    }

    public function forceRelease(): bool
    {
        return (bool) $this->redis->del($this->name);
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