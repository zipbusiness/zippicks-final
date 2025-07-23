<?php
/**
 * API Client
 * 
 * Handles ALL data operations via PostgreSQL API
 * NO LOCAL DATABASE OPERATIONS
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class API_Client {
    
    /**
     * API base URL
     * @var string
     */
    private $api_url;
    
    /**
     * API key
     * @var string
     */
    private $api_key;
    
    /**
     * Request timeout
     * @var int
     */
    private $timeout;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_url = $this->get_api_url();
        $this->api_key = $this->get_api_key();
        $this->timeout = get_option('zippicks_search_api_timeout', 5);
    }
    
    /**
     * Search restaurants
     * 
     * @param array $params Search parameters
     * @return array|WP_Error
     */
    public function search_restaurants($params) {
        $endpoint = '/api/v1/restaurants';
        
        // Build query parameters
        $query_params = [];
        
        // Location-based search
        if (!empty($params['zip'])) {
            $query_params['zip'] = $params['zip'];
            $query_params['radius'] = $params['radius'] ?? 10;
        } elseif (!empty($params['lat']) && !empty($params['lng'])) {
            // If we have coordinates, we'll need to find nearest ZIP
            // This is a limitation of current API - may need enhancement
            $query_params['city'] = $params['city'] ?? '';
        }
        
        // Filters
        if (!empty($params['vibe'])) {
            $query_params['vibe'] = $params['vibe'];
        }
        
        if (!empty($params['cuisine'])) {
            $query_params['cuisine'] = $params['cuisine'];
        }
        
        if (!empty($params['min_confidence'])) {
            $query_params['min_confidence'] = $params['min_confidence'];
        }
        
        // Pagination
        $query_params['limit'] = $params['limit'] ?? 20;
        $query_params['offset'] = $params['offset'] ?? 0;
        
        // Include enhanced fields for better search results
        $query_params['include_enhanced'] = true;
        
        return $this->make_request('GET', $endpoint, $query_params);
    }
    
    /**
     * Get restaurant by ZPID
     * 
     * @param string $zpid
     * @return array|WP_Error
     */
    public function get_restaurant($zpid) {
        $endpoint = '/api/v1/restaurants/' . $zpid;
        
        return $this->make_request('GET', $endpoint, [
            'include_quality' => true,
            'include_enhanced' => true
        ]);
    }
    
    /**
     * Track search query (needs API endpoint)
     * 
     * @param array $data Search query data
     * @return array|WP_Error
     */
    public function track_search_query($data) {
        // TODO: This endpoint needs to be created in PostgreSQL API
        $endpoint = '/api/v1/search/track';
        return $this->make_request('POST', $endpoint, $data);
    }
    
    /**
     * Track search demand (needs API endpoint)
     * 
     * @param array $data Demand data
     * @return array|WP_Error
     */
    public function track_search_demand($data) {
        // TODO: This endpoint needs to be created in PostgreSQL API
        $endpoint = '/api/v1/search/demand';
        return $this->make_request('POST', $endpoint, $data);
    }
    
    /**
     * Track search click (needs API endpoint)
     * 
     * @param array $data Click data
     * @return array|WP_Error
     */
    public function track_search_click($data) {
        // TODO: This endpoint needs to be created in PostgreSQL API
        $endpoint = '/api/v1/search/click';
        return $this->make_request('POST', $endpoint, $data);
    }
    
    /**
     * Get search analytics (needs API endpoint)
     * 
     * @param array $params Query parameters
     * @return array|WP_Error
     */
    public function get_search_analytics($params = []) {
        // TODO: This endpoint needs to be created in PostgreSQL API
        $endpoint = '/api/v1/search/analytics';
        return $this->make_request('GET', $endpoint, $params);
    }
    
    /**
     * Get search demand analytics (needs API endpoint)
     * 
     * @param array $params Query parameters
     * @return array|WP_Error
     */
    public function get_search_demand_analytics($params = []) {
        // TODO: This endpoint needs to be created in PostgreSQL API
        $endpoint = '/api/v1/search/demand/analytics';
        return $this->make_request('GET', $endpoint, $params);
    }
    
    /**
     * Make API request
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return array|WP_Error
     */
    private function make_request($method, $endpoint, $params = []) {
        $url = $this->api_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'X-API-Key' => $this->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];
        
        if ($method === 'GET' && !empty($params)) {
            $url = add_query_arg($params, $url);
        } elseif ($method !== 'GET' && !empty($params)) {
            $args['body'] = json_encode($params);
        }
        
        // Log request for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ZipPicks Search API Request: ' . $method . ' ' . $url);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('ZipPicks Search API Error: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code >= 400) {
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['error']['message'] ?? 'API request failed';
            
            return new \WP_Error(
                'api_error_' . $response_code,
                $error_message,
                ['status' => $response_code, 'response' => $error_data]
            );
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'json_decode_error',
                'Failed to parse API response',
                ['response' => $response_body]
            );
        }
        
        return $data;
    }
    
    /**
     * Get API URL
     * 
     * @return string
     */
    private function get_api_url() {
        if (defined('ZIPPICKS_API_URL')) {
            return rtrim(ZIPPICKS_API_URL, '/');
        }
        
        return 'https://zipbusiness-api.onrender.com';
    }
    
    /**
     * Get API key
     * 
     * @return string
     */
    private function get_api_key() {
        // Check for constant first
        if (defined('ZIPPICKS_API_KEY')) {
            return ZIPPICKS_API_KEY;
        }
        
        // Check options
        $api_key = get_option('zippicks_api_key');
        if ($api_key) {
            return $api_key;
        }
        
        // Check for search-specific API key
        $search_api_key = get_option('zippicks_search_api_key');
        if ($search_api_key) {
            return $search_api_key;
        }
        
        error_log('ZipPicks Search: No API key configured');
        return '';
    }
    
    /**
     * Test API connection
     * 
     * @return bool
     */
    public function test_connection() {
        $result = $this->make_request('GET', '/health');
        
        if (is_wp_error($result)) {
            return false;
        }
        
        return isset($result['status']) && $result['status'] === 'healthy';
    }
    
    /**
     * Get API status
     * 
     * @return array
     */
    public function get_status() {
        $result = $this->make_request('GET', '/api/v1/status');
        
        if (is_wp_error($result)) {
            return [
                'connected' => false,
                'error' => $result->get_error_message()
            ];
        }
        
        return [
            'connected' => true,
            'data' => $result
        ];
    }
    
    /**
     * Check API connection
     * 
     * @return bool
     */
    public function check_connection() {
        return $this->test_connection();
    }
    
    /**
     * Get autocomplete suggestions
     * 
     * @param string $prefix Search prefix
     * @param array $location Location data
     * @return array|WP_Error
     */
    public function get_autocomplete($prefix, $location) {
        // For now, return mock data until API endpoint is created
        // TODO: Replace with actual API call when endpoint is available
        
        $suggestions = [
            'businesses' => [],
            'vibes' => [],
            'categories' => []
        ];
        
        // Mock vibe suggestions based on common prefixes
        $all_vibes = [
            'romantic', 'rooftop', 'rustic', 'retro',
            'cozy', 'chic', 'casual', 'craft',
            'trendy', 'traditional',
            'modern', 'minimalist',
            'vintage', 'vibrant',
            'intimate', 'industrial',
            'elegant', 'eclectic'
        ];
        
        $prefix_lower = strtolower($prefix);
        foreach ($all_vibes as $vibe) {
            if (strpos($vibe, $prefix_lower) === 0) {
                $suggestions['vibes'][] = $vibe;
            }
        }
        
        // Limit suggestions
        $suggestions['vibes'] = array_slice($suggestions['vibes'], 0, 5);
        
        return $suggestions;
    }
    
    /**
     * Track coming soon notification request
     * 
     * @param string $zpid Business ZPID
     * @param string $email User email
     * @return array|WP_Error
     */
    public function track_coming_soon($zpid, $email) {
        // TODO: This endpoint needs to be created in PostgreSQL API
        $endpoint = '/api/v1/search/notify';
        
        return $this->make_request('POST', $endpoint, [
            'zpid' => $zpid,
            'email' => $email,
            'timestamp' => current_time('mysql')
        ]);
    }
}