<?php
namespace ZipPicks\Favorites\API;

/**
 * API Client for communicating with ZipPicks Postgres backend
 */
class Client {
    
    private $base_url;
    private $timeout = 30;
    private $api_key;
    
    public function __construct() {
        $this->base_url = get_option('zippicks_favorites_api_endpoint', 'https://api.zippicks.com/v1');
        $this->api_key = get_option('zippicks_api_key', '');
    }
    
    /**
     * Make GET request
     */
    public function get($endpoint, $params = []) {
        $url = $this->base_url . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $this->request('GET', $url);
    }
    
    /**
     * Make POST request
     */
    public function post($endpoint, $data = []) {
        $url = $this->base_url . $endpoint;
        return $this->request('POST', $url, $data);
    }
    
    /**
     * Make DELETE request
     */
    public function delete($endpoint) {
        $url = $this->base_url . $endpoint;
        return $this->request('DELETE', $url);
    }
    
    /**
     * Make PUT request
     */
    public function put($endpoint, $data = []) {
        $url = $this->base_url . $endpoint;
        return $this->request('PUT', $url, $data);
    }
    
    /**
     * Perform HTTP request
     */
    private function request($method, $url, $data = null) {
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => $this->get_headers(),
            'sslverify' => true
        ];
        
        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
            $args['headers']['Content-Type'] = 'application/json';
        }
        
        // Add authentication for user-specific requests
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $args['headers']['X-User-ID'] = $user->ID;
            $args['headers']['X-User-Email'] = $user->user_email;
            
            // Generate a temporary token for this request
            $args['headers']['X-Request-Token'] = $this->generate_request_token($user->ID);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new \Exception('API request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Handle non-200 responses
        if ($status_code < 200 || $status_code >= 300) {
            $error_data = json_decode($body, true);
            $error_message = $error_data['message'] ?? 'API request failed';
            throw new \Exception($error_message, $status_code);
        }
        
        return json_decode($body, true);
    }
    
    /**
     * Get request headers
     */
    private function get_headers() {
        $headers = [
            'Accept' => 'application/json',
            'X-API-Version' => '1.0',
            'X-Client' => 'zippicks-wordpress/' . ZIPPICKS_FAVORITES_VERSION
        ];
        
        if (!empty($this->api_key)) {
            $headers['Authorization'] = 'Bearer ' . $this->api_key;
        }
        
        return $headers;
    }
    
    /**
     * Generate temporary request token
     */
    private function generate_request_token($user_id) {
        $secret = wp_salt('auth');
        $timestamp = time();
        $data = $user_id . ':' . $timestamp;
        
        return base64_encode($data . ':' . hash_hmac('sha256', $data, $secret));
    }
    
    /**
     * Get user's favorites
     */
    public function get_user_favorites($user_id, $params = []) {
        return $this->get("/users/{$user_id}/favorites", $params);
    }
    
    /**
     * Add favorite
     */
    public function add_favorite($user_id, $business_id, $context = []) {
        return $this->post('/favorites', [
            'user_id' => $user_id,
            'business_id' => $business_id,
            'source' => $context['source'] ?? 'wordpress',
            'source_context' => $context
        ]);
    }
    
    /**
     * Remove favorite
     */
    public function remove_favorite($user_id, $favorite_id) {
        return $this->delete("/favorites/{$favorite_id}");
    }
    
    /**
     * Get favorites by location
     */
    public function get_favorites_by_location($user_id, $location_params) {
        return $this->get("/users/{$user_id}/favorites", $location_params);
    }
    
    /**
     * Get user's favorite cities
     */
    public function get_favorite_cities($user_id) {
        return $this->get("/users/{$user_id}/favorites/cities");
    }
    
    /**
     * Search favorites
     */
    public function search_favorites($user_id, $query, $params = []) {
        $params['q'] = $query;
        return $this->get("/users/{$user_id}/favorites/search", $params);
    }
    
    /**
     * Get nearby favorites
     */
    public function get_nearby_favorites($user_id, $lat, $lng, $radius = 5) {
        return $this->get("/users/{$user_id}/favorites/nearby", [
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius
        ]);
    }
    
    /**
     * Bulk operations
     */
    public function bulk_add_favorites($user_id, $business_ids) {
        return $this->post('/favorites/bulk', [
            'user_id' => $user_id,
            'business_ids' => $business_ids
        ]);
    }
    
    public function bulk_remove_favorites($user_id, $favorite_ids) {
        return $this->delete('/favorites/bulk', [
            'user_id' => $user_id,
            'favorite_ids' => $favorite_ids
        ]);
    }
}