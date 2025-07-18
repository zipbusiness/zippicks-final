<?php
/**
 * Template Loader Fix for Vibes Archive
 * Fixes blank page issue in Chrome by using WordPress template system properly
 * 
 * @package ZipPicksVibes
 */

namespace ZipPicksVibes;

class TemplateLoaderFix {
    
    /**
     * Initialize the template loader fix
     */
    public static function init() {
        // Use template_include filter with very high priority
        add_filter('template_include', [__CLASS__, 'load_vibe_template'], 999999);
        
        // Also try template_redirect as backup
        // DISABLED: This was causing duplicate rendering
        // add_action('template_redirect', [__CLASS__, 'force_load_template'], 1);
        
        // Add debugging headers
        add_action('send_headers', [__CLASS__, 'add_debug_headers'], 1);
        
        // Fix body classes
        add_filter('body_class', [__CLASS__, 'add_body_classes']);
        
        // Add debug action to verify this is running
        add_action('wp_head', function() {
            if (strpos($_SERVER['REQUEST_URI'] ?? '', '/vibes') !== false) {
                echo '<!-- VIBES TEMPLATE LOADER INITIALIZED -->' . "\n";
            }
        });
    }
    
    /**
     * Load vibe template using WordPress template system
     * 
     * @param string $template Original template path
     * @return string Modified template path
     */
    public static function load_vibe_template($template) {
        // Add visible debug output
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/vibes') !== false) {
            echo '<!-- VIBES TEMPLATE LOADER: URL matches /vibes -->';
        }
        // Check if we're on a vibes page using URL detection first
        $request_uri = isset($_SERVER['REQUEST_URI']) 
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) 
            : '';
        
        $is_vibes_page = false;
        $vibe_slug = '';
        
        // Primary detection: URL-based
        if (strpos($request_uri, '/vibes') !== false) {
            $is_vibes_page = true;
            
            // Extract vibe slug if it's a single vibe page
            if (preg_match('#/vibes/([^/]+)/?#', $request_uri, $matches)) {
                $vibe_slug = $matches[1];
            }
        }
        
        // Debug output
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ZipPicks Vibes Debug] Request URI: ' . $request_uri);
            error_log('[ZipPicks Vibes Debug] Is vibes page: ' . ($is_vibes_page ? 'yes' : 'no'));
            error_log('[ZipPicks Vibes Debug] Original template: ' . $template);
        }
        
        // Fallback: Check query vars when available
        if (!$is_vibes_page) {
            $is_vibes_archive = get_query_var('zippicks_vibes');
            $vibe_slug_qv = get_query_var('vibe_slug');
            
            if ($is_vibes_archive || $vibe_slug_qv) {
                $is_vibes_page = true;
                if ($vibe_slug_qv) {
                    $vibe_slug = $vibe_slug_qv;
                }
            }
        }
        
        if (!$is_vibes_page) {
            return $template;
        }
        
        // Log template loading
        error_log('[ZipPicks Vibes] Loading template for vibes archive');
        
        // Determine which template to load
        if ($vibe_slug) {
            $custom_template = ZIPPICKS_VIBES_DIR . 'templates/client-render/vibe-single.php';
        } else {
            // Use debug template if WP_DEBUG is on
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $debug_template = ZIPPICKS_VIBES_DIR . 'templates/client-render/vibe-archive-debug.php';
                if (file_exists($debug_template)) {
                    return $debug_template;
                }
            }
            
            $custom_template = ZIPPICKS_VIBES_DIR . 'templates/client-render/vibe-archive.php';
        }
        
        // Check if custom template exists
        if (file_exists($custom_template)) {
            error_log('[ZipPicks Vibes] Using custom template: ' . $custom_template);
            return $custom_template;
        }
        
        // Fallback to original template
        error_log('[ZipPicks Vibes] Custom template not found, using default');
        return $template;
    }
    
    /**
     * Add debug headers for Chrome
     */
    public static function add_debug_headers() {
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($current_url, '/vibes') === false) {
            return;
        }
        
        // Add headers to prevent Chrome caching issues
        header('X-ZipPicks-Template: vibes-archive');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Remove problematic CSP headers that might block resources
        if (!headers_sent()) {
            header_remove('Content-Security-Policy');
        }
    }
    
    /**
     * Add body classes for vibes pages
     */
    public static function add_body_classes($classes) {
        // Use URL-based detection for body classes
        $request_uri = isset($_SERVER['REQUEST_URI']) 
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) 
            : '';
        
        $is_vibes_page = false;
        
        // Check URL first
        if (strpos($request_uri, '/vibes') !== false) {
            $is_vibes_page = true;
        }
        
        // Fallback to query vars
        if (!$is_vibes_page && (get_query_var('zippicks_vibes') || get_query_var('vibe_slug'))) {
            $is_vibes_page = true;
        }
        
        if ($is_vibes_page) {
            $classes[] = 'vibes-archive';
            $classes[] = 'zippicks-vibes-page';
            $classes[] = 'no-sidebar'; // Force no sidebar
            $classes[] = 'full-width-content'; // Force full width
            
            // Add browser-specific class
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (strpos($user_agent, 'Chrome') !== false) {
                $classes[] = 'chrome-browser';
            }
        }
        
        return $classes;
    }
    
    /**
     * Force load template using template_redirect
     */
    public static function force_load_template() {
        // Check if we're on a vibes page
        $request_uri = isset($_SERVER['REQUEST_URI']) 
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) 
            : '';
            
        if (strpos($request_uri, '/vibes') === false) {
            return;
        }
        
        // Check if this is the main query
        if (!is_main_query()) {
            return;
        }
        
        // Determine which template to load
        if (preg_match('#/vibes/([^/]+)/?#', $request_uri, $matches)) {
            $template = ZIPPICKS_VIBES_DIR . 'templates/client-render/vibe-single.php';
        } else {
            $template = ZIPPICKS_VIBES_DIR . 'templates/client-render/vibe-archive.php';
        }
        
        // Load the template if it exists
        if (file_exists($template)) {
            include($template);
            exit; // Stop WordPress from loading any other template
        }
    }
}

// Initialize the fix
TemplateLoaderFix::init();