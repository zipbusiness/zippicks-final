<?php
/**
 * Vibe Archive Template - Server-Rendered SEO-Optimized Version
 * Full-width responsive layout with GeneratePress compatibility
 * 
 * @package ZipPicksVibes
 */

defined('ABSPATH') || exit;

get_header();

global $wpdb;

// DEBUG: Log the page load
error_log('[VIBE ARCHIVE] Page loaded - URL: ' . $_SERVER['REQUEST_URI']);

// Get selected category from URL parameter
$selected_category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
error_log('[VIBE ARCHIVE] Selected category: ' . ($selected_category ?: 'none (show all)'));

// Get all categories from database
$categories_query = "
    SELECT id, name, slug, description, order_position 
    FROM {$wpdb->prefix}zippicks_vibe_categories 
    ORDER BY order_position ASC, name ASC
";
$categories = $wpdb->get_results($categories_query);
error_log('[VIBE ARCHIVE] Found ' . count($categories) . ' categories');

// Initialize variables
$display_vibes = [];
$selected_category_id = null;

// If a category is selected, look up its ID
if (!empty($selected_category)) {
    $selected_category_id = $wpdb->get_var($wpdb->prepare("
        SELECT id 
        FROM {$wpdb->prefix}zippicks_vibe_categories 
        WHERE slug = %s
    ", $selected_category));
    error_log('[VIBE ARCHIVE] Category ID for slug "' . $selected_category . '": ' . ($selected_category_id ?: 'not found'));
}

// Always get ALL vibes for client-side filtering
$vibes_query = "
    SELECT v.*, 
           (SELECT GROUP_CONCAT(category_id SEPARATOR ' ') 
            FROM {$wpdb->prefix}zippicks_vibe_category_assignments 
            WHERE vibe_id = v.id) as category_ids
    FROM {$wpdb->prefix}zippicks_vibes v
    WHERE v.is_active = 1
    ORDER BY v.order_position ASC, v.name ASC
";
$display_vibes = $wpdb->get_results($vibes_query);
error_log('[VIBE ARCHIVE] Loaded ' . count($display_vibes) . ' total active vibes for client-side filtering');

// Get business counts for each vibe
$vibe_ids = array_map(function($vibe) { return $vibe->id; }, $display_vibes);
if (!empty($vibe_ids)) {
    $vibe_ids_string = implode(',', array_map('intval', $vibe_ids));
    // Table exists but may be empty - this is fine
    $business_counts = $wpdb->get_results("
        SELECT vibe_id, COUNT(DISTINCT business_id) as count 
        FROM {$wpdb->prefix}zippicks_business_vibes 
        WHERE vibe_id IN ($vibe_ids_string)
        GROUP BY vibe_id
    ", OBJECT_K);
    
    // Add business counts to vibes
    foreach ($display_vibes as &$vibe) {
        $vibe->business_count = isset($business_counts[$vibe->id]) ? $business_counts[$vibe->id]->count : 0;
    }
    unset($vibe); // CRITICAL: Unset reference to prevent corruption
} else {
    // No vibes, so no business counts needed
    error_log('[VIBE ARCHIVE] No vibes to get business counts for');
}
?>

<!-- Full-width wrapper to break out of GeneratePress container -->
<div class="zp-vibes-wrapper">

    <!-- Hero Section with Premium Gradient -->
    <div class="zp-vibes-hero" style="background: linear-gradient(135deg, #194FAD 0%, #5A9BFF 100%); position: relative; overflow: hidden;">
        <!-- Subtle texture overlay -->
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 80% 80%, rgba(255,255,255,0.05) 0%, transparent 50%); pointer-events: none;"></div>
        
        <div class="zp-hero-content">
            <h1 class="zp-vibes-hero__title">Discover Your Vibe</h1>
            <p class="zp-vibes-hero__subtitle">Find the perfect spots that match your mood, occasion, and social context</p>
        </div>
    </div>

    <!-- Category Filters with Horizontal Scroll -->
    <div class="zp-category-filters">
        <div class="zp-section-label">Categories</div>
        <div class="category-scroll-wrapper">
            <!-- All button -->
            <button type="button"
                    class="category-pill<?php echo empty($selected_category) ? ' active' : ''; ?>"
                    data-category-slug="all"
                    data-category-id=""
                    aria-label="Show all vibes"
                    <?php echo empty($selected_category) ? 'aria-current="true"' : ''; ?>>
                All
            </button>
            <?php
            // Define priority order
            $priority_order = ['perfect-for', 'the-vibe', 'cravings', 'the-crowd', 'global-flavors'];
            $priority_categories = [];
            $other_categories = [];
            
            // Sort categories into priority and other
            foreach ($categories as $category) {
                if (in_array($category->slug, $priority_order)) {
                    $priority_categories[$category->slug] = $category;
                } else {
                    $other_categories[] = $category;
                }
            }
            
            // Display priority categories in order
            foreach ($priority_order as $priority_slug) {
                if (isset($priority_categories[$priority_slug])) {
                    $category = $priority_categories[$priority_slug];
                    $active_class = ($category->slug === $selected_category) ? ' active' : '';
                    $is_active = ($category->slug === $selected_category);
                    ?>
                    <button type="button"
                            class="category-pill<?php echo $active_class; ?>"
                            data-category-slug="<?php echo esc_attr($category->slug); ?>"
                            data-category-id="<?php echo esc_attr($category->id); ?>"
                            aria-label="Filter by <?php echo esc_attr($category->name); ?>"
                            <?php echo $is_active ? 'aria-current="true"' : ''; ?>>
                        <?php echo esc_html($category->name); ?>
                    </button>
                    <?php
                }
            }
            
            // Display remaining categories alphabetically
            usort($other_categories, function($a, $b) {
                return strcasecmp($a->name, $b->name);
            });
            
            foreach ($other_categories as $category) :
                $active_class = ($category->slug === $selected_category) ? ' active' : '';
                $is_active = ($category->slug === $selected_category);
                ?>
                <button type="button"
                        class="category-pill<?php echo $active_class; ?>"
                        data-category-slug="<?php echo esc_attr($category->slug); ?>"
                        data-category-id="<?php echo esc_attr($category->id); ?>"
                        aria-label="Filter by <?php echo esc_attr($category->name); ?>"
                        <?php echo $is_active ? 'aria-current="true"' : ''; ?>>
                    <?php echo esc_html($category->name); ?>
                </button>
            <?php 
            endforeach;
            ?>
        </div>
        <div class="zp-vibes-count">
            <span id="vibes-count"><?php echo count($display_vibes); ?></span> vibes found
        </div>
    </div>

    <!-- Vibes Section -->
    <div class="zp-vibes-section">
        <div class="zp-container zp-container--wide">
            <div class="zp-vibes-header">
                <h2 class="zp-vibes-header__title" style="color: #194FAD;">Explore All Vibes</h2>
                <p class="zp-vibes-header__subtitle">Browse our full collection of curated vibes</p>
            </div>
            
            <!-- Include the server-rendered vibe list -->
            <?php 
            // Handle no vibes found
            if (empty($display_vibes)) : ?>
                <div class="zp-no-vibes-found">
                    <p><?php echo esc_html__('No vibes found in this category.', 'zippicks-vibes'); ?></p>
                </div>
            <?php else : ?>
                <div class="zp-vibes-grid zp-vibes-grid--responsive">
                    <?php
                    foreach ($display_vibes as $vibe) {
                        // Pass vibe data to item template
                        $item_options = [
                            'item_class' => 'zp-vibe-card',
                            'show_count' => true,
                            'show_description' => true,
                            'obfuscate' => false, // Force server-side rendering
                            'show_schema' => true,
                            'category_ids' => $vibe->category_ids // Pass category IDs for data attribute
                        ];
                        
                        // Include the item template
                        include ZIPPICKS_VIBES_DIR . 'templates/partials/vibe-item.php';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- .zp-vibes-wrapper -->

<?php get_footer(); ?>