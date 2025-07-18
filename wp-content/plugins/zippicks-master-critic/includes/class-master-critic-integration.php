<?php
/**
 * Master Critic Integration Class
 * 
 * Demonstrates how the ScoringEngine integrates with WordPress
 * and the broader ZipPicks plugin ecosystem
 *
 * @package ZipPicks_MasterCritic
 * @since 1.0.0
 */

class ZP_MasterCritic_Integration {
    
    /**
     * Scoring engine instance
     * @var ZP_MasterCritic_ScoringEngine
     */
    private $scoring_engine;
    
    /**
     * Constructor
     */
    public function __construct() {
        require_once plugin_dir_path(__FILE__) . 'ScoringEngine.php';
        $this->scoring_engine = new ZP_MasterCritic_ScoringEngine();
    }
    
    /**
     * Generate Top 10 list for a city and dish combination
     * 
     * @param string $city City name
     * @param string $dish_category Dish category (e.g., 'pizza', 'sushi', 'burgers')
     * @param array $restaurant_data Array of restaurant data with reviews
     * @return array Scored and ranked restaurants
     */
    public function generate_top_10($city, $dish_category, $restaurant_data) {
        // Calculate scores using the engine
        $scored_restaurants = $this->scoring_engine->calculate_scores($restaurant_data);
        
        // Take top 10
        $top_10 = array_slice($scored_restaurants, 0, 10);
        
        // Add metadata
        $result = [
            'city' => $city,
            'dish_category' => $dish_category,
            'generated_at' => current_time('mysql'),
            'restaurants' => $top_10
        ];
        
        return $result;
    }
    
    /**
     * Store Top 10 list as WordPress post
     * 
     * @param array $top_10_data Generated Top 10 data
     * @return int|WP_Error Post ID on success, WP_Error on failure
     */
    public function save_top_10_as_post($top_10_data) {
        $title = sprintf(
            'Top 10 %s in %s',
            ucwords($top_10_data['dish_category']),
            ucwords($top_10_data['city'])
        );
        
        // Build content
        $content = $this->build_top_10_content($top_10_data);
        
        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => 'master_critic_list',
            'post_status' => 'publish',
            'meta_input' => [
                '_mc_city' => $top_10_data['city'],
                '_mc_dish_category' => $top_10_data['dish_category'],
                '_mc_generated_at' => $top_10_data['generated_at'],
                '_mc_scores_data' => json_encode($top_10_data['restaurants'])
            ]
        ];
        
        return wp_insert_post($post_data);
    }
    
    /**
     * Build HTML content for Top 10 post
     * 
     * @param array $top_10_data Top 10 data
     * @return string HTML content
     */
    private function build_top_10_content($top_10_data) {
        $content = '';
        
        foreach ($top_10_data['restaurants'] as $restaurant) {
            $content .= sprintf(
                '<div class="mc-restaurant-entry" data-rank="%d">',
                $restaurant['rank']
            );
            
            // Restaurant header
            $content .= sprintf(
                '<h3>%d. %s <span class="mc-score">%s</span></h3>',
                $restaurant['rank'],
                esc_html($restaurant['name']),
                $restaurant['score']
            );
            
            // Meta info
            $content .= sprintf(
                '<div class="mc-meta">%s • %d reviews • %s confidence</div>',
                $restaurant['price_tier'],
                $restaurant['review_count'],
                $restaurant['confidence']
            );
            
            // Editorial summary
            $content .= sprintf(
                '<div class="mc-summary">%s</div>',
                esc_html($restaurant['summary'])
            );
            
            // Pillar scores
            $content .= '<div class="mc-pillars">';
            foreach ($restaurant['pillar_scores'] as $pillar => $score) {
                $pillar_name = ucwords(str_replace('_', ' ', $pillar));
                $content .= sprintf(
                    '<span class="mc-pillar">%s: %s</span>',
                    $pillar_name,
                    $score
                );
            }
            $content .= '</div>';
            
            // Top dishes
            if (!empty($restaurant['top_dishes'])) {
                $content .= '<div class="mc-dishes">Top Dishes: ';
                $content .= esc_html(implode(', ', $restaurant['top_dishes']));
                $content .= '</div>';
            }
            
            $content .= '</div>';
        }
        
        return $content;
    }
    
    /**
     * Get structured data for SEO/Schema.org
     * 
     * @param array $top_10_data Top 10 data
     * @return array Schema.org structured data
     */
    public function get_structured_data($top_10_data) {
        $structured_data = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => sprintf(
                'Top 10 %s in %s',
                ucwords($top_10_data['dish_category']),
                ucwords($top_10_data['city'])
            ),
            'description' => sprintf(
                'Master Critic AI-curated list of the best %s restaurants in %s',
                $top_10_data['dish_category'],
                $top_10_data['city']
            ),
            'itemListElement' => []
        ];
        
        foreach ($top_10_data['restaurants'] as $restaurant) {
            $structured_data['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $restaurant['rank'],
                'item' => [
                    '@type' => 'Restaurant',
                    'name' => $restaurant['name'],
                    'aggregateRating' => [
                        '@type' => 'AggregateRating',
                        'ratingValue' => $restaurant['score'],
                        'bestRating' => '10',
                        'worstRating' => '0',
                        'reviewCount' => $restaurant['review_count']
                    ],
                    'priceRange' => $restaurant['price_tier'],
                    'description' => $restaurant['summary']
                ]
            ];
        }
        
        return $structured_data;
    }
    
    /**
     * REST API endpoint handler for scoring
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response with scored data
     */
    public function rest_calculate_scores($request) {
        $restaurant_data = $request->get_param('restaurants');
        
        if (empty($restaurant_data) || !is_array($restaurant_data)) {
            return new WP_Error(
                'invalid_data',
                'Invalid restaurant data provided',
                ['status' => 400]
            );
        }
        
        try {
            $scores = $this->scoring_engine->calculate_scores($restaurant_data);
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $scores
            ], 200);
            
        } catch (Exception $e) {
            return new WP_Error(
                'scoring_error',
                'Error calculating scores: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
    
    /**
     * AJAX handler for admin scoring preview
     */
    public function ajax_preview_scores() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mc_preview_scores')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $restaurant_data = json_decode(stripslashes($_POST['restaurant_data']), true);
        
        if (!$restaurant_data) {
            wp_send_json_error('Invalid restaurant data');
        }
        
        try {
            $scores = $this->scoring_engine->calculate_scores($restaurant_data);
            wp_send_json_success($scores);
        } catch (Exception $e) {
            wp_send_json_error('Scoring error: ' . $e->getMessage());
        }
    }
    
    /**
     * Batch process restaurants for a city
     * 
     * @param string $city City name
     * @param string $dish_category Dish category
     * @return array Processing results
     */
    public function batch_process_city($city, $dish_category) {
        $results = [
            'city' => $city,
            'dish_category' => $dish_category,
            'processed' => 0,
            'errors' => []
        ];
        
        // In real implementation, this would fetch from external APIs
        // For now, using mock data structure
        $restaurant_batches = $this->fetch_restaurant_data($city, $dish_category);
        
        foreach ($restaurant_batches as $batch) {
            try {
                $scored = $this->scoring_engine->calculate_scores($batch);
                
                // Store results
                $top_10_data = [
                    'city' => $city,
                    'dish_category' => $dish_category,
                    'generated_at' => current_time('mysql'),
                    'restaurants' => array_slice($scored, 0, 10)
                ];
                
                $post_id = $this->save_top_10_as_post($top_10_data);
                
                if (!is_wp_error($post_id)) {
                    $results['processed']++;
                } else {
                    $results['errors'][] = $post_id->get_error_message();
                }
                
            } catch (Exception $e) {
                $results['errors'][] = $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Mock function to simulate fetching restaurant data
     * 
     * @param string $city City name
     * @param string $dish_category Dish category
     * @return array Restaurant data batches
     */
    private function fetch_restaurant_data($city, $dish_category) {
        // In production, this would integrate with Yelp, Google, TripAdvisor APIs
        // Returns array of restaurant data batches
        return [];
    }
}