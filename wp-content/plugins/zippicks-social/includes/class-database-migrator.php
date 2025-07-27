<?php
/**
 * Database migration handler for ZipPicks Social
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_Database_Migrator
 * 
 * Handles database schema migrations and version management
 */
class ZipPicks_Social_Database_Migrator {
    
    /**
     * Current schema version
     */
    const CURRENT_SCHEMA_VERSION = '1.0.0';
    
    /**
     * Option name for storing database version
     */
    const VERSION_OPTION = 'zippicks_social_db_version';
    
    /**
     * Migration lock transient
     */
    const MIGRATION_LOCK = 'zippicks_social_migration_lock';
    
    /**
     * Lock timeout in seconds
     */
    const LOCK_TIMEOUT = 300; // 5 minutes
    
    /**
     * Migration methods by version
     */
    private static $migrations = [
        '1.0.0' => 'migrate_to_1_0_0',
    ];
    
    /**
     * Run all pending migrations
     *
     * @return array Migration results
     */
    public static function run_migrations(): array {
        // Check if migration is already running
        if (get_transient(self::MIGRATION_LOCK)) {
            return [
                'status' => 'locked',
                'message' => 'Migration is already running',
                'current_version' => get_option(self::VERSION_OPTION, '0.0.0')
            ];
        }
        
        // Set migration lock
        set_transient(self::MIGRATION_LOCK, true, self::LOCK_TIMEOUT);
        
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        $target_version = self::CURRENT_SCHEMA_VERSION;
        
        $results = [
            'status' => 'success',
            'from_version' => $current_version,
            'to_version' => $target_version,
            'migrations_run' => []
        ];
        
        try {
            // If already at current version
            if (version_compare($current_version, $target_version, '>=')) {
                $results['status'] = 'up_to_date';
                $results['message'] = 'Database is already up to date';
                delete_transient(self::MIGRATION_LOCK);
                return $results;
            }
            
            // Run each migration in sequence
            foreach (self::$migrations as $version => $method) {
                if (version_compare($current_version, $version, '<')) {
                    $migration_result = self::$method();
                    
                    if ($migration_result['success']) {
                        update_option(self::VERSION_OPTION, $version);
                        $results['migrations_run'][] = [
                            'version' => $version,
                            'method' => $method,
                            'result' => $migration_result
                        ];
                    } else {
                        $results['status'] = 'error';
                        $results['error'] = $migration_result['error'];
                        $results['failed_version'] = $version;
                        break;
                    }
                }
            }
            
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            error_log('ZipPicks Social Migration Error: ' . $e->getMessage());
        }
        
        // Release migration lock
        delete_transient(self::MIGRATION_LOCK);
        
        return $results;
    }
    
    /**
     * Initial migration to version 1.0.0
     *
     * @return array
     */
    private static function migrate_to_1_0_0(): array {
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-database.php';
        
        try {
            $db_results = ZipPicks_Social_Database::create_tables();
            
            return [
                'success' => true,
                'message' => 'Initial tables created',
                'details' => $db_results
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get migration status
     *
     * @return array
     */
    public static function get_migration_status(): array {
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        $target_version = self::CURRENT_SCHEMA_VERSION;
        
        return [
            'current_version' => $current_version,
            'target_version' => $target_version,
            'needs_migration' => version_compare($current_version, $target_version, '<'),
            'is_locked' => (bool) get_transient(self::MIGRATION_LOCK),
            'pending_migrations' => self::get_pending_migrations($current_version)
        ];
    }
    
    /**
     * Get list of pending migrations
     *
     * @param string $current_version
     * @return array
     */
    private static function get_pending_migrations(string $current_version): array {
        $pending = [];
        
        foreach (self::$migrations as $version => $method) {
            if (version_compare($current_version, $version, '<')) {
                $pending[] = $version;
            }
        }
        
        return $pending;
    }
    
    /**
     * Reset database version (for development only)
     *
     * @return void
     */
    public static function reset_version(): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            delete_option(self::VERSION_OPTION);
            delete_transient(self::MIGRATION_LOCK);
        }
    }
    
    /**
     * Force unlock migrations (emergency use)
     *
     * @return void
     */
    public static function force_unlock(): void {
        delete_transient(self::MIGRATION_LOCK);
    }
}