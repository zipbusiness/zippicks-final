<?php
/**
 * Process Business Analytics Job
 * 
 * Processes real-time business analytics and insights for the ZipPicks platform.
 * Demonstrates enterprise data processing and aggregation at scale.
 * 
 * @package ZipPicks\Foundation\Jobs
 * @since 3.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Jobs;

use ZipPicks\Foundation\Queue\Job;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use Exception;

/**
 * Process Business Analytics Job
 * 
 * Calculates and stores business performance metrics and insights
 */
class ProcessBusinessAnalyticsJob extends Job
{
    /**
     * Business ID to process
     */
    protected int $businessId;
    
    /**
     * Analytics period
     */
    protected string $period;
    
    /**
     * Analytics type
     */
    protected string $type;
    
    /**
     * Additional options
     */
    protected array $options;
    
    /**
     * Job timeout (10 minutes for complex analytics)
     */
    public int $timeout = 600;
    
    /**
     * Number of retries
     */
    public int $tries = 3;
    
    /**
     * Analytics types
     */
    const TYPE_PERFORMANCE = 'performance';
    const TYPE_ENGAGEMENT = 'engagement';
    const TYPE_COMPETITIVE = 'competitive';
    const TYPE_TASTE_MATCH = 'taste_match';
    const TYPE_REVENUE = 'revenue';
    const TYPE_FORECAST = 'forecast';
    
    /**
     * Analytics periods
     */
    const PERIOD_DAILY = 'daily';
    const PERIOD_WEEKLY = 'weekly';
    const PERIOD_MONTHLY = 'monthly';
    const PERIOD_QUARTERLY = 'quarterly';
    const PERIOD_YEARLY = 'yearly';
    const PERIOD_REALTIME = 'realtime';
    
    /**
     * Create a new job instance
     * 
     * @param int $businessId Business ID
     * @param string $type Analytics type
     * @param string $period Analytics period
     * @param array $options Additional options
     */
    public function __construct(
        int $businessId,
        string $type = self::TYPE_PERFORMANCE,
        string $period = self::PERIOD_DAILY,
        array $options = []
    ) {
        parent::__construct();
        
        $this->businessId = $businessId;
        $this->type = $type;
        $this->period = $period;
        $this->options = $options;
        
        // Set job metadata
        $this->metadata = [
            'business_id' => $businessId,
            'analytics_type' => $type,
            'period' => $period,
            'options' => $options,
        ];
        
        // Tag for monitoring
        $this->tags = ['analytics', 'business', $type, $period];
    }
    
    /**
     * Execute the job
     * 
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        $logger = $this->getLogger();
        $startTime = microtime(true);
        
        $logger->info('Starting business analytics processing', [
            'business_id' => $this->businessId,
            'type' => $this->type,
            'period' => $this->period,
        ]);
        
        try {
            // Step 1: Validate business exists
            $business = $this->validateBusiness();
            
            // Step 2: Gather raw data based on analytics type
            $rawData = $this->gatherRawData($business);
            
            // Step 3: Process analytics based on type
            $analytics = $this->processAnalytics($rawData);
            
            // Step 4: Calculate insights and recommendations
            $insights = $this->generateInsights($analytics);
            
            // Step 5: Compare with benchmarks
            $benchmarks = $this->compareBenchmarks($analytics);
            
            // Step 6: Store analytics results
            $this->storeAnalytics($analytics, $insights, $benchmarks);
            
            // Step 7: Update business dashboard cache
            $this->updateDashboardCache($analytics);
            
            // Step 8: Trigger alerts if needed
            $this->checkAlerts($analytics, $insights);
            
            // Step 9: Queue follow-up jobs if needed
            $this->queueFollowUpJobs($analytics);
            
            $logger->info('Business analytics processing completed', [
                'business_id' => $this->businessId,
                'duration' => microtime(true) - $startTime,
                'metrics_count' => count($analytics['metrics'] ?? []),
                'insights_count' => count($insights),
            ]);
            
        } catch (Exception $e) {
            $logger->error('Business analytics processing failed', [
                'business_id' => $this->businessId,
                'type' => $this->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Validate business exists and is active
     * 
     * @return array Business data
     * @throws Exception
     */
    protected function validateBusiness(): array
    {
        $business = get_post($this->businessId);
        
        if (!$business || $business->post_type !== 'zippicks_business') {
            throw new Exception("Invalid business ID: {$this->businessId}");
        }
        
        if ($business->post_status !== 'publish') {
            throw new Exception("Business is not active: {$this->businessId}");
        }
        
        return [
            'id' => $business->ID,
            'title' => $business->post_title,
            'created' => $business->post_date,
            'owner_id' => $business->post_author,
            'metadata' => $this->getBusinessMetadata($business->ID),
        ];
    }
    
    /**
     * Gather raw data for analytics
     * 
     * @param array $business Business data
     * @return array Raw data
     */
    protected function gatherRawData(array $business): array
    {
        $data = [
            'business' => $business,
            'period_start' => $this->getPeriodStart(),
            'period_end' => $this->getPeriodEnd(),
        ];
        
        switch ($this->type) {
            case self::TYPE_PERFORMANCE:
                $data['views'] = $this->getBusinessViews();
                $data['clicks'] = $this->getBusinessClicks();
                $data['saves'] = $this->getBusinessSaves();
                $data['reviews'] = $this->getBusinessReviews();
                $data['ratings'] = $this->getBusinessRatings();
                break;
                
            case self::TYPE_ENGAGEMENT:
                $data['user_interactions'] = $this->getUserInteractions();
                $data['social_shares'] = $this->getSocialShares();
                $data['comments'] = $this->getComments();
                $data['mentions'] = $this->getMentions();
                break;
                
            case self::TYPE_COMPETITIVE:
                $data['competitors'] = $this->getCompetitors();
                $data['market_share'] = $this->getMarketShare();
                $data['ranking_changes'] = $this->getRankingChanges();
                break;
                
            case self::TYPE_TASTE_MATCH:
                $data['vibe_performance'] = $this->getVibePerformance();
                $data['audience_segments'] = $this->getAudienceSegments();
                $data['taste_trends'] = $this->getTasteTrends();
                break;
                
            case self::TYPE_REVENUE:
                $data['bookings'] = $this->getBookings();
                $data['featured_listing_performance'] = $this->getFeaturedListingPerformance();
                $data['conversion_rates'] = $this->getConversionRates();
                break;
                
            case self::TYPE_FORECAST:
                $data['historical_data'] = $this->getHistoricalData();
                $data['seasonal_factors'] = $this->getSeasonalFactors();
                $data['market_trends'] = $this->getMarketTrends();
                break;
        }
        
        return $data;
    }
    
    /**
     * Process analytics based on type
     * 
     * @param array $rawData Raw data
     * @return array Processed analytics
     */
    protected function processAnalytics(array $rawData): array
    {
        $analytics = [
            'business_id' => $this->businessId,
            'type' => $this->type,
            'period' => $this->period,
            'generated_at' => current_time('mysql'),
            'metrics' => [],
        ];
        
        switch ($this->type) {
            case self::TYPE_PERFORMANCE:
                $analytics['metrics'] = $this->calculatePerformanceMetrics($rawData);
                break;
                
            case self::TYPE_ENGAGEMENT:
                $analytics['metrics'] = $this->calculateEngagementMetrics($rawData);
                break;
                
            case self::TYPE_COMPETITIVE:
                $analytics['metrics'] = $this->calculateCompetitiveMetrics($rawData);
                break;
                
            case self::TYPE_TASTE_MATCH:
                $analytics['metrics'] = $this->calculateTasteMatchMetrics($rawData);
                break;
                
            case self::TYPE_REVENUE:
                $analytics['metrics'] = $this->calculateRevenueMetrics($rawData);
                break;
                
            case self::TYPE_FORECAST:
                $analytics['metrics'] = $this->calculateForecastMetrics($rawData);
                break;
        }
        
        // Add period comparisons
        $analytics['comparisons'] = $this->calculatePeriodComparisons($analytics['metrics']);
        
        return $analytics;
    }
    
    /**
     * Generate insights from analytics
     * 
     * @param array $analytics Processed analytics
     * @return array Insights
     */
    protected function generateInsights(array $analytics): array
    {
        $insights = [];
        $metrics = $analytics['metrics'];
        
        // Performance insights
        if ($this->type === self::TYPE_PERFORMANCE) {
            if (($metrics['conversion_rate'] ?? 0) < 0.02) {
                $insights[] = [
                    'type' => 'warning',
                    'category' => 'conversion',
                    'message' => 'Low conversion rate detected',
                    'recommendation' => 'Consider updating your business photos and description',
                    'priority' => 'high',
                ];
            }
            
            if (($metrics['review_velocity'] ?? 0) > 5) {
                $insights[] = [
                    'type' => 'positive',
                    'category' => 'reviews',
                    'message' => 'High review velocity',
                    'recommendation' => 'Capitalize on momentum with featured listing',
                    'priority' => 'medium',
                ];
            }
        }
        
        // Engagement insights
        if ($this->type === self::TYPE_ENGAGEMENT) {
            if (($metrics['repeat_visitor_rate'] ?? 0) > 0.3) {
                $insights[] = [
                    'type' => 'positive',
                    'category' => 'loyalty',
                    'message' => 'Strong repeat visitor rate',
                    'recommendation' => 'Launch a loyalty program to increase retention',
                    'priority' => 'medium',
                ];
            }
        }
        
        // Competitive insights
        if ($this->type === self::TYPE_COMPETITIVE) {
            if (($metrics['market_share_change'] ?? 0) < -0.05) {
                $insights[] = [
                    'type' => 'alert',
                    'category' => 'competition',
                    'message' => 'Losing market share to competitors',
                    'recommendation' => 'Review competitor offerings and differentiate',
                    'priority' => 'high',
                ];
            }
        }
        
        return $insights;
    }
    
    /**
     * Compare with industry benchmarks
     * 
     * @param array $analytics Analytics data
     * @return array Benchmark comparisons
     */
    protected function compareBenchmarks(array $analytics): array
    {
        $benchmarks = [];
        $industryData = $this->getIndustryBenchmarks();
        
        foreach ($analytics['metrics'] as $metric => $value) {
            if (isset($industryData[$metric])) {
                $benchmark = $industryData[$metric];
                $percentile = $this->calculatePercentile($value, $benchmark);
                
                $benchmarks[$metric] = [
                    'value' => $value,
                    'industry_avg' => $benchmark['avg'],
                    'industry_p50' => $benchmark['p50'],
                    'industry_p75' => $benchmark['p75'],
                    'industry_p90' => $benchmark['p90'],
                    'percentile' => $percentile,
                    'performance' => $this->getPerformanceLevel($percentile),
                ];
            }
        }
        
        return $benchmarks;
    }
    
    /**
     * Store analytics results
     * 
     * @param array $analytics Analytics data
     * @param array $insights Insights
     * @param array $benchmarks Benchmarks
     * @return void
     */
    protected function storeAnalytics(array $analytics, array $insights, array $benchmarks): void
    {
        global $wpdb;
        
        // Store main analytics record
        $wpdb->insert(
            $wpdb->prefix . 'zippicks_business_analytics',
            [
                'business_id' => $this->businessId,
                'type' => $this->type,
                'period' => $this->period,
                'period_start' => $this->getPeriodStart(),
                'period_end' => $this->getPeriodEnd(),
                'metrics' => json_encode($analytics['metrics']),
                'comparisons' => json_encode($analytics['comparisons']),
                'insights' => json_encode($insights),
                'benchmarks' => json_encode($benchmarks),
                'created_at' => current_time('mysql'),
            ]
        );
        
        // Store individual metrics for time series
        foreach ($analytics['metrics'] as $metric => $value) {
            $wpdb->insert(
                $wpdb->prefix . 'zippicks_analytics_metrics',
                [
                    'business_id' => $this->businessId,
                    'metric_name' => $metric,
                    'metric_value' => $value,
                    'period' => $this->period,
                    'recorded_at' => current_time('mysql'),
                ]
            );
        }
        
        // Update business meta with latest analytics
        update_post_meta($this->businessId, '_zippicks_latest_analytics', [
            'updated' => current_time('timestamp'),
            'performance_score' => $this->calculateOverallScore($analytics),
            'key_metrics' => $this->getKeyMetrics($analytics),
        ]);
    }
    
    /**
     * Update dashboard cache
     * 
     * @param array $analytics Analytics data
     * @return void
     */
    protected function updateDashboardCache(array $analytics): void
    {
        $cacheKey = "business_dashboard_{$this->businessId}";
        $dashboardData = wp_cache_get($cacheKey, 'zippicks_analytics') ?: [];
        
        // Update relevant section
        $dashboardData[$this->type] = [
            'metrics' => $analytics['metrics'],
            'comparisons' => $analytics['comparisons'],
            'updated' => current_time('timestamp'),
        ];
        
        // Cache for 1 hour
        wp_cache_set($cacheKey, $dashboardData, 'zippicks_analytics', HOUR_IN_SECONDS);
        
        // Also update Redis if available
        if (function_exists('wp_cache_add_redis_hash_groups')) {
            wp_cache_set(
                $cacheKey,
                $dashboardData,
                'zippicks_analytics_redis',
                HOUR_IN_SECONDS
            );
        }
    }
    
    /**
     * Check and trigger alerts
     * 
     * @param array $analytics Analytics data
     * @param array $insights Insights
     * @return void
     */
    protected function checkAlerts(array $analytics, array $insights): void
    {
        $criticalInsights = array_filter($insights, function ($insight) {
            return $insight['priority'] === 'high' && $insight['type'] === 'alert';
        });
        
        if (!empty($criticalInsights)) {
            // Queue alert email to business owner
            $owner = get_user_by('id', get_post_field('post_author', $this->businessId));
            
            if ($owner) {
                dispatch(new SendPersonalizedEmailJob(
                    $owner->ID,
                    'business_alert',
                    [
                        'business_id' => $this->businessId,
                        'alerts' => $criticalInsights,
                        'analytics_summary' => $this->getAnalyticsSummary($analytics),
                    ]
                ));
            }
            
            // Log alert
            $this->getLogger()->warning('Business analytics alert triggered', [
                'business_id' => $this->businessId,
                'alerts' => $criticalInsights,
            ]);
        }
    }
    
    /**
     * Queue follow-up jobs
     * 
     * @param array $analytics Analytics data
     * @return void
     */
    protected function queueFollowUpJobs(array $analytics): void
    {
        // If performance is declining, queue competitive analysis
        if ($this->type === self::TYPE_PERFORMANCE) {
            $performanceScore = $this->calculateOverallScore($analytics);
            
            if ($performanceScore < 0.5) {
                dispatch(new ProcessBusinessAnalyticsJob(
                    $this->businessId,
                    self::TYPE_COMPETITIVE,
                    $this->period
                ))->delay(300); // 5 minutes delay
            }
        }
        
        // If this was a daily calculation, check if weekly is due
        if ($this->period === self::PERIOD_DAILY) {
            $lastWeekly = get_post_meta(
                $this->businessId,
                '_zippicks_last_weekly_analytics',
                true
            );
            
            if (!$lastWeekly || (time() - $lastWeekly) > WEEK_IN_SECONDS) {
                dispatch(new ProcessBusinessAnalyticsJob(
                    $this->businessId,
                    $this->type,
                    self::PERIOD_WEEKLY
                ));
                
                update_post_meta(
                    $this->businessId,
                    '_zippicks_last_weekly_analytics',
                    time()
                );
            }
        }
    }
    
    /**
     * Get business metadata
     * 
     * @param int $businessId Business ID
     * @return array Metadata
     */
    protected function getBusinessMetadata(int $businessId): array
    {
        return [
            'vibes' => wp_get_post_terms($businessId, 'zippicks_vibe', ['fields' => 'slugs']),
            'cuisine' => wp_get_post_terms($businessId, 'zippicks_cuisine', ['fields' => 'slugs']),
            'price_range' => get_post_meta($businessId, '_price_range', true),
            'location' => get_post_meta($businessId, '_location', true),
            'featured' => get_post_meta($businessId, '_is_featured', true) === 'yes',
        ];
    }
    
    /**
     * Calculate performance metrics
     * 
     * @param array $data Raw data
     * @return array Metrics
     */
    protected function calculatePerformanceMetrics(array $data): array
    {
        $views = $data['views'] ?? 0;
        $clicks = $data['clicks'] ?? 0;
        $saves = $data['saves'] ?? 0;
        $reviews = count($data['reviews'] ?? []);
        
        return [
            'total_views' => $views,
            'total_clicks' => $clicks,
            'total_saves' => $saves,
            'total_reviews' => $reviews,
            'click_through_rate' => $views > 0 ? $clicks / $views : 0,
            'save_rate' => $views > 0 ? $saves / $views : 0,
            'conversion_rate' => $clicks > 0 ? $saves / $clicks : 0,
            'avg_rating' => $this->calculateAverageRating($data['ratings'] ?? []),
            'review_velocity' => $this->calculateReviewVelocity($data['reviews'] ?? []),
            'engagement_score' => $this->calculateEngagementScore($views, $clicks, $saves, $reviews),
        ];
    }
    
    /**
     * Calculate engagement metrics
     * 
     * @param array $data Raw data
     * @return array Metrics
     */
    protected function calculateEngagementMetrics(array $data): array
    {
        $interactions = $data['user_interactions'] ?? [];
        $shares = $data['social_shares'] ?? 0;
        
        return [
            'unique_visitors' => count(array_unique(array_column($interactions, 'user_id'))),
            'repeat_visitor_rate' => $this->calculateRepeatVisitorRate($interactions),
            'avg_session_duration' => $this->calculateAvgSessionDuration($interactions),
            'social_shares' => $shares,
            'virality_score' => $this->calculateViralityScore($shares, count($interactions)),
            'engagement_depth' => $this->calculateEngagementDepth($interactions),
        ];
    }
    
    /**
     * Calculate competitive metrics
     * 
     * @param array $data Raw data
     * @return array Metrics
     */
    protected function calculateCompetitiveMetrics(array $data): array
    {
        return [
            'market_share' => $data['market_share'] ?? 0,
            'market_share_change' => $this->calculateMarketShareChange($data),
            'competitive_position' => $this->calculateCompetitivePosition($data),
            'share_of_voice' => $this->calculateShareOfVoice($data),
            'relative_performance' => $this->calculateRelativePerformance($data),
        ];
    }
    
    /**
     * Calculate taste match metrics
     * 
     * @param array $data Raw data
     * @return array Metrics
     */
    protected function calculateTasteMatchMetrics(array $data): array
    {
        return [
            'vibe_alignment_score' => $this->calculateVibeAlignment($data),
            'audience_fit_score' => $this->calculateAudienceFit($data),
            'taste_trend_alignment' => $this->calculateTasteTrendAlignment($data),
            'discovery_potential' => $this->calculateDiscoveryPotential($data),
        ];
    }
    
    /**
     * Calculate revenue metrics
     * 
     * @param array $data Raw data
     * @return array Metrics
     */
    protected function calculateRevenueMetrics(array $data): array
    {
        $bookings = $data['bookings'] ?? [];
        
        return [
            'total_bookings' => count($bookings),
            'booking_value' => array_sum(array_column($bookings, 'value')),
            'avg_booking_value' => $this->calculateAvgBookingValue($bookings),
            'featured_roi' => $this->calculateFeaturedROI($data),
            'revenue_per_view' => $this->calculateRevenuePerView($data),
        ];
    }
    
    /**
     * Calculate forecast metrics
     * 
     * @param array $data Raw data
     * @return array Metrics
     */
    protected function calculateForecastMetrics(array $data): array
    {
        return [
            'predicted_views' => $this->forecastViews($data),
            'predicted_bookings' => $this->forecastBookings($data),
            'growth_trajectory' => $this->calculateGrowthTrajectory($data),
            'seasonality_factor' => $this->calculateSeasonalityFactor($data),
            'confidence_interval' => $this->calculateConfidenceInterval($data),
        ];
    }
    
    /**
     * Get period start timestamp
     * 
     * @return string
     */
    protected function getPeriodStart(): string
    {
        $start = new \DateTime();
        
        switch ($this->period) {
            case self::PERIOD_DAILY:
                $start->modify('today');
                break;
            case self::PERIOD_WEEKLY:
                $start->modify('monday this week');
                break;
            case self::PERIOD_MONTHLY:
                $start->modify('first day of this month');
                break;
            case self::PERIOD_QUARTERLY:
                $month = (int) $start->format('n');
                $quarterStart = floor(($month - 1) / 3) * 3 + 1;
                $start->setDate((int) $start->format('Y'), $quarterStart, 1);
                break;
            case self::PERIOD_YEARLY:
                $start->modify('first day of january');
                break;
            case self::PERIOD_REALTIME:
                $start->modify('-1 hour');
                break;
        }
        
        return $start->format('Y-m-d H:i:s');
    }
    
    /**
     * Get period end timestamp
     * 
     * @return string
     */
    protected function getPeriodEnd(): string
    {
        $end = new \DateTime();
        
        switch ($this->period) {
            case self::PERIOD_DAILY:
                $end->modify('tomorrow')->modify('-1 second');
                break;
            case self::PERIOD_WEEKLY:
                $end->modify('sunday this week')->modify('+1 day')->modify('-1 second');
                break;
            case self::PERIOD_MONTHLY:
                $end->modify('last day of this month')->modify('+1 day')->modify('-1 second');
                break;
            case self::PERIOD_QUARTERLY:
                $month = (int) $end->format('n');
                $quarterEnd = floor(($month - 1) / 3) * 3 + 3;
                $end->setDate((int) $end->format('Y'), $quarterEnd, 1);
                $end->modify('last day of this month')->modify('+1 day')->modify('-1 second');
                break;
            case self::PERIOD_YEARLY:
                $end->modify('last day of december')->modify('+1 day')->modify('-1 second');
                break;
            case self::PERIOD_REALTIME:
                // Current time
                break;
        }
        
        return $end->format('Y-m-d H:i:s');
    }
    
    /**
     * Calculate overall performance score
     * 
     * @param array $analytics Analytics data
     * @return float Score between 0 and 1
     */
    protected function calculateOverallScore(array $analytics): float
    {
        // Weighted scoring based on key metrics
        $weights = [
            'conversion_rate' => 0.3,
            'engagement_score' => 0.3,
            'avg_rating' => 0.2,
            'review_velocity' => 0.2,
        ];
        
        $score = 0.0;
        $totalWeight = 0.0;
        
        foreach ($weights as $metric => $weight) {
            if (isset($analytics['metrics'][$metric])) {
                $value = $analytics['metrics'][$metric];
                $normalizedValue = $this->normalizeMetric($metric, $value);
                $score += $normalizedValue * $weight;
                $totalWeight += $weight;
            }
        }
        
        return $totalWeight > 0 ? $score / $totalWeight : 0.0;
    }
    
    /**
     * Normalize metric value to 0-1 range
     * 
     * @param string $metric Metric name
     * @param float $value Metric value
     * @return float Normalized value
     */
    protected function normalizeMetric(string $metric, float $value): float
    {
        // Define normalization ranges for each metric
        $ranges = [
            'conversion_rate' => [0, 0.1],
            'engagement_score' => [0, 100],
            'avg_rating' => [0, 10],
            'review_velocity' => [0, 10],
        ];
        
        if (!isset($ranges[$metric])) {
            return 0.5;
        }
        
        [$min, $max] = $ranges[$metric];
        
        if ($value <= $min) return 0.0;
        if ($value >= $max) return 1.0;
        
        return ($value - $min) / ($max - $min);
    }
    
    /**
     * Get key metrics summary
     * 
     * @param array $analytics Analytics data
     * @return array Key metrics
     */
    protected function getKeyMetrics(array $analytics): array
    {
        $metrics = $analytics['metrics'];
        
        return [
            'views' => $metrics['total_views'] ?? 0,
            'conversion' => round(($metrics['conversion_rate'] ?? 0) * 100, 1) . '%',
            'rating' => round($metrics['avg_rating'] ?? 0, 1),
            'engagement' => round($metrics['engagement_score'] ?? 0, 0),
        ];
    }
    
    /**
     * Get analytics summary
     * 
     * @param array $analytics Analytics data
     * @return array Summary
     */
    protected function getAnalyticsSummary(array $analytics): array
    {
        return [
            'period' => $this->period,
            'score' => $this->calculateOverallScore($analytics),
            'key_metrics' => $this->getKeyMetrics($analytics),
            'trend' => $this->calculateTrend($analytics),
        ];
    }
    
    /**
     * Calculate trend direction
     * 
     * @param array $analytics Analytics data
     * @return string Trend direction
     */
    protected function calculateTrend(array $analytics): string
    {
        $comparisons = $analytics['comparisons'] ?? [];
        $positiveChanges = 0;
        $negativeChanges = 0;
        
        foreach ($comparisons as $comparison) {
            if (($comparison['change'] ?? 0) > 0) {
                $positiveChanges++;
            } elseif (($comparison['change'] ?? 0) < 0) {
                $negativeChanges++;
            }
        }
        
        if ($positiveChanges > $negativeChanges * 2) {
            return 'strong_growth';
        } elseif ($positiveChanges > $negativeChanges) {
            return 'growth';
        } elseif ($negativeChanges > $positiveChanges * 2) {
            return 'strong_decline';
        } elseif ($negativeChanges > $positiveChanges) {
            return 'decline';
        }
        
        return 'stable';
    }
    
    // Mock implementations for data gathering methods
    // In production, these would query actual data sources
    
    protected function getBusinessViews(): int
    {
        return rand(100, 10000);
    }
    
    protected function getBusinessClicks(): int
    {
        return rand(10, 1000);
    }
    
    protected function getBusinessSaves(): int
    {
        return rand(5, 500);
    }
    
    protected function getBusinessReviews(): array
    {
        return [];
    }
    
    protected function getBusinessRatings(): array
    {
        return [];
    }
    
    protected function getUserInteractions(): array
    {
        return [];
    }
    
    protected function getSocialShares(): int
    {
        return rand(0, 100);
    }
    
    protected function getComments(): array
    {
        return [];
    }
    
    protected function getMentions(): array
    {
        return [];
    }
    
    protected function getCompetitors(): array
    {
        return [];
    }
    
    protected function getMarketShare(): float
    {
        return 0.15;
    }
    
    protected function getRankingChanges(): array
    {
        return [];
    }
    
    protected function getVibePerformance(): array
    {
        return [];
    }
    
    protected function getAudienceSegments(): array
    {
        return [];
    }
    
    protected function getTasteTrends(): array
    {
        return [];
    }
    
    protected function getBookings(): array
    {
        return [];
    }
    
    protected function getFeaturedListingPerformance(): array
    {
        return [];
    }
    
    protected function getConversionRates(): array
    {
        return [];
    }
    
    protected function getHistoricalData(): array
    {
        return [];
    }
    
    protected function getSeasonalFactors(): array
    {
        return [];
    }
    
    protected function getMarketTrends(): array
    {
        return [];
    }
    
    protected function getIndustryBenchmarks(): array
    {
        return [
            'conversion_rate' => ['avg' => 0.03, 'p50' => 0.025, 'p75' => 0.04, 'p90' => 0.06],
            'engagement_score' => ['avg' => 50, 'p50' => 45, 'p75' => 65, 'p90' => 80],
        ];
    }
    
    protected function calculateAverageRating(array $ratings): float
    {
        if (empty($ratings)) return 0.0;
        return array_sum($ratings) / count($ratings);
    }
    
    protected function calculateReviewVelocity(array $reviews): float
    {
        // Reviews per day in period
        return count($reviews) / 30;
    }
    
    protected function calculateEngagementScore(int $views, int $clicks, int $saves, int $reviews): float
    {
        // Weighted engagement formula
        return ($clicks * 1 + $saves * 3 + $reviews * 5) / max(1, $views) * 100;
    }
    
    protected function calculateRepeatVisitorRate(array $interactions): float
    {
        return 0.25; // Mock
    }
    
    protected function calculateAvgSessionDuration(array $interactions): float
    {
        return 180; // 3 minutes
    }
    
    protected function calculateViralityScore(int $shares, int $interactions): float
    {
        return $interactions > 0 ? $shares / $interactions : 0;
    }
    
    protected function calculateEngagementDepth(array $interactions): float
    {
        return 0.6; // Mock
    }
    
    protected function calculateMarketShareChange(array $data): float
    {
        return -0.02; // Mock
    }
    
    protected function calculateCompetitivePosition(array $data): int
    {
        return 3; // Mock position
    }
    
    protected function calculateShareOfVoice(array $data): float
    {
        return 0.12; // Mock
    }
    
    protected function calculateRelativePerformance(array $data): float
    {
        return 0.95; // Mock
    }
    
    protected function calculateVibeAlignment(array $data): float
    {
        return 0.8; // Mock
    }
    
    protected function calculateAudienceFit(array $data): float
    {
        return 0.75; // Mock
    }
    
    protected function calculateTasteTrendAlignment(array $data): float
    {
        return 0.7; // Mock
    }
    
    protected function calculateDiscoveryPotential(array $data): float
    {
        return 0.85; // Mock
    }
    
    protected function calculateAvgBookingValue(array $bookings): float
    {
        if (empty($bookings)) return 0.0;
        return array_sum(array_column($bookings, 'value')) / count($bookings);
    }
    
    protected function calculateFeaturedROI(array $data): float
    {
        return 2.5; // Mock 250% ROI
    }
    
    protected function calculateRevenuePerView(array $data): float
    {
        return 0.15; // Mock $0.15 per view
    }
    
    protected function forecastViews(array $data): int
    {
        return 15000; // Mock forecast
    }
    
    protected function forecastBookings(array $data): int
    {
        return 150; // Mock forecast
    }
    
    protected function calculateGrowthTrajectory(array $data): float
    {
        return 0.15; // 15% growth
    }
    
    protected function calculateSeasonalityFactor(array $data): float
    {
        return 1.2; // 20% seasonal boost
    }
    
    protected function calculateConfidenceInterval(array $data): array
    {
        return [0.85, 0.95]; // 85-95% confidence
    }
    
    protected function calculatePeriodComparisons(array $metrics): array
    {
        // Mock period-over-period comparisons
        $comparisons = [];
        
        foreach ($metrics as $metric => $value) {
            $comparisons[$metric] = [
                'current' => $value,
                'previous' => $value * 0.9,
                'change' => 0.1,
                'change_percent' => 10,
            ];
        }
        
        return $comparisons;
    }
    
    protected function calculatePercentile(float $value, array $benchmark): int
    {
        if ($value <= $benchmark['avg']) return 50;
        if ($value <= $benchmark['p75']) return 75;
        if ($value <= $benchmark['p90']) return 90;
        return 95;
    }
    
    protected function getPerformanceLevel(int $percentile): string
    {
        if ($percentile >= 90) return 'excellent';
        if ($percentile >= 75) return 'good';
        if ($percentile >= 50) return 'average';
        if ($percentile >= 25) return 'below_average';
        return 'poor';
    }
    
    /**
     * Get logger instance
     * 
     * @return LoggerInterface
     */
    protected function getLogger(): LoggerInterface
    {
        return $this->container->get(LoggerInterface::class);
    }
    
    /**
     * Handle job failure
     * 
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception): void
    {
        $this->getLogger()->error('Business analytics job failed', [
            'business_id' => $this->businessId,
            'type' => $this->type,
            'period' => $this->period,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
        
        // Update business meta to indicate analytics failure
        update_post_meta($this->businessId, '_zippicks_analytics_failed', [
            'timestamp' => current_time('timestamp'),
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);
        
        // Notify monitoring
        do_action('zippicks_analytics_job_failed', $this->businessId, $this->type, $exception);
    }
}