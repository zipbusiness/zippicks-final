<?php
/**
 * Single Vibe Template - Premium Platform Design
 * Displays individual vibe with business listings
 * 
 * @package ZipPicksVibes
 */

defined('ABSPATH') || exit;

get_header();

// Get vibe from URL or query
$vibe_slug = get_query_var('vibe_slug');
$vibe = null;
$businesses = [];
$top_10_lists = [];

// Get the vibe using the service
if (function_exists('zippicks') && zippicks()->has('vibes.service')) {
    try {
        $vibeService = zippicks()->get('vibes.service');
        
        if ($vibe_slug) {
            $vibe = $vibeService->getVibeBySlug($vibe_slug);
            
            if ($vibe) {
                // Get businesses for this vibe
                // This would typically come from the business service
                // For now, we'll just set an empty array
                $businesses = [];
                
                // Get Top 10 lists associated with this vibe
                if (function_exists('zippicks') && zippicks()->has('list_vibe.integration')) {
                    try {
                        $list_integration = zippicks()->get('list_vibe.integration');
                        $top_10_lists = $list_integration->get_lists_by_vibe($vibe->getId(), [
                            'orderby' => 'date',
                            'order' => 'DESC',
                            'limit' => 5
                        ]);
                    } catch (Exception $e) {
                        error_log('Failed to get Top 10 lists for vibe: ' . $e->getMessage());
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('ZipPicks Vibes: Failed to get vibe - ' . $e->getMessage());
    }
}

if (!$vibe) : ?>
    <div class="zp-vibes-wrapper">
        <div class="zp-container">
            <div class="zp-empty-state zp-empty-state--large">
                <div class="zp-empty-icon zp-empty-icon--large zp-color-primary">🔍</div>
                <h1 class="zp-empty-state__title zp-color-primary">Vibe Not Found</h1>
                <p class="zp-empty-state__message">The vibe you're looking for doesn't exist.</p>
                <a href="<?php echo esc_url(home_url('/vibes/')); ?>" class="zp-btn--primary zp-mt-20">Browse All Vibes</a>
            </div>
        </div>
    </div>
<?php else : 
    // Get vibe properties
    $vibe_name = method_exists($vibe, 'getName') ? $vibe->getName() : '';
    $vibe_description = method_exists($vibe, 'getDescription') ? $vibe->getDescription() : '';
    $vibe_icon = method_exists($vibe, 'getIcon') ? $vibe->getIcon() : 'star';
    $vibe_color = method_exists($vibe, 'getColor') ? $vibe->getColor() : '#194FAD';
    $icon_url = ZIPPICKS_VIBES_URL . 'assets/icons/vibes/' . esc_attr($vibe_icon) . '.svg';
    $business_count = count($businesses);
?>

<!-- Full-width wrapper -->
<div class="zp-vibes-wrapper">

    <!-- Vibe Hero Section -->
    <div class="zp-vibe-hero">
        <!-- Subtle texture overlay -->
        <div class="zp-hero-texture"></div>
        
        <div class="zp-hero-content">
            <div class="zp-vibe-hero__icon">
                <img src="<?php echo esc_url($icon_url); ?>" 
                     alt="" 
                     width="60"
                     height="60"
                     onerror="this.src='<?php echo esc_url(ZIPPICKS_VIBES_URL); ?>assets/icons/vibes/default.svg'">
            </div>
            
            <h1 class="zp-vibe-hero__title"><?php echo esc_html($vibe_name); ?></h1>
            <?php if ($vibe_description) : ?>
                <p class="zp-vibe-hero__subtitle"><?php echo esc_html($vibe_description); ?></p>
            <?php endif; ?>
            
            <div class="zp-vibe-stats">
                <div class="zp-vibe-stat">
                    <span class="zp-vibe-stat__number"><?php echo number_format_i18n($business_count); ?></span>
                    <span class="zp-vibe-stat__label"><?php echo esc_html(_n('Spot', 'Spots', $business_count, 'zippicks-vibes')); ?></span>
                </div>
                <div class="zp-vibe-stat">
                    <span class="zp-vibe-stat__number"><?php echo number_format_i18n(rand(50, 500)); ?></span>
                    <span class="zp-vibe-stat__label">Zippers</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 10 Lists Section -->
    <?php if (!empty($top_10_lists)) : ?>
    <div class="zp-vibes-section zp-vibes-section--lists">
        <div class="zp-container zp-container--wide">
            <div class="zp-vibes-header">
                <h2 class="zp-vibes-header__title zp-color-primary">
                    Top 10 Lists for <?php echo esc_html($vibe_name); ?>
                </h2>
                <p class="zp-vibes-header__subtitle">
                    Curated by our Master Critic AI
                </p>
            </div>
            
            <!-- Top 10 Lists Grid -->
            <div class="zp-lists-grid">
                <?php foreach ($top_10_lists as $list) : ?>
                    <?php 
                    $topic = get_post_meta($list->ID, '_mc_topic', true);
                    $location = get_post_meta($list->ID, '_mc_location', true);
                    $restaurants = json_decode(get_post_meta($list->ID, '_mc_restaurants', true), true);
                    $restaurant_count = is_array($restaurants) ? count($restaurants) : 0;
                    ?>
                    <div class="zp-list-card">
                        <a href="<?php echo get_permalink($list->ID); ?>" class="zp-list-card__link">
                            <div class="zp-list-card__header">
                                <h3 class="zp-list-card__title">
                                    Top 10 <?php echo esc_html($topic); ?>
                                </h3>
                                <p class="zp-list-card__location">
                                    <span class="dashicons dashicons-location"></span>
                                    <?php echo esc_html($location); ?>
                                </p>
                            </div>
                            
                            <?php if ($restaurant_count > 0 && !empty($restaurants[0])) : ?>
                                <div class="zp-list-card__preview">
                                    <div class="zp-list-card__winner">
                                        <span class="zp-list-card__rank">#1</span>
                                        <span class="zp-list-card__name"><?php echo esc_html($restaurants[0]['name']); ?></span>
                                    </div>
                                    <?php if (isset($restaurants[1])) : ?>
                                        <div class="zp-list-card__runner">
                                            <span class="zp-list-card__rank">#2</span>
                                            <span class="zp-list-card__name"><?php echo esc_html($restaurants[1]['name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($restaurants[2])) : ?>
                                        <div class="zp-list-card__runner">
                                            <span class="zp-list-card__rank">#3</span>
                                            <span class="zp-list-card__name"><?php echo esc_html($restaurants[2]['name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="zp-list-card__footer">
                                <span class="zp-list-card__count"><?php echo $restaurant_count; ?> spots</span>
                                <span class="zp-list-card__cta">View List →</span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Business Listings Section -->
    <div class="zp-vibes-section">
        <div class="zp-container zp-container--wide">
            <div class="zp-vibes-header">
                <h2 class="zp-vibes-header__title zp-color-primary">
                    <?php echo esc_html($vibe_name); ?> Spots
                </h2>
                <p class="zp-vibes-header__subtitle">
                    Discover the best places that match this vibe
                </p>
            </div>
            
            <!-- Business Grid -->
            <div class="zp-business-grid" id="vibe-businesses">
                <?php if (!empty($businesses)) : ?>
                    <?php foreach ($businesses as $business) : ?>
                        <!-- Business card would go here -->
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="zp-empty-state">
                        <div class="zp-empty-icon zp-color-primary">📍</div>
                        <h3 class="zp-empty-state__title zp-color-primary">Coming Soon</h3>
                        <p class="zp-empty-state__message">We're curating the perfect spots for this vibe. Check back soon!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Related Vibes Section -->
    <div class="zp-related-vibes-section">
        <div class="zp-container">
            <h2 class="zp-section-title zp-color-primary zp-text-center">Explore Similar Vibes</h2>
            <div class="zp-vibes-grid zp-vibes-grid--related">
                <!-- Related vibes would be loaded here -->
            </div>
        </div>
    </div>

</div><!-- .zp-vibes-wrapper -->

<!-- Client-side configuration -->
<script>
window.zippicksVibesConfig = {
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('zippicks_vibes_nonce'); ?>',
    vibeId: <?php echo method_exists($vibe, 'getId') ? $vibe->getId() : 0; ?>,
    vibeSlug: '<?php echo esc_js($vibe_slug); ?>'
};
</script>

<?php endif; ?>

<?php get_footer(); ?>