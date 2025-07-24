/**
 * ZipPicks Smart Search - Admin JavaScript
 */

(function($) {
    'use strict';
    
    /**
     * Show notification helper function
     * Creates a dismissible notice element and auto-dismisses after 5 seconds
     * 
     * @param {string} message - The message to display
     * @param {string} type - The notification type ('error', 'success', 'warning', 'info')
     */
    function showNotification(message, type) {
        // Map type to WordPress notice classes
        const typeClass = {
            'error': 'notice-error',
            'success': 'notice-success',
            'warning': 'notice-warning',
            'info': 'notice-info'
        }[type] || 'notice-info';
        
        // Create notification element
        const $notification = $('<div>', {
            'class': 'notice is-dismissible ' + typeClass,
            'style': 'margin: 10px 0;'
        }).html('<p>' + message + '</p>');
        
        // Add dismiss button
        const $dismissButton = $('<button>', {
            'type': 'button',
            'class': 'notice-dismiss'
        }).html('<span class="screen-reader-text">Dismiss this notice.</span>');
        
        $notification.append($dismissButton);
        
        // Find container or create one
        let $container = $('.zippicks-notifications');
        if (!$container.length) {
            $container = $('<div class="zippicks-notifications"></div>');
            $('.wrap > h1').after($container);
        }
        
        // Add notification to container
        $container.append($notification);
        
        // Handle dismiss button
        $dismissButton.on('click', function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    $(document).ready(function() {
        
        // Clear cache button
        $('#clear-search-cache').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            // Disable button and show loading
            $button.prop('disabled', true).text(zippicks_search_admin.strings.clearing_cache);
            
            // Make AJAX request
            $.post(zippicks_search_admin.ajax_url, {
                action: 'zippicks_clear_search_cache',
                nonce: zippicks_search_admin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    // Show success message
                    $button.text(zippicks_search_admin.strings.cache_cleared);
                    
                    // Add success styling
                    $button.addClass('button-primary');
                    
                    // Reset after delay
                    setTimeout(function() {
                        $button
                            .prop('disabled', false)
                            .text(originalText)
                            .removeClass('button-primary');
                    }, 2000);
                } else {
                    // Show error
                    showNotification(response.data.message || zippicks_search_admin.strings.error, 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            })
            .fail(function() {
                showNotification(zippicks_search_admin.strings.error, 'error');
                $button.prop('disabled', false).text(originalText);
            });
        });
        
        // Refresh stats button
        $('.refresh-stats').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $container = $button.closest('.analytics-card');
            
            // Show loading
            $container.css('opacity', '0.5');
            
            // Get fresh stats
            $.post(zippicks_search_admin.ajax_url, {
                action: 'zippicks_get_search_stats',
                nonce: zippicks_search_admin.nonce
            })
            .done(function(response) {
                if (response.success && response.data) {
                    // Update stats dynamically without page reload
                    updateAllStats(response.data);
                    showNotification('Stats refreshed successfully', 'success');
                } else {
                    showNotification(response.data.message || zippicks_search_admin.strings.error, 'error');
                }
            })
            .fail(function() {
                showNotification(zippicks_search_admin.strings.error, 'error');
            })
            .always(function() {
                $container.css('opacity', '1');
            });
        });
        
        // Auto-refresh dashboard every 60 seconds
        if ($('.zippicks-search-dashboard').length) {
            setInterval(function() {
                // Only refresh if page is visible
                if (!document.hidden) {
                    updateDashboardStats();
                }
            }, 60000);
        }
        
        // Update dashboard stats via AJAX
        function updateDashboardStats() {
            $.get(zippicks_search_admin.ajax_url, {
                action: 'zippicks_get_search_stats',
                nonce: zippicks_search_admin.nonce
            })
            .done(function(response) {
                if (response.success && response.data) {
                    // Update all stats dynamically
                    updateAllStats(response.data);
                }
            });
        }
        
        // Update cache status indicator
        function updateCacheStatus(cacheData) {
            const $indicator = $('.status-card:has(h3:contains("Cache Status")) .status-indicator');
            
            if (cacheData.enabled) {
                $indicator
                    .removeClass('status-yellow')
                    .addClass('status-green')
                    .html('<span class="dashicons dashicons-yes-alt"></span> ' + 
                          cacheData.driver.charAt(0).toUpperCase() + cacheData.driver.slice(1) + ' Cache Active');
            } else {
                $indicator
                    .removeClass('status-green')
                    .addClass('status-yellow')
                    .html('<span class="dashicons dashicons-warning"></span> No Object Cache');
            }
        }
        
        // Update all stats on the dashboard
        function updateAllStats(data) {
            // Update API Status
            if (data.api_status !== undefined) {
                const $apiIndicator = $('.status-card:has(h3:contains("API Status")) .status-indicator');
                if (data.api_status) {
                    $apiIndicator
                        .removeClass('status-red')
                        .addClass('status-green')
                        .html('<span class="dashicons dashicons-yes-alt"></span> Connected');
                } else {
                    $apiIndicator
                        .removeClass('status-green')
                        .addClass('status-red')
                        .html('<span class="dashicons dashicons-warning"></span> Disconnected');
                }
            }
            
            // Update Cache Status
            if (data.cache) {
                updateCacheStatus(data.cache);
            }
            
            // Update Cache Stats
            if (data.cache_stats) {
                const stats = data.cache_stats;
                $('.cache-stat[data-stat="entries"] .stat-value').text(stats.entries || '0');
                $('.cache-stat[data-stat="hit_rate"] .stat-value').text((stats.hit_rate || '0') + '%');
                $('.cache-stat[data-stat="memory"] .stat-value').text(stats.memory || '0 KB');
            }
            
            // Update Recent Searches
            if (data.recent_searches) {
                const $tbody = $('.recent-searches tbody');
                $tbody.empty();
                
                if (data.recent_searches.length > 0) {
                    data.recent_searches.forEach(function(search) {
                        const $row = $('<tr>').html(
                            '<td>' + escapeHtml(search.query || '') + '</td>' +
                            '<td>' + escapeHtml(search.intent || 'unknown') + '</td>' +
                            '<td>' + (search.results || '0') + '</td>' +
                            '<td>' + escapeHtml(search.location || 'N/A') + '</td>' +
                            '<td>' + escapeHtml(search.time_ago || 'just now') + '</td>'
                        );
                        $tbody.append($row);
                    });
                } else {
                    $tbody.html('<tr><td colspan="5">No recent searches</td></tr>');
                }
            }
        }
        
        // Escape HTML helper
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
    });
    
})(jQuery);