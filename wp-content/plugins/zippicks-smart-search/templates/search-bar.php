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
$search_query = '';
if (isset($_GET['q'])) {
    // Verify nonce if present (for enhanced security)
    if (isset($_GET['zippicks_search_nonce']) && !wp_verify_nonce($_GET['zippicks_search_nonce'], 'zippicks_search_form')) {
        // Invalid nonce, but don't block search completely for GET requests
        // Just log it for monitoring
        if (class_exists('\\ZipPicks\\SmartSearch\\Error_Tracker')) {
            \ZipPicks\SmartSearch\Error_Tracker::instance()->track_error(
                'security',
                'Invalid search nonce',
                ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'query' => sanitize_text_field($_GET['q'])]
            );
        }
    }
    
    // Validate and sanitize search query
    $raw_query = $_GET['q'];
    if (class_exists('\\ZipPicks\\SmartSearch\\Security_Manager')) {
        $validated = \ZipPicks\SmartSearch\Security_Manager::validate_search_query($raw_query);
        if (is_wp_error($validated)) {
            // Invalid query, use empty string
            $search_query = '';
            // Optionally show error message
            $search_error = $validated->get_error_message();
        } else {
            $search_query = sanitize_text_field($raw_query);
        }
    } else {
        // Fallback to basic sanitization
        $search_query = sanitize_text_field($raw_query);
    }
}
?>

<div class="zippicks-search-widget">
    <?php if (!empty($title)) : ?>
        <h2 class="zippicks-search-title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    
    <?php if (!empty($search_error)) : ?>
    <div class="zippicks-search-error notice notice-error">
        <p><?php echo esc_html($search_error); ?></p>
    </div>
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
        <?php wp_nonce_field('zippicks_search_form', 'zippicks_search_nonce'); ?>
        <div class="zippicks-search-wrapper">
            <input type="text" 
                   name="q" 
                   class="zippicks-search-input" 
                   placeholder="<?php esc_attr_e('Search for vibes, places, or experiences...', 'zippicks-smart-search'); ?>"
                   value="<?php echo esc_attr($search_query); ?>"
                   autocomplete="off"
                   required
                   pattern="[a-zA-Z0-9\s\-\',&amp;.!?]{1,100}"
                   title="<?php esc_attr_e('Please enter a valid search query (letters, numbers, spaces, and basic punctuation only)', 'zippicks-smart-search'); ?>"
                   maxlength="100">
            <button type="submit" class="zippicks-search-button">
                <?php _e('Search', 'zippicks-smart-search'); ?>
            </button>
        </div>
    </form>
    
    <div class="zippicks-search-results" style="display: none;"></div>
</div>