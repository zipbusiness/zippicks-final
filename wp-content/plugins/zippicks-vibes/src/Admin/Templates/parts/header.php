<?php
/**
 * Admin Header Template Part
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 * @var array $header_data Header configuration data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access prohibited');
}

// Default header data
$defaults = array(
    'title' => __('ZipPicks Vibes', 'zippicks-vibes'),
    'actions' => array(),
    'show_breadcrumbs' => false,
    'breadcrumbs' => array(),
    'subtitle' => '',
    'icon' => 'dashicons-heart'
);

$header_data = wp_parse_args($header_data ?? array(), $defaults);
?>

<div class="zippicks-header">
    <div class="header-main">
        <h1 id="main-heading" class="header-title">
            <?php if (!empty($header_data['icon'])): ?>
                <span class="icon <?php echo esc_attr($header_data['icon']); ?>" aria-hidden="true"></span>
            <?php endif; ?>
            <?php echo esc_html($header_data['title']); ?>
            <?php if (!empty($header_data['subtitle'])): ?>
                <small class="header-subtitle"><?php echo esc_html($header_data['subtitle']); ?></small>
            <?php endif; ?>
        </h1>
        
        <?php if ($header_data['show_breadcrumbs'] && !empty($header_data['breadcrumbs'])): ?>
            <nav aria-label="<?php esc_attr_e('Breadcrumb navigation', 'zippicks-vibes'); ?>" class="breadcrumbs">
                <ol class="breadcrumb-list">
                    <?php foreach ($header_data['breadcrumbs'] as $index => $crumb): ?>
                        <li class="breadcrumb-item">
                            <?php if (!empty($crumb['url'])): ?>
                                <a href="<?php echo esc_url($crumb['url']); ?>" 
                                   class="breadcrumb-link">
                                    <?php echo esc_html($crumb['title']); ?>
                                </a>
                            <?php else: ?>
                                <span class="breadcrumb-current" aria-current="page">
                                    <?php echo esc_html($crumb['title']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($index < count($header_data['breadcrumbs']) - 1): ?>
                                <span class="breadcrumb-separator" aria-hidden="true">/</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($header_data['actions'])): ?>
        <nav aria-labelledby="main-heading" class="header-actions">
            <?php foreach ($header_data['actions'] as $action): ?>
                <?php if ($action['type'] === 'link'): ?>
                    <a href="<?php echo esc_url($action['url']); ?>" 
                       class="button <?php echo esc_attr($action['class'] ?? 'button-primary'); ?>"
                       <?php if (!empty($action['aria_label'])): ?>
                           aria-label="<?php echo esc_attr($action['aria_label']); ?>"
                       <?php endif; ?>
                       <?php if (!empty($action['target'])): ?>
                           target="<?php echo esc_attr($action['target']); ?>"
                       <?php endif; ?>>
                        <?php if (!empty($action['icon'])): ?>
                            <span class="icon <?php echo esc_attr($action['icon']); ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <?php echo esc_html($action['text']); ?>
                    </a>
                <?php elseif ($action['type'] === 'button'): ?>
                    <button type="button" 
                            class="button <?php echo esc_attr($action['class'] ?? 'button-secondary'); ?>"
                            <?php if (!empty($action['id'])): ?>
                                id="<?php echo esc_attr($action['id']); ?>"
                            <?php endif; ?>
                            <?php if (!empty($action['aria_label'])): ?>
                                aria-label="<?php echo esc_attr($action['aria_label']); ?>"
                            <?php endif; ?>
                            <?php if (!empty($action['data'])): ?>
                                <?php foreach ($action['data'] as $key => $value): ?>
                                    data-<?php echo esc_attr($key); ?>="<?php echo esc_attr($value); ?>"
                                <?php endforeach; ?>
                            <?php endif; ?>>
                        <?php if (!empty($action['icon'])): ?>
                            <span class="icon <?php echo esc_attr($action['icon']); ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <?php echo esc_html($action['text']); ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</div>