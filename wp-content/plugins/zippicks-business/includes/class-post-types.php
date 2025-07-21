<?php
/**
 * Register custom post types and taxonomies.
 *
 * Handles registration of the Business post type and related functionality.
 */
class ZipPicks_Business_Post_Types {
    
    /**
     * Register post types and taxonomies
     */
    public function register() {
        // Register Business CPT
        $this->register_business_post_type();
        
        // Register custom post statuses
        $this->register_custom_statuses();
        
        // Register post meta fields
        $this->register_post_meta_fields();
        
        // Add meta box support
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_zippicks_business', array($this, 'save_business_meta'), 10, 3);
    }
    
    /**
     * Register the Business custom post type
     */
    private function register_business_post_type() {
        $labels = array(
            'name'                  => _x('Businesses', 'Post type general name', 'zippicks-business'),
            'singular_name'         => _x('Business', 'Post type singular name', 'zippicks-business'),
            'menu_name'             => _x('Businesses', 'Admin Menu text', 'zippicks-business'),
            'name_admin_bar'        => _x('Business', 'Add New on Toolbar', 'zippicks-business'),
            'add_new'               => __('Add New', 'zippicks-business'),
            'add_new_item'          => __('Add New Business', 'zippicks-business'),
            'new_item'              => __('New Business', 'zippicks-business'),
            'edit_item'             => __('Edit Business', 'zippicks-business'),
            'view_item'             => __('View Business', 'zippicks-business'),
            'all_items'             => __('All Businesses', 'zippicks-business'),
            'search_items'          => __('Search Businesses', 'zippicks-business'),
            'parent_item_colon'     => __('Parent Businesses:', 'zippicks-business'),
            'not_found'             => __('No businesses found.', 'zippicks-business'),
            'not_found_in_trash'    => __('No businesses found in Trash.', 'zippicks-business'),
            'featured_image'        => _x('Business Logo', 'Overrides the "Featured Image" phrase', 'zippicks-business'),
            'set_featured_image'    => _x('Set business logo', 'Overrides the "Set featured image" phrase', 'zippicks-business'),
            'remove_featured_image' => _x('Remove business logo', 'Overrides the "Remove featured image" phrase', 'zippicks-business'),
            'use_featured_image'    => _x('Use as business logo', 'Overrides the "Use as featured image" phrase', 'zippicks-business'),
            'archives'              => _x('Business Archives', 'The post type archive label', 'zippicks-business'),
            'insert_into_item'      => _x('Insert into business', 'Overrides the "Insert into post" phrase', 'zippicks-business'),
            'uploaded_to_this_item' => _x('Uploaded to this business', 'Overrides the "Uploaded to this post" phrase', 'zippicks-business'),
            'filter_items_list'     => _x('Filter businesses list', 'Screen reader text', 'zippicks-business'),
            'items_list_navigation' => _x('Businesses list navigation', 'Screen reader text', 'zippicks-business'),
            'items_list'            => _x('Businesses list', 'Screen reader text', 'zippicks-business'),
        );
        
        $args = array(
            'labels'                => $labels,
            'description'           => __('ZipPicks business listings', 'zippicks-business'),
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => 'zippicks-business',
            'query_var'             => true,
            'rewrite'               => array('slug' => 'business', 'with_front' => false),
            'capability_type'       => array('business', 'businesses'),
            'map_meta_cap'          => true,
            'has_archive'           => true,
            'hierarchical'          => false,
            'menu_position'         => 25,
            'menu_icon'             => 'dashicons-store',
            'supports'              => array('title', 'editor', 'thumbnail', 'custom-fields', 'revisions', 'excerpt'),
            'show_in_rest'          => true,
            'rest_base'             => 'businesses'
        );
        
        register_post_type('zippicks_business', $args);
    }
    
    /**
     * Register custom post statuses
     */
    private function register_custom_statuses() {
        // Pending Verification status
        register_post_status('pending_verification', array(
            'label'                     => _x('Pending Verification', 'post status', 'zippicks-business'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Pending Verification <span class="count">(%s)</span>',
                'Pending Verification <span class="count">(%s)</span>',
                'zippicks-business'
            ),
        ));
        
        // Verified status
        register_post_status('verified', array(
            'label'                     => _x('Verified', 'post status', 'zippicks-business'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Verified <span class="count">(%s)</span>',
                'Verified <span class="count">(%s)</span>',
                'zippicks-business'
            ),
        ));
        
        // Suspended status
        register_post_status('suspended', array(
            'label'                     => _x('Suspended', 'post status', 'zippicks-business'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Suspended <span class="count">(%s)</span>',
                'Suspended <span class="count">(%s)</span>',
                'zippicks-business'
            ),
        ));
    }
    
    /**
     * Register post meta fields for REST API and validation
     */
    private function register_post_meta_fields() {
        // ZipBusiness API identifier
        register_post_meta('zippicks_business', 'zpid', array(
            'type' => 'string',
            'description' => 'ZipBusiness API identifier',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        // API verification status
        register_post_meta('zippicks_business', 'api_verified', array(
            'type' => 'boolean',
            'description' => 'Whether business is verified via API',
            'single' => true,
            'default' => false,
            'show_in_rest' => true
        ));
        
        // API confidence score
        register_post_meta('zippicks_business', 'api_confidence_score', array(
            'type' => 'number',
            'description' => 'API confidence score (0-1)',
            'single' => true,
            'show_in_rest' => true
        ));
        
        // Cached enriched data from API
        register_post_meta('zippicks_business', 'api_enriched_data', array(
            'type' => 'string',
            'description' => 'Cached enriched data from API',
            'single' => true,
            'show_in_rest' => false // Large JSON data
        ));
        
        // API vibe associations
        register_post_meta('zippicks_business', 'api_vibes', array(
            'type' => 'string',
            'description' => 'JSON array of API vibe associations',
            'single' => true,
            'show_in_rest' => true
        ));
        
        // Last API sync timestamp
        register_post_meta('zippicks_business', 'last_api_sync', array(
            'type' => 'string',
            'description' => 'Last API data synchronization timestamp',
            'single' => true,
            'show_in_rest' => true
        ));
        
        // Pillar scores (for existing functionality)
        register_post_meta('zippicks_business', 'pillar_scores', array(
            'type' => 'object',
            'description' => 'ZipPicks pillar scores',
            'single' => true,
            'show_in_rest' => array(
                'schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'taste' => array('type' => 'number'),
                        'service' => array('type' => 'number'),
                        'speed' => array('type' => 'number'),
                        'value' => array('type' => 'number'),
                        'overall' => array('type' => 'number')
                    )
                )
            )
        ));
    }
    
    /**
     * Add meta boxes for business edit screen
     */
    public function add_meta_boxes() {
        // Business Information
        add_meta_box(
            'zippicks_business_info',
            __('Business Information', 'zippicks-business'),
            array($this, 'render_business_info_metabox'),
            'zippicks_business',
            'normal',
            'high'
        );
        
        // Monetization Status
        add_meta_box(
            'zippicks_monetization',
            __('Monetization & Features', 'zippicks-business'),
            array($this, 'render_monetization_metabox'),
            'zippicks_business',
            'side',
            'high'
        );
        
        // Analytics Summary
        add_meta_box(
            'zippicks_analytics',
            __('Analytics Summary', 'zippicks-business'),
            array($this, 'render_analytics_metabox'),
            'zippicks_business',
            'side',
            'default'
        );
        
        // Verification Status
        add_meta_box(
            'zippicks_verification',
            __('Verification Status', 'zippicks-business'),
            array($this, 'render_verification_metabox'),
            'zippicks_business',
            'side',
            'default'
        );
    }
    
    /**
     * Render business information meta box
     */
    public function render_business_info_metabox($post) {
        // Add nonce for security
        wp_nonce_field('zippicks_business_meta', 'zippicks_business_meta_nonce');
        
        // Get existing values
        $score = get_post_meta($post->ID, '_zp_score', true);
        $review_count = get_post_meta($post->ID, '_zp_review_count', true);
        $price_tier = get_post_meta($post->ID, '_zp_price_tier', true);
        $address = get_post_meta($post->ID, '_zp_address', true);
        $phone = get_post_meta($post->ID, '_zp_phone', true);
        $website = get_post_meta($post->ID, '_zp_website', true);
        $hours = get_post_meta($post->ID, '_zp_hours', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="zp_score"><?php _e('Overall Score', 'zippicks-business'); ?></label></th>
                <td>
                    <input type="number" id="zp_score" name="zp_score" value="<?php echo esc_attr($score); ?>" 
                           min="0" max="10" step="0.1" style="width: 100px;">
                    <p class="description"><?php _e('Score from 0-10', 'zippicks-business'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="zp_review_count"><?php _e('Review Count', 'zippicks-business'); ?></label></th>
                <td>
                    <input type="number" id="zp_review_count" name="zp_review_count" 
                           value="<?php echo esc_attr($review_count); ?>" min="0" style="width: 100px;">
                </td>
            </tr>
            <tr>
                <th><label for="zp_price_tier"><?php _e('Price Tier', 'zippicks-business'); ?></label></th>
                <td>
                    <select id="zp_price_tier" name="zp_price_tier">
                        <option value="$" <?php selected($price_tier, '$'); ?>>$ - Budget</option>
                        <option value="$$" <?php selected($price_tier, '$$'); ?>>$$ - Moderate</option>
                        <option value="$$$" <?php selected($price_tier, '$$$'); ?>>$$$ - Upscale</option>
                        <option value="$$$$" <?php selected($price_tier, '$$$$'); ?>>$$$$ - Luxury</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="zp_address"><?php _e('Address', 'zippicks-business'); ?></label></th>
                <td>
                    <textarea id="zp_address" name="zp_address" rows="3" class="large-text"><?php echo esc_textarea($address); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="zp_phone"><?php _e('Phone', 'zippicks-business'); ?></label></th>
                <td>
                    <input type="tel" id="zp_phone" name="zp_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><label for="zp_website"><?php _e('Website', 'zippicks-business'); ?></label></th>
                <td>
                    <input type="url" id="zp_website" name="zp_website" value="<?php echo esc_url($website); ?>" class="large-text">
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render monetization meta box
     */
    public function render_monetization_metabox($post) {
        global $wpdb;
        
        $tier = get_post_meta($post->ID, '_zp_listing_tier', true) ?: 'basic';
        $tiers = get_option('zippicks_business_tiers', array());
        
        // Get monetization data from database
        $table = $wpdb->prefix . 'zippicks_business_monetization';
        $monetization = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE business_id = %d",
            $post->ID
        ));
        ?>
        <p>
            <label for="zp_listing_tier"><?php _e('Listing Tier:', 'zippicks-business'); ?></label>
            <select id="zp_listing_tier" name="zp_listing_tier" style="width: 100%;">
                <?php foreach ($tiers as $tier_key => $tier_data) : ?>
                    <option value="<?php echo esc_attr($tier_key); ?>" <?php selected($tier, $tier_key); ?>>
                        <?php echo esc_html($tier_data['name']); ?> 
                        <?php if ($tier_data['price'] > 0) : ?>
                            ($<?php echo number_format($tier_data['price'], 2); ?>/mo)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <?php if ($monetization) : ?>
            <p>
                <strong><?php _e('Status:', 'zippicks-business'); ?></strong> 
                <?php echo esc_html($monetization->subscription_status ?: 'Active'); ?>
            </p>
            <?php if ($monetization->expires_at) : ?>
                <p>
                    <strong><?php _e('Expires:', 'zippicks-business'); ?></strong> 
                    <?php echo date_i18n(get_option('date_format'), strtotime($monetization->expires_at)); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
        
        <p>
            <a href="<?php echo admin_url('admin.php?page=zippicks-business-monetization&business_id=' . $post->ID); ?>" 
               class="button button-primary">
                <?php _e('Manage Subscription', 'zippicks-business'); ?>
            </a>
        </p>
        <?php
    }
    
    /**
     * Render analytics meta box
     */
    public function render_analytics_metabox($post) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_business_analytics';
        
        // Get view count for last 30 days
        $views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
            WHERE business_id = %d 
            AND event_type = 'view' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $post->ID
        ));
        
        // Get click count for last 30 days
        $clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
            WHERE business_id = %d 
            AND event_type = 'click' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $post->ID
        ));
        ?>
        <p>
            <strong><?php _e('Last 30 Days:', 'zippicks-business'); ?></strong>
        </p>
        <ul>
            <li><?php _e('Views:', 'zippicks-business'); ?> <?php echo number_format($views); ?></li>
            <li><?php _e('Clicks:', 'zippicks-business'); ?> <?php echo number_format($clicks); ?></li>
        </ul>
        <p>
            <a href="<?php echo admin_url('admin.php?page=zippicks-business-analytics&business_id=' . $post->ID); ?>" 
               class="button">
                <?php _e('View Full Analytics', 'zippicks-business'); ?>
            </a>
        </p>
        <?php
    }
    
    /**
     * Render verification meta box
     */
    public function render_verification_metabox($post) {
        $verified = get_post_meta($post->ID, '_zp_verified', true);
        $verified_at = get_post_meta($post->ID, '_zp_verified_at', true);
        $verified_by = get_post_meta($post->ID, '_zp_verified_by', true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="zp_verified" value="1" <?php checked($verified, true); ?>>
                <?php _e('Business is verified', 'zippicks-business'); ?>
            </label>
        </p>
        
        <?php if ($verified && $verified_at) : ?>
            <p>
                <small>
                    <?php _e('Verified on:', 'zippicks-business'); ?> 
                    <?php echo date_i18n(get_option('date_format'), strtotime($verified_at)); ?>
                    <?php if ($verified_by) : 
                        $user = get_user_by('id', $verified_by);
                        if ($user) : ?>
                            <?php _e('by', 'zippicks-business'); ?> <?php echo esc_html($user->display_name); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </small>
            </p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Save business meta data
     */
    public function save_business_meta($post_id, $post, $update) {
        // Check nonce
        if (!isset($_POST['zippicks_business_meta_nonce']) || 
            !wp_verify_nonce($_POST['zippicks_business_meta_nonce'], 'zippicks_business_meta')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_business', $post_id)) {
            return;
        }
        
        // Save meta fields
        $fields = array(
            'zp_score' => 'floatval',
            'zp_review_count' => 'intval',
            'zp_price_tier' => 'sanitize_text_field',
            'zp_address' => 'sanitize_textarea_field',
            'zp_phone' => 'sanitize_text_field',
            'zp_website' => 'esc_url_raw',
            'zp_listing_tier' => 'sanitize_text_field'
        );
        
        foreach ($fields as $field => $sanitize) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize, $_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
        
        // Handle verification
        $was_verified = get_post_meta($post_id, '_zp_verified', true);
        $is_verified = isset($_POST['zp_verified']) && $_POST['zp_verified'] === '1';
        
        if (!$was_verified && $is_verified) {
            // Business is being verified
            update_post_meta($post_id, '_zp_verified', true);
            update_post_meta($post_id, '_zp_verified_at', current_time('mysql'));
            update_post_meta($post_id, '_zp_verified_by', get_current_user_id());
            
            // Track in database
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'zippicks_business_verification',
                array(
                    'business_id' => $post_id,
                    'verification_type' => 'manual',
                    'verification_status' => 'verified',
                    'verified_by' => get_current_user_id(),
                    'verified_at' => current_time('mysql'),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
        } elseif ($was_verified && !$is_verified) {
            // Business verification is being removed
            delete_post_meta($post_id, '_zp_verified');
            delete_post_meta($post_id, '_zp_verified_at');
            delete_post_meta($post_id, '_zp_verified_by');
        }
    }
}