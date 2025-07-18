<?php
/**
 * Vibe Empty State Template
 * 
 * Variables available:
 * - $message: Empty state message
 * - $options: array of rendering options
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$container_class = esc_attr($options['container_class'] ?? 'vibes-empty-state');
$show_cta = $options['show_cta'] ?? false;
$cta_text = $options['cta_text'] ?? __('Browse All Vibes', 'zippicks-vibes');
$cta_url = $options['cta_url'] ?? home_url('/vibes/');
?>

<div class="<?php echo $container_class; ?>">
    <div class="empty-state-icon">
        <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="32" cy="32" r="30" stroke="currentColor" stroke-width="2" opacity="0.2"/>
            <path d="M32 20V36M32 44H32.01" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    
    <p class="empty-state-message"><?php echo esc_html($message); ?></p>
    
    <?php if ($show_cta): ?>
        <div class="empty-state-actions">
            <a href="<?php echo esc_url($cta_url); ?>" class="button button-primary">
                <?php echo esc_html($cta_text); ?>
            </a>
        </div>
    <?php endif; ?>
    
    <?php if (current_user_can('manage_options')): ?>
        <div class="empty-state-admin-notice">
            <p class="description">
                <?php _e('Admin notice: No vibes are currently active or match your criteria.', 'zippicks-vibes'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=zippicks-vibes')); ?>">
                    <?php _e('Manage Vibes', 'zippicks-vibes'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
</div>