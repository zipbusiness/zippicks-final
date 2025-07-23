<?php
/**
 * Search Analytics
 * 
 * Sends analytics to PostgreSQL via API
 * NO LOCAL DATABASE OPERATIONS
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Analytics {
    
    /**
     * API client instance
     * @var API_Client
     */
    private $api_client;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new API_Client();
    }
    
    /**
     * Track search query via API
     * 
     * @param array $data Search data
     * @return bool Success
     */
    public function track_search($data) {
        if (!get_option('zippicks_search_enable_analytics', true)) {
            return true; // Analytics disabled
        }
        
        // Send to API endpoint (needs to be created in PostgreSQL API)
        $response = $this->api_client->track_search_query($data);
        
        return !is_wp_error($response);
    }
    
    /**
     * Track result click via API
     * 
     * @param string $zpid Business ZPID
     * @param string $query_id Search query ID
     * @param int $position Result position
     * @return bool Success
     */
    public function track_click($zpid, $query_id, $position) {
        // Send to API endpoint (needs to be created in PostgreSQL API)
        $response = $this->api_client->track_search_click([
            'zpid' => $zpid,
            'query_id' => $query_id,
            'position' => $position,
            'clicked_at' => current_time('mysql'),
        ]);
        
        return !is_wp_error($response);
    }
    
    /**
     * Get analytics data from API
     * 
     * @param array $params Query parameters
     * @return array|WP_Error
     */
    public function get_analytics($params = []) {
        return $this->api_client->get_search_analytics($params);
    }
}