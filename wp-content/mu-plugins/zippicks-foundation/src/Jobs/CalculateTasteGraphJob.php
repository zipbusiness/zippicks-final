<?php
/**
 * Calculate Taste Graph Job
 * 
 * Processes user interactions and preferences to build personalized taste graphs
 * for the ZipPicks recommendation engine. This job demonstrates enterprise-grade
 * ML pipeline integration within WordPress.
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
 * Calculate Taste Graph Job
 * 
 * Builds and updates user taste profiles based on their interactions
 */
class CalculateTasteGraphJob extends Job
{
    /**
     * User ID to calculate taste graph for
     */
    protected int $userId;
    
    /**
     * Interaction data to process
     */
    protected array $interactions;
    
    /**
     * Whether to perform full recalculation
     */
    protected bool $fullRecalculation;
    
    /**
     * Job timeout (5 minutes for ML processing)
     */
    public int $timeout = 300;
    
    /**
     * Number of retries
     */
    public int $tries = 3;
    
    /**
     * Create a new job instance
     * 
     * @param int $userId User ID
     * @param array $interactions Recent interactions
     * @param bool $fullRecalculation Full recalculation flag
     */
    public function __construct(
        int $userId,
        array $interactions = [],
        bool $fullRecalculation = false
    ) {
        parent::__construct();
        
        $this->userId = $userId;
        $this->interactions = $interactions;
        $this->fullRecalculation = $fullRecalculation;
        
        // Set job metadata for monitoring
        $this->metadata = [
            'user_id' => $userId,
            'interaction_count' => count($interactions),
            'full_recalculation' => $fullRecalculation,
        ];
        
        // Tag for easy filtering
        $this->tags = ['ml', 'taste-graph', 'user-profile'];
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
        
        $logger->info('Starting taste graph calculation', [
            'user_id' => $this->userId,
            'interactions' => count($this->interactions),
            'full_recalculation' => $this->fullRecalculation,
        ]);
        
        try {
            // Step 1: Load existing taste profile
            $existingProfile = $this->loadExistingProfile();
            
            // Step 2: Process new interactions
            $newSignals = $this->processInteractions();
            
            // Step 3: Update taste vectors
            $updatedVectors = $this->updateTasteVectors($existingProfile, $newSignals);
            
            // Step 4: Calculate vibe affinities
            $vibeAffinities = $this->calculateVibeAffinities($updatedVectors);
            
            // Step 5: Generate recommendations
            $recommendations = $this->generateRecommendations($vibeAffinities);
            
            // Step 6: Store updated profile
            $this->storeProfile([
                'user_id' => $this->userId,
                'taste_vectors' => $updatedVectors,
                'vibe_affinities' => $vibeAffinities,
                'recommendations' => $recommendations,
                'last_calculated' => current_time('mysql'),
                'calculation_time' => microtime(true) - $startTime,
            ]);
            
            // Step 7: Trigger downstream events
            $this->triggerDownstreamEvents($recommendations);
            
            $logger->info('Taste graph calculation completed', [
                'user_id' => $this->userId,
                'duration' => microtime(true) - $startTime,
                'recommendation_count' => count($recommendations),
            ]);
            
        } catch (Exception $e) {
            $logger->error('Taste graph calculation failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Re-throw for retry mechanism
            throw $e;
        }
    }
    
    /**
     * Load existing user taste profile
     * 
     * @return array
     */
    protected function loadExistingProfile(): array
    {
        // In production, this would load from database or cache
        $profile = get_user_meta($this->userId, 'zippicks_taste_profile', true);
        
        if (empty($profile) || $this->fullRecalculation) {
            return $this->getDefaultProfile();
        }
        
        return $profile;
    }
    
    /**
     * Process user interactions into signals
     * 
     * @return array
     */
    protected function processInteractions(): array
    {
        $signals = [];
        
        foreach ($this->interactions as $interaction) {
            $weight = $this->calculateInteractionWeight($interaction);
            
            $signals[] = [
                'type' => $interaction['type'],
                'business_id' => $interaction['business_id'],
                'vibes' => $interaction['vibes'] ?? [],
                'score' => $interaction['score'] ?? null,
                'weight' => $weight,
                'timestamp' => $interaction['timestamp'],
            ];
        }
        
        return $signals;
    }
    
    /**
     * Update taste vectors based on new signals
     * 
     * @param array $profile Existing profile
     * @param array $signals New signals
     * @return array Updated vectors
     */
    protected function updateTasteVectors(array $profile, array $signals): array
    {
        $vectors = $profile['taste_vectors'] ?? [];
        
        // Apply exponential decay to old preferences
        $vectors = $this->applyDecay($vectors);
        
        // Update vectors with new signals
        foreach ($signals as $signal) {
            foreach ($signal['vibes'] as $vibe) {
                $vectors[$vibe] = ($vectors[$vibe] ?? 0.0) + $signal['weight'];
            }
        }
        
        // Normalize vectors
        return $this->normalizeVectors($vectors);
    }
    
    /**
     * Calculate vibe affinities from taste vectors
     * 
     * @param array $vectors Taste vectors
     * @return array Vibe affinities
     */
    protected function calculateVibeAffinities(array $vectors): array
    {
        $affinities = [];
        
        // Calculate affinity scores for each vibe
        foreach ($vectors as $vibe => $strength) {
            $affinities[$vibe] = [
                'strength' => $strength,
                'confidence' => $this->calculateConfidence($vibe, $strength),
                'trending' => $this->calculateTrending($vibe),
            ];
        }
        
        // Sort by strength
        uasort($affinities, function ($a, $b) {
            return $b['strength'] <=> $a['strength'];
        });
        
        return array_slice($affinities, 0, 20, true);
    }
    
    /**
     * Generate personalized recommendations
     * 
     * @param array $affinities Vibe affinities
     * @return array Recommendations
     */
    protected function generateRecommendations(array $affinities): array
    {
        global $wpdb;
        
        $recommendations = [];
        $vibes = array_keys($affinities);
        
        if (empty($vibes)) {
            return [];
        }
        
        // Query businesses matching user's top vibes
        // In production, this would use a more sophisticated algorithm
        $placeholders = implode(',', array_fill(0, count($vibes), '%s'));
        
        $query = $wpdb->prepare(
            "SELECT DISTINCT p.ID, p.post_title, 
                    COUNT(DISTINCT t.term_id) as vibe_match_count,
                    AVG(pm.meta_value) as avg_score
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'master_critic_score'
             WHERE p.post_type = 'zippicks_business'
             AND p.post_status = 'publish'
             AND t.slug IN ($placeholders)
             GROUP BY p.ID
             ORDER BY vibe_match_count DESC, avg_score DESC
             LIMIT 50",
            ...$vibes
        );
        
        $businesses = $wpdb->get_results($query);
        
        foreach ($businesses as $business) {
            $score = $this->calculateRecommendationScore(
                $business,
                $affinities
            );
            
            $recommendations[] = [
                'business_id' => $business->ID,
                'title' => $business->post_title,
                'score' => $score,
                'reason' => $this->generateReason($business, $affinities),
            ];
        }
        
        // Sort by recommendation score
        usort($recommendations, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return array_slice($recommendations, 0, 20);
    }
    
    /**
     * Store updated profile
     * 
     * @param array $profile Updated profile data
     * @return void
     */
    protected function storeProfile(array $profile): void
    {
        // Store in user meta
        update_user_meta($this->userId, 'zippicks_taste_profile', $profile);
        
        // Cache for quick access
        wp_cache_set(
            "taste_profile_{$this->userId}",
            $profile,
            'zippicks_profiles',
            HOUR_IN_SECONDS
        );
        
        // Log profile update
        $this->getLogger()->info('Taste profile updated', [
            'user_id' => $this->userId,
            'vector_count' => count($profile['taste_vectors'] ?? []),
            'recommendation_count' => count($profile['recommendations'] ?? []),
        ]);
    }
    
    /**
     * Trigger downstream events
     * 
     * @param array $recommendations Generated recommendations
     * @return void
     */
    protected function triggerDownstreamEvents(array $recommendations): void
    {
        // Dispatch recommendation ready event
        do_action('zippicks_recommendations_ready', $this->userId, $recommendations);
        
        // Queue email job if user has notifications enabled
        $notificationsEnabled = get_user_meta(
            $this->userId,
            'zippicks_email_notifications',
            true
        );
        
        if ($notificationsEnabled && !empty($recommendations)) {
            dispatch(new SendPersonalizedEmailJob(
                $this->userId,
                'new_recommendations',
                ['recommendations' => array_slice($recommendations, 0, 5)]
            ));
        }
        
        // Update user activity timestamp
        update_user_meta($this->userId, 'zippicks_last_taste_update', current_time('timestamp'));
    }
    
    /**
     * Calculate interaction weight based on type and recency
     * 
     * @param array $interaction Interaction data
     * @return float Weight value
     */
    protected function calculateInteractionWeight(array $interaction): float
    {
        // Base weights for different interaction types
        $weights = [
            'visit' => 1.0,
            'save' => 2.0,
            'review' => 3.0,
            'share' => 2.5,
            'click' => 0.5,
        ];
        
        $baseWeight = $weights[$interaction['type']] ?? 1.0;
        
        // Apply time decay
        $age = time() - strtotime($interaction['timestamp']);
        $decayFactor = exp(-$age / (30 * DAY_IN_SECONDS)); // 30-day half-life
        
        return $baseWeight * $decayFactor;
    }
    
    /**
     * Apply decay to existing taste vectors
     * 
     * @param array $vectors Existing vectors
     * @return array Decayed vectors
     */
    protected function applyDecay(array $vectors): array
    {
        $decayRate = 0.95; // 5% decay per calculation
        
        foreach ($vectors as $vibe => $strength) {
            $vectors[$vibe] = $strength * $decayRate;
        }
        
        return $vectors;
    }
    
    /**
     * Normalize taste vectors
     * 
     * @param array $vectors Raw vectors
     * @return array Normalized vectors
     */
    protected function normalizeVectors(array $vectors): array
    {
        $sum = array_sum($vectors);
        
        if ($sum == 0) {
            return $vectors;
        }
        
        foreach ($vectors as $vibe => $strength) {
            $vectors[$vibe] = $strength / $sum;
        }
        
        return $vectors;
    }
    
    /**
     * Calculate confidence for a vibe affinity
     * 
     * @param string $vibe Vibe name
     * @param float $strength Affinity strength
     * @return float Confidence score
     */
    protected function calculateConfidence(string $vibe, float $strength): float
    {
        // Simplified confidence calculation
        // In production, this would consider interaction count, consistency, etc.
        return min(1.0, $strength * 10);
    }
    
    /**
     * Calculate trending score for a vibe
     * 
     * @param string $vibe Vibe name
     * @return float Trending score
     */
    protected function calculateTrending(string $vibe): float
    {
        // In production, this would analyze recent platform-wide trends
        return 0.0;
    }
    
    /**
     * Calculate recommendation score for a business
     * 
     * @param object $business Business data
     * @param array $affinities User's vibe affinities
     * @return float Recommendation score
     */
    protected function calculateRecommendationScore($business, array $affinities): float
    {
        // Simplified scoring algorithm
        // In production, this would be much more sophisticated
        $matchScore = floatval($business->vibe_match_count) / count($affinities);
        $qualityScore = floatval($business->avg_score) / 10;
        
        return ($matchScore * 0.7) + ($qualityScore * 0.3);
    }
    
    /**
     * Generate recommendation reason
     * 
     * @param object $business Business data
     * @param array $affinities User's vibe affinities
     * @return string Reason text
     */
    protected function generateReason($business, array $affinities): string
    {
        // In production, this would generate personalized explanations
        return "Matches your taste for " . implode(', ', array_slice(array_keys($affinities), 0, 3));
    }
    
    /**
     * Get default profile for new users
     * 
     * @return array Default profile structure
     */
    protected function getDefaultProfile(): array
    {
        return [
            'taste_vectors' => [],
            'vibe_affinities' => [],
            'recommendations' => [],
            'created_at' => current_time('mysql'),
        ];
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
        $this->getLogger()->error('Taste graph job permanently failed', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
        
        // Notify monitoring system
        do_action('zippicks_job_failed', static::class, $this->userId, $exception);
    }
}