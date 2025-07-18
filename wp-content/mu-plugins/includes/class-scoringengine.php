<?php
/**
 * Master Critic AI Scoring Engine
 * 
 * Implements the 6-pillar scoring system (0-10 scale) for businesses
 * across different verticals with dynamic pillar configuration.
 * 
 * @package ZipPicks\Foundation
 */

namespace ZipPicks\Foundation;

if (!defined('ABSPATH')) {
    exit;
}

class ScoringEngine {
    
    /**
     * Scoring pillars by vertical
     * 
     * @var array
     */
    private $pillars = [
        'restaurant' => [
            'food_quality' => [
                'label' => 'Food Quality',
                'weight' => 0.25,
                'description' => 'Taste, freshness, presentation, and culinary execution'
            ],
            'service' => [
                'label' => 'Service',
                'weight' => 0.20,
                'description' => 'Staff attentiveness, knowledge, and hospitality'
            ],
            'atmosphere_design' => [
                'label' => 'Atmosphere & Design',
                'weight' => 0.15,
                'description' => 'Ambiance, decor, comfort, and overall vibe'
            ],
            'value' => [
                'label' => 'Value',
                'weight' => 0.15,
                'description' => 'Price-to-quality ratio and portion sizes'
            ],
            'consistency' => [
                'label' => 'Consistency',
                'weight' => 0.15,
                'description' => 'Reliability across visits and dishes'
            ],
            'cultural_relevance' => [
                'label' => 'Cultural Relevance',
                'weight' => 0.10,
                'description' => 'Authenticity, innovation, and local impact'
            ]
        ],
        'hotel' => [
            'room_quality' => [
                'label' => 'Room Quality',
                'weight' => 0.25,
                'description' => 'Comfort, cleanliness, amenities, and design'
            ],
            'staff' => [
                'label' => 'Staff',
                'weight' => 0.20,
                'description' => 'Helpfulness, professionalism, and service quality'
            ],
            'amenities' => [
                'label' => 'Amenities',
                'weight' => 0.15,
                'description' => 'Facilities, dining options, and extras'
            ],
            'cleanliness' => [
                'label' => 'Cleanliness',
                'weight' => 0.15,
                'description' => 'Overall hygiene and maintenance standards'
            ],
            'location' => [
                'label' => 'Location',
                'weight' => 0.15,
                'description' => 'Convenience, safety, and nearby attractions'
            ],
            'uniqueness' => [
                'label' => 'Uniqueness',
                'weight' => 0.10,
                'description' => 'Character, distinction, and memorable qualities'
            ]
        ],
        'salon' => [
            'technical_skill' => [
                'label' => 'Technical Skill',
                'weight' => 0.30,
                'description' => 'Expertise, precision, and results quality'
            ],
            'friendliness' => [
                'label' => 'Friendliness',
                'weight' => 0.20,
                'description' => 'Warmth, communication, and customer care'
            ],
            'cleanliness' => [
                'label' => 'Cleanliness',
                'weight' => 0.15,
                'description' => 'Hygiene standards and facility maintenance'
            ],
            'price' => [
                'label' => 'Price',
                'weight' => 0.15,
                'description' => 'Value for money and transparent pricing'
            ],
            'ambience' => [
                'label' => 'Ambience',
                'weight' => 0.10,
                'description' => 'Atmosphere, comfort, and overall vibe'
            ],
            'punctuality' => [
                'label' => 'Punctuality',
                'weight' => 0.10,
                'description' => 'Appointment scheduling and time management'
            ]
        ],
        'gym' => [
            'coaching' => [
                'label' => 'Coaching',
                'weight' => 0.25,
                'description' => 'Trainer quality, programming, and support'
            ],
            'equipment' => [
                'label' => 'Equipment',
                'weight' => 0.20,
                'description' => 'Quality, variety, and maintenance'
            ],
            'cleanliness' => [
                'label' => 'Cleanliness',
                'weight' => 0.20,
                'description' => 'Hygiene standards and facility upkeep'
            ],
            'schedule_access' => [
                'label' => 'Schedule Access',
                'weight' => 0.15,
                'description' => 'Hours, class times, and availability'
            ],
            'community' => [
                'label' => 'Community',
                'weight' => 0.10,
                'description' => 'Member culture and social atmosphere'
            ],
            'value' => [
                'label' => 'Value',
                'weight' => 0.10,
                'description' => 'Pricing and membership benefits'
            ]
        ]
    ];
    
    /**
     * Score precision
     * 
     * @var int
     */
    private $precision = ZIPPICKS_SCORE_PRECISION;
    
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
        // Allow filtering of pillars
        add_filter('zippicks_scoring_pillars', [$this, 'filter_pillars'], 10, 2);
        
        // Score calculation hooks
        add_action('zippicks_calculate_score', [$this, 'calculate_business_score'], 10, 2);
        add_action('zippicks_review_approved', [$this, 'update_aggregate_score'], 10, 2);
    }
    
    /**
     * Get pillars for a vertical
     * 
     * @param string $vertical Business vertical
     * @return array Pillar configuration
     */
    public function get_pillars($vertical) {
        $pillars = $this->pillars[$vertical] ?? $this->pillars['restaurant'];
        return apply_filters('zippicks_scoring_pillars', $pillars, $vertical);
    }
    
    /**
     * Filter pillars (hook callback)
     * 
     * @param array $pillars Current pillars
     * @param string $vertical Business vertical
     * @return array Filtered pillars
     */
    public function filter_pillars($pillars, $vertical) {
        // Allow custom verticals to be added
        return $pillars;
    }
    
    /**
     * Calculate Master Critic AI score
     * 
     * @param int $business_id Business ID
     * @param array $scores Individual pillar scores
     * @return array Score data
     */
    public function calculate_master_score($business_id, $scores) {
        $vertical = $this->get_business_vertical($business_id);
        $pillars = $this->get_pillars($vertical);
        
        $weighted_sum = 0;
        $total_weight = 0;
        $pillar_scores = [];
        
        foreach ($pillars as $pillar_key => $pillar_config) {
            if (isset($scores[$pillar_key])) {
                $score = $this->validate_score($scores[$pillar_key]);
                $weight = $pillar_config['weight'];
                
                $weighted_sum += $score * $weight;
                $total_weight += $weight;
                
                $pillar_scores[$pillar_key] = [
                    'score' => $score,
                    'label' => $pillar_config['label'],
                    'weight' => $weight
                ];
            }
        }
        
        // Calculate overall score
        $overall_score = $total_weight > 0 ? round($weighted_sum / $total_weight, $this->precision) : 0;
        
        return [
            'overall' => $overall_score,
            'pillars' => $pillar_scores,
            'vertical' => $vertical,
            'timestamp' => current_time('timestamp'),
            'version' => ZIPPICKS_FOUNDATION_VERSION
        ];
    }
    
    /**
     * Validate individual score
     * 
     * @param float $score Raw score
     * @return float Validated score
     */
    private function validate_score($score) {
        $score = floatval($score);
        $score = max(ZIPPICKS_MIN_SCORE, $score);
        $score = min(ZIPPICKS_MAX_SCORE, $score);
        return round($score, $this->precision);
    }
    
    /**
     * Get business vertical
     * 
     * @param int $business_id Business ID
     * @return string Vertical identifier
     */
    private function get_business_vertical($business_id) {
        $vertical = get_post_meta($business_id, '_zippicks_vertical', true);
        return $vertical ?: 'restaurant';
    }
    
    /**
     * Save Master Critic score
     * 
     * @param int $business_id Business ID
     * @param array $score_data Score data
     */
    public function save_master_score($business_id, $score_data) {
        // Save overall score for queries
        update_post_meta($business_id, '_zippicks_master_score', $score_data['overall']);
        
        // Save detailed score data
        update_post_meta($business_id, '_zippicks_master_score_data', $score_data);
        
        // Save individual pillar scores for filtering
        foreach ($score_data['pillars'] as $pillar_key => $pillar_data) {
            update_post_meta($business_id, '_zippicks_score_' . $pillar_key, $pillar_data['score']);
        }
        
        // Update last scored timestamp
        update_post_meta($business_id, '_zippicks_last_scored', $score_data['timestamp']);
        
        // Trigger action for other systems
        do_action('zippicks_master_score_updated', $business_id, $score_data);
    }
    
    /**
     * Get Master Critic score
     * 
     * @param int $business_id Business ID
     * @return array|null Score data
     */
    public function get_master_score($business_id) {
        $score_data = get_post_meta($business_id, '_zippicks_master_score_data', true);
        
        if (!$score_data) {
            return null;
        }
        
        // Ensure score data is complete
        $score_data = wp_parse_args($score_data, [
            'overall' => 0,
            'pillars' => [],
            'vertical' => 'restaurant',
            'timestamp' => 0,
            'version' => '1.0.0'
        ]);
        
        return $score_data;
    }
    
    /**
     * Generate AI score for business
     * 
     * @param int $business_id Business ID
     * @param array $context Additional context
     * @return array Score data
     */
    public function generate_ai_score($business_id, $context = []) {
        $vertical = $this->get_business_vertical($business_id);
        $pillars = $this->get_pillars($vertical);
        
        // Gather scoring inputs
        $inputs = $this->gather_scoring_inputs($business_id, $context);
        
        // Generate scores for each pillar
        $pillar_scores = [];
        foreach ($pillars as $pillar_key => $pillar_config) {
            $pillar_scores[$pillar_key] = $this->score_pillar($business_id, $pillar_key, $inputs);
        }
        
        // Calculate master score
        $score_data = $this->calculate_master_score($business_id, $pillar_scores);
        
        // Add AI metadata
        $score_data['ai_generated'] = true;
        $score_data['ai_context'] = $context;
        $score_data['ai_inputs'] = $this->sanitize_inputs_for_storage($inputs);
        
        return $score_data;
    }
    
    /**
     * Gather scoring inputs
     * 
     * @param int $business_id Business ID
     * @param array $context Additional context
     * @return array Scoring inputs
     */
    private function gather_scoring_inputs($business_id, $context) {
        $inputs = [
            'business_data' => $this->get_business_data($business_id),
            'user_reviews' => $this->get_user_reviews($business_id),
            'critic_reviews' => $this->get_critic_reviews($business_id),
            'social_signals' => $this->get_social_signals($business_id),
            'market_data' => $this->get_market_data($business_id),
            'context' => $context
        ];
        
        return apply_filters('zippicks_scoring_inputs', $inputs, $business_id);
    }
    
    /**
     * Score individual pillar
     * 
     * @param int $business_id Business ID
     * @param string $pillar_key Pillar key
     * @param array $inputs Scoring inputs
     * @return float Pillar score
     */
    private function score_pillar($business_id, $pillar_key, $inputs) {
        // This would integrate with the actual AI scoring API
        // For now, return a calculated score based on available data
        
        $base_score = 7.0; // Starting neutral score
        
        // Adjust based on review sentiments
        if (!empty($inputs['user_reviews'])) {
            $review_adjustment = $this->calculate_review_adjustment($inputs['user_reviews'], $pillar_key);
            $base_score += $review_adjustment;
        }
        
        // Adjust based on critic reviews
        if (!empty($inputs['critic_reviews'])) {
            $critic_adjustment = $this->calculate_critic_adjustment($inputs['critic_reviews'], $pillar_key);
            $base_score += $critic_adjustment * 1.5; // Critics have more weight
        }
        
        // Apply vertical-specific adjustments
        $base_score = apply_filters('zippicks_pillar_score', $base_score, $pillar_key, $business_id, $inputs);
        
        return $this->validate_score($base_score);
    }
    
    /**
     * Calculate review adjustment
     * 
     * @param array $reviews User reviews
     * @param string $pillar_key Pillar key
     * @return float Adjustment value
     */
    private function calculate_review_adjustment($reviews, $pillar_key) {
        $total_adjustment = 0;
        $count = 0;
        
        foreach ($reviews as $review) {
            if (isset($review['pillar_mentions'][$pillar_key])) {
                $sentiment = $review['pillar_mentions'][$pillar_key]['sentiment'] ?? 0;
                $total_adjustment += $sentiment;
                $count++;
            }
        }
        
        return $count > 0 ? ($total_adjustment / $count) : 0;
    }
    
    /**
     * Calculate critic adjustment
     * 
     * @param array $reviews Critic reviews
     * @param string $pillar_key Pillar key
     * @return float Adjustment value
     */
    private function calculate_critic_adjustment($reviews, $pillar_key) {
        $total_score = 0;
        $count = 0;
        
        foreach ($reviews as $review) {
            if (isset($review['pillar_scores'][$pillar_key])) {
                $score = $review['pillar_scores'][$pillar_key];
                $total_score += ($score - 7.0); // Adjustment from neutral
                $count++;
            }
        }
        
        return $count > 0 ? ($total_score / $count) : 0;
    }
    
    /**
     * Get business data
     * 
     * @param int $business_id Business ID
     * @return array Business data
     */
    private function get_business_data($business_id) {
        $business = get_post($business_id);
        
        return [
            'title' => $business->post_title,
            'description' => $business->post_content,
            'meta' => get_post_meta($business_id),
            'vibes' => wp_get_object_terms($business_id, 'zippicks_vibe', ['fields' => 'all']),
            'category' => wp_get_object_terms($business_id, 'zippicks_category', ['fields' => 'all'])
        ];
    }
    
    /**
     * Get user reviews
     * 
     * @param int $business_id Business ID
     * @return array Reviews
     */
    private function get_user_reviews($business_id) {
        $reviews = get_posts([
            'post_type' => 'zippicks_review',
            'posts_per_page' => 50,
            'meta_query' => [
                [
                    'key' => '_zippicks_business_id',
                    'value' => $business_id
                ],
                [
                    'key' => '_zippicks_review_type',
                    'value' => 'user'
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        $review_data = [];
        foreach ($reviews as $review) {
            $review_data[] = [
                'id' => $review->ID,
                'content' => $review->post_content,
                'rating' => get_post_meta($review->ID, '_zippicks_rating', true),
                'pillar_mentions' => get_post_meta($review->ID, '_zippicks_pillar_mentions', true) ?: []
            ];
        }
        
        return $review_data;
    }
    
    /**
     * Get critic reviews
     * 
     * @param int $business_id Business ID
     * @return array Reviews
     */
    private function get_critic_reviews($business_id) {
        $reviews = get_posts([
            'post_type' => 'zippicks_review',
            'posts_per_page' => 10,
            'meta_query' => [
                [
                    'key' => '_zippicks_business_id',
                    'value' => $business_id
                ],
                [
                    'key' => '_zippicks_review_type',
                    'value' => 'critic'
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        $review_data = [];
        foreach ($reviews as $review) {
            $review_data[] = [
                'id' => $review->ID,
                'critic_id' => $review->post_author,
                'content' => $review->post_content,
                'pillar_scores' => get_post_meta($review->ID, '_zippicks_pillar_scores', true) ?: []
            ];
        }
        
        return $review_data;
    }
    
    /**
     * Get social signals
     * 
     * @param int $business_id Business ID
     * @return array Social data
     */
    private function get_social_signals($business_id) {
        return [
            'saves' => intval(get_post_meta($business_id, '_zippicks_save_count', true)),
            'views' => intval(get_post_meta($business_id, '_zippicks_view_count', true)),
            'shares' => intval(get_post_meta($business_id, '_zippicks_share_count', true)),
            'trending_score' => floatval(get_post_meta($business_id, '_zippicks_trending_score', true))
        ];
    }
    
    /**
     * Get market data
     * 
     * @param int $business_id Business ID
     * @return array Market data
     */
    private function get_market_data($business_id) {
        $zip = get_post_meta($business_id, '_zippicks_zip', true);
        $category = wp_get_object_terms($business_id, 'zippicks_category', ['fields' => 'slugs']);
        
        return [
            'zip' => $zip,
            'category' => $category[0] ?? '',
            'competition_level' => $this->get_competition_level($zip, $category[0] ?? ''),
            'price_tier' => get_post_meta($business_id, '_zippicks_price_tier', true)
        ];
    }
    
    /**
     * Get competition level
     * 
     * @param string $zip ZIP code
     * @param string $category Category
     * @return string Competition level
     */
    private function get_competition_level($zip, $category) {
        // This would analyze market saturation
        // For now, return a default
        return 'medium';
    }
    
    /**
     * Sanitize inputs for storage
     * 
     * @param array $inputs Raw inputs
     * @return array Sanitized inputs
     */
    private function sanitize_inputs_for_storage($inputs) {
        // Remove sensitive data before storage
        unset($inputs['context']['api_key']);
        unset($inputs['context']['user_session']);
        
        return $inputs;
    }
    
    /**
     * Calculate business score (action callback)
     * 
     * @param int $business_id Business ID
     * @param array $context Context
     */
    public function calculate_business_score($business_id, $context = []) {
        $score_data = $this->generate_ai_score($business_id, $context);
        $this->save_master_score($business_id, $score_data);
    }
    
    /**
     * Update aggregate score when review approved
     * 
     * @param int $review_id Review ID
     * @param int $business_id Business ID
     */
    public function update_aggregate_score($review_id, $business_id) {
        // Recalculate score with new review data
        $this->calculate_business_score($business_id, ['trigger' => 'new_review']);
    }
    
    /**
     * Get score display HTML
     * 
     * @param int $business_id Business ID
     * @param array $args Display arguments
     * @return string HTML
     */
    public function get_score_display($business_id, $args = []) {
        $defaults = [
            'show_pillars' => false,
            'show_label' => true,
            'format' => 'default',
            'class' => 'zippicks-score'
        ];
        
        $args = wp_parse_args($args, $defaults);
        $score_data = $this->get_master_score($business_id);
        
        if (!$score_data) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($args['class']); ?>" data-score="<?php echo esc_attr($score_data['overall']); ?>">
            <?php if ($args['show_label']): ?>
                <span class="zippicks-score-label"><?php esc_html_e('ZipPicks Score', 'zippicks-foundation'); ?></span>
            <?php endif; ?>
            
            <span class="zippicks-score-value"><?php echo esc_html($score_data['overall']); ?></span>
            
            <?php if ($args['show_pillars'] && !empty($score_data['pillars'])): ?>
                <div class="zippicks-score-pillars">
                    <?php foreach ($score_data['pillars'] as $pillar_key => $pillar_data): ?>
                        <div class="zippicks-score-pillar" data-pillar="<?php echo esc_attr($pillar_key); ?>">
                            <span class="pillar-label"><?php echo esc_html($pillar_data['label']); ?></span>
                            <span class="pillar-score"><?php echo esc_html($pillar_data['score']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
}