<?php
/**
 * REST Controller
 * 
 * WordPress REST API endpoints that proxy to PostgreSQL API
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

use ZipPicks\SmartSearch\Traits\LocationDetection;

class REST_Controller {
    use LocationDetection;
    
    /**
     * Namespace for REST routes
     * @var string
     */
    const NAMESPACE = 'zippicks/v1';
    
    /**
     * Register REST routes
     */
    public function register_routes() {
        // Main search endpoint
        register_rest_route(self::NAMESPACE, '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search'],
            'permission_callback' => '__return_true',
            'args' => $this->get_search_args()
        ]);
        
        // Coming soon notification endpoint
        register_rest_route(self::NAMESPACE, '/search/notify/(?P<zpid>[a-zA-Z0-9-]{1,50})', [
            'methods' => 'POST',
            'callback' => [$this, 'notify_coming_soon'],
            'permission_callback' => '__return_true',
            'args' => [
                'zpid' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param) && strlen($param) <= 50;
                    },
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'email' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_email($param);
                    }
                ]
            ]
        ]);
        
        // Autocomplete endpoint
        register_rest_route(self::NAMESPACE, '/search/autocomplete', [
            'methods' => 'GET',
            'callback' => [$this, 'autocomplete'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && strlen($param) >= 2;
                    }
                ],
                'lat' => [
                    'required' => false,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= -90 && $param <= 90;
                    }
                ],
                'lng' => [
                    'required' => false,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= -180 && $param <= 180;
                    }
                ],
                'loc' => [
                    'required' => false,
                    'validate_callback' => function($param) {
                        if (!is_string($param) || empty($param)) {
                            return false;
                        }
                        $parts = explode(',', $param);
                        if (count($parts) !== 2) {
                            return false;
                        }
                        $lat = trim($parts[0]);
                        $lng = trim($parts[1]);
                        return is_numeric($lat) && is_numeric($lng) 
                            && $lat >= -90 && $lat <= 90 
                            && $lng >= -180 && $lng <= 180;
                    },
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Track search click endpoint
        register_rest_route(self::NAMESPACE, '/search/track-click', [
            'methods' => 'POST',
            'callback' => [$this, 'track_click'],
            'permission_callback' => '__return_true',
            'args' => [
                'zpid' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param) && strlen($param) <= 50;
                    },
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'query' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
                'position' => [
                    'required' => false,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ]
            ]
        ]);
        
        // API Proxy endpoint (for secure API calls)
        if (get_option('zippicks_search_use_proxy', false)) {
            register_rest_route(self::NAMESPACE, '/proxy/search', [
                'methods' => 'POST',
                'callback' => [$this, 'proxy_search'],
                'permission_callback' => '__return_true',
                'args' => [
                    'endpoint' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            // Only allow specific endpoints
                            $allowed = ['search', 'restaurants/search', 'restaurants/details'];
                            return in_array($param, $allowed);
                        }
                    ],
                    'params' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_array($param);
                        }
                    ]
                ]
            ]);
        }
    }
    
    /**
     * Handle search request
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function search($request) {
        // Check rate limit
        $rate_limit = Rate_Limiter::check('search');
        if (isset($rate_limit['error'])) {
            $error = new \WP_Error(
                'rate_limit_exceeded',
                $rate_limit['message'],
                ['status' => 429]
            );
            return Rate_Limiter::add_to_error($error, $rate_limit);
        }
        
        $query = sanitize_text_field($request->get_param('q'));
        $lat = $request->get_param('lat');
        $lng = $request->get_param('lng');
        $radius = $request->get_param('radius') ?: 10;
        $limit = $request->get_param('limit') ?: 20;
        
        // Get location
        $location = $this->get_location($lat, $lng);
        if (is_wp_error($location)) {
            return $location;
        }
        
        // Initialize cache manager
        $cache = Cache_Manager::instance();
        
        // Check cache first
        $cache_params = ['radius' => $radius, 'limit' => $limit];
        $cached_results = $cache->get_search_results($query, $location, $cache_params);
        
        if ($cached_results !== false) {
            return rest_ensure_response([
                'success' => true,
                'data' => $cached_results,
                'cached' => true
            ]);
        }
        
        // Perform search
        $search_engine = new Search_Engine();
        $results = $search_engine->search($query, $location, [
            'radius' => $radius,
            'limit' => $limit
        ]);
        
        if (is_wp_error($results)) {
            return $results;
        }
        
        // Cache results
        if (isset($results['intent'])) {
            $cache->set_search_results($query, $location, $cache_params, $results, $results['intent']);
        }
        
        // Track search
        $this->track_search($query, $location, $results);
        
        $response = rest_ensure_response([
            'success' => true,
            'data' => $results,
            'cached' => false
        ]);
        
        // Add rate limit headers
        $headers = Rate_Limiter::get_headers($rate_limit);
        foreach ($headers as $header => $value) {
            $response->header($header, $value);
        }
        
        return $response;
    }
    
    /**
     * Handle autocomplete request
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function autocomplete($request) {
        // Check rate limit
        $rate_limit = Rate_Limiter::check('autocomplete');
        if (isset($rate_limit['error'])) {
            $error = new \WP_Error(
                'rate_limit_exceeded',
                $rate_limit['message'],
                ['status' => 429]
            );
            return Rate_Limiter::add_to_error($error, $rate_limit);
        }
        
        $prefix = sanitize_text_field($request->get_param('q'));
        
        // Handle location parameters - support both 'loc' and separate lat/lng
        $loc = $request->get_param('loc');
        $lat = null;
        $lng = null;
        
        if ($loc) {
            // Parse loc parameter (format: "lat,lng")
            $parts = explode(',', $loc);
            if (count($parts) === 2) {
                $lat = floatval(trim($parts[0]));
                $lng = floatval(trim($parts[1]));
            } else {
                return new \WP_Error(
                    'invalid_location_format',
                    __('Location parameter must be in format "latitude,longitude"', 'zippicks-smart-search'),
                    ['status' => 400]
                );
            }
        } else {
            // Fall back to separate lat/lng parameters
            $lat = $request->get_param('lat');
            $lng = $request->get_param('lng');
        }
        
        // Get location
        $location = $this->get_location($lat, $lng);
        if (is_wp_error($location)) {
            return $location;
        }
        
        // Check cache
        $cache = Cache_Manager::instance();
        $cached_suggestions = $cache->get_autocomplete($prefix, $location);
        
        if ($cached_suggestions !== false) {
            return rest_ensure_response([
                'success' => true,
                'suggestions' => $cached_suggestions
            ]);
        }
        
        // Get suggestions from API
        $api_client = API_Client::instance();
        $suggestions = $api_client->get_autocomplete($prefix, $location);
        
        if (is_wp_error($suggestions)) {
            return $suggestions;
        }
        
        // Process suggestions
        $processed_suggestions = $this->process_autocomplete_suggestions($suggestions, $prefix);
        
        // Cache results
        $cache->set_autocomplete($prefix, $location, $processed_suggestions);
        
        $response = rest_ensure_response([
            'success' => true,
            'suggestions' => $processed_suggestions
        ]);
        
        // Add rate limit headers
        $headers = Rate_Limiter::get_headers($rate_limit);
        foreach ($headers as $header => $value) {
            $response->header($header, $value);
        }
        
        return $response;
    }
    
    /**
     * Handle coming soon notification
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function notify_coming_soon($request) {
        // Check rate limit
        $rate_limit = Rate_Limiter::check('notify');
        if (isset($rate_limit['error'])) {
            $error = new \WP_Error(
                'rate_limit_exceeded',
                $rate_limit['message'],
                ['status' => 429]
            );
            return Rate_Limiter::add_to_error($error, $rate_limit);
        }
        
        $zpid = sanitize_text_field($request->get_param('zpid'));
        $email = sanitize_email($request->get_param('email'));
        
        // Track demand
        $demand_tracker = new Demand_Tracker();
        $result = $demand_tracker->track_demand($zpid, $email);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Send notification to API
        $api_client = API_Client::instance();
        $notification_result = $api_client->track_coming_soon($zpid, $email);
        
        if (is_wp_error($notification_result)) {
            // Log error but don't fail the request
            error_log('Failed to send coming soon notification to API: ' . $notification_result->get_error_message());
        }
        
        $response = rest_ensure_response([
            'success' => true,
            'message' => __('You will be notified when this business is added!', 'zippicks-smart-search')
        ]);
        
        // Add rate limit headers
        $headers = Rate_Limiter::get_headers($rate_limit);
        foreach ($headers as $header => $value) {
            $response->header($header, $value);
        }
        
        return $response;
    }
    
    /**
     * Track search click
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function track_click($request) {
        // Check rate limit
        $rate_limit = Rate_Limiter::check('track');
        if (isset($rate_limit['error'])) {
            $error = new \WP_Error(
                'rate_limit_exceeded',
                $rate_limit['message'],
                ['status' => 429]
            );
            return Rate_Limiter::add_to_error($error, $rate_limit);
        }
        
        $zpid = sanitize_text_field($request->get_param('zpid'));
        $query = sanitize_text_field($request->get_param('query'));
        $position = intval($request->get_param('position') ?: 0);
        
        // Send to analytics
        $analytics = Analytics::instance();
        $result = $analytics->track_click($zpid, $query, $position);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $response = rest_ensure_response([
            'success' => true
        ]);
        
        // Add rate limit headers
        $headers = Rate_Limiter::get_headers($rate_limit);
        foreach ($headers as $header => $value) {
            $response->header($header, $value);
        }
        
        return $response;
    }
    
    /**
     * Proxy API requests securely
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function proxy_search($request) {
        // Check rate limit
        $rate_limit = Rate_Limiter::check('search');
        if (isset($rate_limit['error'])) {
            $error = new \WP_Error(
                'rate_limit_exceeded',
                $rate_limit['message'],
                ['status' => 429]
            );
            return Rate_Limiter::add_to_error($error, $rate_limit);
        }
        
        $endpoint = $request->get_param('endpoint');
        $params = $request->get_param('params');
        
        // Get the backend API key (not exposed to frontend)
        $api_key = get_option('zippicks_search_backend_api_key', '');
        if (empty($api_key)) {
            return new \WP_Error(
                'api_key_missing',
                __('Backend API key not configured', 'zippicks-smart-search'),
                ['status' => 500]
            );
        }
        
        // Initialize API client with backend key
        $api_client = new API_Client();
        $api_client->set_api_key($api_key);
        
        // Make the proxied request
        $result = null;
        switch ($endpoint) {
            case 'search':
            case 'restaurants/search':
                $result = $api_client->search_restaurants($params);
                break;
            case 'restaurants/details':
                if (isset($params['zpid'])) {
                    $result = $api_client->get_restaurant_details($params['zpid']);
                }
                break;
            default:
                return new \WP_Error(
                    'invalid_endpoint',
                    __('Invalid API endpoint', 'zippicks-smart-search'),
                    ['status' => 400]
                );
        }
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $response = rest_ensure_response($result);
        
        // Add rate limit headers
        $headers = Rate_Limiter::get_headers($rate_limit);
        foreach ($headers as $header => $value) {
            $response->header($header, $value);
        }
        
        return $response;
    }
    
    /**
     * Get search arguments schema
     * 
     * @return array
     */
    private function get_search_args() {
        return [
            'q' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_string($param) && !empty(trim($param));
                }
            ],
            'lat' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param >= -90 && $param <= 90;
                }
            ],
            'lng' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param >= -180 && $param <= 180;
                }
            ],
            'radius' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0 && $param <= 50;
                },
                'default' => 10
            ],
            'limit' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0 && $param <= 100;
                },
                'default' => 20
            ]
        ];
    }
    
    
    /**
     * Process autocomplete suggestions
     * 
     * @param array $suggestions Raw suggestions from API
     * @param string $prefix Search prefix
     * @return array
     */
    private function process_autocomplete_suggestions($suggestions, $prefix) {
        $processed = [];
        
        // Add search query as first suggestion
        $processed[] = [
            'type' => 'query',
            'value' => $prefix,
            'label' => $prefix,
            'icon' => 'search'
        ];
        
        // Process business suggestions
        if (!empty($suggestions['businesses'])) {
            foreach ($suggestions['businesses'] as $business) {
                $processed[] = [
                    'type' => 'business',
                    'value' => $business['name'],
                    'label' => $business['name'],
                    'zpid' => $business['zpid'],
                    'icon' => 'location',
                    'meta' => $business['city'] ?? ''
                ];
            }
        }
        
        // Process vibe suggestions
        if (!empty($suggestions['vibes'])) {
            foreach ($suggestions['vibes'] as $vibe) {
                $processed[] = [
                    'type' => 'vibe',
                    'value' => $vibe,
                    'label' => $vibe,
                    'icon' => 'vibe'
                ];
            }
        }
        
        // Process category suggestions
        if (!empty($suggestions['categories'])) {
            foreach ($suggestions['categories'] as $category) {
                $processed[] = [
                    'type' => 'category',
                    'value' => $category,
                    'label' => $category,
                    'icon' => 'category'
                ];
            }
        }
        
        return array_slice($processed, 0, 10); // Limit to 10 suggestions
    }
    
    /**
     * Track search query
     * 
     * @param string $query Search query
     * @param array $location Location data
     * @param array $results Search results
     */
    private function track_search($query, $location, $results) {
        $analytics = Analytics::instance();
        
        $analytics->track_search([
            'query' => $query,
            'location' => $location,
            'result_count' => isset($results['results']) ? count($results['results']) : 0,
            'intent' => $results['intent'] ?? 'unknown',
            'has_results' => !empty($results['results'])
        ]);
    }
}