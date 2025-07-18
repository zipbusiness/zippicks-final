/**
 * ZipPicks Web Application - Main JavaScript
 * Enterprise-grade vanilla JavaScript with no external dependencies
 */

(function() {
    'use strict';

    // Constants
    const LOADING_DELAY = 300; // Minimum loading time for better UX
    const SHARE_API_SUPPORTED = navigator.share !== undefined;
    const MOBILE_BREAKPOINT = 768;

    // DOM Elements Cache
    const elements = {
        loadingOverlay: null,
        searchForm: null,
        navbarToggle: null,
        navbarMenu: null,
        shareButton: null,
        shareRestaurantButton: null,
        dropdowns: [],
        categorySelect: null,
        citySelect: null
    };

    /**
     * Initialize DOM element references
     */
    function initializeElements() {
        elements.loadingOverlay = document.getElementById('loadingOverlay');
        elements.searchForm = document.getElementById('searchForm');
        elements.navbarToggle = document.querySelector('.navbar-toggle');
        elements.navbarMenu = document.querySelector('.navbar-menu');
        elements.shareButton = document.getElementById('shareButton');
        elements.shareRestaurantButton = document.getElementById('shareRestaurant');
        elements.dropdowns = document.querySelectorAll('.dropdown');
        elements.categorySelect = document.getElementById('category');
        elements.citySelect = document.getElementById('city');
    }

    /**
     * Show loading overlay
     */
    function showLoading() {
        if (elements.loadingOverlay) {
            elements.loadingOverlay.classList.add('active');
            elements.loadingOverlay.setAttribute('aria-hidden', 'false');
        }
    }

    /**
     * Hide loading overlay
     */
    function hideLoading() {
        if (elements.loadingOverlay) {
            setTimeout(() => {
                elements.loadingOverlay.classList.remove('active');
                elements.loadingOverlay.setAttribute('aria-hidden', 'true');
            }, LOADING_DELAY);
        }
    }

    /**
     * Handle search form submission
     */
    function handleSearchSubmit(event) {
        const form = event.target;
        const cityValue = form.city?.value;
        const categoryValue = form.category?.value;

        if (!cityValue || !categoryValue) {
            event.preventDefault();
            
            // Highlight empty fields
            if (!cityValue && form.city) {
                form.city.classList.add('error');
                form.city.focus();
            }
            if (!categoryValue && form.category) {
                form.category.classList.add('error');
                if (!cityValue) return;
                form.category.focus();
            }
            
            return;
        }

        // Show loading overlay
        showLoading();
    }

    /**
     * Handle navbar toggle for mobile
     */
    function handleNavbarToggle() {
        const isExpanded = elements.navbarToggle.getAttribute('aria-expanded') === 'true';
        elements.navbarToggle.setAttribute('aria-expanded', !isExpanded);
        elements.navbarMenu.classList.toggle('active');
        
        // Close all dropdowns when closing mobile menu
        if (isExpanded) {
            elements.dropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
                const toggle = dropdown.querySelector('.dropdown-toggle');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            });
        }
    }

    /**
     * Handle dropdown toggles
     */
    function handleDropdownToggle(dropdown) {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        if (!toggle) return;

        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            
            // Close other dropdowns
            elements.dropdowns.forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('active');
                    const t = d.querySelector('.dropdown-toggle');
                    if (t) t.setAttribute('aria-expanded', 'false');
                }
            });
            
            // Toggle current dropdown
            dropdown.classList.toggle('active');
            toggle.setAttribute('aria-expanded', !isExpanded);
        });
    }

    /**
     * Handle share functionality
     */
    async function handleShare(title, text, url) {
        if (SHARE_API_SUPPORTED) {
            try {
                await navigator.share({
                    title: title,
                    text: text,
                    url: url || window.location.href
                });
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error('Share failed:', err);
                    fallbackShare(url || window.location.href);
                }
            }
        } else {
            fallbackShare(url || window.location.href);
        }
    }

    /**
     * Fallback share functionality (copy to clipboard)
     */
    function fallbackShare(url) {
        const tempInput = document.createElement('input');
        tempInput.value = url;
        tempInput.style.position = 'fixed';
        tempInput.style.left = '-999999px';
        document.body.appendChild(tempInput);
        tempInput.select();
        
        try {
            document.execCommand('copy');
            showNotification('Link copied to clipboard!');
        } catch (err) {
            console.error('Copy failed:', err);
            showNotification('Failed to copy link');
        }
        
        document.body.removeChild(tempInput);
    }

    /**
     * Show notification message
     */
    function showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'notification';
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--color-text);
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            animation: slideUp 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideDown 0.3s ease';
            setTimeout(() => document.body.removeChild(notification), 300);
        }, 3000);
    }

    /**
     * Handle form field error styling
     */
    function handleFormFieldChange(event) {
        const field = event.target;
        if (field.value) {
            field.classList.remove('error');
        }
    }

    /**
     * Handle window resize
     */
    function handleResize() {
        if (window.innerWidth > MOBILE_BREAKPOINT) {
            // Reset mobile menu state on desktop
            if (elements.navbarMenu) {
                elements.navbarMenu.classList.remove('active');
            }
            if (elements.navbarToggle) {
                elements.navbarToggle.setAttribute('aria-expanded', 'false');
            }
        }
    }

    /**
     * Close dropdowns when clicking outside
     */
    function handleDocumentClick(event) {
        if (!event.target.closest('.dropdown')) {
            elements.dropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
                const toggle = dropdown.querySelector('.dropdown-toggle');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            });
        }
    }

    /**
     * Handle keyboard navigation
     */
    function handleKeyboardNavigation(event) {
        // Escape key closes mobile menu and dropdowns
        if (event.key === 'Escape') {
            if (elements.navbarMenu?.classList.contains('active')) {
                handleNavbarToggle();
            }
            
            elements.dropdowns.forEach(dropdown => {
                if (dropdown.classList.contains('active')) {
                    dropdown.classList.remove('active');
                    const toggle = dropdown.querySelector('.dropdown-toggle');
                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'false');
                        toggle.focus();
                    }
                }
            });
        }
    }

    /**
     * Animate elements on scroll
     */
    function handleScrollAnimations() {
        const animatedElements = document.querySelectorAll('[data-animate]');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });
        
        animatedElements.forEach(el => observer.observe(el));
    }

    /**
     * Initialize share buttons
     */
    function initializeShareButtons() {
        if (elements.shareButton) {
            elements.shareButton.addEventListener('click', () => {
                const pageTitle = document.querySelector('.page-title')?.textContent || 'Check out this list';
                const pageDescription = document.querySelector('.meta-description')?.content || '';
                handleShare(pageTitle, pageDescription, window.location.href);
            });
        }
        
        if (elements.shareRestaurantButton) {
            elements.shareRestaurantButton.addEventListener('click', () => {
                const restaurantName = document.querySelector('.restaurant-title')?.textContent || 'this restaurant';
                const description = `Check out ${restaurantName} on ZipPicks`;
                handleShare(restaurantName, description, window.location.href);
            });
        }
    }

    /**
     * Lazy load images
     */
    function initializeLazyLoading() {
        const lazyImages = document.querySelectorAll('img[data-lazy-src]');
        
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.lazySrc;
                        img.removeAttribute('data-lazy-src');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            lazyImages.forEach(img => imageObserver.observe(img));
        } else {
            // Fallback for older browsers
            lazyImages.forEach(img => {
                img.src = img.dataset.lazySrc;
                img.removeAttribute('data-lazy-src');
            });
        }
    }

    /**
     * Add animations
     */
    function addAnimations() {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideUp {
                from {
                    transform: translate(-50%, 100%);
                    opacity: 0;
                }
                to {
                    transform: translate(-50%, 0);
                    opacity: 1;
                }
            }
            
            @keyframes slideDown {
                from {
                    transform: translate(-50%, 0);
                    opacity: 1;
                }
                to {
                    transform: translate(-50%, 100%);
                    opacity: 0;
                }
            }
            
            .form-select.error {
                border-color: var(--color-danger) !important;
                animation: shake 0.3s ease;
            }
            
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
            
            [data-animate] {
                opacity: 0;
                transform: translateY(20px);
                transition: all 0.6s ease;
            }
            
            [data-animate].animated {
                opacity: 1;
                transform: translateY(0);
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Initialize all functionality
     */
    function initialize() {
        initializeElements();
        addAnimations();
        
        // Search form handling
        if (elements.searchForm) {
            elements.searchForm.addEventListener('submit', handleSearchSubmit);
        }
        
        // Mobile navigation
        if (elements.navbarToggle) {
            elements.navbarToggle.addEventListener('click', handleNavbarToggle);
        }
        
        // Dropdown menus
        elements.dropdowns.forEach(handleDropdownToggle);
        
        // Form field error handling
        if (elements.citySelect) {
            elements.citySelect.addEventListener('change', handleFormFieldChange);
        }
        if (elements.categorySelect) {
            elements.categorySelect.addEventListener('change', handleFormFieldChange);
        }
        
        // Share functionality
        initializeShareButtons();
        
        // Lazy loading
        initializeLazyLoading();
        
        // Scroll animations
        handleScrollAnimations();
        
        // Global event listeners
        window.addEventListener('resize', handleResize);
        document.addEventListener('click', handleDocumentClick);
        document.addEventListener('keydown', handleKeyboardNavigation);
        
        // Hide loading overlay if visible on page load
        hideLoading();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

    // Expose utility functions for external use
    window.ZipPicks = {
        showLoading,
        hideLoading,
        showNotification
    };

})();