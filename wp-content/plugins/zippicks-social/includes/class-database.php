<?php
/**
 * Database handler for ZipPicks Social
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_Database
 * 
 * Handles all database operations including table creation and management
 */
class ZipPicks_Social_Database {
    
    /**
     * Table names
     */
    const TABLE_FOLLOWS = 'zippicks_follows';
    const TABLE_FOLLOW_STATS = 'zippicks_follow_stats';
    const TABLE_ACTIVITIES = 'zippicks_activities';
    const TABLE_SUGGESTIONS = 'zippicks_follow_suggestions';
    
    /**
     * Get follows table name
     *
     * @return string
     */
    public static function get_follows_table(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_FOLLOWS;
    }
    
    /**
     * Get follow stats table name
     *
     * @return string
     */
    public static function get_follow_stats_table(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_FOLLOW_STATS;
    }
    
    /**
     * Get activities table name
     *
     * @return string
     */
    public static function get_activities_table(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_ACTIVITIES;
    }
    
    /**
     * Get suggestions table name
     *
     * @return string
     */
    public static function get_suggestions_table(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUGGESTIONS;
    }
    
    /**
     * Create all tables
     *
     * @return array Results of table creation
     */
    public static function create_tables(): array {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $results = [];
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create follows table
        $sql = self::get_follows_table_sql();
        $results['follows'] = dbDelta($sql);
        
        // Create follow stats table
        $sql = self::get_follow_stats_table_sql();
        $results['follow_stats'] = dbDelta($sql);
        
        // Create activities table
        $sql = self::get_activities_table_sql();
        $results['activities'] = dbDelta($sql);
        
        // Create suggestions table
        $sql = self::get_suggestions_table_sql();
        $results['suggestions'] = dbDelta($sql);
        
        return $results;
    }
    
    /**
     * Get SQL for follows table
     *
     * @return string
     */
    public static function get_follows_table_sql(): string {
        global $wpdb;
        $table_name = self::get_follows_table();
        $charset_collate = $wpdb->get_charset_collate();
        
        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            follower_id BIGINT(20) UNSIGNED NOT NULL,
            followed_id BIGINT(20) UNSIGNED NOT NULL,
            followed_type ENUM('user', 'critic', 'business', 'list') DEFAULT 'user',
            status ENUM('active', 'pending', 'blocked', 'muted') DEFAULT 'active',
            notification_pref ENUM('all', 'important', 'none') DEFAULT 'all',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_follow (follower_id, followed_id, followed_type),
            KEY idx_follower (follower_id, status),
            KEY idx_followed (followed_id, followed_type, status),
            KEY idx_created (created_at)
        ) $charset_collate;";
    }
    
    /**
     * Get SQL for follow stats table
     *
     * @return string
     */
    public static function get_follow_stats_table_sql(): string {
        global $wpdb;
        $table_name = self::get_follow_stats_table();
        $charset_collate = $wpdb->get_charset_collate();
        
        return "CREATE TABLE {$table_name} (
            entity_id BIGINT(20) UNSIGNED NOT NULL,
            entity_type ENUM('user', 'critic', 'business', 'list') DEFAULT 'user',
            followers_count INT UNSIGNED DEFAULT 0,
            following_count INT UNSIGNED DEFAULT 0,
            mutual_count INT UNSIGNED DEFAULT 0,
            last_calculated DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (entity_id, entity_type),
            KEY idx_type_followers (entity_type, followers_count),
            KEY idx_last_calc (last_calculated)
        ) $charset_collate;";
    }
    
    /**
     * Get SQL for activities table
     *
     * @return string
     */
    public static function get_activities_table_sql(): string {
        global $wpdb;
        $table_name = self::get_activities_table();
        $charset_collate = $wpdb->get_charset_collate();
        
        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_id BIGINT(20) UNSIGNED NOT NULL,
            actor_type ENUM('user', 'critic', 'business') DEFAULT 'user',
            action VARCHAR(50) NOT NULL,
            object_type VARCHAR(50),
            object_id BIGINT(20) UNSIGNED,
            metadata JSON,
            visibility ENUM('public', 'followers', 'private') DEFAULT 'public',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_actor (actor_id, actor_type, created_at),
            KEY idx_visibility (visibility, created_at),
            KEY idx_action (action, created_at)
        ) $charset_collate;";
    }
    
    /**
     * Get SQL for suggestions table
     *
     * @return string
     */
    public static function get_suggestions_table_sql(): string {
        global $wpdb;
        $table_name = self::get_suggestions_table();
        $charset_collate = $wpdb->get_charset_collate();
        
        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            suggested_id BIGINT(20) UNSIGNED NOT NULL,
            suggested_type ENUM('user', 'critic', 'business') DEFAULT 'user',
            reason VARCHAR(100),
            score DECIMAL(5,2) DEFAULT 0,
            shown_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            dismissed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_suggestion (user_id, suggested_id, suggested_type),
            KEY idx_user_score (user_id, dismissed_at, score DESC)
        ) $charset_collate;";
    }
    
    /**
     * Get all schema SQL for Foundation registration
     *
     * @return array
     */
    public static function get_schema_sql(): array {
        return [
            'follows' => self::get_follows_table_sql(),
            'follow_stats' => self::get_follow_stats_table_sql(),
            'activities' => self::get_activities_table_sql(),
            'suggestions' => self::get_suggestions_table_sql(),
        ];
    }
    
    /**
     * Verify all tables exist
     *
     * @return bool
     */
    public static function verify_tables(): bool {
        global $wpdb;
        
        $tables = [
            self::get_follows_table(),
            self::get_follow_stats_table(),
            self::get_activities_table(),
            self::get_suggestions_table(),
        ];
        
        foreach ($tables as $table) {
            $query = $wpdb->prepare("SHOW TABLES LIKE %s", $table);
            if ($wpdb->get_var($query) !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Drop all tables (for uninstall)
     *
     * @return void
     */
    public static function drop_tables(): void {
        global $wpdb;
        
        $tables = [
            self::get_follows_table(),
            self::get_follow_stats_table(),
            self::get_activities_table(),
            self::get_suggestions_table(),
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }
}