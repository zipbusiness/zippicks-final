<?php
/**
 * Vibe Item Partial - Database-driven version
 *
 * @package ZipPicksVibes
 */

defined('ABSPATH') || exit;

// Validate vibe object
if (!isset($vibe) || !is_object($vibe)) {
    return;
}

// Parse options with defaults
$options = wp_parse_args($item_options ?? $options ?? [], [
    'item_class' => 'vibe-card',
    'show_description' => true,
    'show_count' => false,
    'obfuscate' => !is_user_logged_in() || !current_user_can('edit_posts'),
    'show_schema' => true,
    'category_ids' => '' // Space-separated category IDs passed from archive
]);

// Generate unique hash for anti-scraping
$vibe_id = isset($vibe->id) ? intval($vibe->id) : 0;
$vibe_hash = substr(md5($vibe_id . wp_salt('secure')), 0, 8);

// Build CSS classes with obfuscated identifier
$item_class = esc_attr($options['item_class']) . ' zp-v-' . $vibe_hash;

// Get vibe properties from database object
$vibe_name = isset($vibe->name) ? $vibe->name : '';
$vibe_slug = isset($vibe->slug) ? $vibe->slug : '';
$vibe_description = isset($vibe->description) ? $vibe->description : '';
$vibe_icon = isset($vibe->icon) ? $vibe->icon : 'star';
$vibe_color = isset($vibe->color) ? $vibe->color : '#000000';
$vibe_url = home_url('/vibes/' . $vibe_slug . '/');
$vibe_count = isset($vibe->business_count) ? intval($vibe->business_count) : 0;

// Get category IDs - either from options or from vibe object
$category_ids = !empty($options['category_ids']) ? $options['category_ids'] : (isset($vibe->category_ids) ? $vibe->category_ids : '');

// Construct icon URL
$icon_url = ZIPPICKS_VIBES_URL . 'assets/icons/vibes/' . esc_attr($vibe_icon) . '.svg';

// Get zipper count if available
$zipper_count = isset($vibe->zipper_count) ? intval($vibe->zipper_count) : 0;
?>

<!-- GenerateBlocks-compatible vibe item -->
<article class="<?php echo $item_class; ?> zp-vibe-card gb-block-element" 
         data-vibe="<?php echo $options['obfuscate'] ? base64_encode(hash('sha256', $vibe_id . wp_salt(), true)) : esc_attr($vibe_id); ?>"
         data-category="<?php echo esc_attr($category_ids); ?>"
         <?php if ($options['show_schema']): ?>
         itemprop="item" 
         itemscope 
         itemtype="https://schema.org/Thing"
         <?php endif; ?>>
    
    <a href="<?php echo esc_url($vibe_url); ?>" 
       class="zp-vibe-card__link"
       <?php if ($options['show_schema']): ?>itemprop="url"<?php endif; ?>>
        
        <div class="zp-vibe-card__inner">
            <!-- Icon Container -->
            <div class="zp-vibe-card__icon-wrapper">
                <div class="zp-vibe-card__icon" 
                     aria-hidden="true">
                    <img src="<?php echo esc_url($icon_url); ?>" 
                         alt="" 
                         loading="lazy"
                         width="24"
                         height="24"
                         class="zp-vibe-card__icon-img"
                         onerror="this.src='<?php echo esc_url(ZIPPICKS_VIBES_URL); ?>assets/icons/vibes/default.svg'">
                </div>
            </div>
            
            <!-- Content Container -->
            <div class="zp-vibe-card__content">
                <!-- Name -->
                <h3 class="zp-vibe-card__title" 
                    <?php if ($options['show_schema']): ?>itemprop="name"<?php endif; ?>>
                    <?php echo esc_html($vibe_name); ?>
                </h3>
                
                <!-- Description -->
                <?php if ($options['show_description'] && !empty($vibe_description)): ?>
                    <p class="zp-vibe-card__description" 
                       <?php if ($options['show_schema']): ?>itemprop="description"<?php endif; ?>>
                        <?php echo esc_html($vibe_description); ?>
                    </p>
                <?php endif; ?>
            
                <!-- Metadata (spots and zippers) -->
                <?php if ($vibe_count > 0 || $zipper_count > 0): ?>
                <div class="zp-vibe-card__meta">
                    <span class="zp-vibe-card__meta-item">
                        <?php echo number_format_i18n($vibe_count); ?> <?php echo esc_html(_n('spot', 'spots', $vibe_count, 'zippicks-vibes')); ?>
                    </span>
                    <?php if ($zipper_count > 0): ?>
                        <span class="zp-vibe-card__meta-separator">·</span>
                        <span class="zp-vibe-card__meta-item">
                            <?php echo number_format_i18n($zipper_count); ?> <?php echo esc_html(_n('zipper', 'zippers', $zipper_count, 'zippicks-vibes')); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Accessibility label -->
        <span class="screen-reader-text">
            <?php 
            printf(
                esc_html__('Explore %s vibe', 'zippicks-vibes'),
                esc_html($vibe_name)
            ); 
            ?>
        </span>
    </a>
    
    <!-- Anti-scraping measures -->
    <?php if ($options['obfuscate']): ?>
        <!-- Hidden fingerprint -->
        <span class="zp-fp" 
              data-hash="<?php echo esc_attr($vibe_hash); ?>" 
              data-ts="<?php echo time(); ?>"
              aria-hidden="true"></span>
        
        <!-- Invisible copy trap -->
        <span class="zp-trap">
            <?php echo substr(md5(uniqid()), 0, 16); ?>
        </span>
    <?php endif; ?>
    
    <!-- Schema.org metadata -->
    <?php if ($options['show_schema']): ?>
        <meta itemprop="identifier" content="vibe-<?php echo esc_attr($vibe_id); ?>" />
        <?php if (!empty($vibe_color)): ?>
            <meta itemprop="color" content="<?php echo esc_attr($vibe_color); ?>" />
        <?php endif; ?>
    <?php endif; ?>
</article>