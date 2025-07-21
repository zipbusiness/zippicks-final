<?php
/**
 * Send Personalized Email Job
 * 
 * Handles high-volume, taste-matched email campaigns for the ZipPicks platform.
 * Demonstrates enterprise email processing with personalization at scale.
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
 * Send Personalized Email Job
 * 
 * Processes and sends personalized emails based on user taste profiles
 */
class SendPersonalizedEmailJob extends Job
{
    /**
     * Recipient user ID
     */
    protected int $userId;
    
    /**
     * Email template type
     */
    protected string $templateType;
    
    /**
     * Email data payload
     */
    protected array $data;
    
    /**
     * Campaign ID for tracking
     */
    protected ?string $campaignId;
    
    /**
     * Job timeout (2 minutes for email processing)
     */
    public int $timeout = 120;
    
    /**
     * Number of retries
     */
    public int $tries = 5;
    
    /**
     * Retry backoff multiplier
     */
    public int $backoff = 60;
    
    /**
     * Email template types
     */
    const TEMPLATE_NEW_RECOMMENDATIONS = 'new_recommendations';
    const TEMPLATE_WEEKLY_DIGEST = 'weekly_digest';
    const TEMPLATE_TRENDING_SPOTS = 'trending_spots';
    const TEMPLATE_FRIEND_ACTIVITY = 'friend_activity';
    const TEMPLATE_BUSINESS_UPDATE = 'business_update';
    const TEMPLATE_TASTE_MATCH = 'taste_match';
    
    /**
     * Create a new job instance
     * 
     * @param int $userId Recipient user ID
     * @param string $templateType Email template type
     * @param array $data Email data
     * @param string|null $campaignId Campaign ID
     */
    public function __construct(
        int $userId,
        string $templateType,
        array $data = [],
        ?string $campaignId = null
    ) {
        parent::__construct();
        
        $this->userId = $userId;
        $this->templateType = $templateType;
        $this->data = $data;
        $this->campaignId = $campaignId ?: $this->generateCampaignId();
        
        // Set job metadata
        $this->metadata = [
            'user_id' => $userId,
            'template' => $templateType,
            'campaign_id' => $this->campaignId,
            'data_size' => strlen(serialize($data)),
        ];
        
        // Tag for filtering and monitoring
        $this->tags = ['email', 'campaign', $templateType];
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
        
        $logger->info('Starting personalized email send', [
            'user_id' => $this->userId,
            'template' => $this->templateType,
            'campaign_id' => $this->campaignId,
        ]);
        
        try {
            // Step 1: Load user data and preferences
            $user = $this->loadUserData();
            
            // Step 2: Check email preferences and suppression
            if (!$this->shouldSendEmail($user)) {
                $logger->info('Email skipped due to preferences or suppression', [
                    'user_id' => $this->userId,
                    'reason' => $this->getSuppressionReason($user),
                ]);
                return;
            }
            
            // Step 3: Load taste profile for personalization
            $tasteProfile = $this->loadTasteProfile();
            
            // Step 4: Build personalized content
            $content = $this->buildPersonalizedContent($user, $tasteProfile);
            
            // Step 5: Render email template
            $email = $this->renderEmail($content);
            
            // Step 6: Send email
            $result = $this->sendEmail($email);
            
            // Step 7: Track delivery
            $this->trackDelivery($result);
            
            // Step 8: Update user engagement metrics
            $this->updateEngagementMetrics();
            
            $logger->info('Personalized email sent successfully', [
                'user_id' => $this->userId,
                'campaign_id' => $this->campaignId,
                'duration' => microtime(true) - $startTime,
                'message_id' => $result['message_id'] ?? null,
            ]);
            
        } catch (Exception $e) {
            $logger->error('Failed to send personalized email', [
                'user_id' => $this->userId,
                'template' => $this->templateType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Check if this is a permanent failure
            if ($this->isPermanentFailure($e)) {
                $this->markAsFailed($e);
                return;
            }
            
            // Re-throw for retry
            throw $e;
        }
    }
    
    /**
     * Load user data
     * 
     * @return array User data
     * @throws Exception
     */
    protected function loadUserData(): array
    {
        $user = get_user_by('id', $this->userId);
        
        if (!$user) {
            throw new Exception("User not found: {$this->userId}");
        }
        
        return [
            'id' => $user->ID,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'first_name' => get_user_meta($user->ID, 'first_name', true),
            'last_name' => get_user_meta($user->ID, 'last_name', true),
            'locale' => get_user_meta($user->ID, 'locale', true) ?: 'en_US',
            'timezone' => get_user_meta($user->ID, 'timezone', true) ?: 'America/New_York',
            'preferences' => $this->loadEmailPreferences($user->ID),
        ];
    }
    
    /**
     * Check if email should be sent
     * 
     * @param array $user User data
     * @return bool
     */
    protected function shouldSendEmail(array $user): bool
    {
        // Check global unsubscribe
        if ($user['preferences']['unsubscribed'] ?? false) {
            return false;
        }
        
        // Check template-specific preferences
        $templatePrefs = $user['preferences']['templates'] ?? [];
        if (isset($templatePrefs[$this->templateType]) && !$templatePrefs[$this->templateType]) {
            return false;
        }
        
        // Check frequency limits
        if (!$this->checkFrequencyLimits($user)) {
            return false;
        }
        
        // Check suppression list
        if ($this->isOnSuppressionList($user['email'])) {
            return false;
        }
        
        // Check bounce status
        if ($this->hasRecentBounce($user['email'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Load user taste profile
     * 
     * @return array Taste profile data
     */
    protected function loadTasteProfile(): array
    {
        // Try cache first
        $cached = wp_cache_get("taste_profile_{$this->userId}", 'zippicks_profiles');
        if ($cached !== false) {
            return $cached;
        }
        
        // Load from database
        $profile = get_user_meta($this->userId, 'zippicks_taste_profile', true);
        
        if (empty($profile)) {
            // Return minimal profile for new users
            return [
                'vibe_affinities' => [],
                'recommendations' => [],
                'favorite_cuisines' => [],
                'price_preference' => 'moderate',
            ];
        }
        
        return $profile;
    }
    
    /**
     * Build personalized email content
     * 
     * @param array $user User data
     * @param array $tasteProfile Taste profile
     * @return array Email content
     */
    protected function buildPersonalizedContent(array $user, array $tasteProfile): array
    {
        $content = [
            'user' => $user,
            'campaign_id' => $this->campaignId,
            'template' => $this->templateType,
            'timestamp' => current_time('timestamp'),
        ];
        
        // Add base data
        $content = array_merge($content, $this->data);
        
        // Build template-specific content
        switch ($this->templateType) {
            case self::TEMPLATE_NEW_RECOMMENDATIONS:
                $content['recommendations'] = $this->personalizeRecommendations(
                    $content['recommendations'] ?? [],
                    $tasteProfile
                );
                $content['subject'] = $this->generateSubject($user, $content['recommendations']);
                break;
                
            case self::TEMPLATE_WEEKLY_DIGEST:
                $content['digest'] = $this->buildWeeklyDigest($user, $tasteProfile);
                $content['subject'] = "Your ZipPicks Weekly: {$content['digest']['highlight']}";
                break;
                
            case self::TEMPLATE_TRENDING_SPOTS:
                $content['trending'] = $this->getTrendingForTaste($tasteProfile);
                $content['subject'] = "Trending in your vibes: {$content['trending'][0]['name'] ?? 'New spots'}";
                break;
                
            case self::TEMPLATE_FRIEND_ACTIVITY:
                $content['activities'] = $this->getFriendActivities($user['id']);
                $content['subject'] = "Your friends are discovering new places";
                break;
                
            case self::TEMPLATE_BUSINESS_UPDATE:
                $content['updates'] = $this->getBusinessUpdates($user, $tasteProfile);
                $content['subject'] = "Updates from your favorite spots";
                break;
                
            case self::TEMPLATE_TASTE_MATCH:
                $content['matches'] = $this->findTasteMatches($user['id'], $tasteProfile);
                $content['subject'] = "You have new taste matches!";
                break;
                
            default:
                $content['subject'] = "Updates from ZipPicks";
        }
        
        // Add personalization tokens
        $content['personalization'] = [
            'user_name' => $user['first_name'] ?: $user['name'],
            'top_vibes' => array_slice(array_keys($tasteProfile['vibe_affinities'] ?? []), 0, 3),
            'locale' => $user['locale'],
            'timezone' => $user['timezone'],
        ];
        
        // Add tracking parameters
        $content['tracking'] = [
            'campaign_id' => $this->campaignId,
            'user_id' => $user['id'],
            'template' => $this->templateType,
            'send_time' => current_time('mysql'),
        ];
        
        return $content;
    }
    
    /**
     * Render email template
     * 
     * @param array $content Email content
     * @return array Rendered email
     */
    protected function renderEmail(array $content): array
    {
        // Get template path
        $templatePath = $this->getTemplatePath($this->templateType);
        
        // Start output buffering
        ob_start();
        
        // Extract content variables
        extract($content);
        
        // Include template
        include $templatePath;
        
        $htmlContent = ob_get_clean();
        
        // Generate text version
        $textContent = $this->generateTextVersion($htmlContent);
        
        return [
            'to' => $content['user']['email'],
            'subject' => $content['subject'],
            'html' => $htmlContent,
            'text' => $textContent,
            'headers' => $this->buildEmailHeaders($content),
            'attachments' => [],
        ];
    }
    
    /**
     * Send email via configured provider
     * 
     * @param array $email Email data
     * @return array Send result
     */
    protected function sendEmail(array $email): array
    {
        // In production, this would use a service like SendGrid, AWS SES, etc.
        // For now, use WordPress mail with enhanced headers
        
        $headers = [];
        foreach ($email['headers'] as $key => $value) {
            $headers[] = "{$key}: {$value}";
        }
        
        // Add content type for HTML
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        
        $sent = wp_mail(
            $email['to'],
            $email['subject'],
            $email['html'],
            $headers,
            $email['attachments']
        );
        
        if (!$sent) {
            throw new Exception('Failed to send email via wp_mail');
        }
        
        return [
            'success' => true,
            'message_id' => wp_generate_uuid4(),
            'timestamp' => current_time('timestamp'),
        ];
    }
    
    /**
     * Track email delivery
     * 
     * @param array $result Send result
     * @return void
     */
    protected function trackDelivery(array $result): void
    {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'zippicks_email_log',
            [
                'user_id' => $this->userId,
                'campaign_id' => $this->campaignId,
                'template' => $this->templateType,
                'message_id' => $result['message_id'],
                'status' => 'sent',
                'sent_at' => current_time('mysql'),
                'metadata' => json_encode($this->metadata),
            ]
        );
        
        // Update campaign statistics
        $this->updateCampaignStats('sent');
    }
    
    /**
     * Update user engagement metrics
     * 
     * @return void
     */
    protected function updateEngagementMetrics(): void
    {
        $lastSent = get_user_meta($this->userId, 'zippicks_last_email', true) ?: [];
        $lastSent[$this->templateType] = current_time('timestamp');
        update_user_meta($this->userId, 'zippicks_last_email', $lastSent);
        
        // Increment send count
        $sendCount = (int) get_user_meta($this->userId, 'zippicks_email_count', true);
        update_user_meta($this->userId, 'zippicks_email_count', $sendCount + 1);
    }
    
    /**
     * Load email preferences
     * 
     * @param int $userId User ID
     * @return array Preferences
     */
    protected function loadEmailPreferences(int $userId): array
    {
        $prefs = get_user_meta($userId, 'zippicks_email_preferences', true);
        
        if (empty($prefs)) {
            // Default preferences
            return [
                'unsubscribed' => false,
                'frequency' => 'weekly',
                'templates' => [
                    self::TEMPLATE_NEW_RECOMMENDATIONS => true,
                    self::TEMPLATE_WEEKLY_DIGEST => true,
                    self::TEMPLATE_TRENDING_SPOTS => true,
                    self::TEMPLATE_FRIEND_ACTIVITY => true,
                    self::TEMPLATE_BUSINESS_UPDATE => true,
                    self::TEMPLATE_TASTE_MATCH => false,
                ],
            ];
        }
        
        return $prefs;
    }
    
    /**
     * Check frequency limits
     * 
     * @param array $user User data
     * @return bool
     */
    protected function checkFrequencyLimits(array $user): bool
    {
        $frequency = $user['preferences']['frequency'] ?? 'weekly';
        $lastSent = get_user_meta($user['id'], 'zippicks_last_email', true) ?: [];
        
        // Check template-specific last sent
        $templateLastSent = $lastSent[$this->templateType] ?? 0;
        
        switch ($frequency) {
            case 'daily':
                $minInterval = DAY_IN_SECONDS;
                break;
            case 'weekly':
                $minInterval = WEEK_IN_SECONDS;
                break;
            case 'monthly':
                $minInterval = MONTH_IN_SECONDS;
                break;
            default:
                $minInterval = WEEK_IN_SECONDS;
        }
        
        return (time() - $templateLastSent) >= $minInterval;
    }
    
    /**
     * Check if email is on suppression list
     * 
     * @param string $email Email address
     * @return bool
     */
    protected function isOnSuppressionList(string $email): bool
    {
        global $wpdb;
        
        $suppressed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_email_suppression 
             WHERE email = %s AND status = 'active'",
            $email
        ));
        
        return $suppressed > 0;
    }
    
    /**
     * Check for recent email bounces
     * 
     * @param string $email Email address
     * @return bool
     */
    protected function hasRecentBounce(string $email): bool
    {
        global $wpdb;
        
        $recentBounce = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_email_bounces 
             WHERE email = %s 
             AND bounce_type IN ('hard', 'complaint')
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $email
        ));
        
        return $recentBounce > 0;
    }
    
    /**
     * Get suppression reason
     * 
     * @param array $user User data
     * @return string
     */
    protected function getSuppressionReason(array $user): string
    {
        if ($user['preferences']['unsubscribed'] ?? false) {
            return 'unsubscribed';
        }
        
        if ($this->isOnSuppressionList($user['email'])) {
            return 'suppression_list';
        }
        
        if ($this->hasRecentBounce($user['email'])) {
            return 'recent_bounce';
        }
        
        return 'frequency_limit';
    }
    
    /**
     * Personalize recommendations
     * 
     * @param array $recommendations Base recommendations
     * @param array $tasteProfile User taste profile
     * @return array Personalized recommendations
     */
    protected function personalizeRecommendations(array $recommendations, array $tasteProfile): array
    {
        // Sort recommendations by taste match
        usort($recommendations, function ($a, $b) use ($tasteProfile) {
            $scoreA = $this->calculateTasteMatch($a, $tasteProfile);
            $scoreB = $this->calculateTasteMatch($b, $tasteProfile);
            return $scoreB <=> $scoreA;
        });
        
        // Add personalized reasons
        foreach ($recommendations as &$rec) {
            $rec['personalized_reason'] = $this->generatePersonalizedReason($rec, $tasteProfile);
            $rec['taste_match_score'] = $this->calculateTasteMatch($rec, $tasteProfile);
        }
        
        return array_slice($recommendations, 0, 5);
    }
    
    /**
     * Calculate taste match score
     * 
     * @param array $item Item to score
     * @param array $tasteProfile User taste profile
     * @return float Match score
     */
    protected function calculateTasteMatch(array $item, array $tasteProfile): float
    {
        $score = 0.0;
        $vibeAffinities = $tasteProfile['vibe_affinities'] ?? [];
        
        foreach ($item['vibes'] ?? [] as $vibe) {
            if (isset($vibeAffinities[$vibe])) {
                $score += $vibeAffinities[$vibe]['strength'] ?? 0;
            }
        }
        
        return $score;
    }
    
    /**
     * Generate personalized reason
     * 
     * @param array $item Item
     * @param array $tasteProfile User taste profile
     * @return string Reason text
     */
    protected function generatePersonalizedReason(array $item, array $tasteProfile): string
    {
        $topVibes = array_keys(array_slice($tasteProfile['vibe_affinities'] ?? [], 0, 3, true));
        $matchingVibes = array_intersect($item['vibes'] ?? [], $topVibes);
        
        if (!empty($matchingVibes)) {
            return "Perfect for your love of " . implode(' and ', $matchingVibes);
        }
        
        return "Discovered just for you";
    }
    
    /**
     * Generate email subject
     * 
     * @param array $user User data
     * @param array $recommendations Recommendations
     * @return string Subject line
     */
    protected function generateSubject(array $user, array $recommendations): string
    {
        if (empty($recommendations)) {
            return "New discoveries await you";
        }
        
        $topRec = $recommendations[0];
        return "{$user['first_name']}, {$topRec['name']} is calling your name";
    }
    
    /**
     * Build weekly digest content
     * 
     * @param array $user User data
     * @param array $tasteProfile Taste profile
     * @return array Digest content
     */
    protected function buildWeeklyDigest(array $user, array $tasteProfile): array
    {
        return [
            'highlight' => $this->getWeekHighlight($user['id']),
            'new_favorites' => $this->getNewFavorites($user['id']),
            'trending_in_vibes' => $this->getTrendingInVibes($tasteProfile),
            'friend_discoveries' => $this->getFriendDiscoveries($user['id']),
            'stats' => $this->getWeeklyStats($user['id']),
        ];
    }
    
    /**
     * Get trending spots for user's taste
     * 
     * @param array $tasteProfile Taste profile
     * @return array Trending spots
     */
    protected function getTrendingForTaste(array $tasteProfile): array
    {
        // Mock implementation - would query trending algorithm
        return [];
    }
    
    /**
     * Get friend activities
     * 
     * @param int $userId User ID
     * @return array Friend activities
     */
    protected function getFriendActivities(int $userId): array
    {
        // Mock implementation - would query social graph
        return [];
    }
    
    /**
     * Get business updates
     * 
     * @param array $user User data
     * @param array $tasteProfile Taste profile
     * @return array Business updates
     */
    protected function getBusinessUpdates(array $user, array $tasteProfile): array
    {
        // Mock implementation - would query saved businesses
        return [];
    }
    
    /**
     * Find taste matches
     * 
     * @param int $userId User ID
     * @param array $tasteProfile Taste profile
     * @return array Taste matches
     */
    protected function findTasteMatches(int $userId, array $tasteProfile): array
    {
        // Mock implementation - would query taste matching algorithm
        return [];
    }
    
    /**
     * Get template path
     * 
     * @param string $templateType Template type
     * @return string Template path
     */
    protected function getTemplatePath(string $templateType): string
    {
        $basePath = dirname(__DIR__, 3) . '/templates/emails/';
        $templateFile = str_replace('_', '-', $templateType) . '.php';
        
        $path = $basePath . $templateFile;
        
        if (!file_exists($path)) {
            // Fall back to default template
            $path = $basePath . 'default.php';
        }
        
        return $path;
    }
    
    /**
     * Generate text version of HTML email
     * 
     * @param string $html HTML content
     * @return string Text content
     */
    protected function generateTextVersion(string $html): string
    {
        // Simple HTML to text conversion
        $text = strip_tags($html);
        $text = html_entity_decode($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = wordwrap($text, 70);
        
        return $text;
    }
    
    /**
     * Build email headers
     * 
     * @param array $content Email content
     * @return array Headers
     */
    protected function buildEmailHeaders(array $content): array
    {
        return [
            'From' => 'ZipPicks <hello@zippicks.com>',
            'Reply-To' => 'support@zippicks.com',
            'X-Campaign-ID' => $this->campaignId,
            'X-Template' => $this->templateType,
            'X-User-ID' => (string) $content['user']['id'],
            'List-Unsubscribe' => '<https://zippicks.com/unsubscribe?token=' . $this->generateUnsubscribeToken($content['user']['id']) . '>',
        ];
    }
    
    /**
     * Generate campaign ID
     * 
     * @return string Campaign ID
     */
    protected function generateCampaignId(): string
    {
        return $this->templateType . '_' . date('Ymd') . '_' . wp_generate_uuid4();
    }
    
    /**
     * Generate unsubscribe token
     * 
     * @param int $userId User ID
     * @return string Token
     */
    protected function generateUnsubscribeToken(int $userId): string
    {
        return wp_hash($userId . $this->campaignId . wp_salt());
    }
    
    /**
     * Update campaign statistics
     * 
     * @param string $action Action type
     * @return void
     */
    protected function updateCampaignStats(string $action): void
    {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}zippicks_campaign_stats 
             (campaign_id, date, {$action}_count) 
             VALUES (%s, CURDATE(), 1)
             ON DUPLICATE KEY UPDATE {$action}_count = {$action}_count + 1",
            $this->campaignId
        ));
    }
    
    /**
     * Check if exception is permanent failure
     * 
     * @param Exception $e Exception
     * @return bool
     */
    protected function isPermanentFailure(Exception $e): bool
    {
        $permanentErrors = [
            'User not found',
            'Invalid email address',
            'Template not found',
        ];
        
        foreach ($permanentErrors as $error) {
            if (strpos($e->getMessage(), $error) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Mark job as permanently failed
     * 
     * @param Exception $e Exception
     * @return void
     */
    protected function markAsFailed(Exception $e): void
    {
        $this->delete();
        
        $this->getLogger()->error('Email job permanently failed', [
            'user_id' => $this->userId,
            'template' => $this->templateType,
            'campaign_id' => $this->campaignId,
            'error' => $e->getMessage(),
        ]);
        
        // Update campaign stats
        $this->updateCampaignStats('failed');
    }
    
    /**
     * Get week highlight for user
     * 
     * @param int $userId User ID
     * @return string Highlight text
     */
    protected function getWeekHighlight(int $userId): string
    {
        // Mock implementation
        return "5 new spots match your taste";
    }
    
    /**
     * Get new favorites
     * 
     * @param int $userId User ID
     * @return array New favorites
     */
    protected function getNewFavorites(int $userId): array
    {
        // Mock implementation
        return [];
    }
    
    /**
     * Get trending in user's vibes
     * 
     * @param array $tasteProfile Taste profile
     * @return array Trending items
     */
    protected function getTrendingInVibes(array $tasteProfile): array
    {
        // Mock implementation
        return [];
    }
    
    /**
     * Get friend discoveries
     * 
     * @param int $userId User ID
     * @return array Friend discoveries
     */
    protected function getFriendDiscoveries(int $userId): array
    {
        // Mock implementation
        return [];
    }
    
    /**
     * Get weekly statistics
     * 
     * @param int $userId User ID
     * @return array Weekly stats
     */
    protected function getWeeklyStats(int $userId): array
    {
        // Mock implementation
        return [
            'places_discovered' => 12,
            'vibes_explored' => 5,
            'friends_active' => 8,
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
        $this->getLogger()->error('Email job permanently failed after retries', [
            'user_id' => $this->userId,
            'template' => $this->templateType,
            'campaign_id' => $this->campaignId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
        
        // Update failure stats
        $this->updateCampaignStats('failed');
        
        // Notify monitoring
        do_action('zippicks_email_job_failed', $this->userId, $this->templateType, $exception);
    }
}