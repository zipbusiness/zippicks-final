<?php
/**
 * Queue Database Schema
 * 
 * Creates the database tables required for the enterprise queue system.
 * Optimized for high-throughput job processing at scale.
 * 
 * @package ZipPicks\Foundation\Database\Migrations
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Database\Migrations;

/**
 * Queue Tables Migration
 * 
 * Creates all database tables required for the queue system
 */
class CreateQueueTables
{
    /**
     * Run the migration
     * 
     * @return void
     */
    public function up(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Jobs table - Core queue storage
        $jobs_table = $wpdb->prefix . 'zippicks_jobs';
        $sql_jobs = "CREATE TABLE IF NOT EXISTS `{$jobs_table}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `queue` varchar(191) NOT NULL,
            `payload` longtext NOT NULL,
            `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
            `priority` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
            `reserved_at` int(10) UNSIGNED DEFAULT NULL,
            `available_at` int(10) UNSIGNED NOT NULL,
            `created_at` int(10) UNSIGNED NOT NULL,
            `metadata` longtext DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_queue_reserved_at` (`queue`, `reserved_at`),
            KEY `idx_queue_available_at` (`queue`, `available_at`),
            KEY `idx_priority_available_at` (`priority`, `available_at`),
            KEY `idx_created_at` (`created_at`)
        ) $charset_collate;";
        
        // Failed jobs table - Stores jobs that exceeded retry attempts
        $failed_jobs_table = $wpdb->prefix . 'zippicks_failed_jobs';
        $sql_failed_jobs = "CREATE TABLE IF NOT EXISTS `{$failed_jobs_table}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `uuid` varchar(191) NOT NULL,
            `connection` text NOT NULL,
            `queue` text NOT NULL,
            `payload` longtext NOT NULL,
            `exception` longtext NOT NULL,
            `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `metadata` longtext DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_uuid` (`uuid`),
            KEY `idx_failed_at` (`failed_at`),
            KEY `idx_queue` (`queue`(191))
        ) $charset_collate;";
        
        // Job batches table - For batch job processing
        $job_batches_table = $wpdb->prefix . 'zippicks_job_batches';
        $sql_job_batches = "CREATE TABLE IF NOT EXISTS `{$job_batches_table}` (
            `id` varchar(191) NOT NULL,
            `name` varchar(191) DEFAULT NULL,
            `total_jobs` int(11) NOT NULL DEFAULT 0,
            `pending_jobs` int(11) NOT NULL DEFAULT 0,
            `failed_jobs` int(11) NOT NULL DEFAULT 0,
            `failed_job_ids` longtext DEFAULT NULL,
            `options` mediumtext DEFAULT NULL,
            `cancelled_at` int(10) UNSIGNED DEFAULT NULL,
            `created_at` int(10) UNSIGNED NOT NULL,
            `finished_at` int(10) UNSIGNED DEFAULT NULL,
            `metadata` longtext DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_created_at` (`created_at`),
            KEY `idx_finished_at` (`finished_at`)
        ) $charset_collate;";
        
        // Queue metrics table - For monitoring and analytics
        $queue_metrics_table = $wpdb->prefix . 'zippicks_queue_metrics';
        $sql_queue_metrics = "CREATE TABLE IF NOT EXISTS `{$queue_metrics_table}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `queue` varchar(191) NOT NULL,
            `job_class` varchar(191) NOT NULL,
            `job_id` varchar(191) DEFAULT NULL,
            `worker` varchar(191) DEFAULT NULL,
            `status` enum('dispatched','processing','processed','failed') NOT NULL,
            `runtime` decimal(8,4) DEFAULT NULL,
            `memory_usage` int(11) DEFAULT NULL,
            `exception_class` varchar(191) DEFAULT NULL,
            `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `metadata` longtext DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_queue_status` (`queue`, `status`),
            KEY `idx_job_class_status` (`job_class`, `status`),
            KEY `idx_recorded_at` (`recorded_at`),
            KEY `idx_worker` (`worker`),
            KEY `idx_runtime` (`runtime`)
        ) $charset_collate;";
        
        // Job chains table - For sequential job processing
        $job_chains_table = $wpdb->prefix . 'zippicks_job_chains';
        $sql_job_chains = "CREATE TABLE IF NOT EXISTS `{$job_chains_table}` (
            `id` varchar(191) NOT NULL,
            `jobs` longtext NOT NULL,
            `processed_jobs` int(11) NOT NULL DEFAULT 0,
            `failed_at_job` int(11) DEFAULT NULL,
            `options` mediumtext DEFAULT NULL,
            `created_at` int(10) UNSIGNED NOT NULL,
            `finished_at` int(10) UNSIGNED DEFAULT NULL,
            `metadata` longtext DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_created_at` (`created_at`)
        ) $charset_collate;";
        
        // Queue locks table - For distributed locking
        $queue_locks_table = $wpdb->prefix . 'zippicks_queue_locks';
        $sql_queue_locks = "CREATE TABLE IF NOT EXISTS `{$queue_locks_table}` (
            `key` varchar(191) NOT NULL,
            `owner` varchar(191) NOT NULL,
            `expiration` int(10) UNSIGNED NOT NULL,
            PRIMARY KEY (`key`),
            KEY `idx_expiration` (`expiration`)
        ) $charset_collate;";
        
        // Execute all table creation queries
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_jobs);
        dbDelta($sql_failed_jobs);
        dbDelta($sql_job_batches);
        dbDelta($sql_queue_metrics);
        dbDelta($sql_job_chains);
        dbDelta($sql_queue_locks);
        
        // Add any additional indexes for performance
        $this->addPerformanceIndexes($wpdb);
    }
    
    /**
     * Reverse the migration
     * 
     * @return void
     */
    public function down(): void
    {
        global $wpdb;
        
        $tables = [
            'zippicks_queue_locks',
            'zippicks_job_chains',
            'zippicks_queue_metrics',
            'zippicks_job_batches',
            'zippicks_failed_jobs',
            'zippicks_jobs'
        ];
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");
        }
    }
    
    /**
     * Add performance indexes
     * 
     * @param \wpdb $wpdb WordPress database instance
     * @return void
     */
    protected function addPerformanceIndexes(\wpdb $wpdb): void
    {
        $jobs_table = $wpdb->prefix . 'zippicks_jobs';
        
        // Composite index for efficient job fetching
        $wpdb->query("
            CREATE INDEX IF NOT EXISTS idx_queue_priority_available 
            ON `{$jobs_table}` (`queue`, `priority`, `available_at`)
        ");
        
        // Index for job reservation queries
        $wpdb->query("
            CREATE INDEX IF NOT EXISTS idx_reserved_at_attempts 
            ON `{$jobs_table}` (`reserved_at`, `attempts`)
        ");
        
        $metrics_table = $wpdb->prefix . 'zippicks_queue_metrics';
        
        // Composite index for metrics queries
        $wpdb->query("
            CREATE INDEX IF NOT EXISTS idx_queue_recorded_at 
            ON `{$metrics_table}` (`queue`, `recorded_at`)
        ");
    }
    
    /**
     * Check if migration has been run
     * 
     * @return bool
     */
    public function isApplied(): bool
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zippicks_jobs';
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = %s 
             AND table_name = %s",
            DB_NAME,
            $table_name
        );
        
        return (bool) $wpdb->get_var($query);
    }
}