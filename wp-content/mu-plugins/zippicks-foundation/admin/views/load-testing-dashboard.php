<?php
/**
 * Load Testing Dashboard View
 * 
 * WordPress admin interface for enterprise load testing
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap zippicks-load-testing">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('ZipPicks Load Testing', 'zippicks'); ?>
        <span class="title-count theme-count"><?php echo count($activeSessions); ?> Active</span>
    </h1>

    <hr class="wp-header-end">

    <!-- Alert/Status Messages -->
    <div id="load-testing-messages"></div>

    <!-- Active Tests Section -->
    <?php if (!empty($activeSessions)): ?>
    <div class="card active-tests-card">
        <h2 class="card-header">
            <span class="dashicons dashicons-performance"></span>
            Active Load Tests
        </h2>
        <div class="card-body">
            <?php foreach ($activeSessions as $testId => $session): ?>
            <div class="active-test-item" data-test-id="<?php echo esc_attr($testId); ?>">
                <div class="test-info">
                    <div class="test-meta">
                        <strong><?php echo esc_html($session['test_suite']); ?></strong>
                        <span class="test-id">#<?php echo esc_html($testId); ?></span>
                        <span class="test-status running">Running</span>
                    </div>
                    <div class="test-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 45%"></div>
                        </div>
                        <span class="progress-text">45% Complete</span>
                    </div>
                    <div class="test-metrics">
                        <div class="metric">
                            <span class="metric-label">RPS:</span>
                            <span class="metric-value" id="rps-<?php echo esc_attr($testId); ?>">0</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Response Time:</span>
                            <span class="metric-value" id="response-time-<?php echo esc_attr($testId); ?>">0ms</span>
                        </div>
                        <div class="metric">
                            <span class="metric-label">Error Rate:</span>
                            <span class="metric-value" id="error-rate-<?php echo esc_attr($testId); ?>">0%</span>
                        </div>
                    </div>
                </div>
                <div class="test-actions">
                    <button type="button" class="button stop-test-btn" data-test-id="<?php echo esc_attr($testId); ?>">
                        <span class="dashicons dashicons-no"></span>
                        Stop Test
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- New Test Section -->
    <div class="card new-test-card">
        <h2 class="card-header">
            <span class="dashicons dashicons-plus-alt"></span>
            Run New Load Test
        </h2>
        <div class="card-body">
            <form id="new-load-test-form" class="load-test-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="test-suite">Test Suite</label>
                        <select id="test-suite" name="test_suite" required>
                            <?php foreach ($availableScenarios as $scenario): ?>
                            <option value="<?php echo esc_attr($scenario); ?>">
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $scenario))); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="test-duration">Duration (seconds)</label>
                        <input type="number" id="test-duration" name="config[duration]" value="300" min="30" max="3600" required>
                    </div>
                    <div class="form-group">
                        <label for="concurrent-users">Concurrent Users</label>
                        <input type="number" id="concurrent-users" name="config[concurrent_users]" value="100" min="1" max="10000" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="target-rps">Target RPS</label>
                        <input type="number" id="target-rps" name="config[target_rps]" value="1000" min="1" max="100000" required>
                    </div>
                    <div class="form-group">
                        <label for="ramp-up-time">Ramp Up Time (seconds)</label>
                        <input type="number" id="ramp-up-time" name="config[ramp_up_time]" value="60" min="0" max="600">
                    </div>
                    <div class="form-group">
                        <label for="ramp-down-time">Ramp Down Time (seconds)</label>
                        <input type="number" id="ramp-down-time" name="config[ramp_down_time]" value="30" min="0" max="300">
                    </div>
                </div>

                <div class="form-row advanced-options" style="display: none;">
                    <div class="form-group">
                        <label for="peak-load-factor">Peak Load Factor</label>
                        <input type="number" id="peak-load-factor" name="config[peak_load_factor]" value="2.0" min="1.0" max="10.0" step="0.1">
                    </div>
                    <div class="form-group">
                        <label for="timeout">Timeout (seconds)</label>
                        <input type="number" id="timeout" name="config[timeout]" value="30" min="1" max="300">
                    </div>
                    <div class="form-group">
                        <label for="base-url">Base URL</label>
                        <input type="url" id="base-url" name="config[base_url]" value="<?php echo esc_attr(home_url()); ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="button button-secondary" id="toggle-advanced">
                        <span class="dashicons dashicons-admin-settings"></span>
                        Advanced Options
                    </button>
                    <button type="submit" class="button button-primary" id="run-test-btn">
                        <span class="dashicons dashicons-controls-play"></span>
                        Run Load Test
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="avg-rps">0</div>
                <div class="stat-label">Avg RPS (24h)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="avg-response-time">0ms</div>
                <div class="stat-label">Avg Response Time</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="error-rate">0%</div>
                <div class="stat-label">Error Rate</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="success-rate">100%</div>
                <div class="stat-label">Success Rate</div>
            </div>
        </div>
    </div>

    <!-- Performance Charts -->
    <div class="charts-section">
        <div class="chart-container">
            <h3>Request Rate Over Time</h3>
            <canvas id="rps-chart" width="800" height="400"></canvas>
        </div>
        <div class="chart-container">
            <h3>Response Time Trends</h3>
            <canvas id="response-time-chart" width="800" height="400"></canvas>
        </div>
    </div>

    <!-- Test History -->
    <div class="card history-card">
        <h2 class="card-header">
            <span class="dashicons dashicons-backup"></span>
            Test History
            <div class="header-actions">
                <button type="button" class="button button-small" id="refresh-history">
                    <span class="dashicons dashicons-update"></span>
                    Refresh
                </button>
            </div>
        </h2>
        <div class="card-body">
            <div class="history-filters">
                <select id="history-filter-suite">
                    <option value="">All Test Suites</option>
                    <?php foreach ($availableScenarios as $scenario): ?>
                    <option value="<?php echo esc_attr($scenario); ?>">
                        <?php echo esc_html(ucwords(str_replace('_', ' ', $scenario))); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select id="history-filter-status">
                    <option value="">All Statuses</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                    <option value="stopped">Stopped</option>
                </select>
                <input type="date" id="history-filter-date" placeholder="Filter by date">
            </div>

            <div class="history-table-container">
                <table class="wp-list-table widefat fixed striped" id="test-history-table">
                    <thead>
                        <tr>
                            <th>Test ID</th>
                            <th>Suite</th>
                            <th>Started</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>RPS</th>
                            <th>Response Time</th>
                            <th>Error Rate</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="test-history-tbody">
                        <?php if (empty($testHistory)): ?>
                        <tr>
                            <td colspan="9" class="no-items">No test history found.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($testHistory as $test): ?>
                        <tr data-test-id="<?php echo esc_attr($test['test_id']); ?>">
                            <td>
                                <code><?php echo esc_html($test['test_id']); ?></code>
                            </td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $test['test_suite']))); ?></td>
                            <td><?php echo esc_html(date('M j, Y H:i', strtotime($test['start_time']))); ?></td>
                            <td><?php echo esc_html($this->formatDuration($test['duration'])); ?></td>
                            <td><?php echo $this->getStatusBadge($test['status']); ?></td>
                            <td><?php echo esc_html($test['rps'] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html($test['response_time'] ?? 'N/A'); ?>ms</td>
                            <td><?php echo esc_html($test['error_rate'] ?? 'N/A'); ?>%</td>
                            <td>
                                <button type="button" class="button button-small view-details-btn" 
                                        data-test-id="<?php echo esc_attr($test['test_id']); ?>">
                                    View Details
                                </button>
                                <button type="button" class="button button-small button-link-delete delete-test-btn" 
                                        data-test-id="<?php echo esc_attr($test['test_id']); ?>">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="history-pagination">
                <button type="button" class="button" id="load-more-history">Load More</button>
            </div>
        </div>
    </div>
</div>

<!-- Test Details Modal -->
<div id="test-details-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Test Details</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="test-details-content">
                <!-- Test details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
.zippicks-load-testing {
    margin: 20px;
}

.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.card-header {
    background: #f7f7f7;
    border-bottom: 1px solid #ccd0d4;
    padding: 15px 20px;
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-header .dashicons {
    margin-right: 8px;
    color: #646970;
}

.card-body {
    padding: 20px;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: #2271b1;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon .dashicons {
    color: white;
    font-size: 24px;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #1d2327;
}

.stat-label {
    color: #646970;
    font-size: 12px;
}

.charts-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.chart-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.chart-container h3 {
    margin: 0 0 15px 0;
    font-size: 14px;
    color: #1d2327;
}

.active-test-item {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.test-info {
    flex: 1;
}

.test-meta {
    margin-bottom: 10px;
}

.test-meta strong {
    margin-right: 10px;
}

.test-id {
    background: #646970;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    margin-right: 10px;
}

.test-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.test-status.running {
    background: #007cba;
    color: white;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e1e1e1;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-fill {
    height: 100%;
    background: #00a32a;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 12px;
    color: #646970;
}

.test-metrics {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.metric {
    font-size: 12px;
}

.metric-label {
    color: #646970;
    margin-right: 4px;
}

.metric-value {
    font-weight: 600;
    color: #1d2327;
}

.history-filters {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.history-filters select,
.history-filters input {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.badge-primary { background: #007cba; color: white; }
.badge-success { background: #00a32a; color: white; }
.badge-danger { background: #d63638; color: white; }
.badge-warning { background: #dba617; color: white; }
.badge-secondary { background: #646970; color: white; }

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 4px;
    max-width: 800px;
    width: 90%;
    max-height: 80%;
    overflow: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
}

.modal-body {
    padding: 20px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.no-items {
    text-align: center;
    color: #646970;
    font-style: italic;
    padding: 40px 20px;
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
    }
    
    .charts-section {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .active-test-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .test-metrics {
        flex-wrap: wrap;
    }
}
</style>

<script>
// Chart.js configuration
Chart.defaults.responsive = true;
Chart.defaults.maintainAspectRatio = false;

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize RPS chart
    const rpsCtx = document.getElementById('rps-chart').getContext('2d');
    window.rpsChart = new Chart(rpsCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Requests per Second',
                data: [],
                borderColor: '#2271b1',
                backgroundColor: 'rgba(34, 113, 177, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Initialize Response Time chart
    const responseTimeCtx = document.getElementById('response-time-chart').getContext('2d');
    window.responseTimeChart = new Chart(responseTimeCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Response Time (ms)',
                data: [],
                borderColor: '#00a32a',
                backgroundColor: 'rgba(0, 163, 42, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>