<?php
/**
 * ZipBusiness API Sync Service
 *
 * Handles synchronization between ZipPicks businesses and the ZipBusiness API.
 * Manages ZPID verification, data enrichment, and vibe synchronization.
 *
 * @package ZipPicks_Business
 * @since 1.0.0
 */
class ZipPicks_Business_API_Sync_Service {
    
    /**
     * Logger instance
     * @var object
     */
    private $logger = null;
    
    /**
     * Cache instance
     * @var object
     */
    private $cache = null;
    
    /**
     * API endpoint base URL
     * @var string
     */
    private $api_base_url = 'https://api.zipbusiness.com/v1';
    
    /**
     * API key
     * @var string
     */
    private $api_key = '';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get Foundation services if available
        if (function_exists('zippicks')) {
            if (zippicks()->has('logger')) {
                $this->logger = zippicks()->get('logger');
            }
            if (zippicks()->has('cache')) {
                $this->cache = zippicks()->get('cache');
            }
        }
        
        // Get API settings
        $settings = get_option('zippicks_business_settings', array());
        if (isset($settings['zipbusiness_api_key'])) {
            $this->api_key = $settings['zipbusiness_api_key'];
        }
        if (isset($settings['zipbusiness_api_url'])) {
            $this->api_base_url = $settings['zipbusiness_api_url'];
        }
    }
    
    /**
     * Sync business with API data
     *
     * @param int $post_id Business post ID
     * @param string $zpid Optional ZPID to use
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function sync_business($post_id, $zpid = null) {
        // Validate post ID
        if (!$post_id || get_post_type($post_id) !== 'zippicks_business') {
            return new WP_Error('invalid_post', 'Invalid business post ID');
        }
        
        // Get zpid if not provided
        if (!$zpid) {
            $zpid = get_post_meta($post_id, 'zpid', true);
        }
        
        if (!$zpid) {
            return new WP_Error('no_zpid', 'No ZPID found for this business');
        }
        
        // Check cache first
        $cache_key = 'zpbusiness_data_' . $zpid;
        if ($this->cache) {
            $cached_data = $this->cache->get($cache_key);
            if ($cached_data !== false) {
                $this->update_business_from_api_data($post_id, $cached_data);
                return true;
            }
        }
        
        // Fetch from API
        $api_data = $this->fetch_from_api($zpid);
        
        if (is_wp_error($api_data)) {
            if ($this->logger) {
                $this->logger->error('API sync failed', array(
                    'post_id' => $post_id,
                    'zpid' => $zpid,
                    'error' => $api_data->get_error_message()
                ));
            }
            return $api_data;
        }
        
        // Cache the data
        if ($this->cache) {
            $this->cache->set($cache_key, $api_data, 3600); // 1 hour cache
        }
        
        // Update business with API data
        $this->update_business_from_api_data($post_id, $api_data);
        
        // Log success
        if ($this->logger) {
            $this->logger->info('Business synced with API', array(
                'post_id' => $post_id,
                'zpid' => $zpid
            ));
        }
        
        return true;
    }
    
    /**
     * Fetch business data from API
     *
     * @param string $zpid ZipBusiness ID
     * @return array|WP_Error API data or error
     */
    private function fetch_from_api($zpid) {
        if (!$this->api_key) {
            return new WP_Error('no_api_key', 'ZipBusiness API key not configured');
        }
        
        $url = $this->api_base_url . '/businesses/' . $zpid;
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            return new WP_Error('api_error', 'API returned status ' . $status_code, array(
                'status' => $status_code,
                'body' => $body
            ));
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            return new WP_Error('invalid_response', 'Invalid JSON response from API');
        }
        
        return $data;
    }
    
    /**
     * Update business post with API data
     *
     * @param int $post_id Business post ID
     * @param array $api_data API response data
     */
    private function update_business_from_api_data($post_id, $api_data) {
        // Update basic meta fields
        update_post_meta($post_id, 'api_verified', true);
        update_post_meta($post_id, 'api_confidence_score', $api_data['confidence_score'] ?? 0);
        update_post_meta($post_id, 'api_enriched_data', json_encode($api_data));
        update_post_meta($post_id, 'last_api_sync', current_time('mysql'));
        
        // Update vibes if available
        if (!empty($api_data['vibe_attributes'])) {
            $this->sync_vibes($post_id, $api_data['vibe_attributes']);
        }
        
        // Update basic business fields
        $this->update_business_fields($post_id, $api_data);
        
        // Update post status if needed
        if ($api_data['confidence_score'] >= 0.8) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'verified'
            ));
        }
    }
    
    /**
     * Sync vibe associations for a business
     *
     * @param int $post_id Business post ID
     * @param array $api_vibes Vibe data from API
     */
    private function sync_vibes($post_id, $api_vibes) {
        global $wpdb;
        
        // Clear existing API vibes
        $wpdb->delete(
            ZipPicks_Business_Database::get_vibes_table(),
            array(
                'business_id' => $post_id,
                'source' => 'api'
            ),
            array('%d', '%s')
        );
        
        // Prepare vibes for display
        $display_vibes = array();
        
        // Insert new vibes
        foreach ($api_vibes as $vibe_slug => $confidence) {
            // Insert into database
            $wpdb->insert(
                ZipPicks_Business_Database::get_vibes_table(),
                array(
                    'business_id' => $post_id,
                    'vibe_slug' => $vibe_slug,
                    'confidence_score' => $confidence,
                    'source' => 'api',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%f', '%s', '%s')
            );
            
            // Get display name from vibe taxonomy if available
            $display_name = $this->get_vibe_display_name($vibe_slug);
            
            $display_vibes[] = array(
                'slug' => $vibe_slug,
                'display_name' => $display_name,
                'confidence' => $confidence
            );
        }
        
        // Cache for display
        update_post_meta($post_id, 'api_vibes', json_encode($display_vibes));
        
        // Also sync with WordPress taxonomy if it exists
        if (taxonomy_exists('vibes')) {
            $this->sync_vibe_taxonomy($post_id, $api_vibes);
        }
    }
    
    /**
     * Get vibe display name
     *
     * @param string $vibe_slug Vibe slug
     * @return string Display name
     */
    private function get_vibe_display_name($vibe_slug) {
        // Check if vibes taxonomy exists
        if (taxonomy_exists('vibes')) {
            $term = get_term_by('slug', $vibe_slug, 'vibes');
            if ($term) {
                return $term->name;
            }
        }
        
        // Fallback to formatting the slug
        return ucwords(str_replace(array('-', '_'), ' ', $vibe_slug));
    }
    
    /**
     * Sync vibes with WordPress taxonomy
     *
     * @param int $post_id Business post ID
     * @param array $api_vibes Vibe data from API
     */
    private function sync_vibe_taxonomy($post_id, $api_vibes) {
        // Get high confidence vibes (>= 0.6)
        $term_ids = array();
        
        foreach ($api_vibes as $vibe_slug => $confidence) {
            if ($confidence >= 0.6) {
                $term = get_term_by('slug', $vibe_slug, 'vibes');
                if ($term) {
                    $term_ids[] = (int) $term->term_id;
                }
            }
        }
        
        // Set the terms
        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, $term_ids, 'vibes', false);
        }
    }
    
    /**
     * Update business fields from API data
     *
     * @param int $post_id Business post ID
     * @param array $api_data API response data
     */
    private function update_business_fields($post_id, $api_data) {
        // Update address fields if available
        if (!empty($api_data['location'])) {
            update_post_meta($post_id, '_zp_address', $api_data['location']['address'] ?? '');
            update_post_meta($post_id, '_zp_city', $api_data['location']['city'] ?? '');
            update_post_meta($post_id, '_zp_state', $api_data['location']['state'] ?? '');
            update_post_meta($post_id, '_zp_zip', $api_data['location']['zip'] ?? '');
            update_post_meta($post_id, '_zp_latitude', $api_data['location']['latitude'] ?? '');
            update_post_meta($post_id, '_zp_longitude', $api_data['location']['longitude'] ?? '');
        }
        
        // Update contact fields
        if (!empty($api_data['contact'])) {
            update_post_meta($post_id, '_zp_phone', $api_data['contact']['phone'] ?? '');
            update_post_meta($post_id, '_zp_website', $api_data['contact']['website'] ?? '');
            update_post_meta($post_id, '_zp_email', $api_data['contact']['email'] ?? '');
        }
        
        // Update business hours
        if (!empty($api_data['hours'])) {
            update_post_meta($post_id, '_zp_business_hours', json_encode($api_data['hours']));
        }
        
        // Update price range
        if (!empty($api_data['price_range'])) {
            update_post_meta($post_id, '_zp_price_range', $api_data['price_range']);
        }
        
        // Update cuisine type if available
        if (!empty($api_data['cuisine_types'])) {
            update_post_meta($post_id, '_zp_cuisine_types', json_encode($api_data['cuisine_types']));
        }
    }
    
    /**
     * Batch sync multiple businesses
     *
     * @param array $post_ids Array of business post IDs
     * @return array Results array with success/error for each post
     */
    public function batch_sync_businesses($post_ids) {
        $results = array();
        
        foreach ($post_ids as $post_id) {
            $result = $this->sync_business($post_id);
            
            $results[$post_id] = array(
                'success' => !is_wp_error($result),
                'message' => is_wp_error($result) ? $result->get_error_message() : 'Synced successfully'
            );
        }
        
        return $results;
    }
    
    /**
     * Search for business by name and location
     *
     * @param string $name Business name
     * @param string $location Location (city, state, or address)
     * @return array|WP_Error Search results or error
     */
    public function search_business($name, $location) {
        if (!$this->api_key) {
            return new WP_Error('no_api_key', 'ZipBusiness API key not configured');
        }
        
        $url = add_query_arg(array(
            'name' => urlencode($name),
            'location' => urlencode($location),
            'limit' => 10
        ), $this->api_base_url . '/businesses/search');
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            return new WP_Error('api_error', 'Search API returned status ' . $status_code);
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            return new WP_Error('invalid_response', 'Invalid JSON response from search API');
        }
        
        return $data['results'] ?? array();
    }
    
    /**
     * Link existing business to ZPID
     *
     * @param int $post_id Business post ID
     * @param string $zpid ZipBusiness ID to link
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function link_zpid($post_id, $zpid) {
        // Validate post
        if (get_post_type($post_id) !== 'zippicks_business') {
            return new WP_Error('invalid_post', 'Not a business post');
        }
        
        // Update ZPID
        update_post_meta($post_id, 'zpid', $zpid);
        
        // Sync data from API
        return $this->sync_business($post_id, $zpid);
    }
}