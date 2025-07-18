<?php
/**
 * Vibe Display Handler for Master Critic Lists
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

class ZipPicks_Master_Critic_Vibe_Display_Handler {
    
    /**
     * Initialize the display handler
     */
    public static function init() {
        // Hook into vibe single page display
        add_action('zippicks_vibe_after_description', [__CLASS__, 'display_related_lists'], 20);
        add_filter('zippicks_vibe_content_sections', [__CLASS__, 'add_lists_section'], 10, 2);
    }
    
    /**
     * Get Top 10 lists associated with a vibe
     *
     * @param int $vibe_id
     * @return array
     */
    public static function get_lists_for_vibe($vibe_id) {
        $args = [
            'post_type' => 'master_critic_list',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_mc_vibe_ids',
                    'value' => $vibe_id,
                    'compare' => 'LIKE'
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        return get_posts($args);
    }
    
    /**
     * Display related lists on vibe single page
     *
     * @param int $vibe_id
     */
    public static function display_related_lists($vibe_id) {
        $lists = self::get_lists_for_vibe($vibe_id);
        
        if (empty($lists)) {
            return;
        }
        
        // Load template
        $template_path = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'templates/vibe-lists-display.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }
    
    /**
     * Add lists section to vibe content sections
     *
     * @param array $sections
     * @param int $vibe_id
     * @return array
     */
    public static function add_lists_section($sections, $vibe_id) {
        $lists = self::get_lists_for_vibe($vibe_id);
        
        if (!empty($lists)) {
            $sections['top10_lists'] = [
                'title' => 'Top 10 Lists',
                'content' => self::render_lists_section($lists),
                'priority' => 30
            ];
        }
        
        return $sections;
    }
    
    /**
     * Render lists section HTML
     *
     * @param array $lists
     * @return string
     */
    private static function render_lists_section($lists) {
        ob_start();
        ?>
        <div class="master-critic-lists-section">
            <div class="lists-grid">
                <?php foreach ($lists as $list) : ?>
                    <?php 
                    $topic = get_post_meta($list->ID, '_mc_topic', true);
                    $location = get_post_meta($list->ID, '_mc_location', true);
                    $category = get_post_meta($list->ID, '_mc_category', true);
                    $restaurants = json_decode(get_post_meta($list->ID, '_mc_restaurants', true), true);
                    $restaurant_count = is_array($restaurants) ? count($restaurants) : 0;
                    ?>
                    <div class="list-card">
                        <h3 class="list-title">
                            <a href="<?php echo get_permalink($list->ID); ?>">
                                <?php echo esc_html($list->post_title); ?>
                            </a>
                        </h3>
                        <div class="list-meta">
                            <span class="location"><?php echo esc_html($location); ?></span>
                            <span class="separator">•</span>
                            <span class="count"><?php echo $restaurant_count; ?> places</span>
                        </div>
                        <?php if ($restaurant_count > 0 && is_array($restaurants)) : ?>
                            <div class="list-preview">
                                <?php 
                                $preview_items = array_slice($restaurants, 0, 3);
                                foreach ($preview_items as $index => $restaurant) : 
                                ?>
                                    <div class="preview-item">
                                        <span class="rank"><?php echo $index + 1; ?>.</span>
                                        <span class="name"><?php echo esc_html($restaurant['name'] ?? ''); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <a href="<?php echo get_permalink($list->ID); ?>" class="view-list-link">
                            View Full List →
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}