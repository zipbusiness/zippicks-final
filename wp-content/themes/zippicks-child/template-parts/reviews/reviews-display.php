<?php
/**
 * Reviews Display Template
 * template-parts/reviews/reviews-display.php
 * 
 * HTML template for complete reviews display section
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Extract template variables
$business_id = $args['business_id'] ?? get_the_ID();
$business_data = $args['business_data'] ?? array();
$score_data = $args['score_data'] ?? array();
$reviews_data = $args['reviews_data'] ?? array();
$vertical = $args['vertical'] ?? 'restaurant';
$has_reviews = $args['has_reviews'] ?? false;
$strings = $args['strings'] ?? array();
?>

<div class="zippicks-reviews-container" data-business-id="<?php echo esc_attr($business_id); ?>">
    
    <!-- ZipScore Header Section -->
    <div class="zippicks-score-header">
        <div class="zipscore-main">
            <div class="zipscore-number <?php echo esc_attr($score_data['score_class'] ?? 'no-score'); ?>">
                <?php echo esc_html($score_data['display_score'] ?? 'N/A'); ?>
            </div>
            <div class="zipscore-label">
                <h3><?php echo esc_html($strings['zipscore'] ?? __('ZipScore', 'zippicks-reviews')); ?></h3>
                <p class="confidence-<?php echo esc_attr($score_data['confidence'] ?? 'insufficient'); ?>">
                    <?php echo esc_html(zippicks_get_confidence_text($score_data['confidence'] ?? 'insufficient')); ?>
                </p>
            </div>
        </div>
        
        <div class="review-summary">
            <div class="review-count">
                <span class="total"><?php echo esc_html($score_data['total_reviews'] ?? 0); ?></span>
                <span class="label"><?php echo esc_html($strings['total_reviews'] ?? __('Total Reviews', 'zippicks-reviews')); ?></span>
            </div>
            <div class="review-breakdown">
                <div class="critic-count">
                    <span><?php echo esc_html($score_data['critic_reviews'] ?? 0); ?></span> 
                    <?php echo esc_html($strings['critics'] ?? __('Critics', 'zippicks-reviews')); ?>
                </div>
                <div class="zipper-count">
                    <span><?php echo esc_html($score_data['zipper_reviews'] ?? 0); ?></span> 
                    <?php echo esc_html($strings['zippers'] ?? __('Zippers', 'zippicks-reviews')); ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pillar Scores Breakdown -->
    <?php if (!empty($score_data['pillar_scores'])): ?>
    <div class="zippicks-pillar-scores">
        <h4><?php echo esc_html($strings['score_breakdown'] ?? __('Score Breakdown', 'zippicks-reviews')); ?></h4>
        <div class="pillars-grid">
            <?php foreach ($score_data['pillar_scores'] as $pillar_slug => $pillar_data): ?>
            <div class="pillar-item" data-pillar="<?php echo esc_attr($pillar_slug); ?>">
                <div class="pillar-header">
                    <span class="pillar-name"><?php echo esc_html($pillar_data['name']); ?></span>
                    <span class="pillar-score score-<?php echo esc_attr(zippicks_get_score_class($pillar_data['score'])); ?>">
                        <?php echo number_format($pillar_data['score'], 1); ?>
                    </span>
                </div>
                <div class="pillar-details">
                    <?php if ($pillar_data['critic_avg']): ?>
                    <span class="critic-score">
                        <?php echo sprintf(__('Critics: %s', 'zippicks-reviews'), number_format($pillar_data['critic_avg'], 1)); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($pillar_data['zipper_avg']): ?>
                    <span class="zipper-score">
                        <?php echo sprintf(__('Zippers: %s', 'zippicks-reviews'), number_format($pillar_data['zipper_avg'], 1)); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="pillar-counts">
                    <small>
                        <?php 
                        $total_count = $pillar_data['critic_count'] + $pillar_data['zipper_count'];
                        echo sprintf(_n('%d review', '%d reviews', $total_count, 'zippicks-reviews'), $total_count);
                        ?>
                    </small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Individual Reviews Section -->
    <div class="zippicks-individual-reviews">
        <h4><?php echo esc_html($strings['reviews'] ?? __('Reviews', 'zippicks-reviews')); ?></h4>
        
        <?php if ($has_reviews): ?>
        
            <!-- Critic Reviews -->
            <?php if (!empty($reviews_data['critic_reviews'])): ?>
            <div class="reviews-section critic-reviews" data-role="critic">
                <h5 class="section-title">
                    <span class="role-badge critic"><?php echo esc_html($strings['critics'] ?? __('Critics', 'zippicks-reviews')); ?></span>
                    <span class="count">(<?php echo count($reviews_data['critic_reviews']); ?>)</span>
                </h5>
                <div class="reviews-list">
                    <?php foreach ($reviews_data['critic_reviews'] as $review): ?>
                        <?php get_template_part('template-parts/reviews/single-review', null, array('review' => $review)); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Zipper Reviews -->
            <?php if (!empty($reviews_data['zipper_reviews'])): ?>
            <div class="reviews-section zipper-reviews" data-role="zipper">
                <h5 class="section-title">
                    <span class="role-badge zipper"><?php echo esc_html($strings['zippers'] ?? __('Zippers', 'zippicks-reviews')); ?></span>
                    <span class="count">(<?php echo count($reviews_data['zipper_reviews']); ?>)</span>
                </h5>
                <div class="reviews-list">
                    <?php foreach ($reviews_data['zipper_reviews'] as $review): ?>
                        <?php get_template_part('template-parts/reviews/single-review', null, array('review' => $review)); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
        
            <!-- No Reviews State -->
            <div class="no-reviews">
                <div class="no-reviews-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/>
                    </svg>
                </div>
                <h5><?php echo esc_html($strings['no_reviews'] ?? __('No reviews yet', 'zippicks-reviews')); ?></h5>
                <p><?php echo esc_html($strings['be_first_reviewer'] ?? __('Be the first to share your experience!', 'zippicks-reviews')); ?></p>
                
                <?php if (is_user_logged_in() && zippicks_user_can_review()): ?>
                <a href="#zippicks-review-form" class="cta-review-btn">
                    <?php _e('Write a Review', 'zippicks-reviews'); ?>
                </a>
                <?php endif; ?>
            </div>
            
        <?php endif; ?>
        
        <!-- Load More Reviews (if applicable) -->
        <?php if ($has_reviews && (count($reviews_data['critic_reviews']) + count($reviews_data['zipper_reviews'])) >= 20): ?>
        <div class="load-more-reviews">
            <button class="zippicks-btn zippicks-btn-secondary" id="load-more-reviews-btn" data-business-id="<?php echo esc_attr($business_id); ?>" data-page="2">
                <?php _e('Load More Reviews', 'zippicks-reviews'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Schema.org Structured Data for Reviews -->
    <?php if ($has_reviews): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "<?php echo esc_js($business_data['title'] ?? ''); ?>",
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "<?php echo esc_js($score_data['raw_score'] ?? 0); ?>",
            "reviewCount": "<?php echo esc_js($score_data['total_reviews'] ?? 0); ?>",
            "bestRating": "10",
            "worstRating": "1"
        }
    }
    </script>
    <?php endif; ?>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize load more functionality
    initializeLoadMore();
    
    // Initialize smooth scrolling for review links
    initializeSmoothScrolling();
    
    function initializeLoadMore() {
        const loadMoreBtn = document.getElementById('load-more-reviews-btn');
        if (!loadMoreBtn) return;
        
        loadMoreBtn.addEventListener('click', function() {
            const businessId = this.dataset.businessId;
            const page = parseInt(this.dataset.page);
            
            this.disabled = true;
            this.textContent = '<?php _e('Loading...', 'zippicks-reviews'); ?>';
            
            // AJAX call to load more reviews would go here
            // For now, just hide the button
            setTimeout(() => {
                this.style.display = 'none';
            }, 1000);
        });
    }
    
    function initializeSmoothScrolling() {
        const reviewLinks = document.querySelectorAll('a[href^="#zippicks-review"]');
        reviewLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    }
});
</script>