<?php
/**
 * Database Log Driver
 * 
 * @package ZipPicks\Foundation\Logging\Drivers
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Logging\Drivers;

use ZipPicks\Foundation\Contracts\Logging\LogDriverInterface;
use ZipPicks\Foundation\Logging\LogEntry;
use ZipPicks\Foundation\Logging\LogLevel;

/**
 * Database-based log driver with automatic table creation and cleanup
 */
class DatabaseLogDriver implements LogDriverInterface
{
    private string $tableName;
    private string $minLevel;
    private int $retentionDays;
    private array $metrics = [
        'writes' => 0,
        'failures' => 0,
        'queries' => 0,
    ];
    private array $buffer = [];
    private int $bufferSize;
    private float $lastCleanup = 0;

    public function __construct(
        string $tableName = 'zippicks_logs',
        string $minLevel = LogLevel::INFO,
        int $retentionDays = 30,
        int $bufferSize = 50
    ) {
        global $wpdb;
        
        $this->tableName = $wpdb->prefix . $tableName;
        $this->minLevel = $minLevel;
        $this->retentionDays = $retentionDays;
        $this->bufferSize = $bufferSize;
        
        $this->ensureTable();
    }

    public function write(LogEntry $entry): void
    {
        if (!LogLevel::meetsThreshold($entry->getLevel(), $this->minLevel)) {
            return;
        }

        $this->buffer[] = $entry;

        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    public function writeBatch(array $entries): void
    {
        foreach ($entries as $entry) {
            if (LogLevel::meetsThreshold($entry->getLevel(), $this->minLevel)) {
                $this->buffer[] = $entry;
            }
        }
        
        $this->flush();
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        global $wpdb;
        
        $values = [];
        $placeholders = [];
        
        foreach ($this->buffer as $entry) {
            $data = $entry->toArray();
            
            $values[] = $data['datetime'];
            $values[] = $data['level'];
            $values[] = $data['channel'];
            $values[] = $entry->getFormattedMessage();
            $values[] = wp_json_encode($data['context']);
            $values[] = wp_json_encode($data['metadata']);
            $values[] = $data['metadata']['user_id'] ?? 0;
            $values[] = $data['metadata']['request_id'] ?? '';
            
            $placeholders[] = "(%s, %s, %s, %s, %s, %s, %d, %s)";
        }
        
        $query = "INSERT INTO {$this->tableName} 
                  (created_at, level, channel, message, context, metadata, user_id, request_id) 
                  VALUES " . implode(', ', $placeholders);
        
        $result = $wpdb->query($wpdb->prepare($query, $values));
        
        if ($result === false) {
            $this->metrics['failures'] += count($this->buffer);
            error_log("DatabaseLogDriver: Failed to insert logs - " . $wpdb->last_error);
        } else {
            $this->metrics['writes'] += count($this->buffer);
        }
        
        $this->metrics['queries']++;
        $this->buffer = [];
        
        // Cleanup old logs periodically
        $this->cleanupOldLogs();
    }

    public function isHealthy(): bool
    {
        global $wpdb;
        
        $result = $wpdb->get_var("SELECT 1 FROM {$this->tableName} LIMIT 1");
        return $result !== null || $wpdb->last_error === '';
    }

    public function getName(): string
    {
        return 'database';
    }

    public function getMetrics(): array
    {
        global $wpdb;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->tableName}");
        
        return array_merge($this->metrics, [
            'buffer_size' => count($this->buffer),
            'total_logs' => (int)$count,
            'table_name' => $this->tableName,
            'retention_days' => $this->retentionDays,
        ]);
    }

    private function ensureTable(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            level varchar(20) NOT NULL,
            channel varchar(50) NOT NULL,
            message text NOT NULL,
            context longtext,
            metadata longtext,
            user_id bigint(20) unsigned DEFAULT 0,
            request_id varchar(36),
            PRIMARY KEY (id),
            KEY idx_created_at (created_at),
            KEY idx_level (level),
            KEY idx_channel (channel),
            KEY idx_user_id (user_id),
            KEY idx_request_id (request_id)
        ) $charset_collate";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function cleanupOldLogs(): void
    {
        // Only cleanup once per hour
        if ((time() - $this->lastCleanup) < 3600) {
            return;
        }
        
        global $wpdb;
        
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$this->retentionDays} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tableName} WHERE created_at < %s LIMIT 1000",
            $cutoffDate
        ));
        
        $this->lastCleanup = time();
        $this->metrics['queries']++;
    }

    public function queryLogs(array $filters = []): array
    {
        global $wpdb;
        
        $where = ['1=1'];
        $values = [];
        
        if (!empty($filters['level'])) {
            $where[] = 'level = %s';
            $values[] = $filters['level'];
        }
        
        if (!empty($filters['channel'])) {
            $where[] = 'channel = %s';
            $values[] = $filters['channel'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $values[] = $filters['user_id'];
        }
        
        if (!empty($filters['start_date'])) {
            $where[] = 'created_at >= %s';
            $values[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where[] = 'created_at <= %s';
            $values[] = $filters['end_date'];
        }
        
        $limit = min($filters['limit'] ?? 100, 1000);
        $offset = max($filters['offset'] ?? 0, 0);
        
        $query = "SELECT * FROM {$this->tableName} 
                  WHERE " . implode(' AND ', $where) . " 
                  ORDER BY created_at DESC 
                  LIMIT %d OFFSET %d";
        
        $values[] = $limit;
        $values[] = $offset;
        
        $results = $wpdb->get_results($wpdb->prepare($query, $values), ARRAY_A);
        
        return array_map(function($row) {
            $row['context'] = json_decode($row['context'], true) ?: [];
            $row['metadata'] = json_decode($row['metadata'], true) ?: [];
            return $row;
        }, $results ?: []);
    }

    public function __destruct()
    {
        $this->flush();
    }
}