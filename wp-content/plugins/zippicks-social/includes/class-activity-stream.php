<?php
/**
 * Activity Stream Manager
 *
 * @package ZipPicks_Social
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_Activity_Stream
 * 
 * Manages activity feed operations with real API integration
 */
class ZipPicks_Social_Activity_Stream {
    
    /**
     * API client instance
     *
     * @var ZipPicks_Social_API_Client
     */
    private $api_client;
    
    /**
     * Cache manager instance
     *
     * @var ZipPicks_Social_Cache_Manager
     */
    private $cache;
    
    /**
     * Logger instance
     *
     * @var object|null
     */
    private $logger;
    
    /**
     * Activity types configuration
     *
     * @var array
     */
    private $activity_types = [
        'follow_user' => [
            'icon' => 'user-plus',
            'template' => '{actor} started following {object}',
            'cache_ttl' => 300
        ],
        'follow_critic' => [
            'icon' => 'star',
            'template' => '{actor} started following critic {object}',
            'cache_ttl' => 300
        ],
        'follow_business' => [
            'icon' => 'building',
            'template' => '{actor} started following {object}',
            'cache_ttl' => 300
        ],
        'favorite_restaurant' => [
            'icon' => 'heart',
            'template' => '{actor} favorited {object}',
            'cache_ttl' => 300
        ],
        'review_restaurant' => [
            'icon' => 'pen',
            'template' => '{actor} reviewed {object}',
            'cache_ttl' => 600
        ],
        'create_list' => [
            'icon' => 'list',
            'template' => '{actor} created a new list: {object}',
            'cache_ttl' => 600
        ],
        'add_to_list' => [
            'icon' => 'plus-circle',
            'template' => '{actor} added {object} to {metadata.list_name}',
            'cache_ttl' => 300
        ],
        'milestone_followers' => [
            'icon' => 'trophy',
            'template' => '{actor} reached {metadata.count} followers!',
            'cache_ttl' => 3600
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = ZipPicks_Social_API_Client::get_instance();
        $this->cache = new ZipPicks_Social_Cache_Manager();
        
        // Use Foundation logger if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $this->logger = zippicks()->get('logger');
        }
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks() {
        // Hook into various actions to record activities
        add_action('zippicks_social_after_follow', [$this, 'record_follow_activity'], 10, 3);
        add_action('zippicks_favorites_added', [$this, 'record_favorite_activity'], 10, 2);
        add_action('publish_zippicks_review', [$this, 'record_review_activity'], 10, 2);
        add_action('zippicks_list_created', [$this, 'record_list_activity'], 10, 2);
        add_action('zippicks_social_milestone_reached', [$this, 'record_milestone_activity'], 10, 3);
        
        // Clean up old activities periodically
        add_action('zippicks_social_cleanup_activities', [$this, 'cleanup_old_activities']);
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('zippicks_social_cleanup_activities')) {
            wp_schedule_event(time(), 'daily', 'zippicks_social_cleanup_activities');
        }
    }
    
    /**
     * Record an activity
     *
     * @param int $actor_id User performing the action
     * @param string $action Activity type
     * @param string $object_type Type of object acted upon
     * @param int $object_id ID of object acted upon
     * @param array $metadata Additional activity data
     * @return bool|int Activity ID on success, false on failure
     */
    public function record_activity($actor_id, $action, $object_type, $object_id, $metadata = []) {
        // Validate activity type
        if (!isset($this->activity_types[$action])) {
            if ($this->logger) {
                $this->logger->warning('Unknown activity type', ['action' => $action]);
            }
            return false;
        }
        
        // Build activity data
        $activity_data = [
            'actor_id' => $actor_id,
            'action' => $action,
            'object_type' => $object_type,
            'object_id' => $object_id,
            'metadata' => $metadata,
            'visibility' => $this->determine_visibility($actor_id)
        ];
        
        // Record via API
        $result = $this->api_client->record_activity($activity_data);
        
        if (is_wp_error($result)) {
            if ($this->logger) {
                $this->logger->error('Failed to record activity', [
                    'error' => $result->get_error_message(),
                    'activity' => $activity_data
                ]);
            }
            return false;
        }
        
        // Clear relevant caches
        $this->clear_activity_caches($actor_id);
        
        // Trigger notification system
        do_action('zippicks_social_activity_recorded', $result['id'], $activity_data);
        
        return $result['id'];
    }
    
    /**
     * Get activity feed for a user
     *
     * @param int $user_id User to get feed for
     * @param array $args Query arguments
     * @return array Activity items
     */
    public function get_feed($user_id, $args = []) {
        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'include_self' => true,
            'activity_types' => []
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Calculate offset from page
        $args['offset'] = ($args['page'] - 1) * $args['per_page'];
        $args['limit'] = $args['per_page'];
        unset($args['page'], $args['per_page']);
        
        // Try cache first
        $cache_key = 'feed_' . md5($user_id . serialize($args));
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Get from API
        $result = $this->api_client->get_activity_feed($user_id, $args);
        
        if (is_wp_error($result)) {
            if ($this->logger) {
                $this->logger->error('Failed to get activity feed', [
                    'error' => $result->get_error_message(),
                    'user_id' => $user_id
                ]);
            }
            return [
                'activities' => [],
                'total' => 0,
                'has_more' => false
            ];
        }
        
        // Process activities for display
        $activities = $this->process_activities($result['data']);
        
        $feed_data = [
            'activities' => $activities,
            'total' => $result['total'],
            'has_more' => $result['has_more']
        ];
        
        // Cache for short period
        $this->cache->set($cache_key, $feed_data, 300);
        
        return $feed_data;
    }
    
    /**
     * Process activities for display
     *
     * @param array $activities Raw activity data
     * @return array Processed activities
     */
    private function process_activities($activities) {
        $processed = [];
        
        foreach ($activities as $activity) {
            $processed_activity = [
                'id' => $activity['id'],
                'type' => $activity['action'],
                'actor' => $this->get_actor_data($activity['actor_id'], $activity['actor_type']),
                'object' => $this->get_object_data($activity['object_type'], $activity['object_id']),
                'metadata' => $activity['metadata'],
                'formatted_text' => $this->format_activity_text($activity),
                'icon' => $this->activity_types[$activity['action']]['icon'] ?? 'circle',
                'timestamp' => strtotime($activity['created_at']),
                'time_ago' => human_time_diff(strtotime($activity['created_at']), current_time('timestamp')),
                'url' => $this->get_activity_url($activity)
            ];
            
            $processed[] = $processed_activity;
        }
        
        return $processed;
    }
    
    /**
     * Get actor data
     *
     * @param int $actor_id
     * @param string $actor_type
     * @return array
     */
    private function get_actor_data($actor_id, $actor_type) {
        switch ($actor_type) {
            case 'user':
                $user = get_user_by('id', $actor_id);
                if ($user) {
                    return [
                        'id' => $actor_id,
                        'type' => 'user',
                        'name' => $user->display_name,
                        'avatar' => get_avatar_url($actor_id),
                        'url' => get_author_posts_url($actor_id)
                    ];
                }
                break;
                
            case 'critic':
                // Get critic data from Core plugin
                $critic = get_post($actor_id);
                if ($critic && $critic->post_type === 'zippicks_critic') {
                    return [
                        'id' => $actor_id,
                        'type' => 'critic',
                        'name' => $critic->post_title,
                        'avatar' => get_the_post_thumbnail_url($actor_id, 'thumbnail'),
                        'url' => get_permalink($actor_id)
                    ];
                }
                break;
        }
        
        return [
            'id' => $actor_id,
            'type' => $actor_type,
            'name' => 'Unknown',
            'avatar' => '',
            'url' => '#'
        ];
    }
    
    /**
     * Get object data based on type and ID
     *
     * @param string $object_type
     * @param int $object_id
     * @return array
     */
    private function get_object_data($object_type, $object_id) {
        switch ($object_type) {
            case 'user':
                return $this->get_actor_data($object_id, 'user');
                
            case 'critic':
                return $this->get_actor_data($object_id, 'critic');
                
            case 'business':
            case 'restaurant':
                // Get from API or cache
                $cache_key = "business_data_{$object_id}";
                $cached = $this->cache->get($cache_key);
                
                if ($cached !== false) {
                    return $cached;
                }
                
                // Try WordPress post first
                $business = get_post($object_id);
                if ($business && $business->post_type === 'zippicks_business') {
                    $data = [
                        'id' => $object_id,
                        'type' => 'business',
                        'name' => $business->post_title,
                        'url' => get_permalink($object_id),
                        'zpid' => get_post_meta($object_id, 'zpid', true)
                    ];
                    $this->cache->set($cache_key, $data, 3600);
                    return $data;
                }
                break;
                
            case 'list':
                $list = get_post($object_id);
                if ($list && $list->post_type === 'zippicks_list') {
                    return [
                        'id' => $object_id,
                        'type' => 'list',
                        'name' => $list->post_title,
                        'url' => get_permalink($object_id)
                    ];
                }
                break;
        }
        
        return [
            'id' => $object_id,
            'type' => $object_type,
            'name' => 'Unknown',
            'url' => '#'
        ];
    }
    
    /**
     * Format activity text
     *
     * @param array $activity
     * @return string
     */
    private function format_activity_text($activity) {
        $template = $this->activity_types[$activity['action']]['template'] ?? '{actor} performed an action';
        
        $actor = $this->get_actor_data($activity['actor_id'], $activity['actor_type']);
        $object = $this->get_object_data($activity['object_type'], $activity['object_id']);
        
        $replacements = [
            '{actor}' => sprintf('<a href="%s" class="activity-actor">%s</a>', 
                esc_url($actor['url']), 
                esc_html($actor['name'])
            ),
            '{object}' => sprintf('<a href="%s" class="activity-object">%s</a>', 
                esc_url($object['url']), 
                esc_html($object['name'])
            )
        ];
        
        // Handle metadata replacements
        if (!empty($activity['metadata'])) {
            foreach ($activity['metadata'] as $key => $value) {
                $replacements['{metadata.' . $key . '}'] = esc_html($value);
            }
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Get activity URL
     *
     * @param array $activity
     * @return string
     */
    private function get_activity_url($activity) {
        // Return URL to the object being acted upon
        $object = $this->get_object_data($activity['object_type'], $activity['object_id']);
        return $object['url'];
    }
    
    /**
     * Determine activity visibility
     *
     * @param int $user_id
     * @return string
     */
    private function determine_visibility($user_id) {
        // Check user privacy settings
        $privacy = get_user_meta($user_id, 'zippicks_privacy_settings', true);
        
        if (!empty($privacy['activity_visibility'])) {
            return $privacy['activity_visibility'];
        }
        
        return 'public'; // Default to public
    }
    
    /**
     * Clear activity-related caches
     *
     * @param int $user_id
     * @return void
     */
    private function clear_activity_caches($user_id) {
        // Clear feed caches for the user and their followers
        $this->cache->delete_group('feed_' . $user_id);
        
        // Clear follower feeds
        $followers = $this->api_client->get_followers($user_id, 'user', ['limit' => 1000]);
        if (!is_wp_error($followers) && !empty($followers['data'])) {
            foreach ($followers['data'] as $follower) {
                $this->cache->delete_group('feed_' . $follower['follower_id']);
            }
        }
    }
    
    // ===========================
    // Activity Recording Callbacks
    // ===========================
    
    /**
     * Record follow activity
     *
     * @param int $follower_id
     * @param int $followed_id
     * @param string $followed_type
     * @return void
     */
    public function record_follow_activity($follower_id, $followed_id, $followed_type) {
        $this->record_activity(
            $follower_id,
            'follow_' . $followed_type,
            $followed_type,
            $followed_id
        );
    }
    
    /**
     * Record favorite activity
     *
     * @param int $user_id
     * @param string $restaurant_zpid
     * @return void
     */
    public function record_favorite_activity($user_id, $restaurant_zpid) {
        // Get restaurant ID from ZPID
        $restaurant = get_posts([
            'post_type' => 'zippicks_business',
            'meta_key' => 'zpid',
            'meta_value' => $restaurant_zpid,
            'posts_per_page' => 1
        ]);
        
        if (!empty($restaurant)) {
            $this->record_activity(
                $user_id,
                'favorite_restaurant',
                'restaurant',
                $restaurant[0]->ID
            );
        }
    }
    
    /**
     * Record review activity
     *
     * @param int $post_id
     * @param WP_Post $post
     * @return void
     */
    public function record_review_activity($post_id, $post) {
        if ($post->post_type !== 'zippicks_review') {
            return;
        }
        
        $restaurant_id = get_post_meta($post_id, 'restaurant_id', true);
        if ($restaurant_id) {
            $this->record_activity(
                $post->post_author,
                'review_restaurant',
                'restaurant',
                $restaurant_id,
                [
                    'review_id' => $post_id,
                    'rating' => get_post_meta($post_id, 'overall_rating', true)
                ]
            );
        }
    }
    
    /**
     * Record list creation activity
     *
     * @param int $list_id
     * @param int $user_id
     * @return void
     */
    public function record_list_activity($list_id, $user_id) {
        $this->record_activity(
            $user_id,
            'create_list',
            'list',
            $list_id
        );
    }
    
    /**
     * Record milestone activity
     *
     * @param int $user_id
     * @param string $milestone_type
     * @param int $count
     * @return void
     */
    public function record_milestone_activity($user_id, $milestone_type, $count) {
        $this->record_activity(
            $user_id,
            'milestone_followers',
            'user',
            $user_id,
            [
                'milestone_type' => $milestone_type,
                'count' => $count
            ]
        );
    }
    
    /**
     * Clean up old activities
     *
     * @return void
     */
    public function cleanup_old_activities() {
        // This would be handled by the API
        // Just log that cleanup was triggered
        if ($this->logger) {
            $this->logger->info('Activity cleanup triggered');
        }
    }
}