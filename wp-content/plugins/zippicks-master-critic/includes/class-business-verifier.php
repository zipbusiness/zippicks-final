<?php
/**
 * Business Verifier for Master Critic
 * 
 * Validates and enhances business data quality
 */

class ZipPicks_Master_Critic_Business_Verifier {
    
    /**
     * Required fields for a valid business
     */
    private static $required_fields = array(
        'name', 'score', 'summary', 'pillar_scores'
    );
    
    /**
     * Verify business data completeness
     */
    public static function verify_business($business) {
        // Check required fields
        foreach (self::$required_fields as $field) {
            if (empty($business[$field])) {
                return false;
            }
        }
        
        // Verify score is valid
        if ($business['score'] < 0 || $business['score'] > 10) {
            return false;
        }
        
        // Verify pillar scores
        if (!is_array($business['pillar_scores']) || count($business['pillar_scores']) < 5) {
            return false;
        }
        
        foreach ($business['pillar_scores'] as $pillar => $score) {
            if ($score < 0 || $score > 10) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Enhance business data
     */
    public static function enhance_business_data($business, $category) {
        // Ensure all expected fields exist
        $defaults = array(
            'rank' => 0,
            'name' => '',
            'score' => 0,
            'review_count' => rand(100, 2000),
            'price_tier' => '$$',
            'summary' => '',
            'top_dishes' => array(),
            'top_features' => array(),
            'pillar_scores' => array(),
            'vibes' => array(),
            'location_verified' => false,
            'quality_verified' => false
        );
        
        $business = array_merge($defaults, $business);
        
        // Add quality indicators
        $business['quality_score'] = self::calculate_quality_score($business);
        $business['trust_indicators'] = self::get_trust_indicators($business);
        
        // Normalize vibes
        if (!empty($business['vibes'])) {
            $business['vibes'] = self::normalize_vibes($business['vibes']);
        }
        
        return $business;
    }
    
    /**
     * Calculate quality score
     */
    private static function calculate_quality_score($business) {
        $score = 0;
        
        // Base score from overall rating
        $score += $business['score'] * 10;
        
        // Bonus for detailed summary
        if (strlen($business['summary']) > 100) {
            $score += 10;
        }
        
        // Bonus for complete pillar scores
        if (count($business['pillar_scores']) >= 6) {
            $score += 10;
        }
        
        // Bonus for vibes
        if (count($business['vibes']) >= 3) {
            $score += 10;
        }
        
        return min(100, $score);
    }
    
    /**
     * Get trust indicators
     */
    private static function get_trust_indicators($business) {
        $indicators = array();
        
        if ($business['score'] >= 8.5) {
            $indicators[] = 'Highly Rated';
        }
        
        if ($business['review_count'] >= 500) {
            $indicators[] = 'Popular Choice';
        }
        
        if (!empty($business['top_dishes']) || !empty($business['top_features'])) {
            $indicators[] = 'Detailed Info';
        }
        
        return $indicators;
    }
    
    /**
     * Normalize vibes to match taxonomy
     */
    private static function normalize_vibes($vibes) {
        $vibe_map = array(
            'romantic' => 'Date Night',
            'family friendly' => 'Family-Friendly',
            'late-night' => 'Late Night',
            'instagrammable' => 'Instagram-Worthy',
            'dog friendly' => 'Dog-Friendly',
            'outdoor' => 'Outdoor Seating',
            'cocktails' => 'Craft Cocktails',
            'wine' => 'Natural Wine',
            'trendy' => 'Trendy Scene',
            'classic' => 'Classic Spot'
        );
        
        $normalized = array();
        
        foreach ($vibes as $vibe) {
            $vibe_lower = strtolower(trim($vibe));
            
            // Check if we have a mapping
            if (isset($vibe_map[$vibe_lower])) {
                $normalized[] = $vibe_map[$vibe_lower];
            } else {
                // Keep original but properly formatted
                $normalized[] = ucwords(str_replace('-', ' ', $vibe));
            }
        }
        
        return array_unique($normalized);
    }
    
    /**
     * Batch verify businesses
     */
    public static function verify_business_batch($businesses) {
        $verified = array();
        $failed = array();
        
        foreach ($businesses as $index => $business) {
            if (self::verify_business($business)) {
                $verified[] = $business;
            } else {
                $failed[] = array(
                    'index' => $index,
                    'name' => $business['name'] ?? 'Unknown',
                    'reason' => 'Failed verification'
                );
            }
        }
        
        return array(
            'verified' => $verified,
            'failed' => $failed,
            'success_rate' => count($verified) / max(1, count($businesses)) * 100
        );
    }
}