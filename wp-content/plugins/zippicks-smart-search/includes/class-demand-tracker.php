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
            self::$api_client = new API_Client();
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
     * @return bool Success
     */
    public static function track_demand($zpid, $query, $location = null, $user_id = null, $session_id = null) {
        $api_client = self::get_api_client();
        
        // Send to API endpoint (needs to be created in PostgreSQL API)
        $response = $api_client->track_search_demand([
            'zpid' => $zpid,
            'query' => substr($query, 0, 255),
            'location' => $location,
            'wp_user_id' => $user_id ?: null,
            'session_id' => $session_id ?: null,
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