<?php
/**
 * Smart Router - Intelligent data source orchestration
 * 
 * @package ZipPicks_Master_Critic
 * @subpackage Hybrid
 */

namespace ZipPicks\MasterCritic\Hybrid;

use ZipPicks\MasterCritic\Services\CacheManager;
use ZipPicks\MasterCritic\Services\AIService;

class SmartRouter {
    
    private CacheManager $cache;
    private ConfidenceEngine $confidence_engine;
    private DataAggregator $data_aggregator;
    private PaidAPIManager $paid_api_manager;
    private CostOptimizer $cost_optimizer;
    private AIService $ai_service;
    
    /**
     * Cache configuration
     */
    private const CACHE_TTL_DEFAULT = 86400; // 24 hours
    private const CACHE_TTL_TRENDING = 3600;  // 1 hour for trending data
    private const CACHE_PREFIX = 'zp_hybrid_';
    
    /**
     * Query routing configuration
     */
    private const CONFIDENCE_THRESHOLD = 70; // Minimum confidence before enhancement
    private const MAX_PAID_ENHANCEMENT_PERCENT = 0.2; // Max 20% of queries use paid APIs
    
    public function __construct() {
        $this->cache = new CacheManager();
        $this->confidence_engine = new ConfidenceEngine();
        $this->data_aggregator = new DataAggregator();
        $this->paid_api_manager = new PaidAPIManager();
        $this->cost_optimizer = new CostOptimizer();
        $this->ai_service = new AIService();
    }
    
    /**
     * Main routing method - intelligently fetches data from optimal sources
     */
    public function route_query( array $query ): array {
        $start_time = microtime(true);
        $query_hash = $this->generate_query_hash($query);
        
        // Level 1: Serve from cache if fresh
        $cached_data = $this->get_fresh_cache($query_hash, $query);
        if ($cached_data !== false) {
            $this->track_query_metrics('cache_hit', microtime(true) - $start_time);
            return $cached_data;
        }
        
        // Level 2: Determine data requirements
        $requirements = $this->analyze_query_needs($query);
        
        // Level 3: Fetch free data first (90% of our data)
        $free_data = $this->aggregate_free_sources($query, $requirements);
        
        // Level 4: Evaluate confidence and identify gaps
        $confidence_result = $this->confidence_engine->evaluate($free_data, $requirements);
        
        // Level 5: Selective paid enhancement (only if needed)
        if ($confidence_result['score'] < self::CONFIDENCE_THRESHOLD) {
            if ($this->should_enhance_with_paid_apis($query, $confidence_result)) {
                $enhanced_data = $this->enhance_with_paid_apis($free_data, $confidence_result['gaps']);
                $free_data = array_merge_recursive($free_data, $enhanced_data);
            }
        }
        
        // Level 6: AI synthesis and enrichment
        $final_data = $this->synthesize_with_ai($free_data, $query);
        
        // Level 7: Validate, cache, and return
        $validated_data = $this->validate_and_clean($final_data);
        $this->cache_result($query_hash, $validated_data, $query);
        
        // Track metrics
        $this->track_query_metrics('query_complete', microtime(true) - $start_time, [
            'confidence' => $confidence_result['score'],
            'used_paid_apis' => isset($enhanced_data),
            'data_sources' => array_keys($free_data)
        ]);
        
        return $validated_data;
    }
    
    /**
     * Get router statistics
     */
    public function get_statistics(): array {
        return [
            'total_queries' => get_option('zippicks_hybrid_total_queries', 0),
            'cache_hits' => get_option('zippicks_hybrid_cache_hits', 0),
            'paid_api_calls' => get_option('zippicks_hybrid_paid_calls', 0),
            'ai_enhancements' => get_option('zippicks_hybrid_ai_calls', 0),
            'avg_response_time' => get_option('zippicks_hybrid_avg_response', 0)
        ];
    }
    
    /**
     * Analyze what data the query needs
     */
    private function analyze_query_needs( array $query ): array {
        $needs = [
            'basic_info' => true,          // Name, address, hours
            'reviews' => false,            // Review data
            'scoring' => false,            // Scoring data
            'real_time' => false,          // Live status
            'social_proof' => false,       // Social signals
            'menu_data' => false,          // Menu/pricing
            'photos' => false,             // Visual content
            'trending' => false,           // Trending status
        ];
        
        // Analyze query type
        if (!empty($query['include_reviews'])) {
            $needs['reviews'] = true;
            $needs['scoring'] = true;
        }
        
        if (!empty($query['list_type']) && $query['list_type'] === 'trending') {
            $needs['trending'] = true;
            $needs['real_time'] = true;
            $needs['social_proof'] = true;
        }
        
        if (!empty($query['include_full_details'])) {
            $needs['menu_data'] = true;
            $needs['photos'] = true;
        }
        
        // For top 10 lists, we need scoring
        if (!empty($query['list_type']) && strpos($query['list_type'], 'top_') === 0) {
            $needs['scoring'] = true;
            $needs['reviews'] = true;
        }
        
        return $needs;
    }
    
    /**
     * Aggregate data from free sources
     */
    private function aggregate_free_sources( array $query, array $requirements ): array {
        $aggregated_data = [];
        
        // Always get basic data
        if ($requirements['basic_info']) {
            $aggregated_data['osm'] = $this->data_aggregator->fetch_osm_data($query);
            $aggregated_data['wikidata'] = $this->data_aggregator->fetch_wikidata($query);
        }
        
        // Government data for quality signals
        if ($requirements['scoring'] || $requirements['reviews']) {
            $aggregated_data['gov'] = $this->data_aggregator->fetch_government_data($query);
        }
        
        // Social signals
        if ($requirements['social_proof'] || $requirements['trending']) {
            $aggregated_data['social'] = $this->data_aggregator->fetch_social_signals($query);
        }
        
        // Community data
        $aggregated_data['community'] = $this->data_aggregator->fetch_community_data($query);
        
        return $aggregated_data;
    }
    
    /**
     * Determine if we should use paid APIs
     */
    private function should_enhance_with_paid_apis( array $query, array $confidence_result ): bool {
        // Check daily budget
        if (!$this->cost_optimizer->has_budget_remaining()) {
            return false;
        }
        
        // Check if we're within our enhancement percentage
        $today_stats = $this->cost_optimizer->get_today_stats();
        $enhancement_rate = $today_stats['enhanced_queries'] / max($today_stats['total_queries'], 1);
        
        if ($enhancement_rate > self::MAX_PAID_ENHANCEMENT_PERCENT) {
            return false;
        }
        
        // High-value queries get priority
        $priority_score = $this->calculate_query_priority($query, $confidence_result);
        
        return $priority_score > 50; // Threshold for paid enhancement
    }
    
    /**
     * Calculate query priority for paid enhancement
     */
    private function calculate_query_priority( array $query, array $confidence_result ): int {
        $priority = 0;
        
        // User-initiated queries have high priority
        if (!empty($query['user_initiated'])) {
            $priority += 40;
        }
        
        // Top 10 lists need accurate data
        if (!empty($query['list_type']) && strpos($query['list_type'], 'top_') === 0) {
            $priority += 30;
        }
        
        // Low confidence needs enhancement
        if ($confidence_result['score'] < 50) {
            $priority += 30;
        }
        
        // Trending queries need fresh data
        if (!empty($query['list_type']) && $query['list_type'] === 'trending') {
            $priority += 20;
        }
        
        // Popular locations get priority
        if (!empty($query['city']) && $this->is_major_city($query['city'])) {
            $priority += 10;
        }
        
        return min($priority, 100);
    }
    
    /**
     * Enhance data with paid APIs selectively
     */
    private function enhance_with_paid_apis( array $existing_data, array $gaps ): array {
        $enhanced_data = [];
        
        // Prioritize which gaps to fill based on importance
        $prioritized_gaps = $this->prioritize_gaps($gaps);
        
        foreach ($prioritized_gaps as $gap) {
            if (!$this->cost_optimizer->can_make_api_call($gap['api'])) {
                continue;
            }
            
            switch ($gap['type']) {
                case 'reviews':
                    $enhanced_data['yelp'] = $this->paid_api_manager->fetch_yelp_reviews($gap['business_id']);
                    $this->cost_optimizer->track_api_call('yelp');
                    break;
                    
                case 'real_time_status':
                    $enhanced_data['google'] = $this->paid_api_manager->fetch_google_place_details($gap['place_id']);
                    $this->cost_optimizer->track_api_call('google_places');
                    break;
                    
                case 'photos':
                    $enhanced_data['google_photos'] = $this->paid_api_manager->fetch_google_photos($gap['place_id']);
                    $this->cost_optimizer->track_api_call('google_places');
                    break;
            }
        }
        
        return $enhanced_data;
    }
    
    /**
     * Synthesize data with AI for final enrichment
     */
    private function synthesize_with_ai( array $data, array $query ): array {
        // Prepare context for AI
        $context = $this->prepare_ai_context($data, $query);
        
        // Generate enriched content
        $ai_synthesis = $this->ai_service->synthesize_business_data($context);
        
        // Merge AI insights with factual data
        return array_merge($data, [
            'ai_insights' => $ai_synthesis['insights'],
            'master_critic_review' => $ai_synthesis['review'],
            'scoring' => $ai_synthesis['scoring'],
            'vibes' => $ai_synthesis['vibes'],
            'confidence_meta' => [
                'sources_used' => array_keys($data),
                'synthesis_model' => $ai_synthesis['model_used'],
                'confidence_score' => $ai_synthesis['confidence']
            ]
        ]);
    }
    
    /**
     * Validate and clean final data
     */
    private function validate_and_clean( array $data ): array {
        // Remove null values
        $data = array_filter($data, function($value) {
            return $value !== null;
        });
        
        // Validate required fields
        if (empty($data['business_name']) && !empty($data['osm']['name'])) {
            $data['business_name'] = $data['osm']['name'];
        }
        
        // Clean up internal fields
        unset($data['_internal']);
        unset($data['_debug']);
        
        return $data;
    }
    
    /**
     * Cache the result with appropriate TTL
     */
    private function cache_result( string $cache_key, array $data, array $query ): void {
        $ttl = self::CACHE_TTL_DEFAULT;
        
        // Shorter TTL for trending/real-time data
        if (!empty($query['list_type']) && $query['list_type'] === 'trending') {
            $ttl = self::CACHE_TTL_TRENDING;
        }
        
        $this->cache->set($cache_key, $data, $ttl);
    }
    
    /**
     * Get fresh cached data if available
     */
    private function get_fresh_cache( string $cache_key, array $query ): mixed {
        return $this->cache->get($cache_key);
    }
    
    /**
     * Generate consistent cache key for queries
     */
    private function generate_query_hash( array $query ): string {
        ksort($query); // Ensure consistent ordering
        return self::CACHE_PREFIX . md5(json_encode($query));
    }
    
    /**
     * Track query metrics for optimization
     */
    private function track_query_metrics( string $event, float $duration, array $metadata = [] ): void {
        $metrics = [
            'event' => $event,
            'duration' => $duration,
            'timestamp' => time(),
            'metadata' => $metadata
        ];
        
        // Log to database for analysis
        $this->log_metrics($metrics);
    }
    
    /**
     * Check if city is major (for prioritization)
     */
    private function is_major_city( string $city ): bool {
        $major_cities = [
            'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix',
            'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose',
            'Austin', 'Jacksonville', 'San Francisco', 'Seattle', 'Denver',
            'Boston', 'Nashville', 'Portland', 'Las Vegas', 'Miami'
        ];
        
        return in_array($city, $major_cities, true);
    }
    
    /**
     * Prioritize data gaps for paid API usage
     */
    private function prioritize_gaps( array $gaps ): array {
        usort($gaps, function($a, $b) {
            // Priority order: real_time_status > reviews > photos > other
            $priority_map = [
                'real_time_status' => 100,
                'reviews' => 80,
                'photos' => 60,
                'menu' => 40,
                'other' => 20
            ];
            
            $a_priority = $priority_map[$a['type']] ?? 20;
            $b_priority = $priority_map[$b['type']] ?? 20;
            
            return $b_priority - $a_priority;
        });
        
        return array_slice($gaps, 0, 3); // Max 3 API calls per query
    }
    
    /**
     * Prepare context for AI synthesis
     */
    private function prepare_ai_context( array $data, array $query ): array {
        return [
            'query_intent' => $query['list_type'] ?? 'general',
            'location' => $query['city'] ?? 'unknown',
            'factual_data' => $data,
            'synthesis_goals' => [
                'create_compelling_description',
                'identify_unique_vibes',
                'generate_master_critic_score',
                'highlight_standout_features'
            ]
        ];
    }
    
    /**
     * Log metrics to database
     */
    private function log_metrics( array $metrics ): void {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'zippicks_query_metrics',
            [
                'event_type' => $metrics['event'],
                'duration_ms' => $metrics['duration'] * 1000,
                'metadata' => json_encode($metrics['metadata']),
                'created_at' => current_time('mysql')
            ]
        );
    }
}