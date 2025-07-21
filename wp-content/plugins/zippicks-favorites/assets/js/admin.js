/**
 * ZipPicks Favorites Admin JavaScript
 */

jQuery(document).ready(function($) {
    // Initialize charts if analytics data is available
    if (typeof analyticsData !== 'undefined') {
        initializeCharts();
    }
    
    function initializeCharts() {
        // Timeline Chart
        const timelineCtx = document.getElementById('favorites-timeline-chart');
        if (timelineCtx) {
            new Chart(timelineCtx, {
                type: 'line',
                data: {
                    labels: analyticsData.timeline_data.map(d => {
                        const date = new Date(d.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Favorites Saved',
                        data: analyticsData.timeline_data.map(d => d.count),
                        borderColor: '#007cba',
                        backgroundColor: 'rgba(0, 124, 186, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
        
        // Cities Chart
        const citiesCtx = document.getElementById('cities-chart');
        if (citiesCtx) {
            new Chart(citiesCtx, {
                type: 'bar',
                data: {
                    labels: analyticsData.city_distribution.map(c => c.city),
                    datasets: [{
                        label: 'Favorites',
                        data: analyticsData.city_distribution.map(c => c.count),
                        backgroundColor: [
                            '#007cba',
                            '#00a0d2',
                            '#0073aa',
                            '#005177',
                            '#003c5a'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
    }
    
    // Auto-refresh dashboard widget
    if ($('.zippicks-favorites-widget').length) {
        setInterval(function() {
            refreshDashboardWidget();
        }, 60000); // Refresh every minute
    }
    
    function refreshDashboardWidget() {
        $.ajax({
            url: zipPicksFavoritesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zippicks_refresh_favorites_widget',
                nonce: zipPicksFavoritesAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateWidgetData(response.data);
                }
            }
        });
    }
    
    function updateWidgetData(data) {
        $('.widget-summary .value').each(function(index) {
            const newValue = Object.values(data)[index];
            const $value = $(this);
            
            // Animate number change
            const currentValue = parseInt($value.text().replace(/,/g, ''));
            const targetValue = parseInt(newValue);
            
            if (currentValue !== targetValue) {
                $({ value: currentValue }).animate({ value: targetValue }, {
                    duration: 500,
                    step: function() {
                        $value.text(Math.floor(this.value).toLocaleString());
                    },
                    complete: function() {
                        $value.text(targetValue.toLocaleString());
                    }
                });
            }
        });
    }
    
    // Export functionality
    $('.export-analytics').on('click', function(e) {
        e.preventDefault();
        
        const format = $(this).data('format');
        const params = new URLSearchParams({
            action: 'zippicks_export_analytics',
            format: format,
            nonce: zipPicksFavoritesAdmin.nonce
        });
        
        window.location.href = zipPicksFavoritesAdmin.ajaxUrl + '?' + params.toString();
    });
    
    // Date range picker for analytics
    if ($('#analytics-date-range').length) {
        $('#analytics-date-range').on('change', function() {
            const range = $(this).val();
            
            // Reload page with new date range
            const url = new URL(window.location);
            url.searchParams.set('range', range);
            window.location.href = url.toString();
        });
    }
});