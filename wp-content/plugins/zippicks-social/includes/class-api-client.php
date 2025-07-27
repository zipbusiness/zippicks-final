<?php
/**
 * ZipBusiness API Client for Social Features
 *
 * @package ZipPicks_Social
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_API_Client
 * 
 * Handles all communication with ZipBusiness PostgreSQL API
 * for social features including follows, activities, and suggestions
 */
class ZipPicks_Social_API_Client {
    
    /**
     * API base URL
     *
     * @var string
     */
    private $api_url;
    
    /**
     * API key for authentication
     *
     * @var string
     */
    private $api_key;
    
    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private $timeout = 30;
    
    /**
     * Cache manager instance
     *
     * @var ZipPicks_Social_Cache_Manager
     */
    private $cache;
    
    /**
     * Logger instance
     *
     * @var object|null
     */
    private $logger;
    
    /**
     * Singleton instance
     *
     * @var ZipPicks_Social_API_Client
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return ZipPicks_Social_API_Client
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->api_url = get_option('zippicks_api_url', 'https://api.zippicks.com');
        $this->api_key = get_option('zippicks_api_key', '');
        $this->cache = new ZipPicks_Social_Cache_Manager();
        
        // Use Foundation logger if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $this->logger = zippicks()->get('logger');
        }
    }
    
    /**
     * Make API request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    private function request($method, $endpoint, $data = [], $headers = []) {
        $url = trailingslashit($this->api_url) . ltrim($endpoint, '/');
        
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => array_merge([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-API-Key' => $this->api_key,
                'X-Client' => 'zippicks-social/' . ZIPPICKS_SOCIAL_VERSION,
                'X-WordPress-Site' => home_url()
            ], $headers)
        ];
        
        // Add user authentication if logged in
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $args['headers']['X-WP-User-ID'] = $user->ID;
            $args['headers']['X-WP-User-Email'] = $user->user_email;
            
            // Generate JWT token for user authentication
            $args['headers']['Authorization'] = 'Bearer ' . $this->generate_user_token($user->ID);
        }
        
        // Add body for POST/PUT requests
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        // Add query params for GET requests
        if ($method === 'GET' && !empty($data)) {
            $url = add_query_arg($data, $url);
        }
        
        // Log request if logger available
        if ($this->logger) {
            $this->logger->debug('Social API request', [
                'method' => $method,
                'endpoint' => $endpoint,
                'user_id' => get_current_user_id()
            ]);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            if ($this->logger) {
                $this->logger->error('Social API request failed', [
                    'error' => $response->get_error_message(),
                    'endpoint' => $endpoint
                ]);
            }
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        if ($status_code >= 200 && $status_code < 300) {
            return $decoded ?: [];
        }
        
        $error_message = isset($decoded['detail']) ? $decoded['detail'] : 'API request failed';
        return new WP_Error('api_error', $error_message, ['status' => $status_code]);
    }
    
    /**
     * Generate JWT token for user authentication
     *
     * @param int $user_id
     * @return string
     */
    private function generate_user_token($user_id) {
        $secret = wp_salt('auth');
        $timestamp = time();
        $data = [
            'user_id' => $user_id,
            'timestamp' => $timestamp,
            'site' => home_url()
        ];
        
        return base64_encode(json_encode($data) . '.' . hash_hmac('sha256', json_encode($data), $secret));
    }
    
    // ===========================
    // Follow System Endpoints
    // ===========================
    
    /**
     * Follow an entity
     *
     * @param int $follower_id
     * @param int $followed_id
     * @param string $followed_type
     * @return array|WP_Error
     */
    public function follow($follower_id, $followed_id, $followed_type) {
        return $this->request('POST', '/api/v1/social/follow', [
            'follower_id' => $follower_id,
            'followed_id' => $followed_id,
            'followed_type' => $followed_type
        ]);
    }
    
    /**
     * Unfollow an entity
     *
     * @param int $follower_id
     * @param int $followed_id
     * @param string $followed_type
     * @return array|WP_Error
     */
    public function unfollow($follower_id, $followed_id, $followed_type) {
        return $this->request('POST', '/api/v1/social/unfollow', [
            'follower_id' => $follower_id,
            'followed_id' => $followed_id,
            'followed_type' => $followed_type
        ]);
    }
    
    /**
     * Check if following
     *
     * @param int $follower_id
     * @param int $followed_id
     * @param string $followed_type
     * @return array|WP_Error
     */
    public function is_following($follower_id, $followed_id, $followed_type) {
        $cache_key = "following_{$follower_id}_{$followed_id}_{$followed_type}";
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $result = $this->request('GET', '/api/v1/social/is-following', [
            'follower_id' => $follower_id,
            'followed_id' => $followed_id,
            'followed_type' => $followed_type
        ]);
        
        if (!is_wp_error($result)) {
            $this->cache->set($cache_key, $result, 300); // Cache for 5 minutes
        }
        
        return $result;
    }
    
    /**
     * Get followers
     *
     * @param int $entity_id
     * @param string $entity_type
     * @param array $params
     * @return array|WP_Error
     */
    public function get_followers($entity_id, $entity_type, $params = []) {
        $defaults = [
            'limit' => 20,
            'offset' => 0
        ];
        
        $params = array_merge($defaults, $params);
        $params['entity_id'] = $entity_id;
        $params['entity_type'] = $entity_type;
        
        return $this->request('GET', '/api/v1/social/followers', $params);
    }
    
    /**
     * Get following
     *
     * @param int $user_id
     * @param array $params
     * @return array|WP_Error
     */
    public function get_following($user_id, $params = []) {
        $defaults = [
            'limit' => 20,
            'offset' => 0
        ];
        
        $params = array_merge($defaults, $params);
        $params['user_id'] = $user_id;
        
        return $this->request('GET', '/api/v1/social/following', $params);
    }
    
    /**
     * Get follow statistics
     *
     * @param int $entity_id
     * @param string $entity_type
     * @return array|WP_Error
     */
    public function get_stats($entity_id, $entity_type) {
        $cache_key = "stats_{$entity_id}_{$entity_type}";
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $result = $this->request('GET', "/api/v1/social/stats/{$entity_type}/{$entity_id}");
        
        if (!is_wp_error($result)) {
            $this->cache->set($cache_key, $result, 600); // Cache for 10 minutes
        }
        
        return $result;
    }
    
    // ===========================
    // Activity Feed Endpoints
    // ===========================
    
    /**
     * Get activity feed
     *
     * @param int $user_id
     * @param array $params
     * @return array|WP_Error
     */
    public function get_activity_feed($user_id, $params = []) {
        $defaults = [
            'limit' => 20,
            'offset' => 0,
            'include_self' => true
        ];
        
        $params = array_merge($defaults, $params);
        $params['user_id'] = $user_id;
        
        return $this->request('GET', '/api/v1/social/activity-feed', $params);
    }
    
    /**
     * Record an activity
     *
     * @param array $activity_data
     * @return array|WP_Error
     */
    public function record_activity($activity_data) {
        return $this->request('POST', '/api/v1/social/activities', $activity_data);
    }
    
    // ===========================
    // Suggestion Endpoints
    // ===========================
    
    /**
     * Get follow suggestions
     *
     * @param int $user_id
     * @param array $params
     * @return array|WP_Error
     */
    public function get_suggestions($user_id, $params = []) {
        $defaults = [
            'limit' => 10,
            'type' => 'all' // all, users, critics, businesses
        ];
        
        $params = array_merge($defaults, $params);
        $params['user_id'] = $user_id;
        
        // Try cache first
        $cache_key = "suggestions_{$user_id}_{$params['type']}_{$params['limit']}";
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $result = $this->request('GET', '/api/v1/social/suggestions', $params);
        
        if (!is_wp_error($result)) {
            $this->cache->set($cache_key, $result, 3600); // Cache for 1 hour
        }
        
        return $result;
    }
    
    /**
     * Dismiss a suggestion
     *
     * @param int $user_id
     * @param int $suggested_id
     * @param string $suggested_type
     * @return array|WP_Error
     */
    public function dismiss_suggestion($user_id, $suggested_id, $suggested_type) {
        return $this->request('POST', '/api/v1/social/suggestions/dismiss', [
            'user_id' => $user_id,
            'suggested_id' => $suggested_id,
            'suggested_type' => $suggested_type
        ]);
    }
    
    // ===========================
    // Taste Graph Integration
    // ===========================
    
    /**
     * Get taste overlap between users
     *
     * @param int $user_a_id
     * @param int $user_b_id
     * @return array|WP_Error
     */
    public function get_taste_overlap($user_a_id, $user_b_id) {
        return $this->request('GET', '/api/v1/social/taste-overlap', [
            'user_a' => $user_a_id,
            'user_b' => $user_b_id
        ]);
    }
    
    /**
     * Get mutual connections
     *
     * @param int $user_id
     * @param int $target_id
     * @param string $target_type
     * @return array|WP_Error
     */
    public function get_mutual_connections($user_id, $target_id, $target_type) {
        return $this->request('GET', '/api/v1/social/mutual-connections', [
            'user_id' => $user_id,
            'target_id' => $target_id,
            'target_type' => $target_type
        ]);
    }
    
    // ===========================
    // Privacy & Blocking
    // ===========================
    
    /**
     * Block a user
     *
     * @param int $blocker_id
     * @param int $blocked_id
     * @param string $reason
     * @return array|WP_Error
     */
    public function block_user($blocker_id, $blocked_id, $reason = '') {
        return $this->request('POST', '/api/v1/social/block', [
            'blocker_id' => $blocker_id,
            'blocked_id' => $blocked_id,
            'reason' => $reason
        ]);
    }
    
    /**
     * Unblock a user
     *
     * @param int $blocker_id
     * @param int $blocked_id
     * @return array|WP_Error
     */
    public function unblock_user($blocker_id, $blocked_id) {
        return $this->request('POST', '/api/v1/social/unblock', [
            'blocker_id' => $blocker_id,
            'blocked_id' => $blocked_id
        ]);
    }
    
    /**
     * Update privacy settings
     *
     * @param int $user_id
     * @param array $settings
     * @return array|WP_Error
     */
    public function update_privacy_settings($user_id, $settings) {
        return $this->request('PUT', "/api/v1/social/privacy/{$user_id}", $settings);
    }
    
    // ===========================
    // Bulk Operations
    // ===========================
    
    /**
     * Bulk follow operation
     *
     * @param int $follower_id
     * @param array $entities Array of ['id' => x, 'type' => y]
     * @return array|WP_Error
     */
    public function bulk_follow($follower_id, $entities) {
        return $this->request('POST', '/api/v1/social/bulk-follow', [
            'follower_id' => $follower_id,
            'entities' => $entities
        ]);
    }
    
    /**
     * Clear all cache
     *
     * @return void
     */
    public function clear_cache() {
        $this->cache->flush();
    }
}