/**
 * ZipPicks Smart Search - Admin JavaScript
 */

(function($) {
    'use strict';
    
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
                    alert(response.data.message || zippicks_search_admin.strings.error);
                    $button.prop('disabled', false).text(originalText);
                }
            })
            .fail(function() {
                alert(zippicks_search_admin.strings.error);
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
                if (response.success) {
                    // Reload page to show new stats
                    location.reload();
                } else {
                    alert(response.data.message || zippicks_search_admin.strings.error);
                }
            })
            .fail(function() {
                alert(zippicks_search_admin.strings.error);
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
                    // Update cache status
                    if (response.data.cache) {
                        updateCacheStatus(response.data.cache);
                    }
                    
                    // Could update other stats here if needed
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
        
    });
    
})(jQuery);