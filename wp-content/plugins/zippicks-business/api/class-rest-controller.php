<?php
/**
 * ZipPicks Business REST API Controller
 *
 * Extends WordPress REST API with business verification
 * and vibe management endpoints.
 *
 * @package ZipPicks_Business
 * @since 1.0.0
 */
class ZipPicks_Business_REST_Controller extends WP_REST_Controller {
    
    /**
     * Namespace for the REST API
     * @var string
     */
    protected $namespace = 'zippicks/v1';
    
    /**
     * Rest base for the endpoints
     * @var string  
     */
    protected $rest_base = 'business';
    
    /**
     * API sync service instance
     * @var ZipPicks_Business_API_Sync_Service
     */
    private $sync_service;
    
    /**
     * Logger instance
     * @var object
     */
    private $logger = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get Foundation services if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $this->logger = zippicks()->get('logger');
        }
        
        // Initialize sync service
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'api/class-api-sync-service.php';
        $this->sync_service = new ZipPicks_Business_API_Sync_Service();
    }
    
    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes() {
        // Verify business with API
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/verify', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'verify_business'),
                'permission_callback' => array($this, 'verify_permissions_check'),
                'args' => $this->get_verify_params()
            ),
            'schema' => array($this, 'get_verify_schema')
        ));
        
        // Get business vibes
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/vibes', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_business_vibes'),
                'permission_callback' => array($this, 'get_vibes_permissions_check'),
                'args' => $this->get_vibes_params()
            ),
            'schema' => array($this, 'get_vibes_schema')
        ));
        
        // Sync business with API
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/sync', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'sync_business'),
                'permission_callback' => array($this, 'sync_permissions_check'),
                'args' => $this->get_sync_params()
            ),
            'schema' => array($this, 'get_sync_schema')
        ));
        
        // Search ZipBusiness API
        register_rest_route($this->namespace, '/' . $this->rest_base . '/search', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'search_businesses'),
                'permission_callback' => array($this, 'search_permissions_check'),
                'args' => $this->get_search_params()
            ),
            'schema' => array($this, 'get_search_schema')
        ));
        
        // Batch operations
        register_rest_route($this->namespace, '/' . $this->rest_base . '/batch', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'batch_operations'),
                'permission_callback' => array($this, 'batch_permissions_check'),
                'args' => $this->get_batch_params()
            ),
            'schema' => array($this, 'get_batch_schema')
        ));
    }
    
    /**
     * Verify business with API
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function verify_business($request) {
        $post_id = (int) $request['id'];
        $zpid = $request['zpid'] ?? null;
        
        // Validate business post
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'zippicks_business') {
            return new WP_Error(
                'invalid_business',
                __('Invalid business ID', 'zippicks-business'),
                array('status' => 404)
            );
        }
        
        // Sync with API
        $result = $this->sync_service->sync_business($post_id, $zpid);
        
        if (is_wp_error($result)) {
            if ($this->logger) {
                $this->logger->error('REST API verification failed', array(
                    'post_id' => $post_id,
                    'zpid' => $zpid,
                    'error' => $result->get_error_message()
                ));
            }
            
            return new WP_Error(
                'verification_failed',
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        // Get updated data
        $verification_data = array(
            'id' => $post_id,
            'zpid' => get_post_meta($post_id, 'zpid', true),
            'verified' => get_post_meta($post_id, 'api_verified', true),
            'confidence_score' => get_post_meta($post_id, 'api_confidence_score', true),
            'last_sync' => get_post_meta($post_id, 'last_api_sync', true),
            'vibes' => json_decode(get_post_meta($post_id, 'api_vibes', true), true) ?: array()
        );
        
        if ($this->logger) {
            $this->logger->info('Business verified via REST API', array(
                'post_id' => $post_id,
                'zpid' => $verification_data['zpid']
            ));
        }
        
        return rest_ensure_response($verification_data);
    }
    
    /**
     * Get business vibes
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function get_business_vibes($request) {
        $post_id = (int) $request['id'];
        
        // Validate business post
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'zippicks_business') {
            return new WP_Error(
                'invalid_business',
                __('Invalid business ID', 'zippicks-business'),
                array('status' => 404)
            );
        }
        
        global $wpdb;
        
        // Get vibes from database
        $vibes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . ZipPicks_Business_Database::get_vibes_table() . "
             WHERE business_id = %d
             ORDER BY confidence_score DESC",
            $post_id
        ));
        
        // Get API vibes from meta
        $api_vibes = json_decode(get_post_meta($post_id, 'api_vibes', true), true) ?: array();
        
        $response_data = array(
            'id' => $post_id,
            'vibes' => $vibes,
            'api_vibes' => $api_vibes,
            'verified' => get_post_meta($post_id, 'api_verified', true),
            'last_sync' => get_post_meta($post_id, 'last_api_sync', true)
        );
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Sync business with API
     *
     * @param WP_REST_Request $request Full data about the request  
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function sync_business($request) {
        $post_id = (int) $request['id'];
        $force = $request['force'] ?? false;
        
        // Validate business post
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'zippicks_business') {
            return new WP_Error(
                'invalid_business',
                __('Invalid business ID', 'zippicks-business'),
                array('status' => 404)
            );
        }
        
        // Check if sync is needed (unless forced)
        if (!$force) {
            $last_sync = get_post_meta($post_id, 'last_api_sync', true);
            if ($last_sync && (time() - strtotime($last_sync)) < 3600) { // 1 hour
                return new WP_Error(
                    'sync_too_recent',
                    __('Business was synced recently. Use force=true to override.', 'zippicks-business'),
                    array('status' => 429)
                );
            }
        }
        
        // Perform sync
        $result = $this->sync_service->sync_business($post_id);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                'sync_failed',
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        return $this->get_business_vibes($request);
    }
    
    /**
     * Search businesses in ZipBusiness API
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function search_businesses($request) {
        $name = sanitize_text_field($request['name']);
        $location = sanitize_text_field($request['location']);
        
        if (empty($name) || empty($location)) {
            return new WP_Error(
                'missing_params',
                __('Both name and location are required', 'zippicks-business'),
                array('status' => 400)
            );
        }
        
        $results = $this->sync_service->search_business($name, $location);
        
        if (is_wp_error($results)) {
            return new WP_Error(
                'search_failed',
                $results->get_error_message(),
                array('status' => 400)
            );
        }
        
        return rest_ensure_response(array(
            'query' => array(
                'name' => $name,
                'location' => $location
            ),
            'results' => $results,
            'count' => count($results)
        ));
    }
    
    /**
     * Handle batch operations
     *
     * @param WP_REST_Request $request Full data about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function batch_operations($request) {
        $operation = $request['operation'];
        $business_ids = $request['business_ids'];
        
        if (!in_array($operation, array('sync', 'verify'))) {
            return new WP_Error(
                'invalid_operation',
                __('Invalid operation. Must be sync or verify.', 'zippicks-business'),
                array('status' => 400)
            );
        }
        
        if (empty($business_ids) || !is_array($business_ids)) {
            return new WP_Error(
                'missing_business_ids',
                __('business_ids must be a non-empty array', 'zippicks-business'),
                array('status' => 400)
            );
        }
        
        // Limit batch size
        if (count($business_ids) > 50) {
            return new WP_Error(
                'batch_too_large',
                __('Maximum 50 businesses per batch', 'zippicks-business'),
                array('status' => 400)
            );
        }
        
        $results = $this->sync_service->batch_sync_businesses($business_ids);
        
        return rest_ensure_response(array(
            'operation' => $operation,
            'results' => $results,
            'summary' => array(
                'total' => count($business_ids),
                'success' => count(array_filter($results, function($r) { return $r['success']; })),
                'failed' => count(array_filter($results, function($r) { return !$r['success']; }))
            )
        ));
    }
    
    /**
     * Permission check for verify endpoint
     */
    public function verify_permissions_check($request) {
        return current_user_can('edit_posts');
    }
    
    /**
     * Permission check for get vibes endpoint
     */
    public function get_vibes_permissions_check($request) {
        return true; // Public endpoint
    }
    
    /**
     * Permission check for sync endpoint
     */
    public function sync_permissions_check($request) {
        return current_user_can('edit_posts');
    }
    
    /**
     * Permission check for search endpoint
     */
    public function search_permissions_check($request) {
        return current_user_can('edit_posts');
    }
    
    /**
     * Permission check for batch endpoint
     */
    public function batch_permissions_check($request) {
        return current_user_can('manage_options');
    }
    
    /**
     * Get verify endpoint parameters
     */
    public function get_verify_params() {
        return array(
            'id' => array(
                'description' => __('Business post ID', 'zippicks-business'),
                'type' => 'integer',
                'required' => true
            ),
            'zpid' => array(
                'description' => __('ZipBusiness ID to verify against', 'zippicks-business'),
                'type' => 'string',
                'required' => false
            )
        );
    }
    
    /**
     * Get vibes endpoint parameters
     */
    public function get_vibes_params() {
        return array(
            'id' => array(
                'description' => __('Business post ID', 'zippicks-business'),
                'type' => 'integer',
                'required' => true
            )
        );
    }
    
    /**
     * Get sync endpoint parameters
     */
    public function get_sync_params() {
        return array(
            'id' => array(
                'description' => __('Business post ID', 'zippicks-business'),
                'type' => 'integer',
                'required' => true
            ),
            'force' => array(
                'description' => __('Force sync even if recently synced', 'zippicks-business'),
                'type' => 'boolean',
                'default' => false
            )
        );
    }
    
    /**
     * Get search endpoint parameters
     */
    public function get_search_params() {
        return array(
            'name' => array(
                'description' => __('Business name to search for', 'zippicks-business'),
                'type' => 'string',
                'required' => true
            ),
            'location' => array(
                'description' => __('Location to search in', 'zippicks-business'),
                'type' => 'string',
                'required' => true
            )
        );
    }
    
    /**
     * Get batch endpoint parameters
     */
    public function get_batch_params() {
        return array(
            'operation' => array(
                'description' => __('Batch operation to perform', 'zippicks-business'),
                'type' => 'string',
                'enum' => array('sync', 'verify'),
                'required' => true
            ),
            'business_ids' => array(
                'description' => __('Array of business post IDs', 'zippicks-business'),
                'type' => 'array',
                'items' => array('type' => 'integer'),
                'required' => true
            )
        );
    }
    
    /**
     * Schema definitions for documentation
     */
    public function get_verify_schema() {
        return array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'Business Verification',
            'type' => 'object',
            'properties' => array(
                'id' => array('type' => 'integer'),
                'zpid' => array('type' => 'string'),
                'verified' => array('type' => 'boolean'),
                'confidence_score' => array('type' => 'number'),
                'last_sync' => array('type' => 'string'),
                'vibes' => array('type' => 'array')
            )
        );
    }
    
    public function get_vibes_schema() {
        return array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'Business Vibes',
            'type' => 'object',
            'properties' => array(
                'id' => array('type' => 'integer'),
                'vibes' => array('type' => 'array'),
                'api_vibes' => array('type' => 'array'),
                'verified' => array('type' => 'boolean'),
                'last_sync' => array('type' => 'string')
            )
        );
    }
    
    public function get_sync_schema() {
        return $this->get_vibes_schema();
    }
    
    public function get_search_schema() {
        return array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'Business Search Results',
            'type' => 'object',
            'properties' => array(
                'query' => array('type' => 'object'),
                'results' => array('type' => 'array'),
                'count' => array('type' => 'integer')
            )
        );
    }
    
    public function get_batch_schema() {
        return array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'Batch Operation Results',
            'type' => 'object',
            'properties' => array(
                'operation' => array('type' => 'string'),
                'results' => array('type' => 'object'),
                'summary' => array('type' => 'object')
            )
        );
    }
}