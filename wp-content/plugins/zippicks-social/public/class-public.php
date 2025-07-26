<?php
/**
 * Public-facing functionality
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Social_Public
 * 
 * Handles public-facing functionality
 */
class ZipPicks_Social_Public {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register shortcodes
        add_action('init', [$this, 'register_shortcodes']);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        
        // Add follow button to author pages
        add_action('zippicks_author_profile_actions', [$this, 'add_author_follow_button'], 10, 2);
        
        // Add follow counts to author meta
        add_filter('zippicks_author_meta', [$this, 'add_follow_counts_to_meta'], 10, 2);
        
        // Handle frontend AJAX for logged-out users
        add_action('wp_ajax_nopriv_zippicks_check_follow_status', [$this, 'check_follow_status']);
    }
    
    /**
     * Register shortcodes
     *
     * @return void
     */
    public function register_shortcodes(): void {
        add_shortcode('zippicks_follow_button', [$this, 'shortcode_follow_button']);
        add_shortcode('zippicks_followers_count', [$this, 'shortcode_followers_count']);
        add_shortcode('zippicks_following_count', [$this, 'shortcode_following_count']);
        add_shortcode('zippicks_followers_list', [$this, 'shortcode_followers_list']);
        add_shortcode('zippicks_following_list', [$this, 'shortcode_following_list']);
        add_shortcode('zippicks_activity_feed', [$this, 'shortcode_activity_feed']);
    }
    
    /**
     * Follow button shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function shortcode_follow_button($atts): string {
        $atts = shortcode_atts([
            'entity_id' => 0,
            'entity_type' => 'user',
            'show_count' => 'true',
            'class' => 'zps-follow-button',
            'size' => 'medium',
            'style' => 'default'
        ], $atts);
        
        if (!$atts['entity_id']) {
            return '';
        }
        
        $args = [
            'class' => $atts['class'] . ' zps-size-' . $atts['size'] . ' zps-style-' . $atts['style'],
            'show_count' => $atts['show_count'] === 'true'
        ];
        
        return zippicks_social_follow_button(
            (int) $atts['entity_id'],
            $atts['entity_type'],
            $args
        );
    }
    
    /**
     * Followers count shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function shortcode_followers_count($atts): string {
        $atts = shortcode_atts([
            'entity_id' => 0,
            'entity_type' => 'user',
            'format' => 'true'
        ], $atts);
        
        if (!$atts['entity_id']) {
            return '0';
        }
        
        $count = zippicks_social_followers_count(
            (int) $atts['entity_id'],
            $atts['entity_type']
        );
        
        return $atts['format'] === 'true' ? number_format_i18n($count) : (string) $count;
    }
    
    /**
     * Following count shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function shortcode_following_count($atts): string {
        $atts = shortcode_atts([
            'user_id' => 0,
            'format' => 'true'
        ], $atts);
        
        if (!$atts['user_id']) {
            return '0';
        }
        
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-follow-manager.php';
        $follow_manager = new ZipPicks_Social_Follow_Manager();
        
        $count = $follow_manager->get_following_count((int) $atts['user_id']);
        
        return $atts['format'] === 'true' ? number_format_i18n($count) : (string) $count;
    }
    
    /**
     * Followers list shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function shortcode_followers_list($atts): string {
        $atts = shortcode_atts([
            'entity_id' => 0,
            'entity_type' => 'user',
            'limit' => 10,
            'show_avatar' => 'true',
            'link_to_profile' => 'true'
        ], $atts);
        
        if (!$atts['entity_id']) {
            return '';
        }
        
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-follow-manager.php';
        $follow_manager = new ZipPicks_Social_Follow_Manager();
        
        $followers = $follow_manager->get_followers(
            (int) $atts['entity_id'],
            $atts['entity_type'],
            ['limit' => (int) $atts['limit']]
        );
        
        if (empty($followers)) {
            return '<p class="zps-no-followers">' . __('No followers yet.', 'zippicks-social') . '</p>';
        }
        
        ob_start();
        include ZIPPICKS_SOCIAL_PLUGIN_DIR . 'templates/followers-list.php';
        return ob_get_clean();
    }
    
    /**
     * Following list shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function shortcode_following_list($atts): string {
        $atts = shortcode_atts([
            'user_id' => 0,
            'limit' => 10,
            'entity_type' => '',
            'show_type' => 'true'
        ], $atts);
        
        if (!$atts['user_id']) {
            return '';
        }
        
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-follow-manager.php';
        $follow_manager = new ZipPicks_Social_Follow_Manager();
        
        $args = ['limit' => (int) $atts['limit']];
        if ($atts['entity_type']) {
            $args['entity_type'] = $atts['entity_type'];
        }
        
        $following = $follow_manager->get_following((int) $atts['user_id'], $args);
        
        if (empty($following)) {
            return '<p class="zps-no-following">' . __('Not following anyone yet.', 'zippicks-social') . '</p>';
        }
        
        ob_start();
        include ZIPPICKS_SOCIAL_PLUGIN_DIR . 'templates/following-list.php';
        return ob_get_clean();
    }
    
    /**
     * Activity feed shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function shortcode_activity_feed($atts): string {
        $atts = shortcode_atts([
            'user_id' => get_current_user_id(),
            'limit' => 20,
            'filter' => 'all'
        ], $atts);
        
        if (!$atts['user_id'] || !is_user_logged_in()) {
            return '';
        }
        
        // This is a placeholder for activity feed
        ob_start();
        include ZIPPICKS_SOCIAL_PLUGIN_DIR . 'templates/activity-feed.php';
        return ob_get_clean();
    }
    
    /**
     * Add follow button to author pages
     *
     * @param int $author_id Author ID
     * @param bool $is_own_profile Whether viewing own profile
     * @return void
     */
    public function add_author_follow_button($author_id, $is_own_profile): void {
        if (!$is_own_profile && is_user_logged_in()) {
            echo zippicks_social_follow_button($author_id, 'user', [
                'class' => 'zps-follow-button zps-author-follow',
                'show_count' => true
            ]);
        }
    }
    
    /**
     * Add follow counts to author meta
     *
     * @param array $meta Current meta data
     * @param int $author_id Author ID
     * @return array Modified meta data
     */
    public function add_follow_counts_to_meta($meta, $author_id): array {
        require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-follow-manager.php';
        $follow_manager = new ZipPicks_Social_Follow_Manager();
        
        $meta['followers_count'] = $follow_manager->get_followers_count($author_id, 'user');
        $meta['following_count'] = $follow_manager->get_following_count($author_id);
        
        return $meta;
    }
    
    /**
     * Enqueue public assets
     *
     * @return void
     */
    public function enqueue_public_assets(): void {
        // CSS
        wp_enqueue_style(
            'zippicks-social-public',
            ZIPPICKS_SOCIAL_PLUGIN_URL . 'assets/css/public.css',
            [],
            ZIPPICKS_SOCIAL_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'zippicks-social-follow',
            ZIPPICKS_SOCIAL_PLUGIN_URL . 'assets/js/follow-system.js',
            ['jquery'],
            ZIPPICKS_SOCIAL_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('zippicks-social-follow', 'zippicksSocial', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('zippicks-social/v1/'),
            'nonce' => wp_create_nonce('zippicks_social_follow'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'isLoggedIn' => is_user_logged_in(),
            'currentUserId' => get_current_user_id(),
            'strings' => [
                'follow' => __('Follow', 'zippicks-social'),
                'following' => __('Following', 'zippicks-social'),
                'unfollow' => __('Unfollow', 'zippicks-social'),
                'loading' => __('Loading...', 'zippicks-social'),
                'error' => __('An error occurred. Please try again.', 'zippicks-social'),
                'loginRequired' => __('You must be logged in to follow.', 'zippicks-social')
            ]
        ]);
    }
}