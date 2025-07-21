<?php
/**
 * Confidence Engine - Evaluates data quality and identifies gaps
 * 
 * @package ZipPicks_Master_Critic
 * @subpackage Hybrid
 */

namespace ZipPicks\MasterCritic\Hybrid;

class ConfidenceEngine {
    
    /**
     * Confidence scoring weights
     */
    private const WEIGHT_EXISTENCE = 40;      // Business exists and is verified
    private const WEIGHT_RICHNESS = 30;       // Data completeness
    private const WEIGHT_SOCIAL = 20;         // Social proof
    private const WEIGHT_FRESHNESS = 10;      // Data recency
    
    /**
     * Data source trust levels
     */
    private const SOURCE_TRUST = [
        'gov' => 0.95,          // Government data most trusted
        'osm' => 0.85,          // OpenStreetMap community verified
        'wikidata' => 0.80,     // Wikidata curated
        'yelp' => 0.75,         // Commercial but comprehensive
        'google' => 0.75,       // Commercial but comprehensive
        'social' => 0.60,       // Social signals less reliable
        'community' => 0.70     // Community data moderate trust
    ];
    
    /**
     * Required fields by business type
     */
    private const REQUIRED_FIELDS = [
        'restaurant' => [
            'name', 'address', 'hours', 'phone', 'cuisine_type',
            'price_range', 'website', 'health_score'
        ],
        'salon' => [
            'name', 'address', 'hours', 'phone', 'services',
            'price_range', 'website', 'license_status'
        ],
        'hotel' => [
            'name', 'address', 'phone', 'website', 'star_rating',
            'amenities', 'room_types', 'price_range'
        ]
    ];
    
    /**
     * Evaluate data quality and confidence
     */
    public function evaluate( array $data, array $requirements ): array {
        $confidence_score = 0;
        $factors = [];
        $gaps = [];
        $source_agreement = [];
        
        // 1. Existence Verification (40 points max)
        $existence_result = $this->evaluate_existence($data);
        $confidence_score += $existence_result['score'];
        $factors = array_merge($factors, $existence_result['factors']);
        
        // 2. Data Richness (30 points max)
        $richness_result = $this->evaluate_richness($data, $requirements);
        $confidence_score += $richness_result['score'];
        $factors = array_merge($factors, $richness_result['factors']);
        $gaps = array_merge($gaps, $richness_result['gaps']);
        
        // 3. Social Proof (20 points max)
        $social_result = $this->evaluate_social_proof($data);
        $confidence_score += $social_result['score'];
        $factors = array_merge($factors, $social_result['factors']);
        
        // 4. Data Freshness (10 points max)
        $freshness_result = $this->evaluate_freshness($data);
        $confidence_score += $freshness_result['score'];
        $factors = array_merge($factors, $freshness_result['factors']);
        
        // 5. Source Agreement Analysis
        $source_agreement = $this->analyze_source_agreement($data);
        
        // Adjust confidence based on disagreement
        if ($source_agreement['conflict_score'] > 0.3) {
            $confidence_score *= (1 - $source_agreement['conflict_score'] * 0.2);
            $factors[] = 'Source conflict detected';
            $gaps[] = [
                'type' => 'verification',
                'reason' => 'Conflicting data between sources',
                'priority' => 'high'
            ];
        }
        
        return [
            'score' => round($confidence_score),
            'factors' => $factors,
            'gaps' => $gaps,
            'source_agreement' => $source_agreement,
            'needs_enhancement' => $confidence_score < 70,
            'breakdown' => [
                'existence' => $existence_result['score'],
                'richness' => $richness_result['score'],
                'social' => $social_result['score'],
                'freshness' => $freshness_result['score']
            ]
        ];
    }
    
    /**
     * Evaluate business existence verification
     */
    private function evaluate_existence( array $data ): array {
        $score = 0;
        $factors = [];
        
        // OSM verification (15 points)
        if (!empty($data['osm']) && !empty($data['osm']['id'])) {
            $score += 15;
            $factors[] = 'OpenStreetMap verified';
            
            // Bonus for detailed OSM data
            if (!empty($data['osm']['tags']) && count($data['osm']['tags']) > 5) {
                $score += 5;
                $factors[] = 'Detailed OSM data';
            }
        }
        
        // Government verification (20 points)
        if (!empty($data['gov'])) {
            if (!empty($data['gov']['business_license'])) {
                $score += 10;
                $factors[] = 'Valid business license';
            }
            
            if (!empty($data['gov']['health_permit'])) {
                $score += 10;
                $factors[] = 'Valid health permit';
                
                // Recent inspection bonus
                if ($this->is_recent_date($data['gov']['last_inspection'], 180)) {
                    $score += 5;
                    $factors[] = 'Recent health inspection';
                }
            }
        }
        
        // Wikidata presence (5 points)
        if (!empty($data['wikidata']) && !empty($data['wikidata']['id'])) {
            $score += 5;
            $factors[] = 'Wikidata entry exists';
        }
        
        return [
            'score' => min($score, self::WEIGHT_EXISTENCE),
            'factors' => $factors
        ];
    }
    
    /**
     * Evaluate data richness and completeness
     */
    private function evaluate_richness( array $data, array $requirements ): array {
        $score = 0;
        $factors = [];
        $gaps = [];
        
        // Determine business type
        $business_type = $this->detect_business_type($data);
        $required_fields = self::REQUIRED_FIELDS[$business_type] ?? self::REQUIRED_FIELDS['restaurant'];
        
        // Check field completeness
        $present_fields = 0;
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if ($this->has_field($data, $field)) {
                $present_fields++;
            } else {
                $missing_fields[] = $field;
            }
        }
        
        // Calculate completeness score
        $completeness_ratio = $present_fields / count($required_fields);
        $score = round($completeness_ratio * self::WEIGHT_RICHNESS);
        
        if ($completeness_ratio >= 0.9) {
            $factors[] = 'Comprehensive data';
        } elseif ($completeness_ratio >= 0.7) {
            $factors[] = 'Good data coverage';
        } else {
            $factors[] = 'Limited data available';
        }
        
        // Identify specific gaps
        foreach ($missing_fields as $field) {
            $gaps[] = [
                'type' => 'missing_field',
                'field' => $field,
                'priority' => $this->get_field_priority($field)
            ];
        }
        
        // Check for enhanced data
        if ($this->has_field($data, 'photos')) {
            $score += 2;
            $factors[] = 'Has photos';
        }
        
        if ($this->has_field($data, 'menu')) {
            $score += 2;
            $factors[] = 'Has menu data';
        }
        
        if ($this->has_field($data, 'reviews')) {
            $score += 2;
            $factors[] = 'Has reviews';
        }
        
        return [
            'score' => min($score, self::WEIGHT_RICHNESS),
            'factors' => $factors,
            'gaps' => $gaps
        ];
    }
    
    /**
     * Evaluate social proof and engagement
     */
    private function evaluate_social_proof( array $data ): array {
        $score = 0;
        $factors = [];
        
        // Social media mentions
        if (!empty($data['social'])) {
            $mention_count = $data['social']['mention_count'] ?? 0;
            
            if ($mention_count > 100) {
                $score += 10;
                $factors[] = 'High social visibility';
            } elseif ($mention_count > 20) {
                $score += 5;
                $factors[] = 'Moderate social presence';
            }
            
            // Trending status
            if (!empty($data['social']['trending'])) {
                $score += 5;
                $factors[] = 'Currently trending';
            }
            
            // Engagement quality
            if (!empty($data['social']['engagement_rate']) && $data['social']['engagement_rate'] > 0.05) {
                $score += 5;
                $factors[] = 'High engagement rate';
            }
        }
        
        // Review volume (from any source)
        $total_reviews = 0;
        if (!empty($data['yelp']['review_count'])) {
            $total_reviews += $data['yelp']['review_count'];
        }
        if (!empty($data['google']['review_count'])) {
            $total_reviews += $data['google']['review_count'];
        }
        
        if ($total_reviews > 500) {
            $score += 5;
            $factors[] = 'High review volume';
        } elseif ($total_reviews > 100) {
            $score += 3;
            $factors[] = 'Good review volume';
        }
        
        return [
            'score' => min($score, self::WEIGHT_SOCIAL),
            'factors' => $factors
        ];
    }
    
    /**
     * Evaluate data freshness
     */
    private function evaluate_freshness( array $data ): array {
        $score = 0;
        $factors = [];
        $freshness_dates = [];
        
        // Collect all data timestamps
        if (!empty($data['osm']['timestamp'])) {
            $freshness_dates['osm'] = $data['osm']['timestamp'];
        }
        
        if (!empty($data['gov']['last_updated'])) {
            $freshness_dates['gov'] = $data['gov']['last_updated'];
        }
        
        if (!empty($data['social']['last_post'])) {
            $freshness_dates['social'] = $data['social']['last_post'];
        }
        
        // Calculate average freshness
        if (!empty($freshness_dates)) {
            $most_recent = max($freshness_dates);
            $days_old = (time() - strtotime($most_recent)) / 86400;
            
            if ($days_old < 7) {
                $score = self::WEIGHT_FRESHNESS;
                $factors[] = 'Very fresh data';
            } elseif ($days_old < 30) {
                $score = self::WEIGHT_FRESHNESS * 0.7;
                $factors[] = 'Recent data';
            } elseif ($days_old < 90) {
                $score = self::WEIGHT_FRESHNESS * 0.4;
                $factors[] = 'Moderately fresh data';
            } else {
                $factors[] = 'Stale data';
            }
        }
        
        return [
            'score' => round($score),
            'factors' => $factors
        ];
    }
    
    /**
     * Analyze agreement between different data sources
     */
    private function analyze_source_agreement( array $data ): array {
        $conflicts = [];
        $agreements = [];
        $fields_to_check = ['name', 'address', 'hours', 'phone'];
        
        foreach ($fields_to_check as $field) {
            $values = [];
            
            // Collect values from different sources
            foreach (['osm', 'gov', 'yelp', 'google', 'wikidata'] as $source) {
                if (!empty($data[$source][$field])) {
                    $values[$source] = $this->normalize_value($data[$source][$field]);
                }
            }
            
            if (count($values) > 1) {
                $unique_values = array_unique(array_values($values));
                
                if (count($unique_values) === 1) {
                    $agreements[] = [
                        'field' => $field,
                        'sources' => array_keys($values)
                    ];
                } else {
                    $conflicts[] = [
                        'field' => $field,
                        'values' => $values,
                        'severity' => $this->calculate_conflict_severity($field, $unique_values)
                    ];
                }
            }
        }
        
        // Calculate overall conflict score
        $total_severity = array_sum(array_column($conflicts, 'severity'));
        $max_severity = count($fields_to_check) * 1.0;
        $conflict_score = $total_severity / $max_severity;
        
        return [
            'conflicts' => $conflicts,
            'agreements' => $agreements,
            'conflict_score' => $conflict_score,
            'reliability' => 1 - $conflict_score
        ];
    }
    
    /**
     * Detect business type from available data
     */
    private function detect_business_type( array $data ): string {
        // Check various sources for type hints
        if (!empty($data['osm']['tags']['amenity'])) {
            $amenity = $data['osm']['tags']['amenity'];
            if ($amenity === 'restaurant' || $amenity === 'cafe' || $amenity === 'bar') {
                return 'restaurant';
            }
        }
        
        if (!empty($data['gov']['business_type'])) {
            $gov_type = strtolower($data['gov']['business_type']);
            if (strpos($gov_type, 'salon') !== false || strpos($gov_type, 'beauty') !== false) {
                return 'salon';
            }
            if (strpos($gov_type, 'hotel') !== false || strpos($gov_type, 'lodging') !== false) {
                return 'hotel';
            }
        }
        
        // Default to restaurant
        return 'restaurant';
    }
    
    /**
     * Check if a field exists in the aggregated data
     */
    private function has_field( array $data, string $field ): bool {
        // Check all data sources
        foreach ($data as $source => $source_data) {
            if (is_array($source_data) && !empty($source_data[$field])) {
                return true;
            }
        }
        
        // Special field mappings
        $field_mappings = [
            'hours' => ['opening_hours', 'business_hours', 'hours_of_operation'],
            'website' => ['url', 'website_url', 'homepage'],
            'cuisine_type' => ['cuisine', 'food_type', 'category']
        ];
        
        if (isset($field_mappings[$field])) {
            foreach ($field_mappings[$field] as $alt_field) {
                if ($this->has_field($data, $alt_field)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get priority level for missing fields
     */
    private function get_field_priority( string $field ): string {
        $high_priority = ['name', 'address', 'hours', 'phone'];
        $medium_priority = ['website', 'price_range', 'cuisine_type'];
        
        if (in_array($field, $high_priority)) {
            return 'high';
        } elseif (in_array($field, $medium_priority)) {
            return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * Check if date is recent
     */
    private function is_recent_date( $date, int $days_threshold ): bool {
        if (empty($date)) {
            return false;
        }
        
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        $days_old = (time() - $timestamp) / 86400;
        
        return $days_old <= $days_threshold;
    }
    
    /**
     * Normalize values for comparison
     */
    private function normalize_value( $value ): string {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        
        return strtolower(trim((string)$value));
    }
    
    /**
     * Calculate severity of data conflicts
     */
    private function calculate_conflict_severity( string $field, array $values ): float {
        // Critical fields have higher severity
        $field_weights = [
            'name' => 0.8,
            'address' => 1.0,
            'hours' => 0.6,
            'phone' => 0.5
        ];
        
        $weight = $field_weights[$field] ?? 0.3;
        
        // More unique values = higher severity
        $value_penalty = min(count($values) * 0.2, 1.0);
        
        return $weight * $value_penalty;
    }
}