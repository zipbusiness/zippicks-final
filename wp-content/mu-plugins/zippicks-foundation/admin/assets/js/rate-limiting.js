/**
 * Rate Limiting Dashboard JavaScript
 * 
 * Real-time monitoring for our $100B platform's rate limiting system
 */

(function($) {
    'use strict';

    const RateLimitingDashboard = {
        charts: {},
        updateInterval: 10000, // 10 seconds
        updateTimer: null,

        init: function() {
            this.initCharts();
            this.bindEvents();
            this.startAutoUpdate();
            this.loadInitialData();
        },

        initCharts: function() {
            // Usage Over Time Chart
            const usageCtx = document.getElementById('usageChart');
            if (usageCtx) {
                this.charts.usage = new Chart(usageCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'API Requests',
                            data: [],
                            borderColor: '#0073aa',
                            backgroundColor: 'rgba(0, 115, 170, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Taste Graph',
                            data: [],
                            borderColor: '#46b450',
                            backgroundColor: 'rgba(70, 180, 80, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'AI Scores',
                            data: [],
                            borderColor: '#dc3232',
                            backgroundColor: 'rgba(220, 50, 50, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Rate Limit Usage Over Time'
                            },
                            legend: {
                                display: true,
                                position: 'bottom'
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
                                    text: 'Requests'
                                },
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Tier Distribution Chart
            const tierCtx = document.getElementById('tierChart');
            if (tierCtx) {
                this.charts.tier = new Chart(tierCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Free', 'Pro', 'Business', 'Enterprise'],
                        datasets: [{
                            data: [0, 0, 0, 0],
                            backgroundColor: [
                                '#f0f0f0',
                                '#0073aa',
                                '#46b450',
                                '#ffb900'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'User Tier Distribution'
                            },
                            legend: {
                                display: true,
                                position: 'bottom'
                            }
                        }
                    }
                });
            }

            // Rate Limit Exceeded Events
            const eventsCtx = document.getElementById('eventsChart');
            if (eventsCtx) {
                this.charts.events = new Chart(eventsCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['API', 'Taste Graph', 'AI Scores', 'Email', 'Search'],
                        datasets: [{
                            label: 'Exceeded Events (Last Hour)',
                            data: [0, 0, 0, 0, 0],
                            backgroundColor: '#dc3232'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Rate Limit Exceeded Events'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        },

        bindEvents: function() {
            // Reset limit buttons
            $(document).on('click', '.reset-limit', this.resetLimit.bind(this));

            // Clear all limits
            $('#clear-all-limits').on('click', this.clearAllLimits.bind(this));

            // User search
            $('#user-search').on('submit', this.searchUser.bind(this));

            // Tier filter
            $('#tier-filter').on('change', this.filterByTier.bind(this));

            // Auto-update toggle
            $('#auto-update').on('change', this.toggleAutoUpdate.bind(this));

            // Export data
            $('#export-data').on('click', this.exportData.bind(this));
        },

        startAutoUpdate: function() {
            if ($('#auto-update').is(':checked')) {
                this.updateTimer = setInterval(() => {
                    this.updateDashboard();
                }, this.updateInterval);
            }
        },

        toggleAutoUpdate: function(e) {
            if ($(e.target).is(':checked')) {
                this.startAutoUpdate();
            } else {
                clearInterval(this.updateTimer);
            }
        },

        loadInitialData: function() {
            this.updateDashboard();
            this.loadTierStats();
            this.loadTopUsers();
        },

        updateDashboard: function() {
            $.ajax({
                url: zippicks_rate_limiting.api_url + 'rate-limits/stats',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', zippicks_rate_limiting.nonce);
                },
                success: (data) => {
                    this.updateCharts(data);
                    this.updateMetrics(data);
                    this.updateHealth(data);
                },
                error: (xhr, status, error) => {
                    console.error('Failed to update dashboard:', error);
                }
            });
        },

        updateCharts: function(data) {
            // Update usage chart
            if (this.charts.usage && data.usage_history) {
                const now = new Date().toLocaleTimeString();
                const labels = this.charts.usage.data.labels;
                labels.push(now);
                if (labels.length > 30) labels.shift();

                this.charts.usage.data.datasets[0].data.push(data.usage_history.api || 0);
                this.charts.usage.data.datasets[1].data.push(data.usage_history.taste_graph || 0);
                this.charts.usage.data.datasets[2].data.push(data.usage_history.ai_scores || 0);

                // Keep only last 30 data points
                this.charts.usage.data.datasets.forEach(dataset => {
                    if (dataset.data.length > 30) dataset.data.shift();
                });

                this.charts.usage.update();
            }

            // Update exceeded events chart
            if (this.charts.events && data.exceeded_events) {
                this.charts.events.data.datasets[0].data = [
                    data.exceeded_events.api || 0,
                    data.exceeded_events.taste_graph || 0,
                    data.exceeded_events.ai_scores || 0,
                    data.exceeded_events.email || 0,
                    data.exceeded_events.search || 0
                ];
                this.charts.events.update();
            }
        },

        updateMetrics: function(data) {
            // Update metric cards
            $('#total-requests').text(this.formatNumber(data.total_requests || 0));
            $('#exceeded-count').text(this.formatNumber(data.exceeded_count || 0));
            $('#active-users').text(this.formatNumber(data.active_users || 0));
            $('#revenue-impact').text('$' + this.formatNumber(data.revenue_impact || 0));

            // Update percentages
            $('#api-usage').text((data.usage_percentages?.api || 0) + '%');
            $('#taste-graph-usage').text((data.usage_percentages?.taste_graph || 0) + '%');
            $('#ai-scores-usage').text((data.usage_percentages?.ai_scores || 0) + '%');
        },

        updateHealth: function(data) {
            const healthStatus = data.health_status || {};
            
            // Update Redis status
            this.updateHealthIndicator('redis-health', healthStatus.redis);
            
            // Update circuit breaker status
            this.updateHealthIndicator('circuit-breaker-health', healthStatus.circuit_breaker);
            
            // Update overall health
            const overallHealth = healthStatus.overall || 'unknown';
            $('#overall-health')
                .removeClass('health-good health-warning health-critical')
                .addClass('health-' + overallHealth)
                .text(overallHealth.toUpperCase());
        },

        updateHealthIndicator: function(elementId, status) {
            const element = $('#' + elementId);
            element.removeClass('status-active status-warning status-error');
            
            if (status === 'active' || status === 'healthy') {
                element.addClass('status-active').text('Healthy');
            } else if (status === 'degraded') {
                element.addClass('status-warning').text('Degraded');
            } else {
                element.addClass('status-error').text('Error');
            }
        },

        loadTierStats: function() {
            $.ajax({
                url: zippicks_rate_limiting.api_url + 'rate-limits/tier-stats',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', zippicks_rate_limiting.nonce);
                },
                success: (data) => {
                    if (this.charts.tier && data.tier_distribution) {
                        this.charts.tier.data.datasets[0].data = [
                            data.tier_distribution.free || 0,
                            data.tier_distribution.pro || 0,
                            data.tier_distribution.business || 0,
                            data.tier_distribution.enterprise || 0
                        ];
                        this.charts.tier.update();
                    }
                }
            });
        },

        loadTopUsers: function() {
            $.ajax({
                url: zippicks_rate_limiting.api_url + 'rate-limits/top-users',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', zippicks_rate_limiting.nonce);
                },
                success: (data) => {
                    this.renderTopUsers(data.users || []);
                }
            });
        },

        renderTopUsers: function(users) {
            const tbody = $('#top-users tbody');
            tbody.empty();

            users.forEach(user => {
                const row = $('<tr>');
                row.append(`<td>${user.id}</td>`);
                row.append(`<td>${user.name}</td>`);
                row.append(`<td><span class="tier-badge tier-${user.tier}">${user.tier}</span></td>`);
                row.append(`<td>${this.formatNumber(user.requests)}</td>`);
                row.append(`<td>${user.usage_percent}%</td>`);
                row.append(`<td>
                    <button class="button button-small view-user" data-user-id="${user.id}">View</button>
                </td>`);
                tbody.append(row);
            });
        },

        resetLimit: function(e) {
            e.preventDefault();
            const button = $(e.target);
            const key = button.data('key');

            if (!confirm('Reset rate limit for ' + key + '?')) {
                return;
            }

            button.prop('disabled', true).text('Resetting...');

            $.ajax({
                url: zippicks_rate_limiting.api_url + 'rate-limits/reset',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', zippicks_rate_limiting.nonce);
                },
                data: { key: key },
                success: () => {
                    button.text('Reset').prop('disabled', false);
                    this.showNotice('Rate limit reset successfully', 'success');
                    this.updateDashboard();
                },
                error: () => {
                    button.text('Error').prop('disabled', false);
                    this.showNotice('Failed to reset rate limit', 'error');
                }
            });
        },

        clearAllLimits: function(e) {
            e.preventDefault();

            if (!confirm('Clear ALL rate limits? This action cannot be undone.')) {
                return;
            }

            const button = $(e.target);
            button.prop('disabled', true).text('Clearing...');

            $.ajax({
                url: zippicks_rate_limiting.api_url + 'rate-limits/clear-all',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', zippicks_rate_limiting.nonce);
                },
                success: () => {
                    button.text('Clear All').prop('disabled', false);
                    this.showNotice('All rate limits cleared', 'success');
                    this.updateDashboard();
                },
                error: () => {
                    button.text('Error').prop('disabled', false);
                    this.showNotice('Failed to clear rate limits', 'error');
                }
            });
        },

        searchUser: function(e) {
            e.preventDefault();
            const userId = $('#user-id').val();
            if (userId) {
                window.location.href = window.location.pathname + '?page=zippicks-rate-limiting&user_id=' + userId;
            }
        },

        filterByTier: function(e) {
            const tier = $(e.target).val();
            // Implement tier filtering logic
        },

        exportData: function(e) {
            e.preventDefault();
            window.location.href = zippicks_rate_limiting.api_url + 'rate-limits/export?_wpnonce=' + zippicks_rate_limiting.nonce;
        },

        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },

        showNotice: function(message, type) {
            const notice = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
            $('.wrap').prepend(notice);
            setTimeout(() => notice.fadeOut(), 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.zippicks-rate-limiting').length) {
            RateLimitingDashboard.init();
        }
    });

})(jQuery);