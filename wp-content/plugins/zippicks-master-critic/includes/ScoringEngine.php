<?php
/**
 * ZipPicks Master Critic Scoring Engine
 * 
 * Generates deterministic, review-weighted scores across six editorial pillars
 * and simulates credible editorial summaries grounded in review content.
 *
 * @package ZipPicks_MasterCritic
 * @since 1.0.0
 */

class ZP_MasterCritic_ScoringEngine {
    
    /**
     * Source weight distribution for aggregated scoring
     * @var array
     */
    private $source_weights = [
        'yelp' => 0.50,
        'google' => 0.30,
        'tripadvisor' => 0.20
    ];
    
    /**
     * Pillar multipliers for heuristic score distribution
     * @var array
     */
    private $pillar_multipliers = [
        'food_quality' => 1.05,
        'service' => 0.95,
        'atmosphere_design' => 1.00,
        'value' => 0.90,
        'consistency' => 1.00,
        'cultural_relevance' => 1.00
    ];
    
    /**
     * Editorial tone patterns for summary generation
     * @var array
     */
    private $editorial_patterns = [
        'emotional_anchors' => [
            'feels like a secret',
            'operates with quiet confidence',
            'makes regulars protective',
            'transcendent moments',
            'understated excellence',
            'hidden gem status',
            'neighborhood favorite',
            'culinary sanctuary'
        ],
        'descriptor_phrases' => [
            'executed with precision',
            'speaks louder than any marketing',
            'transforms ingredients into art',
            'delivers consistently',
            'showcases mastery',
            'elevates the ordinary',
            'redefines expectations',
            'sets the standard'
        ],
        'vibe_descriptors' => [
            'cozy', 'intimate', 'vibrant', 'sophisticated', 'unpretentious',
            'welcoming', 'refined', 'authentic', 'contemporary', 'timeless'
        ]
    ];
    
    /**
     * Calculate scores for an array of restaurant data
     * 
     * @param array $restaurant_data Array of restaurant information
     * @return array Sorted array of scored restaurants with editorial content
     */
    public function calculate_scores(array $restaurant_data): array {
        $scored_restaurants = [];
        
        foreach ($restaurant_data as $restaurant) {
            // Ensure sources is an array
            $sources = isset($restaurant['sources']) && is_array($restaurant['sources']) 
                ? $restaurant['sources'] 
                : [];
            
            // Calculate base score from sources
            $base_score = $this->calculate_base_score($sources);
            
            // Apply confidence modifier based on total reviews
            $total_reviews = $this->get_total_reviews($sources);
            $confidence_modifier = $this->get_confidence_modifier($total_reviews);
            $adjusted_score = $base_score + $confidence_modifier;
            
            // Ensure score doesn't exceed 10.0
            $final_score = min($adjusted_score, 10.0);
            
            // Generate pillar scores
            $pillar_scores = $this->generate_pillar_scores($final_score);
            
            // Generate editorial summary
            $summary = $this->generate_editorial_summary($restaurant);
            
            // Determine confidence level
            $confidence = $this->determine_confidence($total_reviews);
            
            $scored_restaurants[] = [
                'name' => $restaurant['name'],
                'score' => round($final_score, 1),
                'review_count' => $total_reviews,
                'price_tier' => $restaurant['price_tier'] ?? '$$',
                'summary' => $summary,
                'top_dishes' => $restaurant['top_dishes'] ?? [],
                'pillar_scores' => $pillar_scores,
                'confidence' => $confidence
            ];
        }
        
        // Sort by score descending
        usort($scored_restaurants, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Add rankings
        foreach ($scored_restaurants as $index => &$restaurant) {
            $restaurant['rank'] = $index + 1;
        }
        
        return $scored_restaurants;
    }
    
    /**
     * Calculate weighted base score from multiple sources
     * 
     * @param array $sources Source scores and review counts
     * @return float Weighted average score on 10-point scale
     */
    private function calculate_base_score(array $sources): float {
        $weighted_sum = 0;
        $weight_total = 0;
        
        foreach ($sources as $source_name => $source_data) {
            if (!is_array($source_data) || !isset($source_data['reviews']) || !isset($source_data['score'])) {
                continue;
            }
            
            $source_key = strtolower($source_name);
            
            if (isset($this->source_weights[$source_key]) && $source_data['reviews'] > 0) {
                // Normalize to 10-point scale (assuming source scores are out of 5)
                $normalized_score = $source_data['score'] * 2;
                
                // Apply source weight
                $weighted_sum += $normalized_score * $this->source_weights[$source_key];
                $weight_total += $this->source_weights[$source_key];
            }
        }
        
        // Return weighted average, or 0 if no valid sources
        return $weight_total > 0 ? $weighted_sum / $weight_total : 0;
    }
    
    /**
     * Calculate total review count across all sources
     * 
     * @param array $sources Source data with review counts
     * @return int Total number of reviews
     */
    private function get_total_reviews(array $sources): int {
        $total = 0;
        
        foreach ($sources as $source_data) {
            if (isset($source_data['reviews']) && is_numeric($source_data['reviews'])) {
                $total += (int) $source_data['reviews'];
            }
        }
        
        return $total;
    }
    
    /**
     * Get confidence modifier based on review volume
     * 
     * @param int $total_reviews Total review count
     * @return float Score modifier (-0.3 to +0.1)
     */
    private function get_confidence_modifier(int $total_reviews): float {
        if ($total_reviews < 100) {
            return -0.3;
        } elseif ($total_reviews >= 500) {
            return 0.1;
        }
        
        // 100-499 reviews: no modifier
        return 0.0;
    }
    
    /**
     * Generate pillar scores using heuristic multipliers
     * 
     * @param float $base_score The adjusted base score
     * @return array Pillar scores rounded to 1 decimal
     */
    private function generate_pillar_scores(float $base_score): array {
        $pillar_scores = [];
        
        foreach ($this->pillar_multipliers as $pillar => $multiplier) {
            $pillar_score = $base_score * $multiplier;
            // Cap at 10.0 and round to 1 decimal
            $pillar_scores[$pillar] = round(min($pillar_score, 10.0), 1);
        }
        
        return $pillar_scores;
    }
    
    /**
     * Generate editorial summary using pattern matching and review content
     * 
     * @param array $restaurant Restaurant data including reviews
     * @return string 3-4 sentence editorial summary
     */
    private function generate_editorial_summary(array $restaurant): string {
        $sentences = [];
        
        // Extract key phrases and sentiments from reviews
        $review_insights = $this->extract_review_insights($restaurant['reviews']);
        
        // First sentence: Emotional anchor + positioning
        $emotional_anchor = $this->editorial_patterns['emotional_anchors'][array_rand($this->editorial_patterns['emotional_anchors'])];
        $descriptor = $this->editorial_patterns['descriptor_phrases'][array_rand($this->editorial_patterns['descriptor_phrases'])];
        $sentences[] = ucfirst($restaurant['name']) . "'s " . $emotional_anchor . " " . $descriptor . ".";
        
        // Second sentence: Specific dish callout with vibe
        $vibe = $this->editorial_patterns['vibe_descriptors'][array_rand($this->editorial_patterns['vibe_descriptors'])];
        if (!empty($restaurant['top_dishes'])) {
            $dish = $restaurant['top_dishes'][array_rand($restaurant['top_dishes'])];
            $sentences[] = "This " . $vibe . " spot delivers exceptional " . strtolower($dish) . " that " . $this->generate_dish_descriptor($review_insights) . ".";
        } else {
            $sentences[] = "The " . $vibe . " atmosphere perfectly complements the " . $this->generate_cuisine_descriptor($review_insights) . ".";
        }
        
        // Third sentence: Service/experience insight
        if (!empty($review_insights['positive_phrases'])) {
            $insight = $review_insights['positive_phrases'][array_rand($review_insights['positive_phrases'])];
            $sentences[] = "Diners consistently praise the " . $insight . ", creating an experience that " . $this->generate_impact_phrase() . ".";
        } else {
            $sentences[] = "It's the kind of place that " . $this->generate_impact_phrase() . " through " . $this->generate_strength_phrase() . ".";
        }
        
        return implode(' ', $sentences);
    }
    
    /**
     * Extract insights from review content
     * 
     * @param array $reviews Array of review data
     * @return array Extracted insights and phrases
     */
    private function extract_review_insights(array $reviews): array {
        $insights = [
            'positive_phrases' => [],
            'dish_mentions' => [],
            'vibe_words' => []
        ];
        
        $positive_indicators = ['perfect', 'exceptional', 'amazing', 'excellent', 'fantastic', 'great', 'wonderful', 'delicious', 'fresh', 'homemade', 'authentic'];
        $vibe_indicators = ['cozy', 'family', 'intimate', 'vibrant', 'welcoming', 'comfortable', 'romantic', 'trendy', 'classic', 'modern'];
        
        foreach ($reviews as $review) {
            $content_lower = strtolower($review['content']);
            
            // Extract positive phrases
            foreach ($positive_indicators as $indicator) {
                if (strpos($content_lower, $indicator) !== false) {
                    // Extract surrounding context
                    preg_match('/(\w+\s+)?' . $indicator . '(\s+\w+)?/i', $content_lower, $matches);
                    if (!empty($matches[0])) {
                        $insights['positive_phrases'][] = trim($matches[0]);
                    }
                }
            }
            
            // Extract vibe words
            foreach ($vibe_indicators as $vibe) {
                if (strpos($content_lower, $vibe) !== false) {
                    $insights['vibe_words'][] = $vibe;
                }
            }
        }
        
        // Deduplicate
        $insights['positive_phrases'] = array_unique($insights['positive_phrases']);
        $insights['vibe_words'] = array_unique($insights['vibe_words']);
        
        return $insights;
    }
    
    /**
     * Generate dish descriptor based on review insights
     * 
     * @param array $insights Review insights
     * @return string Dish descriptor phrase
     */
    private function generate_dish_descriptor(array $insights): string {
        $descriptors = [
            'lingers on the palate',
            'showcases culinary expertise',
            'hits all the right notes',
            'exemplifies the chef\'s vision',
            'delivers memorable flavors',
            'sets a new standard',
            'achieves perfect balance'
        ];
        
        return $descriptors[array_rand($descriptors)];
    }
    
    /**
     * Generate cuisine descriptor
     * 
     * @param array $insights Review insights
     * @return string Cuisine descriptor
     */
    private function generate_cuisine_descriptor(array $insights): string {
        $descriptors = [
            'carefully crafted dishes',
            'thoughtfully prepared cuisine',
            'expertly executed menu',
            'seasonally inspired offerings',
            'meticulously sourced ingredients',
            'chef-driven creations'
        ];
        
        return $descriptors[array_rand($descriptors)];
    }
    
    /**
     * Generate impact phrase for summary conclusion
     * 
     * @return string Impact phrase
     */
    private function generate_impact_phrase(): string {
        $phrases = [
            'keeps locals coming back',
            'turns first-timers into regulars',
            'creates lasting impressions',
            'builds a devoted following',
            'earns its reputation daily',
            'justifies the journey',
            'rewards the adventurous'
        ];
        
        return $phrases[array_rand($phrases)];
    }
    
    /**
     * Generate strength phrase
     * 
     * @return string Strength descriptor
     */
    private function generate_strength_phrase(): string {
        $phrases = [
            'consistent excellence',
            'unwavering quality',
            'thoughtful execution',
            'passionate dedication',
            'meticulous attention to detail',
            'genuine hospitality'
        ];
        
        return $phrases[array_rand($phrases)];
    }
    
    /**
     * Determine confidence level based on review count
     * 
     * @param int $total_reviews Total number of reviews
     * @return string Confidence level (low/medium/high)
     */
    private function determine_confidence(int $total_reviews): string {
        if ($total_reviews < 50) {
            return 'low';
        } elseif ($total_reviews < 200) {
            return 'medium';
        }
        
        return 'high';
    }
}