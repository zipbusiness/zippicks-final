<?php
namespace ZipPicks\Favorites;

if (!defined('ABSPATH')) {
    exit;
}

class API {
    
    private $namespace = 'zippicks/v1';
    private $favorites_manager;
    private $location_service;
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        
        // Initialize services
        $this->favorites_manager = new Favorites_Manager();
        $this->location_service = new Location_Service();
    }
    
    public function register_routes() {
        // Save/unsave favorite
        register_rest_route($this->namespace, '/favorites', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'save_favorite'],
                'permission_callback' => [$this, 'check_user_permission'],
                'args' => [
                    'business_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint'
                    ],
                    'notes' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ]
                ]
            ],
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_favorites'],
                'permission_callback' => [$this, 'check_user_permission'],
                'args' => [
                    'page' => [
                        'default' => 1,
                        'sanitize_callback' => 'absint'
                    ],
                    'per_page' => [
                        'default' => 20,
                        'sanitize_callback' => 'absint'
                    ],
                    'city' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'state' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'neighborhood' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'cuisine' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'search' => [
                        'sanitize_callback' => 'sanitize_text_field'
                    ],
                    'sort' => [
                        'default' => 'date',
                        'enum' => ['date', 'name', 'distance', 'rating'],
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ]
        ]);
        
        // Remove favorite
        register_rest_route($this->namespace, '/favorites/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [$this, 'remove_favorite'],
            'permission_callback' => [$this, 'check_user_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Get favorites near location
        register_rest_route($this->namespace, '/favorites/near', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_favorites_near_location'],
            'permission_callback' => [$this, 'check_user_permission'],
            'args' => [
                'lat' => [
                    'required' => true,
                    'type' => 'number',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= -90 && $param <= 90;
                    }
                ],
                'lng' => [
                    'required' => true,
                    'type' => 'number',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= -180 && $param <= 180;
                    }
                ],
                'radius' => [
                    'default' => 10,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        $max_radius = get_option('zippicks_favorites_max_radius', 50);
                        return $param > 0 && $param <= $max_radius;
                    }
                ]
            ]
        ]);
        
        // Get favorites by city
        register_rest_route($this->namespace, '/favorites/city/(?P<city>[a-zA-Z0-9-\s]+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_favorites_by_city'],
            'permission_callback' => [$this, 'check_user_permission'],
            'args' => [
                'city' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'state' => [
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Search favorites
        register_rest_route($this->namespace, '/favorites/search', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'search_favorites'],
            'permission_callback' => [$this, 'check_user_permission'],
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'location' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Get user's favorite cities
        register_rest_route($this->namespace, '/favorites/cities', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_favorite_cities'],
            'permission_callback' => [$this, 'check_user_permission']
        ]);
        
        // Export favorites
        register_rest_route($this->namespace, '/favorites/export', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'export_favorites'],
            'permission_callback' => [$this, 'check_user_permission'],
            'args' => [
                'format' => [
                    'default' => 'json',
                    'enum' => ['json', 'csv'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'city' => [
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Check if business is favorited
        register_rest_route($this->namespace, '/favorites/check/(?P<business_id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'check_favorite_status'],
            'permission_callback' => [$this, 'check_user_permission'],
            'args' => [
                'business_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
    }
    
    public function check_user_permission() {
        return is_user_logged_in();
    }
    
    public function save_favorite($request) {
        $user_id = get_current_user_id();
        $business_id = $request->get_param('business_id');
        $notes = $request->get_param('notes');
        
        // Check if business exists
        $business = get_post($business_id);
        if (!$business || $business->post_type !== 'zippicks_business') {
            return new \WP_Error('invalid_business', 'Invalid business ID', ['status' => 404]);
        }
        
        $result = $this->favorites_manager->save_favorite($user_id, $business_id, $notes);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Favorite saved successfully',
            'data' => $result
        ], 201);
    }
    
    public function remove_favorite($request) {
        $user_id = get_current_user_id();
        $favorite_id = $request->get_param('id');
        
        $result = $this->favorites_manager->remove_favorite($user_id, $favorite_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Favorite removed successfully'
        ], 200);
    }
    
    public function get_favorites($request) {
        $user_id = get_current_user_id();
        $params = $request->get_params();
        
        $favorites = $this->favorites_manager->get_user_favorites($user_id, $params);
        
        return new \WP_REST_Response($favorites, 200);
    }
    
    public function get_favorites_near_location($request) {
        $user_id = get_current_user_id();
        $lat = $request->get_param('lat');
        $lng = $request->get_param('lng');
        $radius = $request->get_param('radius');
        
        $favorites = $this->favorites_manager->get_favorites_within_radius(
            $user_id,
            $lat,
            $lng,
            $radius
        );
        
        return new \WP_REST_Response($favorites, 200);
    }
    
    public function get_favorites_by_city($request) {
        $user_id = get_current_user_id();
        $city = $request->get_param('city');
        $state = $request->get_param('state');
        
        $favorites = $this->favorites_manager->get_favorites_by_city($user_id, $city, $state);
        
        return new \WP_REST_Response($favorites, 200);
    }
    
    public function search_favorites($request) {
        $user_id = get_current_user_id();
        $query = $request->get_param('q');
        $location = $request->get_param('location');
        
        $results = $this->favorites_manager->search_favorites($user_id, $query, $location);
        
        return new \WP_REST_Response($results, 200);
    }
    
    public function get_favorite_cities($request) {
        $user_id = get_current_user_id();
        $cities = $this->favorites_manager->get_user_favorite_cities($user_id);
        
        return new \WP_REST_Response($cities, 200);
    }
    
    public function export_favorites($request) {
        $user_id = get_current_user_id();
        $format = $request->get_param('format');
        $city = $request->get_param('city');
        
        $params = [];
        if ($city) {
            $params['city'] = $city;
        }
        
        $favorites = $this->favorites_manager->get_user_favorites($user_id, $params);
        
        if ($format === 'csv') {
            return $this->export_as_csv($favorites['data']);
        }
        
        return new \WP_REST_Response($favorites['data'], 200);
    }
    
    public function check_favorite_status($request) {
        $user_id = get_current_user_id();
        $business_id = $request->get_param('business_id');
        
        $is_favorited = $this->favorites_manager->is_favorited($user_id, $business_id);
        
        return new \WP_REST_Response([
            'is_favorited' => $is_favorited,
            'business_id' => $business_id
        ], 200);
    }
    
    private function export_as_csv($favorites) {
        $csv_data = [];
        $csv_data[] = ['Name', 'Cuisine', 'Address', 'City', 'State', 'Rating', 'Saved Date'];
        
        foreach ($favorites as $favorite) {
            $csv_data[] = [
                $favorite['business']['name'],
                $favorite['business']['cuisine'],
                $favorite['business']['address'],
                $favorite['city'],
                $favorite['state'],
                $favorite['business']['rating'],
                $favorite['saved_date']
            ];
        }
        
        $output = fopen('php://memory', 'w');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        fseek($output, 0);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return new \WP_REST_Response($csv_content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="favorites.csv"'
        ]);
    }
}