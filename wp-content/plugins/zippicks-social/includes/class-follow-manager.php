<?php
/**
 * Core follow system manager with API integration
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
        
        // Update stats
        $this->update_follow_stats($followed_id, $followed_type);
        $this->update_follow_stats($follower_id, 'user');
        
        // Trigger action hook
        do_action('zippicks_social_after_unfollow', $follower_id, $followed_id, $followed_type);
        
        return [
            'success' => true,
            'message' => __('Successfully unfollowed', 'zippicks-social')
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
        // Check cache first
        $cache_key = "following_{$follower_id}_{$followed_id}_{$followed_type}";
        
        if ($this->cache) {
            $cached = $this->cache->get($cache_key, self::CACHE_GROUP);
            if ($cached !== false) {
                return (bool) $cached;
            }
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_follows';
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE follower_id = %d 
             AND followed_id = %d 
             AND followed_type = %s 
             AND status = 'active'",
            $follower_id,
            $followed_id,
            $followed_type
        );
        
        $count = (int) $wpdb->get_var($query);
        $is_following = $count > 0;
        
        // Cache result
        if ($this->cache) {
            $this->cache->set($cache_key, $is_following, self::CACHE_GROUP, 300);
        }
        
        return $is_following;
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
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_follows';
        
        // Default arguments
        $defaults = [
            'limit' => 20,
            'offset' => 0,
            'status' => 'active',
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        $args = wp_parse_args($args, $defaults);
        
        // Build query
        $query = $wpdb->prepare(
            "SELECT f.*, u.display_name, u.user_email 
             FROM {$table} f
             INNER JOIN {$wpdb->users} u ON f.follower_id = u.ID
             WHERE f.followed_id = %d 
             AND f.followed_type = %s 
             AND f.status = %s
             ORDER BY f.{$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            $entity_id,
            $entity_type,
            $args['status'],
            $args['limit'],
            $args['offset']
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return is_array($results) ? $results : [];
    }
    
    /**
     * Get entities that a user is following
     *
     * @param int $user_id User ID
     * @param array $args Query arguments
     * @return array Array of followed entities
     */
    public function get_following(int $user_id, array $args = []): array {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_follows';
        
        // Default arguments
        $defaults = [
            'limit' => 20,
            'offset' => 0,
            'entity_type' => null,
            'status' => 'active',
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        $args = wp_parse_args($args, $defaults);
        
        // Build query
        $where_type = '';
        if ($args['entity_type'] && in_array($args['entity_type'], self::VALID_ENTITY_TYPES)) {
            $where_type = $wpdb->prepare(" AND followed_type = %s", $args['entity_type']);
        }
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE follower_id = %d 
             AND status = %s
             {$where_type}
             ORDER BY {$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            $user_id,
            $args['status'],
            $args['limit'],
            $args['offset']
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return is_array($results) ? $results : [];
    }
    
    /**
     * Get followers count for an entity
     *
     * @param int $entity_id Entity ID
     * @param string $entity_type Type of entity
     * @return int Number of followers
     */
    public function get_followers_count(int $entity_id, string $entity_type = 'user'): int {
        // Check cache first
        $cache_key = "followers_count_{$entity_id}_{$entity_type}";
        
        if ($this->cache) {
            $cached = $this->cache->get($cache_key, self::CACHE_GROUP);
            if ($cached !== false) {
                return (int) $cached;
            }
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_follows';
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE followed_id = %d 
             AND followed_type = %s 
             AND status = 'active'",
            $entity_id,
            $entity_type
        );
        
        $count = (int) $wpdb->get_var($query);
        
        // Cache result
        if ($this->cache) {
            $this->cache->set($cache_key, $count, self::CACHE_GROUP, 300);
        }
        
        return $count;
    }
    
    /**
     * Get following count for a user
     *
     * @param int $user_id User ID
     * @return int Number of entities being followed
     */
    public function get_following_count(int $user_id): int {
        // Check cache first
        $cache_key = "following_count_{$user_id}";
        
        if ($this->cache) {
            $cached = $this->cache->get($cache_key, self::CACHE_GROUP);
            if ($cached !== false) {
                return (int) $cached;
            }
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_follows';
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE follower_id = %d 
             AND status = 'active'",
            $user_id
        );
        
        $count = (int) $wpdb->get_var($query);
        
        // Cache result
        if ($this->cache) {
            $this->cache->set($cache_key, $count, self::CACHE_GROUP, 300);
        }
        
        return $count;
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
        
        // Get follower count
        $show_count = isset($args['show_count']) ? $args['show_count'] : true;
        $followers_count = $show_count ? $this->get_followers_count($entity_id, $entity_type) : 0;
        
        // Build button HTML
        $button_text = $is_following ? __('Following', 'zippicks-social') : __('Follow', 'zippicks-social');
        $button_class = $args['class'] . ' ' . ($is_following ? 'zps-following' : 'zps-not-following');
        $button_action = $is_following ? 'unfollow' : 'follow';
        
        ob_start();
        ?>
        <button type="button" 
                class="<?php echo esc_attr($button_class); ?>" 
                data-entity-id="<?php echo esc_attr($entity_id); ?>"
                data-entity-type="<?php echo esc_attr($entity_type); ?>"
                data-action="<?php echo esc_attr($button_action); ?>"
                data-nonce="<?php echo wp_create_nonce('zippicks_social_follow'); ?>">
            <span class="zps-button-text"><?php echo esc_html($button_text); ?></span>
            <?php if ($show_count && $followers_count > 0): ?>
                <span class="zps-followers-count"><?php echo number_format_i18n($followers_count); ?></span>
            <?php endif; ?>
        </button>
        <?php
        
        $html = ob_get_clean();
        
        return apply_filters('zippicks_social_follow_button_html', $html, $entity_id, $entity_type, $args);
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
        // Validate entity type
        if (!in_array($followed_type, self::VALID_ENTITY_TYPES)) {
            return [
                'valid' => false,
                'error' => __('Invalid entity type', 'zippicks-social')
            ];
        }
        
        // Validate follower exists
        if (!get_userdata($follower_id)) {
            return [
                'valid' => false,
                'error' => __('Invalid follower', 'zippicks-social')
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
            case 'critic':
                return (bool) get_userdata($entity_id);
                
            case 'business':
                $post = get_post($entity_id);
                return $post && $post->post_type === 'zippicks_business';
                
            case 'list':
                $post = get_post($entity_id);
                return $post && in_array($post->post_type, ['zippicks_list', 'master_critic_list']);
                
            default:
                return false;
        }
    }
    
    /**
     * Check rate limit for follow actions
     *
     * @param int $user_id
     * @return bool
     */
    private function check_rate_limit(int $user_id): bool {
        $rate_limit = (int) get_option('zippicks_social_follow_rate_limit', 50);
        $cache_key = "follow_rate_limit_{$user_id}";
        
        $count = (int) get_transient($cache_key);
        
        if ($count >= $rate_limit) {
            return false;
        }
        
        set_transient($cache_key, $count + 1, HOUR_IN_SECONDS);
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
        if (!$this->cache) {
            return;
        }
        
        // Clear specific caches
        $cache_keys = [
            "following_{$follower_id}_{$followed_id}_{$followed_type}",
            "followers_count_{$followed_id}_{$followed_type}",
            "following_count_{$follower_id}",
        ];
        
        foreach ($cache_keys as $key) {
            $this->cache->delete($key, self::CACHE_GROUP);
        }
    }
    
    /**
     * Update follow statistics
     *
     * @param int $entity_id
     * @param string $entity_type
     * @return void
     */
    private function update_follow_stats(int $entity_id, string $entity_type): void {
        global $wpdb;
        $stats_table = $wpdb->prefix . 'zippicks_follow_stats';
        $follows_table = $wpdb->prefix . 'zippicks_follows';
        
        // Calculate counts
        $followers_count = $this->get_followers_count($entity_id, $entity_type);
        $following_count = 0;
        
        if ($entity_type === 'user') {
            $following_count = $this->get_following_count($entity_id);
        }
        
        // Update or insert stats
        $wpdb->replace(
            $stats_table,
            [
                'entity_id' => $entity_id,
                'entity_type' => $entity_type,
                'followers_count' => $followers_count,
                'following_count' => $following_count,
                'last_calculated' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%d', '%s']
        );
    }
    
    /**
     * Log activity
     *
     * @param int $actor_id
     * @param string $action
     * @param string $object_type
     * @param int $object_id
     * @return void
     */
    private function log_activity(int $actor_id, string $action, string $object_type, int $object_id): void {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_activities';
        
        $wpdb->insert(
            $table,
            [
                'actor_id' => $actor_id,
                'actor_type' => 'user',
                'action' => $action,
                'object_type' => $object_type,
                'object_id' => $object_id,
                'visibility' => 'public',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );
    }
}