<?php
/**
 * API Client Class
 * 
 * Handles all communication with the ZipBusiness FastAPI backend
 * including tracking, session linking, and taste profile retrieval
 * 
 * @package TasteGraphConnector
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TGC_API_Client class
 */
class TGC_API_Client {
    
    /**
     * API endpoints
     */
    const ENDPOINT_SYNC_USER = '/wp/sync-user';
    const ENDPOINT_TRACK = '/wp/track';
    const ENDPOINT_LINK_SESSION = '/wp/link-session';
    const ENDPOINT_TASTE_PROFILE = '/wp/taste-profile';
    const ENDPOINT_HEALTH = '/health';
    
    /**
     * HTTP request timeout
     */
    private $timeout;
    
    /**
     * API base URL
     */
    private $api_url;
    
    /**
     * Debug mode
     */
    private $debug_mode;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_url = trailingslashit(get_option('tgc_api_url', TGC_API_URL));
        $this->timeout = defined('TGC_API_TIMEOUT') ? TGC_API_TIMEOUT : 30;
        $this->debug_mode = get_option('tgc_debug_mode', 'no') === 'yes';
    }
    
    /**
     * Sync WordPress user with Taste Graph
     * 
     * @param int $wp_user_id WordPress user ID
     * @param array $additional_data Additional user data
     * @return array|false Response data or false on failure
     */
    public function sync_user($wp_user_id, $additional_data = array()) {
        $user = get_user_by('id', $wp_user_id);
        if (!$user) {
            return false;
        }
        
        // Generate JWT token
        $token = TGC_JWT_Handler::generate_token($wp_user_id);
        if (!$token) {
            $this->log_error('Failed to generate JWT token for user sync');
            return false;
        }
        
        $data = array_merge(array(
            'token' => $token,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'locale' => get_user_locale($wp_user_id),
            'registered_date' => $user->user_registered
        ), $additional_data);
        
        return $this->make_request(self::ENDPOINT_SYNC_USER, 'POST', $data);
    }
    
    /**
     * Track user interaction
     * 
     * @param array $interaction_data Interaction data
     * @return array|false Response data or false on failure
     */
    public function track_interaction($interaction_data) {
        // Validate required fields
        $required = array('interaction_type');
        foreach ($required as $field) {
            if (!isset($interaction_data[$field])) {
                $this->log_error("Missing required field: {$field}");
                return false;
            }
        }
        
        // Add session ID if not present
        if (!isset($interaction_data['session_id'])) {
            $interaction_data['session_id'] = TGC_Session_Tracker::get_session_id();
        }
        
        // Add WordPress user ID if logged in
        if (is_user_logged_in() && !isset($interaction_data['wp_user_id'])) {
            $interaction_data['wp_user_id'] = get_current_user_id();
        }
        
        // Add fingerprint hash if available
        if (isset($interaction_data['session_id'])) {
            $fingerprint_hash = TGC_Session_Tracker::get_fingerprint_hash($interaction_data['session_id']);
            if ($fingerprint_hash) {
                $interaction_data['fingerprint_hash'] = $fingerprint_hash;
            }
        }
        
        // Add metadata
        if (!isset($interaction_data['metadata'])) {
            $interaction_data['metadata'] = array();
        }
        
        $interaction_data['metadata'] = array_merge($interaction_data['metadata'], array(
            'source' => 'wordpress',
            'plugin_version' => TGC_VERSION,
            'wp_version' => get_bloginfo('version'),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'url' => home_url($_SERVER['REQUEST_URI'] ?? ''),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
        ));
        
        return $this->make_request(self::ENDPOINT_TRACK, 'POST', $interaction_data);
    }
    
    /**
     * Link anonymous session to WordPress user
     * 
     * @param string $session_id Session ID
     * @param int $wp_user_id WordPress user ID
     * @return array|false Response data or false on failure
     */
    public function link_session($session_id, $wp_user_id) {
        // Validate session ID
        if (!TGC_Session_Tracker::validate_session_id($session_id)) {
            $this->log_error('Invalid session ID format');
            return false;
        }
        
        // Validate user ID
        $wp_user_id = absint($wp_user_id);
        if (!$wp_user_id) {
            $this->log_error('Invalid WordPress user ID');
            return false;
        }
        
        $data = array(
            'session_id' => $session_id,
            'wp_user_id' => $wp_user_id
        );
        
        $response = $this->make_request(self::ENDPOINT_LINK_SESSION, 'POST', $data);
        
        if ($response && isset($response['status']) && $response['status'] === 'success') {
            // Update local tracking
            TGC_Session_Tracker::link_session_to_user($wp_user_id, $session_id);
            
            // Trigger user sync
            $this->sync_user($wp_user_id);
        }
        
        return $response;
    }
    
    /**
     * Get user's taste profile
     * 
     * @param int $wp_user_id WordPress user ID
     * @return array|false Taste profile data or false on failure
     */
    public function get_taste_profile($wp_user_id) {
        $wp_user_id = absint($wp_user_id);
        if (!$wp_user_id) {
            return false;
        }
        
        $endpoint = self::ENDPOINT_TASTE_PROFILE . '/' . $wp_user_id;
        
        // Try to get from cache first
        $cache_key = 'tgc_taste_profile_' . $wp_user_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $response = $this->make_request($endpoint, 'GET');
        
        if ($response && isset($response['wp_user_id'])) {
            // Cache for 1 hour
            set_transient($cache_key, $response, HOUR_IN_SECONDS);
        }
        
        return $response;
    }
    
    /**
     * Check API health
     * 
     * @return bool True if API is healthy
     */
    public function check_health() {
        $response = $this->make_request(self::ENDPOINT_HEALTH, 'GET', null, 5); // 5 second timeout
        return $response && isset($response['status']) && $response['status'] === 'healthy';
    }
    
    /**
     * Make HTTP request to API
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array|null $data Request data
     * @param int|null $timeout Custom timeout
     * @return array|false Response data or false on failure
     */
    private function make_request($endpoint, $method = 'POST', $data = null, $timeout = null) {
        $url = $this->api_url . ltrim($endpoint, '/');
        $timeout = $timeout ?: $this->timeout;
        
        // Get API key
        $api_key = get_option('tgc_api_key', '');
        if (empty($api_key) && defined('TGC_API_KEY')) {
            $api_key = TGC_API_KEY;
        }
        
        // Prepare headers
        $headers = array(
            'Content-Type' => 'application/json',
            'X-API-Key' => $api_key,
            'User-Agent' => 'TasteGraphConnector/' . TGC_VERSION
        );
        
        // Prepare request args
        $args = array(
            'method' => $method,
            'timeout' => $timeout,
            'headers' => $headers,
            'sslverify' => !$this->is_localhost()
        );
        
        if ($data !== null) {
            $args['body'] = json_encode($data);
        }
        
        // Log request in debug mode
        if ($this->debug_mode) {
            $this->log_debug("API Request: {$method} {$url}", $data);
        }
        
        // Make request
        $response = wp_remote_request($url, $args);
        
        // Handle errors
        if (is_wp_error($response)) {
            $this->handle_request_error($endpoint, $method, $data, $response->get_error_message());
            return false;
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Log response in debug mode
        if ($this->debug_mode) {
            $this->log_debug("API Response ({$response_code}): {$response_body}");
        }
        
        // Handle non-200 responses
        if ($response_code !== 200) {
            $this->handle_request_error($endpoint, $method, $data, "HTTP {$response_code}: {$response_body}");
            return false;
        }
        
        // Parse JSON response
        $parsed = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->handle_request_error($endpoint, $method, $data, 'Invalid JSON response');
            return false;
        }
        
        return $parsed;
    }
    
    /**
     * Handle request error
     * 
     * @param string $endpoint Endpoint
     * @param string $method HTTP method
     * @param array|null $data Request data
     * @param string $error Error message
     */
    private function handle_request_error($endpoint, $method, $data, $error) {
        $this->log_error("API request failed: {$error}");
        
        // Queue for retry if it's a tracking request
        if ($endpoint === self::ENDPOINT_TRACK && $data) {
            $queue_manager = new TGC_Queue_Manager();
            $queue_manager->queue_failed_call($endpoint, $method, $data);
        }
    }
    
    /**
     * Check if running on localhost
     * 
     * @return bool
     */
    private function is_localhost() {
        $host = parse_url(home_url(), PHP_URL_HOST);
        return in_array($host, array('localhost', '127.0.0.1', '::1'));
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Message
     * @param mixed $data Additional data
     */
    private function log_debug($message, $data = null) {
        if (!$this->debug_mode) {
            return;
        }
        
        $log_message = 'TGC API: ' . $message;
        if ($data !== null) {
            $log_message .= ' - ' . print_r($data, true);
        }
        
        error_log($log_message);
    }
    
    /**
     * Log error message
     * 
     * @param string $message Error message
     */
    private function log_error($message) {
        error_log('TGC API Error: ' . $message);
    }
}