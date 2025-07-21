<?php
/**
 * Notifications Template Part
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 * @var array $notifications_data Notifications configuration data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access prohibited');
}

if (empty($notifications_data) || !is_array($notifications_data)) {
    return;
}

foreach ($notifications_data as $notification):
    $defaults = array(
        'type' => 'info', // success, error, warning, info
        'message' => '',
        'dismissible' => true,
        'inline' => false,
        'id' => '',
        'class' => '',
        'actions' => array()
    );
    
    $notification = wp_parse_args($notification, $defaults);
    
    if (empty($notification['message'])) {
        continue;
    }
    
    $notice_classes = array('notice', 'notice-' . $notification['type']);
    
    if ($notification['dismissible']) {
        $notice_classes[] = 'is-dismissible';
    }
    
    if ($notification['inline']) {
        $notice_classes[] = 'inline';
    }
    
    if (!empty($notification['class'])) {
        $notice_classes[] = $notification['class'];
    }
    ?>
    
    <div <?php if (!empty($notification['id'])): ?>id="<?php echo esc_attr($notification['id']); ?>"<?php endif; ?>
         class="<?php echo esc_attr(implode(' ', $notice_classes)); ?>" 
         role="alert"
         aria-live="polite">
        
        <div class="notice-content">
            <p class="notice-message">
                <?php echo wp_kses_post($notification['message']); ?>
            </p>
            
            <?php if (!empty($notification['actions'])): ?>
                <div class="notice-actions">
                    <?php foreach ($notification['actions'] as $action): ?>
                        <?php if ($action['type'] === 'link'): ?>
                            <a href="<?php echo esc_url($action['url']); ?>" 
                               class="button button-small <?php echo esc_attr($action['class'] ?? 'button-secondary'); ?>"
                               <?php if (!empty($action['target'])): ?>
                                   target="<?php echo esc_attr($action['target']); ?>"
                               <?php endif; ?>>
                                <?php echo esc_html($action['text']); ?>
                            </a>
                        <?php elseif ($action['type'] === 'button'): ?>
                            <button type="button" 
                                    class="button button-small <?php echo esc_attr($action['class'] ?? 'button-secondary'); ?>"
                                    <?php if (!empty($action['id'])): ?>
                                        id="<?php echo esc_attr($action['id']); ?>"
                                    <?php endif; ?>
                                    <?php if (!empty($action['data'])): ?>
                                        <?php foreach ($action['data'] as $key => $value): ?>
                                            data-<?php echo esc_attr($key); ?>="<?php echo esc_attr($value); ?>"
                                        <?php endforeach; ?>
                                    <?php endif; ?>>
                                <?php echo esc_html($action['text']); ?>
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($notification['dismissible']): ?>
            <button type="button" 
                    class="notice-dismiss" 
                    aria-label="<?php esc_attr_e('Dismiss this notice', 'zippicks-vibes'); ?>">
                <span class="screen-reader-text">
                    <?php esc_html_e('Dismiss this notice.', 'zippicks-vibes'); ?>
                </span>
            </button>
        <?php endif; ?>
    </div>
    
<?php endforeach; ?>