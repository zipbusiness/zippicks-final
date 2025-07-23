<?php
/**
 * Search Bar Template
 * 
 * @package ZipPicks_Smart_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current location
$location = null;
if (class_exists('\\ZipPicks\\Geo\\Location_Detector')) {
    $detector = new \ZipPicks\Geo\Location_Detector();
    $location = $detector->get_user_location(get_current_user_id());
}

// Get search parameters
$search_query = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
?>

<div class="zippicks-search-widget">
    <?php if (!empty($title)) : ?>
        <h2 class="zippicks-search-title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    
    <?php if ($location && isset($location['city'])) : ?>
    <div class="zippicks-location-wrapper">
        <span class="dashicons dashicons-location"></span>
        <span class="zippicks-location-display">
            <?php echo esc_html($location['city']); 
                  if (isset($location['state'])) echo ', ' . esc_html($location['state']); ?>
        </span>
        <a href="#" class="zippicks-update-location"><?php _e('Change', 'zippicks-smart-search'); ?></a>
    </div>
    <?php endif; ?>
    
    <form class="zippicks-search-form" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <div class="zippicks-search-wrapper">
            <input type="text" 
                   name="q" 
                   class="zippicks-search-input" 
                   placeholder="<?php esc_attr_e('Search for vibes, places, or experiences...', 'zippicks-smart-search'); ?>"
                   value="<?php echo esc_attr($search_query); ?>"
                   autocomplete="off"
                   required>
            <button type="submit" class="zippicks-search-button">
                <?php _e('Search', 'zippicks-smart-search'); ?>
            </button>
        </div>
    </form>
    
    <div class="zippicks-search-results" style="display: none;"></div>
</div>