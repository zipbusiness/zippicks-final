/**
 * ZipPicks Smart Search - Autocomplete
 */

(function($) {
    'use strict';
    
    window.ZipPicksAutocomplete = {
        
        config: {
            minLength: 2,
            debounceDelay: 150,
            maxSuggestions: 10
        },
        
        state: {
            currentInput: null,
            currentRequest: null,
            isOpen: false,
            selectedIndex: -1,
            timeout: null
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
            // Delay close to allow click on suggestions
            setTimeout(() => {
                this.close();
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
            // Cancel previous request
            if (this.state.currentRequest) {
                this.state.currentRequest.abort();
            }
            
            const params = {
                action: 'zippicks_autocomplete',
                q: query
            };
            
            // Add location if available
            if (window.ZipPicksSearch && window.ZipPicksSearch.state.currentLocation) {
                params.lat = window.ZipPicksSearch.state.currentLocation.lat;
                params.lng = window.ZipPicksSearch.state.currentLocation.lng;
            }
            
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
            
            // Build URL based on pattern
            // This assumes business posts use zpid as slug
            return `/business/${zpid}/`;
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