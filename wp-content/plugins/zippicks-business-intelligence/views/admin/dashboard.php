<?php
/**
 * Admin Dashboard View
 *
 * @package ZipPicks\BusinessIntelligence
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Business Intelligence Dashboard', 'zippicks-business-intelligence'); ?></h1>
    
    <?php if (!empty($config_errors)): ?>
        <div class="notice notice-error">
            <p><strong><?php _e('Configuration Issues:', 'zippicks-business-intelligence'); ?></strong></p>
            <ul>
                <?php foreach ($config_errors as $error): ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <p>
                <a href="<?php echo admin_url('admin.php?page=zippicks-bi-settings'); ?>" class="button">
                    <?php _e('Go to Settings', 'zippicks-business-intelligence'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Status Cards -->
    <div class="zippicks-bi-cards">
        <!-- API Status Card -->
        <div class="card">
            <h2><?php _e('API Status', 'zippicks-business-intelligence'); ?></h2>
            <div class="card-content">
                <div class="status-indicator <?php echo $api_health['status'] === 'healthy' ? 'healthy' : 'unhealthy'; ?>">
                    <span class="dashicons <?php echo $api_health['status'] === 'healthy' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                    <?php echo ucfirst($api_health['status']); ?>
                </div>
                <?php if (isset($api_health['response_time'])): ?>
                    <p><?php printf(__('Response Time: %sms', 'zippicks-business-intelligence'), $api_health['response_time']); ?></p>
                <?php endif; ?>
                <?php if (isset($api_health['api_version'])): ?>
                    <p><?php printf(__('API Version: %s', 'zippicks-business-intelligence'), $api_health['api_version']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Cache Status Card -->
        <div class="card">
            <h2><?php _e('Cache Status', 'zippicks-business-intelligence'); ?></h2>
            <div class="card-content">
                <div class="status-indicator <?php echo $cache_health['status'] === 'healthy' ? 'healthy' : 'unhealthy'; ?>">
                    <span class="dashicons <?php echo $cache_health['status'] === 'healthy' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                    <?php echo ucfirst($cache_health['status']); ?>
                </div>
                <p><?php printf(__('Backend: %s', 'zippicks-business-intelligence'), ucfirst($cache_health['backend'])); ?></p>
                <p><?php printf(__('Cached Items: %s', 'zippicks-business-intelligence'), number_format($stats['cache_stats']['db_cache_count'])); ?></p>
                <button class="button button-secondary" id="clear-cache-btn">
                    <?php _e('Clear All Cache', 'zippicks-business-intelligence'); ?>
                </button>
            </div>
        </div>
        
        <!-- Statistics Card -->
        <div class="card">
            <h2><?php _e('Statistics', 'zippicks-business-intelligence'); ?></h2>
            <div class="card-content">
                <table class="stats-table">
                    <tr>
                        <td><?php _e('Total Businesses:', 'zippicks-business-intelligence'); ?></td>
                        <td><strong><?php echo number_format($stats['total_businesses_cached']); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('Total Cities:', 'zippicks-business-intelligence'); ?></td>
                        <td><strong><?php echo number_format($stats['total_cities']); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('API Requests Today:', 'zippicks-business-intelligence'); ?></td>
                        <td><strong><?php echo number_format($stats['api_requests_today']); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('API Errors Today:', 'zippicks-business-intelligence'); ?></td>
                        <td><strong class="<?php echo $stats['api_errors_today'] > 0 ? 'error' : ''; ?>">
                            <?php echo number_format($stats['api_errors_today']); ?>
                        </strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('Avg Response Time:', 'zippicks-business-intelligence'); ?></td>
                        <td><strong><?php echo number_format($stats['avg_api_response_time'], 3); ?>s</strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="zippicks-bi-actions">
        <h2><?php _e('Quick Actions', 'zippicks-business-intelligence'); ?></h2>
        <div class="action-buttons">
            <a href="<?php echo admin_url('admin.php?page=zippicks-bi-cities'); ?>" class="button button-primary">
                <?php _e('Manage Cities', 'zippicks-business-intelligence'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=zippicks-bi-logs'); ?>" class="button">
                <?php _e('View API Logs', 'zippicks-business-intelligence'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=zippicks-bi-settings'); ?>" class="button">
                <?php _e('Settings', 'zippicks-business-intelligence'); ?>
            </a>
        </div>
    </div>
    
    <!-- City Search -->
    <div class="zippicks-bi-search">
        <h2><?php _e('Search City', 'zippicks-business-intelligence'); ?></h2>
        <div class="search-form">
            <input type="text" 
                   id="city-search" 
                   placeholder="<?php esc_attr_e('Enter city name...', 'zippicks-business-intelligence'); ?>" 
                   class="regular-text" />
            <button class="button button-primary" id="search-city-btn">
                <?php _e('Search', 'zippicks-business-intelligence'); ?>
            </button>
        </div>
        <div id="search-results" style="display: none;">
            <h3><?php _e('Search Results', 'zippicks-business-intelligence'); ?></h3>
            <div id="results-content"></div>
        </div>
    </div>
</div>

<style>
.zippicks-bi-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.zippicks-bi-cards .card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.zippicks-bi-cards .card h2 {
    margin-top: 0;
    font-size: 18px;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.status-indicator {
    display: flex;
    align-items: center;
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 10px;
}

.status-indicator .dashicons {
    margin-right: 5px;
    font-size: 20px;
}

.status-indicator.healthy {
    color: #46b450;
}

.status-indicator.unhealthy {
    color: #dc3232;
}

.stats-table {
    width: 100%;
}

.stats-table td {
    padding: 5px 0;
}

.stats-table td:first-child {
    color: #666;
}

.stats-table .error {
    color: #dc3232;
}

.zippicks-bi-actions {
    margin: 30px 0;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.zippicks-bi-search {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-top: 20px;
}

.search-form {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

#search-results {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}
</style>