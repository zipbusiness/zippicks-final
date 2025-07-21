<?php
/**
 * Schema.org Hooks for Master Critic Lists
 * 
 * Handles injection of structured data into WordPress pages to ensure
 * ZipPicks lists appear at the top of Google search results.
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

class ZipPicks_Master_Critic_Schema_Hooks {
    
    /**
     * Schema generator instance
     *
     * @var ZipPicks_Master_Critic_Schema_Generator
     */
    private $schema_generator;
    
    /**
     * Cache group for schema data
     */
    const CACHE_GROUP = 'zippicks_schema';
    
    /**
     * Cache expiration time (24 hours)
     */
    const CACHE_EXPIRATION = 86400;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->schema_generator = new ZipPicks_Master_Critic_Schema_Generator();
    }
    
    /**
     * Initialize hooks
     */
    public function init() {
        // Output schema in head for single list pages
        add_action('wp_head', array($this, 'output_list_schema_in_head'), 5);
        
        // Filter content to add schema (optional, disabled by default)
        if (apply_filters('zippicks_inject_schema_in_content', false)) {
            add_filter('the_content', array($this, 'inject_schema_in_content'), 20);
        }
        
        // Clear cache when list is updated
        add_action('save_post_zippicks_list', array($this, 'clear_schema_cache'), 10, 2);
        add_action('save_post_master_critic_list', array($this, 'clear_schema_cache'), 10, 2);
        
        // Add meta box for schema preview
        add_action('add_meta_boxes', array($this, 'add_schema_meta_box'));
    }
    
    /**
     * Output schema in wp_head for single list pages
     */
    public function output_list_schema_in_head() {
        // Only on single list pages
        if (!is_singular(['zippicks_list', 'master_critic_list'])) {
            return;
        }
        
        global $post;
        if (!$post) {
            return;
        }
        
        // Get or generate schema
        $schema = $this->get_list_schema($post->ID);
        
        if ($schema) {
            echo "\n<!-- ZipPicks Master Critic ItemList Schema -->\n";
            echo $this->schema_generator->output_schema($schema);
            echo "\n<!-- End ZipPicks Schema -->\n";
        }
    }
    
    /**
     * Inject schema into content (optional)
     *
     * @param string $content Post content
     * @return string Modified content
     */
    public function inject_schema_in_content($content) {
        // Only on single list pages
        if (!is_singular(['zippicks_list', 'master_critic_list'])) {
            return $content;
        }
        
        global $post;
        if (!$post) {
            return $content;
        }
        
        // Get or generate schema
        $schema = $this->get_list_schema($post->ID);
        
        if ($schema) {
            $schema_output = $this->schema_generator->output_schema($schema);
            
            // Add schema at the end of content
            $content .= "\n" . $schema_output;
        }
        
        return $content;
    }
    
    /**
     * Get schema for a list post
     *
     * @param int $post_id Post ID
     * @return array|null Schema data
     */
    private function get_list_schema($post_id) {
        // Try to get from cache first
        $cache_key = 'list_schema_' . $post_id;
        $cached_schema = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if ($cached_schema !== false) {
            return $cached_schema;
        }
        
        // Generate schema
        $schema = $this->generate_list_schema($post_id);
        
        // Cache the result
        if ($schema) {
            wp_cache_set($cache_key, $schema, self::CACHE_GROUP, self::CACHE_EXPIRATION);
            
            // Also save to post meta for persistence
            update_post_meta($post_id, '_zippicks_cached_schema', $schema);
            update_post_meta($post_id, '_zippicks_schema_generated', current_time('mysql'));
        }
        
        return $schema;
    }
    
    /**
     * Generate schema for a list post
     *
     * @param int $post_id Post ID
     * @return array|null Schema data
     */
    private function generate_list_schema($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }
        
        // Get businesses data from post meta
        $businesses_json = get_post_meta($post_id, 'zippicks_list_businesses', true);
        if (empty($businesses_json)) {
            // Try alternative meta key for backwards compatibility
            $businesses_json = get_post_meta($post_id, '_master_critic_businesses', true);
        }
        
        if (empty($businesses_json)) {
            return null;
        }
        
        // Decode businesses data
        $businesses = json_decode($businesses_json, true);
        if (!is_array($businesses) || empty($businesses)) {
            return null;
        }
        
        // Prepare list data
        $list_data = [
            'title' => get_the_title($post_id),
            'url' => get_permalink($post_id),
            'topic' => get_post_meta($post_id, 'zippicks_list_topic', true),
            'location' => get_post_meta($post_id, 'zippicks_list_location', true),
            'business_category' => get_post_meta($post_id, 'zippicks_list_category', true),
            'description' => get_the_excerpt($post_id)
        ];
        
        // Generate schema
        return $this->schema_generator->generate_itemlist_schema($businesses, $list_data);
    }
    
    /**
     * Clear schema cache when list is updated
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function clear_schema_cache($post_id, $post) {
        // Skip auto-saves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Clear cache
        $cache_key = 'list_schema_' . $post_id;
        wp_cache_delete($cache_key, self::CACHE_GROUP);
        
        // Delete cached schema meta
        delete_post_meta($post_id, '_zippicks_cached_schema');
        delete_post_meta($post_id, '_zippicks_schema_generated');
    }
    
    /**
     * Add meta box for schema preview
     */
    public function add_schema_meta_box() {
        add_meta_box(
            'zippicks_schema_preview',
            'Schema.org Preview',
            array($this, 'render_schema_meta_box'),
            ['zippicks_list', 'master_critic_list'],
            'side',
            'low'
        );
    }
    
    /**
     * Render schema preview meta box
     *
     * @param WP_Post $post Post object
     */
    public function render_schema_meta_box($post) {
        $schema = $this->get_list_schema($post->ID);
        
        if (!$schema) {
            echo '<p>No schema generated yet. Save the post to generate schema.</p>';
            return;
        }
        
        // Validate schema
        $validation = $this->schema_generator->validate_schema($schema);
        
        // Show validation status
        if ($validation['valid']) {
            echo '<div style="color: green; margin-bottom: 10px;">✓ Schema is valid</div>';
        } else {
            echo '<div style="color: red; margin-bottom: 10px;">✗ Schema has errors</div>';
            if (!empty($validation['errors'])) {
                echo '<ul style="color: red; font-size: 12px;">';
                foreach ($validation['errors'] as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                echo '</ul>';
            }
        }
        
        if (!empty($validation['warnings'])) {
            echo '<div style="color: orange;">⚠ Warnings:</div>';
            echo '<ul style="color: orange; font-size: 12px;">';
            foreach ($validation['warnings'] as $warning) {
                echo '<li>' . esc_html($warning) . '</li>';
            }
            echo '</ul>';
        }
        
        // Show last generated time
        $generated_time = get_post_meta($post->ID, '_zippicks_schema_generated', true);
        if ($generated_time) {
            echo '<p style="font-size: 12px; color: #666;">Generated: ' . esc_html($generated_time) . '</p>';
        }
        
        // Add test links
        echo '<div style="margin-top: 10px;">';
        echo '<a href="https://search.google.com/test/rich-results?url=' . urlencode(get_permalink($post->ID)) . '" target="_blank" class="button button-secondary">Test in Google</a>';
        echo '</div>';
        
        // Debug: Show schema preview (collapsed by default)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<details style="margin-top: 10px;">';
            echo '<summary style="cursor: pointer;">View Schema JSON</summary>';
            echo '<pre style="font-size: 10px; overflow: auto; max-height: 300px; background: #f5f5f5; padding: 10px; margin-top: 5px;">';
            echo esc_html(json_encode($schema, JSON_PRETTY_PRINT));
            echo '</pre>';
            echo '</details>';
        }
    }
}