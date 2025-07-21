<?php
/**
 * ZipPicks API Key Repository
 * 
 * Handles database operations for API keys
 *
 * @package ZipPicks\Foundation\Api\Keys
 */

namespace ZipPicks\Foundation\Api\Keys;

use ZipPicks\Foundation\Cache\CacheManager;

class ApiKeyRepository
{
    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    protected \wpdb $db;

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected CacheManager $cache;

    /**
     * API keys table name
     *
     * @var string
     */
    protected string $table;

    /**
     * API key usage table name
     *
     * @var string
     */
    protected string $usageTable;

    /**
     * Create new repository instance
     *
     * @param \wpdb $db
     * @param CacheManager $cache
     */
    public function __construct(\wpdb $db, CacheManager $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->table = $db->prefix . 'zippicks_api_keys';
        $this->usageTable = $db->prefix . 'zippicks_api_key_usage';
    }

    /**
     * Create a new API key
     *
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        $this->db->insert($this->table, $data, [
            '%s', // key_hash
            '%s', // key_prefix
            '%d', // user_id
            '%s', // name
            '%s', // tier
            '%s', // permissions
            '%s', // rate_limits
            '%s'  // expires_at
        ]);
        
        return $this->db->insert_id;
    }

    /**
     * Find API key by hash
     *
     * @param string $hash
     * @return object|null
     */
    public function findByHash(string $hash): ?object
    {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE key_hash = %s LIMIT 1",
            $hash
        );
        
        $result = $this->db->get_row($query);
        
        if ($result) {
            // Decode JSON fields
            $result->permissions = json_decode($result->permissions, true) ?: [];
            $result->rate_limits = json_decode($result->rate_limits, true) ?: [];
        }
        
        return $result;
    }

    /**
     * Find API key by ID
     *
     * @param int $id
     * @return object|null
     */
    public function find(int $id): ?object
    {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
            $id
        );
        
        $result = $this->db->get_row($query);
        
        if ($result) {
            // Decode JSON fields
            $result->permissions = json_decode($result->permissions, true) ?: [];
            $result->rate_limits = json_decode($result->rate_limits, true) ?: [];
        }
        
        return $result;
    }

    /**
     * Find API keys by user ID
     *
     * @param int $userId
     * @param array $filters
     * @return array
     */
    public function findByUserId(int $userId, array $filters = []): array
    {
        $where = ["user_id = %d"];
        $params = [$userId];
        
        if (isset($filters['tier'])) {
            $where[] = "tier = %s";
            $params[] = $filters['tier'];
        }
        
        if (isset($filters['active'])) {
            if ($filters['active']) {
                $where[] = "(expires_at IS NULL OR expires_at > NOW())";
            } else {
                $where[] = "expires_at <= NOW()";
            }
        }
        
        $whereClause = implode(' AND ', $where);
        
        $query = $this->db->prepare(
            "SELECT id, key_prefix, name, tier, permissions, rate_limits, 
                    last_used_at, expires_at, created_at
             FROM {$this->table}
             WHERE {$whereClause}
             ORDER BY created_at DESC",
            ...$params
        );
        
        $results = $this->db->get_results($query);
        
        // Decode JSON fields
        foreach ($results as $result) {
            $result->permissions = json_decode($result->permissions, true) ?: [];
            $result->rate_limits = json_decode($result->rate_limits, true) ?: [];
        }
        
        return $results;
    }

    /**
     * Update API key
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $formats = [];
        
        foreach ($data as $field => $value) {
            switch ($field) {
                case 'user_id':
                    $formats[] = '%d';
                    break;
                case 'permissions':
                case 'rate_limits':
                    // Already JSON encoded
                    $formats[] = '%s';
                    break;
                default:
                    $formats[] = '%s';
            }
        }
        
        $result = $this->db->update(
            $this->table,
            $data,
            ['id' => $id],
            $formats,
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Delete API key
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $result = $this->db->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Update last used timestamp
     *
     * @param int $id
     * @return void
     */
    public function updateLastUsed(int $id): void
    {
        $this->db->update(
            $this->table,
            ['last_used_at' => current_time('mysql')],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * Track API key usage
     *
     * @param int $keyId
     * @param string $endpoint
     * @param float $latency
     * @param bool $error
     * @return void
     */
    public function trackUsage(int $keyId, string $endpoint, float $latency, bool $error = false): void
    {
        $date = current_time('Y-m-d');
        
        // Try to update existing record
        $sql = $this->db->prepare(
            "INSERT INTO {$this->usageTable} 
             (api_key_id, endpoint, date, requests, errors, latency_sum)
             VALUES (%d, %s, %s, 1, %d, %f)
             ON DUPLICATE KEY UPDATE
             requests = requests + 1,
             errors = errors + %d,
             latency_sum = latency_sum + %f",
            $keyId,
            $endpoint,
            $date,
            $error ? 1 : 0,
            $latency * 1000, // Convert to milliseconds
            $error ? 1 : 0,
            $latency * 1000
        );
        
        $this->db->query($sql);
    }

    /**
     * Get usage statistics
     *
     * @param int $keyId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getUsageStats(int $keyId, string $startDate, string $endDate): array
    {
        $query = $this->db->prepare(
            "SELECT 
                endpoint,
                SUM(requests) as total_requests,
                SUM(errors) as total_errors,
                AVG(latency_sum / requests) as avg_latency,
                MIN(date) as first_used,
                MAX(date) as last_used
             FROM {$this->usageTable}
             WHERE api_key_id = %d
               AND date BETWEEN %s AND %s
             GROUP BY endpoint
             ORDER BY total_requests DESC",
            $keyId,
            $startDate,
            $endDate
        );
        
        return $this->db->get_results($query, ARRAY_A);
    }

    /**
     * Get daily usage
     *
     * @param int $keyId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getDailyUsage(int $keyId, string $startDate, string $endDate): array
    {
        $query = $this->db->prepare(
            "SELECT 
                date,
                SUM(requests) as requests,
                SUM(errors) as errors,
                AVG(latency_sum / requests) as avg_latency
             FROM {$this->usageTable}
             WHERE api_key_id = %d
               AND date BETWEEN %s AND %s
             GROUP BY date
             ORDER BY date ASC",
            $keyId,
            $startDate,
            $endDate
        );
        
        return $this->db->get_results($query, ARRAY_A);
    }

    /**
     * Clean up expired keys
     *
     * @param int $daysToKeep
     * @return int
     */
    public function cleanupExpired(int $daysToKeep = 30): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        
        $query = $this->db->prepare(
            "DELETE FROM {$this->table} 
             WHERE expires_at IS NOT NULL 
               AND expires_at < %s",
            $cutoffDate
        );
        
        return $this->db->query($query);
    }
}