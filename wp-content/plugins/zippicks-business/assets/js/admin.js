/**
 * Admin JavaScript for ZipPicks Business
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Quick edit functionality
        $('.zippicks-quick-edit').on('change', 'input, select', function() {
            var $this = $(this);
            var businessId = $this.data('business-id');
            var field = $this.data('field');
            var value = $this.val();
            var $parent = $this.parent();
            
            // Show spinner
            $parent.append('<span class="zippicks-spinner"></span>');
            
            // Save via AJAX
            $.ajax({
                url: zippicks_business.ajax_url,
                type: 'POST',
                data: {
                    action: 'zippicks_business_quick_edit',
                    nonce: zippicks_business.nonce,
                    business_id: businessId,
                    field: field,
                    value: value
                },
                success: function(response) {
                    if (response.success) {
                        // Show success indicator
                        $parent.find('.zippicks-spinner').remove();
                        $parent.append('<span class="dashicons dashicons-yes" style="color: #00a32a;"></span>');
                        setTimeout(function() {
                            $parent.find('.dashicons-yes').fadeOut();
                        }, 2000);
                    } else {
                        alert(zippicks_business.strings.error);
                        $parent.find('.zippicks-spinner').remove();
                    }
                },
                error: function() {
                    alert(zippicks_business.strings.error);
                    $parent.find('.zippicks-spinner').remove();
                }
            });
        });
        
        // Verify business
        $('.verify-business').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(zippicks_business.strings.confirm_verify)) {
                return;
            }
            
            var $button = $(this);
            var businessId = $button.data('business-id');
            
            $button.prop('disabled', true).text(zippicks_business.strings.processing);
            
            $.ajax({
                url: zippicks_business.ajax_url,
                type: 'POST',
                data: {
                    action: 'zippicks_verify_business',
                    nonce: zippicks_business.nonce,
                    business_id: businessId
                },
                success: function(response) {
                    if (response.success) {
                        $button.replaceWith('<span class="zippicks-verified"><span class="dashicons dashicons-yes-alt"></span> Verified</span>');
                    } else {
                        alert(zippicks_business.strings.error);
                        $button.prop('disabled', false).text('Verify');
                    }
                },
                error: function() {
                    alert(zippicks_business.strings.error);
                    $button.prop('disabled', false).text('Verify');
                }
            });
        });
        
        // Change tier
        $('.change-tier').on('change', function() {
            if (!confirm(zippicks_business.strings.confirm_tier_change)) {
                $(this).val($(this).data('original-value'));
                return;
            }
            
            var $select = $(this);
            var businessId = $select.data('business-id');
            var tier = $select.val();
            
            $select.prop('disabled', true);
            
            $.ajax({
                url: zippicks_business.ajax_url,
                type: 'POST',
                data: {
                    action: 'zippicks_change_business_tier',
                    nonce: zippicks_business.nonce,
                    business_id: businessId,
                    tier: tier
                },
                success: function(response) {
                    if (response.success) {
                        $select.prop('disabled', false);
                        $select.data('original-value', tier);
                        
                        // Update tier badge if exists
                        var $badge = $select.closest('tr').find('.tier-badge');
                        if ($badge.length) {
                            $badge.removeClass('tier-basic tier-featured tier-premium')
                                  .addClass('tier-' + tier)
                                  .text(tier.charAt(0).toUpperCase() + tier.slice(1));
                        }
                    } else {
                        alert(zippicks_business.strings.error);
                        $select.val($select.data('original-value'));
                        $select.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(zippicks_business.strings.error);
                    $select.val($select.data('original-value'));
                    $select.prop('disabled', false);
                }
            });
        });
        
        // Analytics date range
        $('#analytics-date-range').on('change', function() {
            var days = $(this).val();
            window.location.href = window.location.pathname + '?page=zippicks-business-analytics&days=' + days;
        });
        
        // Business score color coding
        $('.business-score').each(function() {
            var $score = $(this);
            var score = parseFloat($score.text());
            
            if (score >= 8) {
                $score.addClass('high');
            } else if (score >= 6) {
                $score.addClass('medium');
            } else {
                $score.addClass('low');
            }
        });
        
        // Bulk actions enhancement
        $('#doaction, #doaction2').on('click', function(e) {
            var action = $(this).prev('select').val();
            
            if (action === 'verify' || action === 'feature' || action === 'delete') {
                var checked = $('tbody .check-column input:checked').length;
                
                if (checked === 0) {
                    e.preventDefault();
                    alert('Please select at least one business.');
                    return;
                }
                
                if (action === 'delete' && !confirm('Are you sure you want to delete the selected businesses?')) {
                    e.preventDefault();
                    return;
                }
            }
        });
        
        // Table row hover actions
        $('.wp-list-table tbody tr').hover(
            function() {
                $(this).find('.row-actions').css('visibility', 'visible');
            },
            function() {
                $(this).find('.row-actions').css('visibility', 'hidden');
            }
        );
        
        // Settings form validation
        $('#zippicks-business-settings-form').on('submit', function(e) {
            var rateLimit = $('#rate-limit-requests').val();
            
            if (rateLimit < 1 || rateLimit > 1000) {
                e.preventDefault();
                alert('Rate limit must be between 1 and 1000 requests per minute.');
                $('#rate-limit-requests').focus();
            }
        });
        
    });

})(jQuery);