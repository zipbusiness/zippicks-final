<?php
/**
 * AJAX Handlers Class
 * 
 * Handles all AJAX requests from the frontend for tracking
 * user interactions and managing the Taste Graph integration
 * 
 * @package TasteGraphConnector
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TGC_Ajax_Handlers class
 */
class TGC_Ajax_Handlers {
    
    /**
     * Register AJAX handlers
     */
    public function register_handlers() {
        // Public handlers (available to non-logged-in users)
        add_action('wp_ajax_nopriv_tgc_track_interaction', array($this, 'track_interaction'));
        add_action('wp_ajax_tgc_track_interaction', array($this, 'track_interaction'));
        
        add_action('wp_ajax_nopriv_tgc_sync_session', array($this, 'sync_session'));
        add_action('wp_ajax_tgc_sync_session', array($this, 'sync_session'));
        
        // Authenticated handlers
        add_action('wp_ajax_tgc_get_taste_profile', array($this, 'get_taste_profile'));
        add_action('wp_ajax_tgc_get_recommendations', array($this, 'get_recommendations'));
        
        // Admin handlers
        add_action('wp_ajax_tgc_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_tgc_process_queue_manual', array($this, 'process_queue_manual'));
    }
    
    /**
     * Track user interaction
     */
    public function track_interaction() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error('Security check failed', 403);
        }
        
        // Get and validate data
        $data = isset($_POST['data']) ? $_POST['data'] : array();
        if (!is_array($data)) {
            wp_send_json_error('Invalid data format', 400);
        }
        
        // Required fields
        $required_fields = array('interaction_type');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                wp_send_json_error("Missing required field: {$field}", 400);
            }
        }
        
        // Sanitize data
        $interaction_data = array(
            'interaction_type' => sanitize_text_field($data['interaction_type']),
            'session_id' => isset($data['session_id']) ? sanitize_text_field($data['session_id']) : null,
            'restaurant_zpid' => isset($data['restaurant_zpid']) ? sanitize_text_field($data['restaurant_zpid']) : null,
            'vibe_id' => isset($data['vibe_id']) ? absint($data['vibe_id']) : null,
            'metadata' => array()
        );
        
        // Add WordPress user ID if logged in
        if (is_user_logged_in()) {
            $interaction_data['wp_user_id'] = get_current_user_id();
        }
        
        // Validate interaction type
        $valid_types = array(
            'page_view',
            'restaurant_view',
            'restaurant_click',
            'restaurant_save',
            'restaurant_unsave',
            'restaurant_share',
            'vibe_view',
            'vibe_click',
            'vibe_select',
            'search',
            'filter_apply',
            'map_interaction',
            'list_scroll',
            'time_on_page'
        );
        
        if (!in_array($interaction_data['interaction_type'], $valid_types)) {
            wp_send_json_error('Invalid interaction type', 400);
        }
        
        // Process metadata
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $allowed_metadata = array(
                'page_type',
                'search_query',
                'filter_values',
                'scroll_depth',
                'time_spent',
                'referrer',
                'device_type',
                'viewport_width',
                'viewport_height'
            );
            
            foreach ($allowed_metadata as $key) {
                if (isset($data['metadata'][$key])) {
                    $interaction_data['metadata'][$key] = sanitize_text_field($data['metadata'][$key]);
                }
            }
        }
        
        // Add automatic metadata
        $interaction_data['metadata']['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? 
            sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $interaction_data['metadata']['ip_hash'] = $this->get_hashed_ip();
        
        // Send to API
        $api_client = new TGC_API_Client();
        $result = $api_client->track_interaction($interaction_data);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Interaction tracked successfully',
                'interaction_type' => $interaction_data['interaction_type']
            ));
        } else {
            // Don't fail the request if tracking fails (queued for retry)
            wp_send_json_success(array(
                'message' => 'Interaction queued for processing',
                'interaction_type' => $interaction_data['interaction_type']
            ));
        }
    }
    
    /**
     * Sync session ID
     */
    public function sync_session() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error('Security check failed', 403);
        }
        
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        
        if (!TGC_Session_Tracker::validate_session_id($session_id)) {
            wp_send_json_error('Invalid session ID', 400);
        }
        
        // Set cookie if not already set
        if (!isset($_COOKIE[TGC_Session_Tracker::COOKIE_NAME])) {
            setcookie(
                TGC_Session_Tracker::COOKIE_NAME,
                $session_id,
                time() + TGC_Session_Tracker::COOKIE_EXPIRY,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }
        
        // If user is logged in, link the session
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            TGC_Session_Tracker::link_session_to_user($user_id, $session_id);
            
            // Send to API
            $api_client = new TGC_API_Client();
            $api_client->link_session($session_id, $user_id);
        }
        
        wp_send_json_success(array(
            'session_id' => $session_id,
            'synced' => true
        ));
    }
    
    /**
     * Get user's taste profile
     */
    public function get_taste_profile() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error('Security check failed', 403);
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('Authentication required', 401);
        }
        
        $user_id = get_current_user_id();
        
        // Get from API
        $api_client = new TGC_API_Client();
        $profile = $api_client->get_taste_profile($user_id);
        
        if ($profile && isset($profile['wp_user_id'])) {
            wp_send_json_success($profile);
        } else {
            wp_send_json_error('Taste profile not found', 404);
        }
    }
    
    /**
     * Get personalized recommendations
     */
    public function get_recommendations() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error('Security check failed', 403);
        }
        
        // Get parameters
        $params = array(
            'lat' => isset($_POST['lat']) ? floatval($_POST['lat']) : null,
            'lng' => isset($_POST['lng']) ? floatval($_POST['lng']) : null,
            'radius' => isset($_POST['radius']) ? floatval($_POST['radius']) : 5.0,
            'limit' => isset($_POST['limit']) ? absint($_POST['limit']) : 10
        );
        
        // Validate location
        if (!$params['lat'] || !$params['lng']) {
            wp_send_json_error('Location required', 400);
        }
        
        // Get user ID if logged in
        $wp_user_id = is_user_logged_in() ? get_current_user_id() : null;
        
        // Get session ID
        $session_id = TGC_Session_Tracker::get_session_id();
        
        // TODO: This would need to be implemented in the API
        // For now, return a placeholder response
        wp_send_json_success(array(
            'recommendations' => array(),
            'message' => 'Recommendations endpoint not yet implemented',
            'params' => $params,
            'wp_user_id' => $wp_user_id,
            'session_id' => $session_id
        ));
    }
    
    /**
     * Test API connection (admin only)
     */
    public function test_api_connection() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error('Security check failed', 403);
        }
        
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $api_client = new TGC_API_Client();
        $health = $api_client->check_health();
        
        if ($health) {
            wp_send_json_success(array(
                'status' => 'connected',
                'message' => 'API connection successful',
                'api_url' => get_option('tgc_api_url', TGC_API_URL)
            ));
        } else {
            wp_send_json_error('API connection failed', 500);
        }
    }
    
    /**
     * Process queue manually (admin only)
     */
    public function process_queue_manual() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error('Security check failed', 403);
        }
        
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $queue_manager = new TGC_Queue_Manager();
        $results = $queue_manager->process_queue();
        
        wp_send_json_success(array(
            'message' => 'Queue processed',
            'results' => $results,
            'remaining' => $queue_manager->get_queue_count()
        ));
    }
    
    /**
     * Verify nonce
     * 
     * @return bool
     */
    private function verify_nonce() {
        return isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'tgc_ajax_nonce');
    }
    
    /**
     * Get hashed IP address for privacy
     * 
     * @return string
     */
    private function get_hashed_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Hash with salt for privacy
        return hash('sha256', $ip . wp_salt('nonce'));
    }
}