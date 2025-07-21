<?php
/**
 * Taste Graph Engine
 * 
 * Core engine that connects users to places through taste preferences,
 * behavioral patterns, and social connections.
 * 
 * @package ZipPicks\Foundation
 */

namespace ZipPicks\Foundation;

if (!defined('ABSPATH')) {
    exit;
}

class TasteGraph {
    
    /**
     * User taste profile cache
     * 
     * @var array
     */
    private $taste_profiles = [];
    
    /**
     * Connection weights
     * 
     * @var array
     */
    private $weights = [
        'explicit_preference' => 1.0,
        'implicit_behavior' => 0.8,
        'social_influence' => 0.6,
        'demographic_similarity' => 0.4,
        'location_proximity' => 0.3
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Track user interactions
        add_action('zippicks_business_viewed', [$this, 'track_view'], 10, 2);
        add_action('zippicks_business_saved', [$this, 'track_save'], 10, 2);
        add_action('zippicks_review_submitted', [$this, 'track_review'], 10, 3);
        add_action('zippicks_vibe_searched', [$this, 'track_vibe_search'], 10, 2);
        
        // Update taste graph
        add_action('zippicks_daily_maintenance', [$this, 'recalculate_connections']);
    }
    
    /**
     * Get user taste profile
     * 
     * @param int $user_id User ID
     * @return array Taste profile data
     */
    public function get_user_profile($user_id) {
        if (isset($this->taste_profiles[$user_id])) {
            return $this->taste_profiles[$user_id];
        }
        
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'taste_profiles';
        
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        if (!$profile) {
            $profile = $this->create_user_profile($user_id);
        } else {
            $profile['preferences'] = maybe_unserialize($profile['preferences']);
            $profile['behavior_data'] = maybe_unserialize($profile['behavior_data']);
            $profile['social_connections'] = maybe_unserialize($profile['social_connections']);
        }
        
        $this->taste_profiles[$user_id] = $profile;
        return $profile;
    }
    
    /**
     * Create new user taste profile
     * 
     * @param int $user_id User ID
     * @return array New profile data
     */
    private function create_user_profile($user_id) {
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'taste_profiles';
        
        $profile_data = [
            'user_id' => $user_id,
            'preferences' => [],
            'behavior_data' => [
                'views' => [],
                'saves' => [],
                'reviews' => [],
                'searches' => []
            ],
            'social_connections' => [],
            'taste_vector' => $this->initialize_taste_vector(),
            'last_updated' => current_time('mysql')
        ];
        
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'preferences' => serialize($profile_data['preferences']),
            'behavior_data' => serialize($profile_data['behavior_data']),
            'social_connections' => serialize($profile_data['social_connections']),
            'taste_vector' => json_encode($profile_data['taste_vector']),
            'last_updated' => $profile_data['last_updated']
        ]);
        
        return $profile_data;
    }
    
    /**
     * Initialize taste vector with default dimensions
     * 
     * @return array Taste vector
     */
    private function initialize_taste_vector() {
        return [
            'adventurous' => 0.5,
            'traditional' => 0.5,
            'budget_conscious' => 0.5,
            'luxury_seeking' => 0.5,
            'social' => 0.5,
            'intimate' => 0.5,
            'trendy' => 0.5,
            'authentic' => 0.5,
            'health_conscious' => 0.5,
            'indulgent' => 0.5
        ];
    }
    
    /**
     * Update user preferences
     * 
     * @param int $user_id User ID
     * @param array $preferences Preference data
     */
    public function update_preferences($user_id, $preferences) {
        $profile = $this->get_user_profile($user_id);
        $profile['preferences'] = array_merge($profile['preferences'], $preferences);
        
        // Recalculate taste vector based on preferences
        $this->recalculate_taste_vector($user_id, $profile);
        
        // Save updated profile
        $this->save_user_profile($user_id, $profile);
    }
    
    /**
     * Track business view
     * 
     * @param int $business_id Business ID
     * @param int $user_id User ID
     */
    public function track_view($business_id, $user_id) {
        if (!$user_id) return;
        
        $profile = $this->get_user_profile($user_id);
        $profile['behavior_data']['views'][] = [
            'business_id' => $business_id,
            'timestamp' => time(),
            'context' => $this->get_interaction_context()
        ];
        
        // Keep only last 100 views
        $profile['behavior_data']['views'] = array_slice($profile['behavior_data']['views'], -100);
        
        $this->save_user_profile($user_id, $profile);
        $this->update_taste_from_interaction($user_id, $business_id, 'view');
    }
    
    /**
     * Track business save
     * 
     * @param int $business_id Business ID
     * @param int $user_id User ID
     */
    public function track_save($business_id, $user_id) {
        if (!$user_id) return;
        
        $profile = $this->get_user_profile($user_id);
        $profile['behavior_data']['saves'][] = [
            'business_id' => $business_id,
            'timestamp' => time(),
            'list_id' => get_query_var('list_id', 0)
        ];
        
        $this->save_user_profile($user_id, $profile);
        $this->update_taste_from_interaction($user_id, $business_id, 'save');
    }
    
    /**
     * Track review submission
     * 
     * @param int $review_id Review ID
     * @param int $business_id Business ID
     * @param int $user_id User ID
     */
    public function track_review($review_id, $business_id, $user_id) {
        if (!$user_id) return;
        
        $profile = $this->get_user_profile($user_id);
        $profile['behavior_data']['reviews'][] = [
            'review_id' => $review_id,
            'business_id' => $business_id,
            'timestamp' => time()
        ];
        
        $this->save_user_profile($user_id, $profile);
        $this->update_taste_from_interaction($user_id, $business_id, 'review');
    }
    
    /**
     * Track vibe search
     * 
     * @param string $vibe Vibe searched
     * @param int $user_id User ID
     */
    public function track_vibe_search($vibe, $user_id) {
        if (!$user_id) return;
        
        $profile = $this->get_user_profile($user_id);
        $profile['behavior_data']['searches'][] = [
            'vibe' => $vibe,
            'timestamp' => time(),
            'zip' => $this->get_current_zip()
        ];
        
        // Keep only last 50 searches
        $profile['behavior_data']['searches'] = array_slice($profile['behavior_data']['searches'], -50);
        
        $this->save_user_profile($user_id, $profile);
    }
    
    /**
     * Get interaction context
     * 
     * @return array Context data
     */
    private function get_interaction_context() {
        return [
            'device' => wp_is_mobile() ? 'mobile' : 'desktop',
            'time_of_day' => date('G'),
            'day_of_week' => date('w'),
            'referrer' => wp_get_referer(),
            'zip' => $this->get_current_zip()
        ];
    }
    
    /**
     * Get current ZIP code
     * 
     * @return string ZIP code
     */
    private function get_current_zip() {
        // Get from session, cookie, or geolocation
        if (isset($_SESSION['zippicks_zip'])) {
            return $_SESSION['zippicks_zip'];
        }
        
        if (isset($_COOKIE['zippicks_zip'])) {
            return $_COOKIE['zippicks_zip'];
        }
        
        return apply_filters('zippicks_default_zip', '10001');
    }
    
    /**
     * Update taste vector from interaction
     * 
     * @param int $user_id User ID
     * @param int $business_id Business ID
     * @param string $interaction_type Type of interaction
     */
    private function update_taste_from_interaction($user_id, $business_id, $interaction_type) {
        $profile = $this->get_user_profile($user_id);
        $business_vibes = $this->get_business_vibes($business_id);
        
        // Weight based on interaction type
        $weight = [
            'view' => 0.1,
            'save' => 0.3,
            'review' => 0.5
        ][$interaction_type] ?? 0.1;
        
        // Update taste vector based on business vibes
        foreach ($business_vibes as $vibe) {
            $vibe_attributes = $this->get_vibe_attributes($vibe);
            foreach ($vibe_attributes as $attribute => $strength) {
                if (isset($profile['taste_vector'][$attribute])) {
                    // Weighted moving average
                    $profile['taste_vector'][$attribute] = 
                        (1 - $weight) * $profile['taste_vector'][$attribute] + 
                        $weight * $strength;
                }
            }
        }
        
        $this->save_user_profile($user_id, $profile);
    }
    
    /**
     * Get business vibes
     * 
     * @param int $business_id Business ID
     * @return array Vibes
     */
    private function get_business_vibes($business_id) {
        return wp_get_object_terms($business_id, 'zippicks_vibe', ['fields' => 'slugs']);
    }
    
    /**
     * Get vibe attributes mapping
     * 
     * @param string $vibe Vibe slug
     * @return array Attribute mappings
     */
    private function get_vibe_attributes($vibe) {
        // This would be expanded with full vibe-to-attribute mappings
        $mappings = [
            'trendy' => ['trendy' => 0.9, 'adventurous' => 0.6],
            'cozy' => ['intimate' => 0.8, 'traditional' => 0.6],
            'upscale' => ['luxury_seeking' => 0.9, 'social' => 0.5],
            'casual' => ['budget_conscious' => 0.7, 'authentic' => 0.6],
            'healthy' => ['health_conscious' => 0.9, 'adventurous' => 0.4],
            'comfort-food' => ['indulgent' => 0.8, 'traditional' => 0.7]
        ];
        
        return $mappings[$vibe] ?? [];
    }
    
    /**
     * Calculate similarity between users
     * 
     * @param int $user_a User A ID
     * @param int $user_b User B ID
     * @return float Similarity score (0-1)
     */
    public function calculate_user_similarity($user_a, $user_b) {
        $profile_a = $this->get_user_profile($user_a);
        $profile_b = $this->get_user_profile($user_b);
        
        // Calculate cosine similarity of taste vectors
        $dot_product = 0;
        $magnitude_a = 0;
        $magnitude_b = 0;
        
        foreach ($profile_a['taste_vector'] as $dimension => $value_a) {
            $value_b = $profile_b['taste_vector'][$dimension] ?? 0.5;
            $dot_product += $value_a * $value_b;
            $magnitude_a += $value_a * $value_a;
            $magnitude_b += $value_b * $value_b;
        }
        
        $magnitude_a = sqrt($magnitude_a);
        $magnitude_b = sqrt($magnitude_b);
        
        if ($magnitude_a == 0 || $magnitude_b == 0) {
            return 0;
        }
        
        return $dot_product / ($magnitude_a * $magnitude_b);
    }
    
    /**
     * Get personalized recommendations
     * 
     * @param int $user_id User ID
     * @param array $args Query arguments
     * @return array Business IDs
     */
    public function get_recommendations($user_id, $args = []) {
        $defaults = [
            'zip' => $this->get_current_zip(),
            'limit' => 10,
            'exclude' => [],
            'category' => '',
            'min_score' => 7.0
        ];
        
        $args = wp_parse_args($args, $defaults);
        $profile = $this->get_user_profile($user_id);
        
        // Get candidate businesses
        $candidates = $this->get_candidate_businesses($args);
        
        // Score each candidate
        $scored_businesses = [];
        foreach ($candidates as $business_id) {
            $score = $this->calculate_business_affinity($user_id, $business_id, $profile);
            $scored_businesses[$business_id] = $score;
        }
        
        // Sort by score
        arsort($scored_businesses);
        
        // Return top matches
        return array_slice(array_keys($scored_businesses), 0, $args['limit']);
    }
    
    /**
     * Calculate business affinity score
     * 
     * @param int $user_id User ID
     * @param int $business_id Business ID
     * @param array $profile User profile
     * @return float Affinity score
     */
    private function calculate_business_affinity($user_id, $business_id, $profile) {
        $score = 0;
        
        // Vibe match score
        $business_vibes = $this->get_business_vibes($business_id);
        $vibe_score = 0;
        
        foreach ($business_vibes as $vibe) {
            $vibe_attributes = $this->get_vibe_attributes($vibe);
            foreach ($vibe_attributes as $attribute => $strength) {
                if (isset($profile['taste_vector'][$attribute])) {
                    $vibe_score += $profile['taste_vector'][$attribute] * $strength;
                }
            }
        }
        
        $score += $vibe_score * $this->weights['explicit_preference'];
        
        // Social influence score
        $social_score = $this->calculate_social_influence($user_id, $business_id);
        $score += $social_score * $this->weights['social_influence'];
        
        // Location proximity score
        $proximity_score = $this->calculate_proximity_score($business_id, $profile);
        $score += $proximity_score * $this->weights['location_proximity'];
        
        return $score;
    }
    
    /**
     * Calculate social influence score
     * 
     * @param int $user_id User ID
     * @param int $business_id Business ID
     * @return float Social score
     */
    private function calculate_social_influence($user_id, $business_id) {
        // Get user's social connections who have interacted with this business
        $connections = $this->get_user_connections($user_id);
        $influence_score = 0;
        
        foreach ($connections as $connection_id => $connection_strength) {
            if ($this->has_user_interacted_with_business($connection_id, $business_id)) {
                $influence_score += $connection_strength;
            }
        }
        
        return min($influence_score, 1.0);
    }
    
    /**
     * Calculate proximity score
     * 
     * @param int $business_id Business ID
     * @param array $profile User profile
     * @return float Proximity score
     */
    private function calculate_proximity_score($business_id, $profile) {
        // This would calculate actual distance
        // For now, return a normalized score
        return 0.8;
    }
    
    /**
     * Get candidate businesses
     * 
     * @param array $args Query arguments
     * @return array Business IDs
     */
    private function get_candidate_businesses($args) {
        $query_args = [
            'post_type' => 'zippicks_business',
            'posts_per_page' => 100,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_zippicks_zip',
                    'value' => $args['zip'],
                    'compare' => '='
                ],
                [
                    'key' => '_zippicks_master_score',
                    'value' => $args['min_score'],
                    'compare' => '>=',
                    'type' => 'DECIMAL'
                ]
            ]
        ];
        
        if ($args['category']) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'zippicks_category',
                    'field' => 'slug',
                    'terms' => $args['category']
                ]
            ];
        }
        
        if (!empty($args['exclude'])) {
            $query_args['post__not_in'] = $args['exclude'];
        }
        
        return get_posts($query_args);
    }
    
    /**
     * Get user connections
     * 
     * @param int $user_id User ID
     * @return array Connection strengths
     */
    private function get_user_connections($user_id) {
        $profile = $this->get_user_profile($user_id);
        return $profile['social_connections'] ?? [];
    }
    
    /**
     * Check if user has interacted with business
     * 
     * @param int $user_id User ID
     * @param int $business_id Business ID
     * @return bool
     */
    private function has_user_interacted_with_business($user_id, $business_id) {
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'interactions';
        
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE user_id = %d AND business_id = %d",
            $user_id,
            $business_id
        ));
    }
    
    /**
     * Save user profile
     * 
     * @param int $user_id User ID
     * @param array $profile Profile data
     */
    private function save_user_profile($user_id, $profile) {
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'taste_profiles';
        
        $wpdb->update($table, [
            'preferences' => serialize($profile['preferences']),
            'behavior_data' => serialize($profile['behavior_data']),
            'social_connections' => serialize($profile['social_connections']),
            'taste_vector' => json_encode($profile['taste_vector']),
            'last_updated' => current_time('mysql')
        ], ['user_id' => $user_id]);
        
        $this->taste_profiles[$user_id] = $profile;
    }
    
    /**
     * Recalculate taste vector
     * 
     * @param int $user_id User ID
     * @param array $profile Profile data
     */
    private function recalculate_taste_vector($user_id, $profile) {
        // Implement taste vector recalculation based on all data
        // This is a placeholder for the full implementation
    }
    
    /**
     * Recalculate all connections (maintenance task)
     */
    public function recalculate_connections() {
        // This would run as a scheduled task to update the taste graph
        // Implementation would batch process users and update connections
    }
}