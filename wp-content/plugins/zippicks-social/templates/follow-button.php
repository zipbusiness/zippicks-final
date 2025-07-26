<?php
/**
 * Follow button template
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 * 
 * Available variables:
 * - $entity_id: Entity ID
 * - $entity_type: Entity type (user, critic, business, list)
 * - $is_following: Whether currently following
 * - $followers_count: Number of followers
 * - $args: Additional arguments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$button_text = $is_following ? __('Following', 'zippicks-social') : __('Follow', 'zippicks-social');
$button_class = $args['class'] . ' ' . ($is_following ? 'zps-following' : 'zps-not-following');
$button_action = $is_following ? 'unfollow' : 'follow';
?>

<button type="button" 
        class="<?php echo esc_attr($button_class); ?>" 
        data-entity-id="<?php echo esc_attr($entity_id); ?>"
        data-entity-type="<?php echo esc_attr($entity_type); ?>"
        data-action="<?php echo esc_attr($button_action); ?>"
        data-nonce="<?php echo wp_create_nonce('zippicks_social_follow'); ?>">
    <span class="zps-button-icon"></span>
    <span class="zps-button-text"><?php echo esc_html($button_text); ?></span>
    <?php if ($args['show_count'] && $followers_count > 0): ?>
        <span class="zps-followers-count"><?php echo number_format_i18n($followers_count); ?></span>
    <?php endif; ?>
</button>