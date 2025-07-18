<?php
/**
 * Business Vibes Display Template
 *
 * Displays API-verified vibes with confidence scores.
 * Supports both full and compact display modes.
 *
 * @package ZipPicks_Business
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get business vibes data
$business_id = $business_id ?? get_the_ID();
$api_vibes = json_decode(get_post_meta($business_id, 'api_vibes', true), true);
$display_mode = $display_mode ?? 'full'; // 'full', 'compact', 'inline'
$max_vibes = $max_vibes ?? 5;
$min_confidence = $min_confidence ?? 0.6;

// Filter vibes by confidence and sort by score
if (!empty($api_vibes)) {
    $filtered_vibes = array_filter($api_vibes, function($vibe) use ($min_confidence) {
        return isset($vibe['confidence']) && $vibe['confidence'] >= $min_confidence;
    });
    
    // Sort by confidence (highest first)
    usort($filtered_vibes, function($a, $b) {
        return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
    });
    
    // Limit to max vibes
    $api_vibes = array_slice($filtered_vibes, 0, $max_vibes);
}

// Don't display if no vibes meet criteria
if (empty($api_vibes)) {
    return;
}
?>

<div class="zippicks-business-vibes <?php echo esc_attr('vibes-' . $display_mode); ?>">
    <?php if ($display_mode === 'full'): ?>
        <h3 class="vibes-title"><?php _e('The Vibe', 'zippicks-business'); ?></h3>
    <?php endif; ?>
    
    <div class="vibe-tags">
        <?php foreach ($api_vibes as $vibe): 
            $confidence = $vibe['confidence'] ?? 0;
            $confidence_class = '';
            
            if ($confidence >= 0.9) {
                $confidence_class = 'confidence-high';
            } elseif ($confidence >= 0.75) {
                $confidence_class = 'confidence-medium';
            } else {
                $confidence_class = 'confidence-low';
            }
        ?>
            <span class="vibe-tag <?php echo esc_attr($confidence_class); ?>" 
                  data-confidence="<?php echo esc_attr($confidence); ?>"
                  title="<?php echo esc_attr(sprintf(__('%s - %d%% confidence', 'zippicks-business'), 
                                                  $vibe['display_name'], 
                                                  round($confidence * 100))); ?>">
                <?php echo esc_html($vibe['display_name']); ?>
                <?php if ($display_mode === 'full' && $confidence >= 0.8): ?>
                    <span class="confidence-indicator">
                        <?php echo round($confidence * 100); ?>%
                    </span>
                <?php endif; ?>
            </span>
        <?php endforeach; ?>
    </div>
    
    <?php if ($display_mode === 'full' && count($api_vibes) === $max_vibes): ?>
        <small class="vibes-note">
            <?php _e('Showing top vibes based on AI analysis', 'zippicks-business'); ?>
        </small>
    <?php endif; ?>
</div>

<style>
/* Base vibe styles */
.zippicks-business-vibes {
    margin: 15px 0;
}

.vibes-title {
    margin: 0 0 10px 0;
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
}

.vibe-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}

.vibe-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: default;
    border: 1px solid transparent;
}

/* Confidence-based styling */
.vibe-tag.confidence-high {
    background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
    color: #155724;
    border-color: #c3e6cb;
}

.vibe-tag.confidence-medium {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    color: #856404;
    border-color: #ffeaa7;
}

.vibe-tag.confidence-low {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #495057;
    border-color: #dee2e6;
}

.confidence-indicator {
    font-size: 11px;
    font-weight: 600;
    opacity: 0.8;
    background: rgba(255, 255, 255, 0.3);
    padding: 1px 4px;
    border-radius: 8px;
}

.vibes-note {
    font-size: 12px;
    color: #6c757d;
    font-style: italic;
}

/* Compact mode */
.vibes-compact .vibes-title {
    font-size: 16px;
    margin-bottom: 8px;
}

.vibes-compact .vibe-tag {
    padding: 4px 8px;
    font-size: 12px;
}

.vibes-compact .confidence-indicator {
    display: none;
}

/* Inline mode for cards */
.vibes-inline {
    margin: 8px 0;
}

.vibes-inline .vibes-title {
    display: none;
}

.vibes-inline .vibe-tags {
    gap: 4px;
}

.vibes-inline .vibe-tag {
    padding: 3px 8px;
    font-size: 11px;
    border-radius: 12px;
}

.vibes-inline .confidence-indicator {
    display: none;
}

.vibes-inline .vibes-note {
    display: none;
}

/* Hover effects */
.vibe-tag:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Responsive design */
@media (max-width: 768px) {
    .vibe-tags {
        gap: 6px;
    }
    
    .vibe-tag {
        padding: 4px 8px;
        font-size: 12px;
    }
    
    .vibes-title {
        font-size: 16px;
    }
}

/* Dark theme support */
@media (prefers-color-scheme: dark) {
    .vibes-title {
        color: #f8f9fa;
    }
    
    .vibe-tag.confidence-high {
        background: linear-gradient(135deg, #1e3a2e 0%, #2d5a3d 100%);
        color: #90ee90;
        border-color: #2d5a3d;
    }
    
    .vibe-tag.confidence-medium {
        background: linear-gradient(135deg, #3d3a1e 0%, #5a4d2d 100%);
        color: #f0d040;
        border-color: #5a4d2d;
    }
    
    .vibe-tag.confidence-low {
        background: linear-gradient(135deg, #2d2d2d 0%, #404040 100%);
        color: #d0d0d0;
        border-color: #404040;
    }
    
    .vibes-note {
        color: #adb5bd;
    }
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .vibe-tag {
        border: 2px solid;
        background: #fff !important;
        color: #000 !important;
    }
    
    .vibes-title {
        color: #000;
    }
}

/* Print styles */
@media print {
    .vibe-tag {
        background: #fff !important;
        color: #000 !important;
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    
    .confidence-indicator {
        display: inline !important;
    }
    
    .vibe-tag:hover {
        transform: none !important;
    }
}

/* Animation for dynamic loading */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.vibe-tag {
    animation: fadeInUp 0.3s ease-out;
}

.vibe-tag:nth-child(2) { animation-delay: 0.1s; }
.vibe-tag:nth-child(3) { animation-delay: 0.2s; }
.vibe-tag:nth-child(4) { animation-delay: 0.3s; }
.vibe-tag:nth-child(5) { animation-delay: 0.4s; }
</style>