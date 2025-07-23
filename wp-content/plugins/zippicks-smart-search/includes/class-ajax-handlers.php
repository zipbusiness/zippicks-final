<?php
/**
 * AJAX Handlers
 * 
 * Handles AJAX requests for search functionality
 * Provides fallback for non-REST API requests
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Ajax_Handlers {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Public AJAX handlers
        add_action('wp_ajax_zippicks_search', [$this, 'handle_search']);
        add_action('wp_ajax_nopriv_zippicks_search', [$this, 'handle_search']);
        
        add_action('wp_ajax_zippicks_autocomplete', [$this, 'handle_autocomplete']);
        add_action('wp_ajax_nopriv_zippicks_autocomplete', [$this, 'handle_autocomplete']);
        
        add_action('wp_ajax_zippicks_track_click', [$this, 'handle_track_click']);
        add_action('wp_ajax_nopriv_zippicks_track_click', [$this, 'handle_track_click']);
        
        add_action('wp_ajax_zippicks_notify_coming_soon', [$this, 'handle_notify_coming_soon']);
        add_action('wp_ajax_nopriv_zippicks_notify_coming_soon', [$this, 'handle_notify_coming_soon']);
        
        // Admin AJAX handlers
        add_action('wp_ajax_zippicks_clear_search_cache', [$this, 'handle_clear_cache']);
        add_action('wp_ajax_zippicks_get_search_stats', [$this, 'handle_get_stats']);
    }
    
    /**
     * Handle search AJAX request
     */
    public function handle_search() {
        // Verify nonce
        if (!check_ajax_referer('zippicks_search_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'zippicks-smart-search')]);
        }
        
        // Check rate limit
        $rate_limit = Rate_Limiter::check('search');
        if (isset($rate_limit['error'])) {
            wp_send_json_error([
                'message' => $rate_limit['message'],
                'retry_after' => $rate_limit['retry_after']
            ], 429);
        }
        
        // Get and validate parameters
        $query = isset($_POST['q']) ? sanitize_text_field($_POST['q']) : '';
        if (empty($query)) {
            wp_send_json_error(['message' => __('Search query is required', 'zippicks-smart-search')]);
        }
        
        $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
        $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
        $radius = isset($_POST['radius']) ? intval($_POST['radius']) : 10;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
        
        // Validate coordinates
        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            wp_send_json_error(['message' => __('Invalid latitude', 'zippicks-smart-search')]);
        }
        if ($lng !== null && ($lng < -180 || $lng > 180)) {
            wp_send_json_error(['message' => __('Invalid longitude', 'zippicks-smart-search')]);
        }
        
        // Get location
        $location = $this->get_location($lat, $lng);
        
        // Initialize cache
        $cache = Cache_Manager::instance();
        
        // Check cache first
        $cache_params = ['radius' => $radius, 'limit' => $limit];
        $cached_results = $cache->get_search_results($query, $location, $cache_params);
        
        if ($cached_results !== false) {
            wp_send_json_success([
                'results' => $cached_results,
                'cached' => true
            ]);
        }
        
        // Perform search
        $search_engine = new Search_Engine();
        $results = $search_engine->search($query, $location, [
            'radius' => $radius,
            'limit' => $limit
        ]);
        
        if (is_wp_error($results)) {
            wp_send_json_error([
                'message' => $results->get_error_message(),
                'code' => $results->get_error_code()
            ]);
        }
        
        // Cache results
        if (isset($results['intent'])) {
            $cache->set_search_results($query, $location, $cache_params, $results, $results['intent']);
        }
        
        // Track search
        $this->track_search($query, $location, $results);
        
        wp_send_json_success([
            'results' => $results,
            'cached' => false
        ]);
    }
    
    /**
     * Handle autocomplete AJAX request
     */
    public function handle_autocomplete() {
        // No nonce check for autocomplete (performance)
        
        // Check rate limit
        $rate_limit = Rate_Limiter::check('autocomplete');
        if (isset($rate_limit['error'])) {
            wp_send_json_error([
                'message' => $rate_limit['message'],
                'retry_after' => $rate_limit['retry_after']
            ], 429);
        }
        
        $prefix = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        if (strlen($prefix) < 2) {
            wp_send_json_success(['suggestions' => []]);
        }
        
        $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
        $lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
        
        // Get location
        $location = $this->get_location($lat, $lng);
        
        // Check cache
        $cache = Cache_Manager::instance();
        $cached_suggestions = $cache->get_autocomplete($prefix, $location);
        
        if ($cached_suggestions !== false) {
            wp_send_json_success(['suggestions' => $cached_suggestions]);
        }
        
        // Get suggestions from API
        $api_client = new API_Client();
        $suggestions = $api_client->get_autocomplete($prefix, $location);
        
        if (is_wp_error($suggestions)) {
            wp_send_json_success(['suggestions' => []]); // Don't error on autocomplete
        }
        
        // Process suggestions
        $processed_suggestions = $this->process_autocomplete_suggestions($suggestions, $prefix);
        
        // Cache results
        $cache->set_autocomplete($prefix, $location, $processed_suggestions);
        
        wp_send_json_success(['suggestions' => $processed_suggestions]);
    }
    
    /**
     * Handle click tracking AJAX request
     */
    public function handle_track_click() {
        // Verify nonce
        if (!check_ajax_referer('zippicks_search_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'zippicks-smart-search')]);
        }
        
        // Check rate limit
        $rate_limit = Rate_Limiter::check('track');
        if (isset($rate_limit['error'])) {
            wp_send_json_error([
                'message' => $rate_limit['message'],
                'retry_after' => $rate_limit['retry_after']
            ], 429);
        }
        
        $zpid = isset($_POST['zpid']) ? sanitize_text_field($_POST['zpid']) : '';
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $position = isset($_POST['position']) ? intval($_POST['position']) : 0;
        
        if (empty($zpid) || empty($query)) {
            wp_send_json_error(['message' => __('Missing required parameters', 'zippicks-smart-search')]);
        }
        
        // Track click
        $analytics = new Analytics();
        $result = $analytics->track_click($zpid, $query, $position);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success();
    }
    
    /**
     * Handle coming soon notification AJAX request
     */
    public function handle_notify_coming_soon() {
        // Verify nonce
        if (!check_ajax_referer('zippicks_search_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'zippicks-smart-search')]);
        }
        
        // Check rate limit
        $rate_limit = Rate_Limiter::check('notify');
        if (isset($rate_limit['error'])) {
            wp_send_json_error([
                'message' => $rate_limit['message'],
                'retry_after' => $rate_limit['retry_after']
            ], 429);
        }
        
        $zpid = isset($_POST['zpid']) ? sanitize_text_field($_POST['zpid']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($zpid)) {
            wp_send_json_error(['message' => __('Business ID is required', 'zippicks-smart-search')]);
        }
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => __('Valid email is required', 'zippicks-smart-search')]);
        }
        
        // Track demand
        $demand_tracker = new Demand_Tracker();
        $result = $demand_tracker->track_demand($zpid, $email);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        // Send to API
        $api_client = new API_Client();
        $api_result = $api_client->track_coming_soon($zpid, $email);
        
        if (is_wp_error($api_result)) {
            // Log but don't fail
            error_log('Failed to notify API of coming soon request: ' . $api_result->get_error_message());
        }
        
        wp_send_json_success(['message' => __('You will be notified when this business is added!', 'zippicks-smart-search')]);
    }
    
    /**
     * Handle cache clear request (admin only)
     */
    public function handle_clear_cache() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'zippicks-smart-search')]);
        }
        
        // Verify nonce
        if (!check_ajax_referer('zippicks_search_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'zippicks-smart-search')]);
        }
        
        $cache = Cache_Manager::instance();
        $result = $cache->clear_all();
        
        if ($result) {
            wp_send_json_success(['message' => __('Cache cleared successfully', 'zippicks-smart-search')]);
        } else {
            wp_send_json_error(['message' => __('Failed to clear cache. Cache group flushing may not be supported.', 'zippicks-smart-search')]);
        }
    }
    
    /**
     * Handle get stats request (admin only)
     */
    public function handle_get_stats() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'zippicks-smart-search')]);
        }
        
        // Verify nonce
        if (!check_ajax_referer('zippicks_search_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'zippicks-smart-search')]);
        }
        
        // Get analytics stats
        $analytics = new Analytics();
        $stats = $analytics->get_stats();
        
        if (is_wp_error($stats)) {
            wp_send_json_error(['message' => $stats->get_error_message()]);
        }
        
        // Get cache stats
        $cache = Cache_Manager::instance();
        $cache_stats = $cache->get_stats();
        
        wp_send_json_success([
            'analytics' => $stats,
            'cache' => $cache_stats
        ]);
    }
    
    /**
     * Get location data
     * 
     * @param float|null $lat Latitude
     * @param float|null $lng Longitude
     * @return array
     */
    private function get_location($lat = null, $lng = null) {
        // If coordinates provided, use them
        if ($lat !== null && $lng !== null) {
            return [
                'lat' => $lat,
                'lng' => $lng,
                'source' => 'user_provided'
            ];
        }
        
        // Try to get from Geo Service plugin
        if (class_exists('\\ZipPicks\\Geo\\Location_Detector')) {
            try {
                $detector = new \ZipPicks\Geo\Location_Detector();
                $location = $detector->get_user_location(get_current_user_id());
                
                if ($location && isset($location['latitude']) && isset($location['longitude'])) {
                    return [
                        'lat' => floatval($location['latitude']),
                        'lng' => floatval($location['longitude']),
                        'city' => $location['city'] ?? null,
                        'state' => $location['state'] ?? null,
                        'source' => $location['source'] ?? 'geo_service'
                    ];
                }
            } catch (\Exception $e) {
                error_log('Geo Service error: ' . $e->getMessage());
            }
        }
        
        // Fallback to default location
        return [
            'lat' => 34.0522,
            'lng' => -118.2437,
            'city' => 'Los Angeles',
            'state' => 'CA',
            'source' => 'default'
        ];
    }
    
    /**
     * Process autocomplete suggestions
     * 
     * @param array $suggestions Raw suggestions
     * @param string $prefix Search prefix
     * @return array
     */
    private function process_autocomplete_suggestions($suggestions, $prefix) {
        $processed = [];
        
        // Add search query as first suggestion
        $processed[] = [
            'type' => 'query',
            'value' => $prefix,
            'label' => sprintf(__('Search for "%s"', 'zippicks-smart-search'), $prefix),
            'icon' => 'search'
        ];
        
        // Process business suggestions
        if (!empty($suggestions['businesses'])) {
            foreach (array_slice($suggestions['businesses'], 0, 5) as $business) {
                $processed[] = [
                    'type' => 'business',
                    'value' => $business['name'],
                    'label' => $business['name'],
                    'zpid' => $business['zpid'],
                    'icon' => 'location',
                    'meta' => $business['city'] ?? ''
                ];
            }
        }
        
        // Process vibe suggestions
        if (!empty($suggestions['vibes'])) {
            foreach (array_slice($suggestions['vibes'], 0, 3) as $vibe) {
                $processed[] = [
                    'type' => 'vibe',
                    'value' => $vibe,
                    'label' => sprintf(__('Vibe: %s', 'zippicks-smart-search'), $vibe),
                    'icon' => 'vibe'
                ];
            }
        }
        
        return $processed;
    }
    
    /**
     * Track search query
     * 
     * @param string $query Search query
     * @param array $location Location data
     * @param array $results Search results
     */
    private function track_search($query, $location, $results) {
        try {
            $analytics = new Analytics();
            $analytics->track_search([
                'query' => $query,
                'location' => $location,
                'result_count' => isset($results['results']) ? count($results['results']) : 0,
                'intent' => $results['intent'] ?? 'unknown',
                'has_results' => !empty($results['results']),
                'source' => 'ajax'
            ]);
        } catch (\Exception $e) {
            error_log('Failed to track search: ' . $e->getMessage());
        }
    }
}