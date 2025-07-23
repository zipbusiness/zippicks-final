<?php
/**
 * Intent Classifier
 * 
 * Classifies search queries as vibe, utility, or hybrid
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Intent_Classifier {
    
    /**
     * Intent types
     */
    const INTENT_VIBE = 'vibe_search';
    const INTENT_UTILITY = 'utility_search';
    const INTENT_HYBRID = 'hybrid_search';
    
    /**
     * Vibe keywords and patterns
     */
    private static $vibe_patterns = [
        // Atmosphere vibes
        'romantic' => ['romantic', 'date night', 'anniversary', 'intimate', 'couples'],
        'cozy' => ['cozy', 'warm', 'comfortable', 'intimate', 'charming'],
        'trendy' => ['trendy', 'hip', 'cool', 'instagram', 'instagrammable', 'aesthetic'],
        'casual' => ['casual', 'relaxed', 'chill', 'laid back', 'easy going'],
        'upscale' => ['upscale', 'fancy', 'elegant', 'sophisticated', 'classy', 'fine dining'],
        'lively' => ['lively', 'fun', 'energetic', 'vibrant', 'buzzing', 'happening'],
        'quiet' => ['quiet', 'peaceful', 'calm', 'serene', 'tranquil'],
        
        // Social vibes
        'family' => ['family', 'kid friendly', 'kids', 'children', 'family friendly'],
        'groups' => ['group', 'party', 'birthday', 'celebration', 'gathering'],
        'business' => ['business', 'meeting', 'professional', 'work lunch', 'client'],
        'solo' => ['solo', 'alone', 'myself', 'single diner'],
        
        // Experience vibes
        'authentic' => ['authentic', 'traditional', 'real', 'genuine', 'local'],
        'unique' => ['unique', 'different', 'special', 'unusual', 'quirky'],
        'healthy' => ['healthy', 'fresh', 'organic', 'clean', 'nutritious'],
        'comfort' => ['comfort food', 'hearty', 'homestyle', 'soul food'],
    ];
    
    /**
     * Utility keywords
     */
    private static $utility_keywords = [
        // Distance/location
        'near me', 'nearby', 'close', 'around here', 'in my area',
        'walking distance', 'deliver', 'delivery', 'takeout', 'pickup',
        
        // Time
        'open now', 'open late', '24 hour', '24/7', 'breakfast', 'lunch', 'dinner',
        'happy hour', 'brunch',
        
        // Price
        'cheap', 'affordable', 'budget', 'expensive', 'price', '$', '$$', '$$$',
        
        // Speed/convenience
        'fast', 'quick', 'express', 'drive thru', 'drive through',
        
        // Features
        'parking', 'wifi', 'outdoor', 'patio', 'reservation',
        'wheelchair', 'accessible', 'gluten free', 'vegan', 'vegetarian',
    ];
    
    /**
     * Cuisine types (can be either vibe or utility)
     */
    private static $cuisine_types = [
        'italian', 'mexican', 'chinese', 'japanese', 'sushi', 'thai', 'indian',
        'american', 'french', 'korean', 'vietnamese', 'mediterranean', 'greek',
        'spanish', 'pizza', 'burger', 'seafood', 'steak', 'bbq', 'barbecue',
        'cafe', 'coffee', 'bakery', 'dessert', 'ice cream', 'bar', 'pub',
    ];
    
    /**
     * Classify search intent
     * 
     * @param string $query Search query
     * @param array $context Additional context (location, time, user history)
     * @return array Classification result
     */
    public static function classify($query, $context = []) {
        $query_lower = strtolower($query);
        $tokens = self::tokenize($query_lower);
        
        // Score each intent type
        $vibe_score = self::calculate_vibe_score($query_lower, $tokens);
        $utility_score = self::calculate_utility_score($query_lower, $tokens);
        
        // Detect specific attributes
        $detected_vibes = self::detect_vibes($query_lower, $tokens);
        $detected_features = self::detect_utility_features($query_lower, $tokens);
        $detected_cuisine = self::detect_cuisine($query_lower, $tokens);
        
        // Determine primary intent
        $intent = self::determine_intent($vibe_score, $utility_score);
        $confidence = self::calculate_confidence($vibe_score, $utility_score);
        
        // Extract modifiers
        $modifiers = self::extract_modifiers($query_lower, $tokens);
        
        return [
            'intent' => $intent,
            'confidence' => $confidence,
            'scores' => [
                'vibe' => $vibe_score,
                'utility' => $utility_score,
            ],
            'detected_vibes' => $detected_vibes,
            'detected_features' => $detected_features,
            'cuisine' => $detected_cuisine,
            'modifiers' => $modifiers,
            'normalized_query' => self::normalize_query($query_lower, $detected_vibes, $detected_features),
        ];
    }
    
    /**
     * Tokenize query
     * 
     * @param string $query
     * @return array
     */
    private static function tokenize($query) {
        // Simple tokenization - can be enhanced with NLP
        $tokens = preg_split('/\s+/', $query);
        return array_filter($tokens);
    }
    
    /**
     * Calculate vibe score
     * 
     * @param string $query
     * @param array $tokens
     * @return float
     */
    private static function calculate_vibe_score($query, $tokens) {
        $score = 0;
        $matches = 0;
        
        foreach (self::$vibe_patterns as $vibe => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($query, $keyword) !== false) {
                    $score += 1.0;
                    $matches++;
                }
            }
        }
        
        // Check for vibe-indicating phrases
        $vibe_phrases = [
            'looking for somewhere',
            'place for',
            'spot for',
            'restaurant for',
            'where can i',
            'recommend',
            'suggestion',
            'best place',
            'good place',
        ];
        
        foreach ($vibe_phrases as $phrase) {
            if (stripos($query, $phrase) !== false) {
                $score += 0.5;
            }
        }
        
        // Normalize score
        return $matches > 0 ? min($score / max($matches, 1), 1.0) : 0;
    }
    
    /**
     * Calculate utility score
     * 
     * @param string $query
     * @param array $tokens
     * @return float
     */
    private static function calculate_utility_score($query, $tokens) {
        $score = 0;
        $matches = 0;
        
        foreach (self::$utility_keywords as $keyword) {
            if (stripos($query, $keyword) !== false) {
                $score += 1.0;
                $matches++;
            }
        }
        
        // Check for utility patterns
        if (preg_match('/\b(in|at|on)\s+\w+\s*(street|ave|blvd|road|st|avenue|boulevard)?\b/i', $query)) {
            $score += 0.5; // Specific location mentioned
        }
        
        if (preg_match('/\b\d{5}\b/', $query)) {
            $score += 0.5; // ZIP code mentioned
        }
        
        if (preg_match('/\b(asap|now|immediately|urgent)\b/i', $query)) {
            $score += 0.5; // Urgency mentioned
        }
        
        // Normalize score
        return $matches > 0 ? min($score / max($matches, 1), 1.0) : 0;
    }
    
    /**
     * Detect vibes in query
     * 
     * @param string $query
     * @param array $tokens
     * @return array
     */
    private static function detect_vibes($query, $tokens) {
        $detected = [];
        
        foreach (self::$vibe_patterns as $vibe => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($query, $keyword) !== false) {
                    $detected[] = $vibe;
                    break; // Only add each vibe once
                }
            }
        }
        
        return array_unique($detected);
    }
    
    /**
     * Detect utility features
     * 
     * @param string $query
     * @param array $tokens
     * @return array
     */
    private static function detect_utility_features($query, $tokens) {
        $features = [];
        
        // Distance/location
        if (preg_match('/\b(near|nearby|close|around)\b/i', $query)) {
            $features[] = 'distance';
        }
        
        // Time
        if (preg_match('/\b(open|now|late|24)\b/i', $query)) {
            $features[] = 'hours';
        }
        
        // Price
        if (preg_match('/\b(cheap|affordable|budget|expensive|\$+)\b/i', $query)) {
            $features[] = 'price';
        }
        
        // Service type
        if (preg_match('/\b(delivery|takeout|pickup|drive)\b/i', $query)) {
            $features[] = 'service';
        }
        
        // Amenities
        if (preg_match('/\b(parking|wifi|outdoor|patio|reservation)\b/i', $query)) {
            $features[] = 'amenities';
        }
        
        // Dietary
        if (preg_match('/\b(gluten|vegan|vegetarian|halal|kosher)\b/i', $query)) {
            $features[] = 'dietary';
        }
        
        return $features;
    }
    
    /**
     * Detect cuisine type
     * 
     * @param string $query
     * @param array $tokens
     * @return string|null
     */
    private static function detect_cuisine($query, $tokens) {
        foreach (self::$cuisine_types as $cuisine) {
            if (stripos($query, $cuisine) !== false) {
                return $cuisine;
            }
        }
        
        // Check for food items that imply cuisine
        $food_to_cuisine = [
            'taco' => 'mexican',
            'burrito' => 'mexican',
            'pasta' => 'italian',
            'ramen' => 'japanese',
            'pho' => 'vietnamese',
            'curry' => 'indian',
            'dim sum' => 'chinese',
            'tapas' => 'spanish',
        ];
        
        foreach ($food_to_cuisine as $food => $cuisine) {
            if (stripos($query, $food) !== false) {
                return $cuisine;
            }
        }
        
        return null;
    }
    
    /**
     * Determine primary intent
     * 
     * @param float $vibe_score
     * @param float $utility_score
     * @return string
     */
    private static function determine_intent($vibe_score, $utility_score) {
        $threshold = 0.3; // Minimum score to be considered
        
        if ($vibe_score >= $threshold && $utility_score >= $threshold) {
            return self::INTENT_HYBRID;
        } elseif ($vibe_score > $utility_score && $vibe_score >= $threshold) {
            return self::INTENT_VIBE;
        } elseif ($utility_score > $vibe_score && $utility_score >= $threshold) {
            return self::INTENT_UTILITY;
        } else {
            // Default to utility if no clear signal
            return self::INTENT_UTILITY;
        }
    }
    
    /**
     * Calculate confidence score
     * 
     * @param float $vibe_score
     * @param float $utility_score
     * @return float
     */
    private static function calculate_confidence($vibe_score, $utility_score) {
        $max_score = max($vibe_score, $utility_score);
        $diff = abs($vibe_score - $utility_score);
        
        // Higher confidence when there's a clear winner
        $confidence = $max_score * (1 + $diff);
        
        return min($confidence, 1.0);
    }
    
    /**
     * Extract modifiers from query
     * 
     * @param string $query
     * @param array $tokens
     * @return array
     */
    private static function extract_modifiers($query, $tokens) {
        $modifiers = [];
        
        // Time modifiers
        if (preg_match('/\b(breakfast|lunch|dinner|brunch)\b/i', $query, $matches)) {
            $modifiers['meal'] = strtolower($matches[1]);
        }
        
        // Group size
        if (preg_match('/\b(for\s+)?(\d+)\s+(people|person|guests?)\b/i', $query, $matches)) {
            $modifiers['party_size'] = intval($matches[2]);
        }
        
        // Occasion
        $occasions = ['birthday', 'anniversary', 'celebration', 'date', 'meeting', 'party'];
        foreach ($occasions as $occasion) {
            if (stripos($query, $occasion) !== false) {
                $modifiers['occasion'] = $occasion;
                break;
            }
        }
        
        return $modifiers;
    }
    
    /**
     * Normalize query for consistent processing
     * 
     * @param string $query
     * @param array $vibes
     * @param array $features
     * @return string
     */
    private static function normalize_query($query, $vibes, $features) {
        // Remove common stop words
        $stop_words = ['the', 'a', 'an', 'for', 'to', 'in', 'at', 'on', 'with'];
        $tokens = self::tokenize($query);
        $filtered = array_diff($tokens, $stop_words);
        
        return implode(' ', $filtered);
    }
    
    /**
     * Get vibe expansion suggestions
     * 
     * @param array $vibes Detected vibes
     * @return array Expanded vibes
     */
    public static function expand_vibes($vibes) {
        $expansions = [
            'romantic' => ['intimate', 'cozy', 'upscale', 'quiet'],
            'date-night' => ['romantic', 'intimate', 'special-occasion', 'upscale'],
            'casual' => ['relaxed', 'comfortable', 'everyday', 'laid-back'],
            'trendy' => ['instagram-worthy', 'popular', 'buzzing', 'see-and-be-seen'],
            'cozy' => ['intimate', 'warm', 'comfortable', 'quiet'],
            'family' => ['kid-friendly', 'spacious', 'casual', 'accommodating'],
            'business' => ['professional', 'quiet', 'upscale', 'convenient'],
            'groups' => ['spacious', 'lively', 'shareable', 'accommodating'],
        ];
        
        $expanded = $vibes;
        
        foreach ($vibes as $vibe) {
            if (isset($expansions[$vibe])) {
                $expanded = array_merge($expanded, $expansions[$vibe]);
            }
        }
        
        return array_unique($expanded);
    }
}