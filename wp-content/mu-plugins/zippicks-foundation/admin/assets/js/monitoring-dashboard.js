/**
 * ZipPicks Real-time Monitoring Dashboard JavaScript
 * 
 * Handles real-time updates, chart rendering, and alert management
 * for the enterprise monitoring dashboard
 */

class ZipPicksMonitoringDashboard {
    constructor() {
        this.refreshInterval = 30; // seconds
        this.refreshTimer = null;
        this.countdownTimer = null;
        this.charts = {};
        this.isAutoRefresh = true;
        this.currentTimeframe = '1h';
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeCharts();
        this.startAutoRefresh();
        this.loadDashboardData();
    }

    bindEvents() {
        const $ = jQuery;

        // Auto-refresh toggle
        $('#auto-refresh').on('change', (e) => {
            this.isAutoRefresh = e.target.checked;
            if (this.isAutoRefresh) {
                this.startAutoRefresh();
            } else {
                this.stopAutoRefresh();
            }
        });

        // Timeframe selector
        $('#timeframe').on('change', (e) => {
            this.currentTimeframe = e.target.value;
            this.loadDashboardData();
        });

        // Manual refresh button
        $('#refresh-dashboard').on('click', () => {
            this.refreshDashboard();
        });

        // Alert actions
        $(document).on('click', '.acknowledge-alert', (e) => {
            const alertId = $(e.target).data('alert-id');
            this.acknowledgeAlert(alertId);
        });

        $(document).on('click', '.resolve-alert', (e) => {
            const alertId = $(e.target).data('alert-id');
            this.resolveAlert(alertId);
        });

        $(document).on('click', '.view-details', (e) => {
            const alertId = $(e.target).data('alert-id');
            this.showAlertDetails(alertId);
        });

        // Modal events
        $('.close-modal, #modal-close').on('click', () => {
            this.hideModal();
        });

        $('#modal-acknowledge').on('click', () => {
            const alertId = $('#alert-modal').data('alert-id');
            this.acknowledgeAlert(alertId);
            this.hideModal();
        });

        $('#modal-resolve').on('click', () => {
            const alertId = $('#alert-modal').data('alert-id');
            this.resolveAlert(alertId);
            this.hideModal();
        });

        // Close modal on outside click
        $('#alert-modal').on('click', (e) => {
            if (e.target.id === 'alert-modal') {
                this.hideModal();
            }
        });
    }

    initializeCharts() {
        this.initPerformanceChart();
        this.initThroughputChart();
        this.initErrorChart();
        this.initResourcesChart();
    }

    initPerformanceChart() {
        const ctx = document.getElementById('performance-chart');
        if (!ctx) return;

        this.charts.performance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Response Time (ms)',
                    data: [],
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'P95 Response Time (ms)',
                    data: [],
                    borderColor: '#d63638',
                    backgroundColor: 'rgba(214, 54, 56, 0.1)',
                    tension: 0.4,
                    fill: false
                }]
            },
            options: this.getChartOptions('Response Time (ms)')
        });
    }

    initThroughputChart() {
        const ctx = document.getElementById('throughput-chart');
        if (!ctx) return;

        this.charts.throughput = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Requests/sec',
                    data: [],
                    borderColor: '#46b450',
                    backgroundColor: 'rgba(70, 180, 80, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: this.getChartOptions('Requests per Second')
        });
    }

    initErrorChart() {
        const ctx = document.getElementById('error-chart');
        if (!ctx) return;

        this.charts.error = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Error Rate (%)',
                    data: [],
                    borderColor: '#dc3232',
                    backgroundColor: 'rgba(220, 50, 50, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: '4xx Errors',
                    data: [],
                    borderColor: '#ffb900',
                    backgroundColor: 'rgba(255, 185, 0, 0.1)',
                    tension: 0.4,
                    fill: false
                }, {
                    label: '5xx Errors',
                    data: [],
                    borderColor: '#d63638',
                    backgroundColor: 'rgba(214, 54, 56, 0.1)',
                    tension: 0.4,
                    fill: false
                }]
            },
            options: this.getChartOptions('Error Rate (%)')
        });
    }

    initResourcesChart() {
        const ctx = document.getElementById('resources-chart');
        if (!ctx) return;

        this.charts.resources = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'CPU Usage (%)',
                    data: [],
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Memory Usage (%)',
                    data: [],
                    borderColor: '#46b450',
                    backgroundColor: 'rgba(70, 180, 80, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Disk Usage (%)',
                    data: [],
                    borderColor: '#ffb900',
                    backgroundColor: 'rgba(255, 185, 0, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                }]
            },
            options: this.getChartOptions('Resource Usage (%)', true)
        });
    }

    getChartOptions(yAxisLabel, isPercentage = false) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#666',
                    borderWidth: 1
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Time'
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: yAxisLabel
                    },
                    beginAtZero: true,
                    max: isPercentage ? 100 : undefined,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                }
            },
            elements: {
                point: {
                    radius: 3,
                    hoverRadius: 6
                }
            }
        };
    }

    startAutoRefresh() {
        this.stopAutoRefresh();
        
        if (this.isAutoRefresh) {
            this.startCountdown();
            this.refreshTimer = setInterval(() => {
                this.refreshDashboard();
            }, this.refreshInterval * 1000);
        }
    }

    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
        
        if (this.countdownTimer) {
            clearInterval(this.countdownTimer);
            this.countdownTimer = null;
        }
    }

    startCountdown() {
        let remaining = this.refreshInterval;
        
        const updateCountdown = () => {
            jQuery('#refresh-countdown').text(remaining);
            remaining--;
            
            if (remaining < 0) {
                remaining = this.refreshInterval;
            }
        };
        
        updateCountdown();
        this.countdownTimer = setInterval(updateCountdown, 1000);
    }

    async refreshDashboard() {
        this.showLoadingState();
        
        try {
            await this.loadDashboardData();
            await this.updateCharts();
            await this.updateAlerts();
            await this.updateEndpointsTable();
            
            this.hideLoadingState();
            
            // Show success indicator
            this.showRefreshSuccess();
            
        } catch (error) {
            console.error('Dashboard refresh failed:', error);
            this.showRefreshError();
            this.hideLoadingState();
        }
    }

    async loadDashboardData() {
        const response = await this.apiRequest('get_dashboard_data', {
            type: 'overview',
            timeframe: this.currentTimeframe
        });
        
        if (response.success) {
            this.updateMetrics(response.data);
            this.updateSystemHealth(response.data.system_health);
        }
    }

    async updateCharts() {
        const chartsData = await this.apiRequest('get_charts_data', {
            timeframe: this.currentTimeframe
        });
        
        if (chartsData.success) {
            this.updateChartData(chartsData.data);
        }
    }

    updateChartData(data) {
        // Update performance chart
        if (this.charts.performance && data.performance) {
            this.updateChart(this.charts.performance, data.performance);
        }
        
        // Update throughput chart
        if (this.charts.throughput && data.throughput) {
            this.updateChart(this.charts.throughput, data.throughput);
        }
        
        // Update error chart
        if (this.charts.error && data.errors) {
            this.updateChart(this.charts.error, data.errors);
        }
        
        // Update resources chart
        if (this.charts.resources && data.resources) {
            this.updateChart(this.charts.resources, data.resources);
        }
    }

    updateChart(chart, data) {
        chart.data.labels = data.labels || [];
        
        data.datasets.forEach((dataset, index) => {
            if (chart.data.datasets[index]) {
                chart.data.datasets[index].data = dataset.data || [];
            }
        });
        
        chart.update('none');
    }

    updateMetrics(data) {
        // Update key metrics
        if (data.api && data.api.requests_per_second !== undefined) {
            this.updateMetricValue('.metric-card:nth-child(1) .metric-value', 
                data.api.requests_per_second, 1);
        }
        
        if (data.performance && data.performance.response_time && data.performance.response_time.avg !== undefined) {
            this.updateMetricValue('.metric-card:nth-child(2) .metric-value', 
                data.performance.response_time.avg + 'ms');
        }
        
        if (data.performance && data.performance.error_rate && data.performance.error_rate.percentage !== undefined) {
            this.updateMetricValue('.metric-card:nth-child(3) .metric-value', 
                data.performance.error_rate.percentage, 2, '%');
        }
        
        if (data.system && data.system.cpu_usage !== undefined) {
            this.updateMetricValue('.metric-card:nth-child(4) .metric-value', 
                data.system.cpu_usage, 1, '%');
        }
        
        if (data.system && data.system.memory_usage !== undefined) {
            this.updateMetricValue('.metric-card:nth-child(5) .metric-value', 
                data.system.memory_usage, 1, '%');
        }
        
        if (data.business && data.business.active_users !== undefined) {
            this.updateMetricValue('.metric-card:nth-child(6) .metric-value', 
                data.business.active_users);
        }
    }

    updateMetricValue(selector, value, decimals = 0, suffix = '') {
        const element = jQuery(selector);
        if (element.length) {
            const formattedValue = typeof value === 'number' ? 
                value.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) : 
                value;
            element.text(formattedValue + suffix);
        }
    }

    updateSystemHealth(healthData) {
        if (!healthData) return;
        
        // Update overall status
        const overallCard = jQuery('.health-card.overall-status');
        overallCard.removeClass('status-healthy status-warning status-critical')
                  .addClass('status-' + healthData.overall_status);
        overallCard.find('.status-text').text(healthData.overall_status.charAt(0).toUpperCase() + healthData.overall_status.slice(1));
        
        // Update component statuses
        Object.entries(healthData.components).forEach(([component, status]) => {
            const componentCard = jQuery(`.health-card:has(.health-title:contains("${component.replace('_', ' ')}"))`);
            if (componentCard.length) {
                componentCard.removeClass('status-healthy status-warning status-critical')
                           .addClass('status-' + status.status);
                componentCard.find('.status-text').text(status.status.charAt(0).toUpperCase() + status.status.slice(1));
                
                if (status.response_time !== undefined) {
                    componentCard.find('.health-metric').text(status.response_time.toFixed(2) + 'ms');
                }
            }
        });
        
        // Update timestamp
        const timestamp = new Date(healthData.last_updated * 1000).toLocaleTimeString();
        jQuery('.health-timestamp').text('Last updated: ' + timestamp);
    }

    async updateAlerts() {
        const alertsData = await this.apiRequest('get_active_alerts');
        
        if (alertsData.success) {
            this.renderAlerts(alertsData.data);
        }
    }

    renderAlerts(alerts) {
        const container = jQuery('.alerts-container');
        const alertCount = jQuery('.alert-count');
        
        alertCount.text(alerts.length);
        
        if (alerts.length === 0) {
            container.html(`
                <div class="no-alerts">
                    <i class="dashicons dashicons-yes-alt"></i>
                    <p>No active alerts. All systems operating normally.</p>
                </div>
            `);
        } else {
            const alertsHtml = alerts.map(alert => this.renderAlertCard(alert)).join('');
            container.html(`<div class="alerts-list">${alertsHtml}</div>`);
        }
    }

    renderAlertCard(alert) {
        const timeAgo = this.timeAgo(alert.created_at);
        const acknowledgeButton = !alert.acknowledged ? 
            `<button class="button acknowledge-alert" data-alert-id="${alert.id}">Acknowledge</button>` : '';
        
        return `
            <div class="alert-card severity-${alert.severity}">
                <div class="alert-header">
                    <div class="alert-severity">
                        <span class="severity-badge">${alert.severity.charAt(0).toUpperCase() + alert.severity.slice(1)}</span>
                    </div>
                    <div class="alert-time">${timeAgo} ago</div>
                </div>
                
                <div class="alert-content">
                    <div class="alert-type">${alert.type}</div>
                    <div class="alert-message">${alert.message}</div>
                </div>
                
                <div class="alert-actions">
                    ${acknowledgeButton}
                    <button class="button resolve-alert" data-alert-id="${alert.id}">Resolve</button>
                    <button class="button button-link view-details" data-alert-id="${alert.id}">View Details</button>
                </div>
            </div>
        `;
    }

    async updateEndpointsTable() {
        const endpointsData = await this.apiRequest('get_endpoints_performance');
        
        if (endpointsData.success) {
            this.renderEndpointsTable(endpointsData.data);
        }
    }

    renderEndpointsTable(endpoints) {
        const tbody = jQuery('#endpoints-table-body');
        
        const rows = endpoints.map(endpoint => `
            <tr>
                <td>${endpoint.path}</td>
                <td>${endpoint.requests_per_minute}</td>
                <td>${endpoint.avg_response_time}ms</td>
                <td>${endpoint.error_rate}%</td>
                <td><span class="status-${endpoint.status.toLowerCase()}">${endpoint.status}</span></td>
            </tr>
        `).join('');
        
        tbody.html(rows);
    }

    async acknowledgeAlert(alertId) {
        const response = await this.apiRequest('acknowledge_alert', {
            alert_id: alertId,
            acknowledged_by: 'Current User',
            note: 'Acknowledged from dashboard'
        });
        
        if (response.success) {
            this.showNotification('Alert acknowledged successfully', 'success');
            this.updateAlerts();
        } else {
            this.showNotification('Failed to acknowledge alert', 'error');
        }
    }

    async resolveAlert(alertId) {
        const response = await this.apiRequest('resolve_alert', {
            alert_id: alertId,
            resolved_by: 'Current User',
            resolution: 'Resolved from dashboard'
        });
        
        if (response.success) {
            this.showNotification('Alert resolved successfully', 'success');
            this.updateAlerts();
        } else {
            this.showNotification('Failed to resolve alert', 'error');
        }
    }

    async showAlertDetails(alertId) {
        const response = await this.apiRequest('get_alert_details', {
            alert_id: alertId
        });
        
        if (response.success) {
            const alert = response.data;
            const detailsHtml = this.renderAlertDetails(alert);
            
            jQuery('#alert-details-content').html(detailsHtml);
            jQuery('#alert-modal').data('alert-id', alertId).show();
        }
    }

    renderAlertDetails(alert) {
        return `
            <div class="alert-details">
                <h4>Alert Information</h4>
                <table class="alert-details-table">
                    <tr><td><strong>Type:</strong></td><td>${alert.type}</td></tr>
                    <tr><td><strong>Severity:</strong></td><td>${alert.severity}</td></tr>
                    <tr><td><strong>Status:</strong></td><td>${alert.status}</td></tr>
                    <tr><td><strong>Created:</strong></td><td>${new Date(alert.created_at * 1000).toLocaleString()}</td></tr>
                    <tr><td><strong>Message:</strong></td><td>${alert.message}</td></tr>
                </table>
                
                ${alert.context && Object.keys(alert.context).length > 0 ? `
                    <h4>Context</h4>
                    <pre>${JSON.stringify(alert.context, null, 2)}</pre>
                ` : ''}
            </div>
        `;
    }

    hideModal() {
        jQuery('#alert-modal').hide();
    }

    showLoadingState() {
        jQuery('#refresh-dashboard').prop('disabled', true).find('.dashicons').addClass('spinning');
    }

    hideLoadingState() {
        jQuery('#refresh-dashboard').prop('disabled', false).find('.dashicons').removeClass('spinning');
    }

    showRefreshSuccess() {
        this.showNotification('Dashboard refreshed successfully', 'success');
    }

    showRefreshError() {
        this.showNotification('Failed to refresh dashboard', 'error');
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = jQuery(`
            <div class="dashboard-notification notification-${type}">
                <span class="notification-message">${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `);
        
        // Add to page
        jQuery('body').append(notification);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            notification.fadeOut(() => notification.remove());
        }, 5000);
        
        // Manual close
        notification.find('.notification-close').on('click', () => {
            notification.fadeOut(() => notification.remove());
        });
    }

    async apiRequest(action, data = {}) {
        return new Promise((resolve, reject) => {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'zippicks_monitoring_' + action,
                    nonce: zippicks_monitoring_nonce,
                    ...data
                },
                success: resolve,
                error: reject
            });
        });
    }

    timeAgo(timestamp) {
        const now = Date.now() / 1000;
        const diff = now - timestamp;
        
        if (diff < 60) return Math.floor(diff) + ' seconds';
        if (diff < 3600) return Math.floor(diff / 60) + ' minutes';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hours';
        return Math.floor(diff / 86400) + ' days';
    }
}

// CSS for notifications and loading states
const additionalCSS = `
    .dashboard-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 4px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 999999;
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 300px;
    }
    
    .notification-success {
        background: #46b450;
        color: white;
    }
    
    .notification-error {
        background: #dc3232;
        color: white;
    }
    
    .notification-info {
        background: #2271b1;
        color: white;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: white;
        font-size: 16px;
        cursor: pointer;
        margin-left: auto;
    }
    
    .spinning {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .alert-details-table {
        width: 100%;
        margin-bottom: 20px;
    }
    
    .alert-details-table td {
        padding: 8px;
        border-bottom: 1px solid #eee;
    }
    
    .alert-details-table tr:last-child td {
        border-bottom: none;
    }
    
    .alert-details pre {
        background: #f5f5f5;
        padding: 15px;
        border-radius: 4px;
        overflow-x: auto;
        font-size: 12px;
    }
`;

// Inject additional CSS
if (!document.getElementById('zippicks-dashboard-css')) {
    const style = document.createElement('style');
    style.id = 'zippicks-dashboard-css';
    style.textContent = additionalCSS;
    document.head.appendChild(style);
}