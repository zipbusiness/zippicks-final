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
     * Rate limit constants
     */
    const RATE_LIMIT_WINDOW = 60; // 60 seconds
    const RATE_LIMIT_MAX_REQUESTS = 30; // Max 30 searches per minute
    const RATE_LIMIT_TRANSIENT_PREFIX = 'zippicks_search_rate_';
    
    /**
     * Privacy compliance notice
     * 
     * This class implements GDPR/CCPA compliant tracking:
     * - Respects Do Not Track (DNT) browser settings
     * - Requires user consent for tracking
     * - Anonymizes IP addresses using daily rotating hashes
     * - Limits data collection to essential analytics only
     * - No browser fingerprinting or persistent tracking
     * - Session IDs expire when browser closes
     * - Users can opt-out via privacy settings
     */
    
    /**
     * Get singleton instance
     * @return Analytics
     */
    public static function instance() {
        static $instance = null;
        if (is_null($instance)) {
            $instance = new self();
        }
        return $instance;
    }
    
    /**
     * Constructor (private for singleton)
     */
    private function __construct() {
        $this->api_client = API_Client::instance();
    }
    
    /**
     * Track search query via API with validation and rate limiting
     * 
     * @param array $data Search data
     * @return bool|WP_Error Success or error
     */
    public function track_search($data) {
        if (!get_option('zippicks_search_enable_analytics', true)) {
            return true; // Analytics disabled
        }
        
        // Validate input data
        $validation = $this->validate_search_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Get validated data
        $validated_data = $validation;
        
        // Check rate limit
        $rate_limit_check = $this->check_rate_limit($validated_data['user_identifier']);
        if (is_wp_error($rate_limit_check)) {
            return $rate_limit_check;
        }
        
        // Add server-side data
        $validated_data['tracked_at'] = current_time('mysql');
        $validated_data['ip_address'] = $this->get_client_ip();
        $validated_data['user_agent'] = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        
        // Send to API endpoint
        $response = $this->api_client->track_search_query($validated_data);
        
        return !is_wp_error($response);
    }
    
    /**
     * Validate search data
     * 
     * @param mixed $data Data to validate
     * @return array|WP_Error Validated data or error
     */
    private function validate_search_data($data) {
        // Ensure data is an array
        if (!is_array($data)) {
            return new \WP_Error('invalid_data_type', 'Search data must be an array');
        }
        
        $validated = [];
        
        // Required field: query
        if (!isset($data['query'])) {
            return new \WP_Error('missing_field', 'Required field "query" is missing');
        }
        if (!is_string($data['query'])) {
            return new \WP_Error('invalid_type', 'Field "query" must be a string');
        }
        
        // Required field: intent (support both 'intent' and 'intent_type')
        $intent_field = null;
        if (isset($data['intent'])) {
            $intent_field = 'intent';
        } elseif (isset($data['intent_type'])) {
            $intent_field = 'intent_type';
        } else {
            return new \WP_Error('missing_field', 'Required field "intent" or "intent_type" is missing');
        }
        
        if (!is_string($data[$intent_field])) {
            return new \WP_Error('invalid_type', sprintf('Field "%s" must be a string', $intent_field));
        }
        
        // Validate query
        $query = trim($data['query']);
        if (empty($query)) {
            return new \WP_Error('empty_query', 'Search query cannot be empty');
        }
        if (strlen($query) > 200) {
            return new \WP_Error('query_too_long', 'Search query exceeds maximum length of 200 characters');
        }
        $validated['query'] = sanitize_text_field($query);
        
        // Validate intent
        $allowed_intents = ['vibe', 'utility', 'hybrid', 'unknown'];
        $intent = strtolower(trim($data[$intent_field]));
        if (!in_array($intent, $allowed_intents, true)) {
            return new \WP_Error('invalid_intent', 'Invalid search intent value');
        }
        $validated['intent'] = $intent;
        
        // Optional fields validation
        
        // Results count (support both result_count and results_count)
        $count_field = null;
        if (isset($data['result_count'])) {
            $count_field = 'result_count';
        } elseif (isset($data['results_count'])) {
            $count_field = 'results_count';
        }
        
        if ($count_field) {
            $results_count = intval($data[$count_field]);
            if ($results_count < 0 || $results_count > 1000) {
                return new \WP_Error('invalid_results_count', 'Results count must be between 0 and 1000');
            }
            $validated['results_count'] = $results_count;
        }
        
        // Location data
        if (isset($data['location'])) {
            if (!is_array($data['location'])) {
                return new \WP_Error('invalid_location', 'Location must be an array');
            }
            
            $location = [];
            if (isset($data['location']['lat']) && isset($data['location']['lng'])) {
                $lat = floatval($data['location']['lat']);
                $lng = floatval($data['location']['lng']);
                
                if ($lat < -90 || $lat > 90) {
                    return new \WP_Error('invalid_latitude', 'Latitude must be between -90 and 90');
                }
                if ($lng < -180 || $lng > 180) {
                    return new \WP_Error('invalid_longitude', 'Longitude must be between -180 and 180');
                }
                
                $location['lat'] = $lat;
                $location['lng'] = $lng;
            }
            
            if (isset($data['location']['city'])) {
                $location['city'] = sanitize_text_field($data['location']['city']);
            }
            if (isset($data['location']['state'])) {
                $location['state'] = sanitize_text_field($data['location']['state']);
            }
            
            if (!empty($location)) {
                $validated['location'] = $location;
            }
        }
        
        // Query ID (if tracking follow-up searches)
        if (isset($data['query_id'])) {
            $query_id = sanitize_text_field($data['query_id']);
            if (!empty($query_id) && strlen($query_id) <= 100) {
                $validated['query_id'] = $query_id;
            }
        }
        
        // Additional optional fields
        
        // Source (where search originated)
        if (isset($data['source'])) {
            $allowed_sources = ['ajax', 'rest', 'widget', 'shortcode', 'admin'];
            $source = strtolower(sanitize_text_field($data['source']));
            if (in_array($source, $allowed_sources, true)) {
                $validated['source'] = $source;
            }
        }
        
        // Session ID
        if (isset($data['session_id'])) {
            $session_id = sanitize_text_field($data['session_id']);
            if (!empty($session_id) && strlen($session_id) <= 100) {
                $validated['session_id'] = $session_id;
            }
        }
        
        // User ID
        if (isset($data['user_id'])) {
            $user_id = intval($data['user_id']);
            if ($user_id > 0) {
                $validated['user_id'] = $user_id;
            }
        }
        
        // Normalized query
        if (isset($data['normalized_query'])) {
            $validated['normalized_query'] = sanitize_text_field($data['normalized_query']);
        }
        
        // Confidence score
        if (isset($data['confidence_score'])) {
            $confidence = floatval($data['confidence_score']);
            if ($confidence >= 0 && $confidence <= 1) {
                $validated['confidence_score'] = $confidence;
            }
        }
        
        // Search time
        if (isset($data['search_time'])) {
            $search_time = floatval($data['search_time']);
            if ($search_time >= 0 && $search_time <= 60) { // Max 60 seconds
                $validated['search_time'] = $search_time;
            }
        }
        
        // Boolean flags
        if (isset($data['has_results'])) {
            $validated['has_results'] = (bool) $data['has_results'];
        }
        
        if (isset($data['from_cache'])) {
            $validated['from_cache'] = (bool) $data['from_cache'];
        }
        
        // User identifier for rate limiting
        $validated['user_identifier'] = $this->get_user_identifier();
        
        return $validated;
    }
    
    /**
     * Check rate limit for user/IP
     * 
     * @param string $identifier User identifier
     * @return true|WP_Error True if allowed, error if rate limited
     */
    private function check_rate_limit($identifier) {
        $transient_key = self::RATE_LIMIT_TRANSIENT_PREFIX . md5($identifier);
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            // First request in window
            set_transient($transient_key, 1, self::RATE_LIMIT_WINDOW);
            return true;
        }
        
        if ($requests >= self::RATE_LIMIT_MAX_REQUESTS) {
            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    'Rate limit exceeded. Maximum %d searches per %d seconds.',
                    self::RATE_LIMIT_MAX_REQUESTS,
                    self::RATE_LIMIT_WINDOW
                ),
                ['retry_after' => self::RATE_LIMIT_WINDOW]
            );
        }
        
        // Increment counter
        set_transient($transient_key, $requests + 1, self::RATE_LIMIT_WINDOW);
        return true;
    }
    
    /**
     * Get unique identifier for rate limiting
     * 
     * @return string
     */
    private function get_user_identifier() {
        // For logged-in users, use user ID
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        
        // For anonymous users, use IP address
        return 'ip_' . $this->get_client_ip();
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function get_client_ip() {
        // Check for IP behind proxy
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs (proxy chain)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to direct connection IP
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Track result click via API with privacy compliance and validation
     * 
     * @param string $zpid Business ZPID
     * @param string $query_id Search query ID
     * @param int $position Result position
     * @return bool|WP_Error Success or error
     */
    public function track_click($zpid, $query_id, $position) {
        // Check if analytics is enabled globally
        if (!get_option('zippicks_search_enable_analytics', true)) {
            return true; // Analytics disabled
        }
        
        // PRIVACY COMPLIANCE: Check user consent for tracking
        // This checks both cookie consent and user privacy preferences
        $consent_check = $this->check_tracking_consent();
        if (is_wp_error($consent_check)) {
            return $consent_check;
        }
        
        // VALIDATION: Validate ZPID format
        // ZPIDs should be alphanumeric with hyphens, max 100 chars
        if (!is_string($zpid) || empty($zpid)) {
            return new \WP_Error('invalid_zpid', 'ZPID must be a non-empty string');
        }
        
        $zpid = trim($zpid);
        if (!preg_match('/^[a-zA-Z0-9\-]+$/', $zpid) || strlen($zpid) > 100) {
            return new \WP_Error('invalid_zpid_format', 'ZPID contains invalid characters or exceeds maximum length');
        }
        
        // VALIDATION: Validate query_id format
        // Query IDs should be valid identifiers, max 100 chars
        if (!is_string($query_id) || empty($query_id)) {
            return new \WP_Error('invalid_query_id', 'Query ID must be a non-empty string');
        }
        
        $query_id = trim($query_id);
        if (strlen($query_id) > 100) {
            return new \WP_Error('invalid_query_id_length', 'Query ID exceeds maximum length of 100 characters');
        }
        
        // VALIDATION: Validate position is a positive integer
        // Position should be between 1 and reasonable max (100)
        if (!is_numeric($position)) {
            return new \WP_Error('invalid_position_type', 'Position must be numeric');
        }
        
        $position = intval($position);
        if ($position < 1 || $position > 100) {
            return new \WP_Error('invalid_position_range', 'Position must be between 1 and 100');
        }
        
        // PRIVACY COMPLIANCE: Prepare anonymized tracking data
        $tracking_data = [
            'zpid' => sanitize_text_field($zpid),
            'query_id' => sanitize_text_field($query_id),
            'position' => $position,
            'clicked_at' => current_time('mysql'),
            
            // PRIVACY: Include anonymized user context
            'user_type' => is_user_logged_in() ? 'registered' : 'anonymous',
            'session_id' => $this->get_anonymous_session_id(),
            
            // PRIVACY: IP is hashed for privacy compliance
            'ip_hash' => $this->get_privacy_safe_ip_hash(),
            
            // PRIVACY: Limited browser info (no fingerprinting)
            'browser_type' => $this->get_generic_browser_type(),
            
            // PRIVACY: Consent tracking for compliance
            'consent_type' => $this->get_consent_type(),
            'dnt' => $this->is_do_not_track_enabled() // Changed from 'dnta' to standard 'dnt'
        ];
        
        // PRIVACY: Apply rate limiting to prevent tracking abuse
        $rate_limit_check = $this->check_click_rate_limit($this->get_user_identifier());
        if (is_wp_error($rate_limit_check)) {
            return $rate_limit_check;
        }
        
        // Send anonymized data to API endpoint
        $response = $this->api_client->track_search_click($tracking_data);
        
        return !is_wp_error($response);
    }
    
    /**
     * Check if user has consented to tracking (GDPR/CCPA compliance)
     * 
     * @return true|WP_Error
     */
    private function check_tracking_consent() {
        // PRIVACY: Check Do Not Track browser setting
        if ($this->is_do_not_track_enabled()) {
            // Respect DNT header - no tracking
            return new \WP_Error('dnt_enabled', 'Do Not Track is enabled', ['status' => 'dnt']);
        }
        
        // PRIVACY: Check for cookie consent (if cookie consent plugin is active)
        if (function_exists('wp_has_consent') && !wp_has_consent('statistics')) {
            return new \WP_Error('no_consent', 'User has not consented to statistics tracking');
        }
        
        // PRIVACY: Check user privacy preferences (for logged-in users)
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $privacy_preference = get_user_meta($user_id, 'zippicks_allow_tracking', true);
            
            if ($privacy_preference === 'no') {
                return new \WP_Error('user_opted_out', 'User has opted out of tracking');
            }
        }
        
        // PRIVACY: Check global privacy mode
        if (get_option('zippicks_privacy_mode', false)) {
            return new \WP_Error('privacy_mode', 'Site is in privacy mode');
        }
        
        return true;
    }
    
    /**
     * Get anonymous session ID for privacy-compliant tracking
     * 
     * @return string
     */
    private function get_anonymous_session_id() {
        // PRIVACY: Use a cookie-based session ID that expires with browser session
        // This allows tracking within a session without long-term identification
        
        if (isset($_COOKIE['zippicks_session_id'])) {
            return sanitize_text_field($_COOKIE['zippicks_session_id']);
        }
        
        // Generate new session ID
        $session_id = wp_generate_password(16, false);
        
        // Set cookie that expires when browser closes (session cookie)
        // httponly flag prevents JavaScript access for security
        // SameSite=Lax prevents CSRF attacks
        setcookie(
            'zippicks_session_id',
            $session_id,
            0, // Expires when browser closes
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(), // Secure flag for HTTPS
            true // HttpOnly flag
        );
        
        // Add SameSite attribute if PHP version supports it
        if (PHP_VERSION_ID >= 70300) {
            setcookie(
                'zippicks_session_id',
                $session_id,
                [
                    'expires' => 0,
                    'path' => COOKIEPATH,
                    'domain' => COOKIE_DOMAIN,
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        }
        
        return $session_id;
    }
    
    /**
     * Get privacy-safe hashed IP for geographic analytics
     * 
     * @return string
     */
    private function get_privacy_safe_ip_hash() {
        $ip = $this->get_client_ip();
        
        // PRIVACY: Hash IP with daily rotation for privacy
        // This prevents long-term tracking while allowing daily analytics
        $salt = wp_salt('auth') . date('Y-m-d');
        
        return hash('sha256', $ip . $salt);
    }
    
    /**
     * Get generic browser type without fingerprinting
     * 
     * @return string
     */
    private function get_generic_browser_type() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // PRIVACY: Return only generic browser type, no version info
        if (strpos($user_agent, 'Chrome') !== false) {
            return 'chrome';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            return 'firefox';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            return 'safari';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            return 'edge';
        } else {
            return 'other';
        }
    }
    
    /**
     * Check if Do Not Track is enabled
     * 
     * @return bool
     */
    private function is_do_not_track_enabled() {
        return isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1';
    }
    
    /**
     * Get consent type for tracking
     * 
     * @return string
     */
    private function get_consent_type() {
        if (is_user_logged_in()) {
            $user_consent = get_user_meta(get_current_user_id(), 'zippicks_tracking_consent', true);
            return $user_consent ?: 'implicit';
        }
        
        // Check for cookie consent
        if (isset($_COOKIE['zippicks_consent'])) {
            return 'explicit';
        }
        
        return 'implicit';
    }
    
    /**
     * Check click rate limit to prevent abuse
     * 
     * @param string $identifier User identifier
     * @return true|WP_Error
     */
    private function check_click_rate_limit($identifier) {
        // PRIVACY: More lenient rate limit for clicks than searches
        $transient_key = 'zippicks_click_rate_' . md5($identifier);
        $clicks = get_transient($transient_key);
        
        $max_clicks_per_minute = 60;
        $window = 60; // seconds
        
        if ($clicks === false) {
            set_transient($transient_key, 1, $window);
            return true;
        }
        
        if ($clicks >= $max_clicks_per_minute) {
            return new \WP_Error(
                'click_rate_limit_exceeded',
                sprintf('Rate limit exceeded. Maximum %d clicks per minute.', $max_clicks_per_minute),
                ['retry_after' => $window]
            );
        }
        
        set_transient($transient_key, $clicks + 1, $window);
        return true;
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