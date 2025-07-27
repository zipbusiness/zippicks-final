<?php
/**
 * Follow Suggestions Engine
 *
 * @package ZipPicks_Social
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_Suggestions_Engine
 * 
 * Generates intelligent follow suggestions using Taste Graph data
 */
class ZipPicks_Social_Suggestions_Engine {
    
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
     * Suggestion weights configuration
     *
     * @var array
     */
    private $weights = [
        'mutual_follows' => 0.3,
        'taste_similarity' => 0.35,
        'location_proximity' => 0.15,
        'activity_level' => 0.1,
        'trending' => 0.1
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
        // Regenerate suggestions periodically
        add_action('zippicks_social_generate_suggestions', [$this, 'generate_all_suggestions']);
        
        // Schedule suggestion generation if not already scheduled
        if (!wp_next_scheduled('zippicks_social_generate_suggestions')) {
            wp_schedule_event(time(), 'twicedaily', 'zippicks_social_generate_suggestions');
        }
        
        // Regenerate on key events
        add_action('zippicks_social_after_follow', [$this, 'refresh_suggestions_for_user'], 10, 1);
        add_action('zippicks_favorites_added', [$this, 'refresh_suggestions_for_user'], 10, 1);
        add_action('profile_update', [$this, 'refresh_suggestions_for_user'], 10, 1);
    }
    
    /**
     * Generate follow suggestions for a user
     *
     * @param int $user_id User to generate suggestions for
     * @param array $params Parameters for suggestion generation
     * @return array Suggestion results
     */
    public function generate_suggestions($user_id, $params = []) {
        $defaults = [
            'limit' => 10,
            'type' => 'all', // all, users, critics, businesses
            'force_refresh' => false
        ];
        
        $params = wp_parse_args($params, $defaults);
        
        // Check cache unless force refresh
        if (!$params['force_refresh']) {
            $suggestions = $this->api_client->get_suggestions($user_id, $params);
            if (!is_wp_error($suggestions) && !empty($suggestions['data'])) {
                return $this->process_suggestions($suggestions['data']);
            }
        }
        
        // Generate fresh suggestions using multiple algorithms
        $candidates = [];
        
        // 1. Mutual Follows Algorithm
        if (in_array($params['type'], ['all', 'users'])) {
            $mutual_candidates = $this->get_mutual_follow_suggestions($user_id);
            $candidates = array_merge($candidates, $mutual_candidates);
        }
        
        // 2. Taste Similarity Algorithm
        $taste_candidates = $this->get_taste_based_suggestions($user_id, $params['type']);
        $candidates = array_merge($candidates, $taste_candidates);
        
        // 3. Location Based Algorithm
        $location_candidates = $this->get_location_based_suggestions($user_id, $params['type']);
        $candidates = array_merge($candidates, $location_candidates);
        
        // 4. Trending Algorithm
        $trending_candidates = $this->get_trending_suggestions($user_id, $params['type']);
        $candidates = array_merge($candidates, $trending_candidates);
        
        // Remove duplicates and already following
        $candidates = $this->filter_candidates($user_id, $candidates);
        
        // Score and rank candidates
        $scored_candidates = $this->score_candidates($user_id, $candidates);
        
        // Sort by score
        usort($scored_candidates, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Limit results
        $suggestions = array_slice($scored_candidates, 0, $params['limit']);
        
        // Store suggestions in database via API
        $this->store_suggestions($user_id, $suggestions);
        
        return $this->process_suggestions($suggestions);
    }
    
    /**
     * Get mutual follow suggestions
     *
     * @param int $user_id
     * @return array
     */
    private function get_mutual_follow_suggestions($user_id) {
        $suggestions = [];
        
        // Get user's current follows
        $following = $this->api_client->get_following($user_id, ['limit' => 100]);
        if (is_wp_error($following) || empty($following['data'])) {
            return $suggestions;
        }
        
        // For each person they follow, get their followers
        foreach ($following['data'] as $followed) {
            if ($followed['followed_type'] !== 'user') {
                continue;
            }
            
            $their_followers = $this->api_client->get_followers(
                $followed['followed_id'], 
                'user', 
                ['limit' => 50]
            );
            
            if (!is_wp_error($their_followers) && !empty($their_followers['data'])) {
                foreach ($their_followers['data'] as $follower) {
                    if ($follower['follower_id'] == $user_id) {
                        continue; // Skip self
                    }
                    
                    $suggestions[] = [
                        'entity_id' => $follower['follower_id'],
                        'entity_type' => 'user',
                        'reason' => 'mutual_follow',
                        'reason_data' => [
                            'mutual_connection' => $followed['followed_id'],
                            'mutual_name' => get_userdata($followed['followed_id'])->display_name
                        ],
                        'algorithm_score' => $this->weights['mutual_follows']
                    ];
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get taste-based suggestions
     *
     * @param int $user_id
     * @param string $type
     * @return array
     */
    private function get_taste_based_suggestions($user_id, $type) {
        $suggestions = [];
        
        // Get user's taste profile from API
        $taste_overlap_users = $this->api_client->request('GET', '/api/v1/social/taste-similar-users', [
            'user_id' => $user_id,
            'limit' => 20
        ]);
        
        if (!is_wp_error($taste_overlap_users) && !empty($taste_overlap_users['data'])) {
            foreach ($taste_overlap_users['data'] as $similar_user) {
                if (in_array($type, ['all', 'users'])) {
                    $suggestions[] = [
                        'entity_id' => $similar_user['user_id'],
                        'entity_type' => 'user',
                        'reason' => 'taste_similarity',
                        'reason_data' => [
                            'overlap_score' => $similar_user['overlap_score'],
                            'common_vibes' => array_slice($similar_user['common_vibes'], 0, 3)
                        ],
                        'algorithm_score' => $this->weights['taste_similarity'] * $similar_user['overlap_score']
                    ];
                }
                
                // Also suggest critics and businesses they follow
                if (in_array($type, ['all', 'critics', 'businesses'])) {
                    $their_follows = $this->api_client->get_following(
                        $similar_user['user_id'], 
                        ['limit' => 10]
                    );
                    
                    if (!is_wp_error($their_follows) && !empty($their_follows['data'])) {
                        foreach ($their_follows['data'] as $follow) {
                            if ($type !== 'all' && $follow['followed_type'] !== $type) {
                                continue;
                            }
                            
                            $suggestions[] = [
                                'entity_id' => $follow['followed_id'],
                                'entity_type' => $follow['followed_type'],
                                'reason' => 'taste_similarity',
                                'reason_data' => [
                                    'suggested_by' => $similar_user['user_id'],
                                    'overlap_score' => $similar_user['overlap_score']
                                ],
                                'algorithm_score' => $this->weights['taste_similarity'] * $similar_user['overlap_score'] * 0.8
                            ];
                        }
                    }
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get location-based suggestions
     *
     * @param int $user_id
     * @param string $type
     * @return array
     */
    private function get_location_based_suggestions($user_id, $type) {
        $suggestions = [];
        
        // Get user's location from favorites
        $user_location = $this->get_user_primary_location($user_id);
        if (!$user_location) {
            return $suggestions;
        }
        
        // Find active users/critics/businesses in same area
        $location_entities = $this->api_client->request('GET', '/api/v1/social/location-entities', [
            'city' => $user_location['city'],
            'state' => $user_location['state'],
            'type' => $type,
            'limit' => 20
        ]);
        
        if (!is_wp_error($location_entities) && !empty($location_entities['data'])) {
            foreach ($location_entities['data'] as $entity) {
                $suggestions[] = [
                    'entity_id' => $entity['id'],
                    'entity_type' => $entity['type'],
                    'reason' => 'location',
                    'reason_data' => [
                        'city' => $user_location['city'],
                        'distance_miles' => $entity['distance_miles'] ?? null
                    ],
                    'algorithm_score' => $this->weights['location_proximity']
                ];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get trending suggestions
     *
     * @param int $user_id
     * @param string $type
     * @return array
     */
    private function get_trending_suggestions($user_id, $type) {
        $suggestions = [];
        
        // Get trending entities from API
        $trending = $this->api_client->request('GET', '/api/v1/social/trending', [
            'type' => $type,
            'limit' => 10,
            'timeframe' => 'week'
        ]);
        
        if (!is_wp_error($trending) && !empty($trending['data'])) {
            foreach ($trending['data'] as $entity) {
                $suggestions[] = [
                    'entity_id' => $entity['id'],
                    'entity_type' => $entity['type'],
                    'reason' => 'trending',
                    'reason_data' => [
                        'follower_growth' => $entity['growth_rate'],
                        'total_followers' => $entity['follower_count']
                    ],
                    'algorithm_score' => $this->weights['trending']
                ];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get user's primary location from their favorites
     *
     * @param int $user_id
     * @return array|null
     */
    private function get_user_primary_location($user_id) {
        // Get from user meta first
        $saved_location = get_user_meta($user_id, 'zippicks_primary_location', true);
        if (!empty($saved_location)) {
            return $saved_location;
        }
        
        // Otherwise, derive from favorites
        $favorites = $this->api_client->request('GET', '/api/v1/favorites/cities', [
            'user_id' => $user_id,
            'limit' => 1
        ]);
        
        if (!is_wp_error($favorites) && !empty($favorites['data'])) {
            $location = [
                'city' => $favorites['data'][0]['city'],
                'state' => $favorites['data'][0]['state']
            ];
            
            // Save for future use
            update_user_meta($user_id, 'zippicks_primary_location', $location);
            
            return $location;
        }
        
        return null;
    }
    
    /**
     * Filter out invalid candidates
     *
     * @param int $user_id
     * @param array $candidates
     * @return array
     */
    private function filter_candidates($user_id, $candidates) {
        $filtered = [];
        $seen = [];
        
        // Get current follows to exclude
        $following = $this->api_client->get_following($user_id, ['limit' => 1000]);
        $following_ids = [];
        
        if (!is_wp_error($following) && !empty($following['data'])) {
            foreach ($following['data'] as $follow) {
                $key = $follow['followed_type'] . '_' . $follow['followed_id'];
                $following_ids[$key] = true;
            }
        }
        
        // Get dismissed suggestions
        $dismissed = get_user_meta($user_id, 'zippicks_dismissed_suggestions', true) ?: [];
        
        foreach ($candidates as $candidate) {
            $key = $candidate['entity_type'] . '_' . $candidate['entity_id'];
            
            // Skip if already following
            if (isset($following_ids[$key])) {
                continue;
            }
            
            // Skip if dismissed
            if (in_array($key, $dismissed)) {
                continue;
            }
            
            // Skip duplicates
            if (isset($seen[$key])) {
                continue;
            }
            
            // Skip self
            if ($candidate['entity_type'] === 'user' && $candidate['entity_id'] == $user_id) {
                continue;
            }
            
            $seen[$key] = true;
            $filtered[] = $candidate;
        }
        
        return $filtered;
    }
    
    /**
     * Score candidates based on multiple factors
     *
     * @param int $user_id
     * @param array $candidates
     * @return array
     */
    private function score_candidates($user_id, $candidates) {
        $scored = [];
        
        foreach ($candidates as $candidate) {
            $score = $candidate['algorithm_score'];
            
            // Add activity bonus
            $activity_score = $this->calculate_activity_score($candidate['entity_id'], $candidate['entity_type']);
            $score += $activity_score * $this->weights['activity_level'];
            
            // Add engagement bonus
            $stats = $this->api_client->get_stats($candidate['entity_id'], $candidate['entity_type']);
            if (!is_wp_error($stats)) {
                $engagement_score = min(1, $stats['followers_count'] / 1000);
                $score += $engagement_score * 0.05;
            }
            
            $candidate['score'] = $score;
            $scored[] = $candidate;
        }
        
        return $scored;
    }
    
    /**
     * Calculate activity score for an entity
     *
     * @param int $entity_id
     * @param string $entity_type
     * @return float
     */
    private function calculate_activity_score($entity_id, $entity_type) {
        // Get recent activity count from API
        $activity = $this->api_client->request('GET', '/api/v1/social/activity-count', [
            'entity_id' => $entity_id,
            'entity_type' => $entity_type,
            'days' => 30
        ]);
        
        if (is_wp_error($activity)) {
            return 0;
        }
        
        // Normalize to 0-1 scale
        return min(1, $activity['count'] / 30);
    }
    
    /**
     * Store suggestions in database
     *
     * @param int $user_id
     * @param array $suggestions
     * @return void
     */
    private function store_suggestions($user_id, $suggestions) {
        $suggestion_data = [];
        
        foreach ($suggestions as $suggestion) {
            $suggestion_data[] = [
                'user_id' => $user_id,
                'suggested_id' => $suggestion['entity_id'],
                'suggested_type' => $suggestion['entity_type'],
                'reason' => $suggestion['reason'],
                'score' => $suggestion['score']
            ];
        }
        
        // Send to API to store
        $this->api_client->request('POST', '/api/v1/social/suggestions/store', [
            'user_id' => $user_id,
            'suggestions' => $suggestion_data
        ]);
    }
    
    /**
     * Process suggestions for display
     *
     * @param array $suggestions
     * @return array
     */
    private function process_suggestions($suggestions) {
        $processed = [];
        
        foreach ($suggestions as $suggestion) {
            $entity_data = $this->get_entity_data(
                $suggestion['entity_id'] ?? $suggestion['suggested_id'], 
                $suggestion['entity_type'] ?? $suggestion['suggested_type']
            );
            
            if (!$entity_data) {
                continue;
            }
            
            $processed_suggestion = array_merge($entity_data, [
                'reason' => $this->format_reason($suggestion),
                'score' => $suggestion['score'],
                'id' => $suggestion['entity_id'] ?? $suggestion['suggested_id'],
                'type' => $suggestion['entity_type'] ?? $suggestion['suggested_type']
            ]);
            
            $processed[] = $processed_suggestion;
        }
        
        return $processed;
    }
    
    /**
     * Get entity data for display
     *
     * @param int $entity_id
     * @param string $entity_type
     * @return array|null
     */
    private function get_entity_data($entity_id, $entity_type) {
        switch ($entity_type) {
            case 'user':
                $user = get_user_by('id', $entity_id);
                if (!$user) {
                    return null;
                }
                
                $stats = $this->api_client->get_stats($entity_id, 'user');
                
                return [
                    'name' => $user->display_name,
                    'avatar' => get_avatar_url($entity_id),
                    'url' => get_author_posts_url($entity_id),
                    'bio' => get_user_meta($entity_id, 'description', true),
                    'followers_count' => !is_wp_error($stats) ? $stats['followers_count'] : 0,
                    'following_count' => !is_wp_error($stats) ? $stats['following_count'] : 0
                ];
                
            case 'critic':
                $critic = get_post($entity_id);
                if (!$critic || $critic->post_type !== 'zippicks_critic') {
                    return null;
                }
                
                $stats = $this->api_client->get_stats($entity_id, 'critic');
                
                return [
                    'name' => $critic->post_title,
                    'avatar' => get_the_post_thumbnail_url($entity_id, 'thumbnail'),
                    'url' => get_permalink($entity_id),
                    'bio' => get_post_meta($entity_id, 'bio', true),
                    'followers_count' => !is_wp_error($stats) ? $stats['followers_count'] : 0,
                    'expertise' => get_post_meta($entity_id, 'expertise', true)
                ];
                
            case 'business':
                $business = get_post($entity_id);
                if (!$business || $business->post_type !== 'zippicks_business') {
                    return null;
                }
                
                $stats = $this->api_client->get_stats($entity_id, 'business');
                
                return [
                    'name' => $business->post_title,
                    'avatar' => get_the_post_thumbnail_url($entity_id, 'thumbnail'),
                    'url' => get_permalink($entity_id),
                    'bio' => get_post_meta($entity_id, 'description', true),
                    'followers_count' => !is_wp_error($stats) ? $stats['followers_count'] : 0,
                    'cuisine' => wp_get_post_terms($entity_id, 'cuisine', ['fields' => 'names']),
                    'vibes' => wp_get_post_terms($entity_id, 'vibes', ['fields' => 'names'])
                ];
        }
        
        return null;
    }
    
    /**
     * Format suggestion reason for display
     *
     * @param array $suggestion
     * @return string
     */
    private function format_reason($suggestion) {
        $reason = $suggestion['reason'];
        $data = $suggestion['reason_data'] ?? [];
        
        switch ($reason) {
            case 'mutual_follow':
                if (!empty($data['mutual_name'])) {
                    return sprintf('Also followed by %s', esc_html($data['mutual_name']));
                }
                return 'Followed by people you follow';
                
            case 'taste_similarity':
                if (!empty($data['common_vibes'])) {
                    return sprintf('Likes %s', implode(', ', array_map('esc_html', $data['common_vibes'])));
                }
                return 'Similar taste profile';
                
            case 'location':
                if (!empty($data['city'])) {
                    return sprintf('Active in %s', esc_html($data['city']));
                }
                return 'In your area';
                
            case 'trending':
                if (!empty($data['follower_growth'])) {
                    return sprintf('Trending (+%d%% this week)', $data['follower_growth']);
                }
                return 'Trending now';
                
            default:
                return 'Suggested for you';
        }
    }
    
    /**
     * Refresh suggestions for a specific user
     *
     * @param int $user_id
     * @return void
     */
    public function refresh_suggestions_for_user($user_id) {
        // Clear cache
        $this->cache->delete_group('suggestions_' . $user_id);
        
        // Generate new suggestions
        $this->generate_suggestions($user_id, ['force_refresh' => true]);
    }
    
    /**
     * Generate suggestions for all active users
     *
     * @return void
     */
    public function generate_all_suggestions() {
        // Get active users (logged in within last 30 days)
        $args = [
            'meta_query' => [
                [
                    'key' => 'last_login',
                    'value' => date('Y-m-d', strtotime('-30 days')),
                    'compare' => '>',
                    'type' => 'DATE'
                ]
            ],
            'number' => 100
        ];
        
        $users = get_users($args);
        
        foreach ($users as $user) {
            $this->generate_suggestions($user->ID, ['force_refresh' => true]);
            
            // Small delay to avoid overwhelming the API
            usleep(100000); // 0.1 seconds
        }
        
        if ($this->logger) {
            $this->logger->info('Bulk suggestion generation completed', [
                'user_count' => count($users)
            ]);
        }
    }
    
    /**
     * Dismiss a suggestion
     *
     * @param int $user_id
     * @param int $entity_id
     * @param string $entity_type
     * @return bool
     */
    public function dismiss_suggestion($user_id, $entity_id, $entity_type) {
        // Store dismissal in API
        $result = $this->api_client->dismiss_suggestion($user_id, $entity_id, $entity_type);
        
        if (is_wp_error($result)) {
            return false;
        }
        
        // Also store locally for quick filtering
        $dismissed = get_user_meta($user_id, 'zippicks_dismissed_suggestions', true) ?: [];
        $dismissed[] = $entity_type . '_' . $entity_id;
        update_user_meta($user_id, 'zippicks_dismissed_suggestions', array_unique($dismissed));
        
        // Clear suggestion cache
        $this->cache->delete_group('suggestions_' . $user_id);
        
        return true;
    }
}