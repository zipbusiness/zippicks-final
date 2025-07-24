<?php
/**
 * Business Custom Post Type
 * 
 * Registers and manages the Business CPT with indexed _zpid meta
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Business_CPT {
    
    /**
     * Register Business CPT
     */
    public static function register() {
        $labels = [
            'name'                  => _x('Businesses', 'Post type general name', 'zippicks-smart-search'),
            'singular_name'         => _x('Business', 'Post type singular name', 'zippicks-smart-search'),
            'menu_name'             => _x('Businesses', 'Admin Menu text', 'zippicks-smart-search'),
            'name_admin_bar'        => _x('Business', 'Add New on Toolbar', 'zippicks-smart-search'),
            'add_new'               => __('Add New', 'zippicks-smart-search'),
            'add_new_item'          => __('Add New Business', 'zippicks-smart-search'),
            'new_item'              => __('New Business', 'zippicks-smart-search'),
            'edit_item'             => __('Edit Business', 'zippicks-smart-search'),
            'view_item'             => __('View Business', 'zippicks-smart-search'),
            'all_items'             => __('All Businesses', 'zippicks-smart-search'),
            'search_items'          => __('Search Businesses', 'zippicks-smart-search'),
            'parent_item_colon'     => __('Parent Businesses:', 'zippicks-smart-search'),
            'not_found'             => __('No businesses found.', 'zippicks-smart-search'),
            'not_found_in_trash'    => __('No businesses found in Trash.', 'zippicks-smart-search'),
            'featured_image'        => _x('Business Image', 'Overrides the "Featured Image" phrase', 'zippicks-smart-search'),
            'set_featured_image'    => _x('Set business image', 'Overrides the "Set featured image" phrase', 'zippicks-smart-search'),
            'remove_featured_image' => _x('Remove business image', 'Overrides the "Remove featured image" phrase', 'zippicks-smart-search'),
            'use_featured_image'    => _x('Use as business image', 'Overrides the "Use as featured image" phrase', 'zippicks-smart-search'),
            'archives'              => _x('Business archives', 'The post type archive label', 'zippicks-smart-search'),
            'insert_into_item'      => _x('Insert into business', 'Overrides the "Insert into post" phrase', 'zippicks-smart-search'),
            'uploaded_to_this_item' => _x('Uploaded to this business', 'Overrides the "Uploaded to this post" phrase', 'zippicks-smart-search'),
            'filter_items_list'     => _x('Filter businesses list', 'Screen reader text', 'zippicks-smart-search'),
            'items_list_navigation' => _x('Businesses list navigation', 'Screen reader text', 'zippicks-smart-search'),
            'items_list'            => _x('Businesses list', 'Screen reader text', 'zippicks-smart-search'),
        ];
        
        $args = [
            'labels'                => $labels,
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'query_var'             => true,
            'rewrite'               => ['slug' => 'business', 'with_front' => false],
            'capability_type'       => 'post',
            'has_archive'           => true,
            'hierarchical'          => false,
            'menu_position'         => 20,
            'menu_icon'             => 'dashicons-store',
            'supports'              => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
            'show_in_rest'          => true,
            'rest_base'             => 'businesses',
        ];
        
        register_post_type('business', $args);
        
        // Register meta fields
        self::register_meta_fields();
        
        // Add admin columns
        add_filter('manage_business_posts_columns', [__CLASS__, 'add_admin_columns']);
        add_action('manage_business_posts_custom_column', [__CLASS__, 'render_admin_columns'], 10, 2);
        add_filter('manage_edit-business_sortable_columns', [__CLASS__, 'make_columns_sortable']);
        
        // Meta box for admin
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_business', [__CLASS__, 'save_meta_box_data']);
        
        // Custom query vars for filtering
        add_filter('pre_get_posts', [__CLASS__, 'filter_by_zpid']);
    }
    
    /**
     * Register meta fields for REST API
     * 
     * All meta fields include auth_callback to ensure consistent
     * authorization checks. Only users with 'edit_posts' capability
     * can modify these meta fields via REST API or other means.
     */
    private static function register_meta_fields() {
        // ZPID - Primary identifier from PostgreSQL
        register_post_meta('business', '_zpid', [
            'type'              => 'string',
            'description'       => 'ZipPicks Business ID',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => true,
        ]);
        
        // Location meta
        register_post_meta('business', '_city', [
            'type'              => 'string',
            'description'       => 'Business city',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => true,
        ]);
        
        register_post_meta('business', '_state', [
            'type'              => 'string',
            'description'       => 'Business state',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => true,
        ]);
        
        register_post_meta('business', '_address', [
            'type'              => 'string',
            'description'       => 'Business address',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => true,
        ]);
        
        register_post_meta('business', '_lat', [
            'type'              => 'number',
            'description'       => 'Latitude',
            'single'            => true,
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => true,
        ]);
        
        register_post_meta('business', '_lng', [
            'type'              => 'number',
            'description'       => 'Longitude',
            'single'            => true,
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => true,
        ]);
        
        // Business details
        register_post_meta('business', '_cuisine_type', [
            'type'              => 'string',
            'description'       => 'Cuisine type',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => true,
        ]);
        
        register_post_meta('business', '_price_range', [
            'type'              => 'integer',
            'description'       => 'Price range (1-4)',
            'single'            => true,
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => true,
        ]);
        
        register_post_meta('business', '_rating', [
            'type'              => 'number',
            'description'       => 'Average rating',
            'single'            => true,
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => true,
        ]);
        
        register_post_meta('business', '_vibes', [
            'type'              => 'array',
            'description'       => 'Associated vibes',
            'single'            => true,
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ]);
        
        // Sync status
        register_post_meta('business', '_last_synced', [
            'type'              => 'string',
            'description'       => 'Last sync timestamp',
            'single'            => true,
            'auth_callback'     => function() {
                return current_user_can('edit_posts');
            },
            'show_in_rest'      => true,
        ]);
    }
    
    /**
     * Add custom admin columns
     */
    public static function add_admin_columns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add ZPID after title
            if ($key === 'title') {
                $new_columns['zpid'] = __('ZPID', 'zippicks-smart-search');
            }
        }
        
        // Add location columns
        $new_columns['location'] = __('Location', 'zippicks-smart-search');
        $new_columns['cuisine'] = __('Cuisine', 'zippicks-smart-search');
        $new_columns['rating'] = __('Rating', 'zippicks-smart-search');
        
        return $new_columns;
    }
    
    /**
     * Render custom admin columns
     */
    public static function render_admin_columns($column, $post_id) {
        switch ($column) {
            case 'zpid':
                $zpid = get_post_meta($post_id, '_zpid', true);
                echo esc_html($zpid ?: '—');
                break;
                
            case 'location':
                $city = get_post_meta($post_id, '_city', true);
                $state = get_post_meta($post_id, '_state', true);
                echo $city && $state ? esc_html("$city, $state") : '—';
                break;
                
            case 'cuisine':
                $cuisine = get_post_meta($post_id, '_cuisine_type', true);
                echo esc_html($cuisine ?: '—');
                break;
                
            case 'rating':
                $rating = get_post_meta($post_id, '_rating', true);
                if ($rating) {
                    echo esc_html(number_format($rating, 1) . ' ⭐');
                } else {
                    echo esc_html('—');
                }
                break;
        }
    }
    
    /**
     * Make columns sortable
     */
    public static function make_columns_sortable($columns) {
        $columns['zpid'] = 'zpid';
        $columns['location'] = 'city';
        $columns['rating'] = 'rating';
        
        return $columns;
    }
    
    /**
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'business_details',
            __('Business Details', 'zippicks-smart-search'),
            [__CLASS__, 'render_meta_box'],
            'business',
            'normal',
            'high'
        );
    }
    
    /**
     * Render meta box
     */
    public static function render_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('business_meta_box', 'business_meta_box_nonce');
        
        // Get existing values
        $zpid = get_post_meta($post->ID, '_zpid', true);
        $city = get_post_meta($post->ID, '_city', true);
        $state = get_post_meta($post->ID, '_state', true);
        $address = get_post_meta($post->ID, '_address', true);
        $cuisine = get_post_meta($post->ID, '_cuisine_type', true);
        $price_range = get_post_meta($post->ID, '_price_range', true);
        $rating = get_post_meta($post->ID, '_rating', true);
        $lat = get_post_meta($post->ID, '_lat', true);
        $lng = get_post_meta($post->ID, '_lng', true);
        ?>
        
        <div class="business-meta-fields">
            <p>
                <label for="business_zpid"><?php _e('ZPID:', 'zippicks-smart-search'); ?></label>
                <input type="text" id="business_zpid" name="business_zpid" value="<?php echo esc_attr($zpid); ?>" class="regular-text" />
                <span class="description"><?php _e('ZipPicks Business ID (e.g., zp_12345678)', 'zippicks-smart-search'); ?></span>
            </p>
            
            <p>
                <label for="business_address"><?php _e('Address:', 'zippicks-smart-search'); ?></label>
                <input type="text" id="business_address" name="business_address" value="<?php echo esc_attr($address); ?>" class="large-text" />
            </p>
            
            <p>
                <label for="business_city"><?php _e('City:', 'zippicks-smart-search'); ?></label>
                <input type="text" id="business_city" name="business_city" value="<?php echo esc_attr($city); ?>" class="regular-text" />
                
                <label for="business_state"><?php _e('State:', 'zippicks-smart-search'); ?></label>
                <input type="text" id="business_state" name="business_state" value="<?php echo esc_attr($state); ?>" size="2" maxlength="2" />
            </p>
            
            <p>
                <label for="business_lat"><?php _e('Latitude:', 'zippicks-smart-search'); ?></label>
                <input type="number" id="business_lat" name="business_lat" value="<?php echo esc_attr($lat); ?>" step="0.000001" />
                
                <label for="business_lng"><?php _e('Longitude:', 'zippicks-smart-search'); ?></label>
                <input type="number" id="business_lng" name="business_lng" value="<?php echo esc_attr($lng); ?>" step="0.000001" />
            </p>
            
            <p>
                <label for="business_cuisine"><?php _e('Cuisine Type:', 'zippicks-smart-search'); ?></label>
                <input type="text" id="business_cuisine" name="business_cuisine" value="<?php echo esc_attr($cuisine); ?>" class="regular-text" />
            </p>
            
            <p>
                <label for="business_price_range"><?php _e('Price Range:', 'zippicks-smart-search'); ?></label>
                <select id="business_price_range" name="business_price_range">
                    <option value="">— Select —</option>
                    <option value="1" <?php selected($price_range, '1'); ?>>$ - Budget</option>
                    <option value="2" <?php selected($price_range, '2'); ?>>$$ - Moderate</option>
                    <option value="3" <?php selected($price_range, '3'); ?>>$$$ - Upscale</option>
                    <option value="4" <?php selected($price_range, '4'); ?>$$$$ - Fine Dining</option>
                </select>
            </p>
            
            <p>
                <label for="business_rating"><?php _e('Rating:', 'zippicks-smart-search'); ?></label>
                <input type="number" id="business_rating" name="business_rating" value="<?php echo esc_attr($rating); ?>" min="0" max="5" step="0.1" />
            </p>
        </div>
        
        <style>
        .business-meta-fields p {
            margin: 15px 0;
        }
        .business-meta-fields label {
            display: inline-block;
            width: 120px;
            font-weight: 600;
        }
        </style>
        <?php
    }
    
    /**
     * Save meta box data
     */
    public static function save_meta_box_data($post_id) {
        // Check nonce
        if (!isset($_POST['business_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['business_meta_box_nonce'], 'business_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save text fields with proper sanitization
        $text_fields = [
            'business_zpid' => '_zpid',
            'business_city' => '_city',
            'business_state' => '_state',
            'business_address' => '_address',
            'business_cuisine' => '_cuisine_type',
        ];
        
        foreach ($text_fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Save numeric fields with proper validation
        
        // Price range: integer 1-4
        if (isset($_POST['business_price_range'])) {
            $price_range = intval($_POST['business_price_range']);
            // Validate range 1-4, or empty string for no selection
            if ($price_range >= 1 && $price_range <= 4) {
                update_post_meta($post_id, '_price_range', $price_range);
            } elseif (empty($_POST['business_price_range'])) {
                delete_post_meta($post_id, '_price_range');
            }
        }
        
        // Rating: float 0-5
        if (isset($_POST['business_rating'])) {
            $rating = floatval($_POST['business_rating']);
            // Validate range 0-5
            if ($rating >= 0 && $rating <= 5) {
                update_post_meta($post_id, '_rating', $rating);
            } elseif (empty($_POST['business_rating'])) {
                delete_post_meta($post_id, '_rating');
            }
        }
        
        // Latitude: float -90 to 90
        if (isset($_POST['business_lat'])) {
            $lat = floatval($_POST['business_lat']);
            // Validate latitude range
            if ($lat >= -90 && $lat <= 90) {
                update_post_meta($post_id, '_lat', $lat);
            } elseif (empty($_POST['business_lat'])) {
                delete_post_meta($post_id, '_lat');
            }
        }
        
        // Longitude: float -180 to 180
        if (isset($_POST['business_lng'])) {
            $lng = floatval($_POST['business_lng']);
            // Validate longitude range
            if ($lng >= -180 && $lng <= 180) {
                update_post_meta($post_id, '_lng', $lng);
            } elseif (empty($_POST['business_lng'])) {
                delete_post_meta($post_id, '_lng');
            }
        }
    }
    
    /**
     * Filter posts by ZPID
     */
    public static function filter_by_zpid($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if ($query->get('post_type') !== 'business') {
            return;
        }
        
        // Handle orderby for custom columns
        $orderby = $query->get('orderby');
        
        switch ($orderby) {
            case 'zpid':
                $query->set('meta_key', '_zpid');
                $query->set('orderby', 'meta_value');
                break;
                
            case 'city':
                $query->set('meta_key', '_city');
                $query->set('orderby', 'meta_value');
                break;
                
            case 'rating':
                $query->set('meta_key', '_rating');
                $query->set('orderby', 'meta_value_num');
                break;
        }
    }
    
    /**
     * Get business by ZPID
     * 
     * @param string $zpid
     * @return \WP_Post|null
     */
    public static function get_by_zpid($zpid) {
        $args = [
            'post_type' => 'business',
            'meta_key' => '_zpid',
            'meta_value' => $zpid,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ];
        
        $posts = get_posts($args);
        
        return !empty($posts) ? $posts[0] : null;
    }
    
    /**
     * Validate and sanitize API data
     * 
     * @param array $data Raw data from API
     * @return array|\WP_Error Validated data or WP_Error on validation failure
     */
    private static function validate_api_data($data) {
        $validated = [];
        $errors = [];
        
        // Required fields
        if (empty($data['zpid']) || !is_string($data['zpid'])) {
            $errors[] = 'ZPID is required and must be a string';
        } else {
            // Validate ZPID format (alphanumeric with hyphens/underscores)
            $zpid = trim($data['zpid']);
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $zpid)) {
                $errors[] = 'ZPID contains invalid characters';
            } else {
                $validated['zpid'] = $zpid;
            }
        }
        
        if (empty($data['name']) || !is_string($data['name'])) {
            $errors[] = 'Business name is required and must be a string';
        } else {
            $validated['name'] = sanitize_text_field($data['name']);
        }
        
        // String fields (optional but sanitized)
        $validated['city'] = isset($data['city']) ? sanitize_text_field($data['city']) : '';
        $validated['address'] = isset($data['address']) ? sanitize_text_field($data['address']) : '';
        $validated['cuisine_type'] = isset($data['cuisine_type']) ? sanitize_text_field($data['cuisine_type']) : '';
        
        // State validation (2-letter code)
        if (!empty($data['state'])) {
            $state = strtoupper(trim($data['state']));
            if (preg_match('/^[A-Z]{2}$/', $state)) {
                $validated['state'] = $state;
            } else {
                $errors[] = 'State must be a 2-letter code';
            }
        } else {
            $validated['state'] = '';
        }
        
        // Latitude validation (-90 to 90)
        if (isset($data['latitude'])) {
            $lat = filter_var($data['latitude'], FILTER_VALIDATE_FLOAT);
            if ($lat !== false && $lat >= -90 && $lat <= 90) {
                $validated['latitude'] = $lat;
            } else {
                $errors[] = 'Latitude must be a number between -90 and 90';
            }
        }
        
        // Longitude validation (-180 to 180)
        if (isset($data['longitude'])) {
            $lng = filter_var($data['longitude'], FILTER_VALIDATE_FLOAT);
            if ($lng !== false && $lng >= -180 && $lng <= 180) {
                $validated['longitude'] = $lng;
            } else {
                $errors[] = 'Longitude must be a number between -180 and 180';
            }
        }
        
        // Price range validation (1-4)
        if (isset($data['price_range'])) {
            $price = filter_var($data['price_range'], FILTER_VALIDATE_INT);
            if ($price !== false && $price >= 1 && $price <= 4) {
                $validated['price_range'] = $price;
            } else {
                $errors[] = 'Price range must be an integer between 1 and 4';
            }
        }
        
        // Rating validation (0-5)
        if (isset($data['rating'])) {
            $rating = filter_var($data['rating'], FILTER_VALIDATE_FLOAT);
            if ($rating !== false && $rating >= 0 && $rating <= 5) {
                $validated['rating'] = round($rating, 1); // Round to 1 decimal place
            } else {
                $errors[] = 'Rating must be a number between 0 and 5';
            }
        }
        
        // Vibes validation (array of strings)
        if (isset($data['vibes'])) {
            if (is_array($data['vibes'])) {
                $validated['vibes'] = array_map('sanitize_text_field', $data['vibes']);
            } else {
                $errors[] = 'Vibes must be an array';
            }
        } else {
            $validated['vibes'] = [];
        }
        
        // Return errors if any validation failed
        if (!empty($errors)) {
            return new \WP_Error('validation_failed', 'Validation failed', $errors);
        }
        
        return $validated;
    }
    
    /**
     * Create or update business from API data
     * 
     * @param array $data Restaurant data from API
     * @return int|\WP_Error Post ID on success, WP_Error on failure
     */
    public static function create_or_update_from_api($data) {
        // Validate and sanitize input data
        $validated = self::validate_api_data($data);
        if (is_wp_error($validated)) {
            return $validated;
        }
        
        // Check if business already exists
        $existing = self::get_by_zpid($validated['zpid']);
        
        $post_data = [
            'post_title' => $validated['name'],
            'post_name' => sanitize_title($validated['name'] . '-' . $validated['city']),
            'post_type' => 'business',
            'post_status' => 'publish',
            'meta_input' => [
                '_zpid' => $validated['zpid'],
                '_city' => $validated['city'],
                '_state' => $validated['state'],
                '_address' => $validated['address'],
                '_lat' => $validated['latitude'] ?? null,
                '_lng' => $validated['longitude'] ?? null,
                '_cuisine_type' => $validated['cuisine_type'],
                '_price_range' => $validated['price_range'] ?? null,
                '_rating' => $validated['rating'] ?? null,
                '_vibes' => $validated['vibes'],
                '_last_synced' => current_time('mysql'),
            ],
        ];
        
        if ($existing) {
            $post_data['ID'] = $existing->ID;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }
        
        return $result;
    }
}