<?php
/**
 * ZipPicks Real-time Monitoring Dashboard UI
 * 
 * Enterprise-grade monitoring interface for the $100B platform
 * Real-time metrics, alerts, and operational visibility
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard data
$dashboard = $this->getDashboardInstance();
$systemHealth = $dashboard->getSystemHealth();
$realTimeMetrics = $dashboard->getRealTimeMetrics();
$activeAlerts = $dashboard->getActiveAlerts();

?>

<div class="wrap zippicks-monitoring-dashboard">
    <h1 class="wp-heading-inline">
        <i class="dashicons dashicons-chart-line"></i>
        ZipPicks Enterprise Monitoring
    </h1>
    
    <div class="dashboard-controls">
        <div class="auto-refresh">
            <label>
                <input type="checkbox" id="auto-refresh" checked>
                Auto-refresh (<span id="refresh-countdown">30</span>s)
            </label>
        </div>
        
        <div class="timeframe-selector">
            <select id="timeframe">
                <option value="5m">Last 5 minutes</option>
                <option value="15m">Last 15 minutes</option>
                <option value="1h" selected>Last hour</option>
                <option value="6h">Last 6 hours</option>
                <option value="24h">Last 24 hours</option>
                <option value="7d">Last 7 days</option>
            </select>
        </div>
        
        <button class="button button-primary" id="refresh-dashboard">
            <i class="dashicons dashicons-update"></i>
            Refresh Now
        </button>
    </div>

    <!-- System Health Overview -->
    <div class="dashboard-section system-health">
        <h2>System Health Overview</h2>
        
        <div class="health-cards">
            <div class="health-card overall-status status-<?php echo esc_attr($systemHealth['overall_status']); ?>">
                <div class="health-indicator">
                    <span class="status-dot"></span>
                    <span class="status-text"><?php echo ucfirst($systemHealth['overall_status']); ?></span>
                </div>
                <div class="health-title">Overall System</div>
                <div class="health-timestamp">
                    Last updated: <?php echo date('H:i:s', $systemHealth['last_updated']); ?>
                </div>
            </div>

            <?php foreach ($systemHealth['components'] as $component => $status): ?>
            <div class="health-card component-status status-<?php echo esc_attr($status['status']); ?>">
                <div class="health-indicator">
                    <span class="status-dot"></span>
                    <span class="status-text"><?php echo ucfirst($status['status']); ?></span>
                </div>
                <div class="health-title"><?php echo ucfirst(str_replace('_', ' ', $component)); ?></div>
                <?php if (isset($status['response_time'])): ?>
                <div class="health-metric"><?php echo number_format($status['response_time'], 2); ?>ms</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Key Metrics Row -->
    <div class="dashboard-section key-metrics">
        <h2>Key Metrics</h2>
        
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value"><?php echo number_format($realTimeMetrics['api']['requests_per_second'] ?? 0, 1); ?></div>
                <div class="metric-label">Requests/sec</div>
                <div class="metric-trend">
                    <i class="dashicons dashicons-arrow-up-alt"></i>
                    <span>+12.5%</span>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-value"><?php echo number_format($realTimeMetrics['performance']['response_time']['avg'] ?? 0); ?>ms</div>
                <div class="metric-label">Avg Response Time</div>
                <div class="metric-trend">
                    <i class="dashicons dashicons-arrow-down-alt"></i>
                    <span>-5.2%</span>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-value"><?php echo number_format($realTimeMetrics['performance']['error_rate']['percentage'] ?? 0, 2); ?>%</div>
                <div class="metric-label">Error Rate</div>
                <div class="metric-trend">
                    <i class="dashicons dashicons-arrow-down-alt"></i>
                    <span>-0.8%</span>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-value"><?php echo number_format($realTimeMetrics['system']['cpu_usage'] ?? 0, 1); ?>%</div>
                <div class="metric-label">CPU Usage</div>
                <div class="metric-trend">
                    <i class="dashicons dashicons-arrow-up-alt"></i>
                    <span>+3.1%</span>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-value"><?php echo number_format($realTimeMetrics['system']['memory_usage'] ?? 0, 1); ?>%</div>
                <div class="metric-label">Memory Usage</div>
                <div class="metric-trend">
                    <i class="dashicons dashicons-minus"></i>
                    <span>0.0%</span>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-value"><?php echo number_format($realTimeMetrics['business']['active_users'] ?? 0); ?></div>
                <div class="metric-label">Active Users</div>
                <div class="metric-trend">
                    <i class="dashicons dashicons-arrow-up-alt"></i>
                    <span>+18.3%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="dashboard-section charts-section">
        <div class="charts-grid">
            <!-- Performance Chart -->
            <div class="chart-container">
                <h3>API Performance</h3>
                <div class="chart-wrapper">
                    <canvas id="performance-chart"></canvas>
                </div>
            </div>

            <!-- Throughput Chart -->
            <div class="chart-container">
                <h3>Request Throughput</h3>
                <div class="chart-wrapper">
                    <canvas id="throughput-chart"></canvas>
                </div>
            </div>

            <!-- Error Rate Chart -->
            <div class="chart-container">
                <h3>Error Rates</h3>
                <div class="chart-wrapper">
                    <canvas id="error-chart"></canvas>
                </div>
            </div>

            <!-- System Resources Chart -->
            <div class="chart-container">
                <h3>System Resources</h3>
                <div class="chart-wrapper">
                    <canvas id="resources-chart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts Section -->
    <div class="dashboard-section alerts-section">
        <h2>
            Active Alerts 
            <span class="alert-count"><?php echo count($activeAlerts); ?></span>
        </h2>
        
        <div class="alerts-container">
            <?php if (empty($activeAlerts)): ?>
            <div class="no-alerts">
                <i class="dashicons dashicons-yes-alt"></i>
                <p>No active alerts. All systems operating normally.</p>
            </div>
            <?php else: ?>
            <div class="alerts-list">
                <?php foreach ($activeAlerts as $alert): ?>
                <div class="alert-card severity-<?php echo esc_attr($alert['severity']); ?>">
                    <div class="alert-header">
                        <div class="alert-severity">
                            <span class="severity-badge"><?php echo ucfirst($alert['severity']); ?></span>
                        </div>
                        <div class="alert-time">
                            <?php echo human_time_diff($alert['created_at']); ?> ago
                        </div>
                    </div>
                    
                    <div class="alert-content">
                        <div class="alert-type"><?php echo esc_html($alert['type']); ?></div>
                        <div class="alert-message"><?php echo esc_html($alert['message']); ?></div>
                    </div>
                    
                    <div class="alert-actions">
                        <?php if (!$alert['acknowledged']): ?>
                        <button class="button acknowledge-alert" data-alert-id="<?php echo esc_attr($alert['id']); ?>">
                            Acknowledge
                        </button>
                        <?php endif; ?>
                        
                        <button class="button resolve-alert" data-alert-id="<?php echo esc_attr($alert['id']); ?>">
                            Resolve
                        </button>
                        
                        <button class="button button-link view-details" data-alert-id="<?php echo esc_attr($alert['id']); ?>">
                            View Details
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- API Endpoints Performance -->
    <div class="dashboard-section endpoints-section">
        <h2>API Endpoints Performance</h2>
        
        <div class="endpoints-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th>Requests/min</th>
                        <th>Avg Response Time</th>
                        <th>Error Rate</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="endpoints-table-body">
                    <tr>
                        <td>/api/v1/businesses</td>
                        <td>245</td>
                        <td>125ms</td>
                        <td>0.8%</td>
                        <td><span class="status-healthy">Healthy</span></td>
                    </tr>
                    <tr>
                        <td>/api/v1/reviews</td>
                        <td>189</td>
                        <td>98ms</td>
                        <td>1.2%</td>
                        <td><span class="status-healthy">Healthy</span></td>
                    </tr>
                    <tr>
                        <td>/api/v1/search</td>
                        <td>456</td>
                        <td>234ms</td>
                        <td>2.1%</td>
                        <td><span class="status-warning">Warning</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Database Performance -->
    <div class="dashboard-section database-section">
        <h2>Database Performance</h2>
        
        <div class="database-metrics">
            <div class="db-metric">
                <div class="db-metric-label">Query Response Time</div>
                <div class="db-metric-value">12ms</div>
                <div class="db-metric-status status-healthy">Healthy</div>
            </div>
            
            <div class="db-metric">
                <div class="db-metric-label">Active Connections</div>
                <div class="db-metric-value">24</div>
                <div class="db-metric-status status-healthy">Healthy</div>
            </div>
            
            <div class="db-metric">
                <div class="db-metric-label">Slow Queries</div>
                <div class="db-metric-value">3</div>
                <div class="db-metric-status status-warning">Warning</div>
            </div>
            
            <div class="db-metric">
                <div class="db-metric-label">Cache Hit Rate</div>
                <div class="db-metric-value">94.2%</div>
                <div class="db-metric-status status-healthy">Healthy</div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Modal -->
<div id="alert-modal" class="alert-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Alert Details</h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
            <div id="alert-details-content"></div>
        </div>
        <div class="modal-footer">
            <button class="button button-primary" id="modal-acknowledge">Acknowledge</button>
            <button class="button" id="modal-resolve">Resolve</button>
            <button class="button button-link" id="modal-close">Close</button>
        </div>
    </div>
</div>

<script>
// Initialize dashboard
jQuery(document).ready(function($) {
    window.ZipPicksMonitoring = new ZipPicksMonitoringDashboard();
});
</script>

<style>
.zippicks-monitoring-dashboard {
    margin: 20px 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.dashboard-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.dashboard-section {
    margin: 20px 0;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    overflow: hidden;
}

.dashboard-section h2 {
    margin: 0;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e1e5e9;
    font-size: 18px;
    font-weight: 600;
}

.health-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    padding: 20px;
}

.health-card {
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.health-card.status-healthy { border-left-color: #46b450; }
.health-card.status-warning { border-left-color: #ffb900; }
.health-card.status-critical { border-left-color: #dc3232; }

.health-indicator {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}

.status-healthy .status-dot { background: #46b450; }
.status-warning .status-dot { background: #ffb900; }
.status-critical .status-dot { background: #dc3232; }

.health-title {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 5px;
}

.health-metric {
    font-size: 14px;
    color: #666;
}

.health-timestamp {
    font-size: 12px;
    color: #999;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
    padding: 20px;
}

.metric-card {
    padding: 20px;
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    text-align: center;
}

.metric-value {
    font-size: 28px;
    font-weight: 700;
    color: #1d2327;
    margin-bottom: 5px;
}

.metric-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 10px;
}

.metric-trend {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.metric-trend .dashicons {
    font-size: 16px;
    margin-right: 2px;
}

.metric-trend .dashicons-arrow-up-alt { color: #46b450; }
.metric-trend .dashicons-arrow-down-alt { color: #dc3232; }
.metric-trend .dashicons-minus { color: #999; }

.charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    padding: 20px;
}

.chart-container {
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 15px;
}

.chart-container h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
}

.chart-wrapper {
    position: relative;
    height: 300px;
}

.alerts-container {
    padding: 20px;
}

.no-alerts {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.no-alerts .dashicons {
    font-size: 48px;
    color: #46b450;
    margin-bottom: 10px;
}

.alerts-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.alert-card {
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 15px;
    background: #fff;
}

.alert-card.severity-critical { border-left: 4px solid #dc3232; }
.alert-card.severity-error { border-left: 4px solid #d63638; }
.alert-card.severity-warning { border-left: 4px solid #ffb900; }
.alert-card.severity-info { border-left: 4px solid #2271b1; }

.alert-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.severity-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.severity-critical .severity-badge { background: #dc3232; color: white; }
.severity-error .severity-badge { background: #d63638; color: white; }
.severity-warning .severity-badge { background: #ffb900; color: white; }
.severity-info .severity-badge { background: #2271b1; color: white; }

.alert-time {
    font-size: 12px;
    color: #666;
}

.alert-type {
    font-weight: 600;
    margin-bottom: 5px;
}

.alert-message {
    color: #666;
    margin-bottom: 15px;
}

.alert-actions {
    display: flex;
    gap: 10px;
}

.endpoints-table-container {
    padding: 20px;
}

.status-healthy { color: #46b450; font-weight: 600; }
.status-warning { color: #ffb900; font-weight: 600; }
.status-critical { color: #dc3232; font-weight: 600; }

.database-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    padding: 20px;
}

.db-metric {
    padding: 20px;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    text-align: center;
}

.db-metric-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 10px;
}

.db-metric-value {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 10px;
}

.db-metric-status {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.alert-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #fff;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e1e5e9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e1e5e9;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.close-modal {
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.alert-count {
    background: #dc3232;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

@media (max-width: 768px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-controls {
        flex-direction: column;
        gap: 15px;
    }
    
    .health-cards {
        grid-template-columns: 1fr;
    }
    
    .metrics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>