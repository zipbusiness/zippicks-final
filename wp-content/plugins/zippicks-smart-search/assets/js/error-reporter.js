/**
 * Error Reporter for ZipPicks Smart Search
 * Captures and reports JavaScript errors to the backend
 */

(function() {
    'use strict';
    
    window.ZipPicksErrorReporter = {
        
        // Configuration
        config: {
            maxErrors: 10, // Maximum errors to report per session
            throttleMs: 5000, // Minimum time between error reports
            enableStackTrace: true,
            enableLocalStorage: true,
            storageKey: 'zippicks_error_count'
        },
        
        // State
        errorCount: 0,
        lastErrorTime: 0,
        sessionErrors: [],
        
        // Initialize error reporting
        init: function() {
            // Load error count from storage
            if (this.config.enableLocalStorage && window.localStorage) {
                const storedCount = localStorage.getItem(this.config.storageKey);
                if (storedCount) {
                    const data = JSON.parse(storedCount);
                    if (data.date === new Date().toDateString()) {
                        this.errorCount = data.count;
                    }
                }
            }
            
            // Set up global error handler
            window.addEventListener('error', this.handleError.bind(this));
            
            // Set up unhandled promise rejection handler
            window.addEventListener('unhandledrejection', this.handleRejection.bind(this));
            
            // Monitor AJAX errors
            this.monitorAjaxErrors();
        },
        
        // Handle JavaScript errors
        handleError: function(event) {
            // Ignore errors from other domains
            if (event.filename && !event.filename.includes(window.location.hostname)) {
                return;
            }
            
            // Ignore if we've hit the max errors
            if (this.errorCount >= this.config.maxErrors) {
                return;
            }
            
            // Throttle error reporting
            const now = Date.now();
            if (now - this.lastErrorTime < this.config.throttleMs) {
                return;
            }
            
            this.lastErrorTime = now;
            this.errorCount++;
            
            // Update storage
            if (this.config.enableLocalStorage && window.localStorage) {
                localStorage.setItem(this.config.storageKey, JSON.stringify({
                    date: new Date().toDateString(),
                    count: this.errorCount
                }));
            }
            
            // Prepare error data
            const errorData = {
                message: event.message || 'Unknown error',
                source: event.filename || '',
                lineno: event.lineno || 0,
                colno: event.colno || 0,
                stack: this.config.enableStackTrace && event.error ? 
                       this.getStackTrace(event.error) : '',
                userAgent: navigator.userAgent,
                url: window.location.href,
                timestamp: new Date().toISOString()
            };
            
            // Track in session
            this.sessionErrors.push(errorData);
            
            // Send to backend
            this.reportError(errorData);
            
            // Log to console in development
            if (window.zippicks_search && window.zippicks_search.debug) {
                console.error('ZipPicks Search Error:', errorData);
            }
        },
        
        // Handle unhandled promise rejections
        handleRejection: function(event) {
            const errorData = {
                message: 'Unhandled Promise Rejection: ' + (event.reason || 'Unknown'),
                source: 'Promise',
                lineno: 0,
                colno: 0,
                stack: event.reason && event.reason.stack ? event.reason.stack : '',
                userAgent: navigator.userAgent,
                url: window.location.href,
                timestamp: new Date().toISOString()
            };
            
            this.reportError(errorData);
        },
        
        // Monitor AJAX errors
        monitorAjaxErrors: function() {
            if (typeof jQuery !== 'undefined') {
                jQuery(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
                    // Only track our plugin's AJAX calls
                    if (!ajaxSettings.url.includes('zippicks') && 
                        !ajaxSettings.data.includes('zippicks')) {
                        return;
                    }
                    
                    const errorData = {
                        message: 'AJAX Error: ' + thrownError,
                        source: ajaxSettings.url,
                        lineno: 0,
                        colno: 0,
                        stack: JSON.stringify({
                            status: jqXHR.status,
                            statusText: jqXHR.statusText,
                            responseText: jqXHR.responseText ? 
                                        jqXHR.responseText.substring(0, 500) : '',
                            type: ajaxSettings.type,
                            data: ajaxSettings.data
                        }),
                        userAgent: navigator.userAgent,
                        url: window.location.href,
                        timestamp: new Date().toISOString()
                    };
                    
                    this.reportError(errorData);
                }.bind(this));
            }
        },
        
        // Get stack trace from error object
        getStackTrace: function(error) {
            if (error.stack) {
                return error.stack;
            }
            
            // Fallback for older browsers
            try {
                throw error;
            } catch (e) {
                return e.stack || '';
            }
        },
        
        // Report error to backend
        reportError: function(errorData) {
            // Don't report if we don't have the necessary data
            if (!window.zippicks_search || !window.zippicks_search.ajax_url) {
                return;
            }
            
            // Send via AJAX
            const data = {
                action: 'zippicks_report_error',
                nonce: window.zippicks_search.nonce,
                ...errorData
            };
            
            // Use sendBeacon if available for better reliability
            if (navigator.sendBeacon) {
                const formData = new FormData();
                for (const key in data) {
                    formData.append(key, data[key]);
                }
                navigator.sendBeacon(window.zippicks_search.ajax_url, formData);
            } else if (typeof jQuery !== 'undefined') {
                // Fallback to jQuery AJAX
                jQuery.ajax({
                    url: window.zippicks_search.ajax_url,
                    type: 'POST',
                    data: data,
                    timeout: 5000,
                    // Don't retry on failure to avoid infinite loops
                    error: function() {}
                });
            } else {
                // Fallback to fetch
                fetch(window.zippicks_search.ajax_url, {
                    method: 'POST',
                    body: new URLSearchParams(data),
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                }).catch(function() {
                    // Silently fail
                });
            }
        },
        
        // Manual error reporting
        report: function(message, extra = {}) {
            const errorData = {
                message: message,
                source: 'Manual',
                lineno: 0,
                colno: 0,
                stack: new Error().stack || '',
                userAgent: navigator.userAgent,
                url: window.location.href,
                timestamp: new Date().toISOString(),
                ...extra
            };
            
            this.reportError(errorData);
        },
        
        // Get session error summary
        getSessionSummary: function() {
            return {
                errorCount: this.errorCount,
                errors: this.sessionErrors,
                sessionStart: window.performance && window.performance.timing ? 
                            new Date(window.performance.timing.navigationStart) : null
            };
        },
        
        // Clear session errors
        clearSession: function() {
            this.errorCount = 0;
            this.sessionErrors = [];
            
            if (this.config.enableLocalStorage && window.localStorage) {
                localStorage.removeItem(this.config.storageKey);
            }
        }
    };
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            ZipPicksErrorReporter.init();
        });
    } else {
        ZipPicksErrorReporter.init();
    }
    
})();