<?php
/**
 * REST API endpoints for AJAX data loading with enterprise security
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_REST_API {
    
    /**
     * Initialize REST API endpoints
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('rest_api_init', [$this, 'add_security_headers']);
    }
    
    /**
     * Register all REST API routes
     */
    public function register_routes() {
        // Master Critic List endpoint
        register_rest_route('zippicks/v1', '/lists/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_list'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // List businesses in a Master Critic list
        register_rest_route('zippicks/v1', '/lists/(?P<id>\d+)/businesses', [
            'methods' => 'GET',
            'callback' => [$this, 'get_list_businesses'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Search Master Critic lists
        register_rest_route('zippicks/v1', '/lists/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_lists'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'query' => [
                    'validate_callback' => function($param) {
                        return is_string($param) && strlen($param) >= 2;
                    },
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'category' => [
                    'validate_callback' => function($param) {
                        return is_string($param);
                    },
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'location' => [
                    'validate_callback' => function($param) {
                        return is_string($param);
                    },
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Get list analytics (authenticated only)
        register_rest_route('zippicks/v1', '/lists/(?P<id>\d+)/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_list_analytics'],
            'permission_callback' => [$this, 'check_admin_permissions'],
            'args' => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
    }
    
    /**
     * Get Master Critic list data
     */
    public function get_list($request) {
        $list_id = $request['id'];
        
        // Rate limiting check
        if ($this->is_rate_limited()) {
            return new WP_Error('rate_limited', 'Too many requests. Please wait before trying again.', ['status' => 429]);
        }
        
        // Log the request
        $this->log_api_request($list_id, 'get_list');
        
        // Get list data
        $list_data = get_post($list_id);
        
        if (!$list_data || $list_data->post_type !== 'master_critic_list') {
            return new WP_Error('not_found', 'List not found', ['status' => 404]);
        }
        
        // Check if list is published
        if ($list_data->post_status !== 'publish') {
            return new WP_Error('not_available', 'List not available', ['status' => 403]);
        }
        
        // Get hybrid data if available
        $enhanced_data = $this->get_enhanced_list_data($list_data);
        
        // Add watermarks and fingerprint
        $response_data = [
            'id' => $list_id,
            'title' => $list_data->post_title,
            'content' => $this->add_api_watermarks($list_data->post_content),
            'excerpt' => $list_data->post_excerpt,
            'date' => $list_data->post_date,
            'businesses' => $enhanced_data['businesses'] ?? [],
            'meta' => [
                'category' => get_post_meta($list_id, 'business_category', true),
                'location' => get_post_meta($list_id, 'location', true),
                'view_count' => intval(get_post_meta($list_id, 'view_count', true))
            ],
            'fingerprint' => $this->generate_api_fingerprint($list_id),
            '_watermark' => wp_generate_password(16, false)
        ];
        
        // Increment view count
        $this->increment_view_count($list_id);
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Get businesses in a Master Critic list
     */
    public function get_list_businesses($request) {
        $list_id = $request['id'];
        
        if ($this->is_rate_limited()) {
            return new WP_Error('rate_limited', 'Too many requests', ['status' => 429]);
        }
        
        $this->log_api_request($list_id, 'get_businesses');
        
        $list_data = get_post($list_id);
        if (!$list_data || $list_data->post_type !== 'master_critic_list') {
            return new WP_Error('not_found', 'List not found', ['status' => 404]);
        }
        
        $businesses = $this->extract_businesses_from_list($list_data);
        
        return rest_ensure_response([
            'list_id' => $list_id,
            'businesses' => $businesses,
            'count' => count($businesses),
            'fingerprint' => $this->generate_api_fingerprint($list_id)
        ]);
    }
    
    /**
     * Search Master Critic lists
     */
    public function search_lists($request) {
        $query = $request->get_param('query');
        $category = $request->get_param('category');
        $location = $request->get_param('location');
        
        if ($this->is_rate_limited()) {
            return new WP_Error('rate_limited', 'Too many requests', ['status' => 429]);
        }
        
        $this->log_api_request(0, 'search_lists', [
            'query' => $query,
            'category' => $category,
            'location' => $location
        ]);
        
        // Build search arguments
        $args = [
            'post_type' => 'master_critic_list',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'meta_query' => []
        ];
        
        // Add search query
        if ($query) {
            $args['s'] = $query;
        }
        
        // Add category filter
        if ($category) {
            $args['meta_query'][] = [
                'key' => 'business_category',
                'value' => $category,
                'compare' => 'LIKE'
            ];
        }
        
        // Add location filter
        if ($location) {
            $args['meta_query'][] = [
                'key' => 'location',
                'value' => $location,
                'compare' => 'LIKE'
            ];
        }
        
        $search_results = get_posts($args);
        
        $formatted_results = array_map(function($post) {
            return [
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => $post->post_excerpt,
                'date' => $post->post_date,
                'category' => get_post_meta($post->ID, 'business_category', true),
                'location' => get_post_meta($post->ID, 'location', true),
                'view_count' => intval(get_post_meta($post->ID, 'view_count', true))
            ];
        }, $search_results);
        
        return rest_ensure_response([
            'results' => $formatted_results,
            'count' => count($formatted_results),
            'query' => compact('query', 'category', 'location')
        ]);
    }
    
    /**
     * Get list analytics (admin only)
     */
    public function get_list_analytics($request) {
        $list_id = $request['id'];
        
        if ($this->is_rate_limited()) {
            return new WP_Error('rate_limited', 'Too many requests', ['status' => 429]);
        }
        
        $this->log_api_request($list_id, 'get_analytics');
        
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'zippicks_list_analytics';
        
        // Get analytics data
        $analytics = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$analytics_table} WHERE list_id = %d ORDER BY date DESC LIMIT 30",
            $list_id
        ));
        
        return rest_ensure_response([
            'list_id' => $list_id,
            'analytics' => $analytics,
            'summary' => [
                'total_views' => intval(get_post_meta($list_id, 'view_count', true)),
                'created_date' => get_post_field('post_date', $list_id)
            ]
        ]);
    }
    
    /**
     * Check basic permissions (rate limiting + user agent)
     */
    public function check_permissions($request) {
        // Check user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($user_agent) || $this->is_suspicious_user_agent($user_agent)) {
            return new WP_Error('forbidden', 'Invalid user agent', ['status' => 403]);
        }
        
        // Check for CLI agents
        $cli_agents = ['curl', 'wget', 'python', 'scrapy', 'bot'];
        foreach ($cli_agents as $agent) {
            if (stripos($user_agent, $agent) !== false) {
                return new WP_Error('forbidden', 'CLI access not allowed', ['status' => 403]);
            }
        }
        
        return true;
    }
    
    /**
     * Check admin permissions
     */
    public function check_admin_permissions($request) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('unauthorized', 'Admin access required', ['status' => 401]);
        }
        
        return $this->check_permissions($request);
    }
    
    /**
     * Check if request is rate limited
     */
    private function is_rate_limited() {
        $ip = $this->get_client_ip();
        $key = 'zippicks_api_rate_' . md5($ip);
        $requests = get_transient($key) ?: 0;
        
        // Allow 20 requests per minute
        if ($requests >= 20) {
            // Log rate limit violation
            $this->log_rate_limit_violation($ip);
            return true;
        }
        
        set_transient($key, $requests + 1, 60);
        return false;
    }
    
    /**
     * Add security headers to all API responses
     */
    public function add_security_headers() {
        add_filter('rest_pre_serve_request', function($served, $result, $request) {
            if (strpos($request->get_route(), '/zippicks/') !== false) {
                // Anti-scraping headers
                header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
                header('Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate');
                header('X-ZipPicks-Source: frontend-only');
                
                // Security headers
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: DENY');
                header('X-XSS-Protection: 1; mode=block');
                header('Referrer-Policy: strict-origin-when-cross-origin');
                
                // Custom headers for tracking
                header('X-ZipPicks-Version: ' . ZIPPICKS_MASTER_CRITIC_VERSION);
                header('X-ZipPicks-Time: ' . time());
                
                // Vary header for caching
                header('Vary: User-Agent, Accept-Encoding');
            }
            return $served;
        }, 10, 3);
    }
    
    /**
     * Get enhanced list data using hybrid system
     */
    private function get_enhanced_list_data($list_post) {
        $enhanced_data = ['businesses' => []];
        
        // Check if hybrid system is available
        if (class_exists('ZipPicks\MasterCritic\Hybrid\HybridServiceProvider')) {
            try {
                $router = ZipPicks\MasterCritic\Hybrid\HybridServiceProvider::get_smart_router();
                if ($router) {
                    $enhanced_data['businesses'] = $this->extract_businesses_from_list($list_post);
                    // Enhance business data with hybrid sources if needed
                }
            } catch (Exception $e) {
                error_log('ZipPicks Master Critic: Hybrid enhancement failed - ' . $e->getMessage());
            }
        }
        
        return $enhanced_data;
    }
    
    /**
     * Extract businesses from list content
     */
    private function extract_businesses_from_list($list_post) {
        $businesses = [];
        
        // Try to parse JSON from post content or meta
        $list_data = get_post_meta($list_post->ID, 'generated_list_data', true);
        
        if ($list_data && is_string($list_data)) {
            $decoded = json_decode($list_data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $businesses = $decoded;
            }
        }
        
        // Add watermarks to each business
        foreach ($businesses as &$business) {
            $business['_watermark'] = wp_generate_password(8, false);
        }
        
        return $businesses;
    }
    
    /**
     * Add API-specific watermarks
     */
    private function add_api_watermarks($content) {
        $watermarks = [
            '<!-- ZipPicks API Content -->',
            '<!-- Generated: ' . current_time('mysql') . ' -->',
            '<!-- Fingerprint: ' . wp_generate_password(16, false) . ' -->'
        ];
        
        return $content . implode("\n", $watermarks);
    }
    
    /**
     * Generate API fingerprint for tracking
     */
    private function generate_api_fingerprint($list_id) {
        return wp_hash($list_id . time() . $this->get_client_ip() . wp_generate_password(8, false));
    }
    
    /**
     * Increment view count for a list
     */
    private function increment_view_count($list_id) {
        $current_count = intval(get_post_meta($list_id, 'view_count', true));
        update_post_meta($list_id, 'view_count', $current_count + 1);
        
        // Also log to analytics table if it exists
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'zippicks_list_analytics';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$analytics_table}'") === $analytics_table) {
            $wpdb->insert(
                $analytics_table,
                [
                    'list_id' => $list_id,
                    'date' => current_time('mysql'),
                    'views' => 1,
                    'ip_address' => $this->get_client_ip(),
                    'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
                ],
                ['%d', '%s', '%d', '%s', '%s']
            );
        }
    }
    
    /**
     * Log API requests for monitoring
     */
    private function log_api_request($list_id, $action, $params = []) {
        if (class_exists('ZipPicks_Master_Critic_Database')) {
            ZipPicks_Master_Critic_Database::log_scrape_attempt([
                'list_id' => $list_id,
                'action' => $action,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'referer' => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255),
                'is_suspicious' => 0,
                'fingerprint' => $this->generate_api_fingerprint($list_id),
                'params' => $params
            ]);
        }
    }
    
    /**
     * Log rate limit violations
     */
    private function log_rate_limit_violation($ip) {
        if (class_exists('ZipPicks_Master_Critic_Database')) {
            ZipPicks_Master_Critic_Database::log_scrape_attempt([
                'list_id' => 0,
                'action' => 'rate_limit_violation',
                'ip_address' => $ip,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'is_suspicious' => 1,
                'fingerprint' => wp_generate_password(16, false)
            ]);
        }
    }
    
    /**
     * Check for suspicious user agents
     */
    private function is_suspicious_user_agent($user_agent) {
        $suspicious_patterns = [
            'bot', 'crawler', 'spider', 'scraper', 'parser',
            'libwww', 'winhttp', 'python-urllib', 'java/',
            'go-http-client', 'okhttp', 'apache-httpclient'
        ];
        
        $user_agent_lower = strtolower($user_agent);
        
        foreach ($suspicious_patterns as $pattern) {
            if (strpos($user_agent_lower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get client IP address (proxy-aware)
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

// Initialize REST API
new ZipPicks_Master_Critic_REST_API();