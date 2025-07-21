/**
 * Business Intelligence Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Clear cache button
        $('#clear-cache-btn').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(zippicks_bi.strings.confirm_clear_cache)) {
                return;
            }
            
            var $button = $(this);
            $button.prop('disabled', true).text(zippicks_bi.strings.loading);
            
            $.post(zippicks_bi.ajax_url, {
                action: 'zippicks_bi_clear_cache',
                nonce: zippicks_bi.nonce
            })
            .done(function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || zippicks_bi.strings.error);
                }
            })
            .fail(function() {
                alert(zippicks_bi.strings.error);
            })
            .always(function() {
                $button.prop('disabled', false).text('Clear All Cache');
            });
        });
        
        // City search
        $('#search-city-btn').on('click', function(e) {
            e.preventDefault();
            
            var city = $('#city-search').val().trim();
            if (!city) {
                return;
            }
            
            var $button = $(this);
            var $results = $('#search-results');
            var $content = $('#results-content');
            
            $button.prop('disabled', true).text(zippicks_bi.strings.loading);
            $content.html('<p>' + zippicks_bi.strings.loading + '</p>');
            $results.show();
            
            $.post(zippicks_bi.ajax_url, {
                action: 'zippicks_bi_get_city_businesses',
                city: city,
                nonce: zippicks_bi.nonce
            })
            .done(function(response) {
                if (response.success) {
                    var businesses = response.data.businesses;
                    var html = '<p>Found ' + businesses.length + ' businesses in ' + city + '</p>';
                    
                    if (businesses.length > 0) {
                        html += '<table class="wp-list-table widefat fixed striped">';
                        html += '<thead><tr>';
                        html += '<th>Name</th>';
                        html += '<th>Address</th>';
                        html += '<th>Cuisine</th>';
                        html += '<th>Price</th>';
                        html += '<th>Rating</th>';
                        html += '</tr></thead>';
                        html += '<tbody>';
                        
                        businesses.forEach(function(business) {
                            html += '<tr>';
                            html += '<td>' + escapeHtml(business.name) + '</td>';
                            html += '<td>' + escapeHtml(business.address.street1 + ', ' + business.address.city) + '</td>';
                            html += '<td>' + escapeHtml(business.cuisine_types.join(', ')) + '</td>';
                            html += '<td>' + business.price_range.value + '</td>';
                            html += '<td>' + (business.rating ? business.rating.toFixed(1) : 'N/A') + '</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                    }
                    
                    $content.html(html);
                } else {
                    $content.html('<p class="error">' + (response.data.message || zippicks_bi.strings.error) + '</p>');
                }
            })
            .fail(function() {
                $content.html('<p class="error">' + zippicks_bi.strings.error + '</p>');
            })
            .always(function() {
                $button.prop('disabled', false).text('Search');
            });
        });
        
        // Trigger collection button
        $('.trigger-collection-btn').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(zippicks_bi.strings.confirm_trigger)) {
                return;
            }
            
            var $button = $(this);
            var city = $button.data('city');
            
            $button.prop('disabled', true).text(zippicks_bi.strings.loading);
            
            $.post(zippicks_bi.ajax_url, {
                action: 'zippicks_bi_trigger_collection',
                city: city,
                nonce: zippicks_bi.nonce
            })
            .done(function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || zippicks_bi.strings.error);
                }
            })
            .fail(function() {
                alert(zippicks_bi.strings.error);
            })
            .always(function() {
                $button.prop('disabled', false).text('Trigger Collection');
            });
        });
        
    });
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
    }

})(jQuery);