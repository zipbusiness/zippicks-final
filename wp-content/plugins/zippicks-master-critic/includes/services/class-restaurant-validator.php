<?php
/**
 * Restaurant Validator Service
 *
 * Validates AI-generated restaurant selections against real API data
 * to ensure all recommendations are for actual businesses.
 *
 * @package ZipPicks_Master_Critic
 * @since 2.0.0
 */

class ZipPicks_Master_Critic_Restaurant_Validator {
    
    /**
     * Confidence thresholds for matching
     */
    const EXACT_MATCH_THRESHOLD = 1.0;
    const HIGH_CONFIDENCE_THRESHOLD = 0.85;
    const MEDIUM_CONFIDENCE_THRESHOLD = 0.70;
    const LOW_CONFIDENCE_THRESHOLD = 0.50;
    
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
     * Validate AI response against real restaurant data
     *
     * @param array $ai_selections AI's restaurant picks
     * @param array $api_restaurants Real restaurants from API
     * @return array Validated and matched restaurants
     */
    public function validate_selections($ai_selections, $api_restaurants) {
        if (empty($ai_selections) || empty($api_restaurants)) {
            if ($this->logger) {
                $this->logger->warning('Restaurant validation skipped - empty data', [
                    'ai_count' => count($ai_selections),
                    'api_count' => count($api_restaurants)
                ]);
            }
            return [];
        }
        
        // Index API restaurants by zpid for quick lookup
        $api_index = $this->index_restaurants($api_restaurants);
        
        $validated = [];
        $unmatched = [];
        
        foreach ($ai_selections as $ai_restaurant) {
            $match = $this->find_best_match($ai_restaurant, $api_restaurants, $api_index);
            
            if ($match) {
                // Merge AI scoring with real restaurant data
                $validated_entry = $this->merge_restaurant_data($ai_restaurant, $match['restaurant']);
                $validated_entry['match_confidence'] = $match['confidence'];
                $validated_entry['match_method'] = $match['method'];
                
                $validated[] = $validated_entry;
                
                if ($this->logger && $match['confidence'] < self::HIGH_CONFIDENCE_THRESHOLD) {
                    $this->logger->info('Restaurant matched with lower confidence', [
                        'ai_name' => $ai_restaurant['name'] ?? 'unknown',
                        'matched_name' => $match['restaurant']['name'] ?? 'unknown',
                        'confidence' => $match['confidence'],
                        'method' => $match['method']
                    ]);
                }
            } else {
                $unmatched[] = $ai_restaurant;
                
                if ($this->logger) {
                    $this->logger->warning('Restaurant could not be matched', [
                        'ai_name' => $ai_restaurant['name'] ?? 'unknown',
                        'ai_address' => $ai_restaurant['address'] ?? 'unknown'
                    ]);
                }
            }
        }
        
        // Log validation summary
        if ($this->logger) {
            $this->logger->info('Restaurant validation complete', [
                'total_ai_selections' => count($ai_selections),
                'validated_count' => count($validated),
                'unmatched_count' => count($unmatched),
                'validation_rate' => round((count($validated) / count($ai_selections)) * 100, 2) . '%'
            ]);
        }
        
        return $validated;
    }
    
    /**
     * Find the best match for an AI-selected restaurant
     *
     * @param array $ai_restaurant AI-selected restaurant
     * @param array $api_restaurants All API restaurants
     * @param array $api_index Indexed API data
     * @return array|null Match data with confidence score
     */
    private function find_best_match($ai_restaurant, $api_restaurants, $api_index) {
        $candidates = [];
        
        // Method 1: Try exact ZPID match if AI provided one
        if (!empty($ai_restaurant['zpid']) && isset($api_index['by_zpid'][$ai_restaurant['zpid']])) {
            return [
                'restaurant' => $api_index['by_zpid'][$ai_restaurant['zpid']],
                'confidence' => self::EXACT_MATCH_THRESHOLD,
                'method' => 'zpid_match'
            ];
        }
        
        // Method 2: Try exact name match
        $ai_name_normalized = $this->normalize_name($ai_restaurant['name'] ?? '');
        if (isset($api_index['by_normalized_name'][$ai_name_normalized])) {
            return [
                'restaurant' => $api_index['by_normalized_name'][$ai_name_normalized],
                'confidence' => 0.95,
                'method' => 'exact_name_match'
            ];
        }
        
        // Method 3: Fuzzy name matching with additional criteria
        foreach ($api_restaurants as $api_restaurant) {
            $confidence = $this->calculate_match_confidence($ai_restaurant, $api_restaurant);
            
            if ($confidence >= self::LOW_CONFIDENCE_THRESHOLD) {
                $candidates[] = [
                    'restaurant' => $api_restaurant,
                    'confidence' => $confidence,
                    'method' => 'fuzzy_match'
                ];
            }
        }
        
        // Return best candidate if any
        if (!empty($candidates)) {
            usort($candidates, function($a, $b) {
                return $b['confidence'] <=> $a['confidence'];
            });
            
            // Only return if confidence is high enough
            if ($candidates[0]['confidence'] >= self::MEDIUM_CONFIDENCE_THRESHOLD) {
                return $candidates[0];
            }
        }
        
        return null;
    }
    
    /**
     * Calculate match confidence between AI selection and API restaurant
     *
     * @param array $ai_restaurant AI-selected restaurant
     * @param array $api_restaurant API restaurant data
     * @return float Confidence score (0-1)
     */
    private function calculate_match_confidence($ai_restaurant, $api_restaurant) {
        $scores = [];
        $weights = [
            'name' => 0.50,
            'cuisine' => 0.20,
            'address' => 0.20,
            'price' => 0.10
        ];
        
        // Name similarity
        $ai_name = $ai_restaurant['name'] ?? '';
        $api_name = $api_restaurant['name'] ?? '';
        $scores['name'] = $this->calculate_name_similarity($ai_name, $api_name);
        
        // Cuisine match
        $ai_cuisine = strtolower($ai_restaurant['cuisine'] ?? '');
        $api_cuisine = strtolower($api_restaurant['cuisine_type'] ?? '');
        $scores['cuisine'] = $this->calculate_cuisine_similarity($ai_cuisine, $api_cuisine);
        
        // Address similarity
        $ai_address = $ai_restaurant['address'] ?? '';
        $api_address = $api_restaurant['address'] ?? '';
        $scores['address'] = $this->calculate_address_similarity($ai_address, $api_address);
        
        // Price level match
        $ai_price = $ai_restaurant['price_level'] ?? 0;
        $api_price = $api_restaurant['price_level'] ?? 0;
        $scores['price'] = $this->calculate_price_similarity($ai_price, $api_price);
        
        // Calculate weighted average
        $total_score = 0;
        $total_weight = 0;
        
        foreach ($scores as $factor => $score) {
            if ($score !== null && isset($weights[$factor])) {
                $total_score += $score * $weights[$factor];
                $total_weight += $weights[$factor];
            }
        }
        
        return $total_weight > 0 ? $total_score / $total_weight : 0;
    }
    
    /**
     * Calculate name similarity score
     *
     * @param string $name1 First name
     * @param string $name2 Second name
     * @return float Similarity score (0-1)
     */
    private function calculate_name_similarity($name1, $name2) {
        if (empty($name1) || empty($name2)) {
            return 0;
        }
        
        // Normalize names
        $norm1 = $this->normalize_name($name1);
        $norm2 = $this->normalize_name($name2);
        
        // Exact match after normalization
        if ($norm1 === $norm2) {
            return 1.0;
        }
        
        // Check if one contains the other
        if (strpos($norm1, $norm2) !== false || strpos($norm2, $norm1) !== false) {
            return 0.85;
        }
        
        // Levenshtein distance for fuzzy matching
        $max_len = max(strlen($norm1), strlen($norm2));
        $distance = levenshtein($norm1, $norm2);
        $similarity = 1 - ($distance / $max_len);
        
        // Additional boost for matching first words (common for restaurant names)
        $words1 = explode(' ', $norm1);
        $words2 = explode(' ', $norm2);
        
        if (!empty($words1[0]) && !empty($words2[0]) && $words1[0] === $words2[0]) {
            $similarity = min(1.0, $similarity + 0.2);
        }
        
        return max(0, $similarity);
    }
    
    /**
     * Calculate cuisine similarity
     *
     * @param string $cuisine1 First cuisine type
     * @param string $cuisine2 Second cuisine type
     * @return float Similarity score (0-1)
     */
    private function calculate_cuisine_similarity($cuisine1, $cuisine2) {
        if (empty($cuisine1) || empty($cuisine2)) {
            return 0.5; // Neutral score if missing
        }
        
        // Exact match
        if ($cuisine1 === $cuisine2) {
            return 1.0;
        }
        
        // Check for related cuisines
        $cuisine_relations = [
            'italian' => ['pizza', 'pasta'],
            'japanese' => ['sushi', 'ramen'],
            'mexican' => ['tex-mex', 'tacos'],
            'american' => ['burgers', 'bbq', 'barbecue'],
            'chinese' => ['cantonese', 'szechuan', 'dim sum']
        ];
        
        foreach ($cuisine_relations as $primary => $related) {
            if (($cuisine1 === $primary && in_array($cuisine2, $related)) ||
                ($cuisine2 === $primary && in_array($cuisine1, $related))) {
                return 0.8;
            }
        }
        
        return 0;
    }
    
    /**
     * Calculate address similarity
     *
     * @param string $address1 First address
     * @param string $address2 Second address
     * @return float Similarity score (0-1)
     */
    private function calculate_address_similarity($address1, $address2) {
        if (empty($address1) || empty($address2)) {
            return 0.5; // Neutral score if missing
        }
        
        // Extract street numbers
        preg_match('/^\d+/', $address1, $num1);
        preg_match('/^\d+/', $address2, $num2);
        
        if (!empty($num1[0]) && !empty($num2[0]) && $num1[0] === $num2[0]) {
            // Same street number is a strong indicator
            return 0.9;
        }
        
        // Check for common street names
        $streets1 = $this->extract_street_name($address1);
        $streets2 = $this->extract_street_name($address2);
        
        if ($streets1 && $streets2 && stripos($streets1, $streets2) !== false) {
            return 0.7;
        }
        
        return 0;
    }
    
    /**
     * Calculate price level similarity
     *
     * @param mixed $price1 First price level
     * @param mixed $price2 Second price level
     * @return float Similarity score (0-1)
     */
    private function calculate_price_similarity($price1, $price2) {
        // Convert to numeric if needed
        $level1 = $this->normalize_price_level($price1);
        $level2 = $this->normalize_price_level($price2);
        
        if ($level1 === 0 || $level2 === 0) {
            return 0.5; // Neutral if unknown
        }
        
        $diff = abs($level1 - $level2);
        
        switch ($diff) {
            case 0:
                return 1.0;
            case 1:
                return 0.7;
            case 2:
                return 0.3;
            default:
                return 0;
        }
    }
    
    /**
     * Normalize restaurant name for comparison
     *
     * @param string $name Restaurant name
     * @return string Normalized name
     */
    private function normalize_name($name) {
        $name = strtolower(trim($name));
        
        // Remove common suffixes
        $suffixes = [
            ' restaurant', ' cafe', ' bar', ' grill', ' kitchen',
            ' bistro', ' eatery', ' dining', ' steakhouse', ' pizzeria'
        ];
        
        foreach ($suffixes as $suffix) {
            if (substr($name, -strlen($suffix)) === $suffix) {
                $name = substr($name, 0, -strlen($suffix));
            }
        }
        
        // Remove special characters
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);
        
        // Remove extra spaces
        $name = preg_replace('/\s+/', ' ', $name);
        
        return trim($name);
    }
    
    /**
     * Extract street name from address
     *
     * @param string $address Full address
     * @return string|null Street name
     */
    private function extract_street_name($address) {
        // Remove street number
        $address = preg_replace('/^\d+\s*/', '', $address);
        
        // Extract street name (before common suffixes)
        if (preg_match('/^([^,]+?)(?:\s+(?:st|street|ave|avenue|blvd|boulevard|rd|road|ln|lane|dr|drive|way|ct|court))/i', $address, $matches)) {
            return strtolower(trim($matches[1]));
        }
        
        return null;
    }
    
    /**
     * Normalize price level to numeric scale
     *
     * @param mixed $price Price level (string or int)
     * @return int Price level (1-4)
     */
    private function normalize_price_level($price) {
        if (is_numeric($price)) {
            return max(1, min(4, intval($price)));
        }
        
        $price = strtolower(trim($price));
        
        // Handle dollar sign notation
        if (preg_match('/^\$+$/', $price)) {
            return strlen($price);
        }
        
        // Handle text notation
        $price_map = [
            'budget' => 1,
            'moderate' => 2,
            'upscale' => 3,
            'luxury' => 4
        ];
        
        return $price_map[$price] ?? 0;
    }
    
    /**
     * Index restaurants for efficient lookup
     *
     * @param array $restaurants Restaurant array
     * @return array Indexed data
     */
    private function index_restaurants($restaurants) {
        $index = [
            'by_zpid' => [],
            'by_normalized_name' => []
        ];
        
        foreach ($restaurants as $restaurant) {
            // Index by ZPID
            if (!empty($restaurant['zpid'])) {
                $index['by_zpid'][$restaurant['zpid']] = $restaurant;
            }
            
            // Index by normalized name
            if (!empty($restaurant['name'])) {
                $normalized = $this->normalize_name($restaurant['name']);
                $index['by_normalized_name'][$normalized] = $restaurant;
            }
        }
        
        return $index;
    }
    
    /**
     * Merge AI scoring with real restaurant data
     *
     * @param array $ai_data AI-generated data (scores, summary)
     * @param array $api_data Real restaurant data
     * @return array Merged data
     */
    private function merge_restaurant_data($ai_data, $api_data) {
        // Start with real API data as base
        $merged = $api_data;
        
        // Add AI-generated scoring and content
        $ai_fields = [
            'rank', 
            'pillar_scores', 
            'summary', 
            'recommended_vibes',
            'critic_notes',
            'highlights'
        ];
        
        foreach ($ai_fields as $field) {
            if (isset($ai_data[$field])) {
                $merged[$field] = $ai_data[$field];
            }
        }
        
        // Ensure we have the ZPID
        if (empty($merged['zpid']) && !empty($api_data['zpid'])) {
            $merged['zpid'] = $api_data['zpid'];
        }
        
        // Mark as verified
        $merged['api_verified'] = true;
        $merged['verification_timestamp'] = current_time('mysql');
        
        return $merged;
    }
    
    /**
     * Validate a single restaurant selection
     *
     * @param array $restaurant Restaurant data to validate
     * @param array $api_restaurants Available API restaurants
     * @return array|null Validated restaurant or null
     */
    public function validate_single($restaurant, $api_restaurants) {
        $result = $this->validate_selections([$restaurant], $api_restaurants);
        return !empty($result) ? $result[0] : null;
    }
}