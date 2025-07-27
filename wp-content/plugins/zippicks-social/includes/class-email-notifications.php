<?php
/**
 * Email Notifications System
 *
 * @package ZipPicks_Social
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_Email_Notifications
 * 
 * Handles all email notifications for social features
 * Integrates with WP Mail SMTP for reliable delivery
 */
class ZipPicks_Social_Email_Notifications {
    
    /**
     * API client instance
     *
     * @var ZipPicks_Social_API_Client
     */
    private $api_client;
    
    /**
     * Logger instance
     *
     * @var object|null
     */
    private $logger;
    
    /**
     * Email templates directory
     *
     * @var string
     */
    private $templates_dir;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = ZipPicks_Social_API_Client::get_instance();
        $this->templates_dir = ZIPPICKS_SOCIAL_PLUGIN_DIR . 'templates/emails/';
        
        // Use Foundation logger if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $this->logger = zippicks()->get('logger');
        }
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks() {
        // Send notification when someone follows
        add_action('zippicks_social_after_follow', [$this, 'notify_new_follower'], 10, 3);
        
        // Send milestone notifications
        add_action('zippicks_social_milestone_reached', [$this, 'notify_milestone'], 10, 3);
        
        // Weekly digest
        add_action('zippicks_social_send_weekly_digest', [$this, 'send_weekly_digest']);
        
        // Schedule weekly digest if not already scheduled
        if (!wp_next_scheduled('zippicks_social_send_weekly_digest')) {
            // Schedule for Mondays at 10am
            $timestamp = strtotime('next Monday 10:00:00');
            wp_schedule_event($timestamp, 'weekly', 'zippicks_social_send_weekly_digest');
        }
        
        // Process email queue
        add_action('zippicks_social_process_email_queue', [$this, 'process_email_queue']);
        
        // Schedule queue processing if not already scheduled
        if (!wp_next_scheduled('zippicks_social_process_email_queue')) {
            wp_schedule_event(time(), 'hourly', 'zippicks_social_process_email_queue');
        }
    }
    
    /**
     * Send new follower notification
     *
     * @param int $follower_id
     * @param int $followed_id
     * @param string $followed_type
     * @return void
     */
    public function notify_new_follower($follower_id, $followed_id, $followed_type) {
        // Only notify for user follows (not businesses/critics)
        if ($followed_type !== 'user') {
            return;
        }
        
        // Check if user wants notifications
        if (!$this->should_notify_user($followed_id, 'new_follower')) {
            return;
        }
        
        // Get user data
        $follower = get_user_by('id', $follower_id);
        $followed = get_user_by('id', $followed_id);
        
        if (!$follower || !$followed) {
            return;
        }
        
        // Get follower stats
        $stats = $this->api_client->get_stats($follower_id, 'user');
        $follower_stats = !is_wp_error($stats) ? $stats : [
            'followers_count' => 0,
            'following_count' => 0
        ];
        
        // Check for mutual follow
        $is_mutual = false;
        $mutual_check = $this->api_client->is_following($followed_id, $follower_id, 'user');
        if (!is_wp_error($mutual_check)) {
            $is_mutual = $mutual_check['is_following'];
        }
        
        // Get follower's bio and location
        $follower_bio = get_user_meta($follower_id, 'description', true);
        $follower_location = get_user_meta($follower_id, 'zippicks_primary_location', true);
        
        // Get common connections
        $mutual_connections = $this->api_client->get_mutual_connections($followed_id, $follower_id, 'user');
        $mutual_count = !is_wp_error($mutual_connections) ? count($mutual_connections['mutual'] ?? []) : 0;
        
        // Prepare email data
        $email_data = [
            'to' => $followed->user_email,
            'subject' => sprintf('%s started following you on ZipPicks', $follower->display_name),
            'template' => 'new-follower',
            'variables' => [
                'follower_name' => $follower->display_name,
                'follower_avatar' => get_avatar_url($follower_id, ['size' => 96]),
                'follower_bio' => $follower_bio ?: 'No bio yet',
                'follower_location' => $follower_location ? $follower_location['city'] . ', ' . $follower_location['state'] : '',
                'follower_stats' => $follower_stats,
                'follower_url' => get_author_posts_url($follower_id),
                'is_mutual' => $is_mutual,
                'mutual_count' => $mutual_count,
                'followed_name' => $followed->display_name,
                'settings_url' => home_url('/account/notifications/')
            ]
        ];
        
        // Send email
        $this->send_email($email_data);
    }
    
    /**
     * Send milestone notification
     *
     * @param int $user_id
     * @param string $milestone_type
     * @param int $count
     * @return void
     */
    public function notify_milestone($user_id, $milestone_type, $count) {
        // Check if user wants notifications
        if (!$this->should_notify_user($user_id, 'milestone_reached')) {
            return;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        // Define milestone messages
        $milestones = [
            'followers_100' => [
                'subject' => 'You reached 100 followers! 🎉',
                'message' => 'Your taste insights are resonating with the ZipPicks community.'
            ],
            'followers_500' => [
                'subject' => 'Amazing! 500 people follow your taste 🚀',
                'message' => 'You\'re becoming a trusted voice in the food community.'
            ],
            'followers_1000' => [
                'subject' => 'You\'re a ZipPicks influencer with 1,000 followers! 🌟',
                'message' => 'Your recommendations are shaping how people discover great food.'
            ]
        ];
        
        if (!isset($milestones[$milestone_type])) {
            return;
        }
        
        $milestone = $milestones[$milestone_type];
        
        // Get recent followers for the email
        $recent_followers = $this->api_client->get_followers($user_id, 'user', [
            'limit' => 5,
            'offset' => 0
        ]);
        
        $follower_data = [];
        if (!is_wp_error($recent_followers) && !empty($recent_followers['data'])) {
            foreach ($recent_followers['data'] as $follower) {
                $follower_user = get_user_by('id', $follower['follower_id']);
                if ($follower_user) {
                    $follower_data[] = [
                        'name' => $follower_user->display_name,
                        'avatar' => get_avatar_url($follower['follower_id'], ['size' => 48]),
                        'url' => get_author_posts_url($follower['follower_id'])
                    ];
                }
            }
        }
        
        // Prepare email data
        $email_data = [
            'to' => $user->user_email,
            'subject' => $milestone['subject'],
            'template' => 'milestone',
            'variables' => [
                'user_name' => $user->display_name,
                'milestone_message' => $milestone['message'],
                'follower_count' => $count,
                'recent_followers' => $follower_data,
                'profile_url' => get_author_posts_url($user_id),
                'share_url' => $this->get_share_url($user_id, $milestone_type)
            ]
        ];
        
        // Send email
        $this->send_email($email_data);
    }
    
    /**
     * Send weekly digest emails
     *
     * @return void
     */
    public function send_weekly_digest() {
        // Get users who want weekly digests
        $users = get_users([
            'meta_query' => [
                [
                    'key' => 'zippicks_notification_weekly_digest',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);
        
        foreach ($users as $user) {
            $this->send_user_digest($user->ID);
        }
        
        if ($this->logger) {
            $this->logger->info('Weekly digest emails sent', [
                'user_count' => count($users)
            ]);
        }
    }
    
    /**
     * Send digest to specific user
     *
     * @param int $user_id
     * @return void
     */
    private function send_user_digest($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        // Get user's activity feed for the past week
        $activities = $this->api_client->get_activity_feed($user_id, [
            'limit' => 20,
            'since' => strtotime('-1 week')
        ]);
        
        if (is_wp_error($activities) || empty($activities['data'])) {
            return; // No activities to report
        }
        
        // Get new followers this week
        $new_followers = $this->api_client->get_followers($user_id, 'user', [
            'limit' => 10,
            'since' => strtotime('-1 week')
        ]);
        
        $new_follower_count = 0;
        $new_follower_data = [];
        
        if (!is_wp_error($new_followers) && !empty($new_followers['data'])) {
            $new_follower_count = count($new_followers['data']);
            
            foreach (array_slice($new_followers['data'], 0, 5) as $follower) {
                $follower_user = get_user_by('id', $follower['follower_id']);
                if ($follower_user) {
                    $new_follower_data[] = [
                        'name' => $follower_user->display_name,
                        'avatar' => get_avatar_url($follower['follower_id'], ['size' => 48]),
                        'url' => get_author_posts_url($follower['follower_id'])
                    ];
                }
            }
        }
        
        // Get trending restaurants from followed users
        $trending_restaurants = $this->get_trending_from_network($user_id);
        
        // Get personalized recommendations
        $recommendations = $this->api_client->get_suggestions($user_id, [
            'limit' => 3,
            'type' => 'all'
        ]);
        
        // Prepare email data
        $email_data = [
            'to' => $user->user_email,
            'subject' => 'Your ZipPicks Weekly Digest',
            'template' => 'weekly-digest',
            'variables' => [
                'user_name' => $user->display_name,
                'activity_count' => count($activities['data']),
                'top_activities' => array_slice($activities['data'], 0, 5),
                'new_follower_count' => $new_follower_count,
                'new_followers' => $new_follower_data,
                'trending_restaurants' => $trending_restaurants,
                'recommendations' => !is_wp_error($recommendations) ? $recommendations : [],
                'feed_url' => home_url('/feed/'),
                'settings_url' => home_url('/account/notifications/')
            ]
        ];
        
        // Send email
        $this->send_email($email_data);
    }
    
    /**
     * Send email using template
     *
     * @param array $data Email data
     * @return bool
     */
    private function send_email($data) {
        // Get template
        $template_file = $this->templates_dir . $data['template'] . '.php';
        
        if (!file_exists($template_file)) {
            // Use inline template
            $body = $this->get_inline_template($data['template'], $data['variables']);
        } else {
            // Load template file
            extract($data['variables']);
            ob_start();
            include $template_file;
            $body = ob_get_clean();
        }
        
        // Set content type
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        // Add custom headers for tracking
        $headers[] = 'X-ZipPicks-Type: ' . $data['template'];
        $headers[] = 'X-ZipPicks-User: ' . get_current_user_id();
        
        // Send email using wp_mail (which uses WP Mail SMTP)
        $sent = wp_mail($data['to'], $data['subject'], $body, $headers);
        
        // Log result
        if ($this->logger) {
            if ($sent) {
                $this->logger->info('Email sent successfully', [
                    'to' => $data['to'],
                    'template' => $data['template']
                ]);
            } else {
                $this->logger->error('Failed to send email', [
                    'to' => $data['to'],
                    'template' => $data['template']
                ]);
            }
        }
        
        return $sent;
    }
    
    /**
     * Get inline email template
     *
     * @param string $template Template name
     * @param array $vars Template variables
     * @return string
     */
    private function get_inline_template($template, $vars) {
        switch ($template) {
            case 'new-follower':
                return $this->get_new_follower_template($vars);
                
            case 'milestone':
                return $this->get_milestone_template($vars);
                
            case 'weekly-digest':
                return $this->get_weekly_digest_template($vars);
                
            default:
                return '';
        }
    }
    
    /**
     * Get new follower email template
     *
     * @param array $vars Template variables
     * @return string
     */
    private function get_new_follower_template($vars) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>New Follower</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding: 20px 0; border-bottom: 2px solid #f0f0f0; }
                .content { padding: 30px 0; }
                .follower-card { background: #f9f9f9; border-radius: 8px; padding: 20px; margin: 20px 0; }
                .follower-header { display: flex; align-items: center; margin-bottom: 15px; }
                .avatar { width: 60px; height: 60px; border-radius: 50%; margin-right: 15px; }
                .follower-info h3 { margin: 0 0 5px 0; }
                .follower-stats { display: flex; gap: 20px; margin: 15px 0; }
                .stat { text-align: center; }
                .stat-number { font-size: 24px; font-weight: bold; display: block; }
                .stat-label { font-size: 12px; color: #666; }
                .cta-button { display: inline-block; background: #ff6b6b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px 0; border-top: 1px solid #eee; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>You have a new follower! 🎉</h1>
                </div>
                
                <div class="content">
                    <p>Hi <?php echo esc_html($vars['followed_name']); ?>,</p>
                    
                    <div class="follower-card">
                        <div class="follower-header">
                            <img src="<?php echo esc_url($vars['follower_avatar']); ?>" alt="" class="avatar">
                            <div class="follower-info">
                                <h3><?php echo esc_html($vars['follower_name']); ?></h3>
                                <?php if (!empty($vars['follower_location'])): ?>
                                    <p style="margin: 0; color: #666;"><?php echo esc_html($vars['follower_location']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($vars['follower_bio'])): ?>
                            <p style="font-style: italic;">"<?php echo esc_html($vars['follower_bio']); ?>"</p>
                        <?php endif; ?>
                        
                        <div class="follower-stats">
                            <div class="stat">
                                <span class="stat-number"><?php echo number_format($vars['follower_stats']['followers_count']); ?></span>
                                <span class="stat-label">Followers</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number"><?php echo number_format($vars['follower_stats']['following_count']); ?></span>
                                <span class="stat-label">Following</span>
                            </div>
                            <?php if ($vars['mutual_count'] > 0): ?>
                                <div class="stat">
                                    <span class="stat-number"><?php echo number_format($vars['mutual_count']); ?></span>
                                    <span class="stat-label">Mutual</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($vars['is_mutual']): ?>
                            <p style="background: #e3f2fd; padding: 10px; border-radius: 5px; text-align: center;">
                                🤝 You both follow each other!
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div style="text-align: center;">
                        <a href="<?php echo esc_url($vars['follower_url']); ?>" class="cta-button">View Profile</a>
                    </div>
                </div>
                
                <div class="footer">
                    <p>
                        You're receiving this because you have email notifications enabled.<br>
                        <a href="<?php echo esc_url($vars['settings_url']); ?>">Update your notification preferences</a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get milestone email template
     *
     * @param array $vars Template variables
     * @return string
     */
    private function get_milestone_template($vars) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Milestone Reached!</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding: 40px 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; }
                .header h1 { margin: 0; font-size: 36px; }
                .milestone-number { font-size: 72px; font-weight: bold; margin: 20px 0; }
                .content { padding: 30px 0; }
                .recent-followers { margin: 30px 0; }
                .follower-list { display: flex; justify-content: center; gap: 10px; margin: 20px 0; }
                .follower-avatar { width: 48px; height: 48px; border-radius: 50%; }
                .cta-section { text-align: center; margin: 40px 0; }
                .cta-button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 0 10px; }
                .footer { text-align: center; padding: 20px 0; border-top: 1px solid #eee; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Congratulations!</h1>
                    <div class="milestone-number"><?php echo number_format($vars['follower_count']); ?></div>
                    <p style="font-size: 20px;">Followers</p>
                </div>
                
                <div class="content">
                    <p>Hey <?php echo esc_html($vars['user_name']); ?>,</p>
                    
                    <p style="font-size: 18px; text-align: center;">
                        <?php echo esc_html($vars['milestone_message']); ?>
                    </p>
                    
                    <?php if (!empty($vars['recent_followers'])): ?>
                        <div class="recent-followers">
                            <h3 style="text-align: center;">Your newest followers</h3>
                            <div class="follower-list">
                                <?php foreach ($vars['recent_followers'] as $follower): ?>
                                    <a href="<?php echo esc_url($follower['url']); ?>">
                                        <img src="<?php echo esc_url($follower['avatar']); ?>" 
                                             alt="<?php echo esc_attr($follower['name']); ?>" 
                                             class="follower-avatar"
                                             title="<?php echo esc_attr($follower['name']); ?>">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="cta-section">
                        <a href="<?php echo esc_url($vars['profile_url']); ?>" class="cta-button">View Your Profile</a>
                        <a href="<?php echo esc_url($vars['share_url']); ?>" class="cta-button" style="background: #1da1f2;">Share on Twitter</a>
                    </div>
                    
                    <p style="text-align: center; color: #666;">
                        Keep sharing your unique taste perspective with the ZipPicks community!
                    </p>
                </div>
                
                <div class="footer">
                    <p>
                        You're receiving milestone notifications from ZipPicks.<br>
                        <a href="<?php echo esc_url(home_url('/account/notifications/')); ?>">Update your preferences</a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get weekly digest email template
     *
     * @param array $vars Template variables
     * @return string
     */
    private function get_weekly_digest_template($vars) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Your ZipPicks Weekly Digest</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding: 20px 0; border-bottom: 2px solid #f0f0f0; }
                .section { margin: 30px 0; }
                .section-title { font-size: 20px; font-weight: bold; margin-bottom: 15px; color: #333; }
                .activity-item { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
                .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0; }
                .stat-card { background: #f0f0f0; padding: 20px; text-align: center; border-radius: 8px; }
                .stat-number { font-size: 28px; font-weight: bold; color: #667eea; }
                .recommendation { background: #fff; border: 1px solid #eee; padding: 15px; margin: 10px 0; border-radius: 5px; }
                .cta-button { display: inline-block; background: #ff6b6b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; }
                .footer { text-align: center; padding: 20px 0; border-top: 1px solid #eee; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Your Weekly Taste Journey</h1>
                    <p style="color: #666;">Here's what happened in your ZipPicks network this week</p>
                </div>
                
                <div class="content">
                    <!-- Stats Overview -->
                    <div class="section">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $vars['activity_count']; ?></div>
                                <div>New Activities</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $vars['new_follower_count']; ?></div>
                                <div>New Followers</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo count($vars['trending_restaurants']); ?></div>
                                <div>Trending Spots</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activities -->
                    <?php if (!empty($vars['top_activities'])): ?>
                        <div class="section">
                            <h2 class="section-title">📍 What Your Network Discovered</h2>
                            <?php foreach (array_slice($vars['top_activities'], 0, 3) as $activity): ?>
                                <div class="activity-item">
                                    <?php echo $activity['formatted_text']; ?>
                                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                        <?php echo $activity['time_ago']; ?> ago
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- New Followers -->
                    <?php if (!empty($vars['new_followers'])): ?>
                        <div class="section">
                            <h2 class="section-title">👥 New Followers</h2>
                            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                <?php foreach ($vars['new_followers'] as $follower): ?>
                                    <a href="<?php echo esc_url($follower['url']); ?>" style="text-align: center; text-decoration: none; color: #333;">
                                        <img src="<?php echo esc_url($follower['avatar']); ?>" 
                                             style="width: 48px; height: 48px; border-radius: 50%; display: block; margin: 0 auto 5px;">
                                        <div style="font-size: 12px;"><?php echo esc_html($follower['name']); ?></div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Trending Restaurants -->
                    <?php if (!empty($vars['trending_restaurants'])): ?>
                        <div class="section">
                            <h2 class="section-title">🔥 Trending in Your Network</h2>
                            <?php foreach (array_slice($vars['trending_restaurants'], 0, 3) as $restaurant): ?>
                                <div class="recommendation">
                                    <h3 style="margin: 0 0 5px 0;"><?php echo esc_html($restaurant['name']); ?></h3>
                                    <p style="margin: 5px 0; color: #666;">
                                        <?php echo esc_html($restaurant['cuisine']); ?> • 
                                        <?php echo esc_html($restaurant['city']); ?>
                                    </p>
                                    <p style="margin: 5px 0; font-size: 14px;">
                                        Favorited by <?php echo $restaurant['favorite_count']; ?> people you follow
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Call to Action -->
                    <div style="text-align: center; margin: 40px 0;">
                        <a href="<?php echo esc_url($vars['feed_url']); ?>" class="cta-button">
                            View Your Full Feed
                        </a>
                    </div>
                </div>
                
                <div class="footer">
                    <p>
                        You're receiving this weekly digest from ZipPicks.<br>
                        <a href="<?php echo esc_url($vars['settings_url']); ?>">Update your email preferences</a> | 
                        <a href="<?php echo esc_url(home_url('/')); ?>">Visit ZipPicks</a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Check if user should be notified
     *
     * @param int $user_id
     * @param string $notification_type
     * @return bool
     */
    private function should_notify_user($user_id, $notification_type) {
        // Check user preferences via API
        $prefs = get_user_meta($user_id, 'zippicks_notification_preferences', true);
        
        if (!is_array($prefs)) {
            // Default to enabled
            return true;
        }
        
        return isset($prefs[$notification_type]) ? $prefs[$notification_type] : true;
    }
    
    /**
     * Get trending restaurants from user's network
     *
     * @param int $user_id
     * @return array
     */
    private function get_trending_from_network($user_id) {
        // This would query the API for trending restaurants
        // favorited by people the user follows
        // For now, return empty array
        return [];
    }
    
    /**
     * Get share URL for milestone
     *
     * @param int $user_id
     * @param string $milestone_type
     * @return string
     */
    private function get_share_url($user_id, $milestone_type) {
        $user = get_user_by('id', $user_id);
        $count = str_replace('followers_', '', $milestone_type);
        
        $text = sprintf(
            "I just reached %s followers on @ZipPicks! Join me in discovering great food through trusted taste. %s",
            $count,
            get_author_posts_url($user_id)
        );
        
        return 'https://twitter.com/intent/tweet?text=' . urlencode($text);
    }
    
    /**
     * Process email queue
     *
     * @return void
     */
    public function process_email_queue() {
        // This would process queued emails from the API
        // For now, just log
        if ($this->logger) {
            $this->logger->info('Email queue processed');
        }
    }
}