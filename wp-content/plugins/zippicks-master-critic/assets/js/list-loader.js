/**
 * ZipPicks Master Critic List Loader - Enhanced Edition
 * Handles AJAX content loading with anti-scraping protection and advanced interactivity
 */

(function($) {
    'use strict';

    class ZipPicksListLoader {
        constructor() {
            this.listId = null;
            this.loadingAttempts = 0;
            this.maxAttempts = 3;
            this.retryDelay = 2000; // 2 seconds
            this.contentContainer = '#zippicks-list-content';
            this.isLoading = false;
            
            // Enhanced features
            this.animationDuration = window.zippicksAjax?.settings?.animationDuration || 300;
            this.currentBusinessIndex = 0;
            this.keyboardNavigationActive = false;
            this.touchStartY = 0;
            this.touchEndY = 0;
            this.swipeThreshold = 50;
            this.intersectionObserver = null;
            this.prefetchedImages = new Set();
            
            this.init();
        }

        init() {
            // Check if we're on a Master Critic list page
            if (!this.isListPage()) {
                return;
            }

            this.listId = this.getListId();
            if (!this.listId) {
                this.showError('Unable to identify list');
                return;
            }

            // Initialize advanced features
            this.setupKeyboardNavigation();
            this.setupMobileGestures();
            this.setupProgressiveImageLoading();
            this.preloadCriticalAssets();
            
            // Start loading content after a short delay to let skeleton display
            setTimeout(() => {
                this.loadContent();
            }, 500);

            // Bind retry button if it exists
            this.bindRetryButton();
        }

        isListPage() {
            return $('body').hasClass('single-master_critic_list') || 
                   $('.zippicks-master-critic-container').length > 0;
        }

        getListId() {
            // Try multiple methods to get the list ID
            const bodyClasses = $('body').attr('class');
            const postIdMatch = bodyClasses.match(/postid-(\d+)/);
            
            if (postIdMatch) {
                return parseInt(postIdMatch[1]);
            }

            // Fallback: check if ID is available in global variable
            if (typeof window.zippicksListId !== 'undefined') {
                return parseInt(window.zippicksListId);
            }

            // Last resort: check meta tag
            const metaId = $('meta[name="zippicks-list-id"]').attr('content');
            if (metaId) {
                return parseInt(metaId);
            }

            return null;
        }

        async loadContent() {
            if (this.isLoading) {
                return;
            }

            this.isLoading = true;
            this.loadingAttempts++;

            try {
                // Update loading indicator
                this.updateLoadingState('Loading content...');

                // First try the AJAX endpoint (backward compatibility)
                const ajaxResponse = await this.loadViaAjax();
                
                if (ajaxResponse.success) {
                    this.renderContent(ajaxResponse.data);
                    this.trackSuccessfulLoad();
                    return;
                }

                // If AJAX fails, try REST API
                const restResponse = await this.loadViaRest();
                this.renderContent(restResponse);
                this.trackSuccessfulLoad();

            } catch (error) {
                console.error('ZipPicks: Content loading failed', error);
                this.handleLoadingError(error);
            } finally {
                this.isLoading = false;
            }
        }

        async loadViaAjax() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: window.ajaxurl || '/wp-admin/admin-ajax.php',
                    type: 'POST',
                    data: {
                        action: 'zippicks_load_list_content',
                        list_id: this.listId,
                        nonce: this.generateNonce()
                    },
                    timeout: 15000, // 15 seconds
                    success: resolve,
                    error: reject
                });
            });
        }

        async loadViaRest() {
            const response = await fetch(`/wp-json/zippicks/v1/lists/${this.listId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-ZipPicks-Client': 'list-loader'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        }

        generateNonce() {
            // Try to get nonce from page
            const nonceEl = $('meta[name="zippicks-nonce"]');
            if (nonceEl.length) {
                return nonceEl.attr('content');
            }

            // Generate a simple nonce-like string for backward compatibility
            return `zippicks_${this.listId}_${Date.now()}`;
        }

        renderContent(data) {
            try {
                let content = '';

                if (data.content) {
                    // Direct content from AJAX
                    content = data.content;
                } else if (data.title && data.businesses) {
                    // REST API response - render manually
                    content = this.renderFromBusinessData(data);
                } else {
                    throw new Error('Invalid content data received');
                }

                // Smooth content reveal animation
                this.animateContentReveal(content, data);

            } catch (error) {
                console.error('ZipPicks: Content rendering failed', error);
                this.showError('Failed to display content');
            }
        }
        
        animateContentReveal(content, data) {
            const $container = $(this.contentContainer);
            
            // Fade out skeleton
            $container.addClass('zp-transitioning').animate({
                opacity: 0
            }, this.animationDuration / 2, () => {
                
                // Replace content
                $container.html(content);
                
                // Initialize interactive elements before reveal
                this.initializeInteractiveElements();
                this.initializeProgressiveImages();
                this.setupBusinessCardAnimations();
                
                // Update metadata
                if (data.title && data.title !== document.title) {
                    document.title = data.title + ' | ZipPicks';
                }
                this.updateSchema(data);
                
                // Staggered reveal animation
                this.performStaggeredReveal($container, data);
            });
        }
        
        performStaggeredReveal($container, data) {
            // Fade in container
            $container.animate({
                opacity: 1
            }, this.animationDuration / 2);
            
            // Staggered animation for business cards
            const $businessCards = $container.find('.zippicks-business-card');
            
            $businessCards.each((index, card) => {
                const $card = $(card);
                $card.css({
                    opacity: 0,
                    transform: 'translateY(20px)'
                });
                
                setTimeout(() => {
                    $card.animate({
                        opacity: 1
                    }, this.animationDuration).css({
                        transform: 'translateY(0)',
                        transition: `transform ${this.animationDuration}ms ease-out`
                    });
                }, index * 100); // 100ms delay between cards
            });
            
            // Remove transitioning class
            setTimeout(() => {
                $container.removeClass('zp-transitioning');
                
                // Fire events after animation complete
                $(document).trigger('zippicks:content-loaded', [data]);
                $(document).trigger('zippicks:animation-complete', [data]);
                this.trackPageView();
                
            }, $businessCards.length * 100 + this.animationDuration);
        }

        renderFromBusinessData(data) {
            let html = `
                <header class="zippicks-content-header">
                    <h1>${this.escapeHtml(data.title)}</h1>
                    ${data.excerpt ? `<p class="list-excerpt">${this.escapeHtml(data.excerpt)}</p>` : ''}
                </header>
            `;

            if (data.businesses && data.businesses.length > 0) {
                html += '<div class="zippicks-business-list">';
                
                data.businesses.forEach((business, index) => {
                    html += this.renderBusinessCard(business, index + 1);
                });
                
                html += '</div>';
            } else {
                html += '<p class="no-businesses">No businesses found in this list.</p>';
            }

            return html;
        }

        renderBusinessCard(business, rank) {
            const pillars = business.pillar_scores || {};
            const vibes = business.vibes || [];
            const topDishes = business.top_dishes || [];

            return `
                <div class="zippicks-business-card" data-rank="${rank}">
                    <div class="business-rank-badge">
                        <span class="rank-number">${rank}</span>
                    </div>
                    
                    <div class="business-content">
                        <div class="business-header">
                            <h3 class="business-name">${this.escapeHtml(business.name)}</h3>
                            <div class="business-score">${business.score || 'N/A'}</div>
                        </div>
                        
                        <div class="business-details">
                            ${business.price_tier ? `<span class="price-tier">${this.escapeHtml(business.price_tier)}</span>` : ''}
                            ${business.review_count ? `<span class="review-count">${business.review_count} reviews</span>` : ''}
                        </div>
                        
                        ${business.summary ? `
                            <div class="business-summary">
                                <p>${this.escapeHtml(business.summary)}</p>
                            </div>
                        ` : ''}
                        
                        ${topDishes.length > 0 ? `
                            <div class="top-dishes">
                                <strong>Must-try:</strong> ${topDishes.map(dish => this.escapeHtml(dish)).join(', ')}
                            </div>
                        ` : ''}
                        
                        ${Object.keys(pillars).length > 0 ? `
                            <div class="pillar-scores">
                                ${Object.entries(pillars).map(([pillar, score]) => `
                                    <div class="pillar-score">
                                        <span class="pillar-label">${this.escapeHtml(pillar)}</span>
                                        <span class="pillar-value">${score}</span>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                        
                        ${vibes.length > 0 ? `
                            <div class="business-vibes">
                                ${vibes.map(vibe => `<span class="vibe-tag">${this.escapeHtml(vibe)}</span>`).join('')}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        initializeInteractiveElements() {
            // Initialize tooltips
            $('[data-tooltip]').each(function() {
                $(this).attr('title', $(this).data('tooltip'));
            });

            // Initialize expandable sections
            $('.expandable-section').on('click', function() {
                $(this).toggleClass('expanded');
            });

            // Initialize sharing buttons
            $('.share-button').on('click', (e) => {
                e.preventDefault();
                this.handleShare($(e.target));
            });

            // Initialize favorite buttons
            $('.favorite-button').on('click', (e) => {
                e.preventDefault();
                this.handleFavorite($(e.target));
            });
        }

        handleLoadingError(error) {
            if (this.loadingAttempts < this.maxAttempts) {
                this.updateLoadingState(`Loading failed. Retrying in ${this.retryDelay/1000} seconds...`);
                
                setTimeout(() => {
                    this.loadContent();
                }, this.retryDelay);
                
                return;
            }

            // Max attempts reached
            this.showError('Unable to load content. Please refresh the page.');
        }

        showError(message) {
            const errorHtml = `
                <div class="zippicks-error">
                    <div class="error-icon">⚠️</div>
                    <h3>Content Unavailable</h3>
                    <p>${this.escapeHtml(message)}</p>
                    <button class="retry-button">Try Again</button>
                    <p class="error-help">
                        If this problem persists, please 
                        <a href="#" onclick="window.location.reload()">refresh the page</a>
                    </p>
                </div>
            `;
            
            $(this.contentContainer).html(errorHtml);
            this.bindRetryButton();
        }

        updateLoadingState(message) {
            const loadingText = $('.zp-loading-text');
            if (loadingText.length) {
                loadingText.text(message);
            }
        }

        bindRetryButton() {
            $('.retry-button').off('click').on('click', (e) => {
                e.preventDefault();
                this.loadingAttempts = 0; // Reset attempts
                this.loadContent();
            });
        }

        updateSchema(data) {
            try {
                const schema = {
                    "@context": "https://schema.org",
                    "@type": "ItemList",
                    "name": data.title,
                    "description": data.excerpt || `Curated recommendations by ZipPicks`,
                    "url": window.location.href,
                    "datePublished": data.date,
                    "publisher": {
                        "@type": "Organization",
                        "name": "ZipPicks",
                        "url": "https://zippicks.com"
                    },
                    "numberOfItems": data.businesses ? data.businesses.length : 0,
                    "itemListOrder": "https://schema.org/ItemListOrderAscending"
                };

                // Add businesses to schema
                if (data.businesses && data.businesses.length > 0) {
                    schema.itemListElement = data.businesses.map((business, index) => ({
                        "@type": "ListItem",
                        "position": index + 1,
                        "item": {
                            "@type": "LocalBusiness",
                            "name": business.name,
                            "description": business.summary,
                            "aggregateRating": business.score ? {
                                "@type": "AggregateRating",
                                "ratingValue": business.score,
                                "bestRating": "10",
                                "worstRating": "0"
                            } : undefined
                        }
                    }));
                }

                // Update or create schema script
                $('script[type="application/ld+json"]:contains("ItemList")').remove();
                $('head').append(`<script type="application/ld+json">${JSON.stringify(schema)}</script>`);

            } catch (error) {
                console.warn('ZipPicks: Schema update failed', error);
            }
        }

        trackSuccessfulLoad() {
            // Track successful content load
            if (typeof gtag !== 'undefined') {
                gtag('event', 'content_load', {
                    'event_category': 'ZipPicks',
                    'event_label': 'Master Critic List',
                    'value': this.listId
                });
            }

            // Custom tracking hook
            $(document).trigger('zippicks:load-success', {
                listId: this.listId,
                loadTime: Date.now()
            });
        }

        trackPageView() {
            // Track page view
            if (typeof gtag !== 'undefined') {
                gtag('event', 'page_view', {
                    'page_title': document.title,
                    'page_location': window.location.href,
                    'custom_parameter': {
                        'zippicks_list_id': this.listId
                    }
                });
            }
        }

        handleShare(button) {
            const shareData = {
                title: document.title,
                text: 'Check out this curated list from ZipPicks',
                url: window.location.href
            };

            // Enhanced sharing with platform detection
            const platform = button.data('platform');
            
            if (platform) {
                this.shareOnPlatform(platform, shareData);
            } else if (navigator.share) {
                navigator.share(shareData).catch(console.error);
            } else {
                this.showAdvancedShareDialog(shareData);
            }
        }
        
        shareOnPlatform(platform, shareData) {
            const encodedUrl = encodeURIComponent(shareData.url);
            const encodedText = encodeURIComponent(shareData.text);
            const encodedTitle = encodeURIComponent(shareData.title);
            
            const shareUrls = {
                twitter: `https://twitter.com/intent/tweet?text=${encodedText}&url=${encodedUrl}`,
                facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
                linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
                reddit: `https://reddit.com/submit?url=${encodedUrl}&title=${encodedTitle}`,
                whatsapp: `https://wa.me/?text=${encodedText}%20${encodedUrl}`,
                telegram: `https://t.me/share/url?url=${encodedUrl}&text=${encodedText}`
            };
            
            if (shareUrls[platform]) {
                window.open(shareUrls[platform], '_blank', 'width=600,height=400');
                this.trackShare(platform);
            }
        }
        
        showAdvancedShareDialog(shareData) {
            const $dialog = $(`
                <div class="zippicks-share-dialog">
                    <div class="share-dialog-content">
                        <h3>Share this list</h3>
                        <div class="share-buttons">
                            <button class="share-btn twitter" data-platform="twitter">
                                <span class="icon">🐦</span> Twitter
                            </button>
                            <button class="share-btn facebook" data-platform="facebook">
                                <span class="icon">📘</span> Facebook
                            </button>
                            <button class="share-btn linkedin" data-platform="linkedin">
                                <span class="icon">💼</span> LinkedIn
                            </button>
                            <button class="share-btn whatsapp" data-platform="whatsapp">
                                <span class="icon">💬</span> WhatsApp
                            </button>
                        </div>
                        <div class="share-link">
                            <input type="text" value="${shareData.url}" readonly>
                            <button class="copy-link-btn">Copy Link</button>
                        </div>
                        <button class="close-dialog">✕</button>
                    </div>
                </div>
            `);
            
            $('body').append($dialog);
            
            // Bind events
            $dialog.find('[data-platform]').on('click', (e) => {
                const platform = $(e.currentTarget).data('platform');
                this.shareOnPlatform(platform, shareData);
                $dialog.remove();
            });
            
            $dialog.find('.copy-link-btn').on('click', () => {
                navigator.clipboard.writeText(shareData.url).then(() => {
                    this.showToast('Link copied to clipboard!');
                    $dialog.remove();
                });
            });
            
            $dialog.find('.close-dialog, .zippicks-share-dialog').on('click', (e) => {
                if (e.target === e.currentTarget) {
                    $dialog.remove();
                }
            });
        }

        handleFavorite(button) {
            // Placeholder for favorite functionality
            button.toggleClass('favorited');
            this.showToast(button.hasClass('favorited') ? 'Added to favorites' : 'Removed from favorites');
        }

        showToast(message) {
            const toast = $(`<div class="zippicks-toast">${this.escapeHtml(message)}</div>`);
            $('body').append(toast);
            
            setTimeout(() => {
                toast.addClass('show');
            }, 100);
            
            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // ===== ADVANCED INTERACTIVE FEATURES =====
        
        setupKeyboardNavigation() {
            $(document).on('keydown', (e) => {
                if (!this.keyboardNavigationActive) return;
                
                const $businessCards = $('.zippicks-business-card');
                if ($businessCards.length === 0) return;
                
                switch (e.which) {
                    case 38: // Up arrow
                        e.preventDefault();
                        this.navigateToBusinessCard(this.currentBusinessIndex - 1, $businessCards);
                        break;
                    case 40: // Down arrow
                        e.preventDefault();
                        this.navigateToBusinessCard(this.currentBusinessIndex + 1, $businessCards);
                        break;
                    case 13: // Enter
                        e.preventDefault();
                        this.activateCurrentBusinessCard($businessCards);
                        break;
                    case 27: // Escape
                        e.preventDefault();
                        this.exitKeyboardNavigation();
                        break;
                    case 83: // S key
                        if (e.ctrlKey || e.metaKey) {
                            e.preventDefault();
                            this.shareCurrentBusiness($businessCards);
                        }
                        break;
                }
            });
            
            // Activate keyboard navigation on tab or focus
            $(document).on('keydown', (e) => {
                if (e.which === 9 && $('.zippicks-business-card').length > 0) { // Tab key
                    this.activateKeyboardNavigation();
                }
            });
        }
        
        activateKeyboardNavigation() {
            if (this.keyboardNavigationActive) return;
            
            this.keyboardNavigationActive = true;
            this.currentBusinessIndex = 0;
            
            const $businessCards = $('.zippicks-business-card');
            if ($businessCards.length > 0) {
                this.highlightBusinessCard($businessCards.eq(0));
                this.showToast('Keyboard navigation active. Use ↑/↓ arrows, Enter to select, Esc to exit');
            }
        }
        
        navigateToBusinessCard(newIndex, $businessCards) {
            const maxIndex = $businessCards.length - 1;
            newIndex = Math.max(0, Math.min(newIndex, maxIndex));
            
            if (newIndex !== this.currentBusinessIndex) {
                this.unhighlightBusinessCard($businessCards.eq(this.currentBusinessIndex));
                this.currentBusinessIndex = newIndex;
                this.highlightBusinessCard($businessCards.eq(newIndex));
                this.scrollToBusinessCard($businessCards.eq(newIndex));
            }
        }
        
        highlightBusinessCard($card) {
            $card.addClass('zp-keyboard-focus').attr('tabindex', '0').focus();
        }
        
        unhighlightBusinessCard($card) {
            $card.removeClass('zp-keyboard-focus').removeAttr('tabindex');
        }
        
        scrollToBusinessCard($card) {
            $('html, body').animate({
                scrollTop: $card.offset().top - 100
            }, 200);
        }
        
        exitKeyboardNavigation() {
            this.keyboardNavigationActive = false;
            $('.zippicks-business-card').removeClass('zp-keyboard-focus').removeAttr('tabindex');
        }
        
        setupMobileGestures() {
            $(document).on('touchstart', this.contentContainer, (e) => {
                this.touchStartY = e.originalEvent.touches[0].clientY;
            });
            
            $(document).on('touchend', this.contentContainer, (e) => {
                this.touchEndY = e.originalEvent.changedTouches[0].clientY;
                this.handleSwipeGesture();
            });
            
            // Pull to refresh functionality
            let startY = 0;
            let currentY = 0;
            let isRefreshing = false;
            
            $(window).on('touchstart', (e) => {
                if ($(window).scrollTop() === 0) {
                    startY = e.originalEvent.touches[0].clientY;
                }
            });
            
            $(window).on('touchmove', (e) => {
                if ($(window).scrollTop() === 0 && !isRefreshing) {
                    currentY = e.originalEvent.touches[0].clientY;
                    const pullDistance = currentY - startY;
                    
                    if (pullDistance > 100) {
                        this.showPullToRefreshIndicator();
                    }
                }
            });
            
            $(window).on('touchend', (e) => {
                if ($(window).scrollTop() === 0 && !isRefreshing) {
                    const pullDistance = currentY - startY;
                    
                    if (pullDistance > 100) {
                        this.refreshContent();
                    } else {
                        this.hidePullToRefreshIndicator();
                    }
                }
            });
        }
        
        handleSwipeGesture() {
            const swipeDistance = this.touchStartY - this.touchEndY;
            
            if (Math.abs(swipeDistance) > this.swipeThreshold) {
                if (swipeDistance > 0) {
                    // Swipe up - next business
                    this.focusNextBusiness();
                } else {
                    // Swipe down - previous business
                    this.focusPreviousBusiness();
                }
            }
        }
        
        setupProgressiveImageLoading() {
            if ('IntersectionObserver' in window) {
                this.intersectionObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.loadImageProgressive(entry.target);
                            this.intersectionObserver.unobserve(entry.target);
                        }
                    });
                }, {
                    rootMargin: '50px'
                });
            }
        }
        
        initializeProgressiveImages() {
            const $lazyImages = $('img[data-src], .lazy-bg[data-bg]');
            
            $lazyImages.each((index, element) => {
                if (this.intersectionObserver) {
                    this.intersectionObserver.observe(element);
                } else {
                    // Fallback for older browsers
                    this.loadImageProgressive(element);
                }
            });
        }
        
        loadImageProgressive(element) {
            const $element = $(element);
            
            if ($element.is('img') && $element.data('src')) {
                const src = $element.data('src');
                
                if (!this.prefetchedImages.has(src)) {
                    const img = new Image();
                    img.onload = () => {
                        $element.attr('src', src).addClass('zp-image-loaded');
                        this.prefetchedImages.add(src);
                    };
                    img.src = src;
                }
            } else if ($element.hasClass('lazy-bg') && $element.data('bg')) {
                const bgUrl = $element.data('bg');
                
                if (!this.prefetchedImages.has(bgUrl)) {
                    const img = new Image();
                    img.onload = () => {
                        $element.css('background-image', `url(${bgUrl})`).addClass('zp-bg-loaded');
                        this.prefetchedImages.add(bgUrl);
                    };
                    img.src = bgUrl;
                }
            }
        }
        
        preloadCriticalAssets() {
            // Preload critical CSS for animations
            const criticalCSS = `
                .zp-transitioning { transition: opacity ${this.animationDuration}ms ease; }
                .zp-keyboard-focus { outline: 2px solid #667eea; outline-offset: 2px; }
                .zippicks-business-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
                .zippicks-business-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
                .zp-image-loaded { opacity: 1; transition: opacity 0.3s ease; }
                .zp-bg-loaded { opacity: 1; transition: opacity 0.3s ease; }
                .zippicks-share-dialog { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 9999; }
                .share-dialog-content { background: white; padding: 30px; border-radius: 12px; max-width: 400px; width: 90%; }
                .share-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 20px 0; }
                .share-btn { padding: 12px; border: 1px solid #ddd; background: white; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
                .share-btn:hover { background: #f5f5f5; }
                .zippicks-toast { position: fixed; bottom: 20px; right: 20px; background: #333; color: white; padding: 12px 20px; border-radius: 6px; z-index: 1000; opacity: 0; transform: translateY(20px); transition: all 0.3s ease; }
                .zippicks-toast.show { opacity: 1; transform: translateY(0); }
            `;
            
            const $style = $(`<style type="text/css">${criticalCSS}</style>`);
            $('head').append($style);
        }
        
        setupBusinessCardAnimations() {
            $('.zippicks-business-card').each((index, card) => {
                const $card = $(card);
                
                // Hover effects
                $card.on('mouseenter', () => {
                    $card.addClass('zp-card-hover');
                });
                
                $card.on('mouseleave', () => {
                    $card.removeClass('zp-card-hover');
                });
                
                // Click to focus
                $card.on('click', () => {
                    this.currentBusinessIndex = index;
                    this.highlightBusinessCard($card);
                });
            });
        }
        
        trackShare(platform) {
            if (typeof gtag !== 'undefined') {
                gtag('event', 'share', {
                    'method': platform,
                    'content_type': 'zippicks_list',
                    'content_id': this.listId
                });
            }
        }
        
        refreshContent() {
            this.showToast('Refreshing content...');
            this.loadingAttempts = 0;
            this.loadContent();
        }
        
        showPullToRefreshIndicator() {
            // Implementation for pull-to-refresh visual indicator
        }
        
        hidePullToRefreshIndicator() {
            // Implementation to hide pull-to-refresh indicator
        }
        
        focusNextBusiness() {
            const $businessCards = $('.zippicks-business-card');
            if ($businessCards.length > 0) {
                this.navigateToBusinessCard(this.currentBusinessIndex + 1, $businessCards);
            }
        }
        
        focusPreviousBusiness() {
            const $businessCards = $('.zippicks-business-card');
            if ($businessCards.length > 0) {
                this.navigateToBusinessCard(this.currentBusinessIndex - 1, $businessCards);
            }
        }
        
        activateCurrentBusinessCard($businessCards) {
            const $currentCard = $businessCards.eq(this.currentBusinessIndex);
            if ($currentCard.length) {
                $currentCard.find('.share-button').first().click();
            }
        }
        
        shareCurrentBusiness($businessCards) {
            const $currentCard = $businessCards.eq(this.currentBusinessIndex);
            if ($currentCard.length) {
                const businessName = $currentCard.find('.business-name').text() || 'this business';
                const shareData = {
                    title: `${businessName} | ZipPicks`,
                    text: `Check out ${businessName} on ZipPicks`,
                    url: window.location.href
                };
                this.showAdvancedShareDialog(shareData);
            }
        }
    }

    // Initialize when DOM is ready
    $(document).ready(() => {
        new ZipPicksListLoader();
    });

    // Make available globally for debugging
    window.ZipPicksListLoader = ZipPicksListLoader;

})(jQuery);