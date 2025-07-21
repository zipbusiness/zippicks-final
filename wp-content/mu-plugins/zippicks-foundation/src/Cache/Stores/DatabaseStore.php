<?php
/**
 * Database Cache Store
 * 
 * @package ZipPicks\Foundation\Cache\Stores
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Cache\Stores;

use ZipPicks\Foundation\Contracts\Cache\CacheStoreInterface;
use ZipPicks\Foundation\Core\CircuitBreaker;

/**
 * Database-backed cache store for persistent caching
 */
class DatabaseStore implements CacheStoreInterface
{
    private string $table = 'zippicks_cache';
    private string $prefix;
    private CircuitBreaker $circuitBreaker;
    private array $metrics = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
        'errors' => 0,
    ];

    public function __construct(string $prefix = '', ?CircuitBreaker $circuitBreaker = null)
    {
        $this->prefix = $prefix;
        $this->circuitBreaker = $circuitBreaker ?: new CircuitBreaker(
            'database_cache',
            failureThreshold: 3,
            recoveryTime: 30,
            successThreshold: 2
        );
        
        $this->ensureTableExists();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return $default;
        }

        try {
            global $wpdb;
            
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT value, expiration FROM {$this->table} WHERE cache_key = %s",
                $this->prefix . $key
            ));

            if (!$result) {
                $this->metrics['misses']++;
                $this->circuitBreaker->recordSuccess();
                return $default;
            }

            if ($result->expiration !== null && $result->expiration < time()) {
                $this->forget($key);
                $this->metrics['misses']++;
                return $default;
            }

            $this->metrics['hits']++;
            $this->circuitBreaker->recordSuccess();
            
            return unserialize($result->value);
        } catch (\Throwable $e) {
            $this->handleError($e);
            return $default;
        }
    }

    public function many(array $keys): array
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return array_fill_keys($keys, null);
        }

        try {
            global $wpdb;
            
            $placeholders = implode(',', array_fill(0, count($keys), '%s'));
            $prefixedKeys = array_map(fn($key) => $this->prefix . $key, $keys);
            
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT cache_key, value, expiration FROM {$this->table} WHERE cache_key IN ($placeholders)",
                ...$prefixedKeys
            ), OBJECT_K);

            $values = [];
            foreach ($keys as $key) {
                $prefixedKey = $this->prefix . $key;
                if (!isset($results[$prefixedKey])) {
                    $values[$key] = null;
                    continue;
                }

                $result = $results[$prefixedKey];
                if ($result->expiration !== null && $result->expiration < time()) {
                    $values[$key] = null;
                } else {
                    $values[$key] = unserialize($result->value);
                }
            }

            $this->circuitBreaker->recordSuccess();
            return $values;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return array_fill_keys($keys, null);
        }
    }

    public function put(string $key, mixed $value, ?int $seconds = null): bool
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            global $wpdb;
            
            $expiration = $seconds !== null && $seconds > 0 ? time() + $seconds : null;
            
            $result = $wpdb->replace(
                $this->table,
                [
                    'cache_key' => $this->prefix . $key,
                    'value' => serialize($value),
                    'expiration' => $expiration,
                    'created_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%d', '%s']
            );

            if ($result !== false) {
                $this->metrics['writes']++;
                $this->circuitBreaker->recordSuccess();
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    public function putMany(array $values, ?int $seconds = null): bool
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            foreach ($values as $key => $value) {
                if (!$this->put($key, $value, $seconds)) {
                    $wpdb->query('ROLLBACK');
                    return false;
                }
            }

            $wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            $this->handleError($e);
            return false;
        }
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $current = $this->get($key, 0);
        
        if (!is_numeric($current)) {
            return false;
        }

        $newValue = $current + $value;
        $this->put($key, $newValue);
        
        return $newValue;
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value);
    }

    public function forget(string $key): bool
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            global $wpdb;
            
            $result = $wpdb->delete(
                $this->table,
                ['cache_key' => $this->prefix . $key],
                ['%s']
            );

            if ($result !== false) {
                $this->metrics['deletes']++;
                $this->circuitBreaker->recordSuccess();
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    public function flush(): bool
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            global $wpdb;
            
            if ($this->prefix) {
                $result = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$this->table} WHERE cache_key LIKE %s",
                    $wpdb->esc_like($this->prefix) . '%'
                ));
            } else {
                $result = $wpdb->query("TRUNCATE TABLE {$this->table}");
            }

            $this->circuitBreaker->recordSuccess();
            return $result !== false;
        } catch (\Throwable $e) {
            $this->handleError($e);
            return false;
        }
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getMetrics(): array
    {
        global $wpdb;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
        
        return array_merge($this->metrics, [
            'total_entries' => $count,
            'circuit_breaker' => $this->circuitBreaker->getMetrics(),
        ]);
    }

    public function isHealthy(): bool
    {
        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            global $wpdb;
            $wpdb->get_var("SELECT 1");
            $this->circuitBreaker->recordSuccess();
            return true;
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure();
            return false;
        }
    }

    public function getName(): string
    {
        return 'database';
    }

    private function ensureTableExists(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            cache_key varchar(255) NOT NULL,
            value longtext NOT NULL,
            expiration int(11) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (cache_key),
            KEY expiration (expiration)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Clean expired entries periodically
        if (mt_rand(1, 100) === 1) {
            $this->cleanExpired();
        }
    }

    private function cleanExpired(): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table} WHERE expiration IS NOT NULL AND expiration < %d",
            time()
        ));
    }

    private function handleError(\Throwable $e): void
    {
        $this->metrics['errors']++;
        $this->circuitBreaker->recordFailure();
        
        if (function_exists('zippicks_foundation')) {
            $foundation = zippicks_foundation();
            if ($foundation && $foundation->getContainer()->has('logger')) {
                $foundation->getContainer()->get('logger')->error('Database cache error', [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}