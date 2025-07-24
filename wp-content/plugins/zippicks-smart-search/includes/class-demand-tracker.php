<?php
/**
 * Search Demand Tracker
 * 
 * Sends demand tracking to PostgreSQL via API
 * NO LOCAL DATABASE OPERATIONS
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Demand_Tracker {
    
    /**
     * API client instance
     * @var API_Client
     */
    private static $api_client;
    
    /**
     * Get API client instance
     * @return API_Client
     */
    private static function get_api_client() {
        if (!self::$api_client) {
            self::$api_client = API_Client::instance();
        }
        return self::$api_client;
    }
    
    /**
     * Track search demand via API
     * 
     * @param string $zpid Business ZPID
     * @param string $query Original search query
     * @param string $location Location context
     * @param int|null $user_id WordPress user ID
     * @param string|null $session_id Session identifier
     * @return bool|\WP_Error True on success, WP_Error on validation failure or false on API failure
     */
    public static function track_demand($zpid, $query, $location = null, $user_id = null, $session_id = null) {
        // Validate ZPID format
        if (!is_string($zpid) || empty($zpid)) {
            return new \WP_Error('invalid_zpid', 'ZPID must be a non-empty string');
        }
        
        $zpid = trim($zpid);
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $zpid) || strlen($zpid) > 100) {
            return new \WP_Error('invalid_zpid_format', 'ZPID contains invalid characters or exceeds maximum length');
        }
        
        // Validate and sanitize query
        if (!is_string($query) || empty(trim($query))) {
            return new \WP_Error('invalid_query', 'Query must be a non-empty string');
        }
        $query = sanitize_text_field(substr(trim($query), 0, 255));
        
        // Sanitize location
        if ($location !== null) {
            if (!is_string($location)) {
                return new \WP_Error('invalid_location', 'Location must be a string');
            }
            $location = sanitize_text_field(substr(trim($location), 0, 255));
            if (empty($location)) {
                $location = null;
            }
        }
        
        // Validate user_id
        if ($user_id !== null) {
            $user_id = absint($user_id);
            if ($user_id === 0) {
                $user_id = null;
            }
        }
        
        // Sanitize session_id
        if ($session_id !== null) {
            if (!is_string($session_id)) {
                return new \WP_Error('invalid_session_id', 'Session ID must be a string');
            }
            $session_id = sanitize_text_field(substr(trim($session_id), 0, 128));
            if (empty($session_id)) {
                $session_id = null;
            }
        }
        
        $api_client = self::get_api_client();
        
        // Send to API endpoint with validated data
        $response = $api_client->track_search_demand([
            'zpid' => $zpid,
            'query' => $query,
            'location' => $location,
            'wp_user_id' => $user_id,
            'session_id' => $session_id,
        ]);
        
        if (!is_wp_error($response)) {
            // Trigger action for other plugins to hook into
            do_action('zippicks_search_demand_tracked', $zpid, $query, $location);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get demand analytics from API
     * 
     * @param array $params Query parameters
     * @return array|WP_Error
     */
    public static function get_demand_analytics($params = []) {
        $api_client = self::get_api_client();
        
        // Get from API endpoint (needs to be created in PostgreSQL API)
        return $api_client->get_search_demand_analytics($params);
    }
}