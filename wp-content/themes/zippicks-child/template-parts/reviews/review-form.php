<?php
/**
 * Review Form Template
 * template-parts/reviews/review-form.php
 * 
 * HTML template for review submission form
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Extract template variables (passed from class)
$business_id = $args['business_id'] ?? get_the_ID();
$business_title = $args['business_title'] ?? '';
$reviewer_role = $args['reviewer_role'] ?? 'zipper';
$reviewer_id = $args['reviewer_id'] ?? get_current_user_id();
$scoring_pillars = $args['scoring_pillars'] ?? array();
$nonce = $args['nonce'] ?? '';
$ajax_url = $args['ajax_url'] ?? admin_url('admin-ajax.php');
$vertical = $args['vertical'] ?? 'restaurant';
$strings = $args['strings'] ?? array();
?>

<div class="zippicks-review-form-container">
    
    <!-- Form Header -->
    <div class="zippicks-review-header">
        <h3><?php echo sprintf(__('Share Your Experience at %s', 'zippicks-reviews'), esc_html($business_title)); ?></h3>
        <p class="reviewer-badge reviewer-<?php echo esc_attr($reviewer_role); ?>">
            <?php echo sprintf(__('Reviewing as: %s', 'zippicks-reviews'), ucfirst($reviewer_role)); ?>
        </p>
    </div>
    
    <!-- Review Form -->
    <form id="zippicks-review-form" class="zippicks-form" data-business-id="<?php echo esc_attr($business_id); ?>">
        
        <!-- Scoring Section -->
        <div class="zippicks-review-scores">
            <h4><?php _e('Rate Your Experience (1-10 scale)', 'zippicks-reviews'); ?></h4>
            
            <?php foreach ($scoring_pillars as $pillar_slug => $pillar_name): ?>
            <div class="score-group" data-pillar="<?php echo esc_attr($pillar_slug); ?>">
                <label for="<?php echo esc_attr($pillar_slug); ?>" class="pillar-label">
                    <?php echo esc_html($pillar_name); ?>
                </label>
                <div class="score-slider-container">
                    <input 
                        type="range" 
                        id="<?php echo esc_attr($pillar_slug); ?>" 
                        name="<?php echo esc_attr($pillar_slug); ?>" 
                        min="1" 
                        max="10" 
                        value="5" 
                        class="score-slider"
                        required
                        data-pillar="<?php echo esc_attr($pillar_slug); ?>"
                    >
                    <span class="score-display" data-score="5">5</span>
                </div>
                <div class="score-labels">
                    <span class="score-min">1</span>
                    <span class="score-max">10</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Comment Section -->
        <div class="zippicks-review-comment">
            <label for="review_comment" class="comment-label">
                <?php _e('Additional Comments (Optional)', 'zippicks-reviews'); ?>
            </label>
            <textarea 
                id="review_comment" 
                name="comment" 
                rows="4" 
                maxlength="1000"
                placeholder="<?php esc_attr_e('Share more details about your experience...', 'zippicks-reviews'); ?>"
                class="comment-textarea"
            ></textarea>
            <div class="character-count">
                <span class="current-count">0</span> / 1000 <?php _e('characters', 'zippicks-reviews'); ?>
            </div>
        </div>
        
        <!-- Hidden Fields -->
        <input type="hidden" name="reviewer_id" value="<?php echo esc_attr($reviewer_id); ?>">
        <input type="hidden" name="reviewer_role" value="<?php echo esc_attr($reviewer_role); ?>">
        <input type="hidden" name="target_type" value="business">
        <input type="hidden" name="target_id" value="<?php echo esc_attr($business_id); ?>">
        <input type="hidden" name="vertical" value="<?php echo esc_attr($vertical); ?>">
        <input type="hidden" name="action" value="zippicks_submit_review">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
        
        <!-- Form Actions -->
        <div class="zippicks-form-actions">
            <button type="submit" class="zippicks-btn zippicks-btn-primary" id="submit-review-btn">
                <span class="btn-text"><?php echo esc_html($strings['submit'] ?? __('Submit Review', 'zippicks-reviews')); ?></span>
                <span class="btn-loading" style="display: none;">
                    <span class="spinner"></span>
                    <?php echo esc_html($strings['submitting'] ?? __('Submitting...', 'zippicks-reviews')); ?>
                </span>
            </button>
        </div>
    </form>
    
    <!-- Messages Container -->
    <div id="zippicks-review-messages" class="zippicks-messages" style="display: none;"></div>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('zippicks-review-form');
    const messages = document.getElementById('zippicks-review-messages');
    const submitBtn = document.getElementById('submit-review-btn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    
    // Initialize score sliders
    initializeScoreSliders();
    
    // Initialize character counter
    initializeCharacterCounter();
    
    // Handle form submission
    form.addEventListener('submit', handleFormSubmission);
    
    function initializeScoreSliders() {
        const sliders = document.querySelectorAll('.score-slider');
        sliders.forEach(slider => {
            const display = slider.parentElement.querySelector('.score-display');
            
            slider.addEventListener('input', function() {
                display.textContent = this.value;
                display.setAttribute('data-score', this.value);
                
                // Add visual feedback
                const scoreGroup = this.closest('.score-group');
                scoreGroup.classList.add('scored');
                
                // Update slider color based on score
                updateSliderColor(this, parseInt(this.value));
            });
        });
    }
    
    function updateSliderColor(slider, score) {
        slider.className = 'score-slider';
        if (score >= 8) {
            slider.classList.add('score-excellent');
        } else if (score >= 7) {
            slider.classList.add('score-very-good');
        } else if (score >= 6) {
            slider.classList.add('score-good');
        } else if (score >= 4) {
            slider.classList.add('score-fair');
        } else {
            slider.classList.add('score-poor');
        }
    }
    
    function initializeCharacterCounter() {
        const textarea = document.getElementById('review_comment');
        const counter = document.querySelector('.current-count');
        
        if (textarea && counter) {
            textarea.addEventListener('input', function() {
                const currentLength = this.value.length;
                counter.textContent = currentLength;
                
                // Visual feedback for approaching limit
                const container = this.closest('.zippicks-review-comment');
                if (currentLength > 800) {
                    container.classList.add('approaching-limit');
                } else {
                    container.classList.remove('approaching-limit');
                }
                
                if (currentLength >= 1000) {
                    container.classList.add('at-limit');
                } else {
                    container.classList.remove('at-limit');
                }
            });
        }
    }
    
    function handleFormSubmission(e) {
        e.preventDefault();
        
        // Validate form
        if (!validateForm()) {
            return;
        }
        
        // Update button state
        setSubmissionState(true);
        
        // Prepare form data
        const formData = new FormData(form);
        
        // Submit via AJAX
        fetch('<?php echo esc_url($ajax_url); ?>', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            handleSubmissionResponse(data);
        })
        .catch(error => {
            console.error('Submission error:', error);
            showMessage('<?php echo esc_js($strings['error'] ?? __('Network error. Please try again.', 'zippicks-reviews')); ?>', 'error');
            setSubmissionState(false);
        });
    }
    
    function validateForm() {
        const sliders = document.querySelectorAll('.score-slider');
        let isValid = true;
        
        // Check all sliders have been interacted with
        sliders.forEach(slider => {
            const scoreGroup = slider.closest('.score-group');
            if (!scoreGroup.classList.contains('scored')) {
                scoreGroup.classList.add('error');
                isValid = false;
            } else {
                scoreGroup.classList.remove('error');
            }
        });
        
        if (!isValid) {
            showMessage('<?php _e('Please provide scores for all categories.', 'zippicks-reviews'); ?>', 'error');
        }
        
        return isValid;
    }
    
    function setSubmissionState(isSubmitting) {
        submitBtn.disabled = isSubmitting;
        
        if (isSubmitting) {
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-flex';
            form.classList.add('submitting');
        } else {
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
            form.classList.remove('submitting');
        }
    }
    
    function handleSubmissionResponse(data) {
        if (data.success) {
            showMessage(data.data.message, 'success');
            form.style.display = 'none';
            
            // Trigger page refresh after delay to show updated reviews
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showMessage(data.data.message || '<?php echo esc_js($strings['error'] ?? __('An error occurred. Please try again.', 'zippicks-reviews')); ?>', 'error');
            setSubmissionState(false);
        }
    }
    
    function showMessage(message, type) {
        messages.className = `zippicks-messages ${type}`;
        messages.textContent = message;
        messages.style.display = 'block';
        
        // Scroll to message
        messages.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                messages.style.display = 'none';
            }, 5000);
        }
    }
});
</script>