<?php
/**
 * Performance Optimizer for ZipPicks Smart Search
 * 
 * Handles query caching, CDN integration, and lazy loading
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Performance_Optimizer {
    
    /**
     * Instance
     * @var Performance_Optimizer
     */
    private static $instance = null;
    
    /**
     * Get instance
     * @return Performance_Optimizer
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Query caching
        add_filter('zippicks_search_cache_key', [$this, 'normalize_cache_key'], 10, 2);
        add_filter('zippicks_search_cache_ttl', [$this, 'optimize_cache_ttl'], 10, 2);
        
        // CDN support
        add_filter('zippicks_search_asset_url', [$this, 'apply_cdn_url']);
        add_filter('zippicks_search_enqueue_scripts', [$this, 'optimize_script_loading']);
        
        // Lazy loading
        add_filter('zippicks_search_results_per_page', [$this, 'optimize_results_limit']);
        add_filter('zippicks_search_results_html', [$this, 'add_lazy_loading_attributes'], 10, 2);
        
        // Resource hints
        add_action('wp_head', [$this, 'add_resource_hints'], 1);
        
        // Query optimization
        add_filter('zippicks_search_query_args', [$this, 'optimize_query_args'], 10, 2);
        
        // Prefetch popular searches
        add_action('init', [$this, 'schedule_cache_warming']);
    }
    
    /**
     * Normalize cache key for better hit rates
     * 
     * @param string $key Original cache key
     * @param string $query Search query
     * @return string
     */
    public function normalize_cache_key($key, $query) {
        // Normalize query: lowercase, trim, remove extra spaces
        $normalized_query = strtolower(trim(preg_replace('/\s+/', ' ', $query)));
        
        // Remove common stop words for better cache hits
        $stop_words = ['the', 'a', 'an', 'in', 'on', 'at', 'for', 'of', 'with'];
        $words = explode(' ', $normalized_query);
        $filtered_words = array_diff($words, $stop_words);
        
        // If we removed all words, keep the original
        if (empty($filtered_words)) {
            $filtered_words = $words;
        }
        
        $normalized_query = implode(' ', $filtered_words);
        
        // Generate consistent hash
        return 'zps_search_' . md5($normalized_query);
    }
    
    /**
     * Optimize cache TTL based on query type
     * 
     * @param int $ttl Original TTL
     * @param string $intent Query intent
     * @return int
     */
    public function optimize_cache_ttl($ttl, $intent) {
        $ttl_config = [
            'vibe' => 180,        // 3 minutes for vibe searches
            'utility' => 600,     // 10 minutes for utility searches
            'hybrid' => 300,      // 5 minutes for hybrid searches
            'business' => 900,    // 15 minutes for specific business searches
            'category' => 600,    // 10 minutes for category searches
            'default' => 300      // 5 minutes default
        ];
        
        return $ttl_config[$intent] ?? $ttl_config['default'];
    }
    
    /**
     * Apply CDN URL to assets
     * 
     * @param string $url Original URL
     * @return string
     */
    public function apply_cdn_url($url) {
        // Check if CDN is configured
        $cdn_url = defined('ZIPPICKS_CDN_URL') ? ZIPPICKS_CDN_URL : get_option('zippicks_cdn_url', '');
        
        if (empty($cdn_url)) {
            return $url;
        }
        
        // Only apply to static assets
        $static_extensions = ['js', 'css', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'woff', 'woff2'];
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        
        if (!in_array($extension, $static_extensions)) {
            return $url;
        }
        
        // Replace domain with CDN
        $site_url = site_url();
        return str_replace($site_url, rtrim($cdn_url, '/'), $url);
    }
    
    /**
     * Optimize script loading
     * 
     * @param array $scripts Script configuration
     * @return array
     */
    public function optimize_script_loading($scripts) {
        // Add async/defer attributes
        foreach ($scripts as &$script) {
            if (isset($script['handle'])) {
                // Defer non-critical scripts
                if (!in_array($script['handle'], ['zippicks-smart-search-critical'])) {
                    $script['defer'] = true;
                }
                
                // Add resource hints
                if (isset($script['src'])) {
                    $this->add_preload_hint($script['src'], 'script');
                }
            }
        }
        
        return $scripts;
    }
    
    /**
     * Optimize results limit based on device
     * 
     * @param int $limit Original limit
     * @return int
     */
    public function optimize_results_limit($limit) {
        // Mobile optimization
        if (wp_is_mobile()) {
            return min($limit, 10); // Max 10 results on mobile
        }
        
        // Check connection speed if available
        if (isset($_SERVER['HTTP_SAVE_DATA']) && $_SERVER['HTTP_SAVE_DATA'] === 'on') {
            return min($limit, 5); // Data saver mode
        }
        
        return $limit;
    }
    
    /**
     * Add lazy loading attributes to results
     * 
     * @param string $html Result HTML
     * @param array $result Result data
     * @return string
     */
    public function add_lazy_loading_attributes($html, $result) {
        // Add loading="lazy" to images
        $html = str_replace('<img ', '<img loading="lazy" ', $html);
        
        // Add data attributes for progressive enhancement
        if (isset($result['zpid'])) {
            $html = str_replace(
                'class="zippicks-result-card"',
                sprintf(
                    'class="zippicks-result-card" data-zpid="%s" data-lazy="true"',
                    esc_attr($result['zpid'])
                ),
                $html
            );
        }
        
        return $html;
    }
    
    /**
     * Add resource hints for performance
     */
    public function add_resource_hints() {
        // Only on pages with search
        if (!apply_filters('zippicks_search_load_assets', false)) {
            return;
        }
        
        // Preconnect to API domain
        $api_url = get_option('zippicks_api_url', 'https://api.zippicks.com');
        $api_domain = parse_url($api_url, PHP_URL_HOST);
        
        if ($api_domain) {
            echo sprintf(
                '<link rel="preconnect" href="https://%s" crossorigin>',
                esc_attr($api_domain)
            ) . "\n";
        }
        
        // DNS prefetch for CDN
        $cdn_url = defined('ZIPPICKS_CDN_URL') ? ZIPPICKS_CDN_URL : get_option('zippicks_cdn_url', '');
        if ($cdn_url) {
            $cdn_domain = parse_url($cdn_url, PHP_URL_HOST);
            echo sprintf(
                '<link rel="dns-prefetch" href="//%s">',
                esc_attr($cdn_domain)
            ) . "\n";
        }
        
        // Preload critical CSS
        $critical_css = ZIPPICKS_SEARCH_PLUGIN_URL . 'assets/css/search-critical.css';
        if (file_exists(ZIPPICKS_SEARCH_PLUGIN_DIR . 'assets/css/search-critical.css')) {
            echo sprintf(
                '<link rel="preload" href="%s" as="style">',
                esc_url($critical_css)
            ) . "\n";
        }
    }
    
    /**
     * Optimize query arguments
     * 
     * @param array $args Query arguments
     * @param string $context Query context
     * @return array
     */
    public function optimize_query_args($args, $context) {
        // Add field limiting for better performance
        if (!isset($args['fields'])) {
            $args['fields'] = ['zpid', 'name', 'city', 'state', 'category', 'thumbnail'];
        }
        
        // Optimize sorting
        if (isset($args['orderby']) && $args['orderby'] === 'relevance') {
            // Use optimized relevance sorting
            $args['meta_query_optimize'] = true;
        }
        
        // Add query hints
        $args['query_hints'] = [
            'cache_results' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];
        
        return $args;
    }
    
    /**
     * Schedule cache warming for popular searches
     */
    public function schedule_cache_warming() {
        if (!wp_next_scheduled('zippicks_warm_search_cache')) {
            wp_schedule_event(time(), 'hourly', 'zippicks_warm_search_cache');
        }
        
        add_action('zippicks_warm_search_cache', [$this, 'warm_cache']);
    }
    
    /**
     * Warm cache with popular searches
     */
    public function warm_cache() {
        // Get popular searches from analytics
        $analytics = new Analytics();
        $popular_searches = $analytics->get_popular_searches(10);
        
        if (empty($popular_searches)) {
            return;
        }
        
        $search_engine = new Search_Engine();
        $cache = Cache_Manager::instance();
        
        foreach ($popular_searches as $search_data) {
            $query = $search_data['query'] ?? '';
            $location = $search_data['location'] ?? $this->get_default_location();
            
            if (empty($query)) {
                continue;
            }
            
            // Check if already cached
            $cache_key = $cache->get_cache_key('search', [
                'query' => $query,
                'location' => $location,
                'radius' => 10,
                'limit' => 20
            ]);
            
            if ($cache->get($cache_key) !== false) {
                continue; // Already cached
            }
            
            // Perform search to warm cache
            $search_engine->search($query, $location, [
                'radius' => 10,
                'limit' => 20,
                'cache_warm' => true
            ]);
            
            // Small delay to avoid overwhelming the API
            usleep(100000); // 100ms
        }
    }
    
    /**
     * Get default location
     * 
     * @return array
     */
    private function get_default_location() {
        return [
            'lat' => 34.0522,
            'lng' => -118.2437,
            'city' => 'Los Angeles',
            'state' => 'CA'
        ];
    }
    
    /**
     * Add preload hint
     * 
     * @param string $url Resource URL
     * @param string $type Resource type
     */
    private function add_preload_hint($url, $type) {
        static $hints = [];
        
        $key = md5($url);
        if (!isset($hints[$key])) {
            $hints[$key] = true;
            
            add_action('wp_head', function() use ($url, $type) {
                echo sprintf(
                    '<link rel="preload" href="%s" as="%s">',
                    esc_url($url),
                    esc_attr($type)
                ) . "\n";
            }, 2);
        }
    }
    
    /**
     * Clear performance caches
     */
    public function clear_caches() {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear any CDN caches if configured
        do_action('zippicks_clear_cdn_cache');
        
        // Clear scheduled tasks
        wp_clear_scheduled_hook('zippicks_warm_search_cache');
    }
}