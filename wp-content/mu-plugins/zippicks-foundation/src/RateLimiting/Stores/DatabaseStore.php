<?php

namespace ZipPicks\Foundation\RateLimiting\Stores;

use ZipPicks\Foundation\RateLimiting\Contracts\RateLimitStoreInterface;
use wpdb;

/**
 * DatabaseStore - WordPress database-backed rate limit storage
 * 
 * Fallback storage for environments without Redis.
 * Optimized for WordPress with proper indexing and cleanup.
 */
class DatabaseStore implements RateLimitStoreInterface
{
    /**
     * @var string Table name
     */
    protected string $table;

    /**
     * @var wpdb WordPress database object
     */
    protected wpdb $db;

    /**
     * @var string Key prefix
     */
    protected string $prefix;

    /**
     * @var int Cleanup probability (1 in X requests)
     */
    protected int $cleanupProbability = 100;

    /**
     * Constructor
     * 
     * @param string $table
     * @param string $prefix
     */
    public function __construct(string $table = 'wp_zippicks_rate_limits', string $prefix = '')
    {
        global $wpdb;
        
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . str_replace($wpdb->prefix, '', $table);
        $this->prefix = $prefix;
        
        $this->maybeCleanup();
    }

    /**
     * {@inheritDoc}
     */
    public function increment(string $key, int $decay, int $amount = 1): int
    {
        $key = $this->prefix . $key;
        $expiresAt = time() + $decay;
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomicity
        $sql = $this->db->prepare(
            "INSERT INTO {$this->table} (`key`, `value`, `expires_at`) 
             VALUES (%s, %d, %d) 
             ON DUPLICATE KEY UPDATE 
             `value` = `value` + %d,
             `expires_at` = GREATEST(`expires_at`, %d)",
            $key,
            $amount,
            $expiresAt,
            $amount,
            $expiresAt
        );
        
        $this->db->query($sql);
        
        // Get the current value
        return $this->get($key);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): int
    {
        $key = $this->prefix . $key;
        
        $value = $this->db->get_var($this->db->prepare(
            "SELECT `value` FROM {$this->table} 
             WHERE `key` = %s AND `expires_at` > %d",
            $key,
            time()
        ));
        
        return $value === null ? 0 : (int) $value;
    }

    /**
     * {@inheritDoc}
     */
    public function reset(string $key): void
    {
        $key = $this->prefix . $key;
        
        $this->db->delete($this->table, ['key' => $key], ['%s']);
        $this->db->delete($this->table, ['key' => $key . ':meta'], ['%s']);
    }

    /**
     * {@inheritDoc}
     */
    public function ttl(string $key): int
    {
        $key = $this->prefix . $key;
        
        $expiresAt = $this->db->get_var($this->db->prepare(
            "SELECT `expires_at` FROM {$this->table} 
             WHERE `key` = %s AND `expires_at` > %d",
            $key,
            time()
        ));
        
        if ($expiresAt === null) {
            return -1;
        }
        
        $ttl = (int) $expiresAt - time();
        return $ttl > 0 ? $ttl : -1;
    }

    /**
     * {@inheritDoc}
     */
    public function getBatch(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }
        
        $prefixedKeys = array_map(fn($key) => $this->prefix . $key, $keys);
        $placeholders = implode(',', array_fill(0, count($prefixedKeys), '%s'));
        
        $sql = $this->db->prepare(
            "SELECT `key`, `value` FROM {$this->table} 
             WHERE `key` IN ({$placeholders}) AND `expires_at` > %d",
            array_merge($prefixedKeys, [time()])
        );
        
        $results = $this->db->get_results($sql, ARRAY_A);
        
        $values = [];
        foreach ($results as $row) {
            $originalKey = str_replace($this->prefix, '', $row['key']);
            $values[$originalKey] = (int) $row['value'];
        }
        
        // Fill missing keys with 0
        foreach ($keys as $key) {
            if (!isset($values[$key])) {
                $values[$key] = 0;
            }
        }
        
        return $values;
    }

    /**
     * {@inheritDoc}
     */
    public function incrementWithLimit(string $key, int $limit, int $window, int $amount = 1): array
    {
        $key = $this->prefix . $key;
        $expiresAt = time() + $window;
        
        // Start transaction for atomicity
        $this->db->query('START TRANSACTION');
        
        try {
            // Lock the row for update
            $current = $this->db->get_var($this->db->prepare(
                "SELECT `value` FROM {$this->table} 
                 WHERE `key` = %s AND `expires_at` > %d 
                 FOR UPDATE",
                $key,
                time()
            ));
            
            $current = $current === null ? 0 : (int) $current;
            
            if ($current + $amount > $limit) {
                $this->db->query('COMMIT');
                return [
                    'allowed' => false,
                    'current' => $current,
                    'ttl' => $this->ttl($key),
                ];
            }
            
            // Update or insert
            if ($current > 0) {
                $this->db->update(
                    $this->table,
                    [
                        'value' => $current + $amount,
                        'expires_at' => $expiresAt,
                    ],
                    ['key' => $key],
                    ['%d', '%d'],
                    ['%s']
                );
            } else {
                $this->db->insert(
                    $this->table,
                    [
                        'key' => $key,
                        'value' => $amount,
                        'expires_at' => $expiresAt,
                    ],
                    ['%s', '%d', '%d']
                );
            }
            
            $this->db->query('COMMIT');
            
            return [
                'allowed' => true,
                'current' => $current + $amount,
                'ttl' => $window,
            ];
        } catch (\Exception $e) {
            $this->db->query('ROLLBACK');
            
            // Fail open on errors
            return [
                'allowed' => true,
                'current' => 0,
                'ttl' => -1,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setMetadata(string $key, array $metadata, int $ttl): void
    {
        $key = $this->prefix . $key . ':meta';
        $expiresAt = time() + $ttl;
        
        $this->db->replace(
            $this->table,
            [
                'key' => $key,
                'value' => 0,
                'expires_at' => $expiresAt,
                'metadata' => serialize($metadata),
            ],
            ['%s', '%d', '%d', '%s']
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata(string $key): ?array
    {
        $key = $this->prefix . $key . ':meta';
        
        $metadata = $this->db->get_var($this->db->prepare(
            "SELECT `metadata` FROM {$this->table} 
             WHERE `key` = %s AND `expires_at` > %d",
            $key,
            time()
        ));
        
        return $metadata === null ? null : unserialize($metadata);
    }

    /**
     * {@inheritDoc}
     */
    public function isAvailable(): bool
    {
        // Check if table exists
        $table = $this->db->get_var(
            "SHOW TABLES LIKE '{$this->table}'"
        );
        
        return $table !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return 'database';
    }

    /**
     * Clear keys matching a pattern
     * 
     * @param string $pattern
     * @return void
     */
    public function clearPattern(string $pattern): void
    {
        $pattern = $this->prefix . str_replace('*', '%', $pattern);
        
        $this->db->query($this->db->prepare(
            "DELETE FROM {$this->table} WHERE `key` LIKE %s",
            $pattern
        ));
    }

    /**
     * Maybe run cleanup of expired entries
     * 
     * @return void
     */
    protected function maybeCleanup(): void
    {
        if (mt_rand(1, $this->cleanupProbability) !== 1) {
            return;
        }
        
        $this->cleanup();
    }

    /**
     * Clean up expired entries
     * 
     * @return int Number of rows deleted
     */
    public function cleanup(): int
    {
        return $this->db->query($this->db->prepare(
            "DELETE FROM {$this->table} WHERE `expires_at` <= %d LIMIT 1000",
            time()
        ));
    }

    /**
     * Create the rate limits table
     * 
     * @return void
     */
    public function createTable(): void
    {
        $charsetCollate = $this->db->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            `key` VARCHAR(255) NOT NULL,
            `value` INT UNSIGNED NOT NULL DEFAULT 0,
            `expires_at` INT UNSIGNED NOT NULL,
            `metadata` TEXT NULL,
            PRIMARY KEY (`key`),
            INDEX idx_expires (`expires_at`)
        ) $charsetCollate";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Get table statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        return [
            'total_keys' => $this->db->get_var("SELECT COUNT(*) FROM {$this->table}"),
            'active_keys' => $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE `expires_at` > %d",
                time()
            )),
            'expired_keys' => $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE `expires_at` <= %d",
                time()
            )),
        ];
    }
}