/**
 * ZipPicks Social - Admin Scripts
 * 
 * @package ZipPicks_Social
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin Manager
     */
    const ZipPicksSocialAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Settings tabs
            $('.zps-settings-tab').on('click', this.handleTabClick);
            
            // Confirm actions
            $('.zps-confirm-action').on('click', this.handleConfirmAction);
            
            // Export data
            $('#zps-export-data').on('click', this.handleExportData);
        },
        
        /**
         * Handle tab clicks
         */
        handleTabClick: function(e) {
            e.preventDefault();
            
            const $tab = $(this);
            const target = $tab.data('target');
            
            // Update active tab
            $('.zps-settings-tab').removeClass('active');
            $tab.addClass('active');
            
            // Show/hide content
            $('.zps-settings-panel').hide();
            $('#' + target).show();
        },
        
        /**
         * Handle confirm actions
         */
        handleConfirmAction: function(e) {
            const message = $(this).data('confirm') || zippicksSocialAdmin.strings.confirmDelete;
            
            if (!confirm(message)) {
                e.preventDefault();
            }
        },
        
        /**
         * Handle export data
         */
        handleExportData: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            // Set loading state
            $button.prop('disabled', true).text('Exporting...');
            
            // Make AJAX request
            $.ajax({
                url: zippicksSocialAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zippicks_social_export_data',
                    nonce: zippicksSocialAdmin.nonce
                },
                xhrFields: {
                    responseType: 'blob'
                },
                success: function(blob) {
                    // Create download link
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'zippicks-social-export.csv';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                },
                error: function() {
                    alert(zippicksSocialAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        ZipPicksSocialAdmin.init();
    });

})(jQuery);