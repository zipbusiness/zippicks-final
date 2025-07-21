/**
 * ZipPicks Vibes v2 - Frontend Application
 * 
 * Client-side rendering for vibe discovery with anti-scraping protection
 * Enterprise-ready with error boundaries and fault tolerance
 * 
 * @package ZipPicksVibesV2
 * @since 2.0.0
 */

(function() {
    'use strict';

    // Error boundary wrapper
    function withErrorBoundary(fn, fallback) {
        return function() {
            try {
                return fn.apply(this, arguments);
            } catch (error) {
                console.error('[ZipPicks Vibes] Error:', error);
                if (typeof fallback === 'function') {
                    return fallback.call(this, error);
                }
            }
        };
    }

    // Wait for DOM and dependencies
    function initWhenReady() {
        if (typeof jQuery === 'undefined') {
            console.error('[ZipPicks Vibes] jQuery is required but not loaded');
            return;
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initApp);
        } else {
            initApp();
        }
    }

    function initApp() {
        const $ = jQuery;
        
        // Initialize ZipPicks Vibes App
        window.ZipPicksVibesApp = {
            
            // Configuration from localized script
            config: window.zippicksVibesV2 || {},
            
            // App state
            state: {
                vibes: [],
                loading: false,
                currentPage: 1,
                filters: {},
                sessionId: null
            },
            
            /**
             * Initialize the app
             */
            init: function() {
                console.log('ZipPicks Vibes v2 initialized');
                
                // Set session ID
                this.state.sessionId = this.config.sessionId || this.generateSessionId();
                
                // Initialize components
                this.initializeComponents();
                
                // Load initial data
                this.loadVibes();
                
                // Setup event handlers
                this.setupEventHandlers();
            },
            
            /**
             * Initialize UI components
             */
            initializeComponents: function() {
                // Initialize vibe containers
                const containers = document.querySelectorAll('.zippicks-vibes-container');
                containers.forEach(container => {
                    this.renderLoadingState(container);
                });
                
                // Initialize search if present
                const searchForm = document.querySelector('.zippicks-vibe-search');
                if (searchForm) {
                    this.initializeSearch(searchForm);
                }
                
                // Initialize category scrolling
                this.initializeCategoryScroll();
            },
            
            /**
             * Initialize horizontal scrolling for category filters
             */
            initializeCategoryScroll: function() {
                const scrollWrapper = document.querySelector('.category-scroll-wrapper');
                const filterContainer = document.querySelector('.zp-category-filters');
                
                if (!scrollWrapper || !filterContainer) return;
                
                // Update scroll indicators
                const updateScrollIndicators = () => {
                    const scrollLeft = scrollWrapper.scrollLeft;
                    const scrollWidth = scrollWrapper.scrollWidth;
                    const clientWidth = scrollWrapper.clientWidth;
                    const maxScroll = scrollWidth - clientWidth;
                    
                    // Check if scrolling is needed
                    if (scrollWidth > clientWidth) {
                        filterContainer.classList.add('has-scroll');
                        
                        // Update left indicator
                        if (scrollLeft > 5) {
                            filterContainer.classList.add('scrolled');
                        } else {
                            filterContainer.classList.remove('scrolled');
                        }
                        
                        // Update right indicator
                        if (scrollLeft < maxScroll - 5) {
                            filterContainer.classList.add('can-scroll-right');
                        } else {
                            filterContainer.classList.remove('can-scroll-right');
                        }
                    } else {
                        // No scroll needed, remove all indicators
                        filterContainer.classList.remove('has-scroll', 'scrolled', 'can-scroll-right');
                    }
                };
                
                // Initial check
                updateScrollIndicators();
                
                // Check on window resize with debounce
                let resizeTimeout;
                window.addEventListener('resize', () => {
                    clearTimeout(resizeTimeout);
                    resizeTimeout = setTimeout(updateScrollIndicators, 100);
                });
                
                // Update on scroll
                scrollWrapper.addEventListener('scroll', updateScrollIndicators, { passive: true });
                
                // Handle category pill clicks
                const categoryPills = scrollWrapper.querySelectorAll('.category-pill');
                categoryPills.forEach(pill => {
                    pill.addEventListener('click', (e) => {
                        e.preventDefault();
                        
                        // Remove active from all pills
                        categoryPills.forEach(p => p.classList.remove('active'));
                        
                        // Add active to clicked pill
                        pill.classList.add('active');
                        
                        // Smooth scroll to center the pill
                        const pillRect = pill.getBoundingClientRect();
                        const wrapperRect = scrollWrapper.getBoundingClientRect();
                        const pillCenter = pillRect.left + pillRect.width / 2;
                        const wrapperCenter = wrapperRect.left + wrapperRect.width / 2;
                        const scrollBy = pillCenter - wrapperCenter;
                        
                        scrollWrapper.scrollBy({
                            left: scrollBy,
                            behavior: 'smooth'
                        });
                        
                        // Trigger filter change
                        const category = pill.getAttribute('data-category') || pill.textContent.trim();
                        this.filterByCategory(category);
                    });
                });
                
                // Touch scrolling optimization for mobile
                let isScrolling = false;
                scrollWrapper.addEventListener('touchstart', () => {
                    isScrolling = true;
                }, { passive: true });
                
                scrollWrapper.addEventListener('touchend', () => {
                    isScrolling = false;
                    updateScrollIndicators();
                }, { passive: true });
                
                // Keyboard navigation support
                scrollWrapper.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        scrollWrapper.scrollBy({
                            left: -150,
                            behavior: 'smooth'
                        });
                    } else if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        scrollWrapper.scrollBy({
                            left: 150,
                            behavior: 'smooth'
                        });
                    }
                });
                
                // Make scrollWrapper focusable for keyboard navigation
                scrollWrapper.setAttribute('tabindex', '0');
                scrollWrapper.setAttribute('role', 'region');
                scrollWrapper.setAttribute('aria-label', 'Category filters');
                
                // Initialize on page load
                if (document.readyState === 'complete') {
                    updateScrollIndicators();
                } else {
                    window.addEventListener('load', updateScrollIndicators);
                }
            },
            
            /**
             * Load vibes data with retry logic
             */
            loadVibes: withErrorBoundary(function(filters = {}, retryCount = 0) {
                if (this.state.loading) return;
                
                this.state.loading = true;
                
                // Prepare request data
                const requestData = {
                    action: 'zippicks_vibes_load',
                    nonce: this.config.nonce,
                    page: this.state.currentPage,
                    filters: filters,
                    session_id: this.state.sessionId
                };
                
                // Make AJAX request with timeout
                $.ajax({
                    url: this.config.apiUrl || '/wp-json/zippicks/v2/vibes',
                    method: 'GET',
                    data: requestData,
                    timeout: 15000, // 15 second timeout
                    headers: {
                        'X-WP-Nonce': this.config.nonce,
                        'X-ZipPicks-Session': this.state.sessionId
                    },
                    success: (response) => {
                        this.handleVibesLoaded(response);
                    },
                    error: (xhr, status, error) => {
                        // Retry logic for transient failures
                        if (retryCount < 2 && (status === 'timeout' || xhr.status >= 500)) {
                            console.warn(`[ZipPicks Vibes] Retrying request (attempt ${retryCount + 1})`);
                            setTimeout(() => {
                                this.state.loading = false;
                                this.loadVibes(filters, retryCount + 1);
                            }, 1000 * (retryCount + 1)); // Exponential backoff
                        } else {
                            this.handleLoadError(error, xhr.status);
                        }
                    },
                    complete: () => {
                        this.state.loading = false;
                    }
                });
            }),
            
            /**
             * Handle loaded vibes data
             */
            handleVibesLoaded: function(response) {
                if (!response || !response.data) {
                    this.renderEmptyState();
                    return;
                }
                
                // Store vibes in state
                this.state.vibes = response.data.vibes || [];
                
                // Render vibes
                this.renderVibes();
                
                // Update pagination if present
                if (response.data.pagination) {
                    this.updatePagination(response.data.pagination);
                }
            },
            
            /**
             * Render vibes to containers
             */
            renderVibes: function() {
                const containers = document.querySelectorAll('.zippicks-vibes-container');
                
                containers.forEach(container => {
                    // Clear loading state
                    container.innerHTML = '';
                    
                    // Create grid
                    const grid = document.createElement('div');
                    grid.className = 'zippicks-vibes-grid';
                    
                    // Render each vibe
                    this.state.vibes.forEach(vibe => {
                        const vibeCard = this.createVibeCard(vibe);
                        grid.appendChild(vibeCard);
                    });
                    
                    container.appendChild(grid);
                });
            },
            
            /**
             * Create vibe card element
             */
            createVibeCard: function(vibe) {
                const card = document.createElement('div');
                card.className = 'zp-card-' + this.generateHash();
                card.dataset.vibeId = vibe.id;
                
                // Decode obfuscated data
                const name = vibe.n ? atob(vibe.n) : vibe.name;
                const description = vibe.d ? atob(vibe.d) : vibe.description;
                
                card.innerHTML = `
                    <div class="vibe-card-inner">
                        <div class="vibe-icon" style="background-color: ${vibe.c || vibe.color}">
                            <img src="${vibe.icon_url}" alt="${name}" />
                        </div>
                        <h3 class="vibe-name">${this.escapeHtml(name)}</h3>
                        <p class="vibe-description">${this.escapeHtml(description)}</p>
                        <a href="${vibe.url}" class="vibe-link" data-vibe="${vibe.h}">
                            Explore ${name}
                        </a>
                    </div>
                    ${this.generateWatermark(vibe.id)}
                `;
                
                return card;
            },
            
            /**
             * Setup event handlers
             */
            setupEventHandlers: function() {
                // Handle vibe clicks
                $(document).on('click', '.vibe-link', (e) => {
                    const vibeHash = $(e.target).data('vibe');
                    this.trackVibeClick(vibeHash);
                });
                
                // Handle search
                $(document).on('submit', '.zippicks-vibe-search', (e) => {
                    e.preventDefault();
                    const query = $(e.target).find('input[type="search"]').val();
                    this.searchVibes(query);
                });
                
                // Handle filters
                $(document).on('change', '.vibe-filter', (e) => {
                    this.updateFilters();
                });
            },
            
            /**
             * Initialize search functionality
             */
            initializeSearch: function(form) {
                const input = form.querySelector('input[type="search"]');
                if (!input) return;
                
                // Add autocomplete
                $(input).autocomplete({
                    source: (request, response) => {
                        this.getAutocompleteResults(request.term, response);
                    },
                    minLength: 2,
                    delay: 300
                });
            },
            
            /**
             * Get autocomplete results
             */
            getAutocompleteResults: function(term, callback) {
                $.ajax({
                    url: this.config.apiUrl + '/autocomplete',
                    method: 'GET',
                    data: {
                        q: term,
                        nonce: this.config.nonce
                    },
                    headers: {
                        'X-WP-Nonce': this.config.nonce
                    },
                    success: (response) => {
                        callback(response.data || []);
                    }
                });
            },
            
            /**
             * Search vibes
             */
            searchVibes: function(query) {
                this.state.filters.search = query;
                this.state.currentPage = 1;
                this.loadVibes(this.state.filters);
            },
            
            /**
             * Track vibe click
             */
            trackVibeClick: function(vibeHash) {
                // Send tracking data
                $.ajax({
                    url: this.config.apiUrl + '/track',
                    method: 'POST',
                    data: {
                        vibe: vibeHash,
                        action: 'click',
                        session: this.state.sessionId,
                        nonce: this.config.nonce
                    },
                    headers: {
                        'X-WP-Nonce': this.config.nonce
                    }
                });
            },
            
            /**
             * Render loading state
             */
            renderLoadingState: function(container) {
                container.innerHTML = `
                    <div class="zippicks-vibes-loading">
                        <div class="spinner"></div>
                        <p>Discovering vibes...</p>
                    </div>
                `;
            },
            
            /**
             * Render empty state
             */
            renderEmptyState: function() {
                const containers = document.querySelectorAll('.zippicks-vibes-container');
                containers.forEach(container => {
                    container.innerHTML = `
                        <div class="zippicks-vibes-empty">
                            <p>No vibes found. Try adjusting your filters.</p>
                        </div>
                    `;
                });
            },
            
            /**
             * Handle load error with detailed messaging
             */
            handleLoadError: function(error, statusCode) {
                console.error('[ZipPicks Vibes] Error loading vibes:', error, 'Status:', statusCode);
                
                let errorMessage = 'Unable to load vibes. Please try again.';
                
                // Provide specific error messages based on status
                if (statusCode === 403) {
                    errorMessage = 'Access denied. Please check your permissions.';
                } else if (statusCode === 429) {
                    errorMessage = 'Too many requests. Please wait a moment and try again.';
                } else if (statusCode >= 500) {
                    errorMessage = 'Server error. Our team has been notified.';
                } else if (!navigator.onLine) {
                    errorMessage = 'No internet connection. Please check your network.';
                }
                
                const containers = document.querySelectorAll('.zippicks-vibes-container');
                containers.forEach(container => {
                    container.innerHTML = `
                        <div class="zippicks-vibes-error">
                            <p>${this.escapeHtml(errorMessage)}</p>
                            <button class="retry-button" onclick="ZipPicksVibesApp.retryLoad()">
                                Try Again
                            </button>
                        </div>
                    `;
                });
            },
            
            /**
             * Retry loading
             */
            retryLoad: function() {
                this.loadVibes(this.state.filters);
            },
            
            /**
             * Generate session ID
             */
            generateSessionId: function() {
                return 'zp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            },
            
            /**
             * Generate hash
             */
            generateHash: function() {
                return Math.random().toString(36).substr(2, 8);
            },
            
            /**
             * Generate watermark
             */
            generateWatermark: function(vibeId) {
                const timestamp = Date.now();
                const fp = btoa(vibeId + '|' + timestamp + '|' + this.state.sessionId);
                
                return `
                    <span class="zp-fp" data-hash="${fp}" style="display:none;">
                        ${this.generateNoise()}
                    </span>
                `;
            },
            
            /**
             * Generate noise content
             */
            generateNoise: function() {
                const words = ['zippicks', 'protected', 'content'];
                return words.sort(() => Math.random() - 0.5).join('-') + '-' + Date.now();
            },
            
            /**
             * Escape HTML
             */
            escapeHtml: function(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, m => map[m]);
            },
            
            /**
             * Update filters
             */
            updateFilters: function() {
                const filters = {};
                
                $('.vibe-filter').each(function() {
                    const $this = $(this);
                    const name = $this.attr('name');
                    const value = $this.val();
                    
                    if (value) {
                        filters[name] = value;
                    }
                });
                
                this.state.filters = filters;
                this.state.currentPage = 1;
                this.loadVibes(filters);
            },
            
            /**
             * Update pagination
             */
            updatePagination: function(pagination) {
                // Implementation depends on pagination UI
                console.log('Pagination:', pagination);
            }
        };
        
        // Initialize app with error boundary
        ZipPicksVibesApp.init = withErrorBoundary(ZipPicksVibesApp.init);
        
        // Initialize app when DOM is ready
        ZipPicksVibesApp.init();
    }
    
    // Start initialization
    initWhenReady();
    
})();