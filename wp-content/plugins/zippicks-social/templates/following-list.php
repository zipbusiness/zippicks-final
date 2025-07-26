<?php
/**
 * Following list template
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 * 
 * Available variables:
 * - $following: Array of following data
 * - $atts: Shortcode attributes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="zps-following-list">
    <?php foreach ($following as $item): ?>
        <?php
        // Get entity details based on type
        $entity_name = '';
        $entity_url = '';
        $entity_avatar = '';
        
        switch ($item['followed_type']) {
            case 'user':
            case 'critic':
                $user = get_userdata($item['followed_id']);
                if ($user) {
                    $entity_name = $user->display_name;
                    $entity_url = get_author_posts_url($item['followed_id']);
                    $entity_avatar = get_avatar($item['followed_id'], 48);
                }
                break;
                
            case 'business':
                $post = get_post($item['followed_id']);
                if ($post) {
                    $entity_name = $post->post_title;
                    $entity_url = get_permalink($item['followed_id']);
                    $entity_avatar = get_the_post_thumbnail($item['followed_id'], [48, 48]);
                }
                break;
                
            case 'list':
                $post = get_post($item['followed_id']);
                if ($post) {
                    $entity_name = $post->post_title;
                    $entity_url = get_permalink($item['followed_id']);
                }
                break;
        }
        
        if (empty($entity_name)) {
            continue;
        }
        ?>
        
        <div class="zps-following-item zps-type-<?php echo esc_attr($item['followed_type']); ?>">
            <?php if ($entity_avatar): ?>
                <div class="zps-following-avatar">
                    <a href="<?php echo esc_url($entity_url); ?>">
                        <?php echo $entity_avatar; ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="zps-following-info">
                <div class="zps-following-name">
                    <a href="<?php echo esc_url($entity_url); ?>">
                        <?php echo esc_html($entity_name); ?>
                    </a>
                    <?php if ($atts['show_type'] === 'true'): ?>
                        <span class="zps-entity-type"><?php echo esc_html(ucfirst($item['followed_type'])); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if (is_user_logged_in() && get_current_user_id() == $atts['user_id']): ?>
                    <div class="zps-following-actions">
                        <?php 
                        echo zippicks_social_follow_button($item['followed_id'], $item['followed_type'], [
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