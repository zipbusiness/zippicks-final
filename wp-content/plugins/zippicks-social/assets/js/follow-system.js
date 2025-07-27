/**
 * ZipPicks Social Follow System
 * 
 * @package ZipPicks_Social
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Follow System Manager
     */
    const ZipPicksFollowSystem = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initializeButtons();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Follow/unfollow button clicks
            $(document).on('click', '.zps-follow-button', this.handleFollowClick.bind(this));
            
            // Hover effects for following state
            $(document).on('mouseenter', '.zps-follow-button.zps-following', function() {
                const $button = $(this);
                const originalText = $button.find('.zps-button-text').text();
                $button.data('original-text', originalText);
                $button.find('.zps-button-text').text(zippicksSocial.strings.unfollow);
                $button.addClass('zps-hover-unfollow');
            });
            
            $(document).on('mouseleave', '.zps-follow-button.zps-following', function() {
                const $button = $(this);
                const originalText = $button.data('original-text') || zippicksSocial.strings.following;
                $button.find('.zps-button-text').text(originalText);
                $button.removeClass('zps-hover-unfollow');
            });
        },
        
        /**
         * Initialize follow buttons
         */
        initializeButtons: function() {
            // Load initial states for visible buttons
            $('.zps-follow-button').each(function() {
                const $button = $(this);
                const entityId = $button.data('entity-id');
                const entityType = $button.data('entity-type');
                
                // Skip if already initialized
                if ($button.data('initialized')) {
                    return;
                }
                
                $button.data('initialized', true);
                
                // Check follow status if user is logged in
                if (zippicksSocial.isLoggedIn && entityId) {
                    ZipPicksFollowSystem.checkFollowStatus($button);
                }
            });
        },
        
        /**
         * Handle follow button click
         */
        handleFollowClick: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const action = $button.data('action');
            const entityId = $button.data('entity-id');
            const entityType = $button.data('entity-type');
            
            // Check if user is logged in
            if (!zippicksSocial.isLoggedIn) {
                alert(zippicksSocial.strings.loginRequired);
                return;
            }
            
            // Prevent double clicks
            if ($button.hasClass('zps-loading')) {
                return;
            }
            
            // Set loading state
            this.setButtonLoading($button, true);
            
            // Perform action
            if (action === 'follow') {
                this.follow($button, entityId, entityType);
            } else {
                this.unfollow($button, entityId, entityType);
            }
        },
        
        /**
         * Follow entity
         */
        follow: function($button, entityId, entityType) {
            // Use REST API if available, fallback to AJAX
            if (window.fetch && zippicksSocial.restUrl) {
                fetch(zippicksSocial.restUrl + 'follow', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': zippicksSocial.restNonce
                    },
                    body: JSON.stringify({
                        entity_id: entityId,
                        entity_type: entityType
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.updateButtonState($button, 'following', data.followers_count);
                        this.triggerEvent('follow', {entityId, entityType, data});
                    } else {
                        this.showError($button, data.message);
                    }
                })
                .catch(error => {
                    console.error('Follow error:', error);
                    this.fallbackToAjax($button, 'zippicks_follow', entityId, entityType);
                })
                .finally(() => {
                    this.setButtonLoading($button, false);
                });
            } else {
                this.fallbackToAjax($button, 'zippicks_follow', entityId, entityType);
            }
        },
        
        /**
         * Unfollow entity
         */
        unfollow: function($button, entityId, entityType) {
            // Use REST API if available, fallback to AJAX
            if (window.fetch && zippicksSocial.restUrl) {
                fetch(zippicksSocial.restUrl + 'unfollow', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': zippicksSocial.restNonce
                    },
                    body: JSON.stringify({
                        entity_id: entityId,
                        entity_type: entityType
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.updateButtonState($button, 'not-following', data.followers_count);
                        this.triggerEvent('unfollow', {entityId, entityType, data});
                    } else {
                        this.showError($button, data.message);
                    }
                })
                .catch(error => {
                    console.error('Unfollow error:', error);
                    this.fallbackToAjax($button, 'zippicks_unfollow', entityId, entityType);
                })
                .finally(() => {
                    this.setButtonLoading($button, false);
                });
            } else {
                this.fallbackToAjax($button, 'zippicks_unfollow', entityId, entityType);
            }
        },
        
        /**
         * Fallback to AJAX
         */
        fallbackToAjax: function($button, action, entityId, entityType) {
            $.ajax({
                url: zippicksSocial.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    entity_id: entityId,
                    entity_type: entityType,
                    nonce: $button.data('nonce') || zippicksSocial.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const state = action === 'zippicks_follow' ? 'following' : 'not-following';
                        this.updateButtonState($button, state, response.data.followers_count);
                        this.triggerEvent(action.replace('zippicks_', ''), {
                            entityId, 
                            entityType, 
                            data: response.data
                        });
                    } else {
                        this.showError($button, response.data.message);
                    }
                },
                error: () => {
                    this.showError($button, zippicksSocial.strings.error);
                },
                complete: () => {
                    this.setButtonLoading($button, false);
                }
            });
        },
        
        /**
         * Check follow status
         */
        checkFollowStatus: function($button) {
            const entityId = $button.data('entity-id');
            const entityType = $button.data('entity-type');
            
            $.ajax({
                url: zippicksSocial.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'zippicks_check_follow_status',
                    entity_id: entityId,
                    entity_type: entityType
                },
                success: (response) => {
                    if (response.success) {
                        const state = response.data.is_following ? 'following' : 'not-following';
                        this.updateButtonState($button, state, response.data.followers_count, false);
                    }
                }
            });
        },
        
        /**
         * Update button state
         */
        updateButtonState: function($button, state, followersCount, animate = true) {
            const isFollowing = state === 'following';
            const buttonText = isFollowing ? zippicksSocial.strings.following : zippicksSocial.strings.follow;
            const action = isFollowing ? 'unfollow' : 'follow';
            
            // Update button
            $button
                .data('action', action)
                .toggleClass('zps-following', isFollowing)
                .toggleClass('zps-not-following', !isFollowing);
            
            // Update text
            $button.find('.zps-button-text').text(buttonText);
            
            // Update count if present
            const $count = $button.find('.zps-followers-count');
            if ($count.length && followersCount !== undefined) {
                if (animate) {
                    $count.fadeOut(150, function() {
                        $(this).text(followersCount > 0 ? followersCount.toLocaleString() : '');
                        if (followersCount > 0) {
                            $(this).fadeIn(150);
                        }
                    });
                } else {
                    $count.text(followersCount > 0 ? followersCount.toLocaleString() : '');
                    $count.toggle(followersCount > 0);
                }
            }
            
            // Update all buttons for the same entity
            $(`.zps-follow-button[data-entity-id="${$button.data('entity-id')}"][data-entity-type="${$button.data('entity-type')}"]`)
                .not($button)
                .each(function() {
                    ZipPicksFollowSystem.updateButtonState($(this), state, followersCount, false);
                });
        },
        
        /**
         * Set button loading state
         */
        setButtonLoading: function($button, loading) {
            $button.toggleClass('zps-loading', loading);
            $button.prop('disabled', loading);
            
            if (loading) {
                const $text = $button.find('.zps-button-text');
                $text.data('original-text', $text.text());
                $text.text(zippicksSocial.strings.loading);
            } else {
                const $text = $button.find('.zps-button-text');
                const originalText = $text.data('original-text');
                if (originalText) {
                    $text.text(originalText);
                }
            }
        },
        
        /**
         * Show error message
         */
        showError: function($button, message) {
            // Create error tooltip
            const $error = $('<div class="zps-error-tooltip">')
                .text(message)
                .appendTo('body');
            
            // Position near button
            const offset = $button.offset();
            $error.css({
                top: offset.top - $error.outerHeight() - 10,
                left: offset.left + ($button.outerWidth() / 2) - ($error.outerWidth() / 2)
            });
            
            // Show and hide
            $error.fadeIn(200);
            setTimeout(() => {
                $error.fadeOut(200, function() {
                    $(this).remove();
                });
            }, 3000);
        },
        
        /**
         * Trigger custom event
         */
        triggerEvent: function(eventName, data) {
            $(document).trigger('zippicks:' + eventName, data);
        }
    };
    
    /**
     * Activity Feed Manager
     */
    const ZipPicksActivityFeed = {
        
        /**
         * Initialize
         */
        init: function() {
            this.initFeeds();
        },
        
        /**
         * Initialize activity feeds
         */
        initFeeds: function() {
            $('.zps-activity-feed').each(function() {
                const $feed = $(this);
                const userId = $feed.data('user-id');
                
                if (userId && !$feed.data('initialized')) {
                    $feed.data('initialized', true);
                    ZipPicksActivityFeed.loadFeed($feed, userId);
                }
            });
        },
        
        /**
         * Load activity feed
         */
        loadFeed: function($feed, userId) {
            // Placeholder - will be implemented in phase 2
            setTimeout(() => {
                $feed.find('.zps-feed-loading').hide();
                $feed.find('.zps-feed-empty').show();
            }, 1000);
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        ZipPicksFollowSystem.init();
        ZipPicksActivityFeed.init();
    });
    
    /**
     * Re-initialize on AJAX content load
     */
    $(document).on('zippicks:content-loaded', function() {
        ZipPicksFollowSystem.initializeButtons();
        ZipPicksActivityFeed.initFeeds();
    });

})(jQuery);