<?php
/**
 * Template Loader for ZipPicks Business
 *
 * Handles loading of business display templates with anti-scraping
 * protections and frontend hydration patterns.
 *
 * @package ZipPicks_Business
 * @since 1.0.0
 */
class ZipPicks_Business_Template_Loader {
    
    /**
     * Logger instance
     * @var object
     */
    private $logger = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get Foundation services if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $this->logger = zippicks()->get('logger');
        }
        
        // Hook into template loading
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_head', array($this, 'add_template_styles'), 20);
    }
    
    /**
     * Load template with anti-scraping protections
     *
     * @param string $template Template name
     * @param array $args Template arguments
     * @param bool $hydrate_client_side Whether to use client-side hydration
     * @return string Template output
     */
    public static function load_template($template, $args = array(), $hydrate_client_side = true) {
        $template_path = ZIPPICKS_BUSINESS_PLUGIN_DIR . 'templates/' . $template . '.php';
        
        if (!file_exists($template_path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return '<!-- ZipPicks Business Template not found: ' . esc_html($template) . ' -->';
            }
            return '';
        }
        
        // Extract args for template
        extract($args);
        
        if ($hydrate_client_side) {
            // Client-side hydration pattern for anti-scraping
            $placeholder_id = 'zp-' . $template . '-' . wp_generate_password(8, false, false);
            $template_data = self::prepare_template_data($template, $args);
            
            ob_start();
            ?>
            <div id="<?php echo esc_attr($placeholder_id); ?>" class="zp-template-placeholder" data-template="<?php echo esc_attr($template); ?>">
                <div class="zp-loading-skeleton">
                    <?php self::render_loading_skeleton($template); ?>
                </div>
            </div>
            <script type="application/json" class="zp-template-data" data-for="<?php echo esc_attr($placeholder_id); ?>">
                <?php echo wp_json_encode($template_data); ?>
            </script>
            <?php
            return ob_get_clean();
        } else {
            // Direct template loading
            ob_start();
            include $template_path;
            return ob_get_clean();
        }
    }
    
    /**
     * Render verification badge
     *
     * @param int $business_id Business post ID
     * @param string $display_mode Display mode ('full', 'compact', 'inline')
     * @param bool $hydrate Use client-side hydration
     * @return string Template output
     */
    public static function verification_badge($business_id = null, $display_mode = 'full', $hydrate = true) {
        return self::load_template('business-verification-badge', array(
            'business_id' => $business_id ?: get_the_ID(),
            'display_mode' => $display_mode
        ), $hydrate);
    }
    
    /**
     * Render business vibes
     *
     * @param int $business_id Business post ID
     * @param string $display_mode Display mode ('full', 'compact', 'inline')
     * @param int $max_vibes Maximum vibes to show
     * @param float $min_confidence Minimum confidence threshold
     * @param bool $hydrate Use client-side hydration
     * @return string Template output
     */
    public static function business_vibes($business_id = null, $display_mode = 'full', $max_vibes = 5, $min_confidence = 0.6, $hydrate = true) {
        return self::load_template('business-vibes', array(
            'business_id' => $business_id ?: get_the_ID(),
            'display_mode' => $display_mode,
            'max_vibes' => $max_vibes,
            'min_confidence' => $min_confidence
        ), $hydrate);
    }
    
    /**
     * Render ZipPicks scores (excluding external ratings)
     *
     * @param int $business_id Business post ID
     * @param string $display_mode Display mode ('full', 'compact', 'summary')
     * @param bool $hydrate Use client-side hydration
     * @return string Template output
     */
    public static function zippicks_scores($business_id = null, $display_mode = 'full', $hydrate = true) {
        return self::load_template('zippicks-scores', array(
            'business_id' => $business_id ?: get_the_ID(),
            'display_mode' => $display_mode
        ), $hydrate);
    }
    
    /**
     * Prepare template data for client-side hydration
     *
     * @param string $template Template name
     * @param array $args Template arguments
     * @return array Template data
     */
    private static function prepare_template_data($template, $args) {
        $business_id = $args['business_id'] ?? get_the_ID();
        
        switch ($template) {
            case 'business-verification-badge':
                return array(
                    'is_verified' => get_post_meta($business_id, 'api_verified', true),
                    'zpid' => get_post_meta($business_id, 'zpid', true),
                    'confidence' => get_post_meta($business_id, 'api_confidence_score', true),
                    'display_mode' => $args['display_mode'] ?? 'full'
                );
                
            case 'business-vibes':
                $api_vibes = json_decode(get_post_meta($business_id, 'api_vibes', true), true) ?: array();
                $min_confidence = $args['min_confidence'] ?? 0.6;
                $max_vibes = $args['max_vibes'] ?? 5;
                
                // Filter and sort vibes
                $filtered_vibes = array_filter($api_vibes, function($vibe) use ($min_confidence) {
                    return isset($vibe['confidence']) && $vibe['confidence'] >= $min_confidence;
                });
                
                usort($filtered_vibes, function($a, $b) {
                    return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
                });
                
                return array(
                    'vibes' => array_slice($filtered_vibes, 0, $max_vibes),
                    'display_mode' => $args['display_mode'] ?? 'full'
                );
                
            case 'zippicks-scores':
                return array(
                    'pillar_scores' => get_post_meta($business_id, 'pillar_scores', true) ?: array(),
                    'display_mode' => $args['display_mode'] ?? 'full'
                );
                
            default:
                return array();
        }
    }
    
    /**
     * Render loading skeleton for template
     *
     * @param string $template Template name
     */
    private static function render_loading_skeleton($template) {
        switch ($template) {
            case 'business-verification-badge':
                echo '<div class="skeleton-badge"></div>';
                break;
                
            case 'business-vibes':
                echo '<div class="skeleton-vibes">';
                for ($i = 0; $i < 3; $i++) {
                    echo '<div class="skeleton-vibe-tag"></div>';
                }
                echo '</div>';
                break;
                
            case 'zippicks-scores':
                echo '<div class="skeleton-scores">';
                for ($i = 0; $i < 4; $i++) {
                    echo '<div class="skeleton-score-item"></div>';
                }
                echo '</div>';
                break;
                
            default:
                echo '<div class="skeleton-generic"></div>';
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only on business-related pages
        if (!is_singular('zippicks_business') && !is_post_type_archive('zippicks_business') && !is_tax()) {
            return;
        }
        
        wp_enqueue_script(
            'zippicks-business-templates',
            ZIPPICKS_BUSINESS_PLUGIN_URL . 'assets/js/templates.js',
            array('jquery'),
            ZIPPICKS_BUSINESS_VERSION,
            true
        );
        
        // Add anti-scraping headers
        $this->add_anti_scraping_headers();
    }
    
    /**
     * Add template styles to head
     */
    public function add_template_styles() {
        if (!is_singular('zippicks_business') && !is_post_type_archive('zippicks_business')) {
            return;
        }
        ?>
        <style>
        /* Loading skeleton styles */
        .zp-template-placeholder {
            min-height: 40px;
            position: relative;
        }
        
        .zp-loading-skeleton {
            animation: skeleton-pulse 1.5s ease-in-out infinite alternate;
        }
        
        @keyframes skeleton-pulse {
            0% { opacity: 1; }
            100% { opacity: 0.4; }
        }
        
        .skeleton-badge {
            height: 28px;
            width: 150px;
            background: #e0e0e0;
            border-radius: 6px;
        }
        
        .skeleton-vibes {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .skeleton-vibe-tag {
            height: 24px;
            width: 80px;
            background: #e0e0e0;
            border-radius: 12px;
        }
        
        .skeleton-scores {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 12px;
        }
        
        .skeleton-score-item {
            height: 60px;
            background: #e0e0e0;
            border-radius: 8px;
        }
        
        .skeleton-generic {
            height: 40px;
            background: #e0e0e0;
            border-radius: 4px;
        }
        
        /* Hide content until hydrated */
        .zp-template-placeholder[data-hydrated="true"] .zp-loading-skeleton {
            display: none;
        }
        
        /* Dark theme skeletons */
        @media (prefers-color-scheme: dark) {
            .skeleton-badge,
            .skeleton-vibe-tag,
            .skeleton-score-item,
            .skeleton-generic {
                background: #333;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Add anti-scraping headers
     */
    private function add_anti_scraping_headers() {
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex');
            header('Cache-Control: private, max-age=0');
            header('X-ZipPicks-Source: frontend-only');
        }
    }
    
    /**
     * Add fingerprint watermark to content
     *
     * @param string $content Content to watermark
     * @return string Watermarked content
     */
    public static function add_watermark($content) {
        $fingerprint = wp_generate_password(8, false, false);
        $watermark = '<span class="zp-fp" data-hash="ZP' . $fingerprint . '" style="display:none;"></span>';
        
        // Log fingerprint for tracking
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('Content fingerprint generated', array(
                'fingerprint' => $fingerprint,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ));
        }
        
        return $content . $watermark;
    }
    
    /**
     * Check if content should be hydrated client-side
     *
     * @return bool Whether to use client-side hydration
     */
    public static function should_hydrate() {
        // Check user agent for known bots
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $bot_patterns = array(
            'googlebot', 'bingbot', 'slurp', 'duckduckbot',
            'baiduspider', 'yandexbot', 'facebookexternalhit',
            'curl', 'wget', 'scrapy', 'python-requests'
        );
        
        foreach ($bot_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                return false; // Don't hydrate for bots
            }
        }
        
        // Check for empty or suspicious user agents
        if (empty($user_agent) || strlen($user_agent) < 10) {
            return false;
        }
        
        return true;
    }
}