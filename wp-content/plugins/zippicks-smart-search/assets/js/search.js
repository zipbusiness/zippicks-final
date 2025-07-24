/**
 * ZipPicks Smart Search - Main JavaScript
 */

(function($) {
    'use strict';
    
    // Main search module
    window.ZipPicksSearch = {
        
        // Configuration
        config: {
            debounceDelay: 300,
            minSearchLength: 2,
            maxResults: 20,
            useREST: true // Use REST API instead of AJAX
        },
        
        // Current state
        state: {
            currentQuery: '',
            currentLocation: null,
            isSearching: false,
            searchTimeout: null
        },
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.getCurrentLocation();
            
            // Initialize autocomplete if enabled
            if (typeof window.ZipPicksAutocomplete !== 'undefined') {
                window.ZipPicksAutocomplete.init();
            }
        },
        
        // Bind events
        bindEvents: function() {
            // Search form submission
            $(document).on('submit', '.zippicks-search-form', this.handleSearchSubmit.bind(this));
            
            // Search input changes
            $(document).on('input', '.zippicks-search-input', this.handleSearchInput.bind(this));
            
            // Result clicks
            $(document).on('click', '.zippicks-search-result', this.handleResultClick.bind(this));
            
            // Coming soon notifications
            $(document).on('click', '.zippicks-notify-btn', this.handleNotifyClick.bind(this));
            
            // Location update
            $(document).on('click', '.zippicks-update-location', this.updateLocation.bind(this));
        },
        
        // Handle search form submission
        handleSearchSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const query = $form.find('.zippicks-search-input').val().trim();
            
            if (query.length >= this.config.minSearchLength) {
                this.performSearch(query);
            }
        },
        
        // Handle search input changes
        handleSearchInput: function(e) {
            const query = $(e.target).val().trim();
            
            // Clear existing timeout
            if (this.state.searchTimeout) {
                clearTimeout(this.state.searchTimeout);
            }
            
            // Debounce search
            if (query.length >= this.config.minSearchLength) {
                this.state.searchTimeout = setTimeout(() => {
                    this.performSearch(query);
                }, this.config.debounceDelay);
            } else {
                this.clearResults();
            }
        },
        
        // Perform search
        performSearch: function(query) {
            if (this.state.isSearching) {
                return;
            }
            
            this.state.currentQuery = query;
            this.state.isSearching = true;
            
            // Show loading state
            this.showLoading();
            
            // Prepare search parameters
            const params = {
                q: query,
                limit: this.config.maxResults
            };
            
            // Add location if available
            if (this.state.currentLocation) {
                params.lat = this.state.currentLocation.lat;
                params.lng = this.state.currentLocation.lng;
            }
            
            // Choose API method
            if (this.config.useREST) {
                this.searchViaREST(params);
            } else {
                this.searchViaAJAX(params);
            }
        },
        
        // Search via REST API
        searchViaREST: function(params) {
            const url = new URL(zippicks_search.rest_url + 'search');
            Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
            
            fetch(url, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': zippicks_search.nonce,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    this.displayResults(data.data);
                } else {
                    this.showError(data.message || zippicks_search.strings.error);
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                this.showError(zippicks_search.strings.error);
            })
            .finally(() => {
                this.state.isSearching = false;
            });
        },
        
        // Search via AJAX (fallback)
        searchViaAJAX: function(params) {
            params.action = 'zippicks_search';
            params.nonce = zippicks_search.nonce;
            
            $.post(zippicks_search.ajax_url, params)
                .done(response => {
                    if (response.success && response.data.results) {
                        this.displayResults(response.data.results);
                    } else {
                        this.showError(response.data.message || zippicks_search.strings.error);
                    }
                })
                .fail(() => {
                    this.showError(zippicks_search.strings.error);
                })
                .always(() => {
                    this.state.isSearching = false;
                });
        },
        
        // Display search results
        displayResults: function(data) {
            const $container = $('.zippicks-search-results');
            
            if (!data.results || data.results.length === 0) {
                this.showNoResults();
                return;
            }
            
            // Clear existing results
            $container.empty();
            
            // Add intent indicator
            if (data.intent) {
                $container.append(this.renderIntentIndicator(data.intent, data.query_analysis));
            }
            
            // Add results
            data.results.forEach((result, index) => {
                $container.append(this.renderResult(result, index + 1));
            });
            
            // Show container
            $container.show();
        },
        
        // Render intent indicator
        renderIntentIndicator: function(intent, analysis) {
            let message = '';
            
            switch(intent) {
                case 'vibe':
                    message = 'Showing places that match your vibe';
                    break;
                case 'utility':
                    message = 'Showing specific businesses';
                    break;
                case 'hybrid':
                    message = 'Showing a mix of vibes and businesses';
                    break;
            }
            
            // Use ZipPicksSecurity helper to create DOM elements safely
            if (window.ZipPicksSecurity) {
                const iconSpan = ZipPicksSecurity.createElement('span', {
                    'class': 'intent-icon'
                });
                
                const messageSpan = ZipPicksSecurity.createElement('span', {
                    'class': 'intent-message'
                }, message);
                
                const container = ZipPicksSecurity.createElement('div', {
                    'class': `zippicks-intent-indicator intent-${ZipPicksSecurity.escapeAttr(intent)}`
                });
                
                container.appendChild(iconSpan);
                container.appendChild(messageSpan);
                
                return container.outerHTML;
            }
            
            // Fallback with manual sanitization if ZipPicksSecurity is not available
            const sanitizedIntent = this.sanitizeForClass(intent);
            const sanitizedMessage = this.escapeHtml(message);
            
            return `
                <div class="zippicks-intent-indicator intent-${sanitizedIntent}">
                    <span class="intent-icon"></span>
                    <span class="intent-message">${sanitizedMessage}</span>
                </div>
            `;
        },
        
        // Sanitize string for use in CSS class names
        sanitizeForClass: function(str) {
            if (typeof str !== 'string') {
                return '';
            }
            // Only allow alphanumeric, hyphens, and underscores
            return str.replace(/[^a-zA-Z0-9\-_]/g, '');
        },
        
        // Escape HTML entities
        escapeHtml: function(str) {
            if (typeof str !== 'string') {
                return '';
            }
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };
            return str.replace(/[&<>"']/g, function(char) {
                return map[char];
            });
        },
        
        // Render single result
        renderResult: function(result, position) {
            const isComingSoon = !result.exists;
            
            // Use ZipPicksSecurity helper if available, otherwise use fallback
            const security = window.ZipPicksSecurity || this;
            
            // Create main result container
            const resultContainer = this.createSafeElement('div', {
                'class': `zippicks-search-result ${isComingSoon ? 'coming-soon' : ''}`,
                'data-zpid': result.zpid,
                'data-position': position
            });
            
            // Create result content container
            const contentContainer = this.createSafeElement('div', {
                'class': 'result-content'
            });
            
            // Create title element
            const titleElement = this.createSafeElement('h3', {
                'class': 'result-title'
            });
            
            if (isComingSoon) {
                // Just text for coming soon items
                titleElement.textContent = result.name || '';
            } else {
                // Create link for existing businesses
                const linkElement = this.createSafeElement('a', {
                    'href': this.sanitizeUrl(result.url)
                });
                linkElement.textContent = result.name || '';
                titleElement.appendChild(linkElement);
            }
            contentContainer.appendChild(titleElement);
            
            // Create meta information
            if (result.category || result.distance) {
                const metaContainer = this.createSafeElement('div', {
                    'class': 'result-meta'
                });
                
                if (result.category) {
                    const categorySpan = this.createSafeElement('span', {
                        'class': 'result-category'
                    });
                    categorySpan.textContent = result.category;
                    metaContainer.appendChild(categorySpan);
                }
                
                if (result.distance) {
                    const distanceSpan = this.createSafeElement('span', {
                        'class': 'result-distance'
                    });
                    distanceSpan.textContent = `${result.distance} mi`;
                    metaContainer.appendChild(distanceSpan);
                }
                
                contentContainer.appendChild(metaContainer);
            }
            
            // Add description if available
            if (result.description) {
                const descriptionP = this.createSafeElement('p', {
                    'class': 'result-description'
                });
                descriptionP.textContent = result.description;
                contentContainer.appendChild(descriptionP);
            }
            
            // Add vibes if available
            if (result.vibes && Array.isArray(result.vibes) && result.vibes.length > 0) {
                const vibesContainer = this.createSafeElement('div', {
                    'class': 'result-vibes'
                });
                
                result.vibes.forEach(vibe => {
                    if (vibe && typeof vibe === 'string') {
                        const vibeSpan = this.createSafeElement('span', {
                            'class': 'vibe-tag'
                        });
                        vibeSpan.textContent = vibe;
                        vibesContainer.appendChild(vibeSpan);
                    }
                });
                
                contentContainer.appendChild(vibesContainer);
            }
            
            resultContainer.appendChild(contentContainer);
            
            // Add notify button for coming soon items
            if (isComingSoon) {
                const actionContainer = this.createSafeElement('div', {
                    'class': 'result-action'
                });
                
                const notifyButton = this.createSafeElement('button', {
                    'class': 'zippicks-notify-btn button',
                    'data-zpid': result.zpid,
                    'data-name': result.name
                });
                notifyButton.textContent = zippicks_search.strings.notify_me || 'Notify Me';
                
                actionContainer.appendChild(notifyButton);
                resultContainer.appendChild(actionContainer);
            }
            
            return resultContainer.outerHTML;
        },
        
        // Create safe DOM element with sanitized attributes
        createSafeElement: function(tag, attrs = {}) {
            // Use ZipPicksSecurity if available
            if (window.ZipPicksSecurity && window.ZipPicksSecurity.createElement) {
                return window.ZipPicksSecurity.createElement(tag, attrs);
            }
            
            // Fallback: manual safe element creation
            const element = document.createElement(tag);
            
            for (const [key, value] of Object.entries(attrs)) {
                if (typeof value === 'string' || typeof value === 'number') {
                    if (key === 'href') {
                        element.setAttribute(key, this.sanitizeUrl(String(value)));
                    } else if (key === 'class' || key === 'id' || key.startsWith('data-')) {
                        element.setAttribute(key, this.sanitizeAttr(String(value)));
                    }
                }
            }
            
            return element;
        },
        
        // Sanitize URL (enhanced version)
        sanitizeUrl: function(url) {
            if (!url || typeof url !== 'string') {
                return '#';
            }
            
            // Use ZipPicksSecurity if available
            if (window.ZipPicksSecurity && window.ZipPicksSecurity.sanitizeUrl) {
                return window.ZipPicksSecurity.sanitizeUrl(url);
            }
            
            // Fallback: basic URL sanitization
            if (url.match(/^(javascript|data|vbscript):/i)) {
                return '#';
            }
            
            if (!url.match(/^(https?:\/\/|\/|#)/i)) {
                return '#';
            }
            
            return url;
        },
        
        // Sanitize attribute value
        sanitizeAttr: function(attr) {
            if (typeof attr !== 'string') {
                return '';
            }
            
            // Use ZipPicksSecurity if available
            if (window.ZipPicksSecurity && window.ZipPicksSecurity.escapeAttr) {
                return window.ZipPicksSecurity.escapeAttr(attr);
            }
            
            // Fallback: escape dangerous characters
            return attr.replace(/["'<>&]/g, function(char) {
                return '&#' + char.charCodeAt(0) + ';';
            });
        },
        
        // Handle result click
        handleResultClick: function(e) {
            const $result = $(e.currentTarget);
            const zpid = $result.data('zpid');
            const position = $result.data('position');
            
            // Track click
            this.trackClick(zpid, position);
        },
        
        // Handle notify click
        handleNotifyClick: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(e.currentTarget);
            const zpid = $btn.data('zpid');
            const name = $btn.data('name');
            
            // Show inline email form
            this.showEmailForm($btn, zpid, name);
        },
        
        // Submit notification request
        submitNotification: function(zpid, email, $btn, $formContainer) {
            const data = {
                action: 'zippicks_notify_coming_soon',
                nonce: zippicks_search.nonce,
                zpid: zpid,
                email: email
            };
            
            $.post(zippicks_search.ajax_url, data)
                .done(response => {
                    if (response.success) {
                        // Hide form and show success on button
                        if ($formContainer) {
                            this.hideEmailForm();
                        }
                        $btn.text('Notification Set!').addClass('success').prop('disabled', true);
                    } else {
                        // Show error in form if available, otherwise alert
                        const errorMsg = response.data.message || 'Failed to set notification';
                        if ($formContainer) {
                            const $errorDiv = $formContainer.find('.email-error-message');
                            const $submitBtn = $formContainer.find('.email-submit-btn');
                            $errorDiv.text(errorMsg).show();
                            $submitBtn.prop('disabled', false).text('Notify Me');
                        } else {
                            alert(errorMsg);
                            $btn.prop('disabled', false).text(zippicks_search.strings.notify_me);
                        }
                    }
                })
                .fail(() => {
                    // Show error in form if available, otherwise alert
                    const errorMsg = 'Failed to set notification. Please try again.';
                    if ($formContainer) {
                        const $errorDiv = $formContainer.find('.email-error-message');
                        const $submitBtn = $formContainer.find('.email-submit-btn');
                        $errorDiv.text(errorMsg).show();
                        $submitBtn.prop('disabled', false).text('Notify Me');
                    } else {
                        alert(errorMsg);
                        $btn.prop('disabled', false).text(zippicks_search.strings.notify_me);
                    }
                });
        },
        
        // Track click
        trackClick: function(zpid, position) {
            const data = {
                action: 'zippicks_track_click',
                nonce: zippicks_search.nonce,
                zpid: zpid,
                query: this.state.currentQuery,
                position: position
            };
            
            // Fire and forget
            $.post(zippicks_search.ajax_url, data);
        },
        
        // Get current location
        getCurrentLocation: function() {
            // Check if we have a default location
            if (zippicks_search.default_location) {
                this.state.currentLocation = zippicks_search.default_location;
            }
            
            // Try to get user's actual location
            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(
                    position => {
                        this.state.currentLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                            source: 'browser'
                        };
                        this.updateLocationDisplay();
                    },
                    error => {
                        console.log('Geolocation error:', error);
                        // Keep default location
                    }
                );
            }
        },
        
        // Update location display
        updateLocationDisplay: function() {
            const $display = $('.zippicks-location-display');
            if ($display.length && this.state.currentLocation) {
                let text = 'Current location';
                if (this.state.currentLocation.city) {
                    text = this.state.currentLocation.city;
                    if (this.state.currentLocation.state) {
                        text += ', ' + this.state.currentLocation.state;
                    }
                }
                $display.text(text);
            }
        },
        
        // Update location
        updateLocation: function(e) {
            e.preventDefault();
            this.getCurrentLocation();
        },
        
        // Show loading state
        showLoading: function() {
            const $container = $('.zippicks-search-results');
            
            // Create loading container safely
            const $loadingDiv = $('<div>', { class: 'zippicks-loading' });
            const $spinner = $('<span>', { class: 'spinner' });
            const $message = $('<span>').text(zippicks_search.strings.searching || 'Searching...');
            
            $loadingDiv.append($spinner, $message);
            $container.empty().append($loadingDiv).show();
        },
        
        // Show no results
        showNoResults: function() {
            const $container = $('.zippicks-search-results');
            
            // Create no results container safely
            const $noResultsDiv = $('<div>', { class: 'zippicks-no-results' });
            const $message = $('<p>').text(zippicks_search.strings.no_results || 'No results found');
            
            $noResultsDiv.append($message);
            $container.empty().append($noResultsDiv).show();
        },
        
        // Show error
        showError: function(message) {
            const $container = $('.zippicks-search-results');
            
            // Create error container safely
            const $errorDiv = $('<div>', { class: 'zippicks-search-error' });
            const $errorMessage = $('<p>').text(message || 'An error occurred');
            
            $errorDiv.append($errorMessage);
            $container.empty().append($errorDiv).show();
        },
        
        // Clear results
        clearResults: function() {
            $('.zippicks-search-results').empty().hide();
        },
        
        // Show inline email form
        showEmailForm: function($btn, zpid, name) {
            // Remove any existing email forms
            this.hideEmailForm();
            
            // Disable the notify button temporarily
            $btn.prop('disabled', true);
            
            // Create form container
            const $formContainer = $('<div>', {
                class: 'zippicks-email-form-container',
                'data-zpid': zpid
            });
            
            // Create form using safe DOM construction
            const $form = $('<div>', { class: 'zippicks-email-form' });
            
            // Header
            const $header = $('<div>', { class: 'email-form-header' });
            const $title = $('<span>', { class: 'email-form-title' })
                .text(`Get notified when ${name} is added`);
            $header.append($title);
            
            // Body
            const $body = $('<div>', { class: 'email-form-body' });
            const $input = $('<input>', {
                type: 'email',
                class: 'email-input',
                placeholder: 'Enter your email address',
                maxlength: '254',
                required: true
            });
            const $errorMsg = $('<div>', { 
                class: 'email-error-message',
                style: 'display: none;'
            });
            $body.append($input, $errorMsg);
            
            // Actions
            const $actions = $('<div>', { class: 'email-form-actions' });
            const $submitBtn = $('<button>', {
                type: 'button',
                class: 'email-submit-btn'
            }).text('Notify Me');
            const $cancelBtn = $('<button>', {
                type: 'button',
                class: 'email-cancel-btn'
            }).text('Cancel');
            $actions.append($submitBtn, $cancelBtn);
            
            // Assemble form
            $form.append($header, $body, $actions);
            $formContainer.append($form);
            
            // Insert form after the notify button
            $btn.after($formContainer);
            
            // Add event handlers
            this.attachEmailFormHandlers($formContainer, $btn, zpid);
            
            // Focus on email input
            $formContainer.find('.email-input').focus();
        },
        
        // Attach email form event handlers
        attachEmailFormHandlers: function($formContainer, $btn, zpid) {
            const self = this;
            const $emailInput = $formContainer.find('.email-input');
            const $submitBtn = $formContainer.find('.email-submit-btn');
            const $cancelBtn = $formContainer.find('.email-cancel-btn');
            const $errorMsg = $formContainer.find('.email-error-message');
            
            // Submit button handler
            $submitBtn.on('click', function(e) {
                e.preventDefault();
                self.handleEmailSubmit($formContainer, $btn, zpid);
            });
            
            // Cancel button handler
            $cancelBtn.on('click', function(e) {
                e.preventDefault();
                self.hideEmailForm();
                $btn.prop('disabled', false);
            });
            
            // Enter key handler on input
            $emailInput.on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    self.handleEmailSubmit($formContainer, $btn, zpid);
                }
            });
            
            // Escape key handler
            $formContainer.on('keydown', function(e) {
                if (e.which === 27) { // Escape key
                    e.preventDefault();
                    self.hideEmailForm();
                    $btn.prop('disabled', false);
                }
            });
            
            // Real-time validation
            $emailInput.on('input', function() {
                $errorMsg.hide();
                $submitBtn.prop('disabled', false);
            });
        },
        
        // Handle email form submission
        handleEmailSubmit: function($formContainer, $btn, zpid) {
            const $emailInput = $formContainer.find('.email-input');
            const $submitBtn = $formContainer.find('.email-submit-btn');
            const $errorMsg = $formContainer.find('.email-error-message');
            const email = $emailInput.val().trim();
            
            // Validate email using security helper
            const security = window.ZipPicksSecurity || this;
            const validation = security.validateEmail(email);
            
            if (!validation.valid) {
                $errorMsg.text(validation.error).show();
                $emailInput.focus();
                return;
            }
            
            // Disable submit button and show loading
            $submitBtn.prop('disabled', true).text('Submitting...');
            
            // Submit the notification
            this.submitNotification(zpid, validation.email, $btn, $formContainer);
        },
        
        // Hide email form
        hideEmailForm: function() {
            $('.zippicks-email-form-container').remove();
        },
        
        // Validate email (updated to use security helper)
        validateEmail: function(email) {
            const security = window.ZipPicksSecurity || this;
            if (security.validateEmail) {
                const validation = security.validateEmail(email);
                return validation.valid;
            }
            
            // Fallback to basic validation
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        window.ZipPicksSearch.init();
    });
    
})(jQuery);