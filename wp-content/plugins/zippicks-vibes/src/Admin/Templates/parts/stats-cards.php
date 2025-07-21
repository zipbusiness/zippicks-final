<?php
/**
 * Statistics Cards Template Part
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 * @var array $stats_data Statistics configuration data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access prohibited');
}

if (empty($stats_data) || !is_array($stats_data)) {
    return;
}

$section_title = $stats_data['section_title'] ?? __('Dashboard Statistics', 'zippicks-vibes');
$cards = $stats_data['cards'] ?? array();
?>

<section aria-labelledby="stats-heading" class="dashboard-stats">
    <h2 id="stats-heading" class="sr-only"><?php echo esc_html($section_title); ?></h2>
    <div class="dashboard-grid" role="list">
        <?php foreach ($cards as $card): ?>
            <div class="stat-card" role="listitem">
                <?php if (!empty($card['icon'])): ?>
                    <div class="stat-icon">
                        <span class="icon <?php echo esc_attr($card['icon']); ?>" aria-hidden="true"></span>
                    </div>
                <?php endif; ?>
                
                <div class="stat-content">
                    <div class="stat-number" 
                         aria-label="<?php echo esc_attr($card['aria_label'] ?? $card['label']); ?>">
                        <?php echo esc_html($card['value']); ?>
                    </div>
                    <div class="stat-label">
                        <?php echo esc_html($card['label']); ?>
                    </div>
                    
                    <?php if (!empty($card['description'])): ?>
                        <div class="stat-description">
                            <?php echo esc_html($card['description']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($card['trend'])): ?>
                        <div class="stat-trend <?php echo esc_attr($card['trend']['direction']); ?>">
                            <span class="trend-icon" aria-hidden="true">
                                <?php echo $card['trend']['direction'] === 'up' ? '↗' : '↘'; ?>
                            </span>
                            <span class="trend-value">
                                <?php echo esc_html($card['trend']['value']); ?>
                            </span>
                            <span class="trend-period">
                                <?php echo esc_html($card['trend']['period'] ?? __('vs last month', 'zippicks-vibes')); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($card['action'])): ?>
                    <div class="stat-action">
                        <a href="<?php echo esc_url($card['action']['url']); ?>" 
                           class="button button-small"
                           aria-label="<?php echo esc_attr($card['action']['aria_label'] ?? ''); ?>">
                            <?php echo esc_html($card['action']['text']); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>