<?php
/**
 * Author Favorites Template
 * 
 * This template displays the favorites section on the author.php page
 * with advanced location filtering capabilities
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = isset($user_id) ? $user_id : get_current_user_id();
$is_own_profile = ($user_id === get_current_user_id());
?>

<div class="zp-favorites-section" id="user-favorites">
    <div class="zp-favorites-header">
        <h2 class="zp-favorites-title">
            <?php if ($is_own_profile): ?>
                <?php _e('My Favorites', 'zippicks-favorites'); ?>
            <?php else: ?>
                <?php printf(__('%s\'s Favorites', 'zippicks-favorites'), get_the_author_meta('display_name', $user_id)); ?>
            <?php endif; ?>
            <span class="zp-favorites-count" data-user-id="<?php echo esc_attr($user_id); ?>">
                <!-- Count loaded via JS -->
            </span>
        </h2>
    </div>

    <?php if ($is_own_profile): ?>
    <!-- Location Filters -->
    <div class="zp-favorites-filters">
        <!-- City Dropdown -->
        <div class="zp-location-filter">
            <label for="zp-favorites-city-filter">
                <span class="dashicons dashicons-location"></span>
                <?php _e('Location:', 'zippicks-favorites'); ?>
            </label>
            <select id="zp-favorites-city-filter">
                <option value="all"><?php _e('All Locations', 'zippicks-favorites'); ?></option>
                <!-- Cities populated via JS -->
            </select>
        </div>

        <!-- Zip Code Search -->
        <form id="zp-favorites-location-form" class="zp-location-search">
            <input 
                type="text" 
                id="zp-favorites-zip" 
                placeholder="<?php esc_attr_e('Zip code', 'zippicks-favorites'); ?>"
                pattern="[0-9]{5}"
                maxlength="5"
            >
            <select id="zp-favorites-radius">
                <option value="5"><?php _e('5 mi', 'zippicks-favorites'); ?></option>
                <option value="10"><?php _e('10 mi', 'zippicks-favorites'); ?></option>
                <option value="25"><?php _e('25 mi', 'zippicks-favorites'); ?></option>
                <option value="50"><?php _e('50 mi', 'zippicks-favorites'); ?></option>
            </select>
            <button type="submit" class="button button-secondary">
                <?php _e('Search', 'zippicks-favorites'); ?>
            </button>
        </form>

        <!-- Current Location -->
        <button id="zp-use-current-location" class="button button-secondary">
            <span class="dashicons dashicons-location-alt"></span>
            <?php _e('Use Current Location', 'zippicks-favorites'); ?>
        </button>

        <!-- Search Within Favorites -->
        <div class="zp-favorites-search">
            <input 
                type="search" 
                id="zp-favorites-search" 
                placeholder="<?php esc_attr_e('Search in favorites...', 'zippicks-favorites'); ?>"
            >
        </div>
    </div>

    <!-- Sort and Filter Options -->
    <div class="zp-favorites-options">
        <select id="zp-favorites-sort">
            <option value="date"><?php _e('Recently Added', 'zippicks-favorites'); ?></option>
            <option value="name"><?php _e('Name A-Z', 'zippicks-favorites'); ?></option>
            <option value="distance"><?php _e('Distance', 'zippicks-favorites'); ?></option>
            <option value="rating"><?php _e('Rating', 'zippicks-favorites'); ?></option>
        </select>

        <select id="zp-favorites-vibe-filter">
            <option value="all"><?php _e('All Vibes', 'zippicks-favorites'); ?></option>
            <?php
            // Get all vibes
            $vibes = get_terms(['taxonomy' => 'business_vibe', 'hide_empty' => false]);
            foreach ($vibes as $vibe) {
                printf('<option value="%s">%s</option>', esc_attr($vibe->slug), esc_html($vibe->name));
            }
            ?>
        </select>

        <!-- View Toggle -->
        <div class="zp-view-toggle">
            <button data-view="grid" class="active" title="<?php esc_attr_e('Grid View', 'zippicks-favorites'); ?>">
                <span class="dashicons dashicons-grid-view"></span>
            </button>
            <button data-view="list" title="<?php esc_attr_e('List View', 'zippicks-favorites'); ?>">
                <span class="dashicons dashicons-list-view"></span>
            </button>
            <button data-view="map" title="<?php esc_attr_e('Map View', 'zippicks-favorites'); ?>">
                <span class="dashicons dashicons-location-alt"></span>
            </button>
        </div>
    </div>

    <!-- Location Info Bar -->
    <div id="zp-location-info" style="display: none;"></div>

    <?php endif; ?>

    <!-- Favorites List Container -->
    <div id="zp-favorites-list" class="zp-favorites-container" data-user-id="<?php echo esc_attr($user_id); ?>">
        <div class="zp-loading">
            <span class="spinner is-active"></span>
            <p><?php _e('Loading favorites...', 'zippicks-favorites'); ?></p>
        </div>
    </div>

    <!-- Pagination -->
    <div id="zp-favorites-pagination" class="zp-pagination" style="display: none;">
        <!-- Pagination added via JS -->
    </div>

    <?php if ($is_own_profile): ?>
    <!-- Favorites Stats/Insights -->
    <div class="zp-favorites-insights" id="zp-favorites-insights" style="display: none;">
        <h3><?php _e('Your Taste Profile', 'zippicks-favorites'); ?></h3>
        <div class="zp-insights-content">
            <!-- Insights loaded via JS -->
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Map Modal (hidden by default) -->
<div id="zp-favorites-map-modal" class="zp-modal" style="display: none;">
    <div class="zp-modal-content">
        <span class="zp-modal-close">&times;</span>
        <div id="zp-favorites-map" style="height: 500px;"></div>
    </div>
</div>

<script type="text/javascript">
// Initialize favorites when document is ready
jQuery(document).ready(function($) {
    // Load initial favorites
    const userId = $('#zp-favorites-list').data('user-id');
    
    // Trigger initial load
    $(document).trigger('zippicks:load-favorites', {
        userId: userId,
        isOwnProfile: <?php echo $is_own_profile ? 'true' : 'false'; ?>
    });
});
</script>