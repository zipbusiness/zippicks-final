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
            
            return `
                <div class="zippicks-intent-indicator intent-${intent}">
                    <span class="intent-icon"></span>
                    <span class="intent-message">${message}</span>
                </div>
            `;
        },
        
        // Render single result
        renderResult: function(result, position) {
            const isComingSoon = !result.exists;
            const resultClass = isComingSoon ? 'coming-soon' : '';
            
            return `
                <div class="zippicks-search-result ${resultClass}" 
                     data-zpid="${result.zpid}" 
                     data-position="${position}">
                    <div class="result-content">
                        <h3 class="result-title">
                            ${isComingSoon ? 
                                result.name : 
                                `<a href="${result.url}">${result.name}</a>`
                            }
                        </h3>
                        <div class="result-meta">
                            ${result.category ? `<span class="result-category">${result.category}</span>` : ''}
                            ${result.distance ? `<span class="result-distance">${result.distance} mi</span>` : ''}
                        </div>
                        ${result.description ? `<p class="result-description">${result.description}</p>` : ''}
                        ${result.vibes && result.vibes.length ? 
                            `<div class="result-vibes">
                                ${result.vibes.map(vibe => `<span class="vibe-tag">${vibe}</span>`).join('')}
                            </div>` : ''
                        }
                    </div>
                    ${isComingSoon ? 
                        `<div class="result-action">
                            <button class="zippicks-notify-btn button" data-zpid="${result.zpid}" data-name="${result.name}">
                                ${zippicks_search.strings.notify_me}
                            </button>
                        </div>` : ''
                    }
                </div>
            `;
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
            
            // Show email input
            const email = prompt(`Enter your email to be notified when ${name} is added:`, '');
            
            if (email && this.validateEmail(email)) {
                this.submitNotification(zpid, email, $btn);
            }
        },
        
        // Submit notification request
        submitNotification: function(zpid, email, $btn) {
            $btn.prop('disabled', true).text('Submitting...');
            
            const data = {
                action: 'zippicks_notify_coming_soon',
                nonce: zippicks_search.nonce,
                zpid: zpid,
                email: email
            };
            
            $.post(zippicks_search.ajax_url, data)
                .done(response => {
                    if (response.success) {
                        $btn.text('Notification Set!').addClass('success');
                    } else {
                        alert(response.data.message || 'Failed to set notification');
                        $btn.prop('disabled', false).text(zippicks_search.strings.notify_me);
                    }
                })
                .fail(() => {
                    alert('Failed to set notification. Please try again.');
                    $btn.prop('disabled', false).text(zippicks_search.strings.notify_me);
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
            $container.html(`
                <div class="zippicks-loading">
                    <span class="spinner"></span>
                    <span>${zippicks_search.strings.searching}</span>
                </div>
            `).show();
        },
        
        // Show no results
        showNoResults: function() {
            const $container = $('.zippicks-search-results');
            $container.html(`
                <div class="zippicks-no-results">
                    <p>${zippicks_search.strings.no_results}</p>
                </div>
            `).show();
        },
        
        // Show error
        showError: function(message) {
            const $container = $('.zippicks-search-results');
            $container.html(`
                <div class="zippicks-search-error">
                    <p>${message}</p>
                </div>
            `).show();
        },
        
        // Clear results
        clearResults: function() {
            $('.zippicks-search-results').empty().hide();
        },
        
        // Validate email
        validateEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        window.ZipPicksSearch.init();
    });
    
})(jQuery);