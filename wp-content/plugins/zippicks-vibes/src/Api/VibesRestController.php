<?php
/**
 * REST API Controller for ZipPicks Vibes
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API Controller class
 */
class VibesRestController extends WP_REST_Controller {
    
    /**
     * Service dependencies
     */
    private $service;
    private $rate_limiter;
    private $nonce_validator;
    private $renderer;
    private $request_validator;
    
    /**
     * Constructor
     */
    public function __construct($service = null, $rate_limiter = null, $nonce_validator = null, $renderer = null, $request_validator = null) {
        $this->namespace = 'zippicks/v2';
        $this->rest_base = 'vibes';
        
        $this->service = $service;
        $this->rate_limiter = $rate_limiter;
        $this->nonce_validator = $nonce_validator;
        $this->renderer = $renderer;
        $this->request_validator = $request_validator;
    }
    
    /**
     * Register REST routes
     */
    public function register_routes() {
        // Main vibes endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_items'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
                'args'                => $this->get_collection_params(),
            ]
        ]);
        
        // Single vibe endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<slug>[\w-]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args'                => [
                    'slug' => [
                        'description' => __('Vibe slug identifier', 'zippicks-vibes'),
                        'type'        => 'string',
                        'required'    => true,
                        'sanitize_callback' => 'sanitize_title',
                    ],
                ],
            ]
        ]);
    }
    
    /**
     * Get collection of vibes
     */
    public function get_items($request) {
        try {
            // Check if this is the AJAX load action
            $action = $request->get_param('action');
            if ($action === 'zippicks_vibes_load') {
                return $this->handle_ajax_load($request);
            }
            
            // Standard REST API response
            $params = [
                'page'     => $request->get_param('page') ?: 1,
                'per_page' => $request->get_param('per_page') ?: 20,
                'status'   => 'active',
                'orderby'  => 'order_position',
                'order'    => 'ASC'
            ];
            
            // Get vibes from service
            if ($this->service && method_exists($this->service, 'getAllVibes')) {
                $vibes = $this->service->getAllVibes($params);
            } else {
                $vibes = [];
            }
            
            return new WP_REST_Response([
                'success' => true,
                'data'    => [
                    'vibes' => $vibes,
                    'total' => count($vibes)
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return new WP_Error(
                'vibes_fetch_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Handle AJAX load request
     */
    private function handle_ajax_load($request) {
        try {
            // Get filters
            $filters = $request->get_param('filters') ?: [];
            
            // Get vibes
            $vibes = [];
            if ($this->service && method_exists($this->service, 'getAllVibes')) {
                $vibes = $this->service->getAllVibes([
                    'status' => 'active',
                    'orderby' => 'order_position',
                    'order' => 'ASC',
                    'limit' => 50
                ]);
            }
            
            // Apply category filter if provided
            if (!empty($filters['category']) && $filters['category'] !== 'all') {
                $filtered_vibes = [];
                foreach ($vibes as $vibe) {
                    $categories = [];
                    if (is_object($vibe) && method_exists($vibe, 'getCategories')) {
                        $categories = $vibe->getCategories();
                    } elseif (is_array($vibe) && isset($vibe['categories'])) {
                        $categories = $vibe['categories'];
                    }
                    
                    foreach ($categories as $cat) {
                        $slug = is_object($cat) ? $cat->slug : ($cat['slug'] ?? '');
                        if ($slug === $filters['category']) {
                            $filtered_vibes[] = $vibe;
                            break;
                        }
                    }
                }
                $vibes = $filtered_vibes;
            }
            
            // Format response
            $formatted_vibes = [];
            foreach ($vibes as $vibe) {
                if (is_object($vibe)) {
                    $formatted_vibes[] = [
                        'id' => $vibe->id ?? 0,
                        'name' => $vibe->name ?? '',
                        'slug' => $vibe->slug ?? '',
                        'description' => $vibe->description ?? '',
                        'icon' => $vibe->icon ?? '',
                        'color' => $vibe->color ?? '#194FAD',
                        'active_count' => $vibe->active_count ?? 0,
                        'categories' => method_exists($vibe, 'getCategories') ? $vibe->getCategories() : []
                    ];
                } else {
                    $formatted_vibes[] = $vibe;
                }
            }
            
            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'vibes' => $formatted_vibes,
                    'total' => count($formatted_vibes)
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return new WP_Error(
                'vibes_load_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Get single vibe
     */
    public function get_item($request) {
        try {
            $slug = $request->get_param('slug');
            
            if ($this->service && method_exists($this->service, 'getVibeBySlug')) {
                $vibe = $this->service->getVibeBySlug($slug);
                
                if (!$vibe) {
                    return new WP_Error(
                        'vibe_not_found',
                        __('Vibe not found', 'zippicks-vibes'),
                        ['status' => 404]
                    );
                }
                
                return new WP_REST_Response([
                    'success' => true,
                    'data'    => $vibe
                ], 200);
            }
            
            return new WP_Error(
                'service_unavailable',
                __('Vibe service unavailable', 'zippicks-vibes'),
                ['status' => 503]
            );
            
        } catch (\Exception $e) {
            return new WP_Error(
                'vibe_fetch_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Check permissions for getting items
     */
    public function get_items_permissions_check($request) {
        // Public endpoint with rate limiting
        if ($this->rate_limiter) {
            $check = $this->rate_limiter->check($request);
            if (is_wp_error($check)) {
                return $check;
            }
        }
        
        return true;
    }
    
    /**
     * Check permissions for getting single item
     */
    public function get_item_permissions_check($request) {
        // Public endpoint with rate limiting
        if ($this->rate_limiter) {
            $check = $this->rate_limiter->check($request);
            if (is_wp_error($check)) {
                return $check;
            }
        }
        
        return true;
    }
    
    /**
     * Get collection parameters
     */
    public function get_collection_params() {
        return [
            'page' => [
                'description'       => __('Current page of the collection.', 'zippicks-vibes'),
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
                'minimum'           => 1,
            ],
            'per_page' => [
                'description'       => __('Maximum number of items to be returned in result set.', 'zippicks-vibes'),
                'type'              => 'integer',
                'default'           => 20,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ],
            'search' => [
                'description'       => __('Limit results to those matching a string.', 'zippicks-vibes'),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'rest_validate_request_arg',
            ],
            'category' => [
                'description'       => __('Limit results to specific category.', 'zippicks-vibes'),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'rest_validate_request_arg',
            ],
            'action' => [
                'description'       => __('Action parameter for AJAX compatibility.', 'zippicks-vibes'),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'filters' => [
                'description'       => __('Filters for vibes.', 'zippicks-vibes'),
                'type'              => 'object',
            ],
            'session_id' => [
                'description'       => __('Session ID for tracking.', 'zippicks-vibes'),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'nonce' => [
                'description'       => __('Security nonce.', 'zippicks-vibes'),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
}