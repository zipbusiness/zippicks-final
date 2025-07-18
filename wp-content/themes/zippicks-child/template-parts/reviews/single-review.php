<?php
/**
 * Single Review Template
 * template-parts/reviews/single-review.php
 * 
 * HTML template for individual review display
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Extract template variables
$review = $args['review'] ?? null;
$strings = $args['strings'] ?? array();

if (!$review) {
    return;
}
?>

<div class="single-review" data-review-id="<?php echo esc_attr($review->id); ?>" data-reviewer-role="<?php echo esc_attr($review->reviewer_role); ?>">
    
    <!-- Review Header -->
    <div class="review-header">
        <div class="reviewer-info">
            <div class="reviewer-avatar">
                <?php echo esc_html($review->reviewer_initial ?? strtoupper(substr($review->display_name, 0, 1))); ?>
            </div>
            <div class="reviewer-details">
                <div class="reviewer-name"><?php echo esc_html($review->display_name); ?></div>
                <div class="reviewer-meta">
                    <span class="reviewer-role-badge role-<?php echo esc_attr($review->reviewer_role); ?>">
                        <?php echo ucfirst($review->reviewer_role); ?>
                    </span>
                    <span class="review-date"><?php echo esc_html($review->formatted_date ?? human_time_diff(strtotime($review->created_at), current_time('timestamp')) . ' ago'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Review Actions (for logged-in users) -->
        <?php if (is_user_logged_in()): ?>
        <div class="review-actions">
            <?php if (current_user_can('zippicks_manage_reviews') || get_current_user_id() == $review->reviewer_id): ?>
            <button class="review-action-btn" data-action="edit" title="<?php esc_attr_e('Edit Review', 'zippicks-reviews'); ?>">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708L10.5 8.207l-3-3L12.146.146zM11.207 9l-3-3L2.5 11.707V14.5a.5.5 0 0 0 .5.5h2.793L11.207 9z"/>
                </svg>
            </button>
            <?php endif; ?>
            
            <button class="review-action-btn" data-action="report" title="<?php esc_attr_e('Report Review', 'zippicks-reviews'); ?>">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.146.146 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.163.163 0 0 1-.054.06.116.116 0 0 1-.066.017H1.146a.115.115 0 0 1-.066-.017.163.163 0 0 1-.054-.06.176.176 0 0 1 .002-.183L7.884 2.073a.147.147 0 0 1 .054-.057zm1.044-.45a1.13 1.13 0 0 0-2.008 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566z"/>
                    <path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995z"/>
                </svg>
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Review Scores -->
    <?php if (!empty($review->scores)): ?>
    <div class="review-scores-mini">
        <?php foreach ($review->scores as $score): ?>
            <span class="score-pill score-<?php echo esc_attr(zippicks_get_score_class($score->score)); ?>" data-pillar="<?php echo esc_attr($score->pillar_slug); ?>">
                <span class="pillar-name"><?php echo esc_html(zippicks_format_pillar_name($score->pillar_slug)); ?></span>
                <span class="pillar-score"><?php echo esc_html($score->score); ?>/10</span>
            </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Review Comment -->
    <?php if (!empty($review->comment)): ?>
    <div class="review-comment">
        <p><?php echo esc_html($review->comment); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Review Footer -->
    <div class="review-footer">
        <div class="review-helpful">
            <button class="helpful-btn" data-review-id="<?php echo esc_attr($review->id); ?>" data-action="helpful">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8.864.046C7.908-.193 7.02.53 6.956 1.466c-.072 1.051-.23 2.016-.428 2.59-.125.36-.479 1.013-1.04 1.639-.557.623-1.282 1.178-2.131 1.41C2.685 7.288 2 7.87 2 8.72v4.001c0 .845.682 1.464 1.448 1.545 1.07.114 1.564.415 2.068.723l.048.03c.272.165.578.348.97.484.397.136.861.217 1.466.217h3.5c.937 0 1.599-.477 1.934-1.064a1.86 1.86 0 0 0 .254-.912c0-.152-.023-.312-.077-.464.201-.263.38-.578.488-.901.11-.33.172-.762.004-1.149.069-.13.12-.269.159-.403.077-.27.113-.568.113-.857 0-.288-.036-.585-.113-.856a2.144 2.144 0 0 0-.138-.362 1.9 1.9 0 0 0 .234-1.734c-.206-.592-.682-1.1-1.2-1.272-.847-.282-1.803-.276-2.516-.211a9.84 9.84 0 0 0-.443.05 9.365 9.365 0 0 0-.062-4.509A1.38 1.38 0 0 0 9.125.111L8.864.046zM11.5 14.721H8c-.51 0-.863-.069-1.14-.164-.281-.097-.506-.228-.776-.393l-.04-.024c-.555-.339-1.198-.731-2.49-.868-.333-.036-.554-.29-.554-.55V8.72c0-.254.226-.543.62-.65 1.095-.3 1.977-.996 2.614-1.708.635-.71 1.064-1.475 1.238-1.978.243-.7.407-1.768.482-2.85.025-.362.36-.594.667-.518l.262.066c.16.04.258.143.288.255a8.34 8.34 0 0 1-.145 4.725.5.5 0 0 0 .595.644l.003-.001.014-.003.058-.014a8.908 8.908 0 0 1 1.036-.157c.663-.06 1.457-.054 2.11.164.175.058.45.3.57.65.107.308.087.67-.266 1.022l-.353.353.353.354c.043.043.105.141.154.315.048.167.075.37.075.581 0 .212-.027.414-.075.582-.05.174-.111.272-.154.315l-.353.353.353.354c.047.047.109.177.005.488a2.224 2.224 0 0 1-.505.805l-.353.353.353.354c.006.005.041.05.041.17a.866.866 0 0 1-.121.416c-.165.288-.503.56-1.066.56z"/>
                </svg>
                <span class="helpful-text"><?php _e('Helpful', 'zippicks-reviews'); ?></span>
                <span class="helpful-count" data-count="0">0</span>
            </button>
        </div>
        
        <div class="review-timestamp">
            <time datetime="<?php echo esc_attr(date('c', strtotime($review->created_at))); ?>">
                <?php echo esc_html($review->formatted_date ?? human_time_diff(strtotime($review->created_at), current_time('timestamp')) . ' ago'); ?>
            </time>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize review interactions for this review
    initializeReviewInteractions();
    
    function initializeReviewInteractions() {
        const reviewElement = document.querySelector('[data-review-id="<?php echo esc_js($review->id); ?>"]');
        if (!reviewElement) return;
        
        // Handle helpful button
        const helpfulBtn = reviewElement.querySelector('.helpful-btn');
        if (helpfulBtn) {
            helpfulBtn.addEventListener('click', function() {
                handleHelpfulClick(this);
            });
        }
        
        // Handle action buttons
        const actionBtns = reviewElement.querySelectorAll('.review-action-btn');
        actionBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                handleActionClick(this);
            });
        });
    }
    
    function handleHelpfulClick(btn) {
        const reviewId = btn.dataset.reviewId;
        const countElement = btn.querySelector('.helpful-count');
        const currentCount = parseInt(countElement.dataset.count);
        
        // Optimistic update
        btn.classList.add('marked-helpful');
        countElement.textContent = currentCount + 1;
        countElement.dataset.count = currentCount + 1;
        btn.disabled = true;
        
        // AJAX call would go here to record the helpful vote
        // For now, just prevent multiple clicks
    }
    
    function handleActionClick(btn) {
        const action = btn.dataset.action;
        const reviewId = btn.closest('.single-review').dataset.reviewId;
        
        switch(action) {
            case 'edit':
                // Open edit modal or redirect to edit page
                console.log('Edit review:', reviewId);
                break;
                
            case 'report':
                // Open report modal
                if (confirm('<?php esc_js(_e('Report this review as inappropriate?', 'zippicks-reviews')); ?>')) {
                    // AJAX call to report review
                    console.log('Report review:', reviewId);
                }
                break;
        }
    }
});
</script>