<?php
/**
 * REST API Controller Class
 * 
 * Handles all REST API endpoints for the geo service
 * 
 * @package ZipPicks_Geo_Service
 */

namespace ZipPicks\Geo;

class REST_Controller {
    
    /**
     * Namespace for REST routes
     */
    const NAMESPACE = 'zippicks/v1';
    
    /**
     * Location detector instance
     * @var Location_Detector
     */
    private $location_detector;
    
    /**
     * Distance calculator instance
     * @var Distance_Calculator
     */
    private $distance_calculator;
    
    /**
     * Rate limiting configuration
     */
    private $rate_limits = [
        'public' => 60,      // 60 requests per minute
        'user' => 100,       // 100 requests per minute for logged-in users
        'critic' => 200,     // 200 requests per minute for critics
        'business' => 200,   // 200 requests per minute for businesses
        'admin' => 1000,     // 1000 requests per minute for admins
    ];
    
    /**
     * Constructor
     * 
     * @param Location_Detector $detector
     * @param Distance_Calculator $calculator
     */
    public function __construct(Location_Detector $detector, Distance_Calculator $calculator) {
        $this->location_detector = $detector;
        $this->distance_calculator = $calculator;
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Detect current location
        register_rest_route(self::NAMESPACE, '/geo/detect', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'detect_location'],
                'permission_callback' => '__return_true',
                'args' => [
                    'session_id' => [
                        'type' => 'string',
                        'description' => 'Session identifier',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);
        
        // Update user location
        register_rest_route(self::NAMESPACE, '/geo/update', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'update_location'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'latitude' => [
                        'required' => true,
                        'type' => 'number',
                        'minimum' => -90,
                        'maximum' => 90,
                        'description' => 'Latitude coordinate',
                    ],
                    'longitude' => [
                        'required' => true,
                        'type' => 'number',
                        'minimum' => -180,
                        'maximum' => 180,
                        'description' => 'Longitude coordinate',
                    ],
                    'accuracy' => [
                        'type' => 'string',
                        'enum' => ['precise', 'city', 'state'],
                        'default' => 'precise',
                        'description' => 'Location accuracy level',
                    ],
                    'accuracy_meters' => [
                        'type' => 'integer',
                        'minimum' => 0,
                        'description' => 'Accuracy in meters',
                    ],
                ],
            ],
        ]);
        
        // Calculate distance
        register_rest_route(self::NAMESPACE, '/geo/distance', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'calculate_distance'],
                'permission_callback' => '__return_true',
                'args' => [
                    'from' => [
                        'required' => true,
                        'type' => 'object',
                        'properties' => [
                            'lat' => [
                                'type' => 'number',
                                'required' => true,
                                'minimum' => -90,
                                'maximum' => 90,
                            ],
                            'lng' => [
                                'type' => 'number',
                                'required' => true,
                                'minimum' => -180,
                                'maximum' => 180,
                            ],
                        ],
                    ],
                    'to' => [
                        'required' => true,
                        'type' => 'object',
                        'properties' => [
                            'lat' => [
                                'type' => 'number',
                                'required' => true,
                                'minimum' => -90,
                                'maximum' => 90,
                            ],
                            'lng' => [
                                'type' => 'number',
                                'required' => true,
                                'minimum' => -180,
                                'maximum' => 180,
                            ],
                        ],
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['miles', 'km', 'meters'],
                        'default' => 'miles',
                    ],
                ],
            ],
        ]);
        
        // Find nearby locations
        register_rest_route(self::NAMESPACE, '/geo/nearby', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'find_nearby'],
                'permission_callback' => '__return_true',
                'args' => [
                    'latitude' => [
                        'required' => true,
                        'type' => 'number',
                        'minimum' => -90,
                        'maximum' => 90,
                    ],
                    'longitude' => [
                        'required' => true,
                        'type' => 'number',
                        'minimum' => -180,
                        'maximum' => 180,
                    ],
                    'radius' => [
                        'type' => 'number',
                        'default' => 5,
                        'minimum' => 0.1,
                        'maximum' => 100,
                        'description' => 'Search radius in miles',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100,
                        'description' => 'Maximum results to return',
                    ],
                    'type' => [
                        'type' => 'string',
                        'enum' => ['restaurants', 'businesses', 'all'],
                        'default' => 'restaurants',
                    ],
                ],
            ],
        ]);
        
        // Batch distance calculations
        register_rest_route(self::NAMESPACE, '/geo/batch-distance', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'batch_distance'],
                'permission_callback' => '__return_true',
                'args' => [
                    'center' => [
                        'required' => true,
                        'type' => 'object',
                        'properties' => [
                            'lat' => [
                                'type' => 'number',
                                'required' => true,
                            ],
                            'lng' => [
                                'type' => 'number',
                                'required' => true,
                            ],
                        ],
                    ],
                    'points' => [
                        'required' => true,
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string'],
                                'latitude' => ['type' => 'number'],
                                'longitude' => ['type' => 'number'],
                            ],
                        ],
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['miles', 'km'],
                        'default' => 'miles',
                    ],
                ],
            ],
        ]);
    }
    
    /**
     * Detect user location
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function detect_location($request) {
        // Check rate limiting
        if (!$this->check_rate_limit($request)) {
            return new \WP_Error(
                'rate_limit_exceeded',
                ZIPPICKS_GEO_ERRORS['GEO004'],
                ['status' => 429]
            );
        }
        
        $user_id = get_current_user_id();
        $session_id = $request->get_header('X-Session-ID') ?? $request->get_param('session_id');
        
        $location = $this->location_detector->get_user_location($user_id, $session_id);
        
        if (is_wp_error($location)) {
            return $location;
        }
        
        // Add response headers
        $response = rest_ensure_response($location);
        $response->header('X-Robots-Tag', 'noindex');
        $response->header('Cache-Control', 'private, max-age=0');
        $response->header('X-ZipPicks-Source', 'frontend-only');
        
        return $response;
    }
    
    /**
     * Update user location
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_location($request) {
        $user_id = get_current_user_id();
        $session_id = $request->get_header('X-Session-ID');
        
        $location = [
            'latitude' => $request->get_param('latitude'),
            'longitude' => $request->get_param('longitude'),
            'accuracy' => $request->get_param('accuracy'),
            'accuracy_meters' => $request->get_param('accuracy_meters'),
            'source' => 'gps',
        ];
        
        $success = $this->location_detector->update_user_location($location, $user_id, $session_id);
        
        if (!$success) {
            return new \WP_Error(
                'location_update_failed',
                'Failed to update location',
                ['status' => 400]
            );
        }
        
        $response_data = [
            'success' => true,
            'location' => $location,
            'expires_at' => time() + 300, // 5 minutes
        ];
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Calculate distance between two points
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function calculate_distance($request) {
        $from = $request->get_param('from');
        $to = $request->get_param('to');
        $unit = $request->get_param('unit');
        
        $distance = $this->distance_calculator->calculate_distance(
            $from['lat'],
            $from['lng'],
            $to['lat'],
            $to['lng'],
            $unit
        );
        
        $response_data = [
            'distance' => round($distance, 2),
            'unit' => $unit,
            'from' => $from,
            'to' => $to,
        ];
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Find nearby locations
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function find_nearby($request) {
        // Check rate limiting
        if (!$this->check_rate_limit($request)) {
            return new \WP_Error(
                'rate_limit_exceeded',
                ZIPPICKS_GEO_ERRORS['GEO004'],
                ['status' => 429]
            );
        }
        
        $lat = $request->get_param('latitude');
        $lng = $request->get_param('longitude');
        $radius = $request->get_param('radius');
        $limit = $request->get_param('limit');
        $type = $request->get_param('type');
        
        // Get results based on type
        if ($type === 'businesses' || $type === 'all') {
            // Search WordPress posts
            $results = $this->distance_calculator->find_posts_within_radius(
                $lat,
                $lng,
                $radius,
                $limit,
                'zippicks_business'
            );
        } else {
            // Search PostgreSQL restaurants table
            $results = $this->distance_calculator->find_within_radius(
                $lat,
                $lng,
                $radius,
                $limit
            );
        }
        
        $response_data = [
            'results' => $results,
            'total' => count($results),
            'center' => ['lat' => $lat, 'lng' => $lng],
            'radius_miles' => $radius,
            'type' => $type,
        ];
        
        // Add security headers
        $response = rest_ensure_response($response_data);
        $response->header('X-Robots-Tag', 'noindex');
        $response->header('Cache-Control', 'private, max-age=0');
        $response->header('X-ZipPicks-Source', 'frontend-only');
        
        return $response;
    }
    
    /**
     * Calculate batch distances
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function batch_distance($request) {
        $center = $request->get_param('center');
        $points = $request->get_param('points');
        $unit = $request->get_param('unit');
        
        $results = $this->distance_calculator->calculate_batch_distances(
            $center['lat'],
            $center['lng'],
            $points,
            $unit
        );
        
        return rest_ensure_response([
            'center' => $center,
            'results' => $results,
            'unit' => $unit,
        ]);
    }
    
    /**
     * Check permission for protected endpoints
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function check_permission($request) {
        // Allow all authenticated users
        if (is_user_logged_in()) {
            return true;
        }
        
        // Check for valid session
        $session_id = $request->get_header('X-Session-ID');
        if ($session_id && strlen($session_id) >= 32) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check rate limiting
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    private function check_rate_limit($request) {
        // Get user type
        $user_type = 'public';
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('administrator', $user->roles)) {
                $user_type = 'admin';
            } elseif (in_array('critic', $user->roles)) {
                $user_type = 'critic';
            } elseif (in_array('business', $user->roles)) {
                $user_type = 'business';
            } else {
                $user_type = 'user';
            }
        }
        
        // Get rate limit for user type
        $limit = $this->rate_limits[$user_type];
        
        // Generate rate limit key
        $identifier = is_user_logged_in() ? get_current_user_id() : $_SERVER['REMOTE_ADDR'];
        $key = 'geo_rate_' . md5($user_type . '_' . $identifier);
        
        // Check current count
        $count = get_transient($key);
        if ($count === false) {
            set_transient($key, 1, 60); // 1 minute window
            return true;
        }
        
        if ($count >= $limit) {
            return false;
        }
        
        // Increment counter
        set_transient($key, $count + 1, 60);
        
        return true;
    }
}