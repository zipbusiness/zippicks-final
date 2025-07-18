<?php
/**
 * ZipPicks Breadcrumb Navigation Template
 * Template for displaying breadcrumb navigation
 * 
 * @package ZipPicks
 * @version 1.0.0
 */

// Don't show breadcrumbs on front page or admin
if (is_front_page() || is_admin()) {
    return;
}
?>

<nav class="zp-breadcrumb" aria-label="<?php _e('Breadcrumb', 'zippicks'); ?>">
    
    <!-- Home Link -->
    <div class="zp-breadcrumb__item">
        <a href="<?php echo home_url(); ?>" class="zp-breadcrumb__link">
            <?php _e('Home', 'zippicks'); ?>
        </a>
        <span class="zp-breadcrumb__separator">→</span>
    </div>

    <?php if (is_singular('zp_restaurant')) : ?>
        <!-- Restaurant Single Page -->
        <div class="zp-breadcrumb__item">
            <a href="<?php echo get_post_type_archive_link('zp_restaurant'); ?>" class="zp-breadcrumb__link">
                <?php _e('Restaurants', 'zippicks'); ?>
            </a>
            <span class="zp-breadcrumb__separator">→</span>
        </div>
        
        <?php
        // Get cuisine terms
        $cuisines = get_the_terms(get_the_ID(), 'zp_cuisine');
        if ($cuisines && !is_wp_error($cuisines)) :
            $cuisine = $cuisines[0];
        ?>
            <div class="zp-breadcrumb__item">
                <a href="<?php echo get_term_link($cuisine); ?>" class="zp-breadcrumb__link">
                    <?php echo esc_html($cuisine->name); ?>
                </a>
                <span class="zp-breadcrumb__separator">→</span>
            </div>
        <?php endif; ?>
        
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current"><?php the_title(); ?></span>
        </div>

    <?php elseif (is_post_type_archive('zp_restaurant')) : ?>
        <!-- Restaurant Archive -->
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current"><?php _e('Restaurants', 'zippicks'); ?></span>
        </div>

    <?php elseif (is_tax('zp_cuisine')) : ?>
        <!-- Cuisine Taxonomy -->
        <div class="zp-breadcrumb__item">
            <a href="<?php echo get_post_type_archive_link('zp_restaurant'); ?>" class="zp-breadcrumb__link">
                <?php _e('Restaurants', 'zippicks'); ?>
            </a>
            <span class="zp-breadcrumb__separator">→</span>
        </div>
        <div class="zp-breadcrumb__item">
            <a href="<?php echo home_url('/cuisines/'); ?>" class="zp-breadcrumb__link">
                <?php _e('Cuisines', 'zippicks'); ?>
            </a>
            <span class="zp-breadcrumb__separator">→</span>
        </div>
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current"><?php single_term_title(); ?></span>
        </div>

    <?php elseif (is_tax('zp_location')) : ?>
        <!-- Location Taxonomy -->
        <div class="zp-breadcrumb__item">
            <a href="<?php echo get_post_type_archive_link('zp_restaurant'); ?>" class="zp-breadcrumb__link">
                <?php _e('Restaurants', 'zippicks'); ?>
            </a>
            <span class="zp-breadcrumb__separator">→</span>
        </div>
        <div class="zp-breadcrumb__item">
            <a href="<?php echo home_url('/locations/'); ?>" class="zp-breadcrumb__link">
                <?php _e('Locations', 'zippicks'); ?>
            </a>
            <span class="zp-breadcrumb__separator">→</span>
        </div>
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current"><?php single_term_title(); ?></span>
        </div>

    <?php elseif (is_tax('zp_price_range')) : ?>
        <!-- Price Range Taxonomy -->
        <div class="zp-breadcrumb__item">
            <a href="<?php echo get_post_type_archive_link('zp_restaurant'); ?>" class="zp-breadcrumb__link">
                <?php _e('Restaurants', 'zippicks'); ?>
            </a>
            <span class="zp-breadcrumb__separator">→</span>
        </div>
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current">
                <?php 
                $term = get_queried_object();
                echo zippicks_format_price_range($term->slug) . ' ' . __('Restaurants', 'zippicks');
                ?>
            </span>
        </div>

    <?php elseif (is_singular('zp_review')) : ?>
        <!-- Review Single Page -->
        <div class="zp-breadcrumb__item">
            <a href="<?php echo get_post_type_archive_link('zp_review'); ?>" class="zp-breadcrumb__link">
                <?php _e('Reviews', 'zippicks'); ?>
            </a>
            <span class="zp-breadcrumb__separator">→</span>
        </div>
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current"><?php the_title(); ?></span>
        </div>

    <?php elseif (is_post_type_archive('zp_review')) : ?>
        <!-- Review Archive -->
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current"><?php _e('Reviews', 'zippicks'); ?></span>
        </div>

    <?php elseif (is_singular('post')) : ?>
        <!-- Blog Post -->
        <div class="zp-breadcrumb__item">
            <a href="<?php echo get_permalink(get_option('page_for_posts')); ?>" class="zp-breadcrumb__link">
                <?php _e('Blog', 'zippicks'); ?>
            </a>
            <span class="zp-breadcrumb__separator">→</span>
        </div>
        
        <?php
        $categories = get_the_category();
        if ($categories) :
            $category = $categories[0];
        ?>
            <div class="zp-breadcrumb__item">
                <a href="<?php echo get_category_link($category->term_id); ?>" class="zp-breadcrumb__link">
                    <?php echo esc_html($category->name); ?>
                </a>
                <span class="zp-breadcrumb__separator">→</span>
            </div>
        <?php endif; ?>
        
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current"><?php the_title(); ?></span>
        </div>

    <?php elseif (is_category()) : ?>
        <!-- Category Archive -->
        <div class="zp-breadcrumb__item">
            <a href="<?php echo get_permalink(get_option('page_for_posts')); ?>" class="zp-breadcrumb__link">
                <?php _e('Blog', 'zippicks'); ?>
            </a>
            <span class="zp-breadcrumb__separator">→</span>
        </div>
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current"><?php single_cat_title(); ?></span>
        </div>

    <?php elseif (is_tag()) : ?>
        <!-- Tag Archive -->
        <div class="zp-breadcrumb__item">
            <a href="<?php echo get_permalink(get_option('page_for_posts')); ?>" class="zp-breadcrumb__link">
                <?php _e('Blog', 'zippicks'); ?>
            </a>
            <span class="zp-breadcrumb__separator">→</span>
        </div>
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current"><?php _e('Tag:', 'zippicks'); ?> <?php single_tag_title(); ?></span>
        </div>

    <?php elseif (is_home()) : ?>
        <!-- Blog Home -->
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current"><?php _e('Blog', 'zippicks'); ?></span>
        </div>

    <?php elseif (is_page()) : ?>
        <!-- Regular Page -->
        <?php
        $page_id = get_the_ID();
        $ancestors = get_post_ancestors($page_id);
        
        // Show parent pages if they exist
        if ($ancestors) :
            $ancestors = array_reverse($ancestors);
            foreach ($ancestors as $ancestor) :
        ?>
                <div class="zp-breadcrumb__item">
                    <a href="<?php echo get_permalink($ancestor); ?>" class="zp-breadcrumb__link">
                        <?php echo get_the_title($ancestor); ?>
                    </a>
                    <span class="zp-breadcrumb__separator">→</span>
                </div>
        <?php 
            endforeach;
        endif; 
        ?>
        
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current"><?php the_title(); ?></span>
        </div>

    <?php elseif (is_search()) : ?>
        <!-- Search Results -->
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current">
                <?php printf(__('Search Results for: %s', 'zippicks'), '"' . get_search_query() . '"'); ?>
            </span>
        </div>

    <?php elseif (is_404()) : ?>
        <!-- 404 Page -->
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current"><?php _e('Page Not Found', 'zippicks'); ?></span>
        </div>

    <?php elseif (is_author()) : ?>
        <!-- Author Archive -->
        <div class="zp-breadcrumb__item">
            <a href="<?php echo get_permalink(get_option('page_for_posts')); ?>" class="zp-breadcrumb__link">
                <?php _e('Blog', 'zippicks'); ?>
            </a>
            <span class="zp-breadcrumb__separator">→</span>
        </div>
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current">
                <?php printf(__('Author: %s', 'zippicks'), get_the_author()); ?>
            </span>
        </div>

    <?php elseif (is_date()) : ?>
        <!-- Date Archive -->
        <div class="zp-breadcrumb__item">
            <a href="<?php echo get_permalink(get_option('page_for_posts')); ?>" class="zp-breadcrumb__link">
                <?php _e('Blog', 'zippicks'); ?>
            </a>
            <span class="zp-breadcrumb__separator">→</span>
        </div>
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current">
                <?php
                if (is_day()) {
                    printf(__('Daily Archives: %s', 'zippicks'), get_the_date());
                } elseif (is_month()) {
                    printf(__('Monthly Archives: %s', 'zippicks'), get_the_date('F Y'));
                } elseif (is_year()) {
                    printf(__('Yearly Archives: %s', 'zippicks'), get_the_date('Y'));
                }
                ?>
            </span>
        </div>

    <?php else : ?>
        <!-- Fallback for other pages -->
        <div class="zp-breadcrumb__item">
            <span class="zp-breadcrumb__current">
                <?php
                if (is_archive()) {
                    the_archive_title();
                } else {
                    the_title();
                }
                ?>
            </span>
        </div>
    <?php endif; ?>

</nav>

<?php
/**
 * Add structured data for breadcrumbs (SEO)
 */
if (!is_front_page()) :
    $breadcrumb_items = array();
    $position = 1;
    
    // Home item
    $breadcrumb_items[] = array(
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => get_bloginfo('name'),
        'item' => home_url()
    );
    
    // Add current page
    if (is_singular()) {
        $breadcrumb_items[] = array(
            '@type' => 'ListItem',
            'position' => $position,
            'name' => get_the_title(),
            'item' => get_permalink()
        );
    } elseif (is_archive()) {
        $breadcrumb_items[] = array(
            '@type' => 'ListItem',
            'position' => $position,
            'name' => get_the_archive_title(),
            'item' => get_permalink()
        );
    }
    
    $breadcrumb_schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $breadcrumb_items
    );
?>
    <script type="application/ld+json">
        <?php echo json_encode($breadcrumb_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    </script>
<?php endif; ?>