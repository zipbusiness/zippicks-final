/**
 * ZipPicks Master Critic - Hybrid Admin Dashboard JavaScript
 * Handles dashboard interactions and real-time updates
 */

(function($) {
    'use strict';
    
    // Dashboard controller
    const HybridDashboard = {
        
        // Initialize dashboard
        init: function() {
            this.bindEvents();
            this.startAutoRefresh();
            this.initCharts();
        },
        
        // Bind event handlers
        bindEvents: function() {
            $('#refresh-dashboard').on('click', this.refreshDashboard.bind(this));
            $('#warm-cache').on('click', this.warmCache.bind(this));
            $('#clear-stats').on('click', this.clearStatistics.bind(this));
            
            // Recommendation actions
            $(document).on('click', '.recommendation-action', this.handleRecommendation.bind(this));
        },
        
        // Refresh dashboard data
        refreshDashboard: function() {
            const $spinner = $('#refresh-spinner');
            const $button = $('#refresh-dashboard');
            
            $spinner.show();
            $button.prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'zippicks_refresh_hybrid_dashboard',
                    nonce: zippicks_hybrid_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update all dashboard sections
                        HybridDashboard.updateMetrics(response.data);
                        HybridDashboard.showNotice('Dashboard refreshed successfully', 'success');
                    } else {
                        HybridDashboard.showNotice('Failed to refresh dashboard', 'error');
                    }
                },
                error: function() {
                    HybridDashboard.showNotice('Error refreshing dashboard', 'error');
                },
                complete: function() {
                    $spinner.hide();
                    $button.prop('disabled', false);
                }
            });
        },
        
        // Warm cache
        warmCache: function() {
            const $button = $('#warm-cache');
            $button.prop('disabled', true).text('Warming Cache...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'zippicks_warm_cache',
                    nonce: zippicks_hybrid_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        HybridDashboard.showNotice('Cache warming completed. Hit rate: ' + response.data.hit_rate + '%', 'success');
                        HybridDashboard.refreshDashboard();
                    } else {
                        HybridDashboard.showNotice('Cache warming failed', 'error');
                    }
                },
                error: function() {
                    HybridDashboard.showNotice('Error warming cache', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-performance"></span> Warm Cache');
                }
            });
        },
        
        // Clear statistics
        clearStatistics: function() {
            if (!confirm('Are you sure you want to clear all statistics? This action cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'zippicks_clear_hybrid_stats',
                    nonce: zippicks_hybrid_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        HybridDashboard.showNotice('Statistics cleared successfully', 'success');
                        HybridDashboard.refreshDashboard();
                    } else {
                        HybridDashboard.showNotice('Failed to clear statistics', 'error');
                    }
                },
                error: function() {
                    HybridDashboard.showNotice('Error clearing statistics', 'error');
                }
            });
        },
        
        // Handle recommendation actions
        handleRecommendation: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const action = $button.data('action');
            
            if (action) {
                // Execute recommendation action
                this.executeRecommendation(action, $button);
            }
        },
        
        // Execute recommendation
        executeRecommendation: function(action, $button) {
            $button.prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'zippicks_execute_recommendation',
                    recommendation: action,
                    nonce: zippicks_hybrid_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        HybridDashboard.showNotice('Recommendation applied successfully', 'success');
                        $button.closest('.recommendation-item').fadeOut();
                    } else {
                        HybridDashboard.showNotice('Failed to apply recommendation', 'error');
                    }
                },
                error: function() {
                    HybridDashboard.showNotice('Error applying recommendation', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },
        
        // Update dashboard metrics
        updateMetrics: function(data) {
            // Update cost metrics
            if (data.costs) {
                $('.metric-value[data-metric="daily-cost"]').text('$' + data.costs.daily.toFixed(2));
                $('.metric-value[data-metric="monthly-projection"]').text('$' + data.costs.monthly_projection.toFixed(2));
                $('.progress-fill').css('width', data.budget.percentage + '%').text(data.budget.percentage + '%');
            }
            
            // Update performance metrics
            if (data.performance) {
                $('.metric-value[data-metric="hit-rate"]').text(data.performance.hit_rate.toFixed(1) + '%');
                $('.metric-value[data-metric="enhancement-rate"]').text(data.performance.enhancement_rate.toFixed(1) + '%');
                $('.metric-value[data-metric="response-time"]').text(data.performance.avg_response_time + 'ms');
            }
            
            // Update API usage
            if (data.api_usage) {
                $.each(data.api_usage, function(api, stats) {
                    $('[data-api="' + api + '"] .api-calls').text(stats.calls.toLocaleString() + ' calls');
                    if (stats.cost !== undefined) {
                        $('[data-api="' + api + '"] .api-cost').text('$' + stats.cost.toFixed(2));
                    }
                });
            }
            
            // Update recent queries table
            if (data.recent_queries) {
                this.updateRecentQueries(data.recent_queries);
            }
        },
        
        // Update recent queries table
        updateRecentQueries: function(queries) {
            const $tbody = $('.recent-queries tbody');
            $tbody.empty();
            
            $.each(queries, function(i, query) {
                const row = `
                    <tr>
                        <td>${query.business_name}, ${query.city}</td>
                        <td><span class="source-badge ${query.source}">${query.source}</span></td>
                        <td>$${query.cost.toFixed(4)}</td>
                        <td>${query.time_ago}</td>
                    </tr>
                `;
                $tbody.append(row);
            });
        },
        
        // Initialize charts
        initCharts: function() {
            // Future enhancement: Add Chart.js visualizations
            // This is a placeholder for chart initialization
        },
        
        // Start auto-refresh
        startAutoRefresh: function() {
            // Refresh dashboard every 5 minutes
            setInterval(function() {
                HybridDashboard.refreshDashboard();
            }, 300000);
        },
        
        // Show admin notice
        showNotice: function(message, type) {
            const notice = $('<div>')
                .addClass('notice notice-' + type + ' is-dismissible')
                .html('<p>' + message + '</p>');
            
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Make dismissible
            notice.on('click', '.notice-dismiss', function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        },
        
        // Format numbers with commas
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },
        
        // Human-readable time difference
        timeSince: function(date) {
            const seconds = Math.floor((new Date() - date) / 1000);
            let interval = Math.floor(seconds / 31536000);
            
            if (interval > 1) return interval + " years ago";
            interval = Math.floor(seconds / 2592000);
            if (interval > 1) return interval + " months ago";
            interval = Math.floor(seconds / 86400);
            if (interval > 1) return interval + " days ago";
            interval = Math.floor(seconds / 3600);
            if (interval > 1) return interval + " hours ago";
            interval = Math.floor(seconds / 60);
            if (interval > 1) return interval + " minutes ago";
            return Math.floor(seconds) + " seconds ago";
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.zippicks-hybrid-dashboard').length) {
            HybridDashboard.init();
        }
    });
    
    // Export for external use
    window.ZipPicksHybridDashboard = HybridDashboard;
    
})(jQuery);