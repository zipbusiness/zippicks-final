<?php
/**
 * API Logs View
 *
 * @package ZipPicks\BusinessIntelligence
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get filter values
$filter_endpoint = isset($_GET['filter_endpoint']) ? sanitize_text_field($_GET['filter_endpoint']) : '';
$filter_method = isset($_GET['filter_method']) ? sanitize_text_field($_GET['filter_method']) : '';
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
$filter_date = isset($_GET['filter_date']) ? sanitize_text_field($_GET['filter_date']) : '';
?>

<div class="wrap">
    <h1>
        <?php _e('API Logs', 'zippicks-business-intelligence'); ?>
        <a href="#" class="page-title-action" id="bi-refresh-logs"><?php _e('Refresh', 'zippicks-business-intelligence'); ?></a>
        <a href="#" class="page-title-action" id="bi-export-logs"><?php _e('Export', 'zippicks-business-intelligence'); ?></a>
    </h1>
    
    <!-- Filters -->
    <div class="tablenav top">
        <form method="get" action="" class="alignleft">
            <input type="hidden" name="page" value="zippicks-bi-logs" />
            
            <select name="filter_endpoint" id="filter_endpoint">
                <option value=""><?php _e('All Endpoints', 'zippicks-business-intelligence'); ?></option>
                <option value="/restaurants/by-zpid" <?php selected($filter_endpoint, '/restaurants/by-zpid'); ?>>/restaurants/by-zpid</option>
                <option value="/restaurants/by-location" <?php selected($filter_endpoint, '/restaurants/by-location'); ?>>/restaurants/by-location</option>
                <option value="/restaurants/search" <?php selected($filter_endpoint, '/restaurants/search'); ?>>/restaurants/search</option>
                <option value="/businesses/collect" <?php selected($filter_endpoint, '/businesses/collect'); ?>>/businesses/collect</option>
            </select>
            
            <select name="filter_method" id="filter_method">
                <option value=""><?php _e('All Methods', 'zippicks-business-intelligence'); ?></option>
                <option value="GET" <?php selected($filter_method, 'GET'); ?>>GET</option>
                <option value="POST" <?php selected($filter_method, 'POST'); ?>>POST</option>
            </select>
            
            <select name="filter_status" id="filter_status">
                <option value=""><?php _e('All Statuses', 'zippicks-business-intelligence'); ?></option>
                <option value="success" <?php selected($filter_status, 'success'); ?>><?php _e('Success (2xx)', 'zippicks-business-intelligence'); ?></option>
                <option value="error" <?php selected($filter_status, 'error'); ?>><?php _e('Error (4xx/5xx)', 'zippicks-business-intelligence'); ?></option>
                <option value="failed" <?php selected($filter_status, 'failed'); ?>><?php _e('Failed (No Response)', 'zippicks-business-intelligence'); ?></option>
            </select>
            
            <select name="filter_date" id="filter_date">
                <option value=""><?php _e('All Time', 'zippicks-business-intelligence'); ?></option>
                <option value="today" <?php selected($filter_date, 'today'); ?>><?php _e('Today', 'zippicks-business-intelligence'); ?></option>
                <option value="yesterday" <?php selected($filter_date, 'yesterday'); ?>><?php _e('Yesterday', 'zippicks-business-intelligence'); ?></option>
                <option value="week" <?php selected($filter_date, 'week'); ?>><?php _e('Last 7 Days', 'zippicks-business-intelligence'); ?></option>
                <option value="month" <?php selected($filter_date, 'month'); ?>><?php _e('Last 30 Days', 'zippicks-business-intelligence'); ?></option>
            </select>
            
            <input type="submit" class="button" value="<?php _e('Filter', 'zippicks-business-intelligence'); ?>" />
            
            <?php if ($filter_endpoint || $filter_method || $filter_status || $filter_date): ?>
                <a href="<?php echo admin_url('admin.php?page=zippicks-bi-logs'); ?>" class="button">
                    <?php _e('Clear Filters', 'zippicks-business-intelligence'); ?>
                </a>
            <?php endif; ?>
        </form>
        
        <div class="alignright">
            <form method="post" action="" style="display: inline;">
                <?php wp_nonce_field('bi_clear_logs', 'bi_logs_nonce'); ?>
                <input type="hidden" name="action" value="clear_logs" />
                <input type="submit" class="button" value="<?php _e('Clear Old Logs', 'zippicks-business-intelligence'); ?>" 
                       onclick="return confirm('<?php echo esc_js(__('Clear logs older than 30 days?', 'zippicks-business-intelligence')); ?>');" />
            </form>
        </div>
    </div>
    
    <!-- Logs Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="column-time"><?php _e('Time', 'zippicks-business-intelligence'); ?></th>
                <th scope="col" class="column-endpoint"><?php _e('Endpoint', 'zippicks-business-intelligence'); ?></th>
                <th scope="col" class="column-method"><?php _e('Method', 'zippicks-business-intelligence'); ?></th>
                <th scope="col" class="column-status"><?php _e('Status', 'zippicks-business-intelligence'); ?></th>
                <th scope="col" class="column-response-time"><?php _e('Response Time', 'zippicks-business-intelligence'); ?></th>
                <th scope="col" class="column-details"><?php _e('Details', 'zippicks-business-intelligence'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">
                        <?php _e('No logs found.', 'zippicks-business-intelligence'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr class="log-row <?php echo $log['error_message'] ? 'has-error' : ''; ?>">
                        <td class="column-time">
                            <?php 
                            $timestamp = strtotime($log['created_at']);
                            echo '<span title="' . esc_attr($log['created_at']) . '">';
                            echo esc_html(human_time_diff($timestamp, current_time('timestamp')) . ' ago');
                            echo '</span>';
                            ?>
                        </td>
                        <td class="column-endpoint">
                            <code><?php echo esc_html($log['endpoint']); ?></code>
                        </td>
                        <td class="column-method">
                            <span class="method-badge method-<?php echo strtolower($log['method']); ?>">
                                <?php echo esc_html($log['method']); ?>
                            </span>
                        </td>
                        <td class="column-status">
                            <?php if ($log['status_code'] > 0): ?>
                                <span class="status-badge status-<?php echo substr($log['status_code'], 0, 1); ?>xx">
                                    <?php echo esc_html($log['status_code']); ?>
                                </span>
                            <?php elseif ($log['error_message']): ?>
                                <span class="status-badge status-error">Failed</span>
                            <?php else: ?>
                                <span class="status-badge">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-response-time">
                            <?php if ($log['response_time'] > 0): ?>
                                <span class="<?php echo $log['response_time'] > 1 ? 'slow-response' : ''; ?>">
                                    <?php echo number_format($log['response_time'], 3); ?>s
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="column-details">
                            <?php if ($log['error_message']): ?>
                                <a href="#" class="view-error" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                    <?php _e('View Error', 'zippicks-business-intelligence'); ?>
                                </a>
                                <div class="error-details" id="error-<?php echo esc_attr($log['id']); ?>" style="display: none;">
                                    <strong><?php _e('Error:', 'zippicks-business-intelligence'); ?></strong><br/>
                                    <?php echo esc_html($log['error_message']); ?>
                                    <?php if ($log['context']): ?>
                                        <br/><br/>
                                        <strong><?php _e('Context:', 'zippicks-business-intelligence'); ?></strong><br/>
                                        <pre><?php echo esc_html(print_r(json_decode($log['context'], true), true)); ?></pre>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($log['request_params']): ?>
                                <a href="#" class="view-params" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                    <?php _e('View Params', 'zippicks-business-intelligence'); ?>
                                </a>
                                <div class="params-details" id="params-<?php echo esc_attr($log['id']); ?>" style="display: none;">
                                    <strong><?php _e('Request Parameters:', 'zippicks-business-intelligence'); ?></strong><br/>
                                    <pre><?php echo esc_html(print_r(json_decode($log['request_params'], true), true)); ?></pre>
                                </div>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php printf(
                    _n('%s item', '%s items', $total_items, 'zippicks-business-intelligence'),
                    number_format_i18n($total_items)
                ); ?>
            </span>
            
            <?php if ($total_pages > 1): ?>
                <?php
                $pagination_args = [
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'total' => $total_pages,
                    'current' => $page,
                    'show_all' => false,
                    'end_size' => 1,
                    'mid_size' => 2,
                    'prev_next' => true,
                    'prev_text' => __('&laquo; Previous', 'zippicks-business-intelligence'),
                    'next_text' => __('Next &raquo;', 'zippicks-business-intelligence'),
                    'type' => 'plain',
                    'add_args' => false,
                    'add_fragment' => ''
                ];
                
                echo paginate_links($pagination_args);
                ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.column-time { width: 120px; }
.column-endpoint { width: 250px; }
.column-method { width: 80px; }
.column-status { width: 80px; }
.column-response-time { width: 100px; }
.column-details { width: auto; }

.method-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}
.method-get { background: #e7f3ff; color: #0073aa; }
.method-post { background: #fff3cd; color: #856404; }

.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
}
.status-2xx { background: #d4edda; color: #155724; }
.status-3xx { background: #d1ecf1; color: #0c5460; }
.status-4xx { background: #fff3cd; color: #856404; }
.status-5xx { background: #f8d7da; color: #721c24; }
.status-error { background: #f8d7da; color: #721c24; }

.slow-response { color: #dc3232; font-weight: bold; }
.has-error { background-color: #fcf3f2 !important; }

.error-details, .params-details {
    margin-top: 10px;
    padding: 10px;
    background: #f1f1f1;
    border-radius: 3px;
    font-size: 12px;
}

.error-details pre, .params-details pre {
    margin: 5px 0 0 0;
    padding: 5px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 3px;
    overflow-x: auto;
}
</style>

<script>
jQuery(document).ready(function($) {
    // View error details
    $('.view-error').on('click', function(e) {
        e.preventDefault();
        var logId = $(this).data('log-id');
        $('#error-' + logId).slideToggle();
    });
    
    // View params details
    $('.view-params').on('click', function(e) {
        e.preventDefault();
        var logId = $(this).data('log-id');
        $('#params-' + logId).slideToggle();
    });
    
    // Refresh logs
    $('#bi-refresh-logs').on('click', function(e) {
        e.preventDefault();
        location.reload();
    });
    
    // Export logs
    $('#bi-export-logs').on('click', function(e) {
        e.preventDefault();
        
        var logs = <?php echo json_encode($logs); ?>;
        var csvContent = "Time,Endpoint,Method,Status,Response Time,Error\n";
        
        logs.forEach(function(log) {
            var row = [
                log.created_at,
                log.endpoint,
                log.method,
                log.status_code || 'Failed',
                log.response_time || '-',
                log.error_message ? '"' + log.error_message.replace(/"/g, '""') + '"' : ''
            ];
            csvContent += row.join(',') + "\n";
        });
        
        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement("a");
        var url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", "zippicks-bi-logs-" + Date.now() + ".csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
});
</script>