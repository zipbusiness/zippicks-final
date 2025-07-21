<?php
/**
 * ZipPicks Scores Display Template
 *
 * Displays only ZipPicks proprietary scores and explicitly excludes
 * external platform ratings (Yelp, Google, etc.) per data protection policy.
 *
 * @package ZipPicks_Business  
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get business scores
$business_id = $business_id ?? get_the_ID();
$pillar_scores = get_post_meta($business_id, 'pillar_scores', true);
$display_mode = $display_mode ?? 'full'; // 'full', 'compact', 'summary'

// Default pillar names
$pillar_labels = array(
    'taste' => __('Taste', 'zippicks-business'),
    'service' => __('Service', 'zippicks-business'), 
    'speed' => __('Speed', 'zippicks-business'),
    'value' => __('Value', 'zippicks-business'),
    'overall' => __('Overall', 'zippicks-business')
);

// Don't display if no scores
if (empty($pillar_scores) || !is_array($pillar_scores)) {
    return;
}

// Calculate overall if not present
if (!isset($pillar_scores['overall']) && count($pillar_scores) > 0) {
    $pillar_scores['overall'] = array_sum($pillar_scores) / count($pillar_scores);
}

// Function to get score color class
function get_score_class($score) {
    if ($score >= 8.5) return 'score-excellent';
    if ($score >= 7.5) return 'score-great';
    if ($score >= 6.5) return 'score-good';
    if ($score >= 5.0) return 'score-fair';
    return 'score-poor';
}
?>

<div class="zippicks-scores <?php echo esc_attr('scores-' . $display_mode); ?>">
    <?php if ($display_mode === 'full'): ?>
        <h3 class="scores-title"><?php _e('ZipPicks Score', 'zippicks-business'); ?></h3>
    <?php endif; ?>
    
    <?php if ($display_mode === 'summary' && isset($pillar_scores['overall'])): ?>
        <!-- Summary mode: just overall score -->
        <div class="overall-score <?php echo esc_attr(get_score_class($pillar_scores['overall'])); ?>">
            <span class="score-value"><?php echo number_format($pillar_scores['overall'], 1); ?></span>
            <span class="score-label"><?php _e('ZipPicks Score', 'zippicks-business'); ?></span>
        </div>
    <?php else: ?>
        <!-- Full/compact mode: show all pillars -->
        <div class="score-grid">
            <?php 
            // Show overall first if it exists and in full mode
            if ($display_mode === 'full' && isset($pillar_scores['overall'])): 
            ?>
                <div class="score-item overall <?php echo esc_attr(get_score_class($pillar_scores['overall'])); ?>">
                    <span class="pillar-name"><?php echo esc_html($pillar_labels['overall']); ?></span>
                    <span class="score-value"><?php echo number_format($pillar_scores['overall'], 1); ?></span>
                </div>
            <?php endif; ?>
            
            <?php foreach ($pillar_scores as $pillar => $score): 
                if ($pillar === 'overall' && $display_mode === 'full') continue; // Already shown above
                if (!isset($pillar_labels[$pillar])) continue; // Skip unknown pillars
                
                $score = (float) $score;
                if ($score <= 0) continue; // Skip zero scores
            ?>
                <div class="score-item <?php echo esc_attr($pillar . ' ' . get_score_class($score)); ?>">
                    <span class="pillar-name"><?php echo esc_html($pillar_labels[$pillar]); ?></span>
                    <span class="score-value"><?php echo number_format($score, 1); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($display_mode === 'full'): ?>
        <div class="scores-disclaimer">
            <small><?php _e('Scores based on ZipPicks proprietary analysis', 'zippicks-business'); ?></small>
        </div>
    <?php endif; ?>
</div>

<style>
/* Base score styles */
.zippicks-scores {
    margin: 20px 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.scores-title {
    margin: 0 0 15px 0;
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 8px;
}

.scores-title::before {
    content: "⭐";
    font-size: 18px;
}

/* Score grid layout */
.score-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 12px;
}

.score-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px;
    border-radius: 8px;
    border: 2px solid;
    background: linear-gradient(135deg, var(--bg-start), var(--bg-end));
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.score-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--accent-color);
}

.pillar-name {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
    opacity: 0.8;
}

.score-value {
    font-size: 24px;
    font-weight: 800;
    line-height: 1;
}

/* Overall score styling */
.score-item.overall {
    grid-column: 1 / -1;
    flex-direction: row;
    justify-content: space-between;
    padding: 16px 20px;
}

.score-item.overall .pillar-name {
    font-size: 16px;
    margin-bottom: 0;
}

.score-item.overall .score-value {
    font-size: 32px;
}

/* Score color schemes */
.score-excellent {
    --bg-start: #d4edda;
    --bg-end: #c3e6cb;
    --accent-color: #28a745;
    color: #155724;
    border-color: #c3e6cb;
}

.score-great {
    --bg-start: #d1ecf1;
    --bg-end: #bee5eb;
    --accent-color: #17a2b8;
    color: #0c5460;
    border-color: #bee5eb;
}

.score-good {
    --bg-start: #fff3cd;
    --bg-end: #ffeaa7;
    --accent-color: #ffc107;
    color: #856404;
    border-color: #ffeaa7;
}

.score-fair {
    --bg-start: #fce4d6;
    --bg-end: #f8d7da;
    --accent-color: #fd7e14;
    color: #85461f;
    border-color: #f8d7da;
}

.score-poor {
    --bg-start: #f8d7da;
    --bg-end: #f5c6cb;
    --accent-color: #dc3545;
    color: #721c24;
    border-color: #f5c6cb;
}

/* Summary mode */
.scores-summary .overall-score {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    border: 2px solid;
    background: linear-gradient(135deg, var(--bg-start), var(--bg-end));
    font-weight: 700;
}

.scores-summary .score-value {
    font-size: 18px;
}

.scores-summary .score-label {
    font-size: 14px;
}

/* Compact mode */
.scores-compact .scores-title {
    font-size: 16px;
    margin-bottom: 10px;
}

.scores-compact .score-grid {
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    gap: 8px;
}

.scores-compact .score-item {
    padding: 8px;
}

.scores-compact .pillar-name {
    font-size: 10px;
}

.scores-compact .score-value {
    font-size: 18px;
}

.scores-compact .score-item.overall {
    padding: 12px;
}

.scores-compact .score-item.overall .score-value {
    font-size: 24px;
}

/* Disclaimer */
.scores-disclaimer {
    text-align: center;
    margin-top: 10px;
}

.scores-disclaimer small {
    color: #6c757d;
    font-size: 11px;
    font-style: italic;
}

/* Hover effects */
.score-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Responsive design */
@media (max-width: 768px) {
    .score-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    
    .score-item.overall {
        grid-column: 1 / -1;
        padding: 12px;
    }
    
    .scores-title {
        font-size: 18px;
    }
}

@media (max-width: 480px) {
    .score-grid {
        grid-template-columns: 1fr;
    }
    
    .score-item {
        flex-direction: row;
        justify-content: space-between;
        padding: 10px 12px;
    }
    
    .pillar-name {
        margin-bottom: 0;
        font-size: 14px;
    }
    
    .score-value {
        font-size: 20px;
    }
}

/* Dark theme support */
@media (prefers-color-scheme: dark) {
    .scores-title {
        color: #f8f9fa;
    }
    
    .score-excellent {
        --bg-start: #1e3a2e;
        --bg-end: #2d5a3d;
        color: #90ee90;
        border-color: #2d5a3d;
    }
    
    .score-great {
        --bg-start: #1e2f3a;
        --bg-end: #2d475a;
        color: #87ceeb;
        border-color: #2d475a;
    }
    
    .score-good {
        --bg-start: #3a3a1e;
        --bg-end: #5a5a2d;
        color: #f0d040;
        border-color: #5a5a2d;
    }
    
    .score-fair {
        --bg-start: #3a2e1e;
        --bg-end: #5a472d;
        color: #ffa500;
        border-color: #5a472d;
    }
    
    .score-poor {
        --bg-start: #3a1e1e;
        --bg-end: #5a2d2d;
        color: #ff6b6b;
        border-color: #5a2d2d;
    }
    
    .scores-disclaimer small {
        color: #adb5bd;
    }
}

/* Print styles */
@media print {
    .score-item {
        background: #fff !important;
        color: #000 !important;
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    
    .score-item:hover {
        transform: none !important;
    }
    
    .scores-title::before {
        display: none;
    }
}

/* Animation */
@keyframes scoreReveal {
    from {
        opacity: 0;
        transform: scale(0.8);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.score-item {
    animation: scoreReveal 0.4s ease-out;
}

.score-item:nth-child(1) { animation-delay: 0s; }
.score-item:nth-child(2) { animation-delay: 0.1s; }
.score-item:nth-child(3) { animation-delay: 0.2s; }
.score-item:nth-child(4) { animation-delay: 0.3s; }
.score-item:nth-child(5) { animation-delay: 0.4s; }
</style>