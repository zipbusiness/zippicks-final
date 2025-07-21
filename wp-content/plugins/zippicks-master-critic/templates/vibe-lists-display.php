<?php
/**
 * Template for displaying Master Critic lists on vibe pages
 *
 * @package ZipPicks_Master_Critic
 * @var array $lists Array of master critic list posts
 * @var int $vibe_id Current vibe ID
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($lists)) {
    return;
}
?>

<div class="zippicks-vibe-lists-section">
    <h2 class="section-title">Top 10 Lists for This Vibe</h2>
    
    <div class="master-critic-lists-container">
        <?php foreach ($lists as $list) : ?>
            <?php 
            // Get list metadata
            $topic = get_post_meta($list->ID, '_mc_topic', true);
            $location = get_post_meta($list->ID, '_mc_location', true);
            $category = get_post_meta($list->ID, '_mc_category', true);
            $list_category = get_post_meta($list->ID, '_mc_list_category', true);
            $restaurants = json_decode(get_post_meta($list->ID, '_mc_restaurants', true), true);
            $restaurant_count = is_array($restaurants) ? count($restaurants) : 0;
            
            // Get category info
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-category-handler.php';
            $category_info = ZipPicks_Master_Critic_Category_Handler::get_category($list_category);
            ?>
            
            <article class="master-critic-list-card" data-list-id="<?php echo esc_attr($list->ID); ?>">
                <div class="list-card-header">
                    <?php if ($category_info) : ?>
                        <span class="list-category-badge" style="background-color: <?php echo esc_attr($category_info['color'] ?? '#6B46C1'); ?>">
                            <span class="<?php echo esc_attr($category_info['icon']); ?>"></span>
                            <?php echo esc_html($category_info['name']); ?>
                        </span>
                    <?php endif; ?>
                    
                    <h3 class="list-card-title">
                        <a href="<?php echo get_permalink($list->ID); ?>">
                            <?php echo esc_html($list->post_title); ?>
                        </a>
                    </h3>
                </div>
                
                <div class="list-card-meta">
                    <span class="meta-location">
                        <span class="dashicons dashicons-location"></span>
                        <?php echo esc_html($location); ?>
                    </span>
                    <span class="meta-count">
                        <span class="dashicons dashicons-store"></span>
                        <?php echo $restaurant_count; ?> places
                    </span>
                    <span class="meta-date">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php echo get_the_date('M j, Y', $list); ?>
                    </span>
                </div>
                
                <?php if ($list->post_excerpt) : ?>
                    <div class="list-card-excerpt">
                        <?php echo wp_trim_words($list->post_excerpt, 20); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($restaurant_count > 0 && is_array($restaurants)) : ?>
                    <div class="list-card-preview">
                        <ol class="preview-restaurants">
                            <?php 
                            $preview_count = min(3, $restaurant_count);
                            for ($i = 0; $i < $preview_count; $i++) : 
                                $restaurant = $restaurants[$i];
                            ?>
                                <li class="preview-restaurant">
                                    <span class="restaurant-name"><?php echo esc_html($restaurant['name'] ?? ''); ?></span>
                                    <?php if (isset($restaurant['score'])) : ?>
                                        <span class="restaurant-score"><?php echo number_format($restaurant['score'], 1); ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endfor; ?>
                        </ol>
                        
                        <?php if ($restaurant_count > 3) : ?>
                            <p class="more-restaurants">
                                +<?php echo ($restaurant_count - 3); ?> more places
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="list-card-actions">
                    <a href="<?php echo get_permalink($list->ID); ?>" class="button button-primary view-full-list">
                        View Full List
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>

<style>
.zippicks-vibe-lists-section {
    margin: 3rem 0;
}

.master-critic-lists-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 2rem;
    margin-top: 1.5rem;
}

.master-critic-list-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.master-critic-list-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.list-card-header {
    margin-bottom: 1rem;
}

.list-category-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: #6B46C1;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.list-card-title {
    margin: 0.5rem 0;
    font-size: 1.25rem;
}

.list-card-title a {
    color: #1a1a1a;
    text-decoration: none;
}

.list-card-title a:hover {
    color: #6B46C1;
}

.list-card-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.875rem;
    color: #666;
    margin-bottom: 1rem;
}

.list-card-meta span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.list-card-meta .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.list-card-excerpt {
    color: #666;
    margin-bottom: 1rem;
}

.list-card-preview {
    background: #f8f8f8;
    border-radius: 4px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.preview-restaurants {
    list-style: none;
    margin: 0;
    padding: 0;
}

.preview-restaurant {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e0e0e0;
}

.preview-restaurant:last-child {
    border-bottom: none;
}

.restaurant-score {
    background: #6B46C1;
    color: white;
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
}

.more-restaurants {
    margin: 0.5rem 0 0;
    color: #666;
    font-style: italic;
}

.view-full-list {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.view-full-list .dashicons {
    transition: transform 0.2s;
}

.view-full-list:hover .dashicons {
    transform: translateX(4px);
}
</style>