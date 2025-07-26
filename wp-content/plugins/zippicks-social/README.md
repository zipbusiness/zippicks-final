# ZipPicks Social - Enterprise Follow System

A comprehensive social following system for WordPress that enables users to follow other users, critics, businesses, and lists.

## Features

### Core Functionality
- **Universal Follow System** - Follow/unfollow users, critics, businesses, and lists
- **REST API & AJAX Support** - Modern API with backward compatibility
- **Activity Feeds** - Personalized feeds of followed entity activities (Phase 2)
- **Follow Suggestions** - Smart recommendations based on user behavior (Phase 2)
- **Privacy Controls** - Block/mute capabilities (Phase 2)
- **Email Notifications** - Configurable notification preferences (Phase 2)

### Technical Features
- **Enterprise Architecture** - Scalable database design with proper indexing
- **Migration System** - Safe schema evolution with version tracking
- **Foundation Integration** - Works with ZipPicks Foundation services
- **Cache Management** - Intelligent caching with Redis support
- **Rate Limiting** - Prevents spam and abuse
- **Performance Optimized** - <100ms response times

## Installation

1. Upload the `zippicks-social` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Go to **Social → Settings** to configure options
4. Check **Social → Database** to ensure tables are created

## Usage

### Theme Integration

#### Display Follow Button
```php
// Simple usage
echo zippicks_social_follow_button($user_id);

// With options
echo zippicks_social_follow_button($entity_id, 'business', [
    'show_count' => true,
    'size' => 'large',
    'style' => 'rounded'
]);
```

#### Get Follow Counts
```php
// Get followers count
$followers = zippicks_social_followers_count($entity_id, 'user');

// Get following count
$following = zippicks_social_following_count($user_id);

// Check if following
$is_following = zippicks_social_is_following($follower_id, $followed_id, 'user');
```

### Shortcodes

#### Follow Button
```
[zippicks_follow_button entity_id="123" entity_type="user" show_count="true"]
```

#### Followers List
```
[zippicks_followers_list entity_id="123" entity_type="user" limit="10"]
```

#### Following List
```
[zippicks_following_list user_id="123" limit="20" entity_type="business"]
```

#### Activity Feed
```
[zippicks_activity_feed user_id="123" limit="20" filter="all"]
```

### REST API Endpoints

- `POST /wp-json/zippicks-social/v1/follow`
- `POST /wp-json/zippicks-social/v1/unfollow`
- `GET /wp-json/zippicks-social/v1/followers/{entity_type}/{entity_id}`
- `GET /wp-json/zippicks-social/v1/following/{user_id}`
- `GET /wp-json/zippicks-social/v1/is-following`
- `GET /wp-json/zippicks-social/v1/stats/{entity_type}/{entity_id}`

### JavaScript Events

```javascript
// Listen for follow events
jQuery(document).on('zippicks:follow', function(e, data) {
    console.log('Followed:', data);
});

// Listen for unfollow events
jQuery(document).on('zippicks:unfollow', function(e, data) {
    console.log('Unfollowed:', data);
});
```

## Database Schema

### Tables
- `wp_zippicks_follows` - Core follow relationships
- `wp_zippicks_follow_stats` - Cached statistics
- `wp_zippicks_activities` - Activity stream data
- `wp_zippicks_follow_suggestions` - Follow recommendations

## Configuration

### Settings
- **Enable Notifications** - Send notifications for new followers
- **Enable Activity Feed** - Show activity feeds on profiles
- **Enable Suggestions** - Show follow recommendations
- **Follow Rate Limit** - Maximum follows per hour (default: 50)
- **Activity Retention** - Days to keep activity data (default: 90)
- **Cache Duration** - Cache timeout in seconds (default: 300)

## Hooks & Filters

### Actions
- `zippicks_social_after_follow` - Fired after successful follow
- `zippicks_social_after_unfollow` - Fired after successful unfollow
- `zippicks_social_before_follow_button` - Before button render

### Filters
- `zippicks_social_follow_button_html` - Modify button HTML
- `zippicks_social_follow_rate_limit` - Adjust rate limits
- `zippicks_social_cache_duration` - Modify cache times

## Performance Considerations

- Indexed database queries for fast lookups
- Intelligent caching of counts and states
- Lazy loading of follow states
- Optimistic UI updates
- Debounced API calls

## Security

- Nonce verification on all actions
- Rate limiting per user
- SQL injection prevention
- XSS protection
- CSRF protection

## Requirements

- WordPress 6.0+
- PHP 8.0+
- MySQL 8.0+
- ZipPicks Core plugin (recommended)

## Support

For issues or feature requests, please contact the ZipPicks development team.

## License

GPL v2 or later