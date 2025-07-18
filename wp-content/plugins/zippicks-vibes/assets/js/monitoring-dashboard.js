/**
 * ZipPicks Vibes Monitoring Dashboard JavaScript
 * 
 * Enterprise-grade monitoring dashboard with real-time metrics,
 * performance charts, and audit log management.
 */

(function($) {
    'use strict';

    const ZipPicksMonitoring = {
        charts: {},
        refreshInterval: null,
        isRefreshing: false,
        chartColors: {
            primary: '#3B82F6',
            success: '#10B981',
            warning: '#F59E0B',
            danger: '#EF4444',
            info: '#3B82F6',
            secondary: '#6B7280'
        },

        /**
         * Initialize the monitoring dashboard
         */
        init: function() {
            if (typeof zippicksMonitoring === 'undefined') {
                console.error('ZipPicks Monitoring: Missing localized data');
                return;
            }

            this.bindEvents();
            this.initCharts();
            this.startAutoRefresh();
            this.loadInitialData();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Manual refresh button
            $('#zippicks-refresh-metrics').on('click', () => this.refreshMetrics());
            
            // Run health check button
            $('#zippicks-run-health-check').on('click', () => this.runHealthCheck());
            
            // Audit log filters
            $('#audit-log-filter-type, #audit-log-filter-severity').on('change', () => this.loadAuditLogs());
            
            // Audit log pagination
            $(document).on('click', '.audit-log-pagination a', (e) => {
                e.preventDefault();
                const page = $(e.currentTarget).data('page');
                this.loadAuditLogs(page);
            });

            // Time range selector
            $('#metrics-time-range').on('change', () => this.updateCharts());
        },

        /**
         * Initialize Chart.js charts
         */
        initCharts: function() {
            // Response Time Trend Chart
            const responseTimeCtx = document.getElementById('response-time-chart');
            if (responseTimeCtx) {
                this.charts.responseTime = new Chart(responseTimeCtx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Average Response Time (ms)',
                            data: [],
                            borderColor: this.chartColors.primary,
                            backgroundColor: this.hexToRgba(this.chartColors.primary, 0.1),
                            borderWidth: 2,
                            tension: 0.4,
                            pointRadius: 3,
                            pointHoverRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + 'ms';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                title: {
                                    display: true,
                                    text: 'Time'
                                }
                            },
                            y: {
                                display: true,
                                title: {
                                    display: true,
                                    text: 'Response Time (ms)'
                                },
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Request Volume Chart
            const requestVolumeCtx = document.getElementById('request-volume-chart');
            if (requestVolumeCtx) {
                this.charts.requestVolume = new Chart(requestVolumeCtx, {
                    type: 'bar',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'API Requests',
                            data: [],
                            backgroundColor: this.chartColors.success,
                            borderColor: this.chartColors.success,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y + ' requests';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                title: {
                                    display: true,
                                    text: 'Endpoint'
                                }
                            },
                            y: {
                                display: true,
                                title: {
                                    display: true,
                                    text: 'Request Count'
                                },
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
        },

        /**
         * Load initial dashboard data
         */
        loadInitialData: function() {
            this.refreshMetrics();
            this.loadAuditLogs();
        },

        /**
         * Refresh all metrics
         */
        refreshMetrics: function() {
            if (this.isRefreshing) return;
            
            this.isRefreshing = true;
            this.showLoading(true);

            $.ajax({
                url: zippicksMonitoring.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zippicks_get_monitoring_metrics',
                    nonce: zippicksMonitoring.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateMetrics(response.data);
                        this.updateLastRefreshTime();
                    } else {
                        this.showError('Failed to load metrics: ' + (response.data?.message || 'Unknown error'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Metrics refresh error:', error);
                    this.showError('Failed to refresh metrics. Please check your connection.');
                },
                complete: () => {
                    this.isRefreshing = false;
                    this.showLoading(false);
                }
            });
        },

        /**
         * Update metrics display
         */
        updateMetrics: function(data) {
            // Update metric cards
            if (data.metrics) {
                Object.keys(data.metrics).forEach(key => {
                    const $metric = $(`[data-metric="${key}"]`);
                    if ($metric.length) {
                        $metric.text(this.formatMetricValue(key, data.metrics[key]));
                    }
                });
            }

            // Update health checks
            if (data.health_checks) {
                this.updateHealthChecks(data.health_checks);
            }

            // Update charts
            if (data.chart_data) {
                this.updateChartsData(data.chart_data);
            }

            // Update recent events
            if (data.recent_events) {
                this.updateRecentEvents(data.recent_events);
            }

            // Update slow queries
            if (data.slow_queries) {
                this.updateSlowQueries(data.slow_queries);
            }
        },

        /**
         * Update health check displays
         */
        updateHealthChecks: function(healthChecks) {
            const $container = $('#health-checks-container');
            if (!$container.length) return;

            let html = '';
            healthChecks.forEach(check => {
                const statusClass = check.status === 'healthy' ? 'success' : 
                                  check.status === 'warning' ? 'warning' : 'danger';
                const statusIcon = check.status === 'healthy' ? '✓' : 
                                 check.status === 'warning' ? '!' : '✗';
                
                html += `
                    <div class="health-check-item status-${statusClass}">
                        <span class="status-icon">${statusIcon}</span>
                        <span class="check-name">${check.name}</span>
                        <span class="check-message">${check.message || ''}</span>
                    </div>
                `;
            });

            $container.html(html);
        },

        /**
         * Update chart data
         */
        updateChartsData: function(chartData) {
            // Update response time chart
            if (chartData.response_time && this.charts.responseTime) {
                const chart = this.charts.responseTime;
                chart.data.labels = chartData.response_time.labels;
                chart.data.datasets[0].data = chartData.response_time.data;
                chart.update();
            }

            // Update request volume chart
            if (chartData.request_volume && this.charts.requestVolume) {
                const chart = this.charts.requestVolume;
                chart.data.labels = chartData.request_volume.labels;
                chart.data.datasets[0].data = chartData.request_volume.data;
                chart.update();
            }
        },

        /**
         * Update recent security events
         */
        updateRecentEvents: function(events) {
            const $container = $('#recent-events-container');
            if (!$container.length) return;

            if (!events || events.length === 0) {
                $container.html('<p class="no-data">No recent security events</p>');
                return;
            }

            let html = '<table class="widefat striped"><thead><tr>' +
                      '<th>Time</th><th>Type</th><th>Severity</th><th>Message</th>' +
                      '</tr></thead><tbody>';

            events.forEach(event => {
                const severityClass = `severity-${event.severity}`;
                html += `
                    <tr class="${severityClass}">
                        <td>${this.formatTime(event.created_at)}</td>
                        <td>${event.event_type}</td>
                        <td><span class="severity-badge ${severityClass}">${event.severity}</span></td>
                        <td>${this.escapeHtml(event.message)}</td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            $container.html(html);
        },

        /**
         * Update slow queries display
         */
        updateSlowQueries: function(queries) {
            const $container = $('#slow-queries-container');
            if (!$container.length) return;

            if (!queries || queries.length === 0) {
                $container.html('<p class="no-data">No slow queries detected</p>');
                return;
            }

            let html = '<table class="widefat striped"><thead><tr>' +
                      '<th>Query</th><th>Duration</th><th>Time</th>' +
                      '</tr></thead><tbody>';

            queries.forEach(query => {
                html += `
                    <tr>
                        <td class="query-text">${this.escapeHtml(query.query)}</td>
                        <td>${query.duration.toFixed(3)}s</td>
                        <td>${this.formatTime(query.timestamp)}</td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            $container.html(html);
        },

        /**
         * Run health check
         */
        runHealthCheck: function() {
            const $button = $('#zippicks-run-health-check');
            $button.prop('disabled', true).text('Running...');

            $.ajax({
                url: zippicksMonitoring.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zippicks_run_health_check',
                    nonce: zippicksMonitoring.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateHealthChecks(response.data.results);
                        this.showSuccess('Health check completed successfully');
                    } else {
                        this.showError('Health check failed: ' + (response.data?.message || 'Unknown error'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Health check error:', error);
                    this.showError('Failed to run health check');
                },
                complete: () => {
                    $button.prop('disabled', false).text('Run Health Check');
                }
            });
        },

        /**
         * Load audit logs
         */
        loadAuditLogs: function(page = 1) {
            const type = $('#audit-log-filter-type').val();
            const severity = $('#audit-log-filter-severity').val();

            $.ajax({
                url: zippicksMonitoring.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zippicks_get_audit_logs',
                    nonce: zippicksMonitoring.nonce,
                    page: page,
                    type: type,
                    severity: severity
                },
                success: (response) => {
                    if (response.success) {
                        this.displayAuditLogs(response.data.logs);
                        this.displayAuditPagination(response.data.pagination);
                    } else {
                        this.showError('Failed to load audit logs');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Audit log error:', error);
                    this.showError('Failed to load audit logs');
                }
            });
        },

        /**
         * Display audit logs
         */
        displayAuditLogs: function(logs) {
            const $container = $('#audit-logs-container');
            if (!$container.length) return;

            if (!logs || logs.length === 0) {
                $container.html('<p class="no-data">No audit logs found</p>');
                return;
            }

            let html = '<table class="widefat striped"><thead><tr>' +
                      '<th>Time</th><th>User</th><th>Type</th><th>Action</th><th>Details</th>' +
                      '</tr></thead><tbody>';

            logs.forEach(log => {
                const severityClass = log.severity ? `severity-${log.severity}` : '';
                html += `
                    <tr class="${severityClass}">
                        <td>${this.formatTime(log.created_at)}</td>
                        <td>${log.user_name || 'System'}</td>
                        <td>${log.event_type}</td>
                        <td>${log.action || log.message}</td>
                        <td>${this.formatLogDetails(log.details)}</td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            $container.html(html);
        },

        /**
         * Display audit log pagination
         */
        displayAuditPagination: function(pagination) {
            const $container = $('#audit-pagination-container');
            if (!$container.length || !pagination) return;

            let html = '<div class="audit-log-pagination">';
            
            if (pagination.current_page > 1) {
                html += `<a href="#" data-page="${pagination.current_page - 1}" class="prev">« Previous</a>`;
            }

            html += `<span class="page-info">Page ${pagination.current_page} of ${pagination.total_pages}</span>`;

            if (pagination.current_page < pagination.total_pages) {
                html += `<a href="#" data-page="${pagination.current_page + 1}" class="next">Next »</a>`;
            }

            html += '</div>';
            $container.html(html);
        },

        /**
         * Start auto-refresh
         */
        startAutoRefresh: function() {
            if (zippicksMonitoring.autoRefresh) {
                this.refreshInterval = setInterval(() => {
                    this.refreshMetrics();
                }, 30000); // 30 seconds
            }
        },

        /**
         * Stop auto-refresh
         */
        stopAutoRefresh: function() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
        },

        /**
         * Update last refresh time display
         */
        updateLastRefreshTime: function() {
            const $element = $('#last-refresh-time');
            if ($element.length) {
                const now = new Date();
                $element.text('Last updated: ' + this.formatTime(now));
            }
        },

        /**
         * Show loading state
         */
        showLoading: function(show) {
            const $overlay = $('#monitoring-loading-overlay');
            if (show) {
                if (!$overlay.length) {
                    $('body').append('<div id="monitoring-loading-overlay" class="loading-overlay"><div class="spinner"></div></div>');
                }
                $('#monitoring-loading-overlay').fadeIn(200);
            } else {
                $('#monitoring-loading-overlay').fadeOut(200);
            }
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Show notice
         */
        showNotice: function(message, type = 'info') {
            const $container = $('#monitoring-notices');
            if (!$container.length) {
                $('.wrap').prepend('<div id="monitoring-notices"></div>');
            }

            const noticeClass = type === 'error' ? 'notice-error' : 
                              type === 'success' ? 'notice-success' : 'notice-info';
            
            const $notice = $(`<div class="notice ${noticeClass} is-dismissible"><p>${message}</p></div>`);
            $('#monitoring-notices').html($notice);

            // Auto dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        },

        /**
         * Format metric value based on type
         */
        formatMetricValue: function(key, value) {
            if (key.includes('time') || key.includes('duration')) {
                return value.toFixed(2) + 'ms';
            } else if (key.includes('rate') || key.includes('percentage')) {
                return value.toFixed(1) + '%';
            } else if (key.includes('size') || key.includes('bytes')) {
                return this.formatBytes(value);
            } else if (typeof value === 'number') {
                return value.toLocaleString();
            }
            return value;
        },

        /**
         * Format bytes to human readable
         */
        formatBytes: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Format log details
         */
        formatLogDetails: function(details) {
            if (!details) return '';
            if (typeof details === 'string') return this.escapeHtml(details);
            if (typeof details === 'object') {
                return '<pre class="log-details">' + this.escapeHtml(JSON.stringify(details, null, 2)) + '</pre>';
            }
            return '';
        },

        /**
         * Format time
         */
        formatTime: function(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleString();
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        },

        /**
         * Convert hex to rgba
         */
        hexToRgba: function(hex, alpha) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#zippicks-monitoring-dashboard').length) {
            ZipPicksMonitoring.init();
        }
    });

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        ZipPicksMonitoring.stopAutoRefresh();
    });

})(jQuery);