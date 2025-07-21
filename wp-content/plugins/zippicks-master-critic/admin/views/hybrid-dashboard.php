<?php
/**
 * Hybrid Data System Dashboard
 *
 * @package ZipPicks_Master_Critic
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Get service instances from the service provider
use ZipPicks\MasterCritic\Hybrid\HybridServiceProvider;
$cost_optimizer = HybridServiceProvider::get_cost_optimizer();
$smart_router = HybridServiceProvider::get_smart_router();
$confidence_engine = HybridServiceProvider::get_confidence_engine();

// Get dashboard metrics
$metrics = $cost_optimizer->get_dashboard_metrics();
$router_stats = $smart_router->get_statistics();
$cache_stats = get_option('zippicks_cache_daily_stats', []);
$today_stats = $cache_stats[date('Y-m-d')] ?? ['hit_rate' => 0];

// Calculate 7-day averages
$seven_day_stats = array_slice($cache_stats, -7, 7, true);
$avg_hit_rate = !empty($seven_day_stats) ? 
    array_sum(array_column($seven_day_stats, 'hit_rate')) / count($seven_day_stats) : 0;

?>

<div class="wrap zippicks-hybrid-dashboard">
    <h1>
        <?php _e('ZipPicks Hybrid Data System', 'zippicks-master-critic'); ?>
        <span class="dashicons dashicons-update spin" style="display:none;" id="refresh-spinner"></span>
    </h1>
    
    <!-- System Status Overview -->
    <div class="zippicks-status-bar">
        <div class="status-item <?php echo $metrics['budget']['status']; ?>">
            <span class="dashicons dashicons-chart-area"></span>
            <?php _e('System Status:', 'zippicks-master-critic'); ?>
            <strong><?php echo ucfirst($metrics['budget']['status']); ?></strong>
        </div>
        <div class="status-item">
            <span class="dashicons dashicons-backup"></span>
            <?php _e('Cache Hit Rate:', 'zippicks-master-critic'); ?>
            <strong><?php echo number_format($today_stats['hit_rate'], 1); ?>%</strong>
            <?php if ($today_stats['hit_rate'] >= 90): ?>
                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
            <?php endif; ?>
        </div>
        <div class="status-item">
            <span class="dashicons dashicons-clock"></span>
            <?php _e('Last Update:', 'zippicks-master-critic'); ?>
            <strong><?php echo human_time_diff(strtotime($metrics['last_update'])); ?> ago</strong>
        </div>
    </div>

    <!-- Cost Metrics Dashboard -->
    <div class="zippicks-dashboard-grid">
        
        <!-- Daily Cost Overview -->
        <div class="dashboard-widget">
            <h2><?php _e('Cost Optimization', 'zippicks-master-critic'); ?></h2>
            <div class="cost-metrics">
                <div class="metric-row">
                    <span class="metric-label"><?php _e('Today\'s Cost:', 'zippicks-master-critic'); ?></span>
                    <span class="metric-value">$<?php echo number_format($metrics['costs']['daily'], 2); ?></span>
                    <span class="metric-target"><?php _e('(Target: <$6.67/day)', 'zippicks-master-critic'); ?></span>
                </div>
                <div class="metric-row">
                    <span class="metric-label"><?php _e('Monthly Projection:', 'zippicks-master-critic'); ?></span>
                    <span class="metric-value">$<?php echo number_format($metrics['costs']['monthly_projection'], 2); ?></span>
                    <span class="metric-target"><?php _e('(Target: <$50/month)', 'zippicks-master-critic'); ?></span>
                </div>
                <div class="metric-row">
                    <span class="metric-label"><?php _e('Budget Used:', 'zippicks-master-critic'); ?></span>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $metrics['budget']['percentage']; ?>%;">
                            <?php echo $metrics['budget']['percentage']; ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Usage Statistics -->
        <div class="dashboard-widget">
            <h2><?php _e('API Usage', 'zippicks-master-critic'); ?></h2>
            <div class="api-stats">
                <h3><?php _e('Free APIs', 'zippicks-master-critic'); ?></h3>
                <ul class="api-list free-apis">
                    <li>
                        <span class="api-name">OpenStreetMap</span>
                        <span class="api-calls"><?php echo number_format($metrics['api_usage']['osm']['calls']); ?> calls</span>
                    </li>
                    <li>
                        <span class="api-name">Wikidata</span>
                        <span class="api-calls"><?php echo number_format($metrics['api_usage']['wikidata']['calls']); ?> calls</span>
                    </li>
                    <li>
                        <span class="api-name">Government APIs</span>
                        <span class="api-calls"><?php echo number_format($metrics['api_usage']['government']['calls']); ?> calls</span>
                    </li>
                </ul>
                
                <h3><?php _e('Paid APIs', 'zippicks-master-critic'); ?></h3>
                <ul class="api-list paid-apis">
                    <li>
                        <span class="api-name">Google Places</span>
                        <span class="api-calls"><?php echo number_format($metrics['api_usage']['google']['calls']); ?> calls</span>
                        <span class="api-cost">$<?php echo number_format($metrics['api_usage']['google']['cost'], 2); ?></span>
                    </li>
                    <li>
                        <span class="api-name">Yelp Fusion</span>
                        <span class="api-calls"><?php echo number_format($metrics['api_usage']['yelp']['calls']); ?> calls</span>
                        <span class="api-cost">$<?php echo number_format($metrics['api_usage']['yelp']['cost'], 2); ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Query Performance -->
        <div class="dashboard-widget">
            <h2><?php _e('Query Performance', 'zippicks-master-critic'); ?></h2>
            <div class="performance-metrics">
                <div class="metric-row">
                    <span class="metric-label"><?php _e('Cache Hit Rate:', 'zippicks-master-critic'); ?></span>
                    <span class="metric-value <?php echo $today_stats['hit_rate'] >= 90 ? 'success' : 'warning'; ?>">
                        <?php echo number_format($today_stats['hit_rate'], 1); ?>%
                    </span>
                </div>
                <div class="metric-row">
                    <span class="metric-label"><?php _e('7-Day Average:', 'zippicks-master-critic'); ?></span>
                    <span class="metric-value"><?php echo number_format($avg_hit_rate, 1); ?>%</span>
                </div>
                <div class="metric-row">
                    <span class="metric-label"><?php _e('Enhancement Rate:', 'zippicks-master-critic'); ?></span>
                    <span class="metric-value <?php echo $metrics['performance']['enhancement_rate'] <= 20 ? 'success' : 'warning'; ?>">
                        <?php echo number_format($metrics['performance']['enhancement_rate'], 1); ?>%
                    </span>
                    <span class="metric-target"><?php _e('(Target: <20%)', 'zippicks-master-critic'); ?></span>
                </div>
                <div class="metric-row">
                    <span class="metric-label"><?php _e('Avg Response Time:', 'zippicks-master-critic'); ?></span>
                    <span class="metric-value"><?php echo number_format($metrics['performance']['avg_response_time'], 0); ?>ms</span>
                    <span class="metric-target"><?php _e('(Target: <1000ms)', 'zippicks-master-critic'); ?></span>
                </div>
            </div>
        </div>

        <!-- Optimization Recommendations -->
        <div class="dashboard-widget full-width">
            <h2><?php _e('Optimization Recommendations', 'zippicks-master-critic'); ?></h2>
            <div class="recommendations">
                <?php if (!empty($metrics['recommendations'])): ?>
                    <ul class="recommendation-list">
                        <?php foreach ($metrics['recommendations'] as $rec): ?>
                            <li class="recommendation-item priority-<?php echo $rec['priority']; ?>">
                                <span class="dashicons <?php echo $rec['icon']; ?>"></span>
                                <div class="recommendation-content">
                                    <strong><?php echo esc_html($rec['title']); ?></strong>
                                    <p><?php echo esc_html($rec['description']); ?></p>
                                    <?php if (!empty($rec['action'])): ?>
                                        <button class="button button-small" onclick="<?php echo esc_attr($rec['action']); ?>">
                                            <?php echo esc_html($rec['action_label']); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-recommendations">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('System is optimally configured. No recommendations at this time.', 'zippicks-master-critic'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Queries -->
        <div class="dashboard-widget">
            <h2><?php _e('Recent Queries', 'zippicks-master-critic'); ?></h2>
            <div class="recent-queries">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Query', 'zippicks-master-critic'); ?></th>
                            <th><?php _e('Source', 'zippicks-master-critic'); ?></th>
                            <th><?php _e('Cost', 'zippicks-master-critic'); ?></th>
                            <th><?php _e('Time', 'zippicks-master-critic'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metrics['recent_queries'] as $query): ?>
                            <tr>
                                <td><?php echo esc_html($query['business_name'] . ', ' . $query['city']); ?></td>
                                <td>
                                    <span class="source-badge <?php echo $query['source']; ?>">
                                        <?php echo ucfirst($query['source']); ?>
                                    </span>
                                </td>
                                <td>$<?php echo number_format($query['cost'], 4); ?></td>
                                <td><?php echo human_time_diff(strtotime($query['timestamp'])); ?> ago</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Health -->
        <div class="dashboard-widget">
            <h2><?php _e('System Health', 'zippicks-master-critic'); ?></h2>
            <div class="system-health">
                <div class="health-item <?php echo $metrics['health']['database'] ? 'healthy' : 'warning'; ?>">
                    <span class="dashicons dashicons-database"></span>
                    <?php _e('Database Tables:', 'zippicks-master-critic'); ?>
                    <strong><?php echo $metrics['health']['database'] ? __('Healthy', 'zippicks-master-critic') : __('Issues Detected', 'zippicks-master-critic'); ?></strong>
                </div>
                <div class="health-item <?php echo $metrics['health']['api_keys'] ? 'healthy' : 'warning'; ?>">
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php _e('API Keys:', 'zippicks-master-critic'); ?>
                    <strong><?php echo $metrics['health']['api_keys'] ? __('Configured', 'zippicks-master-critic') : __('Missing', 'zippicks-master-critic'); ?></strong>
                </div>
                <div class="health-item <?php echo $metrics['health']['cache'] ? 'healthy' : 'warning'; ?>">
                    <span class="dashicons dashicons-performance"></span>
                    <?php _e('Cache System:', 'zippicks-master-critic'); ?>
                    <strong><?php echo $metrics['health']['cache'] ? __('Active', 'zippicks-master-critic') : __('Inactive', 'zippicks-master-critic'); ?></strong>
                </div>
                <div class="health-item <?php echo $metrics['health']['rate_limits'] ? 'healthy' : 'warning'; ?>">
                    <span class="dashicons dashicons-clock"></span>
                    <?php _e('Rate Limits:', 'zippicks-master-critic'); ?>
                    <strong><?php echo $metrics['health']['rate_limits'] ? __('Within Limits', 'zippicks-master-critic') : __('Exceeded', 'zippicks-master-critic'); ?></strong>
                </div>
            </div>
        </div>

    </div>

    <!-- Action Buttons -->
    <div class="dashboard-actions">
        <button class="button button-primary" id="refresh-dashboard">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Refresh Dashboard', 'zippicks-master-critic'); ?>
        </button>
        <button class="button" id="warm-cache">
            <span class="dashicons dashicons-performance"></span>
            <?php _e('Warm Cache', 'zippicks-master-critic'); ?>
        </button>
        <button class="button" id="clear-stats">
            <span class="dashicons dashicons-trash"></span>
            <?php _e('Clear Statistics', 'zippicks-master-critic'); ?>
        </button>
        <a href="<?php echo admin_url('admin.php?page=zippicks-settings'); ?>" class="button">
            <span class="dashicons dashicons-admin-generic"></span>
            <?php _e('Configure APIs', 'zippicks-master-critic'); ?>
        </a>
    </div>
    
</div>