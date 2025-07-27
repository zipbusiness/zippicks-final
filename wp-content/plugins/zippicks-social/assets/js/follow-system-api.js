/**
 * ZipPicks Follow System - Real API Implementation
 * 
 * @package ZipPicks_Social
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Main Follow System Manager
     */
    const ZipPicksFollowSystem = {
        
        /**
         * API endpoints
         */
        endpoints: {
            follow: zippicks_social_ajax.rest_url + 'zippicks-social/v1/follow',
            unfollow: zippicks_social_ajax.rest_url + 'zippicks-social/v1/unfollow',
            isFollowing: zippicks_social_ajax.rest_url + 'zippicks-social/v1/is-following',
            suggestions: zippicks_social_ajax.rest_url + 'zippicks-social/v1/suggestions',
            activityFeed: zippicks_social_ajax.rest_url + 'zippicks-social/v1/activity-feed',
            stats: zippicks_social_ajax.rest_url + 'zippicks-social/v1/stats'
        },
        
        /**
         * Initialize the follow system
         */
        init: function() {
            this.bindEvents();
            this.initializeButtons();
            this.loadDynamicCounts();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Follow/unfollow button clicks
            $(document).on('click', '.zps-follow-button', this.handleFollowClick.bind(this));
            
            // Dismiss suggestion
            $(document).on('click', '.zps-dismiss-suggestion', this.handleDismissSuggestion.bind(this));
            
            // Load more activities
            $(document).on('click', '.zps-load-more', this.handleLoadMore.bind(this));
            
            // Bulk follow
            $(document).on('click', '.zps-bulk-follow', this.handleBulkFollow.bind(this));
        },
        
        /**
         * Initialize follow buttons
         */
        initializeButtons: function() {
            $('.zps-follow-button').each(function() {
                const $button = $(this);
                const entityId = $button.data('entity-id');
                const entityType = $button.data('entity-type');
                
                // Check follow status
                ZipPicksFollowSystem.checkFollowStatus($button, entityId, entityType);
            });
        },
        
        /**
         * Handle follow/unfollow click
         */
        handleFollowClick: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const entityId = $button.data('entity-id');
            const entityType = $button.data('entity-type');
            const isFollowing = $button.data('following') === 'true';
            
            // Prevent double-clicks
            if ($button.hasClass('zps-loading')) {
                return;
            }
            
            // Add loading state
            $button.addClass('zps-loading');
            const originalText = $button.find('.zps-button-text').text();
            $button.find('.zps-button-text').text(zippicks_social_ajax.strings.loading);
            
            // Make API request
            const endpoint = isFollowing ? this.endpoints.unfollow : this.endpoints.follow;
            
            $.ajax({
                url: endpoint,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': zippicks_social_ajax.nonce
                },
                data: JSON.stringify({
                    entity_id: entityId,
                    entity_type: entityType
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        // Update button state
                        const newFollowing = !isFollowing;
                        $button.data('following', newFollowing ? 'true' : 'false');
                        $button.toggleClass('zps-following', newFollowing);
                        
                        // Update button text
                        const newText = newFollowing 
                            ? zippicks_social_ajax.strings.following 
                            : zippicks_social_ajax.strings.follow;
                        $button.find('.zps-button-text').text(newText);
                        
                        // Update follower count
                        if (response.followers_count !== undefined) {
                            $button.find('.zps-follow-count').text(
                                ZipPicksFollowSystem.formatCount(response.followers_count)
                            );
                        }
                        
                        // Show success message
                        ZipPicksFollowSystem.showNotification(response.message, 'success');
                        
                        // Trigger custom event
                        $(document).trigger('zippicks:follow-changed', {
                            entityId: entityId,
                            entityType: entityType,
                            isFollowing: newFollowing
                        });
                    } else {
                        ZipPicksFollowSystem.showNotification(response.message, 'error');
                        $button.find('.zps-button-text').text(originalText);
                    }
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.message || zippicks_social_ajax.strings.error;
                    ZipPicksFollowSystem.showNotification(error, 'error');
                    $button.find('.zps-button-text').text(originalText);
                },
                complete: function() {
                    $button.removeClass('zps-loading');
                }
            });
        },
        
        /**
         * Check follow status for a button
         */
        checkFollowStatus: function($button, entityId, entityType) {
            if (!zippicks_social_ajax.user_id) {
                return; // Not logged in
            }
            
            $.ajax({
                url: this.endpoints.isFollowing,
                method: 'GET',
                data: {
                    entity_id: entityId,
                    entity_type: entityType
                },
                success: function(response) {
                    const isFollowing = response.is_following;
                    $button.data('following', isFollowing ? 'true' : 'false');
                    $button.toggleClass('zps-following', isFollowing);
                    
                    const text = isFollowing 
                        ? zippicks_social_ajax.strings.following 
                        : zippicks_social_ajax.strings.follow;
                    $button.find('.zps-button-text').text(text);
                }
            });
        },
        
        /**
         * Load dynamic follower counts
         */
        loadDynamicCounts: function() {
            const entities = [];
            
            $('.zps-follow-container').each(function() {
                const $container = $(this);
                entities.push({
                    id: $container.data('entity-id'),
                    type: $container.data('entity-type')
                });
            });
            
            if (entities.length === 0) {
                return;
            }
            
            // Batch request for stats
            entities.forEach(entity => {
                $.ajax({
                    url: `${this.endpoints.stats}/${entity.type}/${entity.id}`,
                    method: 'GET',
                    success: function(response) {
                        const $container = $(`.zps-follow-container[data-entity-id="${entity.id}"][data-entity-type="${entity.type}"]`);
                        const $count = $container.find('.zps-follow-count');
                        
                        if ($count.length && response.followers_count !== undefined) {
                            $count.text(ZipPicksFollowSystem.formatCount(response.followers_count));
                        }
                    }
                });
            });
        },
        
        /**
         * Format count for display
         */
        formatCount: function(count) {
            if (count >= 1000000) {
                return (count / 1000000).toFixed(1) + 'M';
            } else if (count >= 1000) {
                return (count / 1000).toFixed(1) + 'K';
            }
            return count.toString();
        },
        
        /**
         * Show notification
         */
        showNotification: function(message, type) {
            // Remove existing notifications
            $('.zps-notification').remove();
            
            const $notification = $('<div>')
                .addClass('zps-notification')
                .addClass('zps-notification-' + type)
                .text(message);
            
            $('body').append($notification);
            
            // Auto-hide after 3 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
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
                if (!$feed.data('initialized')) {
                    $feed.data('initialized', true);
                    
                    // Feed is already loaded server-side, just bind events
                    ZipPicksActivityFeed.bindFeedEvents($feed);
                }
            });
        },
        
        /**
         * Bind feed events
         */
        bindFeedEvents: function($feed) {
            // Load more button
            $feed.on('click', '.zps-load-more', function(e) {
                e.preventDefault();
                ZipPicksActivityFeed.loadMore($feed);
            });
            
            // Auto-refresh every 60 seconds
            if ($feed.data('auto-refresh') !== false) {
                setInterval(function() {
                    ZipPicksActivityFeed.refreshFeed($feed);
                }, 60000);
            }
        },
        
        /**
         * Load more activities
         */
        loadMore: function($feed) {
            const $button = $feed.find('.zps-load-more');
            const userId = $feed.data('user-id');
            const feedType = $feed.data('feed-type');
            const currentPage = parseInt($feed.data('page')) || 1;
            const perPage = $feed.data('per-page') || 20;
            
            // Add loading state
            $button.prop('disabled', true).text($button.data('loading-text'));
            
            $.ajax({
                url: ZipPicksFollowSystem.endpoints.activityFeed + '/' + userId,
                method: 'GET',
                data: {
                    page: currentPage + 1,
                    per_page: perPage,
                    type: feedType
                },
                success: function(response) {
                    if (response.activities && response.activities.length > 0) {
                        // Render new activities
                        const $content = $feed.find('.zps-feed-content');
                        response.activities.forEach(activity => {
                            const html = ZipPicksActivityFeed.renderActivity(activity);
                            $content.append(html);
                        });
                        
                        // Update page number
                        $feed.data('page', currentPage + 1);
                        
                        // Hide button if no more
                        if (!response.has_more) {
                            $button.hide();
                        }
                    } else {
                        $button.hide();
                    }
                },
                error: function() {
                    ZipPicksFollowSystem.showNotification(
                        zippicks_social_ajax.strings.load_error, 
                        'error'
                    );
                },
                complete: function() {
                    $button.prop('disabled', false).text(zippicks_social_ajax.strings.load_more);
                }
            });
        },
        
        /**
         * Render single activity
         */
        renderActivity: function(activity) {
            // Get template
            const template = $('#zps-activity-template').html();
            
            // Simple template replacement
            let html = template;
            
            // Replace all template variables
            html = html.replace(/\{\{id\}\}/g, activity.id);
            html = html.replace(/\{\{icon\}\}/g, activity.icon);
            html = html.replace(/\{\{time_ago\}\}/g, activity.time_ago);
            html = html.replace(/\{\{timestamp\}\}/g, new Date(activity.timestamp * 1000).toISOString());
            html = html.replace(/\{\{url\}\}/g, activity.url || '#');
            html = html.replace(/\{\{\{formatted_text\}\}\}/g, activity.formatted_text);
            
            // Handle conditional avatar
            if (activity.actor.avatar) {
                html = html.replace(/\{\{#if actor\.avatar\}\}[\s\S]*?\{\{else\}\}/g, '');
                html = html.replace(/\{\{else\}\}[\s\S]*?\{\{\/if\}\}/g, '');
                html = html.replace(/\{\{actor\.avatar\}\}/g, activity.actor.avatar);
                html = html.replace(/\{\{actor\.name\}\}/g, activity.actor.name);
            } else {
                html = html.replace(/\{\{#if actor\.avatar\}\}[\s\S]*?\{\{else\}\}/g, '');
                html = html.replace(/\{\{\/if\}\}/g, '');
            }
            
            // Handle conditional URL
            if (activity.url) {
                html = html.replace(/\{\{#if url\}\}/g, '');
                html = html.replace(/\{\{\/if\}\}/g, '');
            } else {
                html = html.replace(/\{\{#if url\}\}[\s\S]*?\{\{\/if\}\}/g, '');
            }
            
            return html;
        },
        
        /**
         * Refresh feed with new activities
         */
        refreshFeed: function($feed) {
            const userId = $feed.data('user-id');
            const feedType = $feed.data('feed-type');
            const $content = $feed.find('.zps-feed-content');
            const latestId = $content.find('.zps-activity-item:first').data('activity-id');
            
            $.ajax({
                url: ZipPicksFollowSystem.endpoints.activityFeed + '/' + userId,
                method: 'GET',
                data: {
                    page: 1,
                    per_page: 10,
                    type: feedType,
                    since_id: latestId
                },
                success: function(response) {
                    if (response.activities && response.activities.length > 0) {
                        // Prepend new activities
                        response.activities.reverse().forEach(activity => {
                            const html = ZipPicksActivityFeed.renderActivity(activity);
                            const $newItem = $(html).hide();
                            $content.prepend($newItem);
                            $newItem.slideDown();
                        });
                        
                        // Show notification for new activities
                        if (response.activities.length === 1) {
                            ZipPicksFollowSystem.showNotification(
                                'New activity in your feed',
                                'info'
                            );
                        } else {
                            ZipPicksFollowSystem.showNotification(
                                `${response.activities.length} new activities in your feed`,
                                'info'
                            );
                        }
                    }
                }
            });
        }
    };
    
    /**
     * Suggestions Manager
     */
    const ZipPicksSuggestions = {
        
        /**
         * Initialize
         */
        init: function() {
            this.loadSuggestions();
            this.bindEvents();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Dismiss suggestion
            $(document).on('click', '.zps-dismiss-suggestion', function(e) {
                e.preventDefault();
                const $suggestion = $(this).closest('.zps-suggestion-item');
                ZipPicksSuggestions.dismissSuggestion($suggestion);
            });
            
            // Refresh suggestions
            $(document).on('click', '.zps-refresh-suggestions', function(e) {
                e.preventDefault();
                ZipPicksSuggestions.refreshSuggestions();
            });
        },
        
        /**
         * Load suggestions
         */
        loadSuggestions: function() {
            const $container = $('.zps-suggestions-container');
            if (!$container.length || !zippicks_social_ajax.user_id) {
                return;
            }
            
            $.ajax({
                url: ZipPicksFollowSystem.endpoints.suggestions + '/' + zippicks_social_ajax.user_id,
                method: 'GET',
                data: {
                    limit: $container.data('limit') || 5
                },
                success: function(response) {
                    if (response.length > 0) {
                        ZipPicksSuggestions.renderSuggestions(response);
                    } else {
                        $container.html('<p class="zps-no-suggestions">No suggestions available</p>');
                    }
                },
                error: function() {
                    $container.html('<p class="zps-error">Unable to load suggestions</p>');
                }
            });
        },
        
        /**
         * Render suggestions
         */
        renderSuggestions: function(suggestions) {
            const $container = $('.zps-suggestions-container');
            const $list = $('<div class="zps-suggestions-list"></div>');
            
            suggestions.forEach(suggestion => {
                const $item = $(`
                    <div class="zps-suggestion-item" data-id="${suggestion.id}" data-type="${suggestion.type}">
                        <div class="zps-suggestion-avatar">
                            ${suggestion.avatar ? 
                                `<img src="${suggestion.avatar}" alt="${suggestion.name}">` : 
                                '<div class="zps-avatar-placeholder"><span class="dashicons dashicons-admin-users"></span></div>'
                            }
                        </div>
                        <div class="zps-suggestion-info">
                            <h4><a href="${suggestion.url}">${suggestion.name}</a></h4>
                            <p class="zps-suggestion-reason">${suggestion.reason}</p>
                            <p class="zps-suggestion-stats">${suggestion.followers_count} followers</p>
                        </div>
                        <div class="zps-suggestion-actions">
                            <button class="zps-follow-button zps-follow-size-small" 
                                    data-entity-id="${suggestion.id}" 
                                    data-entity-type="${suggestion.type}">
                                <span class="zps-button-text">${zippicks_social_ajax.strings.follow}</span>
                            </button>
                            <button class="zps-dismiss-suggestion" title="Dismiss">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                    </div>
                `);
                
                $list.append($item);
            });
            
            $container.html($list);
            
            // Initialize follow buttons
            ZipPicksFollowSystem.initializeButtons();
        },
        
        /**
         * Dismiss suggestion
         */
        dismissSuggestion: function($suggestion) {
            const id = $suggestion.data('id');
            const type = $suggestion.data('type');
            
            // Fade out immediately for better UX
            $suggestion.fadeOut();
            
            $.ajax({
                url: ZipPicksFollowSystem.endpoints.suggestions + '/dismiss',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': zippicks_social_ajax.nonce
                },
                data: JSON.stringify({
                    suggested_id: id,
                    suggested_type: type
                }),
                contentType: 'application/json',
                success: function() {
                    $suggestion.remove();
                    
                    // Load a replacement suggestion
                    if ($('.zps-suggestion-item').length < 3) {
                        ZipPicksSuggestions.loadSuggestions();
                    }
                },
                error: function() {
                    // Show it again on error
                    $suggestion.fadeIn();
                }
            });
        },
        
        /**
         * Refresh all suggestions
         */
        refreshSuggestions: function() {
            const $container = $('.zps-suggestions-container');
            $container.html('<div class="zps-loading">Loading suggestions...</div>');
            this.loadSuggestions();
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        ZipPicksFollowSystem.init();
        ZipPicksActivityFeed.init();
        ZipPicksSuggestions.init();
    });
    
    /**
     * Re-initialize on AJAX content load
     */
    $(document).on('zippicks:content-loaded', function() {
        ZipPicksFollowSystem.initializeButtons();
        ZipPicksActivityFeed.initFeeds();
    });

})(jQuery);