/**
 * ZipPicks Smart Search - Autocomplete
 */

(function($) {
    'use strict';
    
    window.ZipPicksAutocomplete = {
        
        config: {
            minLength: 2,
            debounceDelay: 150,
            maxSuggestions: 10,
            rateLimitDelay: 100,        // Minimum delay between requests (ms)
            maxRequestsPerMinute: 30    // Maximum requests per minute
        },
        
        state: {
            currentInput: null,
            currentRequest: null,
            isOpen: false,
            selectedIndex: -1,
            timeout: null,
            blurTimeout: null,
            lastRequestTime: 0,
            requestCount: 0,
            requestCountResetTime: 0
        },
        
        init: function() {
            this.bindEvents();
            this.createContainer();
        },
        
        bindEvents: function() {
            // Input events
            $(document).on('input focus', '.zippicks-search-input', this.handleInput.bind(this));
            $(document).on('blur', '.zippicks-search-input', this.handleBlur.bind(this));
            
            // Keyboard navigation
            $(document).on('keydown', '.zippicks-search-input', this.handleKeydown.bind(this));
            
            // Suggestion clicks
            $(document).on('mousedown', '.zippicks-suggestion', this.handleSuggestionClick.bind(this));
            
            // Close on outside click
            $(document).on('click', this.handleOutsideClick.bind(this));
        },
        
        createContainer: function() {
            if (!$('#zippicks-autocomplete').length) {
                $('body').append('<div id="zippicks-autocomplete" class="zippicks-autocomplete"></div>');
            }
        },
        
        handleInput: function(e) {
            const $input = $(e.target);
            const value = $input.val().trim();
            
            this.state.currentInput = $input;
            
            // Clear existing timeout
            if (this.state.timeout) {
                clearTimeout(this.state.timeout);
            }
            
            if (value.length < this.config.minLength) {
                this.close();
                return;
            }
            
            // Debounce request
            this.state.timeout = setTimeout(() => {
                this.getSuggestions(value);
            }, this.config.debounceDelay);
        },
        
        handleBlur: function() {
            // Clear any existing blur timeout to prevent race conditions
            if (this.state.blurTimeout) {
                clearTimeout(this.state.blurTimeout);
                this.state.blurTimeout = null;
            }
            
            // Delay close to allow click on suggestions
            this.state.blurTimeout = setTimeout(() => {
                this.close();
                this.state.blurTimeout = null;
            }, 200);
        },
        
        handleKeydown: function(e) {
            if (!this.state.isOpen) {
                return;
            }
            
            switch(e.which) {
                case 38: // Up arrow
                    e.preventDefault();
                    this.navigateUp();
                    break;
                    
                case 40: // Down arrow
                    e.preventDefault();
                    this.navigateDown();
                    break;
                    
                case 13: // Enter
                    e.preventDefault();
                    this.selectCurrent();
                    break;
                    
                case 27: // Escape
                    e.preventDefault();
                    this.close();
                    break;
            }
        },
        
        handleSuggestionClick: function(e) {
            e.preventDefault();
            const $suggestion = $(e.currentTarget);
            this.selectSuggestion($suggestion);
        },
        
        handleOutsideClick: function(e) {
            if (!$(e.target).closest('.zippicks-search-input, #zippicks-autocomplete').length) {
                this.close();
            }
        },
        
        getSuggestions: function(query) {
            // Check rate limiting before making request
            if (!this.checkRateLimit()) {
                return;
            }
            
            // Cancel previous request
            if (this.state.currentRequest) {
                this.state.currentRequest.abort();
            }
            
            const params = {
                action: 'zippicks_autocomplete',
                q: query,
                nonce: zippicks_search.nonce
            };
            
            // Add location if available
            if (window.ZipPicksSearch && window.ZipPicksSearch.state.currentLocation) {
                params.lat = window.ZipPicksSearch.state.currentLocation.lat;
                params.lng = window.ZipPicksSearch.state.currentLocation.lng;
            }
            
            // Update rate limiting tracking
            this.updateRateLimitTracking();
            
            this.state.currentRequest = $.get(zippicks_search.ajax_url, params)
                .done(response => {
                    if (response.success && response.data.suggestions) {
                        this.showSuggestions(response.data.suggestions);
                    } else {
                        this.close();
                    }
                })
                .fail(() => {
                    this.close();
                })
                .always(() => {
                    this.state.currentRequest = null;
                });
        },
        
        checkRateLimit: function() {
            const now = Date.now();
            
            // Check minimum delay between requests
            if (now - this.state.lastRequestTime < this.config.rateLimitDelay) {
                return false;
            }
            
            // Reset request count if minute has passed
            if (now - this.state.requestCountResetTime >= 60000) {
                this.state.requestCount = 0;
                this.state.requestCountResetTime = now;
            }
            
            // Check maximum requests per minute
            if (this.state.requestCount >= this.config.maxRequestsPerMinute) {
                return false;
            }
            
            return true;
        },
        
        updateRateLimitTracking: function() {
            const now = Date.now();
            this.state.lastRequestTime = now;
            this.state.requestCount++;
            
            // Initialize reset time if not set
            if (this.state.requestCountResetTime === 0) {
                this.state.requestCountResetTime = now;
            }
        },
        
        showSuggestions: function(suggestions) {
            if (!suggestions || suggestions.length === 0) {
                this.close();
                return;
            }
            
            const $container = $('#zippicks-autocomplete');
            const $input = this.state.currentInput;
            
            // Build suggestions HTML
            let html = '<ul class="zippicks-suggestions">';
            
            suggestions.forEach((suggestion, index) => {
                const icon = this.getIcon(suggestion.type);
                const meta = suggestion.meta ? `<span class="suggestion-meta">${suggestion.meta}</span>` : '';
                
                html += `
                    <li class="zippicks-suggestion suggestion-${suggestion.type}" 
                        data-index="${index}"
                        data-value="${this.escapeHtml(suggestion.value)}"
                        data-type="${suggestion.type}"
                        data-zpid="${suggestion.zpid || ''}">
                        <span class="suggestion-icon">${icon}</span>
                        <span class="suggestion-label">${this.escapeHtml(suggestion.label)}</span>
                        ${meta}
                    </li>
                `;
            });
            
            html += '</ul>';
            $container.html(html);
            
            // Position container
            this.positionContainer($input, $container);
            
            // Show container
            $container.addClass('active');
            this.state.isOpen = true;
            this.state.selectedIndex = -1;
        },
        
        positionContainer: function($input, $container) {
            const offset = $input.offset();
            const inputHeight = $input.outerHeight();
            const inputWidth = $input.outerWidth();
            
            $container.css({
                top: offset.top + inputHeight,
                left: offset.left,
                width: inputWidth
            });
        },
        
        navigateUp: function() {
            const $suggestions = $('.zippicks-suggestion');
            const count = $suggestions.length;
            
            if (count === 0) return;
            
            this.state.selectedIndex--;
            if (this.state.selectedIndex < 0) {
                this.state.selectedIndex = count - 1;
            }
            
            this.highlightSuggestion();
        },
        
        navigateDown: function() {
            const $suggestions = $('.zippicks-suggestion');
            const count = $suggestions.length;
            
            if (count === 0) return;
            
            this.state.selectedIndex++;
            if (this.state.selectedIndex >= count) {
                this.state.selectedIndex = 0;
            }
            
            this.highlightSuggestion();
        },
        
        highlightSuggestion: function() {
            $('.zippicks-suggestion').removeClass('selected');
            
            if (this.state.selectedIndex >= 0) {
                $('.zippicks-suggestion').eq(this.state.selectedIndex).addClass('selected');
            }
        },
        
        selectCurrent: function() {
            if (this.state.selectedIndex >= 0) {
                const $suggestion = $('.zippicks-suggestion').eq(this.state.selectedIndex);
                this.selectSuggestion($suggestion);
            } else {
                // Submit search with current input value
                this.state.currentInput.closest('form').submit();
            }
        },
        
        selectSuggestion: function($suggestion) {
            const value = $suggestion.data('value');
            const type = $suggestion.data('type');
            const zpid = $suggestion.data('zpid');
            
            // Update input value
            this.state.currentInput.val(value);
            
            // Close autocomplete
            this.close();
            
            // Handle different suggestion types
            if (type === 'business' && zpid) {
                // Direct navigation to business page
                const businessUrl = this.getBusinessUrl(zpid);
                if (businessUrl) {
                    window.location.href = businessUrl;
                    return;
                }
            }
            
            // Trigger search
            this.state.currentInput.closest('form').submit();
        },
        
        close: function() {
            // Clear blur timeout to prevent memory leaks
            if (this.state.blurTimeout) {
                clearTimeout(this.state.blurTimeout);
                this.state.blurTimeout = null;
            }
            
            $('#zippicks-autocomplete').removeClass('active').empty();
            this.state.isOpen = false;
            this.state.selectedIndex = -1;
        },
        
        getIcon: function(type) {
            const icons = {
                'search': '🔍',
                'business': '📍',
                'vibe': '✨',
                'category': '📁',
                'query': '🔍'
            };
            
            return icons[type] || '🔍';
        },
        
        getBusinessUrl: function(zpid) {
            // Try to get URL from existing data
            const $existingResult = $(`.zippicks-search-result[data-zpid="${zpid}"] a`);
            if ($existingResult.length) {
                return $existingResult.attr('href');
            }
            
            // Build URL based on configurable pattern
            return this.buildBusinessUrl(zpid);
        },
        
        buildBusinessUrl: function(zpid) {
            // Get URL pattern from WordPress configuration
            let urlPattern = '';
            
            // Try to get from global zippicks_search object
            if (window.zippicks_search && window.zippicks_search.business_url_pattern) {
                urlPattern = window.zippicks_search.business_url_pattern;
            }
            // Fallback to checking for a dedicated business URL config
            else if (window.zippicks_autocomplete_config && window.zippicks_autocomplete_config.business_url_pattern) {
                urlPattern = window.zippicks_autocomplete_config.business_url_pattern;
            }
            // Final fallback to default pattern
            else {
                urlPattern = '/business/{zpid}/';
            }
            
            // Replace placeholder with actual zpid
            return urlPattern.replace('{zpid}', zpid);
        },
        
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    };
    
})(jQuery);