<?php
/**
 * ZipPicks Bottom Navigation for Mobile
 */
?>
<nav class="zp-bottom-nav">
    <a href="<?php echo home_url('/discover/'); ?>" class="zp-bottom-nav__item <?php echo is_page('discover') ? 'zp-bottom-nav__item--active' : ''; ?>">
        <span class="zp-bottom-nav__icon">🔍</span>
        <span class="zp-bottom-nav__label"><?php _e('Discover', 'zippicks'); ?></span>
    </a>
    
    <a href="<?php echo home_url('/favorites/'); ?>" class="zp-bottom-nav__item <?php echo is_page('favorites') ? 'zp-bottom-nav__item--active' : ''; ?>">
        <span class="zp-bottom-nav__icon">
            ❤️
            <?php $fav_count = zippicks_get_user_favorites_count(); ?>
            <?php if ($fav_count > 0) : ?>
                <span class="zp-bottom-nav__badge"><?php echo $fav_count; ?></span>
            <?php endif; ?>
        </span>
        <span class="zp-bottom-nav__label"><?php _e('Favorites', 'zippicks'); ?></span>
    </a>
    
    <a href="<?php echo home_url('/search/'); ?>" class="zp-bottom-nav__item <?php echo is_page('search') ? 'zp-bottom-nav__item--active' : ''; ?>">
        <span class="zp-bottom-nav__icon">📍</span>
        <span class="zp-bottom-nav__label"><?php _e('Near Me', 'zippicks'); ?></span>
    </a>
    
    <a href="<?php echo home_url('/profile/'); ?>" class="zp-bottom-nav__item <?php echo is_page('profile') ? 'zp-bottom-nav__item--active' : ''; ?>">
        <span class="zp-bottom-nav__icon">👤</span>
        <span class="zp-bottom-nav__label"><?php _e('Profile', 'zippicks'); ?></span>
    </a>
    
    <a href="<?php echo home_url('/more/'); ?>" class="zp-bottom-nav__item <?php echo is_page('more') ? 'zp-bottom-nav__item--active' : ''; ?>">
        <span class="zp-bottom-nav__icon">☰</span>
        <span class="zp-bottom-nav__label"><?php _e('More', 'zippicks'); ?></span>
    </a>
</nav>