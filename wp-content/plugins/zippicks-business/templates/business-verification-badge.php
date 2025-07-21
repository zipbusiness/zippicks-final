<?php
/**
 * Business Verification Badge Template
 *
 * Displays the verification badge for API-verified businesses.
 * Can be included in single business templates and archive listings.
 *
 * @package ZipPicks_Business
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get business verification data
$business_id = $business_id ?? get_the_ID();
$is_verified = get_post_meta($business_id, 'api_verified', true);
$zpid = get_post_meta($business_id, 'zpid', true);
$confidence = get_post_meta($business_id, 'api_confidence_score', true);

// Only show if verified
if (!$is_verified || !$zpid) {
    return;
}
?>

<div class="zippicks-business-verification">
    <span class="verified-badge">
        <svg class="verification-icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M8 0L10.5 2.5L14 3L14.5 6.5L16 9L14.5 11.5L14 15L10.5 13.5L8 16L5.5 13.5L2 15L1.5 11.5L0 9L1.5 6.5L2 3L5.5 2.5L8 0Z" fill="#22C55E"/>
            <path d="M11.5 5.5L7 10L4.5 7.5" stroke="white" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="verification-text">
            <?php _e('Verified by ZipBusiness', 'zippicks-business'); ?>
        </span>
        <?php if ($confidence && $confidence >= 0.8): ?>
            <span class="confidence-score" title="<?php echo esc_attr(sprintf(__('Confidence: %d%%', 'zippicks-business'), round($confidence * 100))); ?>">
                (<?php echo round($confidence * 100); ?>%)
            </span>
        <?php endif; ?>
    </span>
</div>

<style>
.zippicks-business-verification {
    margin: 10px 0;
}

.verified-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    border: 1px solid #c3e6cb;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    text-decoration: none;
}

.verification-icon {
    flex-shrink: 0;
}

.verification-text {
    white-space: nowrap;
}

.confidence-score {
    font-size: 12px;
    font-weight: normal;
    opacity: 0.8;
}

/* Archive/card variant */
.business-card .verified-badge {
    font-size: 12px;
    padding: 4px 8px;
    margin-top: 5px;
}

.business-card .verification-icon {
    width: 14px;
    height: 14px;
}

/* Compact variant for small spaces */
.verified-indicator {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    color: #22C55E;
    font-size: 12px;
    font-weight: 600;
}

.verified-indicator svg {
    width: 12px;
    height: 12px;
}

/* Dark theme support */
@media (prefers-color-scheme: dark) {
    .verified-badge {
        background: linear-gradient(135deg, #1e3a2e 0%, #2d5a3d 100%);
        color: #90ee90;
        border-color: #2d5a3d;
    }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .verified-badge {
        background: #000;
        color: #fff;
        border: 2px solid #fff;
    }
    
    .verification-icon path:first-child {
        fill: #fff;
    }
    
    .verification-icon path:last-child {
        stroke: #000;
    }
}

/* Print styles */
@media print {
    .verified-badge {
        background: #fff !important;
        color: #000 !important;
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    
    .verification-icon {
        display: none;
    }
}
</style>