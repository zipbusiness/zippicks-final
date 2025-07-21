<?php
/**
 * ZipPicks Custom Header Template - Fixed Logo
 * File: header-zippicks.php
 * 
 * @package ZipPicks
 * @version 1.0.0
 */
?>
<header class="zp-header" role="banner">
    <div class="zp-header__container">
        
        <!-- ZipPicks Logo -->
        <a href="<?php echo home_url(); ?>" class="zp-logo" aria-label="<?php printf(__('%s - Home', 'zippicks'), get_bloginfo('name')); ?>">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/zippicks-logo.png" 
                 alt="<?php bloginfo('name'); ?>" 
                 class="zp-logo__image">
        </a>

        <!-- Desktop Navigation -->
        <nav class="zp-nav" role="navigation" aria-label="<?php _e('Main Navigation', 'zippicks'); ?>">
            <?php
            wp_nav_menu(array(
                'theme_location' => 'primary',
                'menu_class' => 'zp-nav__menu',
                'container' => false,
                'fallback_cb' => 'zippicks_default_menu',
                'walker' => new ZipPicks_Nav_Walker(),
                'items_wrap' => '<ul id="%1$s" class="%2$s" role="menubar">%3$s</ul>'
            ));
            ?>
        </nav>

        <!-- Desktop Header Actions (Sign In + Sign Up) -->
        <div class="zp-header__actions zp-header__actions--desktop">
            <?php if (is_user_logged_in()) : ?>
                <!-- Logged In User Actions -->
                <a href="<?php echo home_url('/favorites/'); ?>" class="zp-header__action zp-header__action--signin" title="<?php _e('My Favorites', 'zippicks'); ?>">
                    <?php _e('Favorites', 'zippicks'); ?>
                </a>
                
                <a href="<?php echo home_url('/account/'); ?>" class="zp-header__action zp-header__action--signup">
                    <?php _e('My Account', 'zippicks'); ?>
                </a>
                
            <?php else : ?>
                <!-- Guest User Actions - Both Sign In and Sign Up -->
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="zp-header__action zp-header__action--signin">
                    <?php _e('Sign In', 'zippicks'); ?>
                </a>
                
                <a href="https://www.zippicks.com/register/" class="zp-header__action zp-header__action--signup">
                    <?php _e('Sign Up', 'zippicks'); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Mobile Actions (Sign Up + Menu Toggle) -->
        <div class="zp-header__actions zp-header__actions--mobile">
            <?php if (!is_user_logged_in()) : ?>
                <a href="https://www.zippicks.com/register/" class="zp-header__action zp-header__action--signup">
                    <?php _e('Sign Up', 'zippicks'); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo home_url('/account/'); ?>" class="zp-header__action zp-header__action--signup">
                    <?php _e('Account', 'zippicks'); ?>
                </a>
            <?php endif; ?>
            
            <!-- Mobile Menu Toggle -->
            <button class="zp-mobile-toggle" 
                    aria-label="<?php _e('Toggle mobile menu', 'zippicks'); ?>" 
                    aria-expanded="false"
                    aria-controls="zp-mobile-menu"
                    onclick="zippicks_toggle_mobile_menu()">
                <div class="zp-mobile-toggle__icon">
                    <span class="zp-mobile-toggle__line"></span>
                    <span class="zp-mobile-toggle__line"></span>
                    <span class="zp-mobile-toggle__line"></span>
                </div>
                <span class="zp-sr-only"><?php _e('Menu', 'zippicks'); ?></span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile Menu Overlay -->
<div class="zp-mobile-menu" id="zp-mobile-menu" role="dialog" aria-modal="true" aria-label="<?php _e('Mobile Navigation', 'zippicks'); ?>">
    
    <!-- Mobile Menu Header -->
    <div class="zp-mobile-menu__header">
        <a href="<?php echo home_url(); ?>" class="zp-logo zp-logo--mobile">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/zippicks-logo.png" 
                 alt="<?php bloginfo('name'); ?>" 
                 class="zp-logo__image zp-logo__image--mobile">
        </a>
        <button class="zp-mobile-menu__close" 
                aria-label="<?php _e('Close mobile menu', 'zippicks'); ?>"
                onclick="zippicks_close_mobile_menu()">
            <span class="zp-mobile-menu__close-x">×</span>
        </button>
    </div>
    
    <!-- Mobile Menu Body -->
    <div class="zp-mobile-menu__body">
        <!-- Mobile Navigation Menu -->
        <?php
        // First, try to get the WordPress menu
        $locations = get_nav_menu_locations();
        $has_menu = isset($locations['mobile']) && wp_get_nav_menu_items($locations['mobile']);
        
        if ($has_menu) {
            // WordPress menu exists and has items
            echo '<!-- WordPress mobile menu loading -->';
            wp_nav_menu(array(
                'theme_location' => 'mobile',
                'menu_class' => 'zp-mobile-menu__nav',
                'container' => false,
                'fallback_cb' => false, // Don't use fallback, we'll handle it manually
                'walker' => new ZipPicks_Mobile_Nav_Walker(),
                'items_wrap' => '<ul id="%1$s" class="%2$s" role="menu">%3$s</ul>'
            ));
        } else {
            // No WordPress menu found, use fallback
            echo '<!-- WordPress menu not found, using fallback -->';
            zippicks_mobile_default_menu();
        }
        ?>
        
        <!-- Mobile Sign In Actions -->
        <div class="zp-mobile-menu__actions">
            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo home_url('/account/'); ?>" class="zp-mobile-menu__link zp-mobile-menu__link--action">
                    <?php _e('My Account', 'zippicks'); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo wp_login_url(home_url()); ?>" class="zp-mobile-menu__link zp-mobile-menu__link--action">
                    <?php _e('Sign In', 'zippicks'); ?>
                </a>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Skip Link for Accessibility -->
<a class="zp-skip-link" href="#main-content"><?php _e('Skip to main content', 'zippicks'); ?></a>

<?php
/**
 * Add structured data for organization
 */
$organization_schema = array(
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => get_bloginfo('name'),
    'url' => home_url(),
    'logo' => array(
        '@type' => 'ImageObject',
        'url' => get_stylesheet_directory_uri() . '/assets/images/zippicks-logo.png'
    ),
    'description' => get_bloginfo('description'),
    'sameAs' => array(
        'https://www.facebook.com/zippicks',
        'https://www.instagram.com/zippicks',
        'https://www.twitter.com/zippicks'
    )
);
?>
<script type="application/ld+json">
    <?php echo json_encode($organization_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
</script>