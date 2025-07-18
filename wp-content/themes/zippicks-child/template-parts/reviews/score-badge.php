<?php
/**
 * Score Badge Template
 * template-parts/reviews/score-badge.php
 * 
 * HTML template for ZipScore badge display
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Extract template variables
$business_id = $args['business_id'] ?? get_the_ID();
$score_data = $args['score_data'] ?? array();
$size = $args['size'] ?? 'medium';
$vertical = $args['vertical'] ?? 'restaurant';

// Determine size classes
$size_class = 'zipscore-badge-' . $size;
$display_score = $score_data['display_score'] ?? 'N/A';
$score_class = $score_data['score_class'] ?? 'no-score';
$total_reviews = $score_data['total_reviews'] ?? 0;
$confidence = $score_data['confidence'] ?? 'insufficient';
?>

<div class="zipscore-badge <?php echo esc_attr($size_class); ?> <?php echo esc_attr($score_class); ?>" 
     data-business-id="<?php echo esc_attr($business_id); ?>" 
     data-score="<?php echo esc_attr($score_data['raw_score'] ?? 0); ?>"
     data-confidence="<?php echo esc_attr($confidence); ?>">
     
    <!-- Score Display -->
    <div class="score-display">
        <span class="score-number"><?php echo esc_html($display_score); ?></span>
        <?php if ($size !== 'small'): ?>
        <span class="score-label"><?php _e('ZipScore', 'zippicks-reviews'); ?></span>
        <?php endif; ?>
    </div>
    
    <!-- Review Count (for medium/large sizes) -->
    <?php if ($size !== 'small' && $total_reviews > 0): ?>
    <div class="review-count">
        <span class="count-number"><?php echo esc_html($total_reviews); ?></span>
        <span class="count-label">
            <?php echo sprintf(_n('%d review', '%d reviews', $total_reviews, 'zippicks-reviews'), $total_reviews); ?>
        </span>
    </div>
    <?php endif; ?>
    
    <!-- Confidence Indicator (for large size) -->
    <?php if ($size === 'large' && !empty($score_data['confidence'])): ?>
    <div class="confidence-indicator">
        <span class="confidence-level confidence-<?php echo esc_attr($confidence); ?>">
            <?php echo esc_html(zippicks_get_confidence_text($confidence)); ?>
        </span>
    </div>
    <?php endif; ?>
    
    <!-- Tooltip Content (hidden) -->
    <div class="badge-tooltip" style="display: none;">
        <div class="tooltip-content">
            <h6><?php _e('ZipScore Details', 'zippicks-reviews'); ?></h6>
            
            <?php if (!empty($score_data['pillar_scores'])): ?>
            <div class="tooltip-pillars">
                <?php foreach ($score_data['pillar_scores'] as $pillar_slug => $pillar_data): ?>
                <div class="tooltip-pillar">
                    <span class="pillar-name"><?php echo esc_html($pillar_data['name']); ?></span>
                    <span class="pillar-score"><?php echo number_format($pillar_data['score'], 1); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="tooltip-breakdown">
                <div class="breakdown-item">
                    <span class="label"><?php _e('Critics:', 'zippicks-reviews'); ?></span>
                    <span class="value"><?php echo esc_html($score_data['critic_reviews'] ?? 0); ?></span>
                </div>
                <div class="breakdown-item">
                    <span class="label"><?php _e('Zippers:', 'zippicks-reviews'); ?></span>
                    <span class="value"><?php echo esc_html($score_data['zipper_reviews'] ?? 0); ?></span>
                </div>
            </div>
            
            <div class="tooltip-footer">
                <small><?php echo esc_html(zippicks_get_confidence_text($confidence)); ?></small>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeBadgeTooltip();
    
    function initializeBadgeTooltip() {
        const badge = document.querySelector('[data-business-id="<?php echo esc_js($business_id); ?>"]');
        const tooltip = badge?.querySelector('.badge-tooltip');
        
        if (!badge || !tooltip) return;
        
        let tooltipVisible = false;
        let hideTimeout;
        
        badge.addEventListener('mouseenter', function() {
            clearTimeout(hideTimeout);
            showTooltip();
        });
        
        badge.addEventListener('mouseleave', function() {
            hideTimeout = setTimeout(hideTooltip, 300);
        });
        
        tooltip.addEventListener('mouseenter', function() {
            clearTimeout(hideTimeout);
        });
        
        tooltip.addEventListener('mouseleave', function() {
            hideTimeout = setTimeout(hideTooltip, 100);
        });
        
        function showTooltip() {
            if (tooltipVisible) return;
            
            tooltip.style.display = 'block';
            tooltip.style.opacity = '0';
            tooltip.style.transform = 'translateY(10px)';
            
            // Position tooltip
            positionTooltip();
            
            // Animate in
            requestAnimationFrame(() => {
                tooltip.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
                tooltip.style.opacity = '1';
                tooltip.style.transform = 'translateY(0)';
            });
            
            tooltipVisible = true;
        }
        
        function hideTooltip() {
            if (!tooltipVisible) return;
            
            tooltip.style.opacity = '0';
            tooltip.style.transform = 'translateY(10px)';
            
            setTimeout(() => {
                tooltip.style.display = 'none';
                tooltipVisible = false;
            }, 200);
        }
        
        function positionTooltip() {
            const badgeRect = badge.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            
            // Default position (below badge)
            let top = badgeRect.bottom + 10;
            let left = badgeRect.left + (badgeRect.width / 2) - (tooltipRect.width / 2);
            
            // Adjust if tooltip goes off screen
            if (left < 10) {
                left = 10;
            } else if (left + tooltipRect.width > viewportWidth - 10) {
                left = viewportWidth - tooltipRect.width - 10;
            }
            
            // If tooltip goes below viewport, show above badge
            if (top + tooltipRect.height > viewportHeight - 10) {
                top = badgeRect.top - tooltipRect.height - 10;
            }
            
            tooltip.style.position = 'fixed';
            tooltip.style.top = top + 'px';
            tooltip.style.left = left + 'px';
            tooltip.style.zIndex = '9999';
        }
    }
});
</script>