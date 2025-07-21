<?php
/**
 * Template Name: ZipPicks Homepage
 * Template Post Type: page
 * 
 * Clean homepage template - styling handled by theme/page builder
 * 
 * @package ZipPicks
 */
get_header(); ?>

<div class="zp-homepage-wrapper">
    <?php while (have_posts()) : the_post(); ?>
        <div class="zp-homepage-content">
            <?php the_content(); ?>
        </div>
    <?php endwhile; ?>
</div>

<?php get_footer(); ?>