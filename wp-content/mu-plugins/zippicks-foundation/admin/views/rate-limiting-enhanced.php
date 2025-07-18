<?php
/**
 * Enhanced Rate Limiting Dashboard
 * 
 * Real-time monitoring interface for our $100B platform's rate limiting system
 */

// Ensure we have necessary variables
if (!isset($manager)) {
    return;
}

// Enqueue assets
wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', [], '3.9.1', true);
wp_enqueue_script('zippicks-rate-limiting', plugin_dir_url(__FILE__) . '../assets/js/rate-limiting.js', ['jquery', 'chart-js'], '1.0.0', true);
wp_enqueue_style('zippicks-rate-limiting', plugin_dir_url(__FILE__) . '../assets/css/rate-limiting.css', [], '1.0.0');

// Localize script
wp_localize_script('zippicks-rate-limiting', 'zippicks_rate_limiting', [
    'api_url' => rest_url('zippicks/v1/'),
    'nonce' => wp_create_nonce('wp_rest'),
]);

// Get initial data
$stats = $manager->getUsageStats();
?>

<div class="wrap zippicks-rate-limiting">
    <h1>Rate Limiting Dashboard - Enterprise Edition</h1>
    
    <!-- Action Bar -->
    <div class="actions">
        <button id="export-data" class="button">Export Data</button>
        <button id="clear-all-limits" class="button button-danger">Clear All Limits</button>
        
        <div class="auto-update-toggle">
            <label>
                <span>Auto-update</span>
                <div class="toggle-switch">
                    <input type="checkbox" id="auto-update" checked>
                    <span class="toggle-slider"></span>
                </div>
            </label>
        </div>
    </div>

    <!-- Metric Cards -->
    <div class="dashboard-grid">
        <div class="metric-card">
            <h3>Total Requests (24h)</h3>
            <div class="metric-value" id="total-requests">0</div>
            <div class="metric-change positive">+12.5% from yesterday</div>
        </div>
        
        <div class="metric-card">
            <h3>Rate Limit Exceeded</h3>
            <div class="metric-value" id="exceeded-count">0</div>
            <div class="metric-change negative">+5% from yesterday</div>
        </div>
        
        <div class="metric-card">
            <h3>Active Users</h3>
            <div class="metric-value" id="active-users">0</div>
            <div class="metric-change positive">+18% from yesterday</div>
        </div>
        
        <div class="metric-card">
            <h3>Revenue Impact</h3>
            <div class="metric-value" id="revenue-impact">$0</div>
            <div class="metric-change positive">Potential ARR from upgrades</div>
        </div>
    </div>

    <!-- Health Status -->
    <div class="health-status">
        <h2>System Health</h2>
        <div class="health-grid">
            <div class="health-item">
                <span>Overall Status</span>
                <span id="overall-health" class="health-indicator health-good">HEALTHY</span>
            </div>
            <div class="health-item">
                <span>Redis Connection</span>
                <span id="redis-health" class="status-active">Active</span>
            </div>
            <div class="health-item">
                <span>Circuit Breaker</span>
                <span id="circuit-breaker-health" class="status-active">Closed</span>
            </div>
            <div class="health-item">
                <span>Queue Processing</span>
                <span id="queue-health" class="status-active">Normal</span>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-container">
        <div class="chart-box">
            <canvas id="usageChart"></canvas>
        </div>
        
        <div class="chart-box">
            <canvas id="tierChart"></canvas>
        </div>
        
        <div class="chart-box">
            <canvas id="eventsChart"></canvas>
        </div>
    </div>

    <!-- Resource Usage -->
    <div class="data-table">
        <h2>Resource Usage by Type</h2>
        <table class="wp-list-table widefat">
            <thead>
                <tr>
                    <th>Resource</th>
                    <th>Current Usage</th>
                    <th>Limit (Free Tier)</th>
                    <th>Usage %</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>API Requests</strong></td>
                    <td>85 / min</td>
                    <td>100 / min</td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill warning" style="width: 85%"></div>
                        </div>
                        <span id="api-usage">85%</span>
                    </td>
                    <td><span class="status-warning">Near Limit</span></td>
                </tr>
                <tr>
                    <td><strong>Taste Graph Calculations</strong></td>
                    <td>7 / hr</td>
                    <td>10 / hr</td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 70%"></div>
                        </div>
                        <span id="taste-graph-usage">70%</span>
                    </td>
                    <td><span class="status-active">Normal</span></td>
                </tr>
                <tr>
                    <td><strong>AI Scores</strong></td>
                    <td>4 / hr</td>
                    <td>5 / hr</td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill warning" style="width: 80%"></div>
                        </div>
                        <span id="ai-scores-usage">80%</span>
                    </td>
                    <td><span class="status-warning">Near Limit</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Top Users -->
    <div class="data-table">
        <h2>Top Users by Request Volume</h2>
        <table id="top-users" class="wp-list-table widefat">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Tier</th>
                    <th>Requests (24h)</th>
                    <th>% of Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated by JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- User Search -->
    <div class="user-search-box">
        <h2>Search User Rate Limits</h2>
        <form id="user-search" class="search-form">
            <input type="number" id="user-id" placeholder="User ID" required>
            <select id="tier-filter">
                <option value="">All Tiers</option>
                <option value="free">Free</option>
                <option value="pro">Pro</option>
                <option value="business">Business</option>
                <option value="enterprise">Enterprise</option>
            </select>
            <button type="submit" class="button button-primary">Search</button>
        </form>
    </div>

    <!-- Advanced Settings -->
    <div class="data-table">
        <h2>Advanced Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Circuit Breaker</th>
                <td>
                    <label>
                        <input type="checkbox" id="circuit-breaker-enabled" checked>
                        Enable circuit breaker protection
                    </label>
                    <p class="description">Automatically fail open if rate limit store becomes unavailable</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Cost-Based Limiting</th>
                <td>
                    <label>
                        <input type="checkbox" id="cost-based-enabled" checked>
                        Enable cost-based rate limiting
                    </label>
                    <p class="description">Different operations consume different amounts of quota</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Analytics Tracking</th>
                <td>
                    <label>
                        <input type="checkbox" id="analytics-enabled" checked>
                        Track rate limit events for analytics
                    </label>
                    <p class="description">Identify upgrade opportunities and usage patterns</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Debug Information -->
    <details class="debug-info">
        <summary>Debug Information</summary>
        <pre><?php print_r($stats); ?></pre>
    </details>
</div>