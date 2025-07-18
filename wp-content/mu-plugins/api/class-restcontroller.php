<?php
/**
 * REST API Controller
 * 
 * Handles REST API endpoints for the foundation.
 * 
 * @package ZipPicks\Foundation\API
 */

namespace ZipPicks\Foundation\API;

use ZipPicks\Foundation\Core;
use WP_REST_Server;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class RestController {
    
    /**
     * Core instance
     * 
     * @var Core
     */
    private $core;
    
    /**
     * REST namespace
     * 
     * @var string
     */
    private $namespace = ZIPPICKS_API_NAMESPACE;
    
    /**
     * Constructor
     * 
     * @param Core $core Core instance
     */
    public function __construct($core) {
        $this->core = $core;
    }
    
    /**
     * Register routes
     */
    public function register_routes() {
        // Location endpoints
        register_rest_route($this->namespace, '/location/current', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_current_location'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route($this->namespace, '/location/set', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'set_location'],
            'permission_callback' => '__return_true',
            'args' => [
                'zip' => [
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => [$this, 'validate_zip']
                ]
            ]
        ]);
        
        // Vibe endpoints
        register_rest_route($this->namespace, '/vibes', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_vibes'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route($this->namespace, '/vibes/search', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'search_vibes'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string'
                ]
            ]
        ]);
        
        register_rest_route($this->namespace, '/vibes/trending', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_trending_vibes'],
            'permission_callback' => '__return_true',
            'args' => [
                'zip' => [
                    'type' => 'string'
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 10
                ]
            ]
        ]);
        
        // Taste Graph endpoints
        register_rest_route($this->namespace, '/taste/profile', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_taste_profile'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);
        
        register_rest_route($this->namespace, '/taste/preferences', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'update_preferences'],
            'permission_callback' => [$this, 'is_authenticated'],
            'args' => [
                'preferences' => [
                    'required' => true,
                    'type' => 'object'
                ]
            ]
        ]);
        
        register_rest_route($this->namespace, '/taste/recommendations', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_recommendations'],
            'permission_callback' => [$this, 'is_authenticated'],
            'args' => [
                'zip' => [
                    'type' => 'string'
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 10
                ]
            ]
        ]);
        
        // Business scoring endpoints
        register_rest_route($this->namespace, '/businesses/(?P<id>\d+)/score', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_business_score'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        // ZIP intelligence endpoints
        register_rest_route($this->namespace, '/zip/(?P<zip>\d{5})/intel', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_zip_intelligence'],
            'permission_callback' => '__return_true',
            'args' => [
                'zip' => [
                    'validate_callback' => [$this, 'validate_zip']
                ]
            ]
        ]);
        
        register_rest_route($this->namespace, '/zip/nearby', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_nearby_zips'],
            'permission_callback' => '__return_true',
            'args' => [
                'zip' => [
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => [$this, 'validate_zip']
                ],
                'radius' => [
                    'type' => 'integer',
                    'default' => 5
                ]
            ]
        ]);
        
        // System endpoints
        register_rest_route($this->namespace, '/system/health', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_system_health'],
            'permission_callback' => [$this, 'is_admin']
        ]);
    }
    
    /**
     * Get current location
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_current_location($request) {
        $zip_intelligence = $this->core->get_service('zip_intelligence');
        $current_zip = $zip_intelligence->get_user_zip();
        $zip_data = $zip_intelligence->get_zip_data($current_zip);
        
        return new WP_REST_Response([
            'zip' => $current_zip,
            'data' => $zip_data
        ]);
    }
    
    /**
     * Set location
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function set_location($request) {
        $zip = $request->get_param('zip');
        $zip_intelligence = $this->core->get_service('zip_intelligence');
        
        if ($zip_intelligence->set_user_zip($zip)) {
            $zip_data = $zip_intelligence->get_zip_data($zip);
            
            return new WP_REST_Response([
                'success' => true,
                'zip' => $zip,
                'data' => $zip_data
            ]);
        }
        
        return new WP_Error(
            'invalid_zip',
            __('Invalid ZIP code', 'zippicks-foundation'),
            ['status' => 400]
        );
    }
    
    /**
     * Get vibes
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_vibes($request) {
        $vibe_taxonomy = $this->core->get_service('vibe_taxonomy');
        $vibes = $vibe_taxonomy->get_vibes_by_category();
        
        return new WP_REST_Response($vibes);
    }
    
    /**
     * Search vibes
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function search_vibes($request) {
        $query = $request->get_param('q');
        $vibe_taxonomy = $this->core->get_service('vibe_taxonomy');
        
        $results = $vibe_taxonomy->search_vibes($query);
        
        return new WP_REST_Response($results);
    }
    
    /**
     * Get trending vibes
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_trending_vibes($request) {
        $vibe_taxonomy = $this->core->get_service('vibe_taxonomy');
        
        $args = [
            'number' => $request->get_param('limit'),
            'zip' => $request->get_param('zip')
        ];
        
        $trending = $vibe_taxonomy->get_trending_vibes($args);
        
        return new WP_REST_Response($trending);
    }
    
    /**
     * Get taste profile
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_taste_profile($request) {
        $user_id = get_current_user_id();
        $taste_graph = $this->core->get_service('taste_graph');
        
        $profile = $taste_graph->get_user_profile($user_id);
        
        // Remove sensitive data
        unset($profile['behavior_data']);
        unset($profile['social_connections']);
        
        return new WP_REST_Response($profile);
    }
    
    /**
     * Update preferences
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function update_preferences($request) {
        $user_id = get_current_user_id();
        $preferences = $request->get_param('preferences');
        
        $taste_graph = $this->core->get_service('taste_graph');
        $taste_graph->update_preferences($user_id, $preferences);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Preferences updated', 'zippicks-foundation')
        ]);
    }
    
    /**
     * Get recommendations
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_recommendations($request) {
        $user_id = get_current_user_id();
        $taste_graph = $this->core->get_service('taste_graph');
        
        $args = [
            'zip' => $request->get_param('zip'),
            'limit' => $request->get_param('limit')
        ];
        
        $recommendations = $taste_graph->get_recommendations($user_id, $args);
        
        // Fetch business data
        $businesses = [];
        foreach ($recommendations as $business_id) {
            $business = get_post($business_id);
            if ($business) {
                $businesses[] = $this->format_business($business);
            }
        }
        
        return new WP_REST_Response($businesses);
    }
    
    /**
     * Get business score
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_business_score($request) {
        $business_id = $request->get_param('id');
        $scoring_engine = $this->core->get_service('scoring_engine');
        
        $score_data = $scoring_engine->get_master_score($business_id);
        
        if (!$score_data) {
            return new WP_Error(
                'no_score',
                __('No score available for this business', 'zippicks-foundation'),
                ['status' => 404]
            );
        }
        
        return new WP_REST_Response($score_data);
    }
    
    /**
     * Get ZIP intelligence
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_zip_intelligence($request) {
        $zip = $request->get_param('zip');
        $zip_intelligence = $this->core->get_service('zip_intelligence');
        
        $intel = $zip_intelligence->get_zip_intelligence($zip);
        
        if (!$intel) {
            return new WP_Error(
                'invalid_zip',
                __('Invalid ZIP code', 'zippicks-foundation'),
                ['status' => 404]
            );
        }
        
        return new WP_REST_Response($intel);
    }
    
    /**
     * Get nearby ZIPs
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_nearby_zips($request) {
        $zip = $request->get_param('zip');
        $radius = $request->get_param('radius');
        
        $zip_intelligence = $this->core->get_service('zip_intelligence');
        $nearby = $zip_intelligence->get_nearby_zips($zip, $radius);
        
        return new WP_REST_Response($nearby);
    }
    
    /**
     * Get system health
     * 
     * @param \WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_system_health($request) {
        $schema_manager = $this->core->get_service('schema_manager');
        $cache_manager = $this->core->get_service('cache_manager');
        $logger = $this->core->get_service('logger');
        
        $health = [
            'status' => 'healthy',
            'checks' => [
                'database' => $schema_manager->get_table_status(),
                'cache' => $cache_manager->get_stats(),
                'errors' => $logger->get_error_stats(1),
                'php' => [
                    'version' => PHP_VERSION,
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time')
                ],
                'wordpress' => [
                    'version' => get_bloginfo('version'),
                    'multisite' => is_multisite()
                ]
            ]
        ];
        
        return new WP_REST_Response($health);
    }
    
    /**
     * Validate ZIP code
     * 
     * @param string $param Parameter value
     * @return bool Valid
     */
    public function validate_zip($param) {
        return preg_match('/^\d{5}$/', $param);
    }
    
    /**
     * Check if authenticated
     * 
     * @return bool Authenticated
     */
    public function is_authenticated() {
        return is_user_logged_in();
    }
    
    /**
     * Check if admin
     * 
     * @return bool Is admin
     */
    public function is_admin() {
        return current_user_can('manage_options');
    }
    
    /**
     * Format business for API response
     * 
     * @param \WP_Post $business Business post
     * @return array Formatted data
     */
    private function format_business($business) {
        $scoring_engine = $this->core->get_service('scoring_engine');
        $vibe_taxonomy = $this->core->get_service('vibe_taxonomy');
        
        return [
            'id' => $business->ID,
            'title' => $business->post_title,
            'slug' => $business->post_name,
            'excerpt' => get_the_excerpt($business),
            'featured_image' => get_the_post_thumbnail_url($business, 'large'),
            'score' => $scoring_engine->get_master_score($business->ID),
            'vibes' => $vibe_taxonomy->get_business_vibes($business->ID),
            'meta' => [
                'address' => get_post_meta($business->ID, '_zippicks_address', true),
                'phone' => get_post_meta($business->ID, '_zippicks_phone', true),
                'website' => get_post_meta($business->ID, '_zippicks_website', true),
                'price_range' => get_post_meta($business->ID, '_zippicks_price_range', true),
                'hours' => get_post_meta($business->ID, '_zippicks_hours', true)
            ],
            'link' => get_permalink($business)
        ];
    }
}