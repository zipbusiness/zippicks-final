<?php
namespace ZipPicks\Favorites;

/**
 * Cache handler for favorites data
 */
class Cache {
    
    private $prefix = 'zp_fav_';
    private $default_ttl;
    private $use_transients = true;
    
    public function __construct() {
        $this->default_ttl = get_option('zippicks_favorites_cache_ttl', 300);
        
        // Use object cache if available
        if (wp_using_ext_object_cache()) {
            $this->use_transients = false;
        }
    }
    
    /**
     * Get cached value
     */
    public function get($key) {
        $cache_key = $this->prefix . $key;
        
        if ($this->use_transients) {
            return get_transient($cache_key);
        } else {
            return wp_cache_get($cache_key, 'zippicks_favorites');
        }
    }
    
    /**
     * Set cached value
     */
    public function set($key, $value, $ttl = null) {
        $cache_key = $this->prefix . $key;
        $ttl = $ttl ?? $this->default_ttl;
        
        if ($this->use_transients) {
            return set_transient($cache_key, $value, $ttl);
        } else {
            return wp_cache_set($cache_key, $value, 'zippicks_favorites', $ttl);
        }
    }
    
    /**
     * Delete cached value
     */
    public function delete($key) {
        $cache_key = $this->prefix . $key;
        
        if ($this->use_transients) {
            return delete_transient($cache_key);
        } else {
            return wp_cache_delete($cache_key, 'zippicks_favorites');
        }
    }
    
    /**
     * Delete cache by pattern
     */
    public function delete_pattern($pattern) {
        global $wpdb;
        
        if ($this->use_transients) {
            // Delete transients matching pattern
            $sql = $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                OR option_name LIKE %s",
                '_transient_' . $this->prefix . $pattern,
                '_transient_timeout_' . $this->prefix . $pattern
            );
            $wpdb->query($sql);
        } else {
            // For object cache, we need to track keys
            $this->flush_group();
        }
    }
    
    /**
     * Flush all favorites cache
     */
    public function flush() {
        if ($this->use_transients) {
            $this->delete_pattern('%');
        } else {
            wp_cache_flush_group('zippicks_favorites');
        }
    }
    
    /**
     * Flush cache group (for object cache)
     */
    private function flush_group() {
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('zippicks_favorites');
        } else {
            // Fallback: delete known keys
            $keys = $this->get_tracked_keys();
            foreach ($keys as $key) {
                wp_cache_delete($key, 'zippicks_favorites');
            }
            $this->clear_tracked_keys();
        }
    }
    
    /**
     * Track cache keys for pattern deletion
     */
    private function track_key($key) {
        if (!$this->use_transients) {
            $tracked = get_option('zp_favorites_cache_keys', []);
            $tracked[] = $this->prefix . $key;
            update_option('zp_favorites_cache_keys', array_unique($tracked));
        }
    }
    
    /**
     * Get tracked keys
     */
    private function get_tracked_keys() {
        return get_option('zp_favorites_cache_keys', []);
    }
    
    /**
     * Clear tracked keys
     */
    private function clear_tracked_keys() {
        delete_option('zp_favorites_cache_keys');
    }
    
    /**
     * Cache user favorites list
     */
    public function cache_user_favorites($user_id, $favorites, $context = 'all') {
        $key = "user_favs_{$user_id}_{$context}";
        $this->set($key, $favorites);
        $this->track_key($key);
    }
    
    /**
     * Get cached user favorites
     */
    public function get_user_favorites($user_id, $context = 'all') {
        $key = "user_favs_{$user_id}_{$context}";
        return $this->get($key);
    }
    
    /**
     * Clear user favorites cache
     */
    public function clear_user_cache($user_id) {
        $this->delete_pattern("user_favs_{$user_id}_%");
        $this->delete_pattern("favorited_{$user_id}_%");
        $this->delete("favorite_cities_{$user_id}");
        $this->delete("favorite_count_{$user_id}");
    }
    
    /**
     * Cache favorite cities
     */
    public function cache_favorite_cities($user_id, $cities) {
        $key = "favorite_cities_{$user_id}";
        $this->set($key, $cities, 3600); // Cache for 1 hour
        $this->track_key($key);
    }
    
    /**
     * Get cached favorite cities
     */
    public function get_favorite_cities($user_id) {
        $key = "favorite_cities_{$user_id}";
        return $this->get($key);
    }
    
    /**
     * Remember cache
     * Store a value with a specific TTL
     */
    public function remember($key, $callback, $ttl = null) {
        $cached = $this->get($key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $value = call_user_func($callback);
        $this->set($key, $value, $ttl);
        
        return $value;
    }
}