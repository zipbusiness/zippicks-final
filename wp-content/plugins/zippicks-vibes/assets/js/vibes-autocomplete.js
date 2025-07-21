/**
 * ZipPicks Vibes Autocomplete System
 * Real-time search suggestions for Vibes
 * 
 * @author ZipPicks Engineering Team
 * @version 1.0.0
 */

class VibesAutocomplete {
    constructor(searchInput) {
        this.searchInput = searchInput;
        this.endpoint = searchInput.dataset.endpoint || '/wp-admin/admin-ajax.php';
        this.debounceTimer = null;
        this.currentIndex = -1;
        this.results = [];
        this.isVisible = false;
        
        this.init();
    }
    
    init() {
        this.createDropdown();
        this.attachEventListeners();
    }
    
    createDropdown() {
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'vibes-autocomplete-dropdown';
        this.dropdown.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            max-height: 320px;
            overflow-y: auto;
            display: none;
            margin-top: 4px;
        `;
        
        // Position relative to search input
        this.searchInput.parentNode.style.position = 'relative';
        this.searchInput.parentNode.appendChild(this.dropdown);
    }
    
    attachEventListeners() {
        // Input event with debouncing
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.handleInput(e.target.value.trim());
            }, 300);
        });
        
        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => {
            if (!this.isVisible) return;
            
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.navigateDown();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.navigateUp();
                    break;
                case 'Enter':
                    e.preventDefault();
                    this.selectCurrent();
                    break;
                case 'Escape':
                    this.hideDropdown();
                    break;
            }
        });
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.searchInput.parentNode.contains(e.target)) {
                this.hideDropdown();
            }
        });
        
        // Hide dropdown when input loses focus (with delay for click handling)
        this.searchInput.addEventListener('blur', () => {
            setTimeout(() => this.hideDropdown(), 150);
        });
    }
    
    async handleInput(query) {
        if (query.length < 2) {
            this.hideDropdown();
            return;
        }
        
        try {
            const response = await this.fetchVibes(query);
            this.results = response;
            this.renderResults();
            this.showDropdown();
        } catch (error) {
            console.error('ZipPicks Autocomplete Error:', error);
            this.hideDropdown();
        }
    }
    
    async fetchVibes(query) {
        const formData = new FormData();
        formData.append('action', 'zippicks_vibes_autocomplete');
        formData.append('query', query);
        formData.append('nonce', window.zippicks_autocomplete?.nonce || '');
        
        const response = await fetch(this.endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.data?.message || 'Failed to fetch vibes');
        }
        
        return data.data;
    }
    
    renderResults() {
        if (!this.results.length) {
            this.dropdown.innerHTML = '<div class="vibes-autocomplete-empty">No vibes found</div>';
            return;
        }
        
        const items = this.results.map((vibe, index) => `
            <div class="vibes-autocomplete-item" data-index="${index}" data-slug="${vibe.slug}">
                <div class="vibe-icon" style="background-color: ${vibe.color};">
                    ${vibe.icon}
                </div>
                <div class="vibe-info">
                    <span class="vibe-name">${this.escapeHtml(vibe.name)}</span>
                </div>
                <div class="vibe-color" style="background-color: ${vibe.color};"></div>
            </div>
        `).join('');
        
        this.dropdown.innerHTML = items;
        
        // Add click listeners to items
        this.dropdown.querySelectorAll('.vibes-autocomplete-item').forEach(item => {
            item.addEventListener('click', () => {
                const slug = item.dataset.slug;
                this.redirectToVibe(slug);
            });
            
            item.addEventListener('mouseenter', () => {
                this.currentIndex = parseInt(item.dataset.index);
                this.updateSelection();
            });
        });
        
        // Add CSS styles if not already added
        this.addStyles();
    }
    
    addStyles() {
        // Check if autocomplete CSS is already loaded via wp_enqueue_style
        // If not, we'll log a warning for development
        if (!document.querySelector('link[href*="vibes-autocomplete.css"]') && 
            !document.querySelector('#vibes-autocomplete-styles')) {
            console.warn('ZipPicks Vibes: Autocomplete CSS not loaded. Please ensure vibes-autocomplete.css is enqueued.');
        }
    }
    
    navigateDown() {
        this.currentIndex = Math.min(this.currentIndex + 1, this.results.length - 1);
        this.updateSelection();
    }
    
    navigateUp() {
        this.currentIndex = Math.max(this.currentIndex - 1, -1);
        this.updateSelection();
    }
    
    updateSelection() {
        const items = this.dropdown.querySelectorAll('.vibes-autocomplete-item');
        items.forEach((item, index) => {
            item.classList.toggle('selected', index === this.currentIndex);
        });
    }
    
    selectCurrent() {
        if (this.currentIndex >= 0 && this.results[this.currentIndex]) {
            const vibe = this.results[this.currentIndex];
            this.redirectToVibe(vibe.slug);
        }
    }
    
    redirectToVibe(slug) {
        window.location.href = `/vibes/${slug}`;
    }
    
    showDropdown() {
        this.dropdown.style.display = 'block';
        this.isVisible = true;
        this.currentIndex = -1;
    }
    
    hideDropdown() {
        this.dropdown.style.display = 'none';
        this.isVisible = false;
        this.currentIndex = -1;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize autocomplete when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const searchInputs = document.querySelectorAll('.search-input');
    
    searchInputs.forEach(input => {
        new VibesAutocomplete(input);
    });
});