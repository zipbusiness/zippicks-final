# Product Requirements Document (PRD)
# ZipPicks Social - Follow System Plugin

## 1. Executive Summary

### Product Name
**ZipPicks Social** - Enterprise Follow System for WordPress

### Version
1.0.0

### Purpose
Create a scalable, performant social following system that enables users to follow other users, critics, businesses, and lists within the ZipPicks ecosystem, driving engagement and community building.

### Success Metrics
- 50% of active users following at least 3 entities within 30 days
- 20% increase in user return rate
- <100ms follow action response time
- 99.9% uptime for follow functionality

## 2. Problem Statement

### Current State
- Follow buttons exist in UI but are non-functional
- No data persistence for social connections
- Missing engagement features that competitors have
- No way to track user preferences and build personalized feeds

### User Pain Points
- Cannot save favorite critics or businesses for easy access
- Miss updates from preferred content creators
- No social proof or community features
- Limited discovery mechanisms

## 3. Solution Overview

### Core Features
1. **Universal Follow System**
   - Follow/unfollow users, critics, businesses, lists
   - Mutual follow detection
   - Follow suggestions based on behavior
   - Bulk follow/unfollow management

2. **Activity Feeds**
   - Personalized feed of followed entity activities
   - Real-time updates via AJAX
   - Infinite scroll pagination
   - Activity filtering by type

3. **Notifications**
   - New follower alerts
   - Important updates from followed entities
   - Digest emails (daily/weekly)
   - Push notification support (future)

4. **Privacy Controls**
   - Block/unblock users
   - Private account option
   - Follower approval system
   - Mute without unfollowing

## 4. Technical Architecture

### Database Schema

```sql
-- Core follows table
CREATE TABLE wp_zippicks_follows (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    follower_id BIGINT(20) UNSIGNED NOT NULL,
    followed_id BIGINT(20) UNSIGNED NOT NULL,
    followed_type ENUM('user', 'critic', 'business', 'list') DEFAULT 'user',
    status ENUM('active', 'pending', 'blocked', 'muted') DEFAULT 'active',
    notification_pref ENUM('all', 'important', 'none') DEFAULT 'all',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_follow (follower_id, followed_id, followed_type),
    INDEX idx_follower (follower_id, status),
    INDEX idx_followed (followed_id, followed_type, status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Follow statistics cache
CREATE TABLE wp_zippicks_follow_stats (
    entity_id BIGINT(20) UNSIGNED PRIMARY KEY,
    entity_type ENUM('user', 'critic', 'business', 'list') DEFAULT 'user',
    followers_count INT UNSIGNED DEFAULT 0,
    following_count INT UNSIGNED DEFAULT 0,
    mutual_count INT UNSIGNED DEFAULT 0,
    last_calculated DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_followers (entity_type, followers_count),
    INDEX idx_last_calc (last_calculated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity stream
CREATE TABLE wp_zippicks_activities (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT(20) UNSIGNED NOT NULL,
    actor_type ENUM('user', 'critic', 'business') DEFAULT 'user',
    action VARCHAR(50) NOT NULL, -- 'reviewed', 'favorited', 'followed', 'created_list'
    object_type VARCHAR(50), -- 'business', 'user', 'list', 'review'
    object_id BIGINT(20) UNSIGNED,
    metadata JSON,
    visibility ENUM('public', 'followers', 'private') DEFAULT 'public',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actor (actor_id, actor_type, created_at),
    INDEX idx_visibility (visibility, created_at),
    INDEX idx_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Follow suggestions
CREATE TABLE wp_zippicks_follow_suggestions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    suggested_id BIGINT(20) UNSIGNED NOT NULL,
    suggested_type ENUM('user', 'critic', 'business') DEFAULT 'user',
    reason VARCHAR(100), -- 'mutual_follows', 'similar_taste', 'trending', 'location_based'
    score DECIMAL(5,2) DEFAULT 0,
    shown_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    dismissed_at DATETIME NULL,
    UNIQUE KEY unique_suggestion (user_id, suggested_id, suggested_type),
    INDEX idx_user_score (user_id, dismissed_at, score DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Plugin Structure

```
/wp-content/plugins/zippicks-social/
├── zippicks-social.php              # Main plugin file
├── README.md
├── LICENSE
├── composer.json
├── package.json
├── uninstall.php
│
├── /includes/
│   ├── class-activator.php          # Activation hooks, DB creation
│   ├── class-deactivator.php        # Deactivation cleanup
│   ├── class-follow-manager.php     # Core follow logic
│   ├── class-activity-stream.php    # Activity feed management
│   ├── class-notifications.php      # Notification system
│   ├── class-suggestions.php        # Follow suggestions engine
│   ├── class-privacy-manager.php    # Privacy controls
│   ├── class-cache-manager.php      # Redis/transient caching
│   └── class-migration.php          # Database migrations
│
├── /api/
│   ├── class-rest-controller.php    # REST API endpoints
│   ├── class-ajax-handler.php       # AJAX endpoints
│   └── class-graphql-schema.php     # Future GraphQL support
│
├── /admin/
│   ├── class-admin.php              # Admin interface
│   ├── class-settings.php           # Plugin settings
│   ├── class-analytics.php          # Follow analytics
│   └── /views/
│       ├── settings-page.php
│       ├── analytics-dashboard.php
│       └── user-follows-meta-box.php
│
├── /public/
│   ├── class-public.php             # Public-facing functionality
│   ├── class-shortcodes.php         # [zippicks_follow_button] etc
│   └── class-widgets.php            # Follow widgets
│
├── /templates/
│   ├── follow-button.php            # Reusable follow button
│   ├── followers-list.php           # Followers modal/page
│   ├── following-list.php           # Following modal/page
│   ├── activity-feed.php            # Activity stream
│   └── follow-suggestions.php       # Who to follow widget
│
├── /assets/
│   ├── /css/
│   │   ├── admin.css
│   │   ├── public.css
│   │   └── public.min.css
│   ├── /js/
│   │   ├── admin.js
│   │   ├── follow-system.js         # Core follow JS
│   │   ├── activity-feed.js         # Feed functionality
│   │   └── follow-system.min.js
│   └── /images/
│       └── /icons/
│
└── /tests/
    ├── /unit/
    ├── /integration/
    └── /e2e/
```

### API Endpoints

```php
// REST API Routes
/wp-json/zippicks-social/v1/follow
/wp-json/zippicks-social/v1/unfollow
/wp-json/zippicks-social/v1/followers/{entity_type}/{entity_id}
/wp-json/zippicks-social/v1/following/{user_id}
/wp-json/zippicks-social/v1/is-following
/wp-json/zippicks-social/v1/suggestions/{user_id}
/wp-json/zippicks-social/v1/activity-feed/{user_id}
/wp-json/zippicks-social/v1/stats/{entity_type}/{entity_id}

// AJAX Actions (backward compatibility)
wp_ajax_zippicks_follow
wp_ajax_zippicks_unfollow
wp_ajax_zippicks_get_followers
wp_ajax_zippicks_get_following
```

## 5. Integration with Existing Code

### Author.php Integration

```php
// In author.php, replace the existing follow button code with:

<?php if (!$is_own_profile && is_user_logged_in()): ?>
    <?php echo do_shortcode('[zippicks_follow_button entity_id="' . $author_id . '" entity_type="user"]'); ?>
<?php endif; ?>

// The plugin will provide:
// 1. Automatic state detection (following/not following)
// 2. AJAX handling
// 3. Proper nonce security
// 4. Loading states
// 5. Error handling
```

### Backwards Compatibility

```php
// The plugin will hook into existing functions:
add_filter('zippicks_get_followers_count', 'zippicks_social_get_followers_count', 10, 2);
add_filter('zippicks_is_following', 'zippicks_social_is_following', 10, 3);

// Existing JavaScript will be enhanced:
if (typeof ZipPicksSocial !== 'undefined') {
    // Use new plugin methods
} else {
    // Fallback to current implementation
}
```

### Theme Integration Points

```php
// Functions available to themes:
zippicks_social_follow_button($entity_id, $entity_type, $args = [])
zippicks_social_followers_count($entity_id, $entity_type)
zippicks_social_following_count($user_id)
zippicks_social_is_following($follower_id, $followed_id, $followed_type)
zippicks_social_get_activity_feed($user_id, $args = [])
zippicks_social_get_suggestions($user_id, $limit = 5)

// Hooks for themes:
do_action('zippicks_social_before_follow_button', $entity_id, $entity_type);
do_action('zippicks_social_after_follow', $follower_id, $followed_id, $followed_type);
apply_filters('zippicks_social_follow_button_html', $html, $entity_id, $entity_type);
```

## 6. User Interface Components

### Follow Button States
1. **Default**: "Follow" (blue button)
2. **Hover**: "Follow" (darker blue)
3. **Following**: "Following" (gray button)
4. **Following Hover**: "Unfollow" (red outline)
5. **Loading**: Spinner icon
6. **Error**: "Try Again" (red)

### Activity Feed Item
```html
<div class="zps-activity-item">
    <div class="zps-activity-actor">
        <img src="{avatar}" />
        <div class="zps-activity-meta">
            <strong>{actor_name}</strong>
            <span class="zps-activity-action">{action_text}</span>
            <time>{relative_time}</time>
        </div>
    </div>
    <div class="zps-activity-object">
        {object_preview}
    </div>
</div>
```

## 7. Performance Optimization

### Caching Strategy
1. **User Stats**: Cache for 5 minutes
2. **Follow State**: Cache until action
3. **Suggestions**: Cache for 1 hour
4. **Activity Feed**: Real-time with pagination

### Database Optimization
1. **Composite Indexes**: For common queries
2. **Stats Table**: Pre-calculated counts
3. **Partitioning**: For activities table (by month)
4. **Read Replicas**: For scaling

### Frontend Optimization
1. **Lazy Loading**: Load follow states on viewport
2. **Debounced Actions**: Prevent double-clicks
3. **Optimistic UI**: Update before server confirms
4. **WebSocket**: Future real-time updates

## 8. Security Considerations

### Authentication
- Nonce verification for all actions
- Rate limiting per user
- Session validation
- CSRF protection

### Authorization
- Can only follow public entities
- Cannot follow blocked users
- Respect privacy settings
- Admin override capabilities

### Data Protection
- Encrypted user relationships
- GDPR compliance (export/delete)
- Audit logging for actions
- Regular security scans

## 9. Analytics & Reporting

### Metrics to Track
1. **Engagement**
   - Daily active followers
   - Follow/unfollow ratio
   - Average follows per user
   - Mutual follow percentage

2. **Performance**
   - API response times
   - Database query times
   - Cache hit rates
   - Error rates

3. **Growth**
   - New follows per day
   - Follower growth by entity type
   - Viral coefficient
   - Retention via follows

### Admin Dashboard
- Top followed entities
- Follow growth charts
- User engagement heatmap
- Suggestion effectiveness

## 10. Rollout Strategy

### Phase 1: Core Following (Week 1-2)
- Basic follow/unfollow
- Database setup
- Migration of existing UI
- Admin interface

### Phase 2: Enhanced Features (Week 3-4)
- Activity feed
- Follow suggestions
- Email notifications
- Performance optimization

### Phase 3: Advanced Features (Week 5-6)
- Privacy controls
- Bulk management
- Analytics dashboard
- Mobile app API

### Phase 4: Scale & Optimize (Week 7-8)
- Load testing
- Caching layer
- CDN integration
- Monitoring setup

## 11. Success Criteria

### Launch Metrics
- Zero downtime migration
- <100ms follow actions
- 100% backward compatibility
- No data loss

### 30-Day Metrics
- 50% user adoption
- <0.1% error rate
- 90% cache hit rate
- 95% user satisfaction

### 90-Day Metrics
- 70% active user follows
- 25% increase in return visits
- 15% increase in reviews
- 30% viral follow rate

## 12. Risk Mitigation

### Technical Risks
- **Database scaling**: Implement sharding strategy
- **Real-time updates**: Use queue system
- **Legacy code conflicts**: Comprehensive testing
- **Performance degradation**: Monitoring alerts

### Business Risks
- **Low adoption**: In-app promotion
- **Spam follows**: Rate limiting
- **Privacy concerns**: Clear settings
- **Feature creep**: Strict scope management

## 13. Future Enhancements

### Version 2.0
- Follow lists/collections
- Collaborative following
- Follow recommendations ML
- Cross-platform sync

### Version 3.0
- Social graph API
- Influencer tools
- Follow analytics API
- Enterprise features

## 14. Appendix

### Competitive Analysis
- Yelp: Follow users and lists
- Instagram: Follow with stories
- Twitter: Lists and notifications
- LinkedIn: Connection types

### Technical Dependencies
- WordPress 6.0+
- PHP 8.0+
- MySQL 8.0+
- Redis (recommended)
- Elasticsearch (future)