<?php
/**
 * Vibe Integration Service
 *
 * Filters and prioritizes restaurants based on their vibes
 * to match specific list types and contexts.
 *
 * @package ZipPicks_Master_Critic
 * @since 2.0.0
 */

class ZipPicks_Master_Critic_Vibe_Integration {
    
    /**
     * Vibe relevance mappings for different list types
     */
    const VIBE_MAPPINGS = [
        'date_night' => [
            'primary' => ['romantic', 'intimate', 'cozy', 'upscale', 'trendy'],
            'secondary' => ['wine_bar', 'cocktails', 'live_music', 'scenic_view', 'elegant'],
            'avoid' => ['family_friendly', 'loud', 'sports_bar', 'quick_bites']
        ],
        'family_friendly' => [
            'primary' => ['family_friendly', 'kid_friendly', 'casual', 'spacious', 'fun'],
            'secondary' => ['outdoor_seating', 'large_portions', 'diverse_menu', 'quick_service'],
            'avoid' => ['bar_scene', 'late_night', 'intimate', 'expensive']
        ],
        'business_lunch' => [
            'primary' => ['business_friendly', 'quiet', 'professional', 'quick_service', 'wifi'],
            'secondary' => ['private_rooms', 'upscale', 'central_location', 'valet_parking'],
            'avoid' => ['loud', 'bar_scene', 'slow_service', 'casual_only']
        ],
        'late_night' => [
            'primary' => ['late_night', 'open_late', 'bar_scene', 'nightlife', 'energetic'],
            'secondary' => ['live_music', 'dj', 'cocktails', 'small_plates', 'downtown'],
            'avoid' => ['breakfast_only', 'family_friendly', 'early_close']
        ],
        'healthy' => [
            'primary' => ['healthy', 'organic', 'vegetarian_friendly', 'vegan_options', 'fresh'],
            'secondary' => ['farm_to_table', 'locally_sourced', 'gluten_free', 'juice_bar'],
            'avoid' => ['fast_food', 'fried_food', 'heavy', 'comfort_food']
        ],
        'brunch' => [
            'primary' => ['brunch', 'breakfast', 'bottomless_mimosas', 'weekend_brunch', 'all_day_breakfast'],
            'secondary' => ['outdoor_seating', 'coffee', 'pastries', 'sunny', 'relaxed'],
            'avoid' => ['dinner_only', 'late_night', 'no_breakfast']
        ],
        'authentic' => [
            'primary' => ['authentic', 'traditional', 'ethnic', 'local_favorite', 'hidden_gem'],
            'secondary' => ['family_owned', 'immigrant_owned', 'regional_cuisine', 'cultural'],
            'avoid' => ['chain', 'fusion', 'americanized', 'touristy']
        ]
    ];
    
    /**
     * Vibe confidence score weights
     */
    const CONFIDENCE_WEIGHTS = [
        'user_generated' => 1.0,
        'verified' => 0.9,
        'ai_suggested' => 0.7,
        'inferred' => 0.5
    ];
    
    /**
     * Logger instance
     *
     * @var object
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
    }
    
    /**
     * Filter restaurants by relevant vibes
     *
     * @param array $restaurants All restaurants
     * @param string $list_type Type of list (date_night, family_friendly, etc)
     * @param array $options Additional filtering options
     * @return array Vibe-filtered and scored restaurants
     */
    public function filter_by_vibes($restaurants, $list_type, $options = []) {
        if (empty($restaurants)) {
            return [];
        }
        
        // Get vibe mapping for this list type
        $vibe_map = $this->get_vibe_mapping($list_type);
        
        // Score each restaurant
        $scored = [];
        foreach ($restaurants as $restaurant) {
            $vibe_score = $this->calculate_vibe_score($restaurant, $vibe_map, $options);
            
            if ($vibe_score > 0) {
                $restaurant['vibe_score'] = $vibe_score;
                $restaurant['vibe_matches'] = $this->get_matching_vibes($restaurant, $vibe_map);
                $scored[] = $restaurant;
            }
        }
        
        // Sort by vibe score
        usort($scored, function($a, $b) {
            return $b['vibe_score'] <=> $a['vibe_score'];
        });
        
        // Apply limits if specified
        $limit = $options['limit'] ?? 40;
        $scored = array_slice($scored, 0, $limit);
        
        if ($this->logger) {
            $this->logger->info('Vibe filtering complete', [
                'list_type' => $list_type,
                'input_count' => count($restaurants),
                'output_count' => count($scored),
                'top_score' => $scored[0]['vibe_score'] ?? 0
            ]);
        }
        
        return $scored;
    }
    
    /**
     * Get vibe display priority for a restaurant
     *
     * @param array $restaurant Restaurant data
     * @param string $context Display context
     * @return array Prioritized vibes for display
     */
    public function prioritize_vibes($restaurant, $context) {
        $vibes = $restaurant['vibes'] ?? [];
        
        if (empty($vibes)) {
            return [];
        }
        
        // Get context mapping
        $vibe_map = $this->get_vibe_mapping($context);
        
        $prioritized = [];
        $scores = [];
        
        // Score each vibe based on context
        foreach ($vibes as $vibe) {
            $vibe_key = $this->normalize_vibe($vibe['name'] ?? $vibe);
            $score = 0;
            
            if (in_array($vibe_key, $vibe_map['primary'])) {
                $score = 3;
            } elseif (in_array($vibe_key, $vibe_map['secondary'])) {
                $score = 2;
            } elseif (!in_array($vibe_key, $vibe_map['avoid'])) {
                $score = 1;
            }
            
            // Apply confidence modifier
            $confidence = $vibe['confidence'] ?? 1.0;
            $score *= $confidence;
            
            $scores[$vibe_key] = [
                'vibe' => $vibe,
                'score' => $score
            ];
        }
        
        // Sort by score
        uasort($scores, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Return top vibes
        $limit = 5;
        foreach (array_slice($scores, 0, $limit) as $item) {
            $prioritized[] = $item['vibe'];
        }
        
        return $prioritized;
    }
    
    /**
     * Calculate vibe score for a restaurant
     *
     * @param array $restaurant Restaurant data
     * @param array $vibe_map Vibe mapping for context
     * @param array $options Scoring options
     * @return float Vibe score (0-100)
     */
    private function calculate_vibe_score($restaurant, $vibe_map, $options = []) {
        $vibes = $restaurant['vibes'] ?? [];
        
        if (empty($vibes)) {
            return 0;
        }
        
        $score = 0;
        $primary_matches = 0;
        $secondary_matches = 0;
        $avoid_matches = 0;
        
        foreach ($vibes as $vibe) {
            $vibe_key = $this->normalize_vibe($vibe['name'] ?? $vibe);
            $confidence = $vibe['confidence'] ?? 1.0;
            
            if (in_array($vibe_key, $vibe_map['primary'])) {
                $primary_matches++;
                $score += 30 * $confidence;
            } elseif (in_array($vibe_key, $vibe_map['secondary'])) {
                $secondary_matches++;
                $score += 15 * $confidence;
            } elseif (in_array($vibe_key, $vibe_map['avoid'])) {
                $avoid_matches++;
                $score -= 20 * $confidence;
            }
        }
        
        // Bonus for multiple primary matches
        if ($primary_matches >= 2) {
            $score += 20;
        }
        
        // Additional factors
        if (!empty($options['boost_verified']) && !empty($restaurant['verified'])) {
            $score *= 1.2;
        }
        
        if (!empty($options['boost_popular']) && !empty($restaurant['popularity_score'])) {
            $score += $restaurant['popularity_score'] * 10;
        }
        
        // Ensure score is within bounds
        return max(0, min(100, $score));
    }
    
    /**
     * Get matching vibes for a restaurant
     *
     * @param array $restaurant Restaurant data
     * @param array $vibe_map Vibe mapping
     * @return array Matching vibes categorized
     */
    private function get_matching_vibes($restaurant, $vibe_map) {
        $vibes = $restaurant['vibes'] ?? [];
        $matches = [
            'primary' => [],
            'secondary' => []
        ];
        
        foreach ($vibes as $vibe) {
            $vibe_key = $this->normalize_vibe($vibe['name'] ?? $vibe);
            
            if (in_array($vibe_key, $vibe_map['primary'])) {
                $matches['primary'][] = $vibe;
            } elseif (in_array($vibe_key, $vibe_map['secondary'])) {
                $matches['secondary'][] = $vibe;
            }
        }
        
        return $matches;
    }
    
    /**
     * Get vibe mapping for a list type
     *
     * @param string $list_type List type identifier
     * @return array Vibe mapping
     */
    private function get_vibe_mapping($list_type) {
        // Check for direct mapping
        if (isset(self::VIBE_MAPPINGS[$list_type])) {
            return self::VIBE_MAPPINGS[$list_type];
        }
        
        // Try to infer from list type
        $inferred = $this->infer_vibe_mapping($list_type);
        if ($inferred) {
            return $inferred;
        }
        
        // Default neutral mapping
        return [
            'primary' => [],
            'secondary' => [],
            'avoid' => []
        ];
    }
    
    /**
     * Infer vibe mapping from list type name
     *
     * @param string $list_type List type
     * @return array|null Inferred mapping
     */
    private function infer_vibe_mapping($list_type) {
        $normalized = strtolower(str_replace(['_', '-'], ' ', $list_type));
        
        // Check for keywords
        $keywords = [
            'romantic' => 'date_night',
            'family' => 'family_friendly',
            'kids' => 'family_friendly',
            'business' => 'business_lunch',
            'professional' => 'business_lunch',
            'night' => 'late_night',
            'healthy' => 'healthy',
            'vegan' => 'healthy',
            'brunch' => 'brunch',
            'breakfast' => 'brunch',
            'authentic' => 'authentic',
            'traditional' => 'authentic'
        ];
        
        foreach ($keywords as $keyword => $mapping_key) {
            if (strpos($normalized, $keyword) !== false) {
                return self::VIBE_MAPPINGS[$mapping_key] ?? null;
            }
        }
        
        return null;
    }
    
    /**
     * Normalize vibe name for comparison
     *
     * @param string $vibe Vibe name
     * @return string Normalized vibe key
     */
    private function normalize_vibe($vibe) {
        $vibe = strtolower(trim($vibe));
        $vibe = str_replace([' ', '-'], '_', $vibe);
        $vibe = preg_replace('/[^a-z0-9_]/', '', $vibe);
        return $vibe;
    }
    
    /**
     * Get all available vibes from restaurants
     *
     * @param array $restaurants Restaurant array
     * @return array Unique vibes with counts
     */
    public function get_available_vibes($restaurants) {
        $vibe_counts = [];
        
        foreach ($restaurants as $restaurant) {
            $vibes = $restaurant['vibes'] ?? [];
            
            foreach ($vibes as $vibe) {
                $vibe_name = $vibe['name'] ?? $vibe;
                $vibe_key = $this->normalize_vibe($vibe_name);
                
                if (!isset($vibe_counts[$vibe_key])) {
                    $vibe_counts[$vibe_key] = [
                        'name' => $vibe_name,
                        'count' => 0,
                        'confidence_sum' => 0
                    ];
                }
                
                $vibe_counts[$vibe_key]['count']++;
                $vibe_counts[$vibe_key]['confidence_sum'] += $vibe['confidence'] ?? 1.0;
            }
        }
        
        // Calculate average confidence
        foreach ($vibe_counts as &$vibe_data) {
            $vibe_data['avg_confidence'] = $vibe_data['confidence_sum'] / $vibe_data['count'];
        }
        
        // Sort by count
        uasort($vibe_counts, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        
        return $vibe_counts;
    }
    
    /**
     * Suggest vibes for a restaurant based on its attributes
     *
     * @param array $restaurant Restaurant data
     * @return array Suggested vibes
     */
    public function suggest_vibes($restaurant) {
        $suggestions = [];
        
        // Price-based suggestions
        $price_level = $restaurant['price_level'] ?? 0;
        if ($price_level >= 3) {
            $suggestions[] = 'upscale';
            $suggestions[] = 'special_occasion';
        } elseif ($price_level == 1) {
            $suggestions[] = 'budget_friendly';
            $suggestions[] = 'casual';
        }
        
        // Cuisine-based suggestions
        $cuisine = strtolower($restaurant['cuisine_type'] ?? '');
        $cuisine_vibes = [
            'italian' => ['romantic', 'cozy', 'wine_bar'],
            'japanese' => ['authentic', 'fresh', 'minimalist'],
            'mexican' => ['vibrant', 'festive', 'casual'],
            'french' => ['elegant', 'romantic', 'upscale'],
            'american' => ['comfort_food', 'casual', 'family_friendly']
        ];
        
        if (isset($cuisine_vibes[$cuisine])) {
            $suggestions = array_merge($suggestions, $cuisine_vibes[$cuisine]);
        }
        
        // Time-based suggestions
        if (!empty($restaurant['hours'])) {
            if ($this->serves_breakfast($restaurant['hours'])) {
                $suggestions[] = 'breakfast';
                $suggestions[] = 'brunch';
            }
            if ($this->open_late($restaurant['hours'])) {
                $suggestions[] = 'late_night';
                $suggestions[] = 'nightlife';
            }
        }
        
        // Remove duplicates and return
        return array_unique($suggestions);
    }
    
    /**
     * Check if restaurant serves breakfast
     *
     * @param array $hours Restaurant hours
     * @return bool
     */
    private function serves_breakfast($hours) {
        foreach ($hours as $day => $times) {
            if (!empty($times['open']) && strtotime($times['open']) <= strtotime('10:00')) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if restaurant is open late
     *
     * @param array $hours Restaurant hours
     * @return bool
     */
    private function open_late($hours) {
        foreach ($hours as $day => $times) {
            if (!empty($times['close']) && 
                (strtotime($times['close']) >= strtotime('23:00') || 
                 strtotime($times['close']) <= strtotime('02:00'))) {
                return true;
            }
        }
        return false;
    }
}