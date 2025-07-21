<?php
/**
 * Vibe List Partial - Server-Rendered Version
 *
 * @package ZipPicksVibes
 */

defined('ABSPATH') || exit;

// Ensure we have required variables
if (empty($vibes)) {
    ?>
    <div class="zp-empty-state">
        <div class="zp-empty-icon zp-color-primary">🔍</div>
        <h2 class="zp-empty-state__title zp-color-primary">No Vibes Found</h2>
        <p class="zp-empty-state__message">Check back soon for amazing vibes!</p>
    </div>
    <?php
    return;
}

// Default options
$options = wp_parse_args($options ?? [], [
    'container_class' => 'zippicks-vibes-grid',
    'item_class' => 'vibe-card',
    'columns' => 3,
    'show_count' => true,
    'show_description' => true,
    'obfuscate' => false, // Force server-side rendering
    'show_schema' => true
]);

// Pass display_vibes if available, otherwise use $vibes
$vibes_to_display = isset($display_vibes) ? $display_vibes : $vibes;

// Generate unique container ID for cache-friendly rendering
$container_id = 'vibes-' . substr(md5(uniqid()), 0, 8);
$container_class = esc_attr($options['container_class']) . ' zp-grid-' . substr(md5($container_id), 0, 6);
?>

<!-- GenerateBlocks-compatible grid container -->
<div id="<?php echo esc_attr($container_id); ?>" 
     class="gb-grid-wrapper <?php echo $container_class; ?>" 
     data-columns="<?php echo intval($options['columns']); ?>"
     data-render="server"
     itemscope 
     itemtype="https://schema.org/ItemList">
    
    <!-- Schema.org metadata -->
    <meta itemprop="numberOfItems" content="<?php echo count($vibes_to_display); ?>" />
    <meta itemprop="itemListOrder" content="Unordered" />
    
    <!-- Direct server-side rendering -->
    <div class="vibes-grid-inner gb-grid">
        <?php 
        $index = 0;
        foreach ($vibes_to_display as $vibe): 
            $index++;
            ?>
            <div class="gb-grid-column" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <meta itemprop="position" content="<?php echo $index; ?>" />
                <?php 
                // Pass vibe and options to item template
                $item_options = array_merge($options, ['obfuscate' => false]);
                include ZIPPICKS_VIBES_DIR . 'templates/partials/vibe-item.php'; 
                ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>