<?php
/**
 * Session Tracker Class
 * 
 * Handles session ID generation and management for anonymous and
 * authenticated users to track their interactions with the Taste Graph
 * 
 * @package TasteGraphConnector
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TGC_Session_Tracker class
 */
class TGC_Session_Tracker {
    
    /**
     * Cookie name for session ID
     */
    const COOKIE_NAME = 'tgc_session_id';
    
    /**
     * Cookie expiration time (30 days)
     */
    const COOKIE_EXPIRY = 2592000; // 30 days in seconds
    
    /**
     * Session ID prefix for anonymous users
     */
    const SESSION_PREFIX = 'anon_';
    
    /**
     * Maximum session ID length (database constraint)
     */
    const MAX_SESSION_LENGTH = 100;
    
    /**
     * Initialize session tracking
     */
    public static function init() {
        // Set up cookie handling
        add_action('init', array(__CLASS__, 'setup_session_cookie'));
        
        // Add JavaScript for session management
        add_action('wp_footer', array(__CLASS__, 'add_session_script'));
        
        // Handle fingerprinting for enhanced tracking
        add_action('wp_ajax_tgc_update_fingerprint', array(__CLASS__, 'update_fingerprint'));
        add_action('wp_ajax_nopriv_tgc_update_fingerprint', array(__CLASS__, 'update_fingerprint'));
    }
    
    /**
     * Generate a new session ID
     * Format: anon_[timestamp]_[random]
     * 
     * @return string Generated session ID
     */
    public static function generate_session_id() {
        $timestamp = round(microtime(true) * 1000); // Millisecond timestamp
        $random = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 9);
        
        $session_id = self::SESSION_PREFIX . $timestamp . '_' . $random;
        
        // Ensure it doesn't exceed max length
        if (strlen($session_id) > self::MAX_SESSION_LENGTH) {
            $session_id = substr($session_id, 0, self::MAX_SESSION_LENGTH);
        }
        
        return $session_id;
    }
    
    /**
     * Get current session ID
     * 
     * @return string|null Session ID or null if not set
     */
    public static function get_session_id() {
        // Check cookie first
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            $session_id = sanitize_text_field($_COOKIE[self::COOKIE_NAME]);
            
            // Validate session ID format
            if (self::validate_session_id($session_id)) {
                return $session_id;
            }
        }
        
        // Check if we have a stored session ID in user meta (for logged-in users)
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $stored_session = get_user_meta($user_id, 'tgc_session_id', true);
            
            if ($stored_session && self::validate_session_id($stored_session)) {
                return $stored_session;
            }
        }
        
        return null;
    }
    
    /**
     * Set up session cookie
     */
    public static function setup_session_cookie() {
        // Don't set cookies in admin or during AJAX requests
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        // Check if we already have a valid session
        if (self::get_session_id()) {
            return;
        }
        
        // Generate new session ID
        $session_id = self::generate_session_id();
        
        // Set cookie
        setcookie(
            self::COOKIE_NAME,
            $session_id,
            time() + self::COOKIE_EXPIRY,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true // HttpOnly
        );
        
        // Store in $_COOKIE for immediate access
        $_COOKIE[self::COOKIE_NAME] = $session_id;
        
        // If user is logged in, also store in user meta
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'tgc_session_id', $session_id);
        }
    }
    
    /**
     * Validate session ID format
     * 
     * @param string $session_id Session ID to validate
     * @return bool True if valid, false otherwise
     */
    public static function validate_session_id($session_id) {
        // Check if empty
        if (empty($session_id)) {
            return false;
        }
        
        // Check length
        if (strlen($session_id) > self::MAX_SESSION_LENGTH) {
            return false;
        }
        
        // Check format: anon_[timestamp]_[random]
        if (!preg_match('/^anon_\d{13}_[a-z0-9]{9}$/', $session_id)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Add session management script to footer
     */
    public static function add_session_script() {
        // Only add if tracking is enabled
        if (get_option('tgc_tracking_enabled', 'yes') !== 'yes') {
            return;
        }
        
        $session_id = self::get_session_id();
        ?>
        <script type="text/javascript">
        (function() {
            // Session ID management
            var TGC_Session = {
                cookieName: '<?php echo esc_js(self::COOKIE_NAME); ?>',
                sessionId: '<?php echo esc_js($session_id ? $session_id : ''); ?>',
                
                // Generate session ID (matches PHP format)
                generateSessionId: function() {
                    var timestamp = Date.now();
                    var random;
                    
                    // Use crypto.getRandomValues if available for better security
                    if (window.crypto && window.crypto.getRandomValues) {
                        var array = new Uint8Array(5);
                        window.crypto.getRandomValues(array);
                        random = Array.from(array, function(byte) {
                            return ('0' + byte.toString(36)).slice(-2);
                        }).join('');
                    } else {
                        // Fallback to Math.random for older browsers
                        random = Math.random().toString(36).substr(2, 9);
                    }
                    
                    return 'anon_' + timestamp + '_' + random;
                },
                
                // Get session ID from localStorage or generate new
                getSessionId: function() {
                    if (this.sessionId) {
                        return this.sessionId;
                    }
                    
                    // Check localStorage
                    var stored = localStorage.getItem('tgc_session_id');
                    if (stored && this.validateSessionId(stored)) {
                        this.sessionId = stored;
                        return stored;
                    }
                    
                    // Generate new
                    this.sessionId = this.generateSessionId();
                    localStorage.setItem('tgc_session_id', this.sessionId);
                    
                    // Sync with server
                    this.syncSessionId();
                    
                    return this.sessionId;
                },
                
                // Validate session ID format
                validateSessionId: function(sessionId) {
                    return /^anon_\d{13}_[a-z0-9]{9}$/.test(sessionId);
                },
                
                // Sync session ID with server via AJAX
                syncSessionId: function() {
                    if (typeof jQuery !== 'undefined' && typeof tgc_ajax !== 'undefined') {
                        jQuery.post(tgc_ajax.ajax_url, {
                            action: 'tgc_sync_session',
                            session_id: this.sessionId,
                            nonce: tgc_ajax.nonce
                        });
                    }
                },
                
                // Initialize
                init: function() {
                    // Ensure we have a session ID
                    this.getSessionId();
                    
                    // Store in window for other scripts
                    window.TGC_SESSION_ID = this.sessionId;
                }
            };
            
            // Initialize on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    TGC_Session.init();
                });
            } else {
                TGC_Session.init();
            }
            
            // Expose globally
            window.TGC_Session = TGC_Session;
        })();
        </script>
        <?php
    }
    
    /**
     * Update fingerprint hash for session
     * AJAX handler
     */
    public static function update_fingerprint() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tgc_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        $fingerprint = isset($_POST['fingerprint']) ? sanitize_text_field($_POST['fingerprint']) : '';
        
        if (!self::validate_session_id($session_id)) {
            wp_send_json_error('Invalid session ID');
        }
        
        // Hash the fingerprint for privacy
        $fingerprint_hash = '';
        if (!empty($fingerprint)) {
            $fingerprint_hash = hash('sha256', $fingerprint . wp_salt('nonce'));
        }
        
        // Store in transient (temporary storage)
        $transient_key = self::get_transient_key($session_id);
        set_transient($transient_key, $fingerprint_hash, DAY_IN_SECONDS);
        
        wp_send_json_success(array(
            'session_id' => $session_id,
            'fingerprint_stored' => !empty($fingerprint_hash)
        ));
    }
    
    /**
     * Get fingerprint hash for session
     * 
     * @param string $session_id Session ID
     * @return string|null Fingerprint hash or null if not found
     */
    public static function get_fingerprint_hash($session_id) {
        $transient_key = self::get_transient_key($session_id);
        return get_transient($transient_key) ?: null;
    }
    
    /**
     * Link session to user on login
     * 
     * @param int $user_id WordPress user ID
     * @param string $session_id Session ID to link
     * @return bool Success status
     */
    public static function link_session_to_user($user_id, $session_id) {
        // Validate inputs
        if (!$user_id || !self::validate_session_id($session_id)) {
            return false;
        }
        
        // Store session ID in user meta
        update_user_meta($user_id, 'tgc_session_id', $session_id);
        update_user_meta($user_id, 'tgc_session_linked_at', current_time('mysql'));
        
        // Log for debugging
        if (get_option('tgc_debug_mode', 'no') === 'yes') {
            error_log(sprintf(
                'TGC Session: Linked session %s to user %d',
                $session_id,
                $user_id
            ));
        }
        
        return true;
    }
    
    /**
     * Get all sessions for a user
     * 
     * @param int $user_id WordPress user ID
     * @return array Array of session IDs
     */
    public static function get_user_sessions($user_id) {
        if (!$user_id) {
            return array();
        }
        
        $sessions = get_user_meta($user_id, 'tgc_all_sessions', true);
        return is_array($sessions) ? $sessions : array();
    }
    
    /**
     * Clear session data
     * 
     * @param string $session_id Session ID to clear
     */
    public static function clear_session($session_id) {
        if (!self::validate_session_id($session_id)) {
            return;
        }
        
        // Clear fingerprint
        $transient_key = self::get_transient_key($session_id);
        delete_transient($transient_key);
        
        // Clear from user meta if linked
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $stored_session = get_user_meta($user_id, 'tgc_session_id', true);
            
            if ($stored_session === $session_id) {
                delete_user_meta($user_id, 'tgc_session_id');
            }
        }
    }
    
    /**
     * Generate a collision-resistant transient key from session ID
     * 
     * Uses MD5 hash of the full session ID to create a fixed-length,
     * collision-resistant key while keeping it within WordPress transient
     * key length limits.
     * 
     * @param string $session_id The session ID
     * @return string The transient key
     */
    private static function get_transient_key($session_id) {
        // Use MD5 hash for a fixed 32-character representation
        // This provides better distribution and collision resistance
        $hash = md5($session_id);
        return 'tgc_fp_' . $hash;
    }
}