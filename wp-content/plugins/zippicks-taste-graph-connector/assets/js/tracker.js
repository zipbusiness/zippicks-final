/**
 * Taste Graph Tracker
 * 
 * Frontend JavaScript for tracking user interactions with restaurants
 * and vibes to build personalized taste profiles
 * 
 * @package TasteGraphConnector
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * TasteGraphTracker class
     */
    class TasteGraphTracker {
        constructor() {
            this.sessionId = this.getSessionId();
            this.apiEndpoint = tgc_ajax.api_url;
            this.ajaxUrl = tgc_ajax.ajax_url;
            this.nonce = tgc_ajax.nonce;
            this.userId = parseInt(tgc_ajax.user_id) || null;
            this.isLoggedIn = tgc_ajax.is_logged_in === '1';
            this.debugMode = tgc_ajax.debug_mode === true;
            
            // Tracking state
            this.pageStartTime = Date.now();
            this.lastScrollDepth = 0;
            this.interactionQueue = [];
            this.isProcessingQueue = false;
            
            // Queue management
            this.MAX_QUEUE_SIZE = 100; // Maximum number of interactions to store
            
            // Initialize
            this.init();
        }
        
        /**
         * Initialize tracker
         */
        init() {
            // Set up event listeners
            this.setupEventListeners();
            
            // Track page view
            this.trackPageView();
            
            // Set up visibility API tracking
            this.setupVisibilityTracking();
            
            // Set up scroll tracking
            this.setupScrollTracking();
            
            // Process any queued interactions every 5 seconds
            setInterval(() => this.processQueue(), 5000);
            
            // Send time on page when leaving
            window.addEventListener('beforeunload', () => this.trackTimeOnPage());
            
            if (this.debugMode) {
                console.log('TasteGraphTracker initialized', {
                    sessionId: this.sessionId,
                    userId: this.userId,
                    isLoggedIn: this.isLoggedIn
                });
            }
        }
        
        /**
         * Get or generate session ID
         */
        getSessionId() {
            // Check if already available from TGC_Session
            if (window.TGC_SESSION_ID) {
                return window.TGC_SESSION_ID;
            }
            
            // Check localStorage
            let sessionId = localStorage.getItem('tgc_session_id');
            
            if (!sessionId || !this.validateSessionId(sessionId)) {
                sessionId = this.generateSessionId();
                localStorage.setItem('tgc_session_id', sessionId);
            }
            
            return sessionId;
        }
        
        /**
         * Generate session ID
         */
        generateSessionId() {
            const timestamp = Date.now();
            const random = Math.random().toString(36).substr(2, 9);
            return `anon_${timestamp}_${random}`;
        }
        
        /**
         * Validate session ID format
         */
        validateSessionId(sessionId) {
            return /^anon_\d{13}_[a-z0-9]{9}$/.test(sessionId);
        }
        
        /**
         * Set up event listeners
         */
        setupEventListeners() {
            // Restaurant interactions
            $(document).on('click', '[data-tgc-restaurant]', (e) => {
                const zpid = $(e.currentTarget).data('tgc-restaurant');
                this.trackRestaurantClick(zpid);
            });
            
            // Vibe interactions
            $(document).on('click', '[data-tgc-vibe]', (e) => {
                const vibeId = $(e.currentTarget).data('tgc-vibe');
                this.trackVibeClick(vibeId);
            });
            
            // Save/unsave buttons
            $(document).on('click', '[data-tgc-save]', (e) => {
                const zpid = $(e.currentTarget).data('tgc-save');
                const isSaved = $(e.currentTarget).hasClass('saved');
                
                if (isSaved) {
                    this.trackRestaurantUnsave(zpid);
                } else {
                    this.trackRestaurantSave(zpid);
                }
            });
            
            // Share buttons
            $(document).on('click', '[data-tgc-share]', (e) => {
                const zpid = $(e.currentTarget).data('tgc-share');
                this.trackRestaurantShare(zpid);
            });
            
            // Search form
            $(document).on('submit', '[data-tgc-search]', (e) => {
                const query = $(e.currentTarget).find('input[type="search"], input[type="text"]').val();
                this.trackSearch(query);
            });
            
            // Filter changes
            $(document).on('change', '[data-tgc-filter]', (e) => {
                const filterType = $(e.currentTarget).data('tgc-filter');
                const filterValue = $(e.currentTarget).val();
                this.trackFilterApply(filterType, filterValue);
            });
        }
        
        /**
         * Track page view
         */
        trackPageView() {
            const pageData = {
                page_type: this.detectPageType(),
                page_title: document.title,
                page_path: window.location.pathname
            };
            
            this.track('page_view', pageData);
        }
        
        /**
         * Track time on page
         */
        trackTimeOnPage() {
            const timeSpent = Math.floor((Date.now() - this.pageStartTime) / 1000);
            
            if (timeSpent > 3) { // Only track if more than 3 seconds
                this.track('time_on_page', {
                    time_spent: timeSpent,
                    scroll_depth: this.lastScrollDepth
                });
            }
        }
        
        /**
         * Track restaurant view
         */
        trackRestaurantView(zpid) {
            this.track('restaurant_view', {
                restaurant_zpid: zpid
            });
        }
        
        /**
         * Track restaurant click
         */
        trackRestaurantClick(zpid) {
            this.track('restaurant_click', {
                restaurant_zpid: zpid
            });
        }
        
        /**
         * Track restaurant save
         */
        trackRestaurantSave(zpid) {
            this.track('restaurant_save', {
                restaurant_zpid: zpid
            });
        }
        
        /**
         * Track restaurant unsave
         */
        trackRestaurantUnsave(zpid) {
            this.track('restaurant_unsave', {
                restaurant_zpid: zpid
            });
        }
        
        /**
         * Track restaurant share
         */
        trackRestaurantShare(zpid) {
            this.track('restaurant_share', {
                restaurant_zpid: zpid
            });
        }
        
        /**
         * Track vibe view
         */
        trackVibeView(vibeId) {
            this.track('vibe_view', {
                vibe_id: vibeId
            });
        }
        
        /**
         * Track vibe click
         */
        trackVibeClick(vibeId) {
            this.track('vibe_click', {
                vibe_id: vibeId
            });
        }
        
        /**
         * Track vibe select
         */
        trackVibeSelect(vibeId) {
            this.track('vibe_select', {
                vibe_id: vibeId
            });
        }
        
        /**
         * Track search
         */
        trackSearch(query) {
            this.track('search', {
                search_query: query
            });
        }
        
        /**
         * Track filter apply
         */
        trackFilterApply(filterType, filterValue) {
            this.track('filter_apply', {
                filter_type: filterType,
                filter_value: filterValue
            });
        }
        
        /**
         * Track map interaction
         */
        trackMapInteraction(action, data) {
            this.track('map_interaction', {
                map_action: action,
                ...data
            });
        }
        
        /**
         * Core tracking method
         */
        track(interactionType, data = {}) {
            const interaction = {
                interaction_type: interactionType,
                session_id: this.sessionId,
                metadata: {
                    ...data,
                    timestamp: new Date().toISOString(),
                    page_url: window.location.href,
                    viewport_width: window.innerWidth,
                    viewport_height: window.innerHeight,
                    device_type: this.detectDeviceType()
                }
            };
            
            // Add vibe_id if present in data
            if (data.vibe_id) {
                interaction.vibe_id = parseInt(data.vibe_id);
            }
            
            // Add restaurant_zpid if present in data
            if (data.restaurant_zpid) {
                interaction.restaurant_zpid = data.restaurant_zpid;
            }
            
            // Add to queue with size limit enforcement
            if (this.interactionQueue.length >= this.MAX_QUEUE_SIZE) {
                // Remove oldest interaction to make room
                this.interactionQueue.shift();
                
                if (this.debugMode) {
                    console.warn('Interaction queue at capacity, removed oldest entry');
                }
            }
            
            this.interactionQueue.push(interaction);
            
            // Process immediately if not already processing
            if (!this.isProcessingQueue) {
                this.processQueue();
            }
            
            if (this.debugMode) {
                console.log('Tracked interaction:', interaction);
            }
        }
        
        /**
         * Process interaction queue
         */
        async processQueue() {
            if (this.isProcessingQueue || this.interactionQueue.length === 0) {
                return;
            }
            
            this.isProcessingQueue = true;
            
            // Process up to 10 interactions at a time
            const batch = this.interactionQueue.splice(0, 10);
            
            for (const interaction of batch) {
                try {
                    await this.sendInteraction(interaction);
                } catch (error) {
                    // Re-add to queue on failure, respecting size limit
                    if (this.interactionQueue.length < this.MAX_QUEUE_SIZE) {
                        this.interactionQueue.push(interaction);
                    } else if (this.debugMode) {
                        console.warn('Failed interaction not re-queued due to size limit');
                    }
                    
                    if (this.debugMode) {
                        console.error('Failed to send interaction:', error);
                    }
                }
            }
            
            this.isProcessingQueue = false;
        }
        
        /**
         * Send interaction to server
         */
        sendInteraction(interaction) {
            return new Promise((resolve, reject) => {
                $.post(this.ajaxUrl, {
                    action: 'tgc_track_interaction',
                    nonce: this.nonce,
                    data: interaction
                })
                .done(response => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data || 'Unknown error'));
                    }
                })
                .fail((xhr, status, error) => {
                    reject(new Error(`AJAX error: ${status} - ${error}`));
                });
            });
        }
        
        /**
         * Set up visibility tracking
         */
        setupVisibilityTracking() {
            let hiddenTime = null;
            
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    hiddenTime = Date.now();
                } else if (hiddenTime) {
                    // Adjust page start time to exclude hidden time
                    const hiddenDuration = Date.now() - hiddenTime;
                    this.pageStartTime += hiddenDuration;
                    hiddenTime = null;
                }
            });
        }
        
        /**
         * Set up scroll tracking
         */
        setupScrollTracking() {
            let scrollTimer = null;
            
            window.addEventListener('scroll', () => {
                // Debounce scroll events
                clearTimeout(scrollTimer);
                
                scrollTimer = setTimeout(() => {
                    const scrollDepth = this.calculateScrollDepth();
                    
                    // Track significant scroll depth increases
                    if (scrollDepth > this.lastScrollDepth && scrollDepth % 25 === 0) {
                        this.track('list_scroll', {
                            scroll_depth: scrollDepth
                        });
                    }
                    
                    this.lastScrollDepth = scrollDepth;
                }, 150);
            });
        }
        
        /**
         * Calculate scroll depth percentage
         */
        calculateScrollDepth() {
            const windowHeight = window.innerHeight;
            const documentHeight = document.documentElement.scrollHeight;
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            const scrollPercent = (scrollTop + windowHeight) / documentHeight * 100;
            return Math.min(100, Math.round(scrollPercent / 25) * 25);
        }
        
        /**
         * Detect page type
         */
        detectPageType() {
            const path = window.location.pathname;
            const body = document.body;
            
            if (path === '/' || body.classList.contains('home')) {
                return 'home';
            } else if (path.includes('/restaurant/') || body.classList.contains('single-restaurant')) {
                return 'restaurant_detail';
            } else if (path.includes('/search') || body.classList.contains('search-results')) {
                return 'search_results';
            } else if (path.includes('/vibes') || body.classList.contains('vibes-page')) {
                return 'vibes_browse';
            } else if (path.includes('/map') || body.classList.contains('map-view')) {
                return 'map_view';
            } else if (path.includes('/profile') || body.classList.contains('user-profile')) {
                return 'user_profile';
            } else {
                return 'other';
            }
        }
        
        /**
         * Detect device type
         */
        detectDeviceType() {
            const width = window.innerWidth;
            
            if (width < 768) {
                return 'mobile';
            } else if (width < 1024) {
                return 'tablet';
            } else {
                return 'desktop';
            }
        }
        
        /**
         * Get user taste profile
         */
        getTasteProfile() {
            if (!this.isLoggedIn) {
                return Promise.reject(new Error('User not logged in'));
            }
            
            return new Promise((resolve, reject) => {
                $.post(this.ajaxUrl, {
                    action: 'tgc_get_taste_profile',
                    nonce: this.nonce
                })
                .done(response => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data || 'Failed to get taste profile'));
                    }
                })
                .fail((xhr, status, error) => {
                    reject(new Error(`AJAX error: ${status} - ${error}`));
                });
            });
        }
    }
    
    // Initialize tracker when DOM is ready
    $(document).ready(() => {
        window.TasteGraphTracker = new TasteGraphTracker();
    });
    
})(jQuery);