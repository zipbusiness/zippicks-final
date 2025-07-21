<?php
/**
 * Anti-scraping protection per CLAUDE.md requirements
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Scrape_Protection {
    
    /**
     * Initialize anti-scraping protections
     */
    public static function init() {
        // Prevent direct access to lists
        add_action('template_redirect', [__CLASS__, 'protect_lists']);
        
        // Add AJAX-only content loading
        add_action('wp_footer', [__CLASS__, 'inject_ajax_loader']);
        
        // Add watermarking to content
        add_filter('the_content', [__CLASS__, 'add_watermarks'], 999);
        
        // Add AJAX handlers for content loading
        add_action('wp_ajax_zippicks_load_list_content', [__CLASS__, 'ajax_load_list_content']);
        add_action('wp_ajax_nopriv_zippicks_load_list_content', [__CLASS__, 'ajax_load_list_content']);
        
        // Add security headers to API responses
        add_action('rest_api_init', [__CLASS__, 'add_security_headers']);
        
        // Add rate limiting for requests
        add_action('wp', [__CLASS__, 'rate_limit_requests']);
    }
    
    /**
     * Protect master critic lists from direct access
     */
    public static function protect_lists() {
        if (is_singular('master_critic_list')) {
            // Check for legitimate AJAX requests
            $is_ajax = wp_doing_ajax() && isset($_SERVER['HTTP_X_REQUESTED_WITH']);
            $has_valid_nonce = isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'zippicks_list_' . get_the_ID());
            
            if (!$is_ajax && !$has_valid_nonce) {
                // Log suspicious access attempt
                self::log_scraping_attempt();
                
                // Show skeleton only for non-AJAX requests
                self::show_skeleton_page();
                exit;
            }
        }
    }
    
    /**
     * Show skeleton loading page
     */
    private static function show_skeleton_page() {
        // Get template path
        $template = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'templates/list-skeleton.php';
        
        if (file_exists($template)) {
            include $template;
        } else {
            // Fallback skeleton
            self::render_fallback_skeleton();
        }
    }
    
    /**
     * Render fallback skeleton if template doesn't exist
     */
    private static function render_fallback_skeleton() {
        get_header();
        ?>
        <div class="zippicks-list-container">
            <div id="zippicks-list-content">
                <div class="zp-skeleton">
                    <div class="zp-skeleton-title"></div>
                    <div class="zp-skeleton-list">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                        <div class="zp-skeleton-item">
                            <div class="zp-skeleton-rank"></div>
                            <div class="zp-skeleton-content">
                                <div class="zp-skeleton-name"></div>
                                <div class="zp-skeleton-desc"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <p class="zp-loading">Loading recommendations...</p>
            </div>
        </div>
        
        <style>
        .zp-skeleton-title { 
            height: 40px; 
            background: #f0f0f0; 
            margin-bottom: 30px;
            animation: pulse 1.5s infinite;
        }
        .zp-skeleton-item {
            display: flex;
            margin-bottom: 20px;
            padding: 20px;
            background: #fff;
            border: 1px solid #eee;
        }
        .zp-skeleton-rank {
            width: 40px;
            height: 40px;
            background: #f0f0f0;
            border-radius: 50%;
            margin-right: 20px;
            animation: pulse 1.5s infinite;
        }
        .zp-skeleton-name {
            height: 24px;
            width: 200px;
            background: #f0f0f0;
            margin-bottom: 10px;
            animation: pulse 1.5s infinite;
        }
        .zp-skeleton-desc {
            height: 16px;
            width: 100%;
            background: #f0f0f0;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
        </style>
        <?php
        get_footer();
    }
    
    /**
     * Inject AJAX loader script
     */
    public static function inject_ajax_loader() {
        if (is_singular('master_critic_list')): ?>
        <script>
        jQuery(document).ready(function($) {
            // Load actual content via AJAX after page load
            $.post(ajaxurl, {
                action: 'zippicks_load_list_content',
                list_id: <?php echo get_the_ID(); ?>,
                nonce: '<?php echo wp_create_nonce('zippicks_list_' . get_the_ID()); ?>'
            }, function(response) {
                if (response.success) {
                    $('#zippicks-list-content').html(response.data.content);
                    
                    // Fire custom event for other scripts
                    $(document).trigger('zippicks_content_loaded', [response.data]);
                } else {
                    $('#zippicks-list-content').html('<p class="error">Unable to load content. Please refresh the page.</p>');
                }
            }).fail(function() {
                $('#zippicks-list-content').html('<p class="error">Connection error. Please refresh the page.</p>');
            });
        });
        </script>
        <?php endif;
    }
    
    /**
     * AJAX handler for loading list content
     */
    public static function ajax_load_list_content() {
        // Verify nonce
        $list_id = intval($_POST['list_id']);
        if (!wp_verify_nonce($_POST['nonce'], 'zippicks_list_' . $list_id)) {
            wp_send_json_error('Invalid security token');
        }
        
        // Rate limiting check
        if (self::is_rate_limited()) {
            wp_send_json_error('Too many requests. Please wait a moment.');
        }
        
        // Get the list post
        $list_post = get_post($list_id);
        if (!$list_post || $list_post->post_type !== 'master_critic_list') {
            wp_send_json_error('List not found');
        }
        
        // Generate secure content with watermarks
        $content = self::generate_protected_content($list_post);
        
        // Log legitimate access
        self::log_impression($list_id);
        
        wp_send_json_success([
            'content' => $content,
            'list_id' => $list_id,
            'fingerprint' => self::generate_fingerprint($list_id)
        ]);
    }
    
    /**
     * Generate protected content with watermarks
     */
    private static function generate_protected_content($list_post) {
        // Apply content filters
        $content = apply_filters('the_content', $list_post->post_content);
        
        // Add watermarks and copy traps
        $content = self::add_watermarks($content);
        $content = self::add_copy_traps($content);
        
        return $content;
    }
    
    /**
     * Add watermarks to content
     */
    public static function add_watermarks($content) {
        if (is_singular('master_critic_list') || wp_doing_ajax()) {
            $fingerprint = self::generate_fingerprint(get_the_ID());
            
            // Create invisible watermark
            $watermark = sprintf(
                '<span class="zp-fp" data-hash="ZP%s" style="display:none;position:absolute;left:-9999px;">%s</span>',
                substr($fingerprint, 0, 10),
                wp_generate_password(32, false)
            );
            
            // Add watermarks throughout content at paragraph breaks
            $content = str_replace('</p>', $watermark . '</p>', $content);
            
            // Add watermark to list items
            $content = str_replace('</li>', $watermark . '</li>', $content);
            
            // Add timestamp-based watermark
            $timestamp_mark = sprintf(
                '<span class="zp-ts" data-ts="%s" style="display:none;">%s</span>',
                time(),
                wp_generate_password(16, false)
            );
            $content .= $timestamp_mark;
        }
        
        return $content;
    }
    
    /**
     * Add invisible copy traps
     */
    private static function add_copy_traps($content) {
        $traps = [
            'Do not redistribute without permission.',
            'Copyright ZipPicks - All rights reserved.',
            'This content is protected by anti-scraping technology.',
            'Unauthorized copying is prohibited.'
        ];
        
        foreach ($traps as $trap) {
            $trap_span = sprintf(
                '<span style="display:none;visibility:hidden;opacity:0;position:absolute;left:-9999px;">%s</span>',
                $trap
            );
            $content .= $trap_span;
        }
        
        return $content;
    }
    
    /**
     * Generate unique fingerprint for tracking
     */
    private static function generate_fingerprint($list_id = null) {
        $list_id = $list_id ?: get_the_ID();
        $session_token = wp_get_session_token() ?: session_id();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $timestamp = current_time('timestamp');
        
        return wp_hash($list_id . $session_token . $user_agent . $timestamp);
    }
    
    /**
     * Log legitimate impressions
     */
    private static function log_impression($list_id) {
        if (class_exists('ZipPicks_Master_Critic_Database')) {
            ZipPicks_Master_Critic_Database::log_scrape_attempt([
                'list_id' => $list_id,
                'fingerprint' => self::generate_fingerprint($list_id),
                'ip_address' => self::get_client_ip(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'referer' => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255),
                'action' => 'view',
                'is_suspicious' => 0
            ]);
        }
    }
    
    /**
     * Log suspicious scraping attempts
     */
    private static function log_scraping_attempt() {
        if (class_exists('ZipPicks_Master_Critic_Database')) {
            ZipPicks_Master_Critic_Database::log_scrape_attempt([
                'list_id' => get_the_ID(),
                'fingerprint' => self::generate_fingerprint(),
                'ip_address' => self::get_client_ip(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'referer' => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255),
                'action' => 'blocked_access',
                'is_suspicious' => 1
            ]);
        }
    }
    
    /**
     * Get client IP address (handle proxies)
     */
    private static function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (proxy chains)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if request is rate limited
     */
    private static function is_rate_limited() {
        $ip = self::get_client_ip();
        $key = 'zippicks_rate_' . md5($ip);
        $requests = get_transient($key) ?: 0;
        
        // Allow 10 requests per minute
        if ($requests >= 10) {
            return true;
        }
        
        set_transient($key, $requests + 1, 60);
        return false;
    }
    
    /**
     * Apply rate limiting to requests
     */
    public static function rate_limit_requests() {
        if (is_singular('master_critic_list') && self::is_rate_limited()) {
            wp_die('Rate limit exceeded. Please wait before making more requests.', 'Rate Limited', ['response' => 429]);
        }
    }
    
    /**
     * Add security headers to REST API responses
     */
    public static function add_security_headers() {
        add_filter('rest_pre_serve_request', function($served, $result, $request) {
            if (strpos($request->get_route(), '/zippicks/') !== false) {
                header('X-Robots-Tag: noindex');
                header('Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate');
                header('X-ZipPicks-Source: frontend-only');
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: DENY');
            }
            return $served;
        }, 10, 3);
    }
}

// Initialize anti-scraping protection
ZipPicks_Master_Critic_Scrape_Protection::init();