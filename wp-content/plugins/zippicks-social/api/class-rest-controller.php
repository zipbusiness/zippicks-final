<?php
/**
 * REST API controller for ZipPicks Social
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_REST_Controller
 * 
 * Handles REST API endpoints for the follow system
 */
class ZipPicks_Social_REST_Controller {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'zippicks-social/v1';
    
    /**
     * Follow manager instance
     *
     * @var ZipPicks_Social_Follow_Manager
     */
    private $follow_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->follow_manager = new ZipPicks_Social_Follow_Manager();
    }
    
    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_routes(): void {
        // Follow endpoint
        register_rest_route(self::NAMESPACE, '/follow', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_follow'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => $this->get_follow_args()
        ]);
        
        // Unfollow endpoint
        register_rest_route(self::NAMESPACE, '/unfollow', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_unfollow'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => $this->get_follow_args()
        ]);
        
        // Check if following
        register_rest_route(self::NAMESPACE, '/is-following', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'check_is_following'],
            'permission_callback' => '__return_true',
            'args' => $this->get_follow_args()
        ]);
        
        // Get followers
        register_rest_route(self::NAMESPACE, '/followers/(?P<entity_type>[\w]+)/(?P<entity_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_followers'],
            'permission_callback' => '__return_true',
            'args' => $this->get_list_args()
        ]);
        
        // Get following
        register_rest_route(self::NAMESPACE, '/following/(?P<user_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_following'],
            'permission_callback' => '__return_true',
            'args' => $this->get_list_args()
        ]);
        
        // Get stats
        register_rest_route(self::NAMESPACE, '/stats/(?P<entity_type>[\w]+)/(?P<entity_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_stats'],
            'permission_callback' => '__return_true'
        ]);
        
        // Get suggestions
        register_rest_route(self::NAMESPACE, '/suggestions/(?P<user_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_suggestions'],
            'permission_callback' => '__return_true',
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 5,
                    'minimum' => 1,
                    'maximum' => 20
                ]
            ]
        ]);
        
        // Activity feed
        register_rest_route(self::NAMESPACE, '/activity-feed/(?P<user_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_activity_feed'],
            'permission_callback' => [$this, 'check_feed_permission'],
            'args' => $this->get_feed_args()
        ]);
    }
    
    /**
     * Handle follow request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_follow(WP_REST_Request $request): WP_REST_Response {
        $follower_id = get_current_user_id();
        $followed_id = (int) $request->get_param('entity_id');
        $followed_type = $request->get_param('entity_type');
        
        $result = $this->follow_manager->follow($follower_id, $followed_id, $followed_type);
        
        if ($result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'message' => $result['message'],
                'follow_id' => $result['follow_id'],
                'followers_count' => $this->follow_manager->get_followers_count($followed_id, $followed_type)
            ], 200);
        }
        
        return new WP_REST_Response([
            'success' => false,
            'message' => $result['error']
        ], 400);
    }
    
    /**
     * Handle unfollow request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_unfollow(WP_REST_Request $request): WP_REST_Response {
        $follower_id = get_current_user_id();
        $followed_id = (int) $request->get_param('entity_id');
        $followed_type = $request->get_param('entity_type');
        
        $result = $this->follow_manager->unfollow($follower_id, $followed_id, $followed_type);
        
        if ($result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'message' => $result['message'],
                'followers_count' => $this->follow_manager->get_followers_count($followed_id, $followed_type)
            ], 200);
        }
        
        return new WP_REST_Response([
            'success' => false,
            'message' => $result['error']
        ], 400);
    }
    
    /**
     * Check if user is following entity
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function check_is_following(WP_REST_Request $request): WP_REST_Response {
        $follower_id = $request->get_param('follower_id') ?: get_current_user_id();
        $followed_id = (int) $request->get_param('entity_id');
        $followed_type = $request->get_param('entity_type');
        
        $is_following = $this->follow_manager->is_following($follower_id, $followed_id, $followed_type);
        
        return new WP_REST_Response([
            'is_following' => $is_following,
            'follower_id' => $follower_id,
            'entity_id' => $followed_id,
            'entity_type' => $followed_type
        ], 200);
    }
    
    /**
     * Get followers list
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_followers(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('entity_id');
        $entity_type = $request->get_param('entity_type');
        
        $args = [
            'limit' => $request->get_param('per_page') ?: 20,
            'offset' => (($request->get_param('page') ?: 1) - 1) * ($request->get_param('per_page') ?: 20)
        ];
        
        $followers = $this->follow_manager->get_followers($entity_id, $entity_type, $args);
        $total = $this->follow_manager->get_followers_count($entity_id, $entity_type);
        
        // Add user avatars and profile URLs
        foreach ($followers as &$follower) {
            $follower['avatar'] = get_avatar_url($follower['follower_id']);
            $follower['profile_url'] = get_author_posts_url($follower['follower_id']);
        }
        
        $response = new WP_REST_Response($followers, 200);
        $response->header('X-Total-Count', $total);
        
        return $response;
    }
    
    /**
     * Get following list
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_following(WP_REST_Request $request): WP_REST_Response {
        $user_id = (int) $request->get_param('user_id');
        
        $args = [
            'limit' => $request->get_param('per_page') ?: 20,
            'offset' => (($request->get_param('page') ?: 1) - 1) * ($request->get_param('per_page') ?: 20),
            'entity_type' => $request->get_param('entity_type')
        ];
        
        $following = $this->follow_manager->get_following($user_id, $args);
        $total = $this->follow_manager->get_following_count($user_id);
        
        // Add entity details based on type
        foreach ($following as &$item) {
            $item['entity_details'] = $this->get_entity_details($item['followed_id'], $item['followed_type']);
        }
        
        $response = new WP_REST_Response($following, 200);
        $response->header('X-Total-Count', $total);
        
        return $response;
    }
    
    /**
     * Get follow statistics
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_stats(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('entity_id');
        $entity_type = $request->get_param('entity_type');
        
        $stats = [
            'followers_count' => $this->follow_manager->get_followers_count($entity_id, $entity_type),
            'following_count' => 0,
            'mutual_count' => 0
        ];
        
        // Add following count for users
        if ($entity_type === 'user') {
            $stats['following_count'] = $this->follow_manager->get_following_count($entity_id);
            
            // Calculate mutual follows
            if (is_user_logged_in()) {
                $current_user_id = get_current_user_id();
                $is_following = $this->follow_manager->is_following($current_user_id, $entity_id, 'user');
                $is_follower = $this->follow_manager->is_following($entity_id, $current_user_id, 'user');
                $stats['is_mutual'] = $is_following && $is_follower;
            }
        }
        
        return new WP_REST_Response($stats, 200);
    }
    
    /**
     * Get follow suggestions
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_suggestions(WP_REST_Request $request): WP_REST_Response {
        $user_id = (int) $request->get_param('user_id');
        $limit = (int) $request->get_param('limit');
        
        // This is a placeholder - implement suggestion algorithm
        $suggestions = [];
        
        return new WP_REST_Response($suggestions, 200);
    }
    
    /**
     * Get activity feed
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_activity_feed(WP_REST_Request $request): WP_REST_Response {
        $user_id = (int) $request->get_param('user_id');
        $page = (int) $request->get_param('page') ?: 1;
        $per_page = (int) $request->get_param('per_page') ?: 20;
        
        // This is a placeholder - implement activity feed
        $activities = [];
        
        return new WP_REST_Response($activities, 200);
    }
    
    /**
     * Check permission for authenticated endpoints
     *
     * @return bool
     */
    public function check_permission(): bool {
        return is_user_logged_in();
    }
    
    /**
     * Check permission for activity feed
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_feed_permission(WP_REST_Request $request): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = (int) $request->get_param('user_id');
        $current_user_id = get_current_user_id();
        
        // Users can only view their own feed or public feeds
        return $user_id === $current_user_id || current_user_can('manage_options');
    }
    
    /**
     * Get follow endpoint arguments
     *
     * @return array
     */
    private function get_follow_args(): array {
        return [
            'entity_id' => [
                'type' => 'integer',
                'required' => true,
                'minimum' => 1
            ],
            'entity_type' => [
                'type' => 'string',
                'required' => true,
                'enum' => ['user', 'critic', 'business', 'list']
            ]
        ];
    }
    
    /**
     * Get list endpoint arguments
     *
     * @return array
     */
    private function get_list_args(): array {
        return [
            'page' => [
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1
            ],
            'per_page' => [
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'maximum' => 100
            ]
        ];
    }
    
    /**
     * Get feed endpoint arguments
     *
     * @return array
     */
    private function get_feed_args(): array {
        return [
            'page' => [
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1
            ],
            'per_page' => [
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'maximum' => 50
            ],
            'filter' => [
                'type' => 'string',
                'enum' => ['all', 'reviews', 'favorites', 'follows', 'lists']
            ]
        ];
    }
    
    /**
     * Get entity details
     *
     * @param int $entity_id
     * @param string $entity_type
     * @return array
     */
    private function get_entity_details(int $entity_id, string $entity_type): array {
        switch ($entity_type) {
            case 'user':
            case 'critic':
                $user = get_userdata($entity_id);
                if (!$user) {
                    return [];
                }
                return [
                    'name' => $user->display_name,
                    'avatar' => get_avatar_url($entity_id),
                    'url' => get_author_posts_url($entity_id)
                ];
                
            case 'business':
                $post = get_post($entity_id);
                if (!$post) {
                    return [];
                }
                return [
                    'name' => $post->post_title,
                    'url' => get_permalink($entity_id),
                    'thumbnail' => get_the_post_thumbnail_url($entity_id)
                ];
                
            case 'list':
                $post = get_post($entity_id);
                if (!$post) {
                    return [];
                }
                return [
                    'name' => $post->post_title,
                    'url' => get_permalink($entity_id)
                ];
                
            default:
                return [];
        }
    }
}