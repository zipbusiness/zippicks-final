<?php
/**
 * ZipBusiness API Metabox
 *
 * Adds API data metabox to business edit screen for managing
 * ZPID linking, verification status, and sync operations.
 *
 * @package ZipPicks_Business
 * @since 1.0.0
 */
class ZipPicks_Business_API_Metabox {
    
    /**
     * API sync service instance
     * @var ZipPicks_Business_API_Sync_Service
     */
    private $sync_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_metabox'));
        add_action('wp_ajax_zippicks_sync_api_data', array($this, 'ajax_sync_api_data'));
        add_action('wp_ajax_zippicks_link_zpid', array($this, 'ajax_link_zpid'));
        add_action('wp_ajax_zippicks_search_business', array($this, 'ajax_search_business'));
        
        // Initialize sync service
        require_once ZIPPICKS_BUSINESS_PLUGIN_DIR . 'api/class-api-sync-service.php';
        $this->sync_service = new ZipPicks_Business_API_Sync_Service();
    }
    
    /**
     * Add API data metabox
     */
    public function add_metabox() {
        add_meta_box(
            'zippicks_api_data',
            __('ZipBusiness API Data', 'zippicks-business'),
            array($this, 'render_metabox'),
            'zippicks_business',
            'side',
            'high'
        );
    }
    
    /**
     * Render the API data metabox
     *
     * @param WP_Post $post Current post object
     */
    public function render_metabox($post) {
        // Get API data
        $zpid = get_post_meta($post->ID, 'zpid', true);
        $verified = get_post_meta($post->ID, 'api_verified', true);
        $confidence = get_post_meta($post->ID, 'api_confidence_score', true);
        $last_sync = get_post_meta($post->ID, 'last_api_sync', true);
        $api_vibes = json_decode(get_post_meta($post->ID, 'api_vibes', true), true);
        
        // Add nonce field
        wp_nonce_field('zippicks_api_metabox', 'zippicks_api_nonce');
        ?>
        <div class="zippicks-api-metabox">
            <?php if ($zpid): ?>
                <div class="api-status">
                    <p><strong><?php _e('ZPID:', 'zippicks-business'); ?></strong> 
                        <code><?php echo esc_html($zpid); ?></code>
                    </p>
                    
                    <p><strong><?php _e('Status:', 'zippicks-business'); ?></strong> 
                        <span class="status-badge <?php echo $verified ? 'verified' : 'unverified'; ?>">
                            <?php echo $verified ? __('Verified', 'zippicks-business') : __('Unverified', 'zippicks-business'); ?>
                            <?php if ($verified && $confidence): ?>
                                <small>(<?php echo number_format($confidence * 100, 0); ?>%)</small>
                            <?php endif; ?>
                        </span>
                    </p>
                    
                    <?php if ($last_sync): ?>
                    <p><strong><?php _e('Last Sync:', 'zippicks-business'); ?></strong> 
                        <?php echo esc_html(human_time_diff(strtotime($last_sync), current_time('timestamp'))); ?> ago
                    </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($api_vibes)): ?>
                    <div class="api-vibes">
                        <strong><?php _e('API Vibes:', 'zippicks-business'); ?></strong>
                        <div class="vibe-list">
                            <?php foreach (array_slice($api_vibes, 0, 5) as $vibe): ?>
                                <span class="vibe-tag" title="<?php echo esc_attr($vibe['confidence'] * 100); ?>% confidence">
                                    <?php echo esc_html($vibe['display_name']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="api-actions">
                        <button type="button" class="button sync-api-data" data-post-id="<?php echo esc_attr($post->ID); ?>" data-zpid="<?php echo esc_attr($zpid); ?>">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Sync with API', 'zippicks-business'); ?>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-api-data">
                    <p><?php _e('No API data linked to this business.', 'zippicks-business'); ?></p>
                    
                    <div class="link-zpid-form">
                        <label for="manual-zpid"><?php _e('Enter ZPID:', 'zippicks-business'); ?></label>
                        <input type="text" id="manual-zpid" class="regular-text" placeholder="e.g. zpb_12345">
                        <button type="button" class="button link-zpid" data-post-id="<?php echo esc_attr($post->ID); ?>">
                            <?php _e('Link ZPID', 'zippicks-business'); ?>
                        </button>
                    </div>
                    
                    <div class="search-business">
                        <p><strong><?php _e('Or search for business:', 'zippicks-business'); ?></strong></p>
                        <button type="button" class="button search-api-business" data-post-id="<?php echo esc_attr($post->ID); ?>">
                            <span class="dashicons dashicons-search"></span>
                            <?php _e('Search ZipBusiness', 'zippicks-business'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="api-messages" style="display: none;">
                <div class="notice"></div>
            </div>
        </div>
        
        <style>
        .zippicks-api-metabox {
            padding: 10px 0;
        }
        .zippicks-api-metabox .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .zippicks-api-metabox .status-badge.verified {
            background: #d4edda;
            color: #155724;
        }
        .zippicks-api-metabox .status-badge.unverified {
            background: #f8d7da;
            color: #721c24;
        }
        .zippicks-api-metabox .vibe-list {
            margin-top: 5px;
        }
        .zippicks-api-metabox .vibe-tag {
            display: inline-block;
            background: #f0f0f0;
            padding: 2px 8px;
            margin: 2px;
            border-radius: 3px;
            font-size: 11px;
        }
        .zippicks-api-metabox .api-actions,
        .zippicks-api-metabox .link-zpid-form,
        .zippicks-api-metabox .search-business {
            margin-top: 15px;
        }
        .zippicks-api-metabox .button .dashicons {
            vertical-align: middle;
            margin-right: 3px;
        }
        .zippicks-api-metabox input[type="text"] {
            width: 100%;
            margin-bottom: 8px;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for syncing API data
     */
    public function ajax_sync_api_data() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zippicks_api_sync')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $zpid = sanitize_text_field($_POST['zpid']);
        
        // Sync with API
        $result = $this->sync_service->sync_business($post_id, $zpid);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Get updated data
        $verified = get_post_meta($post_id, 'api_verified', true);
        $confidence = get_post_meta($post_id, 'api_confidence_score', true);
        $last_sync = get_post_meta($post_id, 'last_api_sync', true);
        
        wp_send_json_success(array(
            'message' => __('Successfully synced with API', 'zippicks-business'),
            'verified' => $verified,
            'confidence' => $confidence,
            'last_sync' => human_time_diff(strtotime($last_sync), current_time('timestamp')) . ' ago'
        ));
    }
    
    /**
     * AJAX handler for linking ZPID
     */
    public function ajax_link_zpid() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zippicks_api_sync')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $zpid = sanitize_text_field($_POST['zpid']);
        
        if (empty($zpid)) {
            wp_send_json_error(__('Please enter a valid ZPID', 'zippicks-business'));
        }
        
        // Link and sync
        $result = $this->sync_service->link_zpid($post_id, $zpid);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('Successfully linked ZPID and synced data', 'zippicks-business'),
            'reload' => true
        ));
    }
    
    /**
     * AJAX handler for searching businesses
     */
    public function ajax_search_business() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zippicks_api_sync')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error('Invalid post ID');
        }
        
        // Get business name and location from post
        $name = $post->post_title;
        $location = get_post_meta($post_id, '_zp_city', true) . ', ' . get_post_meta($post_id, '_zp_state', true);
        
        // Search API
        $results = $this->sync_service->search_business($name, $location);
        
        if (is_wp_error($results)) {
            wp_send_json_error($results->get_error_message());
        }
        
        wp_send_json_success(array(
            'results' => $results,
            'message' => sprintf(__('Found %d matches', 'zippicks-business'), count($results))
        ));
    }
}