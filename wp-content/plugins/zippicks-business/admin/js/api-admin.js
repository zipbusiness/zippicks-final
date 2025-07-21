/**
 * ZipPicks Business API Admin JavaScript
 *
 * Handles AJAX interactions for API sync operations
 * in the business edit screen.
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Sync API data button
        $('.sync-api-data').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const postId = button.data('post-id');
            const zpid = button.data('zpid');
            const originalText = button.html();
            
            // Disable button and show loading
            button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Syncing...');
            
            // Clear any previous messages
            $('.api-messages').hide().find('.notice').removeClass('notice-success notice-error').empty();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'zippicks_sync_api_data',
                    post_id: postId,
                    zpid: zpid,
                    nonce: zippicks_business_api.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('Successfully synced with API!', 'success');
                        
                        // Update displayed data if provided
                        if (response.data.verified !== undefined) {
                            updateVerificationStatus(response.data.verified, response.data.confidence);
                        }
                        if (response.data.last_sync) {
                            updateLastSync(response.data.last_sync);
                        }
                        
                        // Refresh page after 2 seconds to show all changes
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                        
                    } else {
                        showMessage('Sync failed: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('Network error: ' + error, 'error');
                },
                complete: function() {
                    button.prop('disabled', false).html(originalText);
                }
            });
        });
        
        // Link ZPID button
        $('.link-zpid').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const postId = button.data('post-id');
            const zpid = $('#manual-zpid').val().trim();
            
            if (!zpid) {
                showMessage('Please enter a valid ZPID', 'error');
                $('#manual-zpid').focus();
                return;
            }
            
            // Validate ZPID format (basic check)
            if (!zpid.match(/^[a-zA-Z0-9_-]+$/)) {
                showMessage('ZPID format appears invalid', 'error');
                $('#manual-zpid').focus();
                return;
            }
            
            const originalText = button.text();
            button.prop('disabled', true).text('Linking...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'zippicks_link_zpid',
                    post_id: postId,
                    zpid: zpid,
                    nonce: zippicks_business_api.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('Successfully linked ZPID and synced data!', 'success');
                        
                        if (response.data.reload) {
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        }
                        
                    } else {
                        showMessage('Link failed: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('Network error: ' + error, 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Search business button
        $('.search-api-business').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const postId = button.data('post-id');
            const originalText = button.html();
            
            button.prop('disabled', true).html('<span class="dashicons dashicons-search spin"></span> Searching...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'zippicks_search_business',
                    post_id: postId,
                    nonce: zippicks_business_api.nonce
                },
                success: function(response) {
                    if (response.success) {
                        displaySearchResults(response.data.results);
                        showMessage(response.data.message, 'success');
                    } else {
                        showMessage('Search failed: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('Search error: ' + error, 'error');
                },
                complete: function() {
                    button.prop('disabled', false).html(originalText);
                }
            });
        });
        
        // Handle search result selection
        $(document).on('click', '.search-result-item', function(e) {
            e.preventDefault();
            
            const zpid = $(this).data('zpid');
            const businessName = $(this).find('.business-name').text();
            
            if (confirm('Link this business with ZPID: ' + zpid + '?')) {
                $('#manual-zpid').val(zpid);
                $('.link-zpid').trigger('click');
            }
        });
        
        // Allow Enter key in ZPID input
        $('#manual-zpid').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $('.link-zpid').trigger('click');
            }
        });
    });
    
    /**
     * Show message in the API messages area
     */
    function showMessage(message, type) {
        const messagesDiv = $('.api-messages');
        const noticeDiv = messagesDiv.find('.notice');
        
        noticeDiv
            .removeClass('notice-success notice-error')
            .addClass('notice-' + type)
            .html('<p>' + message + '</p>');
        
        messagesDiv.show();
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                messagesDiv.fadeOut();
            }, 5000);
        }
    }
    
    /**
     * Update verification status display
     */
    function updateVerificationStatus(verified, confidence) {
        const statusBadge = $('.status-badge');
        
        if (verified) {
            statusBadge
                .removeClass('unverified')
                .addClass('verified')
                .html('Verified' + (confidence ? ' <small>(' + Math.round(confidence * 100) + '%)</small>' : ''));
        } else {
            statusBadge
                .removeClass('verified')
                .addClass('unverified')
                .text('Unverified');
        }
    }
    
    /**
     * Update last sync display
     */
    function updateLastSync(lastSync) {
        const lastSyncP = $('p:contains("Last Sync:")');
        if (lastSyncP.length) {
            lastSyncP.html('<strong>Last Sync:</strong> ' + lastSync);
        }
    }
    
    /**
     * Display search results
     */
    function displaySearchResults(results) {
        if (results.length === 0) {
            showMessage('No businesses found', 'error');
            return;
        }
        
        let html = '<div class="search-results"><h4>Search Results:</h4>';
        
        results.forEach(function(business) {
            html += '<div class="search-result-item" data-zpid="' + business.zpid + '" style="border: 1px solid #ddd; padding: 10px; margin: 5px 0; cursor: pointer;">';
            html += '<div class="business-name" style="font-weight: bold;">' + business.name + '</div>';
            html += '<div class="business-location" style="color: #666;">' + business.location + '</div>';
            html += '<div class="business-zpid" style="font-size: 11px; color: #999;">ZPID: ' + business.zpid + '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        
        // Insert or replace search results
        if ($('.search-results').length) {
            $('.search-results').replaceWith(html);
        } else {
            $('.search-business').after(html);
        }
    }
    
})(jQuery);

// Add spinning animation for dashicons
const style = document.createElement('style');
style.textContent = `
    .dashicons.spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .search-result-item:hover {
        background-color: #f0f0f0;
    }
`;
document.head.appendChild(style);