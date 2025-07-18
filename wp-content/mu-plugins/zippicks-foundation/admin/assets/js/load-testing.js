/**
 * ZipPicks Load Testing JavaScript
 * 
 * Enterprise load testing interface functionality
 */

(function($) {
    'use strict';

    class LoadTestingManager {
        constructor() {
            this.refreshInterval = null;
            this.isRefreshing = false;
            this.charts = {};
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.startAutoRefresh();
            this.initializeCharts();
        }

        bindEvents() {
            // Form submission
            $('#new-load-test-form').on('submit', this.handleSubmitTest.bind(this));
            
            // Stop test buttons
            $(document).on('click', '.stop-test-btn', this.handleStopTest.bind(this));
            
            // Advanced options toggle
            $('#toggle-advanced').on('click', this.toggleAdvancedOptions.bind(this));
            
            // History filters
            $('#history-filter-suite, #history-filter-status, #history-filter-date').on('change', this.filterHistory.bind(this));
            
            // Refresh history
            $('#refresh-history').on('click', this.refreshHistory.bind(this));
            
            // View test details
            $(document).on('click', '.view-details-btn', this.viewTestDetails.bind(this));
            
            // Delete test
            $(document).on('click', '.delete-test-btn', this.deleteTest.bind(this));
            
            // Modal close
            $('.modal-close, .modal').on('click', this.closeModal.bind(this));
            $('.modal-content').on('click', function(e) {
                e.stopPropagation();
            });
            
            // Load more history
            $('#load-more-history').on('click', this.loadMoreHistory.bind(this));
        }

        handleSubmitTest(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitBtn = $form.find('#run-test-btn');
            const formData = new FormData($form[0]);
            
            // Disable submit button
            $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Starting Test...');
            
            // Prepare AJAX data
            const ajaxData = {
                action: 'zippicks_run_load_test',
                nonce: zippicksLoadTesting.nonce,
                test_suite: formData.get('test_suite'),
                config: {}
            };
            
            // Collect config data
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('config[')) {
                    const configKey = key.replace('config[', '').replace(']', '');
                    ajaxData.config[configKey] = value;
                }
            }
            
            $.ajax({
                url: zippicksLoadTesting.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (response.success) {
                        this.showMessage('Load test started successfully!', 'success');
                        $form[0].reset();
                        this.refreshActiveSessions();
                    } else {
                        this.showMessage('Failed to start load test: ' + (response.data.message || 'Unknown error'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showMessage('Failed to start load test: ' + error, 'error');
                },
                complete: () => {
                    $submitBtn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Run Load Test');
                }
            });
        }

        handleStopTest(e) {
            const testId = $(e.target).closest('.stop-test-btn').data('test-id');
            
            if (!confirm(zippicksLoadTesting.strings.confirmStop)) {
                return;
            }
            
            const $btn = $(e.target).closest('.stop-test-btn');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Stopping...');
            
            $.ajax({
                url: zippicksLoadTesting.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zippicks_stop_load_test',
                    nonce: zippicksLoadTesting.nonce,
                    test_id: testId
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('Load test stopped successfully!', 'success');
                        this.refreshActiveSessions();
                    } else {
                        this.showMessage('Failed to stop load test: ' + (response.data.message || 'Unknown error'), 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Stop Test');
                    }
                },
                error: (xhr, status, error) => {
                    this.showMessage('Failed to stop load test: ' + error, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Stop Test');
                }
            });
        }

        toggleAdvancedOptions() {
            const $advancedOptions = $('.advanced-options');
            const $toggleBtn = $('#toggle-advanced');
            
            $advancedOptions.slideToggle();
            
            if ($advancedOptions.is(':visible')) {
                $toggleBtn.html('<span class="dashicons dashicons-admin-settings"></span> Hide Advanced');
            } else {
                $toggleBtn.html('<span class="dashicons dashicons-admin-settings"></span> Advanced Options');
            }
        }

        filterHistory() {
            const suite = $('#history-filter-suite').val();
            const status = $('#history-filter-status').val();
            const date = $('#history-filter-date').val();
            
            this.loadHistory(1, {
                suite: suite,
                status: status,
                date: date
            });
        }

        refreshHistory() {
            const $btn = $('#refresh-history');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Refreshing...');
            
            this.loadHistory(1, {}, () => {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Refresh');
            });
        }

        loadHistory(page = 1, filters = {}, callback = null) {
            $.ajax({
                url: zippicksLoadTesting.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zippicks_get_test_history',
                    nonce: zippicksLoadTesting.nonce,
                    page: page,
                    limit: 20,
                    filters: filters
                },
                success: (response) => {
                    if (response.success) {
                        this.renderHistoryTable(response.data, page === 1);
                    } else {
                        this.showMessage('Failed to load test history', 'error');
                    }
                },
                error: () => {
                    this.showMessage('Failed to load test history', 'error');
                },
                complete: () => {
                    if (callback) callback();
                }
            });
        }

        loadMoreHistory() {
            const currentRows = $('#test-history-tbody tr').length;
            const page = Math.floor(currentRows / 20) + 1;
            
            this.loadHistory(page, {}, null);
        }

        renderHistoryTable(data, replace = true) {
            const $tbody = $('#test-history-tbody');
            
            if (replace) {
                $tbody.empty();
            }
            
            if (!data || data.length === 0) {
                if (replace) {
                    $tbody.html('<tr><td colspan="9" class="no-items">No test history found.</td></tr>');
                }
                return;
            }
            
            data.forEach(test => {
                const row = this.createHistoryRow(test);
                $tbody.append(row);
            });
        }

        createHistoryRow(test) {
            const statusBadge = this.getStatusBadge(test.status);
            const startTime = new Date(test.start_time).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            return `
                <tr data-test-id="${test.test_id}">
                    <td><code>${test.test_id}</code></td>
                    <td>${this.formatTestSuite(test.test_suite)}</td>
                    <td>${startTime}</td>
                    <td>${this.formatDuration(test.duration)}</td>
                    <td>${statusBadge}</td>
                    <td>${test.rps || 'N/A'}</td>
                    <td>${test.response_time || 'N/A'}ms</td>
                    <td>${test.error_rate || 'N/A'}%</td>
                    <td>
                        <button type="button" class="button button-small view-details-btn" data-test-id="${test.test_id}">
                            View Details
                        </button>
                        <button type="button" class="button button-small button-link-delete delete-test-btn" data-test-id="${test.test_id}">
                            Delete
                        </button>
                    </td>
                </tr>
            `;
        }

        viewTestDetails(e) {
            const testId = $(e.target).data('test-id');
            
            // For now, show a placeholder modal
            // In production, this would load detailed test results
            const modalContent = `
                <h4>Test Details: ${testId}</h4>
                <div class="test-detail-section">
                    <h5>Configuration</h5>
                    <p>Detailed test configuration would be displayed here...</p>
                </div>
                <div class="test-detail-section">
                    <h5>Results Summary</h5>
                    <p>Comprehensive test results would be displayed here...</p>
                </div>
                <div class="test-detail-section">
                    <h5>Performance Charts</h5>
                    <p>Interactive charts showing performance over time...</p>
                </div>
            `;
            
            $('#test-details-content').html(modalContent);
            $('#test-details-modal').show();
        }

        deleteTest(e) {
            const testId = $(e.target).data('test-id');
            
            if (!confirm(zippicksLoadTesting.strings.confirmDelete)) {
                return;
            }
            
            const $btn = $(e.target);
            $btn.prop('disabled', true).text('Deleting...');
            
            $.ajax({
                url: zippicksLoadTesting.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zippicks_delete_test_result',
                    nonce: zippicksLoadTesting.nonce,
                    test_id: testId
                },
                success: (response) => {
                    if (response.success) {
                        $(`tr[data-test-id="${testId}"]`).fadeOut(() => {
                            $(this).remove();
                        });
                        this.showMessage('Test result deleted successfully!', 'success');
                    } else {
                        this.showMessage('Failed to delete test result: ' + (response.data.message || 'Unknown error'), 'error');
                        $btn.prop('disabled', false).text('Delete');
                    }
                },
                error: (xhr, status, error) => {
                    this.showMessage('Failed to delete test result: ' + error, 'error');
                    $btn.prop('disabled', false).text('Delete');
                }
            });
        }

        closeModal(e) {
            if (e.target === e.currentTarget) {
                $('.modal').hide();
            }
        }

        startAutoRefresh() {
            this.refreshInterval = setInterval(() => {
                this.refreshStatus();
            }, zippicksLoadTesting.refreshInterval);
        }

        refreshStatus() {
            if (this.isRefreshing) return;
            
            this.isRefreshing = true;
            
            $.ajax({
                url: zippicksLoadTesting.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zippicks_get_test_status',
                    nonce: zippicksLoadTesting.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateStatus(response.data);
                    }
                },
                complete: () => {
                    this.isRefreshing = false;
                }
            });
        }

        refreshActiveSessions() {
            this.refreshStatus();
        }

        updateStatus(data) {
            // Update active sessions
            this.updateActiveSessions(data.active_sessions);
            
            // Update performance trends
            this.updatePerformanceTrends(data.performance_trends);
            
            // Update quick stats
            this.updateQuickStats(data);
        }

        updateActiveSessions(sessions) {
            // Implementation would update active session display
            // For now, just refresh the page if there are changes
        }

        updatePerformanceTrends(trends) {
            // Update charts with new data
            if (trends && trends.rps_over_time) {
                this.updateChart('rps', trends.rps_over_time);
            }
            
            if (trends && trends.response_time_over_time) {
                this.updateChart('responseTime', trends.response_time_over_time);
            }
        }

        updateQuickStats(data) {
            // Update quick stats displays
            $('#avg-rps').text(this.formatNumber(data.avg_rps || 0));
            $('#avg-response-time').text((data.avg_response_time || 0) + 'ms');
            $('#error-rate').text((data.error_rate || 0) + '%');
            $('#success-rate').text((100 - (data.error_rate || 0)) + '%');
        }

        initializeCharts() {
            // Charts are initialized in the PHP template
            // This method could be used for additional chart configuration
        }

        updateChart(chartType, data) {
            const chart = window[chartType + 'Chart'];
            if (!chart) return;
            
            chart.data.labels = data.labels || [];
            chart.data.datasets[0].data = data.values || [];
            chart.update();
        }

        showMessage(message, type = 'info') {
            const $container = $('#load-testing-messages');
            const alertClass = type === 'error' ? 'notice-error' : 
                              type === 'success' ? 'notice-success' : 'notice-info';
            
            const $notice = $(`
                <div class="notice ${alertClass} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $container.append($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
            
            // Handle manual dismiss
            $notice.find('.notice-dismiss').on('click', () => {
                $notice.fadeOut(() => $notice.remove());
            });
        }

        getStatusBadge(status) {
            const badges = {
                running: '<span class="badge badge-primary">Running</span>',
                completed: '<span class="badge badge-success">Completed</span>',
                failed: '<span class="badge badge-danger">Failed</span>',
                stopped: '<span class="badge badge-warning">Stopped</span>'
            };
            
            return badges[status] || '<span class="badge badge-secondary">Unknown</span>';
        }

        formatTestSuite(suite) {
            return suite.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        }

        formatDuration(seconds) {
            if (seconds < 60) {
                return seconds + 's';
            } else if (seconds < 3600) {
                return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's';
            } else {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                return hours + 'h ' + minutes + 'm';
            }
        }

        formatNumber(number) {
            if (number >= 1000000) {
                return (number / 1000000).toFixed(1) + 'M';
            } else if (number >= 1000) {
                return (number / 1000).toFixed(1) + 'K';
            } else {
                return number.toString();
            }
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        window.loadTestingManager = new LoadTestingManager();
    });

    // Add spinning animation for loading indicators
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .spin { animation: spin 1s linear infinite; }
        `)
        .appendTo('head');

})(jQuery);