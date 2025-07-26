<?php
/**
 * Core follow system manager with full API integration
 *
 * @package ZipPicks_Social
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_Follow_Manager
 * 
 * Handles all follow/unfollow operations via ZipBusiness API
 * This replaces the old local database version
 */
class ZipPicks_Social_Follow_Manager {
    
    /**
     * Valid entity types
     */
    const VALID_ENTITY_TYPES = ['user', 'critic', 'business', 'list'];
    
    /**
     * Valid follow statuses
     */
    const VALID_STATUSES = ['active', 'pending', 'blocked', 'muted'];
    
    /**
     * Cache group name
     */
    const CACHE_GROUP = 'zippicks_social';
    
    /**
     * API client instance
     *
     * @var ZipPicks_Social_API_Client
     */
    private $api_client;
    
    /**
     * Logger instance
     *
     * @var object|null
     */
    private $logger = null;
    
    /**
     * Cache instance
     *
     * @var object|null
     */
    private $cache = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get API client
        $this->api_client = ZipPicks_Social_API_Client::get_instance();
        
        // Get logger if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $this->logger = zippicks()->get('logger');
        }
        
        // Get cache if available
        if (function_exists('zippicks') && zippicks()->has('cache')) {
            $this->cache = zippicks()->get('cache');
        }
    }
    
    /**
     * Follow an entity
     *
     * @param int $follower_id User ID of follower
     * @param int $followed_id Entity ID to follow
     * @param string $followed_type Type of entity
     * @param array $options Additional options
     * @return array Result array with success status
     */
    public function follow(int $follower_id, int $followed_id, string $followed_type = 'user', array $options = []): array {
        // Validate inputs
        $validation = $this->validate_follow_params($follower_id, $followed_id, $followed_type);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }
        
        // Check if already following via API
        $is_following = $this->api_client->is_following($follower_id, $followed_id, $followed_type);
        if (!is_wp_error($is_following) && $is_following['is_following']) {
            return [
                'success' => false,
                'error' => __('Already following this entity', 'zippicks-social')
            ];
        }
        
        // Check rate limit
        if (!$this->check_rate_limit($follower_id)) {
            return [
                'success' => false,
                'error' => __('Follow rate limit exceeded. Please try again later.', 'zippicks-social')
            ];
        }
        
        // Follow via API
        $result = $this->api_client->follow($follower_id, $followed_id, $followed_type);
        
        if (is_wp_error($result)) {
            if ($this->logger) {
                $this->logger->error('Failed to create follow relationship', [
                    'follower_id' => $follower_id,
                    'followed_id' => $followed_id,
                    'followed_type' => $followed_type,
                    'error' => $result->get_error_message()
                ]);
            }
            
            return [
                'success' => false,
                'error' => $result->get_error_message()
            ];
        }
        
        // Clear caches
        $this->clear_follow_caches($follower_id, $followed_id, $followed_type);
        
        // Trigger action hook
        do_action('zippicks_social_after_follow', $follower_id, $followed_id, $followed_type);
        
        return [
            'success' => true,
            'follow_id' => $result['follow_id'],
            'message' => $result['message'] ?? __('Successfully followed', 'zippicks-social')
        ];
    }
    
    /**
     * Unfollow an entity
     *
     * @param int $follower_id User ID of follower
     * @param int $followed_id Entity ID to unfollow
     * @param string $followed_type Type of entity
     * @return array Result array with success status
     */
    public function unfollow(int $follower_id, int $followed_id, string $followed_type = 'user'): array {
        // Validate inputs
        $validation = $this->validate_follow_params($follower_id, $followed_id, $followed_type);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }
        
        // Check if following via API
        $is_following = $this->api_client->is_following($follower_id, $followed_id, $followed_type);
        if (is_wp_error($is_following) || !$is_following['is_following']) {
            return [
                'success' => false,
                'error' => __('Not following this entity', 'zippicks-social')
            ];
        }
        
        // Unfollow via API
        $result = $this->api_client->unfollow($follower_id, $followed_id, $followed_type);
        
        if (is_wp_error($result)) {
            if ($this->logger) {
                $this->logger->error('Failed to delete follow relationship', [
                    'follower_id' => $follower_id,
                    'followed_id' => $followed_id,
                    'followed_type' => $followed_type,
                    'error' => $result->get_error_message()
                ]);
            }
            
            return [
                'success' => false,
                'error' => $result->get_error_message()
            ];
        }
        
        // Clear caches
        $this->clear_follow_caches($follower_id, $followed_id, $followed_type);
        
        // Trigger action hook
        do_action('zippicks_social_after_unfollow', $follower_id, $followed_id, $followed_type);
        
        return [
            'success' => true,
            'message' => $result['message'] ?? __('Successfully unfollowed', 'zippicks-social')
        ];
    }
    
    /**
     * Check if user is following an entity
     *
     * @param int $follower_id User ID of follower
     * @param int $followed_id Entity ID
     * @param string $followed_type Type of entity
     * @return bool
     */
    public function is_following(int $follower_id, int $followed_id, string $followed_type = 'user'): bool {
        $result = $this->api_client->is_following($follower_id, $followed_id, $followed_type);
        
        if (is_wp_error($result)) {
            if ($this->logger) {
                $this->logger->error('Failed to check follow status', [
                    'error' => $result->get_error_message()
                ]);
            }
            return false;
        }
        
        return $result['is_following'] ?? false;
    }
    
    /**
     * Get followers of an entity
     *
     * @param int $entity_id Entity ID
     * @param string $entity_type Type of entity
     * @param array $args Query arguments
     * @return array Array of follower data
     */
    public function get_followers(int $entity_id, string $entity_type = 'user', array $args = []): array {
        $result = $this->api_client->get_followers($entity_id, $entity_type, $args);
        
        if (is_wp_error($result)) {
            if ($this->logger) {
                $this->logger->error('Failed to get followers', [
                    'error' => $result->get_error_message(),
                    'entity_id' => $entity_id,
                    'entity_type' => $entity_type
                ]);
            }
            return [];
        }
        
        return $result['data'] ?? [];
    }
    
    /**
     * Get entities that a user is following
     *
     * @param int $user_id User ID
     * @param array $args Query arguments
     * @return array Array of followed entities
     */
    public function get_following(int $user_id, array $args = []): array {
        $result = $this->api_client->get_following($user_id, $args);
        
        if (is_wp_error($result)) {
            if ($this->logger) {
                $this->logger->error('Failed to get following', [
                    'error' => $result->get_error_message(),
                    'user_id' => $user_id
                ]);
            }
            return [];
        }
        
        return $result['data'] ?? [];
    }
    
    /**
     * Get followers count for an entity
     *
     * @param int $entity_id Entity ID
     * @param string $entity_type Type of entity
     * @return int Number of followers
     */
    public function get_followers_count(int $entity_id, string $entity_type = 'user'): int {
        $stats = $this->api_client->get_stats($entity_id, $entity_type);
        
        if (is_wp_error($stats)) {
            if ($this->logger) {
                $this->logger->error('Failed to get follower count', [
                    'error' => $stats->get_error_message(),
                    'entity_id' => $entity_id,
                    'entity_type' => $entity_type
                ]);
            }
            return 0;
        }
        
        return $stats['followers_count'] ?? 0;
    }
    
    /**
     * Get following count for a user
     *
     * @param int $user_id User ID
     * @return int Number of entities being followed
     */
    public function get_following_count(int $user_id): int {
        $stats = $this->api_client->get_stats($user_id, 'user');
        
        if (is_wp_error($stats)) {
            if ($this->logger) {
                $this->logger->error('Failed to get following count', [
                    'error' => $stats->get_error_message(),
                    'user_id' => $user_id
                ]);
            }
            return 0;
        }
        
        return $stats['following_count'] ?? 0;
    }
    
    /**
     * Get complete follow statistics
     *
     * @param int $entity_id Entity ID
     * @param string $entity_type Type of entity
     * @return array Statistics array
     */
    public function get_stats(int $entity_id, string $entity_type = 'user'): array {
        $stats = $this->api_client->get_stats($entity_id, $entity_type);
        
        if (is_wp_error($stats)) {
            if ($this->logger) {
                $this->logger->error('Failed to get stats', [
                    'error' => $stats->get_error_message(),
                    'entity_id' => $entity_id,
                    'entity_type' => $entity_type
                ]);
            }
            return [
                'followers_count' => 0,
                'following_count' => 0,
                'mutual_count' => 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * Render follow button HTML
     *
     * @param int $entity_id Entity ID
     * @param string $entity_type Type of entity
     * @param array $args Display arguments
     * @return string HTML for follow button
     */
    public function render_follow_button(int $entity_id, string $entity_type = 'user', array $args = []): string {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '';
        }
        
        $current_user_id = get_current_user_id();
        
        // Don't show follow button for own profile
        if ($entity_type === 'user' && $entity_id === $current_user_id) {
            return '';
        }
        
        // Check if following
        $is_following = $this->is_following($current_user_id, $entity_id, $entity_type);
        
        // Default args
        $defaults = [
            'button_class' => 'zps-follow-button',
            'following_text' => __('Following', 'zippicks-social'),
            'follow_text' => __('Follow', 'zippicks-social'),
            'show_count' => true,
            'size' => 'medium' // small, medium, large
        ];
        $args = wp_parse_args($args, $defaults);
        
        // Get follower count if needed
        $follower_count = '';
        if ($args['show_count']) {
            $count = $this->get_followers_count($entity_id, $entity_type);
            $follower_count = sprintf('<span class="zps-follow-count">%s</span>', number_format($count));
        }
        
        // Build button HTML
        $button_class = $args['button_class'] . ' zps-follow-size-' . $args['size'];
        if ($is_following) {
            $button_class .= ' zps-following';
        }
        
        $button_text = $is_following ? $args['following_text'] : $args['follow_text'];
        
        ob_start();
        ?>
        <div class="zps-follow-container" 
             data-entity-id="<?php echo esc_attr($entity_id); ?>"
             data-entity-type="<?php echo esc_attr($entity_type); ?>">
            <button class="<?php echo esc_attr($button_class); ?>"
                    data-entity-id="<?php echo esc_attr($entity_id); ?>"
                    data-entity-type="<?php echo esc_attr($entity_type); ?>"
                    data-following="<?php echo $is_following ? 'true' : 'false'; ?>">
                <span class="zps-button-text"><?php echo esc_html($button_text); ?></span>
                <?php echo $follower_count; ?>
            </button>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Validate follow parameters
     *
     * @param int $follower_id
     * @param int $followed_id
     * @param string $followed_type
     * @return array Validation result
     */
    private function validate_follow_params(int $follower_id, int $followed_id, string $followed_type): array {
        // Validate follower
        if (!get_userdata($follower_id)) {
            return [
                'valid' => false,
                'error' => __('Invalid follower ID', 'zippicks-social')
            ];
        }
        
        // Validate entity type
        if (!in_array($followed_type, self::VALID_ENTITY_TYPES)) {
            return [
                'valid' => false,
                'error' => __('Invalid entity type', 'zippicks-social')
            ];
        }
        
        // Validate followed entity exists
        if (!$this->entity_exists($followed_id, $followed_type)) {
            return [
                'valid' => false,
                'error' => __('Entity does not exist', 'zippicks-social')
            ];
        }
        
        // Can't follow yourself
        if ($followed_type === 'user' && $follower_id === $followed_id) {
            return [
                'valid' => false,
                'error' => __('Cannot follow yourself', 'zippicks-social')
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Check if entity exists
     *
     * @param int $entity_id
     * @param string $entity_type
     * @return bool
     */
    private function entity_exists(int $entity_id, string $entity_type): bool {
        switch ($entity_type) {
            case 'user':
                return (bool) get_userdata($entity_id);
                
            case 'critic':
                $post = get_post($entity_id);
                return $post && $post->post_type === 'zippicks_critic' && $post->post_status === 'publish';
                
            case 'business':
                $post = get_post($entity_id);
                return $post && $post->post_type === 'zippicks_business' && $post->post_status === 'publish';
                
            case 'list':
                $post = get_post($entity_id);
                return $post && $post->post_type === 'zippicks_list' && $post->post_status === 'publish';
                
            default:
                return false;
        }
    }
    
    /**
     * Check rate limit for user
     *
     * @param int $user_id
     * @return bool
     */
    private function check_rate_limit(int $user_id): bool {
        $transient_key = 'zps_follow_rate_' . $user_id;
        $count = get_transient($transient_key);
        
        if ($count === false) {
            set_transient($transient_key, 1, 300); // 5 minutes
            return true;
        }
        
        $limit = apply_filters('zippicks_social_follow_rate_limit', 20);
        
        if ($count >= $limit) {
            return false;
        }
        
        set_transient($transient_key, $count + 1, 300);
        return true;
    }
    
    /**
     * Clear follow-related caches
     *
     * @param int $follower_id
     * @param int $followed_id
     * @param string $followed_type
     * @return void
     */
    private function clear_follow_caches(int $follower_id, int $followed_id, string $followed_type): void {
        // Clear API client cache
        $this->api_client->clear_cache();
        
        // Clear WordPress transients
        delete_transient('zps_followers_' . $followed_id . '_' . $followed_type);
        delete_transient('zps_following_' . $follower_id);
        delete_transient('zps_follow_stats_' . $followed_id . '_' . $followed_type);
        delete_transient('zps_follow_stats_' . $follower_id . '_user');
    }
    
    /**
     * Bulk follow operation
     *
     * @param int $follower_id
     * @param array $entities Array of ['id' => x, 'type' => y]
     * @return array Results
     */
    public function bulk_follow(int $follower_id, array $entities): array {
        $result = $this->api_client->bulk_follow($follower_id, $entities);
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => $result->get_error_message(),
                'results' => []
            ];
        }
        
        // Clear caches
        $this->api_client->clear_cache();
        
        // Trigger action for each successful follow
        if (!empty($result['successful'])) {
            foreach ($result['successful'] as $entity) {
                do_action('zippicks_social_after_follow', $follower_id, $entity['id'], $entity['type']);
            }
        }
        
        return $result;
    }
    
    /**
     * Get mutual followers between two users
     *
     * @param int $user_a_id
     * @param int $user_b_id
     * @return array
     */
    public function get_mutual_follows(int $user_a_id, int $user_b_id): array {
        $result = $this->api_client->get_mutual_connections($user_a_id, $user_b_id, 'user');
        
        if (is_wp_error($result)) {
            return [];
        }
        
        return $result['mutual'] ?? [];
    }
}