/**
 * ZipPicks WCAG-Compliant Autocomplete Component
 * Implements ARIA patterns and keyboard navigation
 * 
 * @version 1.0.0
 * @author ZipPicks Engineering Team
 */

class ZipPicksAutocomplete {
    constructor(inputElement, options = {}) {
        this.input = inputElement;
        this.options = {
            minQueryLength: 2,
            maxSuggestions: 8,
            debounceDelay: 300,
            announceResultCount: true,
            trackFailedSearches: true,
            ...options
        };
        
        this.selectedIndex = -1;
        this.suggestions = [];
        this.isOpen = false;
        this.searchTimeout = null;
        this.lastQuery = '';
        
        this.init();
    }
    
    init() {
        this.createElements();
        this.setupARIA();
        this.bindEvents();
        this.setupKeyboardNavigation();
        this.setupScreenReaderSupport();
    }
    
    createElements() {
        // Create listbox container
        this.listbox = document.createElement('ul');
        this.listbox.className = 'zippicks-autocomplete-listbox';
        this.listbox.setAttribute('role', 'listbox');
        this.listbox.setAttribute('id', `${this.input.id}-listbox`);
        this.listbox.style.display = 'none';
        
        // Create status region for screen readers
        this.statusRegion = document.createElement('div');
        this.statusRegion.className = 'zippicks-autocomplete-status sr-only';
        this.statusRegion.setAttribute('role', 'status');
        this.statusRegion.setAttribute('aria-live', 'polite');
        this.statusRegion.setAttribute('aria-atomic', 'true');
        this.statusRegion.setAttribute('id', `${this.input.id}-status`);
        
        // Create instructions for screen readers
        this.instructions = document.createElement('div');
        this.instructions.className = 'zippicks-autocomplete-instructions sr-only';
        this.instructions.id = `${this.input.id}-instructions`;
        this.instructions.textContent = 'When autocomplete results are available, use up and down arrows to review and enter to select. Touch device users, explore by touch or with swipe gestures.';
        
        // Insert elements after input
        this.input.parentNode.insertBefore(this.listbox, this.input.nextSibling);
        this.input.parentNode.insertBefore(this.statusRegion, this.input.nextSibling);
        this.input.parentNode.insertBefore(this.instructions, this.input.nextSibling);
    }
    
    setupARIA() {
        // Input ARIA attributes
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-owns', this.listbox.id);
        this.input.setAttribute('aria-describedby', `${this.instructions.id} ${this.statusRegion.id}`);
        this.input.setAttribute('aria-haspopup', 'listbox');
        this.input.setAttribute('autocomplete', 'off');
        this.input.setAttribute('spellcheck', 'false');
    }
    
    bindEvents() {
        // Input events
        this.input.addEventListener('input', (e) => this.handleInput(e));
        this.input.addEventListener('focus', (e) => this.handleFocus(e));
        this.input.addEventListener('blur', (e) => this.handleBlur(e));
        
        // Listbox events
        this.listbox.addEventListener('click', (e) => this.handleListboxClick(e));
        this.listbox.addEventListener('mousedown', (e) => e.preventDefault()); // Prevent blur
        
        // Document events
        document.addEventListener('click', (e) => this.handleDocumentClick(e));
    }
    
    setupKeyboardNavigation() {
        this.input.addEventListener('keydown', (e) => {
            if (!this.isOpen) {
                // If closed, only handle special keys
                if (e.key === 'ArrowDown' && this.suggestions.length > 0) {
                    e.preventDefault();
                    this.openListbox();
                    this.selectOption(0);
                }
                return;
            }
            
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.selectNextOption();
                    break;
                    
                case 'ArrowUp':
                    e.preventDefault();
                    this.selectPreviousOption();
                    break;
                    
                case 'Enter':
                    e.preventDefault();
                    if (this.selectedIndex >= 0) {
                        this.selectSuggestion(this.suggestions[this.selectedIndex]);
                    } else {
                        this.handleNoSelection();
                    }
                    break;
                    
                case 'Escape':
                    e.preventDefault();
                    this.closeListbox();
                    break;
                    
                case 'Tab':
                    this.closeListbox();
                    break;
                    
                case 'Home':
                    if (this.suggestions.length > 0) {
                        e.preventDefault();
                        this.selectOption(0);
                    }
                    break;
                    
                case 'End':
                    if (this.suggestions.length > 0) {
                        e.preventDefault();
                        this.selectOption(this.suggestions.length - 1);
                    }
                    break;
            }
        });
    }
    
    setupScreenReaderSupport() {
        // Announce changes to suggestions
        this.announceResultCount = (count, query) => {
            if (!this.options.announceResultCount) return;
            
            let message = '';
            if (count === 0) {
                message = `No suggestions found for "${query}"`;
                this.trackFailedSearch(query, 'no_suggestions');
            } else if (count === 1) {
                message = `1 suggestion available`;
            } else {
                message = `${count} suggestions available`;
            }
            
            this.statusRegion.textContent = message;
        };
        
        // Announce selection changes
        this.announceSelection = (suggestion) => {
            if (suggestion) {
                const position = this.selectedIndex + 1;
                const total = this.suggestions.length;
                this.statusRegion.textContent = `${suggestion.label}, ${position} of ${total}`;
            }
        };
    }
    
    handleInput(e) {
        const query = e.target.value.trim();
        
        // Clear previous timeout
        clearTimeout(this.searchTimeout);
        
        if (query.length < this.options.minQueryLength) {
            this.closeListbox();
            return;
        }
        
        // Debounce search
        this.searchTimeout = setTimeout(() => {
            this.performSearch(query);
        }, this.options.debounceDelay);
    }
    
    handleFocus(e) {
        // If we have recent suggestions and query is long enough, show them
        if (this.suggestions.length > 0 && 
            this.input.value.length >= this.options.minQueryLength) {
            this.openListbox();
        }
    }
    
    handleBlur(e) {
        // Small delay to allow clicking on suggestions
        setTimeout(() => {
            if (!this.listbox.contains(document.activeElement)) {
                this.closeListbox();
            }
        }, 150);
    }
    
    handleListboxClick(e) {
        const option = e.target.closest('[role="option"]');
        if (option) {
            const index = parseInt(option.getAttribute('data-index'));
            if (index >= 0 && index < this.suggestions.length) {
                this.selectSuggestion(this.suggestions[index]);
            }
        }
    }
    
    handleDocumentClick(e) {
        if (!this.input.contains(e.target) && !this.listbox.contains(e.target)) {
            this.closeListbox();
        }
    }
    
    async performSearch(query) {
        this.lastQuery = query;
        
        try {
            const response = await fetch(zippicksSearch.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'zippicks_autocomplete',
                    q: query,
                    limit: this.options.maxSuggestions,
                    nonce: zippicksSearch.nonce
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.displaySuggestions(data.data, query);
            } else {
                this.handleSearchError(query, data.data || 'Unknown error');
            }
        } catch (error) {
            this.handleSearchError(query, error.message);
        }
    }
    
    displaySuggestions(suggestions, query) {
        this.suggestions = suggestions;
        this.selectedIndex = -1;
        
        // Clear previous suggestions
        this.listbox.innerHTML = '';
        
        if (suggestions.length === 0) {
            this.closeListbox();
            this.announceResultCount(0, query);
            return;
        }
        
        // Create suggestion elements
        suggestions.forEach((suggestion, index) => {
            const option = this.createSuggestionElement(suggestion, index);
            this.listbox.appendChild(option);
        });
        
        this.openListbox();
        this.announceResultCount(suggestions.length, query);
    }
    
    createSuggestionElement(suggestion, index) {
        const option = document.createElement('li');
        option.className = 'zippicks-autocomplete-option';
        option.setAttribute('role', 'option');
        option.setAttribute('id', `${this.input.id}-option-${index}`);
        option.setAttribute('data-index', index);
        option.setAttribute('aria-selected', 'false');
        
        // Create suggestion content
        const content = document.createElement('div');
        content.className = 'suggestion-content';
        
        // Icon
        if (suggestion.icon) {
            const icon = document.createElement('span');
            icon.className = 'suggestion-icon';
            icon.textContent = suggestion.icon;
            icon.setAttribute('aria-hidden', 'true');
            content.appendChild(icon);
        }
        
        // Main text
        const label = document.createElement('span');
        label.className = 'suggestion-label';
        label.textContent = suggestion.label;
        content.appendChild(label);
        
        // Type indicator
        if (suggestion.type) {
            const type = document.createElement('span');
            type.className = 'suggestion-type';
            type.textContent = suggestion.type;
            content.appendChild(type);
        }
        
        // Business count
        if (suggestion.business_count) {
            const count = document.createElement('span');
            count.className = 'suggestion-count';
            count.textContent = `${suggestion.business_count} places`;
            content.appendChild(count);
        }
        
        option.appendChild(content);
        
        return option;
    }
    
    openListbox() {
        if (this.isOpen || this.suggestions.length === 0) return;
        
        this.isOpen = true;
        this.listbox.style.display = 'block';
        this.input.setAttribute('aria-expanded', 'true');
        
        // Position listbox
        this.positionListbox();
    }
    
    closeListbox() {
        if (!this.isOpen) return;
        
        this.isOpen = false;
        this.selectedIndex = -1;
        this.listbox.style.display = 'none';
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
    }
    
    positionListbox() {
        const inputRect = this.input.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        
        // Position below input
        this.listbox.style.position = 'absolute';
        this.listbox.style.top = `${inputRect.bottom + window.scrollY}px`;
        this.listbox.style.left = `${inputRect.left + window.scrollX}px`;
        this.listbox.style.width = `${inputRect.width}px`;
        this.listbox.style.maxHeight = '300px';
        this.listbox.style.overflowY = 'auto';
        this.listbox.style.zIndex = '1000';
        
        // Adjust if too close to bottom
        const listboxRect = this.listbox.getBoundingClientRect();
        if (listboxRect.bottom > viewportHeight - 20) {
            this.listbox.style.top = `${inputRect.top + window.scrollY - listboxRect.height}px`;
        }
    }
    
    selectOption(index) {
        if (index < 0 || index >= this.suggestions.length) return;
        
        // Remove previous selection
        if (this.selectedIndex >= 0) {
            const prevOption = this.listbox.children[this.selectedIndex];
            prevOption.setAttribute('aria-selected', 'false');
            prevOption.classList.remove('selected');
        }
        
        // Set new selection
        this.selectedIndex = index;
        const option = this.listbox.children[index];
        option.setAttribute('aria-selected', 'true');
        option.classList.add('selected');
        
        // Update input ARIA
        this.input.setAttribute('aria-activedescendant', option.id);
        
        // Scroll into view if necessary
        option.scrollIntoView({ block: 'nearest' });
        
        // Announce selection
        this.announceSelection(this.suggestions[index]);
    }
    
    selectNextOption() {
        const nextIndex = this.selectedIndex + 1;
        if (nextIndex < this.suggestions.length) {
            this.selectOption(nextIndex);
        }
    }
    
    selectPreviousOption() {
        const prevIndex = this.selectedIndex - 1;
        if (prevIndex >= 0) {
            this.selectOption(prevIndex);
        } else {
            // Deselect all and return focus to input
            this.selectedIndex = -1;
            this.input.removeAttribute('aria-activedescendant');
            this.statusRegion.textContent = 'Use arrow keys to navigate suggestions';
        }
    }
    
    selectSuggestion(suggestion) {
        // Update input value
        this.input.value = suggestion.label;
        
        // Close listbox
        this.closeListbox();
        
        // Track successful selection
        this.trackSuccessfulSearch(this.lastQuery, suggestion);
        
        // Trigger change event
        const event = new CustomEvent('zippicks:suggestion-selected', {
            detail: { suggestion, query: this.lastQuery }
        });
        this.input.dispatchEvent(event);
        
        // Navigate to result (if URL provided)
        if (suggestion.url) {
            window.location.href = suggestion.url;
        }
    }
    
    handleNoSelection() {
        // User pressed Enter without selecting a suggestion
        const query = this.input.value.trim();
        
        if (query.length >= this.options.minQueryLength) {
            this.trackFailedSearch(query, 'no_selection_enter');
            
            // Perform general search
            const searchUrl = `${zippicksSearch.homeUrl}/search-results?q=${encodeURIComponent(query)}`;
            window.location.href = searchUrl;
        }
    }
    
    handleSearchError(query, error) {
        console.error('Autocomplete search error:', error);
        this.trackFailedSearch(query, 'search_error', error);
        this.closeListbox();
        this.statusRegion.textContent = 'Search temporarily unavailable. Please try again.';
    }
    
    trackSuccessfulSearch(query, suggestion) {
        if (!this.options.trackFailedSearches) return;
        
        fetch(zippicksSearch.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'zippicks_track_search',
                type: 'successful_autocomplete',
                query: query,
                selected_suggestion: JSON.stringify(suggestion),
                nonce: zippicksSearch.nonce
            })
        }).catch(console.error);
    }
    
    trackFailedSearch(query, reason, error = null) {
        if (!this.options.trackFailedSearches) return;
        
        fetch(zippicksSearch.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'zippicks_track_search',
                type: 'failed_autocomplete',
                query: query,
                failure_reason: reason,
                error_details: error || '',
                nonce: zippicksSearch.nonce
            })
        }).catch(console.error);
    }
    
    // Public API methods
    destroy() {
        // Remove event listeners
        clearTimeout(this.searchTimeout);
        
        // Remove ARIA attributes
        this.input.removeAttribute('role');
        this.input.removeAttribute('aria-autocomplete');
        this.input.removeAttribute('aria-expanded');
        this.input.removeAttribute('aria-owns');
        this.input.removeAttribute('aria-describedby');
        this.input.removeAttribute('aria-haspopup');
        
        // Remove DOM elements
        if (this.listbox.parentNode) {
            this.listbox.parentNode.removeChild(this.listbox);
        }
        if (this.statusRegion.parentNode) {
            this.statusRegion.parentNode.removeChild(this.statusRegion);
        }
        if (this.instructions.parentNode) {
            this.instructions.parentNode.removeChild(this.instructions);
        }
    }
    
    updateSuggestions(newSuggestions) {
        this.displaySuggestions(newSuggestions, this.lastQuery);
    }
    
    getSuggestions() {
        return this.suggestions;
    }
    
    getSelectedSuggestion() {
        return this.selectedIndex >= 0 ? this.suggestions[this.selectedIndex] : null;
    }
}

// Initialize autocomplete on page load
document.addEventListener('DOMContentLoaded', function() {
    const searchInputs = document.querySelectorAll('.zippicks-search-input');
    
    searchInputs.forEach(input => {
        new ZipPicksAutocomplete(input, {
            minQueryLength: 2,
            maxSuggestions: 8,
            announceResultCount: true,
            trackFailedSearches: true
        });
    });
});

// Export for use in other modules
window.ZipPicksAutocomplete = ZipPicksAutocomplete;