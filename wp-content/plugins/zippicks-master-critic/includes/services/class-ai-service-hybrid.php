<?php
/**
 * AI Service Hybrid - Enhanced AI service that uses hybrid data
 * 
 * @package ZipPicks_Master_Critic
 * @subpackage Services
 */

namespace ZipPicks\MasterCritic\Services;

use ZipPicks\MasterCritic\Hybrid\HybridServiceProvider;

class AIServiceHybrid extends AIService {
    
    /**
     * Enhanced business data synthesis using hybrid sources
     */
    public function synthesize_business_data( array $context ): array {
        $prompt = $this->build_synthesis_prompt($context);
        
        try {
            // Use the appropriate AI provider
            $response = $this->make_ai_request($prompt, [
                'temperature' => 0.7,
                'max_tokens' => 2000
            ]);
            
            // Parse the response
            $synthesis = $this->parse_synthesis_response($response);
            
            // Enhance with confidence scoring
            $synthesis['confidence'] = $this->calculate_synthesis_confidence($synthesis, $context);
            $synthesis['model_used'] = $this->get_last_model_used();
            
            return $synthesis;
            
        } catch (\Exception $e) {
            // Log error and return minimal synthesis
            error_log('AI synthesis failed: ' . $e->getMessage());
            
            return $this->get_fallback_synthesis($context);
        }
    }
    
    /**
     * Generate Master Critic review based on hybrid data
     */
    public function generate_master_critic_review( array $business_data ): array {
        // Prepare context with all available data
        $context = [
            'business_name' => $business_data['business_name'] ?? '',
            'location' => $business_data['city'] ?? '',
            'category' => $this->detect_business_category($business_data),
            'factual_data' => $this->prepare_factual_context($business_data),
            'review_data' => $this->prepare_review_context($business_data),
            'scoring_requirements' => $this->get_scoring_pillars($business_data)
        ];
        
        $prompt = $this->build_master_critic_prompt($context);
        
        try {
            $response = $this->make_ai_request($prompt, [
                'temperature' => 0.8,
                'max_tokens' => 3000
            ]);
            
            $review = $this->parse_master_critic_response($response);
            
            // Validate and adjust scores based on real data
            $review['scores'] = $this->validate_scores($review['scores'], $business_data);
            
            return $review;
            
        } catch (\Exception $e) {
            error_log('Master Critic generation failed: ' . $e->getMessage());
            
            return $this->get_fallback_review($business_data);
        }
    }
    
    /**
     * Build synthesis prompt
     */
    private function build_synthesis_prompt( array $context ): string {
        $prompt = "You are the ZipPicks AI system tasked with synthesizing business information from multiple data sources.\n\n";
        
        $prompt .= "Business Query:\n";
        $prompt .= "- Intent: {$context['query_intent']}\n";
        $prompt .= "- Location: {$context['location']}\n\n";
        
        $prompt .= "Available Data Sources:\n";
        foreach ($context['factual_data'] as $source => $data) {
            if (!empty($data)) {
                $prompt .= "\n{$source} Data:\n";
                $prompt .= $this->format_source_data($source, $data);
            }
        }
        
        $prompt .= "\n\nSynthesis Requirements:\n";
        $prompt .= "1. Create a compelling, accurate description of this business\n";
        $prompt .= "2. Identify the key vibes and characteristics\n";
        $prompt .= "3. Generate a Master Critic scoring assessment\n";
        $prompt .= "4. Highlight what makes this place unique\n";
        $prompt .= "5. Flag any data conflicts or concerns\n\n";
        
        $prompt .= "Provide your synthesis in the following JSON format:\n";
        $prompt .= json_encode([
            'description' => 'Compelling 2-3 sentence description',
            'vibes' => ['vibe1', 'vibe2', 'vibe3'],
            'unique_features' => ['feature1', 'feature2'],
            'scoring' => [
                'overall' => 0.0,
                'confidence' => 'high|medium|low'
            ],
            'data_quality' => [
                'conflicts' => [],
                'missing_data' => [],
                'reliability' => 'high|medium|low'
            ],
            'insights' => 'Key insights about this business',
            'review' => 'A brief Master Critic style review'
        ], JSON_PRETTY_PRINT);
        
        return $prompt;
    }
    
    /**
     * Build Master Critic prompt
     */
    private function build_master_critic_prompt( array $context ): string {
        $category = $context['category'];
        $pillars = $context['scoring_requirements'];
        
        $prompt = "You are the ZipPicks Master Critic, an expert {$category} reviewer with refined taste and high standards.\n\n";
        
        $prompt .= "Business: {$context['business_name']} in {$context['location']}\n";
        $prompt .= "Category: {$category}\n\n";
        
        $prompt .= "Factual Information:\n";
        $prompt .= $this->format_factual_data($context['factual_data']);
        
        if (!empty($context['review_data'])) {
            $prompt .= "\n\nReview Analysis:\n";
            $prompt .= $this->format_review_data($context['review_data']);
        }
        
        $prompt .= "\n\nYour Task:\n";
        $prompt .= "1. Write a sophisticated, insightful review (150-200 words)\n";
        $prompt .= "2. Score each pillar from 0-10 with decimal precision\n";
        $prompt .= "3. Identify 3-5 vibes that capture the essence\n";
        $prompt .= "4. Provide an overall score and verdict\n\n";
        
        $prompt .= "Scoring Pillars for {$category}:\n";
        foreach ($pillars as $pillar => $description) {
            $prompt .= "- {$pillar}: {$description}\n";
        }
        
        $prompt .= "\nProvide your review in the following JSON format:\n";
        $prompt .= json_encode([
            'review_text' => 'Your sophisticated review here',
            'scores' => [
                'pillar1' => 0.0,
                'pillar2' => 0.0,
                'overall' => 0.0
            ],
            'vibes' => ['vibe1', 'vibe2', 'vibe3'],
            'verdict' => 'One-line verdict',
            'highlights' => ['highlight1', 'highlight2'],
            'criticisms' => ['criticism1', 'criticism2']
        ], JSON_PRETTY_PRINT);
        
        return $prompt;
    }
    
    /**
     * Format source data for prompt
     */
    private function format_source_data( string $source, array $data ): string {
        $formatted = "";
        
        switch ($source) {
            case 'osm':
                if (!empty($data['name'])) $formatted .= "- Name: {$data['name']}\n";
                if (!empty($data['opening_hours'])) $formatted .= "- Hours: {$data['opening_hours']}\n";
                if (!empty($data['cuisine'])) $formatted .= "- Cuisine: {$data['cuisine']}\n";
                if (!empty($data['website'])) $formatted .= "- Website: {$data['website']}\n";
                break;
                
            case 'gov':
                if (!empty($data['health_score'])) $formatted .= "- Health Score: {$data['health_score']}\n";
                if (!empty($data['business_license'])) $formatted .= "- License Status: {$data['business_license']}\n";
                if (!empty($data['last_inspection'])) $formatted .= "- Last Inspection: {$data['last_inspection']}\n";
                break;
                
            case 'yelp':
                if (!empty($data['rating'])) $formatted .= "- Yelp Rating: {$data['rating']} ({$data['review_count']} reviews)\n";
                if (!empty($data['price'])) $formatted .= "- Price Level: {$data['price']}\n";
                if (!empty($data['categories'])) {
                    $cats = array_column($data['categories'], 'title');
                    $formatted .= "- Categories: " . implode(', ', $cats) . "\n";
                }
                break;
                
            case 'google':
                if (!empty($data['rating'])) $formatted .= "- Google Rating: {$data['rating']} ({$data['review_count']} reviews)\n";
                if (!empty($data['price_level'])) $formatted .= "- Price Level: " . str_repeat('$', $data['price_level']) . "\n";
                if (!empty($data['business_status'])) $formatted .= "- Status: {$data['business_status']}\n";
                break;
                
            case 'social':
                if (!empty($data['mention_count'])) $formatted .= "- Social Mentions: {$data['mention_count']}\n";
                if (!empty($data['trending'])) $formatted .= "- Currently Trending: Yes\n";
                if (!empty($data['engagement_rate'])) $formatted .= "- Engagement Rate: {$data['engagement_rate']}%\n";
                break;
        }
        
        return $formatted;
    }
    
    /**
     * Parse synthesis response
     */
    private function parse_synthesis_response( string $response ): array {
        // Try to extract JSON from response
        $json_match = [];
        if (preg_match('/\{[\s\S]*\}/', $response, $json_match)) {
            $json_str = $json_match[0];
            $parsed = json_decode($json_str, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }
        
        // Fallback parsing
        return [
            'description' => $this->extract_between($response, 'Description:', "\n\n"),
            'vibes' => $this->extract_list($response, 'Vibes:'),
            'unique_features' => $this->extract_list($response, 'Unique Features:'),
            'scoring' => [
                'overall' => $this->extract_number($response, 'Overall Score:'),
                'confidence' => 'medium'
            ],
            'insights' => $this->extract_between($response, 'Insights:', "\n\n"),
            'review' => $this->extract_between($response, 'Review:', "\n\n")
        ];
    }
    
    /**
     * Parse Master Critic response
     */
    private function parse_master_critic_response( string $response ): array {
        // Try JSON parsing first
        $json_match = [];
        if (preg_match('/\{[\s\S]*\}/', $response, $json_match)) {
            $json_str = $json_match[0];
            $parsed = json_decode($json_str, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }
        
        // Fallback to text parsing
        return [
            'review_text' => $this->extract_between($response, 'Review:', 'Scores:'),
            'scores' => $this->extract_scores($response),
            'vibes' => $this->extract_list($response, 'Vibes:'),
            'verdict' => $this->extract_line($response, 'Verdict:'),
            'highlights' => $this->extract_list($response, 'Highlights:'),
            'criticisms' => $this->extract_list($response, 'Criticisms:')
        ];
    }
    
    /**
     * Calculate synthesis confidence
     */
    private function calculate_synthesis_confidence( array $synthesis, array $context ): float {
        $confidence = 0.5; // Base confidence
        
        // Boost for multiple data sources
        $source_count = count(array_filter($context['factual_data']));
        $confidence += $source_count * 0.1;
        
        // Boost for data agreement
        if (empty($synthesis['data_quality']['conflicts'])) {
            $confidence += 0.2;
        }
        
        // Boost for completeness
        if (empty($synthesis['data_quality']['missing_data'])) {
            $confidence += 0.1;
        }
        
        // Cap at 0.95
        return min($confidence, 0.95);
    }
    
    /**
     * Validate and adjust scores based on real data
     */
    private function validate_scores( array $scores, array $business_data ): array {
        // If we have real review scores, ensure AI scores align somewhat
        $real_scores = [];
        
        if (!empty($business_data['yelp']['rating'])) {
            $real_scores[] = $business_data['yelp']['rating'] * 2; // Convert 5-star to 10-scale
        }
        
        if (!empty($business_data['google']['rating'])) {
            $real_scores[] = $business_data['google']['rating'] * 2;
        }
        
        if (!empty($real_scores)) {
            $avg_real_score = array_sum($real_scores) / count($real_scores);
            
            // If AI overall score is wildly different, adjust it
            if (abs($scores['overall'] - $avg_real_score) > 3.0) {
                $scores['overall'] = round(($scores['overall'] + $avg_real_score) / 2, 1);
                
                // Adjust pillar scores proportionally
                foreach ($scores as $key => &$score) {
                    if ($key !== 'overall') {
                        $score = round($score * ($scores['overall'] / 10), 1);
                    }
                }
            }
        }
        
        return $scores;
    }
    
    /**
     * Get scoring pillars for business category
     */
    private function get_scoring_pillars( array $business_data ): array {
        $category = $this->detect_business_category($business_data);
        
        $pillars = [
            'restaurant' => [
                'Food Quality' => 'Taste, freshness, presentation, and creativity',
                'Service' => 'Attentiveness, knowledge, friendliness, and efficiency',
                'Atmosphere' => 'Ambiance, design, comfort, and vibe',
                'Value' => 'Price-to-quality ratio and portion sizes',
                'Consistency' => 'Reliability across visits and dishes',
                'Cultural Relevance' => 'Authenticity, innovation, and local impact'
            ],
            'salon' => [
                'Technical Skill' => 'Expertise, precision, and results',
                'Customer Service' => 'Friendliness, communication, and comfort',
                'Cleanliness' => 'Hygiene, organization, and maintenance',
                'Value' => 'Pricing fairness and service quality',
                'Ambiance' => 'Atmosphere, design, and relaxation',
                'Innovation' => 'Trendy techniques and modern offerings'
            ],
            'hotel' => [
                'Room Quality' => 'Comfort, cleanliness, and amenities',
                'Staff Service' => 'Helpfulness, professionalism, and responsiveness',
                'Facilities' => 'Pool, gym, restaurant, and common areas',
                'Location' => 'Convenience, safety, and nearby attractions',
                'Value' => 'Price for quality and included services',
                'Uniqueness' => 'Character, design, and memorable features'
            ]
        ];
        
        return $pillars[$category] ?? $pillars['restaurant'];
    }
    
    /**
     * Detect business category from data
     */
    private function detect_business_category( array $business_data ): string {
        // Check various sources for category hints
        $category_hints = [];
        
        if (!empty($business_data['osm']['tags']['amenity'])) {
            $category_hints[] = $business_data['osm']['tags']['amenity'];
        }
        
        if (!empty($business_data['yelp']['categories'])) {
            foreach ($business_data['yelp']['categories'] as $cat) {
                $category_hints[] = strtolower($cat['title'] ?? '');
            }
        }
        
        if (!empty($business_data['google']['types'])) {
            $category_hints = array_merge($category_hints, $business_data['google']['types']);
        }
        
        // Map to our categories
        foreach ($category_hints as $hint) {
            if (strpos($hint, 'restaurant') !== false || 
                strpos($hint, 'food') !== false ||
                strpos($hint, 'cafe') !== false ||
                strpos($hint, 'bar') !== false) {
                return 'restaurant';
            }
            
            if (strpos($hint, 'salon') !== false || 
                strpos($hint, 'beauty') !== false ||
                strpos($hint, 'spa') !== false ||
                strpos($hint, 'hair') !== false) {
                return 'salon';
            }
            
            if (strpos($hint, 'hotel') !== false || 
                strpos($hint, 'lodging') !== false ||
                strpos($hint, 'motel') !== false) {
                return 'hotel';
            }
        }
        
        return 'restaurant'; // Default
    }
    
    /**
     * Prepare factual context
     */
    private function prepare_factual_context( array $business_data ): string {
        $facts = [];
        
        // Basic info
        if (!empty($business_data['name'])) {
            $facts[] = "Name: {$business_data['name']}";
        }
        
        if (!empty($business_data['address'])) {
            $facts[] = "Address: {$business_data['address']}";
        }
        
        // Hours
        if (!empty($business_data['hours'])) {
            $facts[] = "Hours: " . $this->format_hours($business_data['hours']);
        }
        
        // Ratings
        $ratings = [];
        if (!empty($business_data['yelp']['rating'])) {
            $ratings[] = "Yelp: {$business_data['yelp']['rating']}";
        }
        if (!empty($business_data['google']['rating'])) {
            $ratings[] = "Google: {$business_data['google']['rating']}";
        }
        if (!empty($ratings)) {
            $facts[] = "Ratings: " . implode(', ', $ratings);
        }
        
        // Special features
        $features = [];
        if (!empty($business_data['osm']['outdoor_seating']) && $business_data['osm']['outdoor_seating'] === 'yes') {
            $features[] = 'Outdoor seating';
        }
        if (!empty($business_data['osm']['takeaway']) && $business_data['osm']['takeaway'] === 'yes') {
            $features[] = 'Takeaway available';
        }
        if (!empty($business_data['osm']['delivery']) && $business_data['osm']['delivery'] === 'yes') {
            $features[] = 'Delivery available';
        }
        if (!empty($features)) {
            $facts[] = "Features: " . implode(', ', $features);
        }
        
        return implode("\n", $facts);
    }
    
    /**
     * Prepare review context
     */
    private function prepare_review_context( array $business_data ): string {
        $review_summary = [];
        
        // Yelp reviews
        if (!empty($business_data['yelp']['reviews'])) {
            $yelp_themes = $this->extract_review_themes($business_data['yelp']['reviews']);
            if (!empty($yelp_themes)) {
                $review_summary[] = "Yelp reviewers mention: " . implode(', ', array_slice($yelp_themes, 0, 3));
            }
        }
        
        // Google reviews
        if (!empty($business_data['google']['reviews'])) {
            $google_themes = $this->extract_review_themes($business_data['google']['reviews']);
            if (!empty($google_themes)) {
                $review_summary[] = "Google reviewers highlight: " . implode(', ', array_slice($google_themes, 0, 3));
            }
        }
        
        // Overall sentiment
        if (!empty($business_data['yelp']['review_count']) && !empty($business_data['yelp']['rating'])) {
            $sentiment = $business_data['yelp']['rating'] >= 4.0 ? 'positive' : 
                        ($business_data['yelp']['rating'] >= 3.0 ? 'mixed' : 'negative');
            $review_summary[] = "Overall sentiment: {$sentiment} ({$business_data['yelp']['review_count']} Yelp reviews)";
        }
        
        return implode("\n", $review_summary);
    }
    
    /**
     * Extract review themes
     */
    private function extract_review_themes( array $reviews ): array {
        $all_text = '';
        
        foreach ($reviews as $review) {
            $all_text .= ' ' . ($review['text'] ?? '');
        }
        
        // Simple keyword extraction (in production, use NLP)
        $themes = [];
        
        $positive_keywords = [
            'amazing', 'excellent', 'great', 'wonderful', 'fantastic',
            'delicious', 'perfect', 'best', 'love', 'favorite'
        ];
        
        $aspect_keywords = [
            'service', 'food', 'atmosphere', 'ambiance', 'staff',
            'price', 'value', 'location', 'menu', 'drinks'
        ];
        
        foreach ($positive_keywords as $keyword) {
            if (stripos($all_text, $keyword) !== false) {
                foreach ($aspect_keywords as $aspect) {
                    if (stripos($all_text, $aspect) !== false) {
                        $themes[] = $keyword . ' ' . $aspect;
                        break;
                    }
                }
            }
        }
        
        return array_unique($themes);
    }
    
    /**
     * Format hours for display
     */
    private function format_hours( $hours ): string {
        if (is_array($hours) && isset($hours['formatted'])) {
            return 'See detailed hours';
        }
        
        return 'Hours available';
    }
    
    /**
     * Extract text between markers
     */
    private function extract_between( string $text, string $start, string $end ): string {
        $start_pos = strpos($text, $start);
        if ($start_pos === false) return '';
        
        $start_pos += strlen($start);
        $end_pos = strpos($text, $end, $start_pos);
        
        if ($end_pos === false) {
            return trim(substr($text, $start_pos));
        }
        
        return trim(substr($text, $start_pos, $end_pos - $start_pos));
    }
    
    /**
     * Extract list items
     */
    private function extract_list( string $text, string $marker ): array {
        $section = $this->extract_between($text, $marker, "\n\n");
        
        $items = [];
        $lines = explode("\n", $section);
        
        foreach ($lines as $line) {
            $line = trim($line, "- *•");
            if (!empty($line)) {
                $items[] = trim($line);
            }
        }
        
        return $items;
    }
    
    /**
     * Extract single line
     */
    private function extract_line( string $text, string $marker ): string {
        $pos = strpos($text, $marker);
        if ($pos === false) return '';
        
        $pos += strlen($marker);
        $end = strpos($text, "\n", $pos);
        
        if ($end === false) {
            return trim(substr($text, $pos));
        }
        
        return trim(substr($text, $pos, $end - $pos));
    }
    
    /**
     * Extract number
     */
    private function extract_number( string $text, string $marker ): float {
        $line = $this->extract_line($text, $marker);
        
        if (preg_match('/\d+\.?\d*/', $line, $matches)) {
            return (float)$matches[0];
        }
        
        return 0.0;
    }
    
    /**
     * Extract scores
     */
    private function extract_scores( string $text ): array {
        $scores = [];
        
        // Look for score patterns
        $patterns = [
            'Food Quality' => '/Food Quality:?\s*(\d+\.?\d*)/i',
            'Service' => '/Service:?\s*(\d+\.?\d*)/i',
            'Atmosphere' => '/Atmosphere:?\s*(\d+\.?\d*)/i',
            'Value' => '/Value:?\s*(\d+\.?\d*)/i',
            'Consistency' => '/Consistency:?\s*(\d+\.?\d*)/i',
            'Cultural Relevance' => '/Cultural Relevance:?\s*(\d+\.?\d*)/i',
            'Overall' => '/Overall:?\s*(\d+\.?\d*)/i'
        ];
        
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $scores[str_replace(' ', '_', strtolower($key))] = (float)$matches[1];
            }
        }
        
        // Calculate overall if missing
        if (empty($scores['overall']) && count($scores) > 0) {
            $scores['overall'] = round(array_sum($scores) / count($scores), 1);
        }
        
        return $scores;
    }
    
    /**
     * Get fallback synthesis
     */
    private function get_fallback_synthesis( array $context ): array {
        return [
            'description' => 'Business information is being verified.',
            'vibes' => ['local-favorite'],
            'unique_features' => [],
            'scoring' => [
                'overall' => 0.0,
                'confidence' => 'low'
            ],
            'data_quality' => [
                'conflicts' => [],
                'missing_data' => ['Unable to generate synthesis'],
                'reliability' => 'low'
            ],
            'insights' => 'Limited data available for comprehensive analysis.',
            'review' => 'Pending data collection.',
            'confidence' => 0.1,
            'model_used' => 'fallback'
        ];
    }
    
    /**
     * Get fallback review
     */
    private function get_fallback_review( array $business_data ): array {
        $name = $business_data['business_name'] ?? 'This establishment';
        
        return [
            'review_text' => "{$name} is currently being evaluated by our Master Critic team.",
            'scores' => [
                'overall' => 0.0
            ],
            'vibes' => ['under-review'],
            'verdict' => 'Review pending',
            'highlights' => [],
            'criticisms' => []
        ];
    }
}