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
     * CSP nonce for scripts and styles
     * @var string
     */
    private $csp_nonce = null;
    
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
        add_filter('zippicks_search_results', [$this, 'sanitize_search_results'], 10, 1);
        
        // Add nonce verification to all AJAX requests
        add_filter('wp_ajax_nopriv_zippicks_search', [$this, 'verify_ajax_nonce'], 1);
        add_filter('wp_ajax_zippicks_search', [$this, 'verify_ajax_nonce'], 1);
        
        // Validate API responses
        add_filter('zippicks_api_response', [$this, 'validate_api_response'], 10, 2);
        
        // Add security data to frontend
        add_filter('zippicks_search_localize_data', [$this, 'add_security_data']);
        
        // Add nonces to script and style tags
        add_filter('script_loader_tag', [$this, 'add_nonce_to_scripts'], 10, 3);
        add_filter('style_loader_tag', [$this, 'add_nonce_to_styles'], 10, 4);
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (!$this->is_search_page()) {
            return;
        }
        
        // Get nonce for this request
        $nonce = $this->get_csp_nonce();
        
        // Content Security Policy with nonce-based approach
        $csp = "default-src 'self'; " .
               "script-src 'self' 'nonce-" . $nonce . "'; " .
               "style-src 'self' 'nonce-" . $nonce . "'; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self' " . esc_url(rest_url()) . "; " .
               "font-src 'self' data:; " .
               "object-src 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self'; " .
               "frame-ancestors 'none';";
        
        header("Content-Security-Policy: $csp");
        
        // Modern security headers (removed deprecated X-XSS-Protection)
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(self), microphone=(), camera=()');
    }
    
    /**
     * Sanitize search results before output
     * 
     * @param array $results Search results
     * @return array
     */
    public function sanitize_search_results($results) {
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
            'rate_limit_message' => __('Too many requests. Please wait a moment and try again.', 'zippicks-smart-search'),
            'csp_nonce' => $this->get_csp_nonce()
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
    
    /**
     * Get CSP nonce for inline scripts and styles
     * 
     * @return string
     */
    public function get_csp_nonce() {
        if ($this->csp_nonce === null) {
            $this->csp_nonce = wp_create_nonce('zippicks_csp_nonce');
        }
        return $this->csp_nonce;
    }
    
    /**
     * Get CSP nonce attribute for HTML elements
     * 
     * @return string
     */
    public function get_nonce_attribute() {
        return 'nonce="' . esc_attr($this->get_csp_nonce()) . '"';
    }
    
    /**
     * Add nonce to script tags
     * 
     * @param string $tag HTML tag
     * @param string $handle Script handle
     * @param string $src Script source
     * @return string Modified tag
     */
    public function add_nonce_to_scripts($tag, $handle, $src) {
        // Only add nonce to our plugin's scripts
        $plugin_handles = [
            'zippicks-smart-search',
            'zippicks-search-autocomplete',
            'zippicks-error-reporter',
            'zippicks-search-security'
        ];
        
        if (in_array($handle, $plugin_handles) || strpos($handle, 'zippicks') !== false) {
            $nonce = $this->get_csp_nonce();
            return str_replace('<script', '<script nonce="' . esc_attr($nonce) . '"', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Add nonce to style tags
     * 
     * @param string $tag HTML tag
     * @param string $handle Style handle
     * @param string $href Style href
     * @param string $media Media attribute
     * @return string Modified tag
     */
    public function add_nonce_to_styles($tag, $handle, $href, $media) {
        // Only add nonce to our plugin's styles
        if (strpos($handle, 'zippicks') !== false) {
            $nonce = $this->get_csp_nonce();
            return str_replace('<link', '<link nonce="' . esc_attr($nonce) . '"', $tag);
        }
        
        return $tag;
    }
}