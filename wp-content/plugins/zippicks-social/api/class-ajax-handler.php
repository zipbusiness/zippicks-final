<?php
/**
 * AJAX handler for ZipPicks Social
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_AJAX_Handler
 * 
 * Handles AJAX requests for backward compatibility
 */
class ZipPicks_Social_AJAX_Handler {
    
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
        
        // Register AJAX actions
        add_action('wp_ajax_zippicks_follow', [$this, 'handle_follow']);
        add_action('wp_ajax_zippicks_unfollow', [$this, 'handle_unfollow']);
        add_action('wp_ajax_zippicks_get_followers', [$this, 'get_followers']);
        add_action('wp_ajax_nopriv_zippicks_get_followers', [$this, 'get_followers']);
        add_action('wp_ajax_zippicks_get_following', [$this, 'get_following']);
        add_action('wp_ajax_nopriv_zippicks_get_following', [$this, 'get_following']);
        add_action('wp_ajax_zippicks_check_follow_status', [$this, 'check_follow_status']);
        add_action('wp_ajax_nopriv_zippicks_check_follow_status', [$this, 'check_follow_status']);
    }
    
    /**
     * Handle follow AJAX request
     *
     * @return void
     */
    public function handle_follow(): void {
        // Verify nonce
        if (!check_ajax_referer('zippicks_social_follow', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed', 'zippicks-social')
            ], 403);
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('You must be logged in to follow', 'zippicks-social')
            ], 401);
        }
        
        // Get parameters
        $entity_id = isset($_POST['entity_id']) ? (int) $_POST['entity_id'] : 0;
        $entity_type = isset($_POST['entity_type']) ? sanitize_text_field($_POST['entity_type']) : 'user';
        
        if (!$entity_id) {
            wp_send_json_error([
                'message' => __('Invalid entity ID', 'zippicks-social')
            ], 400);
        }
        
        // Process follow
        $follower_id = get_current_user_id();
        $result = $this->follow_manager->follow($follower_id, $entity_id, $entity_type);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                'follow_id' => $result['follow_id'],
                'followers_count' => $this->follow_manager->get_followers_count($entity_id, $entity_type),
                'button_text' => __('Following', 'zippicks-social'),
                'button_class' => 'zps-following'
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['error']
            ], 400);
        }
    }
    
    /**
     * Handle unfollow AJAX request
     *
     * @return void
     */
    public function handle_unfollow(): void {
        // Verify nonce
        if (!check_ajax_referer('zippicks_social_follow', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed', 'zippicks-social')
            ], 403);
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('You must be logged in to unfollow', 'zippicks-social')
            ], 401);
        }
        
        // Get parameters
        $entity_id = isset($_POST['entity_id']) ? (int) $_POST['entity_id'] : 0;
        $entity_type = isset($_POST['entity_type']) ? sanitize_text_field($_POST['entity_type']) : 'user';
        
        if (!$entity_id) {
            wp_send_json_error([
                'message' => __('Invalid entity ID', 'zippicks-social')
            ], 400);
        }
        
        // Process unfollow
        $follower_id = get_current_user_id();
        $result = $this->follow_manager->unfollow($follower_id, $entity_id, $entity_type);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                'followers_count' => $this->follow_manager->get_followers_count($entity_id, $entity_type),
                'button_text' => __('Follow', 'zippicks-social'),
                'button_class' => 'zps-not-following'
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['error']
            ], 400);
        }
    }
    
    /**
     * Get followers via AJAX
     *
     * @return void
     */
    public function get_followers(): void {
        // Get parameters
        $entity_id = isset($_GET['entity_id']) ? (int) $_GET['entity_id'] : 0;
        $entity_type = isset($_GET['entity_type']) ? sanitize_text_field($_GET['entity_type']) : 'user';
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        
        if (!$entity_id) {
            wp_send_json_error([
                'message' => __('Invalid entity ID', 'zippicks-social')
            ], 400);
        }
        
        // Get followers
        $args = [
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page
        ];
        
        $followers = $this->follow_manager->get_followers($entity_id, $entity_type, $args);
        $total = $this->follow_manager->get_followers_count($entity_id, $entity_type);
        
        // Add user details
        foreach ($followers as &$follower) {
            $follower['avatar'] = get_avatar_url($follower['follower_id'], ['size' => 64]);
            $follower['profile_url'] = get_author_posts_url($follower['follower_id']);
            
            // Check if current user follows this follower
            if (is_user_logged_in()) {
                $current_user_id = get_current_user_id();
                $follower['is_following'] = $this->follow_manager->is_following(
                    $current_user_id, 
                    $follower['follower_id'], 
                    'user'
                );
            }
        }
        
        wp_send_json_success([
            'followers' => $followers,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'pages' => ceil($total / $per_page)
        ]);
    }
    
    /**
     * Get following via AJAX
     *
     * @return void
     */
    public function get_following(): void {
        // Get parameters
        $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
        $entity_type = isset($_GET['entity_type']) ? sanitize_text_field($_GET['entity_type']) : null;
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        
        if (!$user_id) {
            wp_send_json_error([
                'message' => __('Invalid user ID', 'zippicks-social')
            ], 400);
        }
        
        // Get following
        $args = [
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'entity_type' => $entity_type
        ];
        
        $following = $this->follow_manager->get_following($user_id, $args);
        $total = $this->follow_manager->get_following_count($user_id);
        
        // Add entity details
        foreach ($following as &$item) {
            $item['entity_details'] = $this->get_entity_details($item['followed_id'], $item['followed_type']);
            
            // Check if current user follows this entity
            if (is_user_logged_in()) {
                $current_user_id = get_current_user_id();
                $item['is_following'] = $this->follow_manager->is_following(
                    $current_user_id, 
                    $item['followed_id'], 
                    $item['followed_type']
                );
            }
        }
        
        wp_send_json_success([
            'following' => $following,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'pages' => ceil($total / $per_page)
        ]);
    }
    
    /**
     * Check follow status via AJAX
     *
     * @return void
     */
    public function check_follow_status(): void {
        // Get parameters
        $entity_id = isset($_GET['entity_id']) ? (int) $_GET['entity_id'] : 0;
        $entity_type = isset($_GET['entity_type']) ? sanitize_text_field($_GET['entity_type']) : 'user';
        $follower_id = isset($_GET['follower_id']) ? (int) $_GET['follower_id'] : get_current_user_id();
        
        if (!$entity_id) {
            wp_send_json_error([
                'message' => __('Invalid entity ID', 'zippicks-social')
            ], 400);
        }
        
        $is_following = $this->follow_manager->is_following($follower_id, $entity_id, $entity_type);
        $followers_count = $this->follow_manager->get_followers_count($entity_id, $entity_type);
        
        wp_send_json_success([
            'is_following' => $is_following,
            'followers_count' => $followers_count,
            'button_text' => $is_following ? __('Following', 'zippicks-social') : __('Follow', 'zippicks-social'),
            'button_class' => $is_following ? 'zps-following' : 'zps-not-following'
        ]);
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
                    'avatar' => get_avatar_url($entity_id, ['size' => 64]),
                    'url' => get_author_posts_url($entity_id),
                    'bio' => get_user_meta($entity_id, 'description', true)
                ];
                
            case 'business':
                $post = get_post($entity_id);
                if (!$post) {
                    return [];
                }
                return [
                    'name' => $post->post_title,
                    'url' => get_permalink($entity_id),
                    'thumbnail' => get_the_post_thumbnail_url($entity_id, 'thumbnail'),
                    'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 20)
                ];
                
            case 'list':
                $post = get_post($entity_id);
                if (!$post) {
                    return [];
                }
                return [
                    'name' => $post->post_title,
                    'url' => get_permalink($entity_id),
                    'author' => get_the_author_meta('display_name', $post->post_author),
                    'date' => get_the_date('', $post)
                ];
                
            default:
                return [];
        }
    }
}