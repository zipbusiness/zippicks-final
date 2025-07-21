<?php
/**
 * Modal Template Part
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 * @var array $modal_data Modal configuration data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access prohibited');
}

// Default modal configuration
$defaults = array(
    'id' => 'modal',
    'title' => __('Modal Dialog', 'zippicks-vibes'),
    'content' => '',
    'size' => 'medium', // small, medium, large, full
    'closable' => true,
    'backdrop_close' => true,
    'footer_actions' => array(),
    'form' => false,
    'form_action' => '',
    'form_method' => 'post'
);

$modal_data = wp_parse_args($modal_data ?? array(), $defaults);

$modal_classes = array('modal');
$modal_classes[] = 'modal-' . $modal_data['size'];
if (!$modal_data['closable']) {
    $modal_classes[] = 'modal-not-closable';
}
?>

<div id="<?php echo esc_attr($modal_data['id']); ?>" 
     class="<?php echo esc_attr(implode(' ', $modal_classes)); ?>" 
     role="dialog" 
     aria-modal="true"
     aria-labelledby="<?php echo esc_attr($modal_data['id']); ?>-title"
     aria-hidden="true"
     tabindex="-1">
     
    <div class="modal-overlay" 
         <?php if ($modal_data['backdrop_close']): ?>
             role="button" 
             aria-label="<?php esc_attr_e('Close modal', 'zippicks-vibes'); ?>"
             tabindex="0"
         <?php endif; ?>>
    </div>
    
    <div class="modal-container">
        <div class="modal-content">
            
            <header class="modal-header">
                <h2 id="<?php echo esc_attr($modal_data['id']); ?>-title" class="modal-title">
                    <?php echo esc_html($modal_data['title']); ?>
                </h2>
                
                <?php if ($modal_data['closable']): ?>
                    <button type="button" 
                            class="modal-close" 
                            aria-label="<?php esc_attr_e('Close modal dialog', 'zippicks-vibes'); ?>">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>
            </header>
            
            <?php if ($modal_data['form']): ?>
                <form method="<?php echo esc_attr($modal_data['form_method']); ?>" 
                      <?php if (!empty($modal_data['form_action'])): ?>
                          action="<?php echo esc_url($modal_data['form_action']); ?>"
                      <?php endif; ?>
                      id="<?php echo esc_attr($modal_data['id']); ?>-form"
                      class="modal-form">
                    
                    <?php wp_nonce_field('zippicks_vibes_modal_' . $modal_data['id']); ?>
            <?php endif; ?>
            
            <div class="modal-body">
                <?php if (!empty($modal_data['content'])): ?>
                    <?php if (is_callable($modal_data['content'])): ?>
                        <?php call_user_func($modal_data['content'], $modal_data); ?>
                    <?php elseif (is_string($modal_data['content']) && file_exists($modal_data['content'])): ?>
                        <?php include $modal_data['content']; ?>
                    <?php else: ?>
                        <?php echo wp_kses_post($modal_data['content']); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($modal_data['footer_actions'])): ?>
                <footer class="modal-footer">
                    <div class="modal-actions">
                        <?php foreach ($modal_data['footer_actions'] as $action): ?>
                            <?php if ($action['type'] === 'button'): ?>
                                <button type="<?php echo esc_attr($action['button_type'] ?? 'button'); ?>" 
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
                                        <span class="<?php echo esc_attr($action['icon']); ?>" aria-hidden="true"></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($action['text']); ?>
                                </button>
                            <?php elseif ($action['type'] === 'link'): ?>
                                <a href="<?php echo esc_url($action['url']); ?>" 
                                   class="button <?php echo esc_attr($action['class'] ?? 'button-secondary'); ?>"
                                   <?php if (!empty($action['target'])): ?>
                                       target="<?php echo esc_attr($action['target']); ?>"
                                   <?php endif; ?>
                                   <?php if (!empty($action['aria_label'])): ?>
                                       aria-label="<?php echo esc_attr($action['aria_label']); ?>"
                                   <?php endif; ?>>
                                    <?php if (!empty($action['icon'])): ?>
                                        <span class="<?php echo esc_attr($action['icon']); ?>" aria-hidden="true"></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($action['text']); ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </footer>
            <?php endif; ?>
            
            <?php if ($modal_data['form']): ?>
                </form>
            <?php endif; ?>
            
        </div>
    </div>
</div>