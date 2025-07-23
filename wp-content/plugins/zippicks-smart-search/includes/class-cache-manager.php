<?php
/**
 * Cache Manager
 * 
 * Handles caching using WordPress Object Cache (Redis via Pressidium)
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Cache_Manager {
    
    /**
     * Cache group for search results
     * @var string
     */
    const CACHE_GROUP = 'zippicks_search';
    
    /**
     * Default cache TTL (5 minutes)
     * @var int
     */
    const DEFAULT_TTL = 300;
    
    /**
     * TTL for vibe searches (shorter, more dynamic)
     * @var int
     */
    const VIBE_TTL = 180;
    
    /**
     * TTL for utility searches (longer, more stable)
     * @var int
     */
    const UTILITY_TTL = 600;
    
    /**
     * Get cache instance
     * @return Cache_Manager
     */
    public static function instance() {
        static $instance = null;
        if (is_null($instance)) {
            $instance = new self();
        }
        return $instance;
    }
    
    /**
     * Check if object cache is available
     * @return bool
     */
    public function is_available() {
        return wp_using_ext_object_cache();
    }
    
    /**
     * Get cached search results
     * 
     * @param string $query Search query
     * @param array $location Location data
     * @param array $params Additional parameters
     * @return mixed|false Cached data or false
     */
    public function get_search_results($query, $location, $params = []) {
        $cache_key = $this->generate_search_key($query, $location, $params);
        return wp_cache_get($cache_key, self::CACHE_GROUP);
    }
    
    /**
     * Cache search results
     * 
     * @param string $query Search query
     * @param array $location Location data
     * @param array $params Additional parameters
     * @param mixed $results Results to cache
     * @param string $intent Search intent type
     * @return bool
     */
    public function set_search_results($query, $location, $params, $results, $intent = 'hybrid') {
        $cache_key = $this->generate_search_key($query, $location, $params);
        $ttl = $this->get_ttl_by_intent($intent);
        
        return wp_cache_set($cache_key, $results, self::CACHE_GROUP, $ttl);
    }
    
    /**
     * Get cached business data
     * 
     * @param string $zpid Business ZPID
     * @return mixed|false
     */
    public function get_business($zpid) {
        return wp_cache_get('business_' . $zpid, self::CACHE_GROUP);
    }
    
    /**
     * Cache business data
     * 
     * @param string $zpid Business ZPID
     * @param array $data Business data
     * @return bool
     */
    public function set_business($zpid, $data) {
        return wp_cache_set('business_' . $zpid, $data, self::CACHE_GROUP, self::DEFAULT_TTL);
    }
    
    /**
     * Get cached autocomplete suggestions
     * 
     * @param string $prefix Search prefix
     * @param array $location Location data
     * @return mixed|false
     */
    public function get_autocomplete($prefix, $location) {
        $cache_key = $this->generate_autocomplete_key($prefix, $location);
        return wp_cache_get($cache_key, self::CACHE_GROUP);
    }
    
    /**
     * Cache autocomplete suggestions
     * 
     * @param string $prefix Search prefix
     * @param array $location Location data
     * @param array $suggestions Suggestions to cache
     * @return bool
     */
    public function set_autocomplete($prefix, $location, $suggestions) {
        $cache_key = $this->generate_autocomplete_key($prefix, $location);
        return wp_cache_set($cache_key, $suggestions, self::CACHE_GROUP, 60); // 1 minute TTL
    }
    
    /**
     * Delete cached item
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function delete($key) {
        return wp_cache_delete($key, self::CACHE_GROUP);
    }
    
    /**
     * Clear all search cache
     * 
     * @return bool
     */
    public function clear_all() {
        // If using Redis, we can use wp_cache_flush_group
        if (function_exists('wp_cache_flush_group')) {
            return wp_cache_flush_group(self::CACHE_GROUP);
        }
        
        // Fallback: WordPress doesn't have a native group flush
        // This would require custom implementation or Redis-specific code
        return false;
    }
    
    /**
     * Invalidate cache for a specific business
     * 
     * @param string $zpid Business ZPID
     * @return bool
     */
    public function invalidate_business($zpid) {
        return $this->delete('business_' . $zpid);
    }
    
    /**
     * Generate cache key for search results
     * 
     * @param string $query Search query
     * @param array $location Location data
     * @param array $params Additional parameters
     * @return string
     */
    private function generate_search_key($query, $location, $params = []) {
        // Apply cache key filter for normalization
        $base_key = apply_filters('zippicks_search_cache_key', '', $query);
        
        if (empty($base_key)) {
            // Fallback to default key generation
            $base_key = 'search_' . md5(strtolower(trim($query)));
        }
        
        $key_parts = [
            $base_key,
            isset($location['lat']) ? round($location['lat'], 3) : 0,
            isset($location['lng']) ? round($location['lng'], 3) : 0,
            isset($params['radius']) ? $params['radius'] : 10,
            isset($params['limit']) ? $params['limit'] : 20
        ];
        
        return implode('_', $key_parts);
    }
    
    /**
     * Generate cache key for autocomplete
     * 
     * @param string $prefix Search prefix
     * @param array $location Location data
     * @return string
     */
    private function generate_autocomplete_key($prefix, $location) {
        $key_parts = [
            'autocomplete',
            md5(strtolower(trim($prefix))),
            isset($location['lat']) ? round($location['lat'], 2) : 0,
            isset($location['lng']) ? round($location['lng'], 2) : 0
        ];
        
        return implode('_', $key_parts);
    }
    
    /**
     * Get TTL based on search intent
     * 
     * @param string $intent Search intent
     * @return int
     */
    private function get_ttl_by_intent($intent) {
        $custom_ttl = get_option('zippicks_search_cache_ttl', 0);
        if ($custom_ttl > 0) {
            return $custom_ttl;
        }
        
        // Apply TTL optimization filter
        $default_ttl = 0;
        switch ($intent) {
            case 'vibe':
                $default_ttl = self::VIBE_TTL;
                break;
            case 'utility':
                $default_ttl = self::UTILITY_TTL;
                break;
            default:
                $default_ttl = self::DEFAULT_TTL;
        }
        
        return apply_filters('zippicks_search_cache_ttl', $default_ttl, $intent);
    }
    
    /**
     * Warm cache with popular searches
     * 
     * @param array $searches Array of popular search terms
     * @param array $location Default location
     * @return array Results of cache warming
     */
    public function warm_cache($searches, $location) {
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0
        ];
        
        if (!$this->is_available()) {
            return $results;
        }
        
        $search_engine = new Search_Engine();
        
        foreach ($searches as $search_term) {
            // Check if already cached
            if ($this->get_search_results($search_term, $location)) {
                $results['skipped']++;
                continue;
            }
            
            // Perform search to warm cache
            $search_results = $search_engine->search($search_term, $location);
            
            if (!is_wp_error($search_results)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function get_stats() {
        $stats = [
            'enabled' => $this->is_available(),
            'driver' => 'unknown',
            'health' => 'unknown'
        ];
        
        if ($this->is_available()) {
            // Check if Redis is being used
            global $wp_object_cache;
            
            if (is_object($wp_object_cache)) {
                $cache_class = get_class($wp_object_cache);
                if (stripos($cache_class, 'redis') !== false) {
                    $stats['driver'] = 'redis';
                    $stats['health'] = 'healthy';
                } elseif (stripos($cache_class, 'memcache') !== false) {
                    $stats['driver'] = 'memcached';
                    $stats['health'] = 'healthy';
                }
            }
        }
        
        return $stats;
    }
}