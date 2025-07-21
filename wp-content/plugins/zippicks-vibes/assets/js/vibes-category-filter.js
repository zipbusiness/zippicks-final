/**
 * ZipPicks Vibes Category Filter
 * 
 * Enterprise-grade frontend category filtering with history.pushState support
 * Implements client-side filtering, URL management, and accessibility features
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

(function() {
    'use strict';

    // Cache DOM elements for performance
    let categoryPills = null;
    let vibeCards = null;
    let vibeCountElement = null;
    let scrollWrapper = null;
    let noVibesMessage = null;
    let vibesGrid = null;

    // State management
    let categoryMapping = {};
    let currentActiveIndex = 0;
    let isTransitioning = false;

    /**
     * Initialize the category filter system
     */
    function initCategoryFilter() {
        // Cache DOM queries
        categoryPills = document.querySelectorAll('.category-pill');
        vibeCards = document.querySelectorAll('.zp-vibe-card');
        vibeCountElement = document.getElementById('vibes-count');
        scrollWrapper = document.querySelector('.category-scroll-wrapper');
        noVibesMessage = document.querySelector('.zp-no-vibes-found');
        vibesGrid = document.querySelector('.zp-vibes-grid');
        
        // Exit if required elements are missing
        if (!categoryPills.length) {
            return;
        }

        // Build category mapping
        buildCategoryMapping();

        // Set up event delegation for better performance
        if (scrollWrapper) {
            scrollWrapper.addEventListener('click', handlePillClick);
            scrollWrapper.addEventListener('keydown', handleKeyboardNavigation);
        }

        // Handle browser navigation
        window.addEventListener('popstate', handlePopState);

        // Apply initial filter from URL
        applyInitialFilter();

        // Add touch support for mobile
        addTouchSupport();

        // Set up focus management
        setupFocusManagement();
    }

    /**
     * Build mapping of category slugs to IDs
     */
    function buildCategoryMapping() {
        categoryPills.forEach((pill, index) => {
            const slug = pill.getAttribute('data-category-slug');
            const id = pill.getAttribute('data-category-id');
            
            if (slug) {
                categoryMapping[slug] = id || '';
            }

            // Track active pill index
            if (pill.classList.contains('active')) {
                currentActiveIndex = index;
            }

            // Ensure pills are focusable
            if (!pill.hasAttribute('tabindex')) {
                pill.setAttribute('tabindex', '0');
            }
        });
        
        // Debug: Log the category mapping
        console.log('Category mapping built:', categoryMapping);
    }

    /**
     * Handle category pill clicks using event delegation
     */
    function handlePillClick(e) {
        const pill = e.target.closest('.category-pill');
        if (!pill || isTransitioning) return;

        e.preventDefault();
        
        const categorySlug = pill.getAttribute('data-category-slug');
        if (categorySlug) {
            performFilter(categorySlug);
            
            // Update current index for keyboard navigation
            currentActiveIndex = Array.from(categoryPills).indexOf(pill);
            
            // Maintain focus for accessibility
            pill.focus();
        }
    }

    /**
     * Perform the actual filtering with animations
     */
    function performFilter(categorySlug) {
        if (isTransitioning) return;
        
        isTransitioning = true;

        // Always use client-side filtering for smooth UX
        performClientSideFilter(categorySlug);
    }

    /**
     * Perform AJAX-based filtering
     */
    function performAjaxFilter(categorySlug) {
        // Show loading state
        showLoadingState();

        // Build request data
        const requestData = {
            action: 'zippicks_vibes_filter_by_category',
            nonce: vibesAjax.nonce || '',
            category: categorySlug
        };

        // Perform AJAX request
        fetch(vibesAjax.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(requestData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateVibesGrid(data.data);
                updateVibeCount(data.data.count || 0);
                updateActiveState(categorySlug);
                updateURL(categorySlug);
                handleEmptyState(data.data.count || 0);
            } else {
                console.error('Filter failed:', data.message);
                showErrorState();
            }
        })
        .catch(error => {
            console.error('AJAX error:', error);
            showErrorState();
        })
        .finally(() => {
            hideLoadingState();
            isTransitioning = false;
        });
    }

    /**
     * Perform client-side filtering (fallback)
     */
    function performClientSideFilter(categorySlug) {
        let visibleCount = 0;
        const categoryId = categoryMapping[categorySlug] || '';
        const promises = [];

        // Handle the case when no vibe cards exist (server-side filtered)
        if (!vibeCards.length) {
            // Just update UI state and URL
            updateActiveState(categorySlug);
            updateURL(categorySlug);
            isTransitioning = false;
            return;
        }

        // Filter vibe cards with staggered animations
        vibeCards.forEach((card, index) => {
            const categories = card.getAttribute('data-category') || '';
            const categoryArray = categories.split(' ').filter(Boolean);
            
            // Debug first card
            if (index === 0) {
                console.log('Filtering for category:', categorySlug, 'ID:', categoryId);
                console.log('Card categories:', categories);
                console.log('Category array:', categoryArray);
            }
            
            let shouldShow = false;
            
            if (categorySlug === 'all' || !categoryId) {
                shouldShow = true;
            } else if (categoryArray.includes(String(categoryId))) {
                // Convert categoryId to string for comparison since data attributes are strings
                shouldShow = true;
            }

            // Animate visibility changes
            const promise = animateCardVisibility(card, shouldShow, index);
            promises.push(promise);

            if (shouldShow) {
                visibleCount++;
            }
        });

        // Update count and UI after all animations
        Promise.all(promises).then(() => {
            updateVibeCount(visibleCount);
            updateActiveState(categorySlug);
            updateURL(categorySlug);
            handleEmptyState(visibleCount);
            isTransitioning = false;
        });
    }

    /**
     * Update vibes grid with AJAX response data
     */
    function updateVibesGrid(data) {
        if (!vibesGrid) return;

        const vibes = data.vibes || [];
        
        // Clear existing content
        vibesGrid.innerHTML = '';

        // Build new vibe cards
        vibes.forEach((vibe, index) => {
            const card = createVibeCard(vibe);
            if (card) {
                vibesGrid.appendChild(card);
                // Animate card appearance
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    requestAnimationFrame(() => {
                        card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    });
                }, index * 30);
            }
        });

        // Update cached vibe cards for client-side operations
        vibeCards = document.querySelectorAll('.zp-vibe-card');
    }

    /**
     * Create a vibe card element from data
     */
    function createVibeCard(vibe) {
        const card = document.createElement('a');
        card.href = `/vibes/${vibe.slug}`;
        card.className = 'zp-vibe-card';
        card.setAttribute('data-vibe-id', vibe.id);
        
        // Add category data attributes
        if (vibe.categories && vibe.categories.length) {
            const categoryIds = vibe.categories.map(cat => cat.id || '').join(' ');
            card.setAttribute('data-category', categoryIds);
        }

        // Build card HTML
        card.innerHTML = `
            <div class="zp-vibe-icon">
                ${vibe.icon_path ? `<img src="${vibe.icon_path}" alt="${vibe.name} icon">` : ''}
            </div>
            <h3 class="zp-vibe-name">${vibe.name}</h3>
            ${vibe.description ? `<p class="zp-vibe-description">${vibe.description}</p>` : ''}
        `;

        return card;
    }

    /**
     * Show loading state
     */
    function showLoadingState() {
        if (!vibesGrid) return;
        
        // Add loading class
        vibesGrid.classList.add('loading');
        
        // Insert loading indicator if it doesn't exist
        let loader = vibesGrid.querySelector('.zp-loading-indicator');
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'zp-loading-indicator';
            loader.innerHTML = '<span class="spinner"></span> Loading vibes...';
            vibesGrid.appendChild(loader);
        }
    }

    /**
     * Hide loading state
     */
    function hideLoadingState() {
        if (!vibesGrid) return;
        
        vibesGrid.classList.remove('loading');
        const loader = vibesGrid.querySelector('.zp-loading-indicator');
        if (loader) {
            loader.remove();
        }
    }

    /**
     * Show error state
     */
    function showErrorState() {
        if (!vibesGrid) return;
        
        const errorMessage = document.createElement('div');
        errorMessage.className = 'zp-error-message';
        errorMessage.innerHTML = '<p>Failed to load vibes. Please try again.</p>';
        vibesGrid.innerHTML = '';
        vibesGrid.appendChild(errorMessage);
    }

    /**
     * Animate card visibility with smooth transitions
     */
    function animateCardVisibility(card, show, index) {
        return new Promise(resolve => {
            // Add transition class if not present
            if (!card.style.transition) {
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            }

            if (show) {
                // Show with staggered animation
                setTimeout(() => {
                    card.style.display = '';
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(10px)';
                    
                    requestAnimationFrame(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                        setTimeout(resolve, 300);
                    });
                }, index * 30); // Stagger by 30ms
            } else {
                // Hide with fade out
                card.style.opacity = '0';
                card.style.transform = 'translateY(-10px)';
                
                setTimeout(() => {
                    card.style.display = 'none';
                    resolve();
                }, 300);
            }
        });
    }

    /**
     * Update vibe count with animation
     */
    function updateVibeCount(count) {
        if (!vibeCountElement) return;

        const currentCount = parseInt(vibeCountElement.textContent) || 0;
        
        if (currentCount !== count) {
            // Animate count change
            animateCountChange(currentCount, count);
        }
    }

    /**
     * Animate number changes smoothly
     */
    function animateCountChange(from, to) {
        const duration = 300;
        const startTime = performance.now();
        
        function updateCount(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const currentValue = Math.floor(from + (to - from) * progress);
            vibeCountElement.textContent = currentValue;
            
            if (progress < 1) {
                requestAnimationFrame(updateCount);
            }
        }
        
        requestAnimationFrame(updateCount);
    }

    /**
     * Update active state of pills
     */
    function updateActiveState(categorySlug) {
        categoryPills.forEach(pill => {
            const pillSlug = pill.getAttribute('data-category-slug');
            const isActive = pillSlug === categorySlug;
            
            pill.classList.toggle('active', isActive);
            pill.setAttribute('aria-current', isActive ? 'true' : 'false');
            
            // Update tabindex for better keyboard navigation
            pill.setAttribute('tabindex', isActive ? '0' : '-1');
        });

        // Ensure active pill is visible in scroll container
        ensureActiveVisible();
    }

    /**
     * Ensure active pill is visible in horizontal scroll
     */
    function ensureActiveVisible() {
        const activePill = document.querySelector('.category-pill.active');
        if (!activePill || !scrollWrapper) return;

        const scrollRect = scrollWrapper.getBoundingClientRect();
        const pillRect = activePill.getBoundingClientRect();
        
        if (pillRect.left < scrollRect.left) {
            scrollWrapper.scrollLeft -= scrollRect.left - pillRect.left + 20;
        } else if (pillRect.right > scrollRect.right) {
            scrollWrapper.scrollLeft += pillRect.right - scrollRect.right + 20;
        }
    }

    /**
     * Update URL using history.pushState
     */
    function updateURL(categorySlug) {
        const url = new URL(window.location);
        
        if (categorySlug === 'all') {
            url.searchParams.delete('category');
        } else {
            url.searchParams.set('category', categorySlug);
        }

        // Only update if URL actually changed
        if (url.toString() !== window.location.toString()) {
            window.history.pushState({category: categorySlug}, '', url);
        }
    }

    /**
     * Handle browser back/forward buttons
     */
    function handlePopState(e) {
        const urlParams = new URLSearchParams(window.location.search);
        const categorySlug = urlParams.get('category') || 'all';
        performFilter(categorySlug);
    }

    /**
     * Apply initial filter based on URL
     */
    function applyInitialFilter() {
        const urlParams = new URLSearchParams(window.location.search);
        const categorySlug = urlParams.get('category') || 'all';

        // Verify category exists
        if (categorySlug !== 'all' && !categoryMapping.hasOwnProperty(categorySlug)) {
            // Invalid category, redirect to all
            performFilter('all');
            return;
        }

        // Update active state to match current URL
        updateActiveState(categorySlug);
        
        // Don't refilter on initial load since server already filtered
        // Just ensure the UI state matches the URL
    }

    /**
     * Handle keyboard navigation
     */
    function handleKeyboardNavigation(e) {
        const pills = Array.from(categoryPills);
        
        switch (e.key) {
            case 'ArrowLeft':
            case 'ArrowUp':
                e.preventDefault();
                if (currentActiveIndex > 0) {
                    currentActiveIndex--;
                    pills[currentActiveIndex].click();
                    pills[currentActiveIndex].focus();
                }
                break;
                
            case 'ArrowRight':
            case 'ArrowDown':
                e.preventDefault();
                if (currentActiveIndex < pills.length - 1) {
                    currentActiveIndex++;
                    pills[currentActiveIndex].click();
                    pills[currentActiveIndex].focus();
                }
                break;
                
            case 'Home':
                e.preventDefault();
                currentActiveIndex = 0;
                pills[0].click();
                pills[0].focus();
                break;
                
            case 'End':
                e.preventDefault();
                currentActiveIndex = pills.length - 1;
                pills[currentActiveIndex].click();
                pills[currentActiveIndex].focus();
                break;
        }
    }

    /**
     * Add touch support for mobile devices
     */
    function addTouchSupport() {
        if (!scrollWrapper || !('ontouchstart' in window)) return;

        let startX = 0;
        let scrollLeft = 0;
        let isScrolling = false;

        scrollWrapper.addEventListener('touchstart', e => {
            startX = e.touches[0].pageX - scrollWrapper.offsetLeft;
            scrollLeft = scrollWrapper.scrollLeft;
            isScrolling = true;
        });

        scrollWrapper.addEventListener('touchmove', e => {
            if (!isScrolling) return;
            e.preventDefault();
            const x = e.touches[0].pageX - scrollWrapper.offsetLeft;
            const walk = (x - startX) * 2;
            scrollWrapper.scrollLeft = scrollLeft - walk;
        });

        scrollWrapper.addEventListener('touchend', () => {
            isScrolling = false;
        });
    }

    /**
     * Set up focus management for accessibility
     */
    function setupFocusManagement() {
        // Add role and aria-label to scroll wrapper
        if (scrollWrapper) {
            scrollWrapper.setAttribute('role', 'tablist');
            scrollWrapper.setAttribute('aria-label', 'Filter vibes by category');
        }

        // Set up pills as tabs
        categoryPills.forEach(pill => {
            pill.setAttribute('role', 'tab');
        });
    }

    /**
     * Handle empty state when no vibes match filter
     */
    function handleEmptyState(visibleCount) {
        if (!vibesGrid) return;

        // Always check for existing message, including dynamically created ones
        const existingMessage = document.querySelector('.zp-no-vibes-found');

        if (visibleCount === 0) {
            // Show empty state message
            if (!existingMessage) {
                const message = document.createElement('div');
                message.className = 'zp-no-vibes-found';
                message.innerHTML = '<p>No vibes found in this category.</p>';
                vibesGrid.parentNode.insertBefore(message, vibesGrid.nextSibling);
            } else {
                existingMessage.style.display = 'block';
            }
            vibesGrid.style.display = 'none';
        } else {
            // Hide empty state message
            if (existingMessage) {
                existingMessage.style.display = 'none';
            }
            vibesGrid.style.display = '';
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCategoryFilter);
    } else {
        initCategoryFilter();
    }

    // Re-initialize on dynamic content changes (e.g., AJAX)
    document.addEventListener('vibes:updated', initCategoryFilter);

})();