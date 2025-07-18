<?php
/**
 * Cost Optimizer - Manages API usage and budget optimization
 * 
 * @package ZipPicks_Master_Critic
 * @subpackage Hybrid
 */

namespace ZipPicks\MasterCritic\Hybrid;

class CostOptimizer {
    
    /**
     * Budget configuration
     */
    private const DAILY_BUDGET = 6.67; // $200/month = ~$6.67/day
    private const GOOGLE_COST_PER_CALL = 0.017; // $17 per 1000
    private const YELP_COST_PER_CALL = 0; // Free within limits
    
    /**
     * Usage thresholds
     */
    private const BUDGET_WARNING_THRESHOLD = 0.8; // Warn at 80% usage
    private const BUDGET_CRITICAL_THRESHOLD = 0.95; // Critical at 95%
    
    /**
     * Enhancement rate targets
     */
    private const TARGET_ENHANCEMENT_RATE = 0.1; // 10% of queries
    private const MAX_ENHANCEMENT_RATE = 0.2; // Never exceed 20%
    
    /**
     * Priority multipliers
     */
    private const PRIORITY_MULTIPLIERS = [
        'top_10_list' => 3.0,
        'user_search' => 2.5,
        'trending' => 2.0,
        'low_confidence' => 1.5,
        'general' => 1.0
    ];
    
    private array $daily_stats;
    private array $monthly_stats;
    
    public function __construct() {
        $this->load_stats();
    }
    
    /**
     * Check if we have budget remaining
     */
    public function has_budget_remaining(): bool {
        $today_spend = $this->get_today_spend();
        return $today_spend < self::DAILY_BUDGET * self::BUDGET_CRITICAL_THRESHOLD;
    }
    
    /**
     * Check if we can make a specific API call
     */
    public function can_make_api_call( string $api ): bool {
        $cost = $this->get_api_cost($api);
        $today_spend = $this->get_today_spend();
        
        // Check daily budget
        if ($today_spend + $cost > self::DAILY_BUDGET) {
            return false;
        }
        
        // Check enhancement rate
        $enhancement_rate = $this->get_enhancement_rate();
        if ($enhancement_rate > self::MAX_ENHANCEMENT_RATE) {
            return false;
        }
        
        // API-specific checks
        switch ($api) {
            case 'google_places':
                return $this->can_use_google();
                
            case 'yelp':
                return $this->can_use_yelp();
                
            default:
                return false;
        }
    }
    
    /**
     * Track an API call
     */
    public function track_api_call( string $api, array $metadata = [] ): void {
        $cost = $this->get_api_cost($api);
        
        // Update daily stats
        $this->daily_stats['api_calls'][$api] = ($this->daily_stats['api_calls'][$api] ?? 0) + 1;
        $this->daily_stats['total_cost'] += $cost;
        $this->daily_stats['enhanced_queries']++;
        
        // Update monthly stats
        $this->monthly_stats['api_calls'][$api] = ($this->monthly_stats['api_calls'][$api] ?? 0) + 1;
        $this->monthly_stats['total_cost'] += $cost;
        
        // Log the call
        $this->log_api_call($api, $cost, $metadata);
        
        // Save stats
        $this->save_stats();
        
        // Check for warnings
        $this->check_budget_warnings();
    }
    
    /**
     * Get today's statistics
     */
    public function get_today_stats(): array {
        return [
            'date' => $this->daily_stats['date'],
            'total_queries' => $this->daily_stats['total_queries'],
            'enhanced_queries' => $this->daily_stats['enhanced_queries'],
            'cache_hits' => $this->daily_stats['cache_hits'],
            'total_cost' => round($this->daily_stats['total_cost'], 2),
            'budget_remaining' => round(self::DAILY_BUDGET - $this->daily_stats['total_cost'], 2),
            'enhancement_rate' => $this->get_enhancement_rate(),
            'api_calls' => $this->daily_stats['api_calls']
        ];
    }
    
    /**
     * Get monthly statistics
     */
    public function get_monthly_stats(): array {
        $days_in_month = date('t');
        $monthly_budget = self::DAILY_BUDGET * $days_in_month;
        
        return [
            'month' => $this->monthly_stats['month'],
            'total_queries' => $this->monthly_stats['total_queries'],
            'enhanced_queries' => $this->monthly_stats['enhanced_queries'],
            'cache_hits' => $this->monthly_stats['cache_hits'],
            'total_cost' => round($this->monthly_stats['total_cost'], 2),
            'budget_remaining' => round($monthly_budget - $this->monthly_stats['total_cost'], 2),
            'average_daily_cost' => round($this->monthly_stats['total_cost'] / date('j'), 2),
            'projected_monthly_cost' => round(($this->monthly_stats['total_cost'] / date('j')) * $days_in_month, 2),
            'api_breakdown' => $this->monthly_stats['api_calls']
        ];
    }
    
    /**
     * Get optimization recommendations
     */
    public function get_optimization_recommendations(): array {
        $recommendations = [];
        $stats = $this->get_today_stats();
        
        // Check enhancement rate
        if ($stats['enhancement_rate'] > self::TARGET_ENHANCEMENT_RATE) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => sprintf(
                    'Enhancement rate is %.1f%% (target: %.1f%%). Consider tightening priority thresholds.',
                    $stats['enhancement_rate'] * 100,
                    self::TARGET_ENHANCEMENT_RATE * 100
                ),
                'action' => 'reduce_enhancement_threshold'
            ];
        }
        
        // Check cache performance
        $cache_hit_rate = $stats['cache_hits'] / max($stats['total_queries'], 1);
        if ($cache_hit_rate < 0.8) {
            $recommendations[] = [
                'type' => 'optimization',
                'message' => sprintf(
                    'Cache hit rate is only %.1f%%. Consider increasing cache TTL or pre-warming popular queries.',
                    $cache_hit_rate * 100
                ),
                'action' => 'improve_cache_strategy'
            ];
        }
        
        // Check budget usage
        $budget_usage = $stats['total_cost'] / self::DAILY_BUDGET;
        if ($budget_usage > self::BUDGET_WARNING_THRESHOLD) {
            $recommendations[] = [
                'type' => 'critical',
                'message' => sprintf(
                    'Daily budget usage at %.1f%%. Reduce enhancement rate immediately.',
                    $budget_usage * 100
                ),
                'action' => 'emergency_budget_reduction'
            ];
        }
        
        // API-specific recommendations
        if (!empty($stats['api_calls']['google_places']) && $stats['api_calls']['google_places'] > 100) {
            $recommendations[] = [
                'type' => 'optimization',
                'message' => 'High Google Places usage. Consider caching place IDs for repeated queries.',
                'action' => 'implement_place_id_cache'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Calculate query priority score
     */
    public function calculate_query_priority( array $query, float $confidence_score ): float {
        $priority = 0;
        
        // Base priority from query type
        $query_type = $query['type'] ?? 'general';
        $priority += (self::PRIORITY_MULTIPLIERS[$query_type] ?? 1.0) * 20;
        
        // Confidence-based priority
        if ($confidence_score < 30) {
            $priority += 40;
        } elseif ($confidence_score < 50) {
            $priority += 25;
        } elseif ($confidence_score < 70) {
            $priority += 15;
        }
        
        // User engagement signals
        if (!empty($query['user_id'])) {
            $priority += 20; // Logged-in user
        }
        
        if (!empty($query['return_user'])) {
            $priority += 10; // Returning user
        }
        
        // Location importance
        if ($this->is_high_value_location($query['city'] ?? '')) {
            $priority += 15;
        }
        
        // Time-based factors
        if ($this->is_peak_hours()) {
            $priority += 10;
        }
        
        // Business value signals
        if (!empty($query['potential_revenue'])) {
            $priority += 20; // Monetizable query
        }
        
        return min($priority, 100);
    }
    
    /**
     * Get cost projection for a query
     */
    public function get_query_cost_projection( array $query ): array {
        $projection = [
            'estimated_cost' => 0,
            'api_calls_needed' => [],
            'confidence_without_enhancement' => 0,
            'confidence_with_enhancement' => 0,
            'roi_score' => 0
        ];
        
        // Analyze what APIs would be needed
        if ($query['needs_reviews'] ?? false) {
            $projection['api_calls_needed'][] = 'yelp';
            $projection['estimated_cost'] += $this->get_api_cost('yelp');
        }
        
        if ($query['needs_real_time'] ?? false) {
            $projection['api_calls_needed'][] = 'google_places';
            $projection['estimated_cost'] += $this->get_api_cost('google_places');
        }
        
        // Estimate confidence improvement
        $projection['confidence_without_enhancement'] = $query['current_confidence'] ?? 60;
        $projection['confidence_with_enhancement'] = min(
            $projection['confidence_without_enhancement'] + 25,
            95
        );
        
        // Calculate ROI
        $confidence_improvement = $projection['confidence_with_enhancement'] - $projection['confidence_without_enhancement'];
        $projection['roi_score'] = $confidence_improvement / max($projection['estimated_cost'], 0.001);
        
        return $projection;
    }
    
    /**
     * Pre-warm cache for high-value queries
     */
    public function get_pre_warm_candidates(): array {
        $candidates = [];
        
        // Analyze query patterns
        $popular_queries = $this->get_popular_query_patterns();
        
        foreach ($popular_queries as $pattern) {
            // Only pre-warm if we have budget
            if (!$this->has_budget_remaining()) {
                break;
            }
            
            // Calculate pre-warm value
            $value_score = $pattern['frequency'] * $pattern['revenue_potential'];
            
            if ($value_score > 100) {
                $candidates[] = [
                    'query' => $pattern['query'],
                    'priority' => $value_score,
                    'estimated_cost' => $pattern['estimated_cost'],
                    'potential_cache_hits' => $pattern['frequency']
                ];
            }
        }
        
        // Sort by priority
        usort($candidates, function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
        
        return array_slice($candidates, 0, 10); // Top 10 candidates
    }
    
    /**
     * Load statistics from storage
     */
    private function load_stats(): void {
        $today = date('Y-m-d');
        $this_month = date('Y-m');
        
        // Load daily stats
        $stored_daily = get_option('zippicks_cost_optimizer_daily', []);
        
        if (empty($stored_daily['date']) || $stored_daily['date'] !== $today) {
            // Reset for new day
            $this->daily_stats = [
                'date' => $today,
                'total_queries' => 0,
                'enhanced_queries' => 0,
                'cache_hits' => 0,
                'total_cost' => 0,
                'api_calls' => []
            ];
        } else {
            $this->daily_stats = $stored_daily;
        }
        
        // Load monthly stats
        $stored_monthly = get_option('zippicks_cost_optimizer_monthly', []);
        
        if (empty($stored_monthly['month']) || $stored_monthly['month'] !== $this_month) {
            // Reset for new month
            $this->monthly_stats = [
                'month' => $this_month,
                'total_queries' => 0,
                'enhanced_queries' => 0,
                'cache_hits' => 0,
                'total_cost' => 0,
                'api_calls' => []
            ];
        } else {
            $this->monthly_stats = $stored_monthly;
        }
    }
    
    /**
     * Save statistics to storage
     */
    private function save_stats(): void {
        update_option('zippicks_cost_optimizer_daily', $this->daily_stats);
        update_option('zippicks_cost_optimizer_monthly', $this->monthly_stats);
    }
    
    /**
     * Get API cost
     */
    private function get_api_cost( string $api ): float {
        switch ($api) {
            case 'google_places':
                return self::GOOGLE_COST_PER_CALL;
                
            case 'yelp':
                return self::YELP_COST_PER_CALL;
                
            default:
                return 0;
        }
    }
    
    /**
     * Get today's spend
     */
    private function get_today_spend(): float {
        return $this->daily_stats['total_cost'];
    }
    
    /**
     * Get enhancement rate
     */
    private function get_enhancement_rate(): float {
        $total = max($this->daily_stats['total_queries'], 1);
        return $this->daily_stats['enhanced_queries'] / $total;
    }
    
    /**
     * Check if we can use Google API
     */
    private function can_use_google(): bool {
        // Check monthly limit ($200 credit)
        if ($this->monthly_stats['total_cost'] >= 200) {
            return false;
        }
        
        // Check rate limiting
        $recent_calls = $this->get_recent_api_calls('google_places', 60); // Last minute
        if ($recent_calls > 10) { // Max 10 per minute
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if we can use Yelp API
     */
    private function can_use_yelp(): bool {
        // Check daily limit (5000 calls)
        $today_calls = $this->daily_stats['api_calls']['yelp'] ?? 0;
        if ($today_calls >= 5000) {
            return false;
        }
        
        // Check rate limiting
        $recent_calls = $this->get_recent_api_calls('yelp', 60); // Last minute
        if ($recent_calls > 50) { // Max 50 per minute
            return false;
        }
        
        return true;
    }
    
    /**
     * Log API call for tracking
     */
    private function log_api_call( string $api, float $cost, array $metadata ): void {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'zippicks_api_cost_log',
            [
                'api_name' => $api,
                'cost' => $cost,
                'timestamp' => current_time('mysql'),
                'metadata' => json_encode($metadata)
            ]
        );
    }
    
    /**
     * Check and trigger budget warnings
     */
    private function check_budget_warnings(): void {
        $daily_usage = $this->daily_stats['total_cost'] / self::DAILY_BUDGET;
        
        if ($daily_usage > self::BUDGET_CRITICAL_THRESHOLD) {
            do_action('zippicks_budget_critical', [
                'usage_percent' => $daily_usage * 100,
                'remaining_budget' => self::DAILY_BUDGET - $this->daily_stats['total_cost']
            ]);
        } elseif ($daily_usage > self::BUDGET_WARNING_THRESHOLD) {
            do_action('zippicks_budget_warning', [
                'usage_percent' => $daily_usage * 100,
                'remaining_budget' => self::DAILY_BUDGET - $this->daily_stats['total_cost']
            ]);
        }
    }
    
    /**
     * Check if location is high-value
     */
    private function is_high_value_location( string $city ): bool {
        $high_value_cities = [
            'New York', 'Los Angeles', 'Chicago', 'San Francisco',
            'Seattle', 'Boston', 'Austin', 'Miami', 'Denver'
        ];
        
        return in_array($city, $high_value_cities, true);
    }
    
    /**
     * Check if current time is peak hours
     */
    private function is_peak_hours(): bool {
        $hour = (int)date('G');
        
        // Peak hours: 11am-2pm, 5pm-9pm
        return ($hour >= 11 && $hour <= 14) || ($hour >= 17 && $hour <= 21);
    }
    
    /**
     * Get recent API calls count
     */
    private function get_recent_api_calls( string $api, int $seconds ): int {
        global $wpdb;
        
        $since = date('Y-m-d H:i:s', time() - $seconds);
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_api_cost_log
             WHERE api_name = %s AND timestamp > %s",
            $api,
            $since
        ));
        
        return (int)$count;
    }
    
    /**
     * Get popular query patterns
     */
    private function get_popular_query_patterns(): array {
        global $wpdb;
        
        // Analyze query logs from last 7 days
        $patterns = $wpdb->get_results(
            "SELECT 
                query_pattern,
                COUNT(*) as frequency,
                AVG(revenue_potential) as revenue_potential,
                AVG(estimated_cost) as estimated_cost
             FROM {$wpdb->prefix}zippicks_query_patterns
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY query_pattern
             ORDER BY frequency DESC
             LIMIT 100"
        );
        
        return array_map(function($pattern) {
            return [
                'query' => json_decode($pattern->query_pattern, true),
                'frequency' => (int)$pattern->frequency,
                'revenue_potential' => (float)$pattern->revenue_potential,
                'estimated_cost' => (float)$pattern->estimated_cost
            ];
        }, $patterns ?: []);
    }
    
    /**
     * Track query for pattern analysis
     */
    public function track_query( array $query ): void {
        $this->daily_stats['total_queries']++;
        $this->monthly_stats['total_queries']++;
        
        $this->save_stats();
    }
    
    /**
     * Track cache hit
     */
    public function track_cache_hit(): void {
        $this->daily_stats['cache_hits']++;
        $this->monthly_stats['cache_hits']++;
        
        $this->save_stats();
    }
    
    /**
     * Get dashboard metrics
     */
    public function get_dashboard_metrics(): array {
        $daily = $this->get_today_stats();
        $monthly = $this->get_monthly_stats();
        
        return [
            'daily' => [
                'cost' => '$' . number_format($daily['total_cost'], 2),
                'queries' => number_format($daily['total_queries']),
                'enhancement_rate' => number_format($daily['enhancement_rate'] * 100, 1) . '%',
                'cache_hit_rate' => number_format(($daily['cache_hits'] / max($daily['total_queries'], 1)) * 100, 1) . '%'
            ],
            'monthly' => [
                'cost' => '$' . number_format($monthly['total_cost'], 2),
                'projected' => '$' . number_format($monthly['projected_monthly_cost'], 2),
                'queries' => number_format($monthly['total_queries']),
                'savings' => '$' . number_format($monthly['total_queries'] * 0.017 - $monthly['total_cost'], 2)
            ],
            'status' => $this->get_system_status()
        ];
    }
    
    /**
     * Get system status
     */
    private function get_system_status(): string {
        $daily_usage = $this->daily_stats['total_cost'] / self::DAILY_BUDGET;
        
        if ($daily_usage > self::BUDGET_CRITICAL_THRESHOLD) {
            return 'critical';
        } elseif ($daily_usage > self::BUDGET_WARNING_THRESHOLD) {
            return 'warning';
        }
        
        return 'optimal';
    }
}