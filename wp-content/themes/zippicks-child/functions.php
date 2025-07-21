<?php
/**
 * ZipPicks Child Theme Functions
 * 
 * Clean launch version - focused on essential functionality
 * Integrates with ZipPicks plugins and GeneratePress parent theme
 * 
 * @package ZipPicks
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZipPicks Theme Setup
 */
function zippicks_theme_setup() {
    // Add theme support
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('custom-logo');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    
    // Add image sizes for ZipPicks
    add_image_size('zippicks-business-card', 400, 300, true);
    add_image_size('zippicks-business-hero', 1200, 600, true);
    add_image_size('zippicks-social-share', 1200, 630, true);
    
    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Navigation', 'zippicks'),
        'mobile' => __('Mobile Navigation', 'zippicks'),
        'footer' => __('Footer Navigation', 'zippicks'),
    ));
}
add_action('after_setup_theme', 'zippicks_theme_setup');

/**
 * Enqueue styles and scripts
 */
function zippicks_enqueue_assets() {
    $theme_version = wp_get_theme()->get('Version');
    
    // Parent theme stylesheet
    wp_enqueue_style('generatepress-style', get_template_directory_uri() . '/style.css');
    
    // Child theme styles
    wp_enqueue_style('zippicks-foundation', get_stylesheet_directory_uri() . '/assets/css/foundation.css', array('generatepress-style'), $theme_version);
    wp_enqueue_style('zippicks-components', get_stylesheet_directory_uri() . '/assets/css/components.css', array('zippicks-foundation'), $theme_version);
    wp_enqueue_style('zippicks-style', get_stylesheet_directory_uri() . '/style.css', array('zippicks-components'), $theme_version);
    
    // Footer styles
    if (file_exists(get_stylesheet_directory() . '/assets/css/footer.css')) {
        wp_enqueue_style('zippicks-footer', get_stylesheet_directory_uri() . '/assets/css/footer.css', array('zippicks-style'), $theme_version);
    }
    
    // Homepage styles
    if (is_page_template('page-templates/homepage-template.php') && file_exists(get_stylesheet_directory() . '/assets/css/homepage.css')) {
        wp_enqueue_style('zippicks-homepage', get_stylesheet_directory_uri() . '/assets/css/homepage.css', array('zippicks-style'), $theme_version);
    }
    
    // Theme JavaScript
    wp_enqueue_script('zippicks-main', get_stylesheet_directory_uri() . '/assets/js/main.js', array('jquery'), $theme_version, true);
    
    // Autocomplete component if it exists
    if (file_exists(get_stylesheet_directory() . '/assets/js/autocomplete-component.js')) {
        wp_enqueue_script('zippicks-autocomplete', get_stylesheet_directory_uri() . '/assets/js/autocomplete-component.js', array('jquery'), $theme_version, true);
    }
    
    // Localize script
    wp_localize_script('zippicks-main', 'zippicks', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('zippicks_nonce'),
        'site_url' => home_url(),
        'theme_url' => get_stylesheet_directory_uri(),
        'is_user_logged_in' => is_user_logged_in(),
        'is_mobile' => wp_is_mobile(),
    ));
}
add_action('wp_enqueue_scripts', 'zippicks_enqueue_assets');

/**
 * Admin styles
 */
function zippicks_admin_styles() {
    if (file_exists(get_stylesheet_directory() . '/assets/css/admin.css')) {
        wp_enqueue_style('zippicks-admin', get_stylesheet_directory_uri() . '/assets/css/admin.css', array(), wp_get_theme()->get('Version'));
    }
}
add_action('admin_enqueue_scripts', 'zippicks_admin_styles');

/**
 * Custom header integration with GeneratePress
 */
function zippicks_custom_header() {
    // Check if we have a custom header template
    $header_file = get_stylesheet_directory() . '/template-parts/header-zippicks.php';
    if (file_exists($header_file)) {
        // Remove GeneratePress default header
        remove_action('generate_header', 'generate_construct_header');
        
        // Add our custom header
        add_action('generate_header', 'zippicks_display_custom_header');
    }
}
add_action('after_setup_theme', 'zippicks_custom_header', 20);

/**
 * Display custom header
 */
function zippicks_display_custom_header() {
    get_template_part('template-parts/header', 'zippicks');
}

/**
 * Custom footer integration with GeneratePress
 * Simplified to avoid conflicts with GP Elements
 */
function zippicks_custom_footer() {
    // Let GP Elements handle the footer if it's being used
    // The theme will only provide fallback footer if needed
}

/**
 * Body classes
 */
function zippicks_body_classes($classes) {
    $classes[] = 'zippicks-theme';
    
    if (wp_is_mobile()) {
        $classes[] = 'zp-mobile';
    }
    
    // Add plugin-specific classes if plugins are active
    if (defined('ZIPPICKS_CORE_VERSION')) {
        $classes[] = 'zp-core-active';
    }
    
    if (is_singular('zippicks_business')) {
        $classes[] = 'zp-business-single';
    }
    
    if (is_post_type_archive('zippicks_business')) {
        $classes[] = 'zp-business-archive';
    }
    
    return $classes;
}
add_filter('body_class', 'zippicks_body_classes');

/**
 * Widget areas
 */
function zippicks_widgets_init() {
    // Main sidebar
    register_sidebar(array(
        'name' => __('ZipPicks Sidebar', 'zippicks'),
        'id' => 'zippicks-sidebar',
        'description' => __('Main sidebar for ZipPicks pages', 'zippicks'),
        'before_widget' => '<div id="%1$s" class="widget zp-widget %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<h3 class="widget-title zp-widget-title">',
        'after_title' => '</h3>',
    ));
    
    // Business sidebar
    register_sidebar(array(
        'name' => __('Business Sidebar', 'zippicks'),
        'id' => 'zippicks-business-sidebar',
        'description' => __('Sidebar for business pages', 'zippicks'),
        'before_widget' => '<div id="%1$s" class="widget zp-widget %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<h3 class="widget-title zp-widget-title">',
        'after_title' => '</h3>',
    ));
}
add_action('widgets_init', 'zippicks_widgets_init');

/**
 * Performance optimizations
 */
function zippicks_performance_optimizations() {
    // Remove emoji scripts
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
    
    // Remove unnecessary meta tags
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'rsd_link');
}
add_action('init', 'zippicks_performance_optimizations');

/**
 * Custom login page styling
 */
function zippicks_login_styles() {
    ?>
    <style type="text/css">
        body.login {
            background: linear-gradient(135deg, #194FAD 0%, #5A9BFF 100%);
        }
        #login h1 a, .login h1 a {
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/assets/images/zippicks-logo.png);
            background-size: contain;
            background-repeat: no-repeat;
            width: 200px;
            height: 80px;
        }
        .login form {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
    </style>
    <?php
}
add_action('login_enqueue_scripts', 'zippicks_login_styles');

/**
 * Admin footer
 */
function zippicks_admin_footer_text($text) {
    return 'Built with ❤️ by the <strong>ZipPicks Engineering Team</strong>';
}
add_filter('admin_footer_text', 'zippicks_admin_footer_text');

/**
 * Theme activation check
 */
function zippicks_theme_activation_check() {
    // Check if GeneratePress is active
    $theme = wp_get_theme();
    $parent_theme = $theme->parent();
    
    if (!$parent_theme || $parent_theme->get('TextDomain') !== 'generatepress') {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><strong>ZipPicks Child Theme:</strong> This theme requires GeneratePress parent theme to be installed and active.</p>
            </div>
            <?php
        });
    }
    
    // Check if ZipPicks foundation is active
    if (!function_exists('zippicks')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-warning">
                <p><strong>ZipPicks Child Theme:</strong> ZipPicks Foundation plugin is not active. Some features may not work properly.</p>
            </div>
            <?php
        });
    }
}
add_action('admin_init', 'zippicks_theme_activation_check');


/**
 * Integration hooks for ZipPicks plugins
 */
add_action('zippicks_before_content', function() {
    // Hook for plugins to add content before main content
    do_action('zippicks_theme_before_content');
});

add_action('zippicks_after_content', function() {
    // Hook for plugins to add content after main content
    do_action('zippicks_theme_after_content');
});

/**
 * Helper functions
 */

/**
 * Get ZipPicks option
 */
function zippicks_get_theme_option($option, $default = '') {
    return get_theme_mod('zippicks_' . $option, $default);
}

/**
 * Check if ZipPicks plugin is active
 */
function zippicks_is_plugin_active($plugin) {
    switch ($plugin) {
        case 'core':
            return defined('ZIPPICKS_CORE_VERSION');
        case 'foundation':
            return function_exists('zippicks');
        default:
            return false;
    }
}

/**
 * Get current user location (ZIP code)
 */
function zippicks_get_user_location() {
    // This will be implemented by the core plugin
    return apply_filters('zippicks_user_location', false);
}

/**
 * Nav Walker Classes
 */
class ZipPicks_Nav_Walker extends Walker_Nav_Menu {
    
    function start_lvl(&$output, $depth = 0, $args = null) {
        $output .= '<ul class="zp-nav__dropdown" role="menu">';
    }
    
    function end_lvl(&$output, $depth = 0, $args = null) {
        $output .= '</ul>';
    }
    
    function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        $classes = empty($item->classes) ? array() : (array) $item->classes;
        $active_class = in_array('current-menu-item', $classes) ? ' zp-nav__link--active' : '';
        $dropdown_class = in_array('menu-item-has-children', $classes) ? ' zp-nav__item--dropdown' : '';
        
        $output .= '<li class="zp-nav__item' . $dropdown_class . '" role="none">';
        $output .= '<a href="' . esc_url($item->url) . '" class="zp-nav__link' . $active_class . '" role="menuitem">';
        $output .= esc_html($item->title);
        $output .= '</a>';
    }
    
    function end_el(&$output, $item, $depth = 0, $args = null) {
        $output .= '</li>';
    }
}

/**
 * Mobile Nav Walker
 */
class ZipPicks_Mobile_Nav_Walker extends Walker_Nav_Menu {
    
    function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        $classes = empty($item->classes) ? array() : (array) $item->classes;
        $active_class = in_array('current-menu-item', $classes) ? ' zp-mobile-menu__link--active' : '';
        
        $output .= '<li class="zp-mobile-menu__item" role="none">';
        $output .= '<a href="' . esc_url($item->url) . '" class="zp-mobile-menu__link' . $active_class . '" role="menuitem">';
        $output .= esc_html($item->title);
        $output .= '</a>';
        $output .= '</li>';
    }
}

/**
 * Default menu fallback
 */
function zippicks_default_menu() {
    echo '<ul class="zp-nav__menu" role="menubar">';
    echo '<li class="zp-nav__item" role="none"><a href="' . home_url('/') . '" class="zp-nav__link" role="menuitem">Home</a></li>';
    echo '<li class="zp-nav__item" role="none"><a href="' . home_url('/businesses/') . '" class="zp-nav__link" role="menuitem">Businesses</a></li>';
    echo '<li class="zp-nav__item" role="none"><a href="' . home_url('/vibes/') . '" class="zp-nav__link" role="menuitem">Vibes</a></li>';
    echo '<li class="zp-nav__item" role="none"><a href="' . home_url('/about/') . '" class="zp-nav__link" role="menuitem">About</a></li>';
    echo '</ul>';
}

/**
 * Mobile menu fallback
 */
function zippicks_mobile_default_menu() {
    echo '<ul class="zp-mobile-menu__nav" role="menu">';
    echo '<li class="zp-mobile-menu__item" role="none"><a href="' . home_url('/') . '" class="zp-mobile-menu__link" role="menuitem">Home</a></li>';
    echo '<li class="zp-mobile-menu__item" role="none"><a href="' . home_url('/businesses/') . '" class="zp-mobile-menu__link" role="menuitem">Businesses</a></li>';
    echo '<li class="zp-mobile-menu__item" role="none"><a href="' . home_url('/vibes/') . '" class="zp-mobile-menu__link" role="menuitem">Vibes</a></li>';
    echo '<li class="zp-mobile-menu__item" role="none"><a href="' . home_url('/about/') . '" class="zp-mobile-menu__link" role="menuitem">About</a></li>';
    echo '</ul>';
}



/**
 * Mobile menu handler
 */
function zippicks_mobile_menu_scripts() {
    ?>
    <script>
    function zippicks_toggle_mobile_menu() {
        const mobileMenu = document.getElementById('zp-mobile-menu');
        const mobileToggle = document.querySelector('.zp-mobile-toggle');
        const body = document.body;
        
        if (mobileMenu) {
            if (mobileMenu.classList.contains('active')) {
                zippicks_close_mobile_menu();
            } else {
                mobileMenu.style.display = 'flex';
                body.style.overflow = 'hidden';
                body.classList.add('zp-mobile-menu-open');
                if (mobileToggle) {
                    mobileToggle.classList.add('zp-mobile-toggle--active');
                    mobileToggle.setAttribute('aria-expanded', 'true');
                }
                setTimeout(() => { mobileMenu.classList.add('active'); }, 10);
            }
        }
    }
    
    function zippicks_close_mobile_menu() {
        const mobileMenu = document.getElementById('zp-mobile-menu');
        const mobileToggle = document.querySelector('.zp-mobile-toggle');
        const body = document.body;
        
        if (mobileMenu) {
            mobileMenu.classList.remove('active');
            body.style.overflow = '';
            body.classList.remove('zp-mobile-menu-open');
            if (mobileToggle) {
                mobileToggle.classList.remove('zp-mobile-toggle--active');
                mobileToggle.setAttribute('aria-expanded', 'false');
            }
            setTimeout(() => { mobileMenu.style.display = 'none'; }, 300);
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Close on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                zippicks_close_mobile_menu();
            }
        });
        
        // Close when clicking mobile menu links
        const mobileLinks = document.querySelectorAll('.zp-mobile-menu__link');
        mobileLinks.forEach(link => {
            link.addEventListener('click', function() {
                setTimeout(zippicks_close_mobile_menu, 150);
            });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'zippicks_mobile_menu_scripts');