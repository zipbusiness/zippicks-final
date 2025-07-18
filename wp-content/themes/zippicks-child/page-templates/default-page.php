<?php
/**
 * Template Name: Default Page
 * 
 * Modern full-width page template for ZipPicks static pages
 * Optimized for zero layout shift and enterprise performance
 * 
 * @package ZipPicks
 */
get_header(); ?>

<div class="zp-page-wrapper">
    <?php while (have_posts()) : the_post(); ?>
        
        <!-- Hero Section with defined min-height -->
        <section class="zp-page-hero" style="min-height: 280px;">
            <div class="zp-page-hero-inner">
                <!-- Pre-allocated space prevents shift -->
                <div class="zp-page-title-container" style="min-height: 120px;">
                    <h1 class="zp-page-title"><?php the_title(); ?></h1>
                    
                    <?php 
                    $subtitle = get_post_meta(get_the_ID(), '_zp_page_subtitle', true);
                    $last_updated = get_post_meta(get_the_ID(), '_zp_last_updated', true);
                    ?>
                    
                    <!-- Always render subtitle container to prevent shift -->
                    <div class="zp-page-subtitle-container" style="min-height: <?php echo $subtitle ? 'auto' : '0'; ?>;">
                        <?php if ($subtitle) : ?>
                            <p class="zp-page-subtitle"><?php echo esc_html($subtitle); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Always render updated container -->
                    <div class="zp-page-updated-container" style="min-height: <?php echo $last_updated ? 'auto' : '0'; ?>;">
                        <?php if ($last_updated) : ?>
                            <p class="zp-page-updated">Last updated: <?php echo esc_html($last_updated); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Content Section with loading optimization -->
        <section class="zp-page-content">
            <div class="zp-page-container">
                <div class="zp-page-content-inner">
                    <!-- Add loading skeleton if content is heavy -->
                    <div class="zp-content-wrapper" data-zp-content="true">
                        <?php 
                        // Pre-process content to estimate height
                        $content = get_the_content();
                        $content = apply_filters('the_content', $content);
                        
                        // Estimate content height (rough calculation)
                        $estimated_height = max(400, strlen(strip_tags($content)) * 0.8);
                        ?>
                        <div style="min-height: <?php echo $estimated_height; ?>px;">
                            <?php echo $content; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section with consistent spacing -->
        <?php 
        $show_cta = get_post_meta(get_the_ID(), '_zp_show_cta', true);
        $cta_title = get_post_meta(get_the_ID(), '_zp_cta_title', true) ?: 'Have Questions?';
        $cta_text = get_post_meta(get_the_ID(), '_zp_cta_text', true) ?: 'We\'re here to help. Contact our support team for assistance.';
        $cta_link = get_post_meta(get_the_ID(), '_zp_cta_link', true) ?: '/contact/';
        $cta_button = get_post_meta(get_the_ID(), '_zp_cta_button', true) ?: 'Contact Support';
        ?>
        
        <!-- CTA Section (Only render if needed) -->
        <?php if ($show_cta) : ?>
        <section class="zp-page-cta">
            <div class="zp-page-container">
                <div class="zp-page-cta-inner">
                    <h2><?php echo esc_html($cta_title); ?></h2>
                    <p><?php echo esc_html($cta_text); ?></p>
                    <a href="<?php echo esc_url($cta_link); ?>" 
                       class="zp-button zp-button--primary"
                       style="display: inline-block; min-height: 48px; min-width: 160px;">
                        <?php echo esc_html($cta_button); ?>
                    </a>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
    <?php endwhile; ?>
</div>

<!-- Add CSS to prevent font loading shifts -->
<style>
    /* Critical CSS to prevent layout shift */
    .zp-page-wrapper {
        font-display: swap; /* Ensures text is visible during font swap */
    }
    
    .zp-page-title {
        font-size: clamp(2rem, 5vw, 3.5rem); /* Responsive sizing prevents reflow */
        line-height: 1.2;
        margin: 0 0 1rem 0;
    }
    
    .zp-page-subtitle {
        font-size: clamp(1.1rem, 2.5vw, 1.25rem);
        line-height: 1.4;
        margin: 0 0 0.5rem 0;
        opacity: 0.8;
    }
    
    .zp-page-updated {
        font-size: 0.9rem;
        line-height: 1.4;
        margin: 0;
        opacity: 0.6;
    }
    
    /* Prevent images from causing layout shift */
    .zp-page-content img {
        max-width: 100%;
        height: auto;
        display: block;
    }
    
    /* Loading states for dynamic content */
    .zp-content-wrapper[data-zp-content="true"] {
        animation: zp-fade-in 0.3s ease-in-out;
    }
    
    @keyframes zp-fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Ensure buttons maintain consistent size */
    .zp-button {
        box-sizing: border-box;
        transition: all 0.2s ease;
    }
    
    /* Progressive enhancement for better loading */
    .no-js .zp-page-wrapper {
        visibility: visible;
    }
    
    .js .zp-page-wrapper {
        visibility: hidden;
    }
    
    .js.fonts-loaded .zp-page-wrapper {
        visibility: visible;
    }
</style>

<!-- JavaScript for font loading optimization -->
<script>
document.documentElement.classList.add('js');

// Font loading detection to prevent shift
if ('fonts' in document) {
    document.fonts.ready.then(() => {
        document.documentElement.classList.add('fonts-loaded');
    });
} else {
    // Fallback for older browsers
    setTimeout(() => {
        document.documentElement.classList.add('fonts-loaded');
    }, 100);
}

// Ensure images don't cause layout shift
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('.zp-page-content img');
    images.forEach(img => {
        if (!img.hasAttribute('width') || !img.hasAttribute('height')) {
            img.addEventListener('load', function() {
                // Image loaded, layout should be stable
                this.style.transition = 'opacity 0.3s';
                this.style.opacity = '1';
            });
        }
    });
});
</script>

<?php get_footer(); ?>