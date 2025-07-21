<?php
/**
 * Debug Panel View
 *
 * @package ZipPicks\BusinessIntelligence
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Business Intelligence Debug Panel', 'zippicks-business-intelligence'); ?></h1>
    
    <div class="zippicks-bi-debug-panel">
        
        <!-- System Information -->
        <div class="postbox">
            <h2 class="hndle"><?php _e('System Information', 'zippicks-business-intelligence'); ?></h2>
            <div class="inside">
                <table class="widefat striped">
                    <tbody>
                        <?php foreach ($debug_info as $key => $value): ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</strong></td>
                            <td><?php echo esc_html($value); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Configuration -->
        <div class="postbox">
            <h2 class="hndle"><?php _e('Configuration', 'zippicks-business-intelligence'); ?></h2>
            <div class="inside">
                <table class="widefat striped">
                    <tbody>
                        <?php foreach ($config_values as $key => $value): ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</strong></td>
                            <td>
                                <?php 
                                if ($key === 'api_key' && $value === 'Not set') {
                                    echo '<span style="color: #dc3232;">' . esc_html($value) . '</span>';
                                } else {
                                    echo esc_html($value);
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Database Tables -->
        <div class="postbox">
            <h2 class="hndle"><?php _e('Database Tables', 'zippicks-business-intelligence'); ?></h2>
            <div class="inside">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Table', 'zippicks-business-intelligence'); ?></th>
                            <th><?php _e('Status', 'zippicks-business-intelligence'); ?></th>
                            <th><?php _e('Records', 'zippicks-business-intelligence'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db_tables as $table): ?>
                        <tr>
                            <td><code><?php echo esc_html($table['name']); ?></code></td>
                            <td>
                                <?php if ($table['exists']): ?>
                                    <span style="color: #46b450;">✓ <?php _e('Exists', 'zippicks-business-intelligence'); ?></span>
                                <?php else: ?>
                                    <span style="color: #dc3232;">✗ <?php _e('Missing', 'zippicks-business-intelligence'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($table['count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Foundation Services -->
        <div class="postbox">
            <h2 class="hndle"><?php _e('Foundation Services', 'zippicks-business-intelligence'); ?></h2>
            <div class="inside">
                <?php if (!$foundation_services['available']): ?>
                    <p style="color: #dc3232;"><?php _e('ZipPicks Foundation is not available.', 'zippicks-business-intelligence'); ?></p>
                <?php else: ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Service', 'zippicks-business-intelligence'); ?></th>
                                <th><?php _e('Status', 'zippicks-business-intelligence'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($foundation_services['services'] as $service): ?>
                            <tr>
                                <td><?php echo esc_html($service['name']); ?></td>
                                <td>
                                    <?php if ($service['available']): ?>
                                        <span style="color: #46b450;">✓ <?php _e('Available', 'zippicks-business-intelligence'); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">○ <?php _e('Not Available', 'zippicks-business-intelligence'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- API Statistics -->
        <?php if (!empty($api_stats)): ?>
        <div class="postbox">
            <h2 class="hndle"><?php _e('API Statistics (Last 24 Hours)', 'zippicks-business-intelligence'); ?></h2>
            <div class="inside">
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?php echo number_format($api_stats['total_requests']); ?></div>
                        <div class="stat-label"><?php _e('Total Requests', 'zippicks-business-intelligence'); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value" style="color: <?php echo $api_stats['error_count'] > 0 ? '#dc3232' : '#46b450'; ?>">
                            <?php echo number_format($api_stats['error_count']); ?>
                        </div>
                        <div class="stat-label"><?php _e('Errors', 'zippicks-business-intelligence'); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?php echo number_format($api_stats['error_rate'], 1); ?>%</div>
                        <div class="stat-label"><?php _e('Error Rate', 'zippicks-business-intelligence'); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?php echo $api_stats['avg_response_time']; ?>s</div>
                        <div class="stat-label"><?php _e('Avg Response Time', 'zippicks-business-intelligence'); ?></div>
                    </div>
                </div>
                
                <?php if (!empty($api_stats['by_endpoint'])): ?>
                <h3><?php _e('Requests by Endpoint', 'zippicks-business-intelligence'); ?></h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Endpoint', 'zippicks-business-intelligence'); ?></th>
                            <th><?php _e('Count', 'zippicks-business-intelligence'); ?></th>
                            <th><?php _e('Avg Time', 'zippicks-business-intelligence'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($api_stats['by_endpoint'] as $endpoint): ?>
                        <tr>
                            <td><code><?php echo esc_html($endpoint['endpoint']); ?></code></td>
                            <td><?php echo number_format($endpoint['count']); ?></td>
                            <td><?php echo number_format($endpoint['avg_time'], 3); ?>s</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Cache Statistics -->
        <?php if (!empty($cache_stats)): ?>
        <div class="postbox">
            <h2 class="hndle"><?php _e('Cache Statistics', 'zippicks-business-intelligence'); ?></h2>
            <div class="inside">
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <td><strong><?php _e('Cache Type:', 'zippicks-business-intelligence'); ?></strong></td>
                            <td><?php echo esc_html($cache_stats['type']); ?></td>
                        </tr>
                        <?php if (isset($cache_stats['status'])): ?>
                        <tr>
                            <td><strong><?php _e('Status:', 'zippicks-business-intelligence'); ?></strong></td>
                            <td><?php echo esc_html($cache_stats['status']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($cache_stats['memory_usage'])): ?>
                        <tr>
                            <td><strong><?php _e('Memory Usage:', 'zippicks-business-intelligence'); ?></strong></td>
                            <td><?php echo esc_html($cache_stats['memory_usage']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($cache_stats['hits']) && isset($cache_stats['misses'])): ?>
                        <tr>
                            <td><strong><?php _e('Hit Rate:', 'zippicks-business-intelligence'); ?></strong></td>
                            <td>
                                <?php 
                                $total = $cache_stats['hits'] + $cache_stats['misses'];
                                $hit_rate = $total > 0 ? ($cache_stats['hits'] / $total) * 100 : 0;
                                echo number_format($hit_rate, 1) . '%';
                                ?>
                                (<?php echo number_format($cache_stats['hits']); ?> hits / <?php echo number_format($cache_stats['misses']); ?> misses)
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Errors -->
        <?php if (!empty($recent_errors)): ?>
        <div class="postbox">
            <h2 class="hndle"><?php _e('Recent API Errors', 'zippicks-business-intelligence'); ?></h2>
            <div class="inside">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'zippicks-business-intelligence'); ?></th>
                            <th><?php _e('Endpoint', 'zippicks-business-intelligence'); ?></th>
                            <th><?php _e('Method', 'zippicks-business-intelligence'); ?></th>
                            <th><?php _e('Error', 'zippicks-business-intelligence'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_errors as $error): ?>
                        <tr>
                            <td><?php echo esc_html(human_time_diff(strtotime($error['created_at']), current_time('timestamp')) . ' ago'); ?></td>
                            <td><code><?php echo esc_html($error['endpoint']); ?></code></td>
                            <td><?php echo esc_html($error['method']); ?></td>
                            <td>
                                <?php echo esc_html(substr($error['error_message'], 0, 100)); ?>
                                <?php if (strlen($error['error_message']) > 100): ?>...<?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=zippicks-bi-logs'); ?>" class="button">
                        <?php _e('View All Logs', 'zippicks-business-intelligence'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Actions -->
        <div class="postbox">
            <h2 class="hndle"><?php _e('Debug Actions', 'zippicks-business-intelligence'); ?></h2>
            <div class="inside">
                <p>
                    <button class="button" id="bi-test-api-connection">
                        <?php _e('Test API Connection', 'zippicks-business-intelligence'); ?>
                    </button>
                    <button class="button" id="bi-clear-all-cache">
                        <?php _e('Clear All Cache', 'zippicks-business-intelligence'); ?>
                    </button>
                    <button class="button" id="bi-export-debug-info">
                        <?php _e('Export Debug Info', 'zippicks-business-intelligence'); ?>
                    </button>
                </p>
                <div id="bi-debug-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
    </div>
</div>

<style>
.zippicks-bi-debug-panel .postbox {
    margin-top: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-box {
    text-align: center;
    padding: 20px;
    background: #f1f1f1;
    border-radius: 5px;
}

.stat-value {
    font-size: 2em;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 0.9em;
}

.widefat code {
    background: #f1f1f1;
    padding: 2px 4px;
    font-size: 0.9em;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Test API Connection
    $('#bi-test-api-connection').on('click', function() {
        var $button = $(this);
        var $result = $('#bi-debug-result');
        
        $button.prop('disabled', true).text('Testing...');
        $result.html('');
        
        $.get({
            url: zippicks_bi.api_url + '/health',
            success: function(response) {
                $result.html('<div class="notice notice-success"><p>✓ API connection successful!</p></div>');
            },
            error: function(xhr) {
                $result.html('<div class="notice notice-error"><p>✗ API connection failed: ' + xhr.statusText + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test API Connection');
            }
        });
    });
    
    // Clear All Cache
    $('#bi-clear-all-cache').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to clear all cache?', 'zippicks-business-intelligence')); ?>')) {
            return;
        }
        
        var $button = $(this);
        var $result = $('#bi-debug-result');
        
        $button.prop('disabled', true);
        
        $.post({
            url: zippicks_bi.ajax_url,
            data: {
                action: 'zippicks_bi_clear_cache',
                nonce: zippicks_bi.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Export Debug Info
    $('#bi-export-debug-info').on('click', function() {
        var debugData = {
            timestamp: new Date().toISOString(),
            system: <?php echo json_encode($debug_info); ?>,
            config: <?php echo json_encode($config_values); ?>,
            database: <?php echo json_encode($db_tables); ?>,
            foundation: <?php echo json_encode($foundation_services); ?>,
            api_stats: <?php echo json_encode($api_stats); ?>,
            cache_stats: <?php echo json_encode($cache_stats); ?>
        };
        
        var dataStr = JSON.stringify(debugData, null, 2);
        var dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
        
        var exportFileDefaultName = 'zippicks-bi-debug-' + Date.now() + '.json';
        
        var linkElement = document.createElement('a');
        linkElement.setAttribute('href', dataUri);
        linkElement.setAttribute('download', exportFileDefaultName);
        linkElement.click();
    });
});
</script>