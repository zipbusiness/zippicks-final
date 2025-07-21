<?php
/**
 * Master Critic Router
 *
 * Handles custom routing for dynamic city/dish URLs and legacy redirects
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

class ZipPicks_Master_Critic_Router {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add rewrite rules
        add_action('init', [$this, 'add_rewrite_rules'], 10);
        
        // Add query vars
        add_filter('query_vars', [$this, 'add_query_vars']);
        
        // Handle template redirect
        add_action('template_redirect', [$this, 'handle_template_redirect']);
        
        // Handle canonical URL
        add_action('wp_head', [$this, 'add_canonical_url']);
        
        // Flush rewrite rules on activation
        register_activation_hook(ZIPPICKS_MASTER_CRITIC_PLUGIN_FILE, [$this, 'flush_rewrite_rules']);
    }
    
    /**
     * Add custom rewrite rules
     */
    public function add_rewrite_rules() {
        // Pattern: /city/top-10-dish/
        add_rewrite_rule(
            '^([^/]+)/top-10-([^/]+)/?$',
            'index.php?mc_city=$matches[1]&mc_dish=$matches[2]&post_type=master_critic_list',
            'top'
        );
        
        // Legacy pattern redirect: /zippicks_list/top-dish-in-city/
        add_rewrite_rule(
            '^zippicks_list/top-([^/]+)-in-([^/]+)/?$',
            'index.php?mc_legacy_redirect=1&mc_dish=$matches[1]&mc_city=$matches[2]',
            'top'
        );
    }
    
    /**
     * Add custom query vars
     *
     * @param array $vars Existing query vars
     * @return array
     */
    public function add_query_vars($vars) {
        $vars[] = 'mc_city';
        $vars[] = 'mc_dish';
        $vars[] = 'mc_legacy_redirect';
        return $vars;
    }
    
    /**
     * Handle template redirect
     */
    public function handle_template_redirect() {
        global $wp_query;
        
        // Handle legacy redirect
        if (get_query_var('mc_legacy_redirect')) {
            $city = get_query_var('mc_city');
            $dish = get_query_var('mc_dish');
            
            if ($city && $dish) {
                $new_url = home_url("/{$city}/top-10-{$dish}/");
                wp_redirect($new_url, 301);
                exit;
            }
        }
        
        // Handle dynamic city/dish route
        $city = get_query_var('mc_city');
        $dish = get_query_var('mc_dish');
        
        if ($city && $dish) {
            // Query for the matching post
            $args = [
                'post_type' => 'master_critic_list',
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => 'city_slug',
                        'value' => sanitize_title($city),
                        'compare' => '='
                    ],
                    [
                        'key' => 'dish_slug',
                        'value' => sanitize_title($dish),
                        'compare' => '='
                    ]
                ]
            ];
            
            $query = new WP_Query($args);
            
            if ($query->have_posts()) {
                // Set up the main query
                $query->the_post();
                $wp_query = $query;
                
                // Load the template
                $template = locate_template(['single-master_critic_list.php']);
                
                if (!$template) {
                    // Try plugin template directory
                    $plugin_template = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'templates/single-master_critic_list.php';
                    if (file_exists($plugin_template)) {
                        $template = $plugin_template;
                    }
                }
                
                if ($template) {
                    include($template);
                    exit;
                }
            } else {
                // No matching post found
                $wp_query->set_404();
                status_header(404);
            }
        }
    }
    
    /**
     * Add canonical URL for SEO
     */
    public function add_canonical_url() {
        if (is_singular('master_critic_list')) {
            global $post;
            
            // Get city and dish slugs
            $city_slug = get_post_meta($post->ID, 'city_slug', true);
            $dish_slug = get_post_meta($post->ID, 'dish_slug', true);
            
            if ($city_slug && $dish_slug) {
                $canonical_url = home_url("/{$city_slug}/top-10-{$dish_slug}/");
                echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";
            }
        }
    }
    
    /**
     * Flush rewrite rules
     */
    public function flush_rewrite_rules() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }
    
    /**
     * Migrate legacy posts (one-time use)
     */
    public static function migrate_legacy_posts() {
        global $wpdb;
        
        // Update post type from zippicks_list to master_critic_list
        $updated = $wpdb->update(
            $wpdb->posts,
            ['post_type' => 'master_critic_list'],
            ['post_type' => 'zippicks_list'],
            ['%s'],
            ['%s']
        );
        
        if ($updated !== false) {
            // Migrate meta keys
            $meta_mappings = [
                'zippicks_list_topic' => 'dish_slug',
                'zippicks_list_location' => 'city_slug',
                'zippicks_list_businesses' => '_mc_restaurants',
                'zippicks_list_category' => '_mc_category',
                'zippicks_list_count' => '_mc_count',
                'zippicks_list_generation_id' => '_mc_generation_id',
                'zippicks_list_ai_provider' => '_mc_ai_provider'
            ];
            
            foreach ($meta_mappings as $old_key => $new_key) {
                // Get all posts with the old meta key
                $posts_with_meta = $wpdb->get_results($wpdb->prepare(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                     WHERE meta_key = %s",
                    $old_key
                ));
                
                foreach ($posts_with_meta as $meta) {
                    // Process special cases
                    if ($old_key === 'zippicks_list_topic') {
                        // Convert to slug format
                        $value = sanitize_title($meta->meta_value);
                    } elseif ($old_key === 'zippicks_list_location') {
                        // Convert to slug format
                        $value = sanitize_title($meta->meta_value);
                    } else {
                        $value = $meta->meta_value;
                    }
                    
                    // Add new meta
                    update_post_meta($meta->post_id, $new_key, $value);
                    
                    // For location and topic, also store the original values
                    if ($old_key === 'zippicks_list_topic') {
                        update_post_meta($meta->post_id, '_mc_topic', $meta->meta_value);
                    } elseif ($old_key === 'zippicks_list_location') {
                        update_post_meta($meta->post_id, '_mc_location', $meta->meta_value);
                    }
                }
                
                // Delete old meta keys
                $wpdb->delete(
                    $wpdb->postmeta,
                    ['meta_key' => $old_key],
                    ['%s']
                );
            }
            
            // Clear caches
            wp_cache_flush();
            
            return $updated;
        }
        
        return false;
    }
}