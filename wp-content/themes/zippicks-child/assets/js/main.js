/*
 * ZipPicks Main JavaScript
 * Interactive functionality for the platform
 * $100B Local Discovery Platform
 */

// ZipPicks namespace
const ZipPicks = {
    
    // Initialize all platform functionality
    init: function() {
        console.log('ZipPicks platform initialized! 🚀');
        
        // Initialize all modules
        this.navigation.init();
        this.search.init();
        this.utils.init();
        
        // Platform ready
        console.log('✅ Navigation system ready');
        console.log('✅ Search system ready');
        console.log('✅ Utilities ready');
        console.log('✅ ZipPicks loaded in ' + ((performance.timing) ? (performance.timing.loadEventEnd - performance.timing.navigationStart) + 'ms' : 'unknown time'));
    },

    // ========================================
    // NAVIGATION MODULE
    // ========================================
    navigation: {
        
        init: function() {
            this.bindEvents();
            this.setupMobileMenu();
            this.setupScrollHeader();
        },

        // Bind navigation event listeners
        bindEvents: function() {
            // Mobile menu toggle
            const mobileToggle = document.querySelector('.zp-mobile-toggle');
            if (mobileToggle) {
                mobileToggle.addEventListener('click', this.toggleMobileMenu.bind(this));
                console.log('📱 Mobile toggle button found and bound');
            } else {
                console.warn('📱 Mobile toggle button not found');
            }

            // Mobile menu close button
            const mobileClose = document.querySelector('.zp-mobile-menu__close');
            if (mobileClose) {
                mobileClose.addEventListener('click', this.closeMobileMenu.bind(this));
                console.log('📱 Mobile close button found and bound');
            } else {
                console.warn('📱 Mobile close button not found');
            }

            // Close mobile menu when clicking outside
            document.addEventListener('click', this.handleOutsideClick.bind(this));

            // Handle mobile submenu toggles
            const dropdownItems = document.querySelectorAll('.zp-mobile-menu__item--dropdown');
            dropdownItems.forEach(item => {
                const link = item.querySelector('.zp-mobile-menu__link');
                if (link) {
                    link.addEventListener('click', this.toggleMobileSubmenu.bind(this));
                }
            });

            // Keyboard navigation
            document.addEventListener('keydown', this.handleKeyboardNav.bind(this));

            // Close menu when clicking nav links
            const mobileLinks = document.querySelectorAll('.zp-mobile-menu__link');
            mobileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    console.log('📱 Mobile nav link clicked, closing menu');
                    setTimeout(() => this.closeMobileMenu(), 150);
                });
            });

            console.log('📱 Found ' + mobileLinks.length + ' mobile menu links');
        },

        // Setup mobile menu functionality
        setupMobileMenu: function() {
            const mobileMenu = document.getElementById('zp-mobile-menu');
            if (mobileMenu) {
                // Ensure menu is hidden on load
                mobileMenu.classList.remove('zp-mobile-menu--active');
                mobileMenu.style.display = 'none';
                console.log('📱 Mobile menu found and initialized');
            } else {
                console.warn('📱 Mobile menu element not found');
            }
        },

        // Setup header scroll behavior
        setupScrollHeader: function() {
            const header = document.querySelector('.zp-header');
            if (!header) return;

            let lastScrollY = window.scrollY;
            let ticking = false;

            const updateHeader = () => {
                const currentScrollY = window.scrollY;
                
                // Add scrolled class when scrolled down
                if (currentScrollY > 10) {
                    header.classList.add('zp-header--scrolled');
                } else {
                    header.classList.remove('zp-header--scrolled');
                }

                lastScrollY = currentScrollY;
                ticking = false;
            };

            window.addEventListener('scroll', () => {
                if (!ticking) {
                    requestAnimationFrame(updateHeader);
                    ticking = true;
                }
            });
        },

        // Toggle mobile menu
        toggleMobileMenu: function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            console.log('📱 Toggle mobile menu called');
            
            const mobileMenu = document.getElementById('zp-mobile-menu');
            const mobileToggle = document.querySelector('.zp-mobile-toggle');
            
            if (mobileMenu && mobileToggle) {
                const isActive = mobileMenu.classList.contains('zp-mobile-menu--active') || 
                                mobileMenu.style.display === 'flex';
                
                if (isActive) {
                    this.closeMobileMenu();
                } else {
                    this.openMobileMenu();
                }
            } else {
                console.error('📱 Mobile menu or toggle not found');
            }
        },

        // Open mobile menu
        openMobileMenu: function() {
            console.log('📱 Opening mobile menu');
            
            const mobileMenu = document.getElementById('zp-mobile-menu');
            const mobileToggle = document.querySelector('.zp-mobile-toggle');
            const body = document.body;
            
            if (mobileMenu && mobileToggle) {
                // Show menu
                mobileMenu.style.display = 'flex';
                mobileMenu.classList.add('zp-mobile-menu--active');
                
                // Update toggle state
                mobileToggle.classList.add('zp-mobile-toggle--active');
                mobileToggle.setAttribute('aria-expanded', 'true');
                
                // Prevent body scroll
                body.style.overflow = 'hidden';
                body.classList.add('zp-mobile-menu-open');
                
                // Add active class after display for animation
                setTimeout(() => {
                    mobileMenu.classList.add('active');
                }, 10);
                
                // Focus management for accessibility
                const firstLink = mobileMenu.querySelector('.zp-mobile-menu__link');
                if (firstLink) {
                    setTimeout(() => firstLink.focus(), 100);
                }
                
                console.log('📱 Mobile menu opened successfully');
            } else {
                console.error('📱 Could not open mobile menu - elements not found');
            }
        },

        // Close mobile menu
        closeMobileMenu: function() {
            console.log('📱 Closing mobile menu');
            
            const mobileMenu = document.getElementById('zp-mobile-menu');
            const mobileToggle = document.querySelector('.zp-mobile-toggle');
            const body = document.body;
            
            if (mobileMenu && mobileToggle) {
                // Remove active classes
                mobileMenu.classList.remove('zp-mobile-menu--active', 'active');
                mobileToggle.classList.remove('zp-mobile-toggle--active');
                mobileToggle.setAttribute('aria-expanded', 'false');
                
                // Restore body scroll
                body.style.overflow = '';
                body.classList.remove('zp-mobile-menu-open');
                
                // Hide menu after animation
                setTimeout(() => {
                    mobileMenu.style.display = 'none';
                }, 300);
                
                // Return focus to toggle button
                mobileToggle.focus();
                
                console.log('📱 Mobile menu closed successfully');
            } else {
                console.error('📱 Could not close mobile menu - elements not found');
            }
        },

        // Handle clicks outside mobile menu
        handleOutsideClick: function(e) {
            const mobileMenu = document.getElementById('zp-mobile-menu');
            const mobileToggle = document.querySelector('.zp-mobile-toggle');
            
            if (mobileMenu && mobileMenu.classList.contains('zp-mobile-menu--active')) {
                const menuContent = mobileMenu.querySelector('.zp-mobile-menu__body');
                const menuHeader = mobileMenu.querySelector('.zp-mobile-menu__header');
                
                // Check if click is outside menu content but inside overlay
                if (e.target === mobileMenu || 
                    (!menuContent?.contains(e.target) && 
                     !menuHeader?.contains(e.target) && 
                     !mobileToggle?.contains(e.target))) {
                    console.log('📱 Clicked outside menu, closing');
                    this.closeMobileMenu();
                }
            }
        },

        // Toggle mobile submenu
        toggleMobileSubmenu: function(e) {
            const item = e.target.closest('.zp-mobile-menu__item--dropdown');
            if (item) {
                e.preventDefault();
                item.classList.toggle('zp-mobile-menu__item--expanded');
                console.log('📱 Submenu toggled');
            }
        },

        // Handle keyboard navigation
        handleKeyboardNav: function(e) {
            // Close mobile menu with Escape key
            if (e.key === 'Escape') {
                const mobileMenu = document.getElementById('zp-mobile-menu');
                if (mobileMenu && mobileMenu.classList.contains('zp-mobile-menu--active')) {
                    console.log('📱 Escape key pressed, closing menu');
                    this.closeMobileMenu();
                }
            }
        }
    },

    // ========================================
    // SEARCH MODULE
    // ========================================
    search: {
        
        init: function() {
            this.bindEvents();
            this.setupAutoComplete();
        },

        // Bind search event listeners
        bindEvents: function() {
            // Search form submissions
            const searchForms = document.querySelectorAll('.zp-search-bar');
            searchForms.forEach(form => {
                const input = form.querySelector('.zp-search-input');
                const button = form.querySelector('.zp-search-action--primary');
                
                if (input && button) {
                    button.addEventListener('click', this.handleSearch.bind(this));
                    input.addEventListener('keypress', this.handleSearchKeypress.bind(this));
                    input.addEventListener('input', this.handleSearchInput.bind(this));
                }
            });

            // Filter chip interactions
            const filterChips = document.querySelectorAll('.zp-filter-chip, .zp-price-chip');
            filterChips.forEach(chip => {
                chip.addEventListener('click', this.handleFilterToggle.bind(this));
            });

            // Quick filter cards
            const quickFilters = document.querySelectorAll('.zp-quick-filter-card');
            quickFilters.forEach(card => {
                card.addEventListener('click', this.handleQuickFilter.bind(this));
            });
        },

        // Setup autocomplete functionality
        setupAutoComplete: function() {
            // This would integrate with your restaurant database
            console.log('🔍 Search autocomplete ready');
        },

        // Handle search submission
        handleSearch: function(e) {
            const searchInput = e.target.closest('.zp-search-bar').querySelector('.zp-search-input');
            const query = searchInput.value.trim();
            
            if (query) {
                console.log('🔍 Searching for:', query);
                // Implement search functionality
                this.performSearch(query);
            }
        },

        // Handle search input keypress
        handleSearchKeypress: function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = e.target.value.trim();
                if (query) {
                    this.performSearch(query);
                }
            }
        },

        // Handle search input changes
        handleSearchInput: function(e) {
            const query = e.target.value.trim();
            if (query.length > 2) {
                // Show suggestions
                console.log('🔍 Showing suggestions for:', query);
            }
        },

        // Perform search
        performSearch: function(query) {
            console.log('🔍 Performing search:', query);
            
            // Build search URL with current filters
            const filters = this.getCurrentFilters();
            const searchUrl = this.buildSearchUrl(query, filters);
            
            // Navigate to search results
            window.location.href = searchUrl;
        },

        // Handle filter chip toggle
        handleFilterToggle: function(e) {
            const chip = e.target;
            chip.classList.toggle('zp-filter-chip--active');
            chip.classList.toggle('zp-price-chip--active');
            
            console.log('🔧 Filter toggled:', chip.textContent);
            
            // Update search results if needed
            this.updateFilters();
        },

        // Handle quick filter selection
        handleQuickFilter: function(e) {
            const card = e.target.closest('.zp-quick-filter-card');
            const title = card.querySelector('.zp-quick-filter-title').textContent;
            
            console.log('⚡ Quick filter selected:', title);
            
            // Apply the quick filter
            this.applyQuickFilter(title);
        },

        // Get current active filters
        getCurrentFilters: function() {
            const activeFilters = {
                cuisine: [],
                price: [],
                features: []
            };

            // Get active cuisine filters
            document.querySelectorAll('.zp-filter-chip--active').forEach(chip => {
                const group = chip.closest('.zp-filter-group');
                const label = group.querySelector('.zp-filter-label').textContent.toLowerCase();
                
                if (label.includes('cuisine')) {
                    activeFilters.cuisine.push(chip.textContent);
                } else if (label.includes('feature')) {
                    activeFilters.features.push(chip.textContent);
                }
            });

            // Get active price filters
            document.querySelectorAll('.zp-price-chip--active').forEach(chip => {
                activeFilters.price.push(chip.textContent);
            });

            return activeFilters;
        },

        // Build search URL with filters
        buildSearchUrl: function(query, filters) {
            const params = new URLSearchParams();
            params.set('s', query);

            if (filters.cuisine.length > 0) {
                params.set('cuisine', filters.cuisine.join(','));
            }
            if (filters.price.length > 0) {
                params.set('price', filters.price.join(','));
            }
            if (filters.features.length > 0) {
                params.set('features', filters.features.join(','));
            }

            return window.location.origin + '/restaurants/?' + params.toString();
        },

        // Update search results with current filters
        updateFilters: function() {
            // This would trigger an AJAX update of search results
            console.log('🔧 Updating search results with filters');
        },

        // Apply quick filter preset
        applyQuickFilter: function(filterName) {
            // Quick filter presets
            const presets = {
                'Pizza Night': { cuisine: ['Italian'], features: ['Delivery'] },
                'Sushi Date': { cuisine: ['Japanese'], price: ['$$$'] },
                'Taco Tuesday': { cuisine: ['Mexican'], price: ['$'] },
                'Coffee Meetup': { cuisine: ['Café'], features: ['WiFi'] }
            };

            const preset = presets[filterName];
            if (preset) {
                // Clear current filters
                this.clearAllFilters();
                
                // Apply preset filters
                this.applyFilterPreset(preset);
                
                // Perform search
                this.performSearch(filterName);
            }
        },

        // Clear all active filters
        clearAllFilters: function() {
            document.querySelectorAll('.zp-filter-chip--active, .zp-price-chip--active').forEach(chip => {
                chip.classList.remove('zp-filter-chip--active', 'zp-price-chip--active');
            });
        },

        // Apply filter preset
        applyFilterPreset: function(preset) {
            // Apply cuisine filters
            if (preset.cuisine) {
                preset.cuisine.forEach(cuisine => {
                    const chip = Array.from(document.querySelectorAll('.zp-filter-chip')).find(c => 
                        c.textContent.trim() === cuisine
                    );
                    if (chip) {
                        chip.classList.add('zp-filter-chip--active');
                    }
                });
            }

            // Apply price filters
            if (preset.price) {
                preset.price.forEach(price => {
                    const chip = Array.from(document.querySelectorAll('.zp-price-chip')).find(c => 
                        c.textContent.trim() === price
                    );
                    if (chip) {
                        chip.classList.add('zp-price-chip--active');
                    }
                });
            }

            // Apply feature filters
            if (preset.features) {
                preset.features.forEach(feature => {
                    const chip = Array.from(document.querySelectorAll('.zp-filter-chip')).find(c => 
                        c.textContent.trim() === feature
                    );
                    if (chip) {
                        chip.classList.add('zp-filter-chip--active');
                    }
                });
            }
        }
    },

    // ========================================
    // UTILITIES MODULE
    // ========================================
    utils: {
        
        init: function() {
            this.setupSmoothScrolling();
            this.setupImageLazyLoading();
            this.setupTooltips();
        },

        // Setup smooth scrolling for anchor links
        setupSmoothScrolling: function() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        },

        // Setup lazy loading for images
        setupImageLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            observer.unobserve(img);
                        }
                    });
                });

                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
        },

        // Setup tooltips
        setupTooltips: function() {
            document.querySelectorAll('[data-tooltip]').forEach(element => {
                element.addEventListener('mouseenter', this.showTooltip);
                element.addEventListener('mouseleave', this.hideTooltip);
            });
        },

        // Show tooltip
        showTooltip: function(e) {
            const tooltipText = e.target.getAttribute('data-tooltip');
            if (tooltipText) {
                const tooltip = document.createElement('div');
                tooltip.className = 'zp-tooltip';
                tooltip.textContent = tooltipText;
                document.body.appendChild(tooltip);
                
                const rect = e.target.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
            }
        },

        // Hide tooltip
        hideTooltip: function() {
            const tooltip = document.querySelector('.zp-tooltip');
            if (tooltip) {
                tooltip.remove();
            }
        },

        // Debounce utility function
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // Throttle utility function
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            }
        }
    }
};

// ========================================
// GLOBAL UTILITY FUNCTIONS
// ========================================

// Make navigation functions globally available for HTML onclick handlers
// These match the function names called in your header-zippicks.php
window.zippicks_toggle_mobile_menu = function() {
    console.log('📱 Global toggle function called');
    ZipPicks.navigation.toggleMobileMenu();
};

window.zippicks_open_mobile_menu = function() {
    console.log('📱 Global open function called');
    ZipPicks.navigation.openMobileMenu();
};

window.zippicks_close_mobile_menu = function() {
    console.log('📱 Global close function called');
    ZipPicks.navigation.closeMobileMenu();
};

// ========================================
// INITIALIZE PLATFORM
// ========================================

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('📱 DOM Content Loaded, initializing ZipPicks...');
    ZipPicks.init();
});

// Additional initialization for dynamic content
document.addEventListener('ZipPicksContentLoaded', ZipPicks.init.bind(ZipPicks));

// Handle page visibility changes
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        console.log('👁️ ZipPicks back in focus');
    }
});

// Performance monitoring
window.addEventListener('load', function() {
    if (performance.timing) {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        console.log(`⚡ ZipPicks loaded in ${loadTime}ms`);
    }
});