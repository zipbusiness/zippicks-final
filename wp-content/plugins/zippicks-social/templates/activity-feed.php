<?php
/**
 * Activity feed template - API Powered
 *
 * @package ZipPicks_Social
 * @since 2.0.0
 * 
 * Available variables:
 * - $atts: Shortcode attributes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get activity stream instance
$activity_stream = new ZipPicks_Social_Activity_Stream();

// Prepare feed arguments
$user_id = isset($atts['user_id']) ? intval($atts['user_id']) : get_current_user_id();
$feed_type = isset($atts['type']) ? $atts['type'] : 'personal'; // personal, public, following

// Get initial feed data
$feed_args = [
    'page' => 1,
    'per_page' => isset($atts['per_page']) ? intval($atts['per_page']) : 20,
    'include_self' => $feed_type !== 'following'
];

$feed_data = $activity_stream->get_feed($user_id, $feed_args);
$activities = $feed_data['activities'];
$has_more = $feed_data['has_more'];

// Check if user is logged in for personal feeds
$require_login = ($feed_type !== 'public' && !is_user_logged_in());
?>

<div class="zps-activity-feed" 
     data-user-id="<?php echo esc_attr($user_id); ?>"
     data-feed-type="<?php echo esc_attr($feed_type); ?>"
     data-per-page="<?php echo esc_attr($feed_args['per_page']); ?>"
     data-page="1">
    
    <?php if ($require_login): ?>
        <div class="zps-feed-login-required">
            <p><?php _e('Please log in to view your activity feed.', 'zippicks-social'); ?></p>
            <a href="<?php echo wp_login_url(get_permalink()); ?>" class="zps-button">
                <?php _e('Log In', 'zippicks-social'); ?>
            </a>
        </div>
    <?php else: ?>
        
        <?php if ($feed_type === 'personal'): ?>
            <div class="zps-feed-header">
                <h3><?php _e('Your Activity Feed', 'zippicks-social'); ?></h3>
                <p class="zps-feed-description">
                    <?php _e('See what the people, critics, and businesses you follow are up to.', 'zippicks-social'); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="zps-feed-content">
            <?php if (!empty($activities)): ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="zps-activity-item" data-activity-id="<?php echo esc_attr($activity['id']); ?>">
                        <div class="zps-activity-avatar">
                            <?php if (!empty($activity['actor']['avatar'])): ?>
                                <img src="<?php echo esc_url($activity['actor']['avatar']); ?>" 
                                     alt="<?php echo esc_attr($activity['actor']['name']); ?>"
                                     class="zps-avatar">
                            <?php else: ?>
                                <div class="zps-avatar-placeholder">
                                    <span class="dashicons dashicons-admin-users"></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="zps-activity-content">
                            <div class="zps-activity-header">
                                <span class="zps-activity-icon dashicons dashicons-<?php echo esc_attr($activity['icon']); ?>"></span>
                                <div class="zps-activity-text">
                                    <?php echo wp_kses_post($activity['formatted_text']); ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($activity['metadata'])): ?>
                                <div class="zps-activity-meta">
                                    <?php
                                    // Display activity-specific metadata
                                    switch ($activity['type']) {
                                        case 'review_restaurant':
                                            if (!empty($activity['metadata']['rating'])):
                                                ?>
                                                <div class="zps-rating">
                                                    <?php echo str_repeat('★', intval($activity['metadata']['rating'])); ?>
                                                </div>
                                                <?php
                                            endif;
                                            break;
                                            
                                        case 'favorite_restaurant':
                                            if (!empty($activity['object']['vibes'])):
                                                ?>
                                                <div class="zps-vibes">
                                                    <?php foreach (array_slice($activity['object']['vibes'], 0, 3) as $vibe): ?>
                                                        <span class="zps-vibe-tag"><?php echo esc_html($vibe); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php
                                            endif;
                                            break;
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="zps-activity-footer">
                                <time class="zps-activity-time" datetime="<?php echo date('c', $activity['timestamp']); ?>">
                                    <?php echo esc_html($activity['time_ago']); ?> ago
                                </time>
                                
                                <?php if (!empty($activity['url'])): ?>
                                    <a href="<?php echo esc_url($activity['url']); ?>" class="zps-activity-link">
                                        <?php _e('View', 'zippicks-social'); ?> →
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="zps-feed-empty">
                    <div class="zps-empty-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <h4><?php _e('No activity yet', 'zippicks-social'); ?></h4>
                    <p><?php _e('Follow users, critics, and businesses to see their updates here!', 'zippicks-social'); ?></p>
                    
                    <?php if (is_user_logged_in()): ?>
                        <a href="<?php echo esc_url(home_url('/discover/')); ?>" class="zps-button">
                            <?php _e('Discover People to Follow', 'zippicks-social'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($has_more): ?>
            <div class="zps-feed-loader">
                <button class="zps-load-more" data-loading-text="<?php esc_attr_e('Loading...', 'zippicks-social'); ?>">
                    <?php _e('Load More', 'zippicks-social'); ?>
                </button>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<script type="text/template" id="zps-activity-template">
    <div class="zps-activity-item" data-activity-id="{{id}}">
        <div class="zps-activity-avatar">
            {{#if actor.avatar}}
                <img src="{{actor.avatar}}" alt="{{actor.name}}" class="zps-avatar">
            {{else}}
                <div class="zps-avatar-placeholder">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
            {{/if}}
        </div>
        
        <div class="zps-activity-content">
            <div class="zps-activity-header">
                <span class="zps-activity-icon dashicons dashicons-{{icon}}"></span>
                <div class="zps-activity-text">{{{formatted_text}}}</div>
            </div>
            
            <div class="zps-activity-footer">
                <time class="zps-activity-time" datetime="{{timestamp}}">{{time_ago}} ago</time>
                {{#if url}}
                    <a href="{{url}}" class="zps-activity-link"><?php _e('View', 'zippicks-social'); ?> →</a>
                {{/if}}
            </div>
        </div>
    </div>
</script>