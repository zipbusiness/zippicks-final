<?php
/**
 * Migration Runner
 * 
 * Manages database migrations for the ZipPicks Foundation.
 * Ensures schema changes are applied safely and tracked.
 * 
 * @package ZipPicks\Foundation\Database
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Database;

use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;

/**
 * Migration Runner
 * 
 * Executes and tracks database migrations
 */
class MigrationRunner
{
    /**
     * Logger instance
     */
    protected ?LoggerInterface $logger;
    
    /**
     * Migrations directory path
     */
    protected string $migrationsPath;
    
    /**
     * Migration history table name
     */
    protected string $historyTable;
    
    /**
     * Create a new migration runner
     * 
     * @param string $migrationsPath Path to migrations directory
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(string $migrationsPath, ?LoggerInterface $logger = null)
    {
        global $wpdb;
        
        $this->migrationsPath = $migrationsPath;
        $this->logger = $logger;
        $this->historyTable = $wpdb->prefix . 'zippicks_migrations';
    }
    
    /**
     * Run all pending migrations
     * 
     * @return array<string> List of migrations run
     */
    public function run(): array
    {
        $this->ensureHistoryTableExists();
        
        $pending = $this->getPendingMigrations();
        $executed = [];
        
        foreach ($pending as $migration) {
            try {
                $this->runMigration($migration);
                $executed[] = $migration;
                
                $this->logger?->info('Migration executed successfully', [
                    'migration' => $migration
                ]);
            } catch (\Throwable $e) {
                $this->logger?->error('Migration failed', [
                    'migration' => $migration,
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        }
        
        return $executed;
    }
    
    /**
     * Rollback migrations
     * 
     * @param int $steps Number of migrations to rollback
     * @return array<string> List of migrations rolled back
     */
    public function rollback(int $steps = 1): array
    {
        $migrations = $this->getExecutedMigrations($steps);
        $rolledBack = [];
        
        foreach ($migrations as $migration) {
            try {
                $this->rollbackMigration($migration);
                $rolledBack[] = $migration;
                
                $this->logger?->info('Migration rolled back successfully', [
                    'migration' => $migration
                ]);
            } catch (\Throwable $e) {
                $this->logger?->error('Migration rollback failed', [
                    'migration' => $migration,
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        }
        
        return $rolledBack;
    }
    
    /**
     * Run a specific migration
     * 
     * @param string $migration Migration name
     * @return void
     */
    protected function runMigration(string $migration): void
    {
        $instance = $this->resolveMigration($migration);
        
        if (method_exists($instance, 'up')) {
            $instance->up();
            $this->recordMigration($migration);
        }
    }
    
    /**
     * Rollback a specific migration
     * 
     * @param string $migration Migration name
     * @return void
     */
    protected function rollbackMigration(string $migration): void
    {
        $instance = $this->resolveMigration($migration);
        
        if (method_exists($instance, 'down')) {
            $instance->down();
            $this->removeMigrationRecord($migration);
        }
    }
    
    /**
     * Get pending migrations
     * 
     * @return array<string>
     */
    protected function getPendingMigrations(): array
    {
        $all = $this->getAllMigrations();
        $executed = $this->getExecutedMigrationNames();
        
        return array_diff($all, $executed);
    }
    
    /**
     * Get all migration files
     * 
     * @return array<string>
     */
    protected function getAllMigrations(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }
        
        $files = glob($this->migrationsPath . '/*.php');
        $migrations = [];
        
        foreach ($files as $file) {
            $migrations[] = basename($file, '.php');
        }
        
        sort($migrations);
        
        return $migrations;
    }
    
    /**
     * Get executed migration names
     * 
     * @return array<string>
     */
    protected function getExecutedMigrationNames(): array
    {
        global $wpdb;
        
        $results = $wpdb->get_col(
            "SELECT migration FROM `{$this->historyTable}` ORDER BY batch ASC, migration ASC"
        );
        
        return $results ?: [];
    }
    
    /**
     * Get executed migrations for rollback
     * 
     * @param int $steps Number of migrations
     * @return array<string>
     */
    protected function getExecutedMigrations(int $steps): array
    {
        global $wpdb;
        
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT migration FROM `{$this->historyTable}` 
             ORDER BY batch DESC, migration DESC 
             LIMIT %d",
            $steps
        ));
        
        return $results ?: [];
    }
    
    /**
     * Resolve a migration instance
     * 
     * @param string $migration Migration name
     * @return object
     * @throws \RuntimeException If migration class not found
     */
    protected function resolveMigration(string $migration): object
    {
        $file = $this->migrationsPath . '/' . $migration . '.php';
        
        if (!file_exists($file)) {
            throw new \RuntimeException("Migration file not found: {$file}");
        }
        
        require_once $file;
        
        // Convert filename to class name
        $className = $this->getMigrationClassName($migration);
        
        if (!class_exists($className)) {
            // Try with namespace
            $namespacedClass = '\\ZipPicks\\Foundation\\Database\\Migrations\\' . $className;
            if (class_exists($namespacedClass)) {
                return new $namespacedClass();
            }
            
            throw new \RuntimeException("Migration class not found: {$className}");
        }
        
        return new $className();
    }
    
    /**
     * Get migration class name from filename
     * 
     * @param string $migration Migration filename
     * @return string
     */
    protected function getMigrationClassName(string $migration): string
    {
        // Remove date prefix if present (e.g., 2024_01_01_000000_create_queue_tables)
        $parts = explode('_', $migration);
        
        // If it starts with a date pattern, remove it
        if (count($parts) > 4 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            $parts = array_slice($parts, 4);
        }
        
        // Convert to PascalCase
        return str_replace(' ', '', ucwords(str_replace('_', ' ', implode('_', $parts))));
    }
    
    /**
     * Record a migration as executed
     * 
     * @param string $migration Migration name
     * @return void
     */
    protected function recordMigration(string $migration): void
    {
        global $wpdb;
        
        $batch = $this->getNextBatchNumber();
        
        $wpdb->insert(
            $this->historyTable,
            [
                'migration' => $migration,
                'batch' => $batch
            ],
            ['%s', '%d']
        );
    }
    
    /**
     * Remove a migration record
     * 
     * @param string $migration Migration name
     * @return void
     */
    protected function removeMigrationRecord(string $migration): void
    {
        global $wpdb;
        
        $wpdb->delete(
            $this->historyTable,
            ['migration' => $migration],
            ['%s']
        );
    }
    
    /**
     * Get the next batch number
     * 
     * @return int
     */
    protected function getNextBatchNumber(): int
    {
        global $wpdb;
        
        $max = $wpdb->get_var("SELECT MAX(batch) FROM `{$this->historyTable}`");
        
        return ($max ?: 0) + 1;
    }
    
    /**
     * Ensure migration history table exists
     * 
     * @return void
     */
    protected function ensureHistoryTableExists(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->historyTable}` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `migration` varchar(191) NOT NULL,
            `batch` int(11) NOT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Check if a migration has been run
     * 
     * @param string $migration Migration name
     * @return bool
     */
    public function isMigrationExecuted(string $migration): bool
    {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$this->historyTable}` WHERE migration = %s",
            $migration
        ));
        
        return (bool) $count;
    }
    
    /**
     * Get migration status
     * 
     * @return array{
     *     total: int,
     *     executed: int,
     *     pending: int,
     *     migrations: array<array{name: string, status: string}>
     * }
     */
    public function getStatus(): array
    {
        $all = $this->getAllMigrations();
        $executed = $this->getExecutedMigrationNames();
        $pending = array_diff($all, $executed);
        
        $migrations = [];
        foreach ($all as $migration) {
            $migrations[] = [
                'name' => $migration,
                'status' => in_array($migration, $executed) ? 'executed' : 'pending'
            ];
        }
        
        return [
            'total' => count($all),
            'executed' => count($executed),
            'pending' => count($pending),
            'migrations' => $migrations
        ];
    }
}