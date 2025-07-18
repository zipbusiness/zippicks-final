<?php
/**
 * Master Critic Post Type Registration
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

class ZipPicks_Master_Critic_Post_Type {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_meta']);
    }
    
    /**
     * Register the master_critic_list post type
     */
    public function register_post_type() {
        // Only register if not already registered by Core plugin
        if (post_type_exists('master_critic_list')) {
            return;
        }
        
        $labels = [
            'name'                  => 'Top 10 Lists',
            'singular_name'         => 'Top 10 List',
            'add_new'              => 'Add New List',
            'add_new_item'         => 'Add New Top 10 List',
            'edit_item'            => 'Edit Top 10 List',
            'new_item'             => 'New Top 10 List',
            'view_item'            => 'View Top 10 List',
            'view_items'           => 'View Top 10 Lists',
            'search_items'         => 'Search Top 10 Lists',
            'not_found'            => 'No Top 10 Lists found',
            'not_found_in_trash'   => 'No Top 10 Lists found in Trash',
            'all_items'            => 'All Top 10 Lists',
            'menu_name'            => 'Master Critic Lists',
        ];
        
        // Check if ZipPicks Core is active to determine menu location
        $active_plugins = get_option('active_plugins', []);
        $core_active = in_array('zippicks-core/zippicks-core.php', $active_plugins);
        $show_in_menu = $core_active ? 'zippicks-system' : true;
        
        $args = [
            'labels'                => $labels,
            'description'           => 'AI-generated Top 10 lists for local discovery',
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => $show_in_menu,
            'query_var'             => true,
            'rewrite'               => [
                'slug'       => 'top10',
                'with_front' => false,
                'pages'      => true,
                'feeds'      => true,
            ],
            'capability_type'       => 'post',
            'has_archive'           => true,
            'hierarchical'          => false,
            'menu_position'         => 25,
            'menu_icon'             => 'dashicons-list-view',
            'supports'              => [
                'title',
                'editor',
                'author',
                'thumbnail',
                'excerpt',
                'custom-fields',
                'revisions',
            ],
            'show_in_rest'          => true,
            'rest_base'             => 'master-critic-lists',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ];
        
        register_post_type('master_critic_list', $args);
    }
    
    /**
     * Register post meta fields
     */
    public function register_meta() {
        // Slug fields for routing
        register_post_meta('master_critic_list', 'city_slug', [
            'type'              => 'string',
            'description'       => 'City slug for URL routing',
            'single'            => true,
            'sanitize_callback' => 'sanitize_title',
            'show_in_rest'      => true,
        ]);
        
        register_post_meta('master_critic_list', 'dish_slug', [
            'type'              => 'string',
            'description'       => 'Dish/cuisine slug for URL routing',
            'single'            => true,
            'sanitize_callback' => 'sanitize_title',
            'show_in_rest'      => true,
        ]);
        
        // Display fields
        register_post_meta('master_critic_list', '_mc_topic', [
            'type'              => 'string',
            'description'       => 'Display topic name',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]);
        
        register_post_meta('master_critic_list', '_mc_location', [
            'type'              => 'string',
            'description'       => 'Display location name',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]);
        
        // Business data
        register_post_meta('master_critic_list', '_mc_restaurants', [
            'type'              => 'string',
            'description'       => 'JSON array of restaurant data',
            'single'            => true,
            'sanitize_callback' => [$this, 'sanitize_json'],
            'show_in_rest'      => [
                'schema' => [
                    'type'   => 'string',
                    'format' => 'json',
                ],
            ],
        ]);
        
        // Additional metadata
        register_post_meta('master_critic_list', '_mc_category', [
            'type'              => 'string',
            'description'       => 'Business category',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]);
        
        register_post_meta('master_critic_list', '_mc_ai_provider', [
            'type'              => 'string',
            'description'       => 'AI provider used',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]);
        
        register_post_meta('master_critic_list', '_mc_generation_id', [
            'type'              => 'integer',
            'description'       => 'Generation record ID',
            'single'            => true,
            'sanitize_callback' => 'absint',
            'show_in_rest'      => true,
        ]);
        
        // Vibe associations for cross-plugin integration
        register_post_meta('master_critic_list', '_mc_vibe_ids', [
            'type'              => 'array',
            'description'       => 'Associated vibe IDs for categorization',
            'single'            => false,
            'sanitize_callback' => [$this, 'sanitize_vibe_ids'],
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ]);
    }
    
    /**
     * Sanitize JSON data
     *
     * @param mixed $value The value to sanitize
     * @return string
     */
    public function sanitize_json($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        return '';
    }
    
    /**
     * Sanitize vibe IDs array
     *
     * @param mixed $value The value to sanitize
     * @return array
     */
    public function sanitize_vibe_ids($value) {
        if (!is_array($value)) {
            return [];
        }
        
        // Ensure all values are positive integers
        return array_values(array_filter(array_map('absint', $value)));
    }
}