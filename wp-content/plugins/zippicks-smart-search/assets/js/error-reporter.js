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
                    try {
                        const data = JSON.parse(storedCount);
                        if (data && typeof data === 'object' && data.date === new Date().toDateString()) {
                            this.errorCount = data.count || 0;
                        }
                    } catch (parseError) {
                        // Log parsing error and reset corrupted data
                        console.warn('ZipPicks Error Reporter: Failed to parse stored error data, resetting:', parseError);
                        localStorage.removeItem(this.config.storageKey);
                        this.errorCount = 0;
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
                    if (!this.isZipPicksAjaxCall(ajaxSettings)) {
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
        
        // Check if AJAX call is related to ZipPicks plugin
        isZipPicksAjaxCall: function(ajaxSettings) {
            // Define ZipPicks-specific patterns
            const zipPicksPatterns = {
                // URL patterns (case-insensitive)
                urlPatterns: [
                    /\/wp-admin\/admin-ajax\.php$/i,
                    /\/wp-json\/zippicks\/v\d+\//i,
                    /zippicks.*\.php$/i
                ],
                
                // Action patterns for WordPress AJAX
                actionPatterns: [
                    /^zippicks_/i,
                    /^wp_ajax_zippicks_/i
                ],
                
                // Data key patterns
                dataKeyPatterns: [
                    /zippicks/i
                ]
            };
            
            // Check URL patterns
            if (ajaxSettings.url) {
                // Parse URL to get pathname
                let urlPath;
                try {
                    const url = new URL(ajaxSettings.url, window.location.origin);
                    urlPath = url.pathname;
                } catch (e) {
                    // Fallback for relative URLs
                    urlPath = ajaxSettings.url;
                }
                
                // Check if URL matches ZipPicks patterns
                for (const pattern of zipPicksPatterns.urlPatterns) {
                    if (pattern.test(urlPath)) {
                        // Additional verification for admin-ajax.php
                        if (urlPath.includes('admin-ajax.php')) {
                            return this.checkAjaxData(ajaxSettings, zipPicksPatterns);
                        }
                        return true;
                    }
                }
            }
            
            // Check data patterns even if URL doesn't match (for some AJAX calls)
            return this.checkAjaxData(ajaxSettings, zipPicksPatterns);
        },
        
        // Check AJAX data for ZipPicks-specific patterns
        checkAjaxData: function(ajaxSettings, patterns) {
            if (!ajaxSettings.data) {
                return false;
            }
            
            let dataToCheck = '';
            
            // Handle different data formats
            if (typeof ajaxSettings.data === 'string') {
                dataToCheck = ajaxSettings.data;
            } else if (typeof ajaxSettings.data === 'object') {
                // Check for 'action' parameter specifically (WordPress AJAX)
                if (ajaxSettings.data.action) {
                    for (const pattern of patterns.actionPatterns) {
                        if (pattern.test(ajaxSettings.data.action)) {
                            return true;
                        }
                    }
                }
                
                // Convert object to string for broader checking
                try {
                    dataToCheck = JSON.stringify(ajaxSettings.data);
                } catch (e) {
                    // Fallback to string conversion
                    dataToCheck = String(ajaxSettings.data);
                }
            }
            
            // Check data content for ZipPicks patterns
            for (const pattern of patterns.dataKeyPatterns) {
                if (pattern.test(dataToCheck)) {
                    return true;
                }
            }
            
            return false;
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