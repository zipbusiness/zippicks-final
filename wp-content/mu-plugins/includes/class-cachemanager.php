<?php
/**
 * Cache Manager
 * 
 * Handles caching for performance optimization across the platform.
 * 
 * @package ZipPicks\Foundation
 */

namespace ZipPicks\Foundation;

if (!defined('ABSPATH')) {
    exit;
}

class CacheManager {
    
    /**
     * Cache group prefix
     * 
     * @var string
     */
    private $cache_group = 'zippicks';
    
    /**
     * Default cache expiration
     * 
     * @var int
     */
    private $default_expiration = 3600; // 1 hour
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Clear cache on relevant actions
        add_action('save_post_zippicks_business', [$this, 'clear_business_cache']);
        add_action('save_post_zippicks_review', [$this, 'clear_review_cache']);
        add_action('created_zippicks_vibe', [$this, 'clear_vibe_cache']);
        add_action('edited_zippicks_vibe', [$this, 'clear_vibe_cache']);
        add_action('delete_zippicks_vibe', [$this, 'clear_vibe_cache']);
        
        // Cache warming
        add_action('zippicks_hourly_maintenance', [$this, 'warm_cache']);
    }
    
    /**
     * Get cached data
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed Cached data or false
     */
    public function get($key, $group = null) {
        $group = $group ?: $this->cache_group;
        
        // Try object cache first
        $data = wp_cache_get($key, $group);
        
        if (false === $data) {
            // Try transient as fallback
            $data = get_transient($this->get_transient_key($key, $group));
        }
        
        return $data;
    }
    
    /**
     * Set cached data
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $expiration Expiration time in seconds
     * @param string $group Cache group
     * @return bool Success
     */
    public function set($key, $data, $expiration = null, $group = null) {
        $group = $group ?: $this->cache_group;
        $expiration = $expiration ?: $this->default_expiration;
        
        // Set in object cache
        $result = wp_cache_set($key, $data, $group, $expiration);
        
        // Also set as transient for persistence
        set_transient($this->get_transient_key($key, $group), $data, $expiration);
        
        return $result;
    }
    
    /**
     * Delete cached data
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Success
     */
    public function delete($key, $group = null) {
        $group = $group ?: $this->cache_group;
        
        // Delete from object cache
        wp_cache_delete($key, $group);
        
        // Delete transient
        delete_transient($this->get_transient_key($key, $group));
        
        return true;
    }
    
    /**
     * Clear cache by group
     * 
     * @param string $group Cache group
     * @return bool Success
     */
    public function clear_group($group) {
        // Clear object cache group
        wp_cache_flush_group($group);
        
        // Clear related transients
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            '_transient_zippicks_' . $group . '_%',
            '_transient_timeout_zippicks_' . $group . '_%'
        ));
        
        return true;
    }
    
    /**
     * Get transient key
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return string Transient key
     */
    private function get_transient_key($key, $group) {
        return 'zippicks_' . $group . '_' . md5($key);
    }
    
    /**
     * Clear business cache
     * 
     * @param int $post_id Post ID
     */
    public function clear_business_cache($post_id) {
        // Clear specific business cache
        $this->delete('business_' . $post_id);
        $this->delete('business_score_' . $post_id);
        $this->delete('business_vibes_' . $post_id);
        
        // Clear listings cache
        $this->clear_group('listings');
        
        // Clear ZIP-specific caches
        $zip = get_post_meta($post_id, '_zippicks_zip', true);
        if ($zip) {
            $this->delete('zip_businesses_' . $zip);
            $this->delete('zip_trending_' . $zip);
        }
    }
    
    /**
     * Clear review cache
     * 
     * @param int $post_id Post ID
     */
    public function clear_review_cache($post_id) {
        $business_id = get_post_meta($post_id, '_zippicks_business_id', true);
        
        if ($business_id) {
            $this->delete('business_reviews_' . $business_id);
            $this->delete('business_score_' . $business_id);
            $this->clear_business_cache($business_id);
        }
    }
    
    /**
     * Clear vibe cache
     * 
     * @param int $term_id Term ID
     */
    public function clear_vibe_cache($term_id = null) {
        $this->clear_group('vibes');
        $this->clear_group('listings');
    }
    
    /**
     * Cache taste graph data
     * 
     * @param int $user_id User ID
     * @param array $data Taste data
     * @param int $expiration Expiration time
     */
    public function cache_taste_data($user_id, $data, $expiration = 3600) {
        $this->set('taste_profile_' . $user_id, $data, $expiration, 'taste_graph');
        $this->set('taste_vector_' . $user_id, $data['taste_vector'] ?? [], $expiration, 'taste_graph');
    }
    
    /**
     * Get taste graph cache
     * 
     * @param int $user_id User ID
     * @return array|false Cached data
     */
    public function get_taste_cache($user_id) {
        return $this->get('taste_profile_' . $user_id, 'taste_graph');
    }
    
    /**
     * Cache search results
     * 
     * @param string $query_hash Query hash
     * @param array $results Search results
     * @param int $expiration Expiration time
     */
    public function cache_search_results($query_hash, $results, $expiration = 1800) {
        $this->set('search_' . $query_hash, $results, $expiration, 'search');
    }
    
    /**
     * Get cached search results
     * 
     * @param string $query_hash Query hash
     * @return array|false Cached results
     */
    public function get_search_cache($query_hash) {
        return $this->get('search_' . $query_hash, 'search');
    }
    
    /**
     * Cache API response
     * 
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param mixed $response Response data
     * @param int $expiration Expiration time
     */
    public function cache_api_response($endpoint, $params, $response, $expiration = 3600) {
        $key = 'api_' . md5($endpoint . serialize($params));
        $this->set($key, $response, $expiration, 'api');
    }
    
    /**
     * Get cached API response
     * 
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return mixed|false Cached response
     */
    public function get_api_cache($endpoint, $params) {
        $key = 'api_' . md5($endpoint . serialize($params));
        return $this->get($key, 'api');
    }
    
    /**
     * Warm cache (scheduled task)
     */
    public function warm_cache() {
        // Warm popular ZIP caches
        $this->warm_zip_caches();
        
        // Warm trending data
        $this->warm_trending_caches();
        
        // Warm popular business caches
        $this->warm_business_caches();
    }
    
    /**
     * Warm ZIP caches
     */
    private function warm_zip_caches() {
        global $wpdb;
        
        // Get top 10 most active ZIPs
        $top_zips = $wpdb->get_col("
            SELECT meta_value, COUNT(*) as count
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_zippicks_zip'
            GROUP BY meta_value
            ORDER BY count DESC
            LIMIT 10
        ");
        
        foreach ($top_zips as $zip) {
            // Cache business listings for each ZIP
            $businesses = get_posts([
                'post_type' => 'zippicks_business',
                'posts_per_page' => 20,
                'meta_query' => [
                    [
                        'key' => '_zippicks_zip',
                        'value' => $zip
                    ]
                ],
                'orderby' => 'meta_value_num',
                'meta_key' => '_zippicks_master_score',
                'order' => 'DESC'
            ]);
            
            $this->set('zip_businesses_' . $zip, $businesses, 3600);
        }
    }
    
    /**
     * Warm trending caches
     */
    private function warm_trending_caches() {
        // Cache trending vibes
        $vibe_taxonomy = Core::get_instance()->get_service('vibe_taxonomy');
        $trending_vibes = $vibe_taxonomy->get_trending_vibes();
        $this->set('trending_vibes_global', $trending_vibes, 1800);
    }
    
    /**
     * Warm business caches
     */
    private function warm_business_caches() {
        // Get top-rated businesses
        $top_businesses = get_posts([
            'post_type' => 'zippicks_business',
            'posts_per_page' => 50,
            'meta_key' => '_zippicks_master_score',
            'orderby' => 'meta_value_num',
            'order' => 'DESC'
        ]);
        
        foreach ($top_businesses as $business) {
            // Cache business data
            $business_data = [
                'post' => $business,
                'meta' => get_post_meta($business->ID),
                'vibes' => wp_get_object_terms($business->ID, 'zippicks_vibe'),
                'score' => get_post_meta($business->ID, '_zippicks_master_score_data', true)
            ];
            
            $this->set('business_' . $business->ID, $business_data, 7200);
        }
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache stats
     */
    public function get_stats() {
        global $wpdb;
        
        // Count transients
        $transient_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_zippicks_%'
        ");
        
        // Calculate size
        $transient_size = $wpdb->get_var("
            SELECT SUM(LENGTH(option_value)) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_zippicks_%'
        ");
        
        return [
            'transient_count' => intval($transient_count),
            'transient_size' => size_format($transient_size ?: 0),
            'object_cache_enabled' => wp_using_ext_object_cache(),
            'groups' => [
                'business' => $this->count_group_items('business'),
                'search' => $this->count_group_items('search'),
                'taste_graph' => $this->count_group_items('taste_graph'),
                'api' => $this->count_group_items('api')
            ]
        ];
    }
    
    /**
     * Count items in cache group
     * 
     * @param string $group Cache group
     * @return int Item count
     */
    private function count_group_items($group) {
        global $wpdb;
        
        return intval($wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", '_transient_zippicks_' . $group . '_%')));
    }
    
    /**
     * Clear all caches
     */
    public function clear_all() {
        // Clear object cache
        wp_cache_flush();
        
        // Clear ZipPicks transients
        global $wpdb;
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_zippicks_%' 
            OR option_name LIKE '_transient_timeout_zippicks_%'
        ");
        
        // Clear cached files if using file-based caching
        $this->clear_file_cache();
        
        do_action('zippicks_cache_cleared');
    }
    
    /**
     * Clear file-based cache
     */
    private function clear_file_cache() {
        $cache_dir = WP_CONTENT_DIR . '/cache/zippicks/';
        
        if (is_dir($cache_dir)) {
            $this->recursive_rmdir($cache_dir);
        }
    }
    
    /**
     * Recursively remove directory
     * 
     * @param string $dir Directory path
     */
    private function recursive_rmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursive_rmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}