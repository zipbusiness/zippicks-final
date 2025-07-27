# ZipPicks Social - Integration Guide

## Updating author.php

To integrate the ZipPicks Social follow system into your existing author.php template, replace the existing follow button code with the following:

### Find this code block (around line 129-169):

```php
<?php if (!$is_own_profile && is_user_logged_in()): ?>
    <button type="button" 
            class="follow-button <?php echo $is_following ? 'following' : ''; ?>" 
            data-author-id="<?php echo esc_attr($author_id); ?>"
            data-action="<?php echo $is_following ? 'unfollow' : 'follow'; ?>"
            data-nonce="<?php echo wp_create_nonce('follow_author'); ?>">
        <?php echo $is_following ? 'Following' : 'Follow'; ?>
    </button>
<?php endif; ?>
```

### Replace with:

```php
<?php if (!$is_own_profile && is_user_logged_in()): ?>
    <?php 
    // Use the new ZipPicks Social follow button
    if (function_exists('zippicks_social_follow_button')) {
        echo zippicks_social_follow_button($author_id, 'user', [
            'class' => 'follow-button',
            'show_count' => false, // Set to true if you want to show follower count
            'size' => 'medium',
            'style' => 'default'
        ]);
    } else {
        // Fallback to original code if plugin is not active
        ?>
        <button type="button" 
                class="follow-button <?php echo $is_following ? 'following' : ''; ?>" 
                data-author-id="<?php echo esc_attr($author_id); ?>"
                data-action="<?php echo $is_following ? 'unfollow' : 'follow'; ?>"
                data-nonce="<?php echo wp_create_nonce('follow_author'); ?>">
            <?php echo $is_following ? 'Following' : 'Follow'; ?>
        </button>
        <?php
    }
    ?>
<?php endif; ?>
```

### Update the follower/following counts (around line 207-217):

```php
<div class="author-stats-grid">
    <div class="stat-item">
        <span class="stat-value"><?php echo number_format($post_count); ?></span>
        <span class="stat-label">Reviews</span>
    </div>
    <?php if (function_exists('zippicks_social_followers_count')): ?>
        <div class="stat-item">
            <span class="stat-value"><?php echo number_format(zippicks_social_followers_count($author_id, 'user')); ?></span>
            <span class="stat-label">Followers</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo number_format(zippicks_social_following_count($author_id)); ?></span>
            <span class="stat-label">Following</span>
        </div>
    <?php else: ?>
        <!-- Original static counts -->
        <div class="stat-item">
            <span class="stat-value"><?php echo number_format($followers_count); ?></span>
            <span class="stat-label">Followers</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo number_format($following_count); ?></span>
            <span class="stat-label">Following</span>
        </div>
    <?php endif; ?>
</div>
```

### Remove the old JavaScript (lines 680-748):

The old follow button JavaScript can be removed as the plugin handles all interactions automatically.

## CSS Adjustments

The plugin respects your existing `.follow-button` class styles. If you need to make adjustments, you can use these additional classes:

- `.zps-follow-button` - Base plugin class
- `.zps-following` - When following
- `.zps-not-following` - When not following
- `.zps-loading` - Loading state
- `.zps-size-small`, `.zps-size-medium`, `.zps-size-large` - Size variants
- `.zps-style-default`, `.zps-style-minimal`, `.zps-style-rounded` - Style variants

## Additional Features

### Show Followers List

```php
// Display a list of followers
echo do_shortcode('[zippicks_followers_list entity_id="' . $author_id . '" entity_type="user" limit="10"]');
```

### Show Following List

```php
// Display who the user is following
echo do_shortcode('[zippicks_following_list user_id="' . $author_id . '" limit="10"]');
```

### Activity Feed (Phase 2)

```php
// Display activity feed for the user
echo do_shortcode('[zippicks_activity_feed user_id="' . $author_id . '"]');
```

## Backwards Compatibility

The integration code includes fallbacks, so if the plugin is deactivated, the original follow button will still work (though without the enhanced features).

## Events

The plugin triggers JavaScript events that you can listen to:

```javascript
jQuery(document).on('zippicks:follow', function(e, data) {
    console.log('User followed:', data);
    // Update any custom UI elements
});

jQuery(document).on('zippicks:unfollow', function(e, data) {
    console.log('User unfollowed:', data);
    // Update any custom UI elements
});
```

## Testing

After integration:

1. Test follow/unfollow functionality
2. Verify follower counts update correctly
3. Check that the button states persist on page reload
4. Test with the plugin deactivated to ensure fallback works
5. Verify no JavaScript errors in console

## Support

For any issues with integration, check:
- Plugin is activated
- Database tables are created (Social → Database)
- No JavaScript conflicts in browser console
- User is logged in for follow functionality