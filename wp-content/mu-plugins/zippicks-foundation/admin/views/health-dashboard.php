<?php
/**
 * Health Check Dashboard View
 * 
 * @package ZipPicks\Foundation\Admin
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get health check results
$healthService = foundation()->get('health.service');
$results = $healthService->check();

// Group checks by status
$checksByStatus = [
    'healthy' => [],
    'degraded' => [],
    'unhealthy' => []
];

foreach ($results['checks'] as $name => $check) {
    $checksByStatus[$check['status']][] = array_merge($check, ['name' => $name]);
}

// Status colors
$statusColors = [
    'healthy' => '#46b450',
    'degraded' => '#ffb900',
    'unhealthy' => '#dc3232'
];
?>

<div class="wrap">
    <h1>
        <?php echo esc_html__('System Health', 'zippicks'); ?>
        <span class="health-status-badge" style="background-color: <?php echo esc_attr($statusColors[$results['status']]); ?>">
            <?php echo esc_html(ucfirst($results['status'])); ?>
        </span>
    </h1>
    
    <div class="health-dashboard-grid">
        <!-- Overview Card -->
        <div class="health-card overview-card">
            <h2><?php echo esc_html__('System Overview', 'zippicks'); ?></h2>
            
            <div class="health-stats">
                <div class="stat-item">
                    <span class="stat-value"><?php echo esc_html($results['environment']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Environment', 'zippicks'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo esc_html($results['version']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Version', 'zippicks'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo esc_html($results['summary']['healthy']); ?>/<?php echo esc_html($results['summary']['total']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Healthy Checks', 'zippicks'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo esc_html($results['metadata']['check_duration_ms']); ?>ms</span>
                    <span class="stat-label"><?php echo esc_html__('Check Duration', 'zippicks'); ?></span>
                </div>
            </div>
            
            <div class="health-summary">
                <div class="summary-bar">
                    <?php if ($results['summary']['healthy'] > 0): ?>
                        <div class="bar-segment healthy" style="width: <?php echo ($results['summary']['healthy'] / $results['summary']['total']) * 100; ?>%"></div>
                    <?php endif; ?>
                    <?php if ($results['summary']['degraded'] > 0): ?>
                        <div class="bar-segment degraded" style="width: <?php echo ($results['summary']['degraded'] / $results['summary']['total']) * 100; ?>%"></div>
                    <?php endif; ?>
                    <?php if ($results['summary']['unhealthy'] > 0): ?>
                        <div class="bar-segment unhealthy" style="width: <?php echo ($results['summary']['unhealthy'] / $results['summary']['total']) * 100; ?>%"></div>
                    <?php endif; ?>
                </div>
                <div class="summary-legend">
                    <span class="legend-item">
                        <span class="legend-color healthy"></span>
                        <?php echo esc_html__('Healthy', 'zippicks'); ?> (<?php echo esc_html($results['summary']['healthy']); ?>)
                    </span>
                    <span class="legend-item">
                        <span class="legend-color degraded"></span>
                        <?php echo esc_html__('Degraded', 'zippicks'); ?> (<?php echo esc_html($results['summary']['degraded']); ?>)
                    </span>
                    <span class="legend-item">
                        <span class="legend-color unhealthy"></span>
                        <?php echo esc_html__('Unhealthy', 'zippicks'); ?> (<?php echo esc_html($results['summary']['unhealthy']); ?>)
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Critical Issues Card -->
        <?php if (!empty($checksByStatus['unhealthy'])): ?>
        <div class="health-card issues-card unhealthy">
            <h2><?php echo esc_html__('Critical Issues', 'zippicks'); ?></h2>
            <div class="health-checks">
                <?php foreach ($checksByStatus['unhealthy'] as $check): ?>
                    <div class="health-check-item unhealthy">
                        <div class="check-header">
                            <span class="check-name"><?php echo esc_html(str_replace('_', ' ', ucwords($check['name']))); ?></span>
                            <span class="check-status"><?php echo esc_html__('Failed', 'zippicks'); ?></span>
                        </div>
                        <div class="check-message"><?php echo esc_html($check['message']); ?></div>
                        <?php if (!empty($check['metadata'])): ?>
                            <div class="check-metadata">
                                <?php foreach ($check['metadata'] as $key => $value): ?>
                                    <span class="metadata-item">
                                        <strong><?php echo esc_html($key); ?>:</strong> 
                                        <?php echo esc_html(is_array($value) ? json_encode($value) : $value); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="check-meta">
                            <span class="latency">⏱ <?php echo esc_html($check['latency_ms']); ?>ms</span>
                            <?php if ($check['critical']): ?>
                                <span class="critical-badge"><?php echo esc_html__('Critical', 'zippicks'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Warnings Card -->
        <?php if (!empty($checksByStatus['degraded'])): ?>
        <div class="health-card issues-card degraded">
            <h2><?php echo esc_html__('Warnings', 'zippicks'); ?></h2>
            <div class="health-checks">
                <?php foreach ($checksByStatus['degraded'] as $check): ?>
                    <div class="health-check-item degraded">
                        <div class="check-header">
                            <span class="check-name"><?php echo esc_html(str_replace('_', ' ', ucwords($check['name']))); ?></span>
                            <span class="check-status"><?php echo esc_html__('Degraded', 'zippicks'); ?></span>
                        </div>
                        <div class="check-message"><?php echo esc_html($check['message']); ?></div>
                        <?php if (!empty($check['metadata'])): ?>
                            <div class="check-metadata">
                                <?php foreach ($check['metadata'] as $key => $value): ?>
                                    <span class="metadata-item">
                                        <strong><?php echo esc_html($key); ?>:</strong> 
                                        <?php echo esc_html(is_array($value) ? json_encode($value) : $value); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="check-meta">
                            <span class="latency">⏱ <?php echo esc_html($check['latency_ms']); ?>ms</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- All Checks Card -->
        <div class="health-card all-checks-card">
            <h2><?php echo esc_html__('All Health Checks', 'zippicks'); ?></h2>
            <div class="health-checks">
                <?php foreach ($results['checks'] as $name => $check): ?>
                    <div class="health-check-item <?php echo esc_attr($check['status']); ?>">
                        <div class="check-header">
                            <span class="check-name"><?php echo esc_html(str_replace('_', ' ', ucwords($name))); ?></span>
                            <span class="check-status"><?php echo esc_html(ucfirst($check['status'])); ?></span>
                        </div>
                        <div class="check-message"><?php echo esc_html($check['message']); ?></div>
                        <div class="check-meta">
                            <span class="latency">⏱ <?php echo esc_html($check['latency_ms']); ?>ms</span>
                            <?php if ($check['critical']): ?>
                                <span class="critical-badge"><?php echo esc_html__('Critical', 'zippicks'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Actions Card -->
        <div class="health-card actions-card">
            <h2><?php echo esc_html__('Health Check Actions', 'zippicks'); ?></h2>
            
            <div class="health-actions">
                <button class="button button-primary" id="refresh-health">
                    <span class="dashicons dashicons-update"></span>
                    <?php echo esc_html__('Refresh Health Check', 'zippicks'); ?>
                </button>
                
                <a href="<?php echo esc_url(rest_url('zippicks/v1/health')); ?>" class="button" target="_blank">
                    <span class="dashicons dashicons-rest-api"></span>
                    <?php echo esc_html__('View API Endpoint', 'zippicks'); ?>
                </a>
                
                <button class="button" id="export-health">
                    <span class="dashicons dashicons-download"></span>
                    <?php echo esc_html__('Export Report', 'zippicks'); ?>
                </button>
            </div>
            
            <div class="health-info">
                <h3><?php echo esc_html__('API Endpoints', 'zippicks'); ?></h3>
                <ul>
                    <li><code>GET /wp-json/zippicks/v1/health</code> - Public health endpoint</li>
                    <li><code>GET /wp-json/zippicks/v1/admin/health</code> - Admin health endpoint</li>
                    <li><code>GET /wp-json/zippicks/v1/health/{check}</code> - Individual check</li>
                </ul>
                
                <h3><?php echo esc_html__('WP-CLI Commands', 'zippicks'); ?></h3>
                <ul>
                    <li><code>wp zippicks health</code> - Run health check</li>
                    <li><code>wp zippicks health --detailed</code> - Detailed health check</li>
                    <li><code>wp zippicks health --detailed --verbose</code> - Include metadata</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.health-status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 3px;
    color: white;
    font-size: 14px;
    font-weight: 600;
    margin-left: 10px;
    vertical-align: middle;
}

.health-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.health-card {
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.health-card h2 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 18px;
    font-weight: 600;
}

.health-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.stat-value {
    display: block;
    font-size: 24px;
    font-weight: 600;
    color: #23282d;
    margin-bottom: 5px;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

.health-summary {
    margin-top: 20px;
}

.summary-bar {
    display: flex;
    height: 20px;
    border-radius: 10px;
    overflow: hidden;
    background: #f0f0f1;
    margin-bottom: 10px;
}

.bar-segment {
    transition: width 0.3s ease;
}

.bar-segment.healthy { background: #46b450; }
.bar-segment.degraded { background: #ffb900; }
.bar-segment.unhealthy { background: #dc3232; }

.summary-legend {
    display: flex;
    justify-content: center;
    gap: 20px;
    font-size: 13px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.legend-color {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 2px;
}

.legend-color.healthy { background: #46b450; }
.legend-color.degraded { background: #ffb900; }
.legend-color.unhealthy { background: #dc3232; }

.health-checks {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.health-check-item {
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #e2e4e7;
    background: #fafafa;
}

.health-check-item.healthy {
    border-left: 4px solid #46b450;
}

.health-check-item.degraded {
    border-left: 4px solid #ffb900;
    background: #fffbf0;
}

.health-check-item.unhealthy {
    border-left: 4px solid #dc3232;
    background: #fff5f5;
}

.check-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.check-name {
    font-weight: 600;
    font-size: 14px;
}

.check-status {
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 3px;
    background: #e2e4e7;
}

.health-check-item.healthy .check-status {
    background: #d4f4d8;
    color: #1a5f23;
}

.health-check-item.degraded .check-status {
    background: #fff3cd;
    color: #856404;
}

.health-check-item.unhealthy .check-status {
    background: #f8d7da;
    color: #721c24;
}

.check-message {
    font-size: 13px;
    color: #555;
    margin-bottom: 8px;
}

.check-metadata {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
    font-size: 12px;
    color: #666;
}

.metadata-item {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
}

.check-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
    font-size: 11px;
    color: #999;
}

.critical-badge {
    background: #dc3232;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: 600;
}

.health-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
}

.health-actions .button {
    width: 100%;
    justify-content: center;
}

.health-actions .dashicons {
    margin-right: 5px;
}

.health-info h3 {
    font-size: 14px;
    margin-top: 20px;
    margin-bottom: 10px;
}

.health-info ul {
    margin: 0;
    padding-left: 20px;
}

.health-info li {
    margin-bottom: 5px;
    font-size: 13px;
}

.health-info code {
    background: #f0f0f1;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 12px;
}

@media (max-width: 782px) {
    .health-dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .health-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Refresh health check
    $('#refresh-health').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.get(ajaxurl, {
            action: 'zippicks_refresh_health',
            nonce: '<?php echo wp_create_nonce('health_check'); ?>'
        }, function() {
            location.reload();
        });
    });
    
    // Export health report
    $('#export-health').on('click', function() {
        var data = <?php echo json_encode($results); ?>;
        var dataStr = JSON.stringify(data, null, 2);
        var dataBlob = new Blob([dataStr], {type: 'application/json'});
        var url = URL.createObjectURL(dataBlob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'zippicks-health-report-' + Date.now() + '.json';
        link.click();
        URL.revokeObjectURL(url);
    });
});
</script>