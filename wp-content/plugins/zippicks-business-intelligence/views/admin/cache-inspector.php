<?php
/**
 * Cache Inspector View
 *
 * @package ZipPicks\BusinessIntelligence
 */

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current user capability
if (!current_user_can('manage_business_intelligence')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'zippicks-business-intelligence'));
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Cache Inspector', 'zippicks-business-intelligence'); ?></h1>
    
    <?php settings_errors('zippicks_bi_cache'); ?>
    
    <!-- Cache Statistics -->
    <div class="card" style="max-width: none; margin-bottom: 20px;">
        <h2><?php _e('Cache Statistics', 'zippicks-business-intelligence'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Cache Backend', 'zippicks-business-intelligence'); ?></th>
                <td>
                    <strong><?php echo ucfirst($cache_stats['backend']); ?></strong>
                    <?php if ($cache_stats['redis_available']): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                    <?php else: ?>
                        <span class="description"><?php _e('(Redis not available, using WordPress transients)', 'zippicks-business-intelligence'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Total Entries', 'zippicks-business-intelligence'); ?></th>
                <td><strong><?php echo count($detailed_entries); ?></strong></td>
            </tr>
            <?php if (isset($cache_stats['redis_memory'])): ?>
            <tr>
                <th scope="row"><?php _e('Redis Memory Usage', 'zippicks-business-intelligence'); ?></th>
                <td><strong><?php echo esc_html($cache_stats['redis_memory']); ?></strong></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th scope="row"><?php _e('Default TTL', 'zippicks-business-intelligence'); ?></th>
                <td><strong><?php echo $cache_stats['default_ttl']; ?></strong> <?php _e('seconds', 'zippicks-business-intelligence'); ?></td>
            </tr>
        </table>
    </div>
    
    <!-- Search and Actions -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" action="" style="display: inline-block;">
                <input type="hidden" name="page" value="zippicks-bi-cache" />
                <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search keys...', 'zippicks-business-intelligence'); ?>" />
                <input type="submit" class="button" value="<?php esc_attr_e('Search', 'zippicks-business-intelligence'); ?>" />
                <?php if ($search): ?>
                    <a href="<?php echo admin_url('admin.php?page=zippicks-bi-cache'); ?>" class="button"><?php _e('Clear', 'zippicks-business-intelligence'); ?></a>
                <?php endif; ?>
            </form>
            
            <form method="post" action="" style="display: inline-block; margin-left: 10px;">
                <?php wp_nonce_field('zippicks_bi_cache_action'); ?>
                <input type="hidden" name="action" value="clear_pattern" />
                <input type="text" name="cache_pattern" placeholder="<?php esc_attr_e('Pattern (e.g., restaurant_*)', 'zippicks-business-intelligence'); ?>" />
                <input type="submit" class="button" value="<?php esc_attr_e('Clear by Pattern', 'zippicks-business-intelligence'); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all cache entries matching this pattern?', 'zippicks-business-intelligence'); ?>');" />
            </form>
        </div>
        
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo sprintf(_n('%s item', '%s items', count($detailed_entries), 'zippicks-business-intelligence'), number_format_i18n(count($detailed_entries))); ?></span>
        </div>
    </div>
    
    <!-- Cache Entries Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="column-key"><?php _e('Cache Key', 'zippicks-business-intelligence'); ?></th>
                <th scope="col" class="column-type" style="width: 100px;"><?php _e('Type', 'zippicks-business-intelligence'); ?></th>
                <th scope="col" class="column-size" style="width: 100px;"><?php _e('Size', 'zippicks-business-intelligence'); ?></th>
                <th scope="col" class="column-ttl" style="width: 100px;"><?php _e('TTL', 'zippicks-business-intelligence'); ?></th>
                <th scope="col" class="column-backend" style="width: 100px;"><?php _e('Backend', 'zippicks-business-intelligence'); ?></th>
                <th scope="col" class="column-actions" style="width: 100px;"><?php _e('Actions', 'zippicks-business-intelligence'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($detailed_entries)): ?>
                <tr>
                    <td colspan="6" class="no-items"><?php _e('No cache entries found.', 'zippicks-business-intelligence'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($detailed_entries as $entry): ?>
                    <tr>
                        <td class="column-key">
                            <strong><?php echo esc_html($entry['key']); ?></strong>
                            <br />
                            <span class="description"><?php _e('Type:', 'zippicks-business-intelligence'); ?> <?php echo esc_html($entry['key_type']); ?></span>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="#" class="view-cache-value" data-key="<?php echo esc_attr($entry['key']); ?>"><?php _e('View Value', 'zippicks-business-intelligence'); ?></a>
                                </span>
                            </div>
                        </td>
                        <td class="column-type"><?php echo esc_html($entry['type']); ?></td>
                        <td class="column-size"><?php echo zippicks_bi_format_bytes($entry['size']); ?></td>
                        <td class="column-ttl">
                            <?php 
                            if ($entry['ttl'] == -1) {
                                echo __('No expiry', 'zippicks-business-intelligence');
                            } elseif ($entry['ttl'] == 0) {
                                echo '<span style="color: #dc3232;">' . __('Expired', 'zippicks-business-intelligence') . '</span>';
                            } else {
                                echo human_time_diff(time() + $entry['ttl'], time());
                            }
                            ?>
                        </td>
                        <td class="column-backend"><?php echo ucfirst($entry['backend']); ?></td>
                        <td class="column-actions">
                            <form method="post" action="" style="display: inline;">
                                <?php wp_nonce_field('zippicks_bi_cache_action'); ?>
                                <input type="hidden" name="action" value="delete_cache_key" />
                                <input type="hidden" name="cache_key" value="<?php echo esc_attr($entry['key']); ?>" />
                                <button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this cache entry?', 'zippicks-business-intelligence'); ?>');">
                                    <?php _e('Delete', 'zippicks-business-intelligence'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <tr class="cache-value-row" id="value-<?php echo esc_attr($entry['key']); ?>" style="display: none;">
                        <td colspan="6">
                            <div style="background: #f7f7f7; padding: 10px; border: 1px solid #e1e1e1; max-height: 300px; overflow: auto;">
                                <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;"><?php 
                                    echo esc_html(json_encode($entry['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); 
                                ?></pre>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// Add helper function for formatting bytes
if (!function_exists('zippicks_bi_format_bytes')) {
    function zippicks_bi_format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
?>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Toggle cache value visibility
    $('.view-cache-value').on('click', function(e) {
        e.preventDefault();
        var key = $(this).data('key');
        $('#value-' + key).toggle();
        
        if ($('#value-' + key).is(':visible')) {
            $(this).text('<?php _e('Hide Value', 'zippicks-business-intelligence'); ?>');
        } else {
            $(this).text('<?php _e('View Value', 'zippicks-business-intelligence'); ?>');
        }
    });
});
</script>

<style type="text/css">
.cache-value-row td {
    padding: 0 !important;
}
.column-key {
    width: 40%;
}
.view-cache-value {
    cursor: pointer;
}
</style>