<?php
/**
 * Enterprise Cache Manager for Master Critic
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

/**
 * Cache manager with support for multiple cache backends
 */
class ZipPicks_Master_Critic_Cache_Manager {
    
    /**
     * Cache group for all plugin caches
     *
     * @var string
     */
    const CACHE_GROUP = 'zippicks_master_critic';
    
    /**
     * Default cache expiration (24 hours)
     *
     * @var int
     */
    const DEFAULT_EXPIRATION = 86400;
    
    /**
     * Cache TTL tiers for different query types
     *
     * @var array
     */
    const TTL_TIERS = [
        'popular_lists' => 604800,   // 7 days for popular lists
        'city_data' => 259200,       // 3 days for city-level data  
        'business_data' => 86400,    // 24 hours for business data
        'api_responses' => 43200,    // 12 hours for API responses
        'search_results' => 3600,    // 1 hour for search results
        'real_time' => 300          // 5 minutes for real-time data
    ];
    
    /**
     * Cache statistics
     *
     * @var array
     */
    protected array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
        'hit_rate' => 0.0
    ];
    
    /**
     * Cache backend type
     *
     * @var string
     */
    protected string $backend;
    
    /**
     * Whether APCu is available
     *
     * @var bool
     */
    protected bool $has_apcu;
    
    /**
     * Whether Redis is available
     *
     * @var bool
     */
    protected bool $has_redis;
    
    /**
     * Logger instance
     *
     * @var ZipPicks_Master_Critic_Logger|null
     */
    protected ?ZipPicks_Master_Critic_Logger $logger = null;
    
    /**
     * Cache key prefix
     *
     * @var string
     */
    protected string $key_prefix;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->detect_cache_backend();
        $this->key_prefix = $this->generate_key_prefix();
        
        // Load logger if available
        if (class_exists('ZipPicks_Master_Critic_Logger')) {
            $this->logger = new ZipPicks_Master_Critic_Logger();
        }
        
        // Register shutdown function to save stats
        register_shutdown_function([$this, 'save_stats']);
    }
    
    /**
     * Detect available cache backend
     */
    protected function detect_cache_backend(): void {
        $this->has_apcu = function_exists('apcu_fetch') && 
                          (function_exists('ini_get') ? @ini_get('apc.enabled') : false);
        $this->has_redis = class_exists('Redis') && $this->test_redis_connection();
        
        // Determine backend priority
        if ($this->has_redis) {
            $this->backend = 'redis';
        } elseif ($this->has_apcu) {
            $this->backend = 'apcu';
        } elseif (wp_using_ext_object_cache()) {
            $this->backend = 'object_cache';
        } else {
            $this->backend = 'transient';
        }
        
        $this->log_info('Cache backend detected', ['backend' => $this->backend]);
    }
    
    /**
     * Test Redis connection
     *
     * @return bool
     */
    protected function test_redis_connection(): bool {
        if (!class_exists('Redis')) {
            return false;
        }
        
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379, 0.5); // 500ms timeout
            $redis->close();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Generate unique key prefix for this installation
     *
     * @return string
     */
    protected function generate_key_prefix(): string {
        return substr(md5(ABSPATH . DB_NAME . DB_PASSWORD), 0, 8) . '_';
    }
    
    /**
     * Set TTL based on query type
     *
     * @param string $key
     * @param string $type
     * @return int
     */
    public function get_ttl_for_type(string $type): int {
        return self::TTL_TIERS[$type] ?? self::DEFAULT_EXPIRATION;
    }
    
    /**
     * Cache warming system for 90%+ hit rate
     *
     * @return void
     */
    public function warm_cache(): void {
        $this->log_info('Starting comprehensive cache warming');
        
        // Analytics-driven warming (popular content)
        $this->warm_popular_lists();
        $this->warm_city_data();
        $this->warm_trending_searches();
        
        // System-level warming (templates & categories)
        $this->warm_common_templates();
        $this->warm_business_categories();
        $this->warm_recent_lists();
        
        // Update cache statistics
        $this->update_cache_statistics();
        
        $this->log_info('Cache warming completed', [
            'hit_rate' => $this->calculate_hit_rate() . '%',
            'total_items' => $this->stats['writes']
        ]);
    }
    
    /**
     * Warm popular lists based on analytics
     */
    protected function warm_popular_lists(): void {
        global $wpdb;
        
        // Get most viewed lists from analytics
        $popular_lists = $wpdb->get_results(
            "SELECT DISTINCT post_id, COUNT(*) as views
             FROM {$wpdb->prefix}zippicks_analytics
             WHERE event_type = 'list_view'
             AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY post_id
             ORDER BY views DESC
             LIMIT 50"
        );
        
        foreach ($popular_lists as $list) {
            $cache_key = 'popular_list_' . $list->post_id;
            if (!$this->get($cache_key)) {
                // Fetch and cache the list data
                $list_data = get_post($list->post_id);
                if ($list_data) {
                    $this->set($cache_key, $list_data, $this->get_ttl_for_type('popular_lists'));
                }
            }
        }
    }
    
    /**
     * Warm city-level aggregated data
     */
    protected function warm_city_data(): void {
        // Get active cities from lists
        $cities = wp_cache_get('active_cities', self::CACHE_GROUP);
        
        if (!$cities) {
            $cities = get_posts([
                'post_type' => 'master_critic_list',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'location',
                        'compare' => 'EXISTS'
                    ]
                ]
            ]);
            
            wp_cache_set('active_cities', $cities, self::CACHE_GROUP, HOUR_IN_SECONDS);
        }
        
        // Pre-cache city aggregations
        foreach ($cities as $city_id) {
            $location = get_post_meta($city_id, 'location', true);
            if ($location) {
                $cache_key = 'city_data_' . sanitize_title($location);
                $this->set($cache_key, [
                    'location' => $location,
                    'list_count' => $this->get_city_list_count($location),
                    'last_updated' => current_time('mysql')
                ], $this->get_ttl_for_type('city_data'));
            }
        }
    }
    
    /**
     * Warm trending search patterns
     */
    protected function warm_trending_searches(): void {
        global $wpdb;
        
        // Get trending searches from query patterns
        $trending = $wpdb->get_results(
            "SELECT query_hash, query_params, COUNT(*) as count
             FROM {$wpdb->prefix}zippicks_query_patterns
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY query_hash
             ORDER BY count DESC
             LIMIT 20"
        );
        
        foreach ($trending as $search) {
            $params = json_decode($search->query_params, true);
            if ($params) {
                $cache_key = 'search_' . $search->query_hash;
                // Cache search results with shorter TTL
                $this->set($cache_key, $params, $this->get_ttl_for_type('search_results'));
            }
        }
    }
    
    /**
     * Update cache hit rate statistics
     */
    protected function update_cache_statistics(): void {
        $this->stats['hit_rate'] = $this->calculate_hit_rate();
        
        // Save daily statistics
        $daily_stats = get_option('zippicks_cache_daily_stats', []);
        $today = date('Y-m-d');
        
        $daily_stats[$today] = [
            'hit_rate' => $this->stats['hit_rate'],
            'total_hits' => $this->stats['hits'],
            'total_misses' => $this->stats['misses'],
            'timestamp' => current_time('timestamp')
        ];
        
        // Keep only last 30 days
        $daily_stats = array_slice($daily_stats, -30, 30, true);
        update_option('zippicks_cache_daily_stats', $daily_stats);
    }
    
    /**
     * Get city list count
     *
     * @param string $location
     * @return int
     */
    protected function get_city_list_count(string $location): int {
        return count(get_posts([
            'post_type' => 'master_critic_list',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'location',
                    'value' => $location,
                    'compare' => '='
                ]
            ]
        ]));
    }
    
    /**
     * Get cached value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = false) {
        $key = $this->prepare_key($key);
        $value = false;
        
        switch ($this->backend) {
            case 'redis':
                $value = $this->get_from_redis($key);
                break;
                
            case 'apcu':
                $value = $this->get_from_apcu($key);
                break;
                
            case 'object_cache':
                $value = wp_cache_get($key, self::CACHE_GROUP);
                break;
                
            case 'transient':
                $value = get_transient($key);
                break;
        }
        
        if ($value !== false) {
            $this->stats['hits']++;
            $this->log_debug('Cache hit', ['key' => $key]);
            
            // Unserialize if needed
            if (is_string($value) && $this->is_serialized($value)) {
                $value = @unserialize($value);
            }
            
            return $value;
        }
        
        $this->stats['misses']++;
        $this->log_debug('Cache miss', ['key' => $key]);
        
        return $default;
    }
    
    /**
     * Set cache value
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return bool
     */
    public function set(string $key, $value, int $expiration = 0): bool {
        $key = $this->prepare_key($key);
        $expiration = $expiration ?: self::DEFAULT_EXPIRATION;
        
        // Serialize complex data types
        if (is_array($value) || is_object($value)) {
            $value = serialize($value);
        }
        
        $result = false;
        
        switch ($this->backend) {
            case 'redis':
                $result = $this->set_in_redis($key, $value, $expiration);
                break;
                
            case 'apcu':
                $result = $this->set_in_apcu($key, $value, $expiration);
                break;
                
            case 'object_cache':
                $result = wp_cache_set($key, $value, self::CACHE_GROUP, $expiration);
                break;
                
            case 'transient':
                $result = set_transient($key, $value, $expiration);
                break;
        }
        
        if ($result) {
            $this->stats['writes']++;
            $this->log_debug('Cache set', ['key' => $key, 'expiration' => $expiration]);
        }
        
        return $result;
    }
    
    /**
     * Delete cached value
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool {
        $key = $this->prepare_key($key);
        $result = false;
        
        switch ($this->backend) {
            case 'redis':
                $result = $this->delete_from_redis($key);
                break;
                
            case 'apcu':
                $result = apcu_delete($key);
                break;
                
            case 'object_cache':
                $result = wp_cache_delete($key, self::CACHE_GROUP);
                break;
                
            case 'transient':
                $result = delete_transient($key);
                break;
        }
        
        if ($result) {
            $this->stats['deletes']++;
            $this->log_debug('Cache deleted', ['key' => $key]);
        }
        
        return $result;
    }
    
    /**
     * Clear all plugin caches
     *
     * @return bool
     */
    public function flush(): bool {
        $result = false;
        
        switch ($this->backend) {
            case 'redis':
                $result = $this->flush_redis();
                break;
                
            case 'apcu':
                $result = $this->flush_apcu();
                break;
                
            case 'object_cache':
                $result = wp_cache_flush_group(self::CACHE_GROUP);
                break;
                
            case 'transient':
                $result = $this->flush_transients();
                break;
        }
        
        $this->log_info('Cache flushed', ['backend' => $this->backend, 'success' => $result]);
        
        return $result;
    }
    
    /**
     * Remember value with callback
     *
     * @param string $key
     * @param callable $callback
     * @param int $expiration
     * @return mixed
     */
    public function remember(string $key, callable $callback, int $expiration = 0) {
        $value = $this->get($key);
        
        if ($value === false) {
            $value = $callback();
            $this->set($key, $value, $expiration);
        }
        
        return $value;
    }
    
    /**
     * Get or set multiple values
     *
     * @param array $keys
     * @return array
     */
    public function get_multiple(array $keys): array {
        $results = [];
        
        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }
        
        return $results;
    }
    
    /**
     * Set multiple values
     *
     * @param array $values
     * @param int $expiration
     * @return bool
     */
    public function set_multiple(array $values, int $expiration = 0): bool {
        $success = true;
        
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $expiration)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Delete multiple values
     *
     * @param array $keys
     * @return bool
     */
    public function delete_multiple(array $keys): bool {
        $success = true;
        
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Increment numeric value
     *
     * @param string $key
     * @param int $offset
     * @return int|false
     */
    public function increment(string $key, int $offset = 1) {
        $key = $this->prepare_key($key);
        
        switch ($this->backend) {
            case 'redis':
                $redis = $this->get_redis_connection();
                if ($redis) {
                    return $redis->incrBy($key, $offset);
                }
                break;
                
            case 'apcu':
                $success = false;
                $value = apcu_inc($key, $offset, $success);
                return $success ? $value : false;
                
            default:
                $value = $this->get($key, 0);
                $new_value = $value + $offset;
                return $this->set($key, $new_value) ? $new_value : false;
        }
        
        return false;
    }
    
    /**
     * Decrement numeric value
     *
     * @param string $key
     * @param int $offset
     * @return int|false
     */
    public function decrement(string $key, int $offset = 1) {
        return $this->increment($key, -$offset);
    }
    
    /**
     * Get cache statistics
     *
     * @return array
     */
    public function get_stats(): array {
        $stats = $this->stats;
        $stats['backend'] = $this->backend;
        $stats['hit_rate'] = $this->calculate_hit_rate();
        
        // Add backend-specific stats
        switch ($this->backend) {
            case 'apcu':
                $stats['apcu_info'] = apcu_cache_info();
                break;
                
            case 'redis':
                $redis = $this->get_redis_connection();
                if ($redis) {
                    $stats['redis_info'] = $redis->info();
                }
                break;
        }
        
        return $stats;
    }
    
    /**
     * Warm common templates cache
     */
    protected function warm_common_templates(): void {
        $templates = $this->get_common_templates();
        foreach ($templates as $key => $template) {
            $this->set('template_' . $key, $template, DAY_IN_SECONDS);
        }
    }
    
    /**
     * Warm business categories cache
     */
    protected function warm_business_categories(): void {
        $categories = $this->get_business_categories();
        $this->set('business_categories', $categories, WEEK_IN_SECONDS);
    }
    
    /**
     * Clear expired cache entries
     */
    public function clear_expired(): void {
        switch ($this->backend) {
            case 'transient':
                global $wpdb;
                $wpdb->query(
                    "DELETE FROM {$wpdb->options} 
                     WHERE option_name LIKE '_transient_timeout_%' 
                     AND option_value < " . time()
                );
                break;
                
            case 'apcu':
                // APCu handles expiration automatically
                break;
                
            case 'redis':
                // Redis handles expiration automatically
                break;
        }
        
        $this->log_info('Cleared expired cache entries');
    }
    
    /**
     * Prepare cache key
     *
     * @param string $key
     * @return string
     */
    protected function prepare_key(string $key): string {
        // Add prefix and sanitize
        $key = $this->key_prefix . preg_replace('/[^a-z0-9_\-]/i', '_', $key);
        
        // Ensure key length is within limits
        if (strlen($key) > 172) { // WordPress transient limit
            $key = substr($key, 0, 150) . '_' . md5($key);
        }
        
        return $key;
    }
    
    /**
     * Get from Redis
     *
     * @param string $key
     * @return mixed
     */
    protected function get_from_redis(string $key) {
        $redis = $this->get_redis_connection();
        if (!$redis) {
            return false;
        }
        
        try {
            return $redis->get($key);
        } catch (Exception $e) {
            $this->log_error('Redis get error', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Set in Redis
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return bool
     */
    protected function set_in_redis(string $key, $value, int $expiration): bool {
        $redis = $this->get_redis_connection();
        if (!$redis) {
            return false;
        }
        
        try {
            return $redis->setex($key, $expiration, $value);
        } catch (Exception $e) {
            $this->log_error('Redis set error', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Delete from Redis
     *
     * @param string $key
     * @return bool
     */
    protected function delete_from_redis(string $key): bool {
        $redis = $this->get_redis_connection();
        if (!$redis) {
            return false;
        }
        
        try {
            return $redis->del($key) > 0;
        } catch (Exception $e) {
            $this->log_error('Redis delete error', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Flush Redis keys
     *
     * @return bool
     */
    protected function flush_redis(): bool {
        $redis = $this->get_redis_connection();
        if (!$redis) {
            return false;
        }
        
        try {
            // Delete all keys with our prefix
            $keys = $redis->keys($this->key_prefix . '*');
            if (!empty($keys)) {
                return $redis->del($keys) > 0;
            }
            return true;
        } catch (Exception $e) {
            $this->log_error('Redis flush error', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get Redis connection
     *
     * @return Redis|null
     */
    protected function get_redis_connection(): ?Redis {
        static $redis = null;
        
        if ($redis === null && $this->has_redis) {
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                
                // Use database 1 for plugin cache
                $redis->select(1);
            } catch (Exception $e) {
                $this->log_error('Redis connection error', ['error' => $e->getMessage()]);
                $redis = false;
            }
        }
        
        return $redis ?: null;
    }
    
    /**
     * Get from APCu
     *
     * @param string $key
     * @return mixed
     */
    protected function get_from_apcu(string $key) {
        $success = false;
        $value = apcu_fetch($key, $success);
        return $success ? $value : false;
    }
    
    /**
     * Set in APCu
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return bool
     */
    protected function set_in_apcu(string $key, $value, int $expiration): bool {
        return apcu_store($key, $value, $expiration);
    }
    
    /**
     * Flush APCu cache
     *
     * @return bool
     */
    protected function flush_apcu(): bool {
        $info = apcu_cache_info();
        foreach ($info['cache_list'] as $entry) {
            if (strpos($entry['info'], $this->key_prefix) === 0) {
                apcu_delete($entry['info']);
            }
        }
        return true;
    }
    
    /**
     * Flush transients
     *
     * @return bool
     */
    protected function flush_transients(): bool {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 OR option_name LIKE %s",
                '_transient_' . $this->key_prefix . '%',
                '_transient_timeout_' . $this->key_prefix . '%'
            )
        );
        
        return true;
    }
    
    /**
     * Check if value is serialized
     *
     * @param mixed $data
     * @return bool
     */
    protected function is_serialized($data): bool {
        if (!is_string($data)) {
            return false;
        }
        
        $data = trim($data);
        
        if ('N;' === $data) {
            return true;
        }
        
        if (strlen($data) < 4) {
            return false;
        }
        
        if (':' !== $data[1]) {
            return false;
        }
        
        $lastc = substr($data, -1);
        if (';' !== $lastc && '}' !== $lastc) {
            return false;
        }
        
        $token = $data[0];
        switch ($token) {
            case 's':
                if ('"' !== substr($data, -2, 1)) {
                    return false;
                }
                // Intentional fall-through
            case 'a':
            case 'O':
                return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
            case 'b':
            case 'i':
            case 'd':
                return (bool) preg_match("/^{$token}:[0-9.E+-]+;$/", $data);
        }
        
        return false;
    }
    
    /**
     * Calculate cache hit rate
     *
     * @return float
     */
    protected function calculate_hit_rate(): float {
        $total = $this->stats['hits'] + $this->stats['misses'];
        
        if ($total === 0) {
            return 0.0;
        }
        
        return round(($this->stats['hits'] / $total) * 100, 2);
    }
    
    /**
     * Get common templates
     *
     * @return array
     */
    protected function get_common_templates(): array {
        global $wpdb;
        
        $templates = [];
        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}zippicks_prompt_templates 
             WHERE is_default = 1 
             ORDER BY created_at DESC",
            ARRAY_A
        );
        
        foreach ($results as $template) {
            $templates[$template['business_category']] = $template;
        }
        
        return $templates;
    }
    
    /**
     * Get business categories
     *
     * @return array
     */
    protected function get_business_categories(): array {
        return [
            'restaurant' => ['name' => 'Restaurant', 'pillars' => ['Food Quality', 'Service', 'Atmosphere', 'Value', 'Consistency', 'Uniqueness']],
            'hotel' => ['name' => 'Hotel', 'pillars' => ['Room Quality', 'Service', 'Amenities', 'Location', 'Cleanliness', 'Value']],
            'salon' => ['name' => 'Salon', 'pillars' => ['Service Quality', 'Expertise', 'Cleanliness', 'Atmosphere', 'Value', 'Products']],
            'gym' => ['name' => 'Gym', 'pillars' => ['Equipment', 'Cleanliness', 'Staff', 'Classes', 'Value', 'Community']],
            'spa' => ['name' => 'Spa', 'pillars' => ['Treatment Quality', 'Atmosphere', 'Staff', 'Cleanliness', 'Value', 'Amenities']],
            'bar' => ['name' => 'Bar', 'pillars' => ['Drink Quality', 'Atmosphere', 'Service', 'Value', 'Music/Entertainment', 'Crowd']]
        ];
    }
    
    /**
     * Warm recent lists cache
     */
    protected function warm_recent_lists(): void {
        $args = [
            'post_type' => 'master_critic_list',
            'posts_per_page' => 10,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $lists = get_posts($args);
        
        foreach ($lists as $list) {
            $cache_key = 'list_' . $list->ID;
            $list_data = [
                'id' => $list->ID,
                'title' => $list->post_title,
                'content' => $list->post_content,
                'meta' => get_post_meta($list->ID)
            ];
            
            $this->set($cache_key, $list_data, HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Save statistics
     */
    public function save_stats(): void {
        update_option('zippicks_cache_stats_' . date('Y-m-d'), $this->stats);
    }
    
    /**
     * Log debug message
     *
     * @param string $message
     * @param array $context
     */
    protected function log_debug(string $message, array $context = []): void {
        if ($this->logger && defined('WP_DEBUG') && WP_DEBUG) {
            $this->logger->debug($message, $context);
        }
    }
    
    /**
     * Log info message
     *
     * @param string $message
     * @param array $context
     */
    protected function log_info(string $message, array $context = []): void {
        if ($this->logger) {
            $this->logger->info($message, $context);
        }
    }
    
    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     */
    protected function log_error(string $message, array $context = []): void {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }
}