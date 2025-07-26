<?php
/**
 * Cache manager for ZipPicks Social
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_Cache_Manager
 * 
 * Handles caching for the follow system with Foundation integration
 */
class ZipPicks_Social_Cache_Manager {
    
    /**
     * Cache group name
     */
    const CACHE_GROUP = 'zippicks_social';
    
    /**
     * Default cache duration
     */
    const DEFAULT_DURATION = 300; // 5 minutes
    
    /**
     * Foundation cache instance
     *
     * @var object|null
     */
    private $foundation_cache = null;
    
    /**
     * Constructor
     *
     * @param object|null $foundation_cache Foundation cache service
     */
    public function __construct($foundation_cache = null) {
        $this->foundation_cache = $foundation_cache;
    }
    
    /**
     * Get cached value
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed Cached value or false
     */
    public function get(string $key, string $group = self::CACHE_GROUP) {
        // Use Foundation cache if available
        if ($this->foundation_cache && method_exists($this->foundation_cache, 'get')) {
            return $this->foundation_cache->get($key, $group);
        }
        
        // Fallback to WordPress transients
        return get_transient($this->get_transient_key($key, $group));
    }
    
    /**
     * Set cached value
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param string $group Cache group
     * @param int $duration Cache duration in seconds
     * @return bool Success
     */
    public function set(string $key, $value, string $group = self::CACHE_GROUP, int $duration = self::DEFAULT_DURATION): bool {
        // Use Foundation cache if available
        if ($this->foundation_cache && method_exists($this->foundation_cache, 'set')) {
            return $this->foundation_cache->set($key, $value, $group, $duration);
        }
        
        // Fallback to WordPress transients
        return set_transient($this->get_transient_key($key, $group), $value, $duration);
    }
    
    /**
     * Delete cached value
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Success
     */
    public function delete(string $key, string $group = self::CACHE_GROUP): bool {
        // Use Foundation cache if available
        if ($this->foundation_cache && method_exists($this->foundation_cache, 'delete')) {
            return $this->foundation_cache->delete($key, $group);
        }
        
        // Fallback to WordPress transients
        return delete_transient($this->get_transient_key($key, $group));
    }
    
    /**
     * Clear cache group
     *
     * @param string $group Cache group
     * @return bool Success
     */
    public function flush_group(string $group = self::CACHE_GROUP): bool {
        // Use Foundation cache if available
        if ($this->foundation_cache && method_exists($this->foundation_cache, 'flush_group')) {
            return $this->foundation_cache->flush_group($group);
        }
        
        // Fallback: clear known transients
        global $wpdb;
        
        $prefix = $this->get_transient_prefix($group);
        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 OR option_name LIKE %s",
                '_transient_' . $prefix . '%',
                '_transient_timeout_' . $prefix . '%'
            )
        );
        
        foreach ($transients as $transient) {
            delete_option($transient);
        }
        
        return true;
    }
    
    /**
     * Get transient key
     *
     * @param string $key
     * @param string $group
     * @return string
     */
    private function get_transient_key(string $key, string $group): string {
        return $this->get_transient_prefix($group) . '_' . $key;
    }
    
    /**
     * Get transient prefix
     *
     * @param string $group
     * @return string
     */
    private function get_transient_prefix(string $group): string {
        return 'zps_' . substr(md5($group), 0, 8);
    }
}