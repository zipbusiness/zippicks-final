<?php
/**
 * ZipPicks Footer Template
 * 
 * @package ZipPicks
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<footer class="zp-footer" role="contentinfo">
    <div class="zp-footer__container">
        
        <!-- Footer Top -->
        <div class="zp-footer__top">
            <div class="zp-container">
                <div class="zp-footer__grid">
                    
                    <!-- About Column -->
                    <div class="zp-footer__column">
                        <h4 class="zp-footer__title">About ZipPicks</h4>
                        <p class="zp-footer__text">The definitive local discovery platform revolutionizing how people find, experience, and share local businesses.</p>
                        <div class="zp-footer__social">
                            <?php do_action('zippicks_footer_social_links'); ?>
                        </div>
                    </div>
                    
                    <!-- Quick Links -->
                    <div class="zp-footer__column">
                        <h4 class="zp-footer__title">Discover</h4>
                        <?php if (has_nav_menu('footer')) : ?>
                            <?php wp_nav_menu(array(
                                'theme_location' => 'footer',
                                'menu_class' => 'zp-footer__menu',
                                'container' => false,
                                'depth' => 1,
                            )); ?>
                        <?php else : ?>
                            <ul class="zp-footer__menu">
                                <li><a href="<?php echo home_url('/vibes/'); ?>">Explore Vibes</a></li>
                                <li><a href="<?php echo home_url('/businesses/'); ?>">Find Businesses</a></li>
                                <li><a href="<?php echo home_url('/critics/'); ?>">Top Critics</a></li>
                                <li><a href="<?php echo home_url('/about/'); ?>">About Us</a></li>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Business Column -->
                    <div class="zp-footer__column">
                        <h4 class="zp-footer__title">For Businesses</h4>
                        <ul class="zp-footer__menu">
                            <li><a href="<?php echo home_url('/business-signup/'); ?>">Claim Your Business</a></li>
                            <li><a href="<?php echo home_url('/pricing/'); ?>">Pricing</a></li>
                            <li><a href="<?php echo home_url('/how-we-score/'); ?>">How We Score</a></li>
                            <li><a href="<?php echo home_url('/contact/'); ?>">Contact Us</a></li>
                        </ul>
                    </div>
                    
                    <!-- Newsletter Column -->
                    <div class="zp-footer__column">
                        <h4 class="zp-footer__title">Stay Updated</h4>
                        <p class="zp-footer__text">Get the latest discoveries and updates from ZipPicks.</p>
                        <?php do_action('zippicks_footer_newsletter_form'); ?>
                    </div>
                    
                </div>
            </div>
        </div>
        
        <!-- Footer Bottom -->
        <div class="zp-footer__bottom">
            <div class="zp-container">
                <div class="zp-footer__bottom-content">
                    <div class="zp-footer__copyright">
                        &copy; <?php echo date('Y'); ?> ZipPicks. All rights reserved.
                    </div>
                    <div class="zp-footer__legal">
                        <a href="<?php echo home_url('/privacy/'); ?>">Privacy Policy</a>
                        <span class="zp-footer__separator">|</span>
                        <a href="<?php echo home_url('/terms/'); ?>">Terms of Service</a>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</footer>

<?php do_action('zippicks_after_footer'); ?>