<?php
/**
 * Top 10 Cache Schema
 *
 * Database schema for caching generated Top 10 lists with enterprise-grade
 * structure and indexing for optimal performance.
 *
 * @package ZipPicks_Master_Critic
 * @subpackage MasterCritic\Schema
 * @since 2.0.0
 */

class ZipPicks_Master_Critic_Top10_Cache_Schema {
    
    /**
     * Create cache table
     *
     * @return bool True on success
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zippicks_top10_cache';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            city_slug varchar(100) NOT NULL,
            vibe_slug varchar(100) NOT NULL,
            restaurant_names longtext NOT NULL,
            full_prompt text DEFAULT NULL,
            ai_response longtext NOT NULL,
            confidence_avg float DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY city_vibe (city_slug, vibe_slug),
            KEY created_at (created_at),
            KEY confidence_avg (confidence_avg)
        ) {$charset_collate}";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Verify table was created
        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }
    
    /**
     * Drop cache table
     *
     * @return bool True on success
     */
    public static function drop_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zippicks_top10_cache';
        
        // Use proper DROP TABLE query
        $result = $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        
        return $result !== false;
    }
    
    /**
     * Clear old cache entries
     *
     * @param int $days_old Number of days to keep
     * @return int Number of rows deleted
     */
    public static function clear_old_cache($days_old = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zippicks_top10_cache';
        
        // Verify table exists before attempting delete
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return 0;
        }
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_old
        ));
        
        return $result === false ? 0 : intval($result);
    }
    
    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public static function get_cache_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zippicks_top10_cache';
        
        // Verify table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return [
                'total_entries' => 0,
                'cities' => 0,
                'vibes' => 0,
                'avg_confidence' => 0,
                'oldest_entry' => null,
                'newest_entry' => null
            ];
        }
        
        // Get total entries
        $total_entries = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Get unique cities count
        $cities = $wpdb->get_var("SELECT COUNT(DISTINCT city_slug) FROM {$table_name}");
        
        // Get unique vibes count
        $vibes = $wpdb->get_var("SELECT COUNT(DISTINCT vibe_slug) FROM {$table_name}");
        
        // Get average confidence
        $avg_confidence = $wpdb->get_var("SELECT AVG(confidence_avg) FROM {$table_name}");
        
        // Get date range
        $oldest = $wpdb->get_var("SELECT MIN(created_at) FROM {$table_name}");
        $newest = $wpdb->get_var("SELECT MAX(created_at) FROM {$table_name}");
        
        return [
            'total_entries' => intval($total_entries),
            'cities' => intval($cities),
            'vibes' => intval($vibes),
            'avg_confidence' => round(floatval($avg_confidence), 3),
            'oldest_entry' => $oldest,
            'newest_entry' => $newest
        ];
    }
    
    /**
     * Optimize table
     *
     * @return bool True on success
     */
    public static function optimize_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zippicks_top10_cache';
        
        // Verify table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return false;
        }
        
        // Run OPTIMIZE TABLE query
        $result = $wpdb->query("OPTIMIZE TABLE {$table_name}");
        
        return $result !== false;
    }
    
    /**
     * Check if cache table exists
     *
     * @return bool True if table exists
     */
    public static function table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zippicks_top10_cache';
        
        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }
    
    /**
     * Get table structure for verification
     *
     * @return array|false Table structure or false if not exists
     */
    public static function get_table_structure() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zippicks_top10_cache';
        
        if (!self::table_exists()) {
            return false;
        }
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
        
        return $columns;
    }
}