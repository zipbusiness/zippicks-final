<?php
/**
 * Geo API Client Class
 * 
 * Handles all communication with the ZipBusiness Geo API endpoints
 * for location detection, geocoding, and distance calculations
 * 
 * @package ZipPicks_Geo_Service
 * @since 1.0.0
 */

namespace ZipPicks\Geo;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Geo_API_Client class
 */
class Geo_API_Client {
    
    /**
     * API endpoints
     */
    const ENDPOINT_DETECT = '/wp/geo/detect';
    const ENDPOINT_UPDATE = '/wp/geo/update';
    const ENDPOINT_NEARBY = '/wp/geo/nearby';
    const ENDPOINT_DISTANCE = '/wp/geo/distance';
    const ENDPOINT_GEOCODE = '/wp/geo/geocode';
    
    /**
     * HTTP request timeout
     */
    private $timeout = 30;
    
    /**
     * API base URL
     */
    private $api_url;
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Use shared ZipPicks API configuration
        $this->api_url = $this->get_api_url();
        $this->api_key = $this->get_api_key();
    }
    
    /**
     * Get API URL from configuration
     * 
     * @return string
     */
    private function get_api_url() {
        // Check for shared ZipPicks API URL first
        if (defined('ZIPPICKS_API_URL')) {
            return trailingslashit(ZIPPICKS_API_URL);
        }
        
        // Fall back to Taste Graph Connector URL if available
        if (defined('TGC_API_URL')) {
            return trailingslashit(TGC_API_URL);
        }
        
        // Check database option
        $url = get_option('zippicks_api_url', '');
        if (!empty($url)) {
            return trailingslashit($url);
        }
        
        // Default to production URL
        return 'https://zipbusiness-api.onrender.com/';
    }
    
    /**
     * Get API key from configuration
     * 
     * @return string
     */
    private function get_api_key() {
        // Check for shared ZipPicks API key first
        if (defined('ZIPPICKS_API_KEY')) {
            return ZIPPICKS_API_KEY;
        }
        
        // Fall back to Taste Graph Connector key if available
        if (defined('TGC_API_KEY')) {
            return TGC_API_KEY;
        }
        
        // Check database option
        $key = get_option('zippicks_api_key', '');
        if (!empty($key)) {
            return $key;
        }
        
        // Try Taste Graph Connector option
        return get_option('tgc_api_key', '');
    }
    
    /**
     * Detect user location
     * 
     * @param string|null $session_id Optional session identifier
     * @return array|false Location data or false on failure
     */
    public function detect_location($session_id = null) {
        $headers = [];
        if ($session_id) {
            $headers['X-Session-ID'] = $session_id;
        }
        
        return $this->make_request(self::ENDPOINT_DETECT, 'GET', null, $headers);
    }
    
    /**
     * Update user location
     * 
     * @param float $latitude
     * @param float $longitude
     * @param array $additional_data Additional location data
     * @return array|false Response data or false on failure
     */
    public function update_location($latitude, $longitude, $additional_data = []) {
        $data = array_merge([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ], $additional_data);
        
        return $this->make_request(self::ENDPOINT_UPDATE, 'POST', $data);
    }
    
    /**
     * Find nearby restaurants
     * 
     * @param float $latitude
     * @param float $longitude
     * @param float $radius_miles
     * @param int $limit
     * @return array|false Response data or false on failure
     */
    public function find_nearby($latitude, $longitude, $radius_miles = 5, $limit = 20) {
        $data = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_miles' => $radius_miles,
            'limit' => min($limit, 100), // API max is 100
        ];
        
        return $this->make_request(self::ENDPOINT_NEARBY, 'POST', $data);
    }
    
    /**
     * Calculate distance between two points
     * 
     * @param array $from ['lat' => float, 'lng' => float]
     * @param array $to ['lat' => float, 'lng' => float]
     * @param string $unit 'miles' or 'km'
     * @return array|false Response data or false on failure
     */
    public function calculate_distance($from, $to, $unit = 'miles') {
        $data = [
            'from' => $from,
            'to' => $to,
            'unit' => $unit,
        ];
        
        return $this->make_request(self::ENDPOINT_DISTANCE, 'POST', $data);
    }
    
    /**
     * Geocode a ZIP code or address
     * 
     * @param string $input ZIP code or address
     * @param string $type 'zip' or 'address'
     * @return array|false Response data or false on failure
     */
    public function geocode($input, $type = 'zip') {
        $data = [
            'input' => $input,
            'type' => $type,
        ];
        
        return $this->make_request(self::ENDPOINT_GEOCODE, 'POST', $data);
    }
    
    /**
     * Make HTTP request to API
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array|null $data Request data
     * @param array $additional_headers Additional headers
     * @return array|false Response data or false on failure
     */
    private function make_request($endpoint, $method = 'POST', $data = null, $additional_headers = []) {
        $url = $this->api_url . ltrim($endpoint, '/');
        
        // Check API key
        if (empty($this->api_key)) {
            $this->log_error('No API key configured for Geo Service');
            return false;
        }
        
        // Prepare headers
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'X-API-Key' => $this->api_key,
            'User-Agent' => 'ZipPicks-Geo-Service/' . ZIPPICKS_GEO_VERSION,
        ], $additional_headers);
        
        // Prepare request args
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => $headers,
            'sslverify' => !$this->is_localhost(),
        ];
        
        if ($data !== null) {
            $args['body'] = json_encode($data);
        }
        
        // Make request
        $response = wp_remote_request($url, $args);
        
        // Handle errors
        if (is_wp_error($response)) {
            $this->log_error('API request failed: ' . $response->get_error_message());
            return false;
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Handle non-200 responses
        if ($response_code !== 200) {
            $this->log_error("API request failed with HTTP {$response_code}: {$response_body}");
            return false;
        }
        
        // Parse JSON response
        $parsed = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error('Invalid JSON response from API');
            return false;
        }
        
        return $parsed;
    }
    
    /**
     * Check if running on localhost
     * 
     * @return bool
     */
    private function is_localhost() {
        $host = parse_url(home_url(), PHP_URL_HOST);
        return in_array($host, ['localhost', '127.0.0.1', '::1']);
    }
    
    /**
     * Log error message
     * 
     * @param string $message Error message
     */
    private function log_error($message) {
        error_log('ZipPicks Geo API Error: ' . $message);
        
        // Also log to ZipPicks Foundation logger if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->error($message, ['context' => 'geo_api_client']);
        }
    }
    
    /**
     * Get last error message
     * 
     * @return string|null
     */
    public function get_last_error() {
        // TODO: Implement error tracking
        return null;
    }
}