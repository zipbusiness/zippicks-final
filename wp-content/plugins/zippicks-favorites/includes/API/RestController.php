<?php
namespace ZipPicks\Favorites\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API Controller for favorites
 */
class RestController extends WP_REST_Controller {
    
    protected $namespace = 'zippicks/v1';
    protected $rest_base = 'favorites';
    
    private $api_client;
    private $cache;
    
    public function __construct($api_client, $cache) {
        $this->api_client = $api_client;
        $this->cache = $cache;
    }
    
    /**
     * Register REST routes
     */
    public function register_routes() {
        // Add/Remove favorite
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_favorite'],
                'permission_callback' => [$this, 'create_favorite_permissions_check'],
                'args' => $this->get_create_favorite_args(),
            ],
        ]);
        
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_favorite'],
                'permission_callback' => [$this, 'delete_favorite_permissions_check'],
            ],
        ]);
        
        // Get user favorites
        register_rest_route($this->namespace, '/users/(?P<user_id>[\d]+)/favorites', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_user_favorites'],
                'permission_callback' => [$this, 'get_favorites_permissions_check'],
                'args' => $this->get_favorites_query_args(),
            ],
        ]);
        
        // Get favorite cities
        register_rest_route($this->namespace, '/users/(?P<user_id>[\d]+)/favorites/cities', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_favorite_cities'],
                'permission_callback' => [$this, 'get_favorites_permissions_check'],
            ],
        ]);
        
        // Search favorites
        register_rest_route($this->namespace, '/users/(?P<user_id>[\d]+)/favorites/search', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'search_favorites'],
                'permission_callback' => [$this, 'get_favorites_permissions_check'],
                'args' => $this->get_search_args(),
            ],
        ]);
        
        // Get nearby favorites
        register_rest_route($this->namespace, '/users/(?P<user_id>[\d]+)/favorites/nearby', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_nearby_favorites'],
                'permission_callback' => [$this, 'get_favorites_permissions_check'],
                'args' => $this->get_nearby_args(),
            ],
        ]);
        
        // Check if favorited
        register_rest_route($this->namespace, '/users/(?P<user_id>[\d]+)/favorites/check', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'check_favorited'],
                'permission_callback' => [$this, 'get_favorites_permissions_check'],
                'args' => [
                    'business_id' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                ],
            ],
        ]);
    }
    
    /**
     * Create favorite
     */
    public function create_favorite($request) {
        $user_id = get_current_user_id();
        $business_id = $request->get_param('business_id');
        
        try {
            // Get context for tracking
            $context = [
                'source' => $request->get_param('source') ?? 'wordpress',
                'page' => $request->get_param('page') ?? '',
                'referer' => wp_get_referer(),
            ];
            
            $response = $this->api_client->add_favorite($user_id, $business_id, $context);
            
            // Clear relevant caches
            $this->cache->clear_user_cache($user_id);
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $response
            ], 201);
            
        } catch (\Exception $e) {
            return new WP_Error(
                'favorite_creation_failed',
                $e->getMessage(),
                ['status' => $e->getCode() ?: 500]
            );
        }
    }
    
    /**
     * Delete favorite
     */
    public function delete_favorite($request) {
        $user_id = get_current_user_id();
        $favorite_id = $request->get_param('id');
        
        try {
            $response = $this->api_client->remove_favorite($user_id, $favorite_id);
            
            // Clear relevant caches
            $this->cache->clear_user_cache($user_id);
            
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Favorite removed', 'zippicks-favorites')
            ]);
            
        } catch (\Exception $e) {
            return new WP_Error(
                'favorite_deletion_failed',
                $e->getMessage(),
                ['status' => $e->getCode() ?: 500]
            );
        }
    }
    
    /**
     * Get user favorites
     */
    public function get_user_favorites($request) {
        $user_id = $request->get_param('user_id');
        $params = $request->get_query_params();
        
        // Build cache key based on params
        $cache_key = 'user_favs_' . $user_id . '_' . md5(serialize($params));
        
        // Try cache first
        $cached = $this->cache->get($cache_key);
        if ($cached !== false) {
            return new WP_REST_Response($cached);
        }
        
        try {
            $response = $this->api_client->get_user_favorites($user_id, $params);
            
            // Cache the response
            $this->cache->set($cache_key, $response, 300);
            
            return new WP_REST_Response($response);
            
        } catch (\Exception $e) {
            return new WP_Error(
                'favorites_fetch_failed',
                $e->getMessage(),
                ['status' => $e->getCode() ?: 500]
            );
        }
    }
    
    /**
     * Get favorite cities
     */
    public function get_favorite_cities($request) {
        $user_id = $request->get_param('user_id');
        
        // Try cache first
        $cached = $this->cache->get_favorite_cities($user_id);
        if ($cached !== false) {
            return new WP_REST_Response(['data' => $cached]);
        }
        
        try {
            $response = $this->api_client->get_favorite_cities($user_id);
            
            // Cache the response
            $this->cache->cache_favorite_cities($user_id, $response['data']);
            
            return new WP_REST_Response($response);
            
        } catch (\Exception $e) {
            return new WP_Error(
                'cities_fetch_failed',
                $e->getMessage(),
                ['status' => $e->getCode() ?: 500]
            );
        }
    }
    
    /**
     * Search favorites
     */
    public function search_favorites($request) {
        $user_id = $request->get_param('user_id');
        $query = $request->get_param('q');
        $params = $request->get_query_params();
        
        try {
            $response = $this->api_client->search_favorites($user_id, $query, $params);
            
            return new WP_REST_Response($response);
            
        } catch (\Exception $e) {
            return new WP_Error(
                'search_failed',
                $e->getMessage(),
                ['status' => $e->getCode() ?: 500]
            );
        }
    }
    
    /**
     * Get nearby favorites
     */
    public function get_nearby_favorites($request) {
        $user_id = $request->get_param('user_id');
        $lat = $request->get_param('lat');
        $lng = $request->get_param('lng');
        $radius = $request->get_param('radius') ?? 5;
        
        try {
            $response = $this->api_client->get_nearby_favorites($user_id, $lat, $lng, $radius);
            
            return new WP_REST_Response($response);
            
        } catch (\Exception $e) {
            return new WP_Error(
                'nearby_fetch_failed',
                $e->getMessage(),
                ['status' => $e->getCode() ?: 500]
            );
        }
    }
    
    /**
     * Check if business is favorited
     */
    public function check_favorited($request) {
        $user_id = $request->get_param('user_id');
        $business_id = $request->get_param('business_id');
        
        $cache_key = "favorited_{$user_id}_{$business_id}";
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            return new WP_REST_Response(['is_favorited' => $cached]);
        }
        
        try {
            // This would be a specific API endpoint in production
            $favorites = $this->api_client->get_user_favorites($user_id, [
                'business_id' => $business_id,
                'limit' => 1
            ]);
            
            $is_favorited = !empty($favorites['data']);
            $this->cache->set($cache_key, $is_favorited, 300);
            
            return new WP_REST_Response(['is_favorited' => $is_favorited]);
            
        } catch (\Exception $e) {
            return new WP_Error(
                'check_failed',
                $e->getMessage(),
                ['status' => $e->getCode() ?: 500]
            );
        }
    }
    
    /**
     * Permission check for creating favorites
     */
    public function create_favorite_permissions_check($request) {
        return is_user_logged_in();
    }
    
    /**
     * Permission check for deleting favorites
     */
    public function delete_favorite_permissions_check($request) {
        return is_user_logged_in();
    }
    
    /**
     * Permission check for getting favorites
     */
    public function get_favorites_permissions_check($request) {
        $user_id = $request->get_param('user_id');
        
        // Users can view their own favorites
        if (get_current_user_id() == $user_id) {
            return true;
        }
        
        // Admins can view any favorites
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // In future, check privacy settings
        return false;
    }
    
    /**
     * Get arguments for creating favorite
     */
    private function get_create_favorite_args() {
        return [
            'business_id' => [
                'required' => true,
                'type' => 'string',
                'description' => 'The ID of the business to favorite',
            ],
            'source' => [
                'type' => 'string',
                'description' => 'Where the favorite action originated',
                'enum' => ['search', 'list', 'vibe_page', 'business_page', 'wordpress'],
                'default' => 'wordpress',
            ],
            'page' => [
                'type' => 'string',
                'description' => 'The page where the action occurred',
            ],
        ];
    }
    
    /**
     * Get arguments for favorites query
     */
    private function get_favorites_query_args() {
        return [
            'page' => [
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
            ],
            'per_page' => [
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'city' => [
                'type' => 'string',
                'description' => 'Filter by city',
            ],
            'state' => [
                'type' => 'string',
                'description' => 'Filter by state',
            ],
            'zip' => [
                'type' => 'string',
                'description' => 'Filter by zip code',
                'pattern' => '^[0-9]{5}$',
            ],
            'radius' => [
                'type' => 'integer',
                'description' => 'Radius in miles for location-based search',
                'default' => 5,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'sort' => [
                'type' => 'string',
                'enum' => ['date', 'name', 'distance', 'rating'],
                'default' => 'date',
            ],
            'vibe' => [
                'type' => 'string',
                'description' => 'Filter by vibe slug',
            ],
        ];
    }
    
    /**
     * Get arguments for search
     */
    private function get_search_args() {
        return [
            'q' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Search query',
                'minLength' => 2,
            ],
        ];
    }
    
    /**
     * Get arguments for nearby search
     */
    private function get_nearby_args() {
        return [
            'lat' => [
                'required' => true,
                'type' => 'number',
                'description' => 'Latitude',
                'minimum' => -90,
                'maximum' => 90,
            ],
            'lng' => [
                'required' => true,
                'type' => 'number',
                'description' => 'Longitude',
                'minimum' => -180,
                'maximum' => 180,
            ],
            'radius' => [
                'type' => 'integer',
                'description' => 'Radius in miles',
                'default' => 5,
                'minimum' => 1,
                'maximum' => 100,
            ],
        ];
    }
}