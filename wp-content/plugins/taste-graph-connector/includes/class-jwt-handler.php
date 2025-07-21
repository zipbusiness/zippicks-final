<?php
/**
 * JWT Handler Class
 * 
 * Handles JWT token generation and validation for secure communication
 * with the ZipBusiness API
 * 
 * @package TasteGraphConnector
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * TGC_JWT_Handler class
 */
class TGC_JWT_Handler {
    
    /**
     * JWT algorithm
     */
    const ALGORITHM = 'HS256';
    
    /**
     * Token expiration time in seconds (1 hour)
     */
    const TOKEN_EXPIRY = 3600;
    
    /**
     * Generate JWT token for a WordPress user
     * 
     * @param int $wp_user_id WordPress user ID (BIGINT)
     * @param array $additional_claims Optional additional claims
     * @return string|false JWT token or false on failure
     */
    public static function generate_token($wp_user_id, $additional_claims = array()) {
        // Validate user ID
        $wp_user_id = absint($wp_user_id);
        if (!$wp_user_id) {
            return false;
        }
        
        // Get user data
        $user = get_user_by('id', $wp_user_id);
        if (!$user) {
            return false;
        }
        
        // Get JWT secret
        $jwt_secret = self::get_jwt_secret();
        if (!$jwt_secret) {
            if (get_option('tgc_debug_mode', 'no') === 'yes') {
                error_log('TGC JWT: No JWT secret configured');
            }
            return false;
        }
        
        // Prepare payload
        $issued_at = time();
        $expiration = $issued_at + self::TOKEN_EXPIRY;
        
        $payload = array(
            'iss' => get_site_url(), // Issuer
            'iat' => $issued_at, // Issued at
            'exp' => $expiration, // Expiration
            'wp_user_id' => (int)$wp_user_id, // Ensure integer for BIGINT compatibility
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'roles' => $user->roles,
            'site_id' => get_current_blog_id() // For multisite compatibility
        );
        
        // Merge additional claims
        if (!empty($additional_claims) && is_array($additional_claims)) {
            $payload = array_merge($payload, $additional_claims);
        }
        
        try {
            // Generate token
            $token = JWT::encode($payload, $jwt_secret, self::ALGORITHM);
            
            // Cache token for reuse
            self::cache_token($wp_user_id, $token, self::TOKEN_EXPIRY);
            
            return $token;
        } catch (Exception $e) {
            if (get_option('tgc_debug_mode', 'no') === 'yes') {
                error_log('TGC JWT: Token generation failed - ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Get cached token for user
     * 
     * @param int $wp_user_id WordPress user ID
     * @return string|false Cached token or false if not found/expired
     */
    public static function get_cached_token($wp_user_id) {
        $cache_key = 'tgc_jwt_' . $wp_user_id;
        $cached = get_transient($cache_key);
        
        if ($cached) {
            // Verify token is still valid
            try {
                $decoded = self::decode_token($cached);
                if ($decoded && isset($decoded->exp) && $decoded->exp > time()) {
                    return $cached;
                }
            } catch (Exception $e) {
                // Token invalid, remove from cache
                delete_transient($cache_key);
            }
        }
        
        return false;
    }
    
    /**
     * Cache token for reuse
     * 
     * @param int $wp_user_id WordPress user ID
     * @param string $token JWT token
     * @param int $expiry Expiry time in seconds
     */
    private static function cache_token($wp_user_id, $token, $expiry) {
        $cache_key = 'tgc_jwt_' . $wp_user_id;
        set_transient($cache_key, $token, $expiry - 60); // Expire 1 minute before actual expiry
    }
    
    /**
     * Decode and validate JWT token
     * 
     * @param string $token JWT token
     * @return object|false Decoded payload or false on failure
     */
    public static function decode_token($token) {
        $jwt_secret = self::get_jwt_secret();
        if (!$jwt_secret) {
            return false;
        }
        
        try {
            $decoded = JWT::decode($token, new Key($jwt_secret, self::ALGORITHM));
            
            // Validate issuer
            if (!isset($decoded->iss) || $decoded->iss !== get_site_url()) {
                return false;
            }
            
            return $decoded;
        } catch (Exception $e) {
            if (get_option('tgc_debug_mode', 'no') === 'yes') {
                error_log('TGC JWT: Token decode failed - ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Generate a system token for server-to-server communication
     * 
     * @param array $claims Token claims
     * @return string|false JWT token or false on failure
     */
    public static function generate_system_token($claims = array()) {
        $jwt_secret = self::get_jwt_secret();
        if (!$jwt_secret) {
            return false;
        }
        
        $issued_at = time();
        $expiration = $issued_at + 300; // 5 minutes for system tokens
        
        $payload = array(
            'iss' => get_site_url(),
            'iat' => $issued_at,
            'exp' => $expiration,
            'type' => 'system',
            'site_id' => get_current_blog_id()
        );
        
        // Merge additional claims
        if (!empty($claims) && is_array($claims)) {
            $payload = array_merge($payload, $claims);
        }
        
        try {
            return JWT::encode($payload, $jwt_secret, self::ALGORITHM);
        } catch (Exception $e) {
            if (get_option('tgc_debug_mode', 'no') === 'yes') {
                error_log('TGC JWT: System token generation failed - ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Get JWT secret
     * 
     * @return string|false JWT secret or false if not configured
     */
    private static function get_jwt_secret() {
        // Check if defined in wp-config.php
        if (defined('TGC_JWT_SECRET') && TGC_JWT_SECRET) {
            return TGC_JWT_SECRET;
        }
        
        // Fall back to database option
        $secret = get_option('tgc_jwt_secret', '');
        if (!empty($secret)) {
            return $secret;
        }
        
        // Generate and store a new secret if none exists
        $new_secret = self::generate_jwt_secret();
        update_option('tgc_jwt_secret', $new_secret);
        
        return $new_secret;
    }
    
    /**
     * Generate a secure JWT secret
     * 
     * @return string Generated secret
     */
    private static function generate_jwt_secret() {
        return wp_generate_password(64, true, true);
    }
    
    /**
     * Refresh token if needed
     * 
     * @param int $wp_user_id WordPress user ID
     * @return string|false New or existing valid token
     */
    public static function get_or_refresh_token($wp_user_id) {
        // Check for cached token first
        $cached_token = self::get_cached_token($wp_user_id);
        if ($cached_token) {
            return $cached_token;
        }
        
        // Generate new token
        return self::generate_token($wp_user_id);
    }
    
    /**
     * Invalidate user token
     * 
     * @param int $wp_user_id WordPress user ID
     */
    public static function invalidate_token($wp_user_id) {
        $cache_key = 'tgc_jwt_' . $wp_user_id;
        delete_transient($cache_key);
    }
    
    /**
     * Invalidate all tokens (e.g., when JWT secret changes)
     */
    public static function invalidate_all_tokens() {
        global $wpdb;
        
        // Delete all JWT transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_tgc_jwt_%' 
             OR option_name LIKE '_transient_timeout_tgc_jwt_%'"
        );
    }
}