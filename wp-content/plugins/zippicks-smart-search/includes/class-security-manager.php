<?php
/**
 * Security Manager for ZipPicks Smart Search
 * 
 * Handles input validation, output escaping, and security hardening
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Security_Manager {
    
    /**
     * Instance
     * @var Security_Manager
     */
    private static $instance = null;
    
    /**
     * Get instance
     * @return Security_Manager
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize security hooks
     */
    private function init_hooks() {
        // Add CSP headers for search pages
        add_action('send_headers', [$this, 'add_security_headers']);
        
        // Sanitize search results before output
        add_filter('zippicks_search_results', [$this, 'sanitize_search_results'], 10, 2);
        
        // Add nonce verification to all AJAX requests
        add_filter('wp_ajax_nopriv_zippicks_search', [$this, 'verify_ajax_nonce'], 1);
        add_filter('wp_ajax_zippicks_search', [$this, 'verify_ajax_nonce'], 1);
        
        // Validate API responses
        add_filter('zippicks_api_response', [$this, 'validate_api_response'], 10, 2);
        
        // Add security data to frontend
        add_filter('zippicks_search_localize_data', [$this, 'add_security_data']);
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (!$this->is_search_page()) {
            return;
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self' " . esc_url(rest_url()) . ";";
        
        header("Content-Security-Policy: $csp");
        
        // Other security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    
    /**
     * Sanitize search results before output
     * 
     * @param array $results Search results
     * @param string $context Output context
     * @return array
     */
    public function sanitize_search_results($results, $context = 'frontend') {
        if (!is_array($results)) {
            return [];
        }
        
        $sanitized = [];
        
        foreach ($results as $result) {
            $sanitized_result = [
                'zpid' => $this->sanitize_zpid($result['zpid'] ?? ''),
                'name' => sanitize_text_field($result['name'] ?? ''),
                'category' => sanitize_text_field($result['category'] ?? ''),
                'city' => sanitize_text_field($result['city'] ?? ''),
                'state' => sanitize_text_field($result['state'] ?? ''),
                'distance' => floatval($result['distance'] ?? 0),
                'exists' => (bool)($result['exists'] ?? true),
                'url' => esc_url($result['url'] ?? '')
            ];
            
            // Sanitize description if present
            if (isset($result['description'])) {
                $sanitized_result['description'] = wp_kses(
                    $result['description'],
                    ['p' => [], 'br' => [], 'strong' => [], 'em' => []]
                );
            }
            
            // Sanitize vibes array
            if (isset($result['vibes']) && is_array($result['vibes'])) {
                $sanitized_result['vibes'] = array_map('sanitize_text_field', $result['vibes']);
            }
            
            // Sanitize additional meta
            if (isset($result['meta']) && is_array($result['meta'])) {
                $sanitized_result['meta'] = $this->sanitize_meta($result['meta']);
            }
            
            $sanitized[] = $sanitized_result;
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize ZPID
     * 
     * @param string $zpid
     * @return string
     */
    private function sanitize_zpid($zpid) {
        // ZPIDs should only contain alphanumeric characters and hyphens
        return preg_replace('/[^a-zA-Z0-9\-]/', '', $zpid);
    }
    
    /**
     * Sanitize meta data
     * 
     * @param array $meta
     * @return array
     */
    private function sanitize_meta($meta) {
        $allowed_keys = ['rating', 'price_level', 'hours', 'phone', 'website'];
        $sanitized = [];
        
        foreach ($allowed_keys as $key) {
            if (isset($meta[$key])) {
                switch ($key) {
                    case 'rating':
                        $sanitized[$key] = floatval($meta[$key]);
                        break;
                    case 'price_level':
                        $sanitized[$key] = intval($meta[$key]);
                        break;
                    case 'phone':
                        $sanitized[$key] = sanitize_text_field($meta[$key]);
                        break;
                    case 'website':
                        $sanitized[$key] = esc_url($meta[$key]);
                        break;
                    default:
                        $sanitized[$key] = sanitize_text_field($meta[$key]);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Verify AJAX nonce
     */
    public function verify_ajax_nonce() {
        if (!check_ajax_referer('zippicks_search_nonce', 'nonce', false)) {
            wp_die('Security check failed', 403);
        }
    }
    
    /**
     * Validate API response
     * 
     * @param mixed $response API response
     * @param string $endpoint API endpoint
     * @return mixed
     */
    public function validate_api_response($response, $endpoint) {
        // Ensure response is valid
        if (!is_array($response) && !is_object($response)) {
            return new \WP_Error('invalid_response', 'Invalid API response format');
        }
        
        // Validate specific endpoints
        switch ($endpoint) {
            case 'search':
                return $this->validate_search_response($response);
            case 'autocomplete':
                return $this->validate_autocomplete_response($response);
            default:
                return $response;
        }
    }
    
    /**
     * Validate search response
     * 
     * @param array $response
     * @return array|\WP_Error
     */
    private function validate_search_response($response) {
        if (!isset($response['results']) || !is_array($response['results'])) {
            return new \WP_Error('invalid_search_response', 'Invalid search response structure');
        }
        
        // Sanitize results
        $response['results'] = $this->sanitize_search_results($response['results']);
        
        // Validate intent
        if (isset($response['intent'])) {
            $valid_intents = ['vibe', 'utility', 'hybrid', 'business', 'category'];
            if (!in_array($response['intent'], $valid_intents)) {
                $response['intent'] = 'hybrid';
            }
        }
        
        return $response;
    }
    
    /**
     * Validate autocomplete response
     * 
     * @param array $response
     * @return array
     */
    private function validate_autocomplete_response($response) {
        if (!is_array($response)) {
            return [];
        }
        
        $sanitized = [];
        
        foreach ($response as $suggestion) {
            if (isset($suggestion['value']) && isset($suggestion['label'])) {
                $sanitized[] = [
                    'value' => sanitize_text_field($suggestion['value']),
                    'label' => sanitize_text_field($suggestion['label']),
                    'type' => sanitize_key($suggestion['type'] ?? 'query'),
                    'zpid' => isset($suggestion['zpid']) ? $this->sanitize_zpid($suggestion['zpid']) : null
                ];
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Add security data to frontend
     * 
     * @param array $data
     * @return array
     */
    public function add_security_data($data) {
        $data['security'] = [
            'escape_html' => true,
            'max_query_length' => 100,
            'allowed_html_tags' => ['strong', 'em', 'span'],
            'rate_limit_message' => __('Too many requests. Please wait a moment and try again.', 'zippicks-smart-search')
        ];
        
        return $data;
    }
    
    /**
     * Check if current page is a search page
     * 
     * @return bool
     */
    private function is_search_page() {
        global $post;
        
        // Check if it's the home page (often has search)
        if (is_front_page() || is_home()) {
            return true;
        }
        
        // Check for search shortcode
        if ($post && has_shortcode($post->post_content, 'zippicks_search')) {
            return true;
        }
        
        // Check for search query parameter
        if (isset($_GET['q'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate search query
     * 
     * @param string $query
     * @return string|\WP_Error
     */
    public static function validate_search_query($query) {
        // Check length
        if (strlen($query) > 100) {
            return new \WP_Error('query_too_long', 'Search query is too long');
        }
        
        // Check for malicious patterns
        $dangerous_patterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i', // onclick, onload, etc.
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return new \WP_Error('dangerous_query', 'Invalid characters in search query');
            }
        }
        
        return sanitize_text_field($query);
    }
    
    /**
     * Generate secure nonce for search operations
     * 
     * @param string $action
     * @return string
     */
    public static function generate_search_nonce($action = 'search') {
        return wp_create_nonce('zippicks_search_' . $action);
    }
}