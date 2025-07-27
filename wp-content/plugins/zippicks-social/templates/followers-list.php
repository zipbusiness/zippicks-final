<?php
/**
 * Followers list template
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 * 
 * Available variables:
 * - $followers: Array of follower data
 * - $atts: Shortcode attributes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="zps-followers-list">
    <?php foreach ($followers as $follower): ?>
        <div class="zps-follower-item">
            <?php if ($atts['show_avatar'] === 'true'): ?>
                <div class="zps-follower-avatar">
                    <?php if ($atts['link_to_profile'] === 'true'): ?>
                        <a href="<?php echo esc_url(get_author_posts_url($follower['follower_id'])); ?>">
                            <?php echo get_avatar($follower['follower_id'], 48); ?>
                        </a>
                    <?php else: ?>
                        <?php echo get_avatar($follower['follower_id'], 48); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="zps-follower-info">
                <div class="zps-follower-name">
                    <?php if ($atts['link_to_profile'] === 'true'): ?>
                        <a href="<?php echo esc_url(get_author_posts_url($follower['follower_id'])); ?>">
                            <?php echo esc_html($follower['display_name']); ?>
                        </a>
                    <?php else: ?>
                        <?php echo esc_html($follower['display_name']); ?>
                    <?php endif; ?>
                </div>
                
                <?php if (is_user_logged_in()): ?>
                    <div class="zps-follower-actions">
                        <?php 
                        echo zippicks_social_follow_button($follower['follower_id'], 'user', [
                            'class' => 'zps-follow-button zps-size-small',
                            'show_count' => false
                        ]); 
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>