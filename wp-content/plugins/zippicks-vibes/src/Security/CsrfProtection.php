<?php
/**
 * CSRF Protection Service
 * 
 * Implements enhanced CSRF protection with double-submit cookies, 
 * origin validation, context-aware tokens, and multiple input support
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Security;

/**
 * Class CsrfProtection
 * 
 * Provides comprehensive CSRF protection with modern features
 */
class CsrfProtection {
    
    /**
     * Default token lifetime (4 hours)
     */
    private const DEFAULT_TOKEN_LIFETIME = 14400;
    
    /**
     * Cookie name for CSRF token
     */
    private const COOKIE_NAME = 'zippicks_csrf_token';
    
    /**
     * Session meta key
     */
    private const SESSION_KEY = 'zippicks_csrf_tokens';
    
    /**
     * Maximum tokens per session
     */
    private const MAX_TOKENS = 10;
    
    /**
     * Input sources for token detection
     */
    private const TOKEN_SOURCES = [
        'header' => 'HTTP_X_CSRF_TOKEN',
        'header_alt' => 'HTTP_X_ZIPPICKS_CSRF_TOKEN',
        'post' => 'zippicks_csrf_token',
        'post_alt' => '_csrf_token',
        'get' => 'csrf_token',
        'json' => 'csrf_token'
    ];
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Cache instance
     */
    private $cache;
    
    /**
     * Configurable token TTL
     */
    private int $tokenLifetime;
    
    /**
     * Whether to enforce IP binding
     */
    private bool $enforceIpBinding;
    
    /**
     * Whether to enforce one-time use
     */
    private bool $enforceOneTimeUse;
    
    /**
     * Constructor
     * 
     * @param $logger Logger instance
     * @param $cache Cache instance
     * @param array $config Configuration options
     */
    public function __construct($logger = null, $cache = null, array $config = []) {
        $this->logger = $logger;
        $this->cache = $cache;
        
        // Apply configuration
        $this->tokenLifetime = $config['token_lifetime'] ?? self::DEFAULT_TOKEN_LIFETIME;
        $this->enforceIpBinding = $config['enforce_ip_binding'] ?? false;
        $this->enforceOneTimeUse = $config['enforce_one_time_use'] ?? true;
    }
    
    /**
     * Generate CSRF token with context awareness
     * 
     * @param string $context Specific context/action name
     * @param array $options Additional options
     * @return string Generated token
     */
    public function generateToken(string $context = 'default', array $options = []): string {
        $user_id = get_current_user_id();
        $session_id = $this->getSessionId();
        $timestamp = time();
        
        // Override TTL if provided
        $ttl = $options['ttl'] ?? $this->tokenLifetime;
        
        // Generate token data with context
        $token_data = [
            'context' => $context,
            'user_id' => $user_id,
            'session_id' => $session_id,
            'timestamp' => $timestamp,
            'expires' => $timestamp + $ttl,
            'ip' => $this->getClientIP(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'random' => wp_generate_password(16, false),
            'one_time' => $options['one_time'] ?? $this->enforceOneTimeUse
        ];
        
        // Add custom data if provided
        if (!empty($options['custom_data'])) {
            $token_data['custom'] = $options['custom_data'];
        }
        
        // Create token
        $token = $this->createToken($token_data);
        
        // Store token
        $this->storeToken($token, $token_data);
        
        // Set double-submit cookie if requested
        if ($options['set_cookie'] ?? true) {
            $this->setTokenCookie($token, $ttl);
        }
        
        // Log token generation
        $this->logTokenEvent('generated', $context, [
            'user_id' => $user_id,
            'ttl' => $ttl
        ]);
        
        return $token;
    }
    
    /**
     * Validate CSRF token with context checking
     * 
     * @param string|null $token Token to validate (null to auto-detect)
     * @param string $context Expected context
     * @param array $options Validation options
     * @return bool Whether token is valid
     */
    public function validateToken(?string $token = null, string $context = 'default', array $options = []): bool {
        try {
            // Auto-detect token if not provided
            if ($token === null) {
                $token = $this->detectToken();
            }
            
            // Empty token check
            if (empty($token)) {
                $this->logSecurityEvent('empty_csrf_token', $context);
                return false;
            }
            
            // Validate token format
            if (!$this->isValidTokenFormat($token)) {
                $this->logSecurityEvent('invalid_csrf_token_format', $context);
                return false;
            }
            
            // Verify double-submit cookie if enabled
            if ($options['verify_cookie'] ?? true) {
                if (!$this->verifyDoubleSubmit($token)) {
                    $this->logSecurityEvent('csrf_double_submit_failed', $context);
                    return false;
                }
            }
            
            // Get stored token data
            $token_data = $this->getStoredToken($token);
            if (!$token_data) {
                $this->logSecurityEvent('csrf_token_not_found', $context);
                return false;
            }
            
            // Check if token was already used (one-time tokens)
            if ($token_data['one_time'] ?? false) {
                if ($this->isTokenUsed($token)) {
                    $this->logSecurityEvent('csrf_token_reused', $context);
                    return false;
                }
            }
            
            // Validate token expiry with custom TTL support
            $custom_ttl = $options['ttl'] ?? null;
            if (!$this->validateTokenExpiry($token_data, $custom_ttl)) {
                $this->logSecurityEvent('csrf_token_expired', $context);
                $this->removeToken($token);
                return false;
            }
            
            // Validate context
            if (!$this->validateContext($token_data, $context, $options)) {
                $this->logSecurityEvent('csrf_context_mismatch', $context, [
                    'expected' => $context,
                    'actual' => $token_data['context'] ?? 'unknown'
                ]);
                return false;
            }
            
            // Validate session binding
            if (!$this->validateSessionBinding($token_data)) {
                $this->logSecurityEvent('csrf_session_mismatch', $context);
                return false;
            }
            
            // Validate IP binding if enforced
            if ($this->enforceIpBinding || ($options['check_ip'] ?? false)) {
                if (!$this->validateIPBinding($token_data)) {
                    $this->logSecurityEvent('csrf_ip_mismatch', $context);
                    return false;
                }
            }
            
            // Validate user agent if requested
            if ($options['check_user_agent'] ?? false) {
                if (!$this->validateUserAgent($token_data)) {
                    $this->logSecurityEvent('csrf_user_agent_mismatch', $context);
                    return false;
                }
            }
            
            // Token is valid, mark as used if one-time
            if ($token_data['one_time'] ?? false) {
                $this->markTokenUsed($token);
            }
            
            // Log successful validation
            $this->logTokenEvent('validated', $context);
            
            return true;
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('CSRF validation error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'context' => $context
                ]);
            }
            return false;
        }
    }
    
    /**
     * Detect token from multiple input sources
     * 
     * @return string|null Detected token or null
     */
    private function detectToken(): ?string {
        // 1. Check headers (highest priority)
        foreach (['header', 'header_alt'] as $source) {
            if (!empty($_SERVER[self::TOKEN_SOURCES[$source]])) {
                return sanitize_text_field($_SERVER[self::TOKEN_SOURCES[$source]]);
            }
        }
        
        // 2. Check POST data
        foreach (['post', 'post_alt'] as $source) {
            if (!empty($_POST[self::TOKEN_SOURCES[$source]])) {
                return sanitize_text_field($_POST[self::TOKEN_SOURCES[$source]]);
            }
        }
        
        // 3. Check GET parameters
        if (!empty($_GET[self::TOKEN_SOURCES['get']])) {
            return sanitize_text_field($_GET[self::TOKEN_SOURCES['get']]);
        }
        
        // 4. Check JSON payload
        $input = file_get_contents('php://input');
        if ($input) {
            $json = json_decode($input, true);
            if (is_array($json) && !empty($json[self::TOKEN_SOURCES['json']])) {
                return sanitize_text_field($json[self::TOKEN_SOURCES['json']]);
            }
        }
        
        return null;
    }
    
    /**
     * Get CSRF token field HTML
     * 
     * @param string $context Token context
     * @param array $attributes HTML attributes
     * @return string HTML input field
     */
    public function getTokenField(string $context = 'default', array $attributes = []): string {
        $token = $this->generateToken($context);
        
        $default_attributes = [
            'type' => 'hidden',
            'name' => self::TOKEN_SOURCES['post'],
            'value' => esc_attr($token),
            'data-context' => esc_attr($context)
        ];
        
        $attributes = array_merge($default_attributes, $attributes);
        
        $html = '<input';
        foreach ($attributes as $key => $value) {
            $html .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
        }
        $html .= ' />';
        
        // Add nonce field for WordPress compatibility
        if ($attributes['include_nonce'] ?? true) {
            $html .= wp_nonce_field('zippicks_csrf_' . $context, '_wpnonce', true, false);
        }
        
        return $html;
    }
    
    /**
     * Get CSRF token for AJAX/API usage
     * 
     * @param string $context Token context
     * @param array $options Token options
     * @return array Token data for client
     */
    public function getAjaxToken(string $context = 'default', array $options = []): array {
        $ttl = $options['ttl'] ?? $this->tokenLifetime;
        $token = $this->generateToken($context, array_merge($options, ['set_cookie' => false]));
        
        return [
            'token' => $token,
            'header' => 'X-CSRF-Token',
            'header_alt' => 'X-ZipPicks-CSRF-Token',
            'param' => self::TOKEN_SOURCES['post'],
            'context' => $context,
            'expires' => time() + $ttl,
            'ttl' => $ttl
        ];
    }
    
    /**
     * Refresh token (extend expiry)
     * 
     * @param string $token Token to refresh
     * @param int|null $new_ttl New TTL (null to use default)
     * @return string|false New token or false on failure
     */
    public function refreshToken(string $token, ?int $new_ttl = null): string|false {
        $token_data = $this->getStoredToken($token);
        if (!$token_data) {
            return false;
        }
        
        // Remove old token
        $this->removeToken($token);
        
        // Generate new token with same context
        $context = $token_data['context'] ?? 'default';
        $options = ['ttl' => $new_ttl ?? $this->tokenLifetime];
        
        return $this->generateToken($context, $options);
    }
    
    /**
     * Create token from data
     * 
     * @param array $data Token data
     * @return string Encoded token
     */
    private function createToken(array $data): string {
        $payload = base64_encode(json_encode($data));
        $signature = hash_hmac('sha256', $payload, $this->getTokenSecret());
        
        return $payload . '.' . $signature;
    }
    
    /**
     * Get token secret with context mixing
     * 
     * @return string Secret key
     */
    private function getTokenSecret(): string {
        $secret = wp_salt('secure_auth');
        
        // Mix in site-specific data
        $secret .= parse_url(home_url(), PHP_URL_HOST);
        $secret .= SECURE_AUTH_KEY;
        
        return hash('sha256', $secret);
    }
    
    /**
     * Validate token format
     * 
     * @param string $token
     * @return bool
     */
    private function isValidTokenFormat(string $token): bool {
        // Check basic structure
        if (!preg_match('/^[A-Za-z0-9+\/=]+\.[a-f0-9]{64}$/', $token)) {
            return false;
        }
        
        // Verify signature
        list($payload, $signature) = explode('.', $token, 2);
        $expected_signature = hash_hmac('sha256', $payload, $this->getTokenSecret());
        
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Store token with enhanced storage strategy
     * 
     * @param string $token
     * @param array $data
     */
    private function storeToken(string $token, array $data): void {
        $user_id = $data['user_id'];
        $ttl = $data['expires'] - $data['timestamp'];
        
        // Store in appropriate location based on user status
        if ($user_id > 0) {
            // Store in user meta for logged-in users
            $this->storeUserToken($user_id, $token, $data);
        } else {
            // Store in transient for guests
            $this->storeGuestToken($data['session_id'], $token, $data, $ttl);
        }
        
        // Also store in cache for fast access
        if ($this->cache) {
            $cache_key = $this->getTokenCacheKey($token);
            $this->cache->set($cache_key, $data, $ttl);
        }
    }
    
    /**
     * Store token for authenticated user
     * 
     * @param int $user_id
     * @param string $token
     * @param array $data
     */
    private function storeUserToken(int $user_id, string $token, array $data): void {
        $tokens = get_user_meta($user_id, self::SESSION_KEY, true) ?: [];
        
        // Clean expired tokens
        $tokens = $this->cleanExpiredTokens($tokens);
        
        // Limit number of tokens
        if (count($tokens) >= self::MAX_TOKENS) {
            array_shift($tokens);
        }
        
        $tokens[$token] = $data;
        update_user_meta($user_id, self::SESSION_KEY, $tokens);
    }
    
    /**
     * Store token for guest user
     * 
     * @param string $session_id
     * @param string $token
     * @param array $data
     * @param int $ttl
     */
    private function storeGuestToken(string $session_id, string $token, array $data, int $ttl): void {
        $key = 'zippicks_csrf_' . $session_id;
        $tokens = get_transient($key) ?: [];
        
        // Clean expired tokens
        $tokens = $this->cleanExpiredTokens($tokens);
        
        // Limit number of tokens
        if (count($tokens) >= self::MAX_TOKENS) {
            array_shift($tokens);
        }
        
        $tokens[$token] = $data;
        set_transient($key, $tokens, $ttl);
    }
    
    /**
     * Get stored token data with caching
     * 
     * @param string $token
     * @return array|null
     */
    private function getStoredToken(string $token): ?array {
        // Check cache first
        if ($this->cache) {
            $cache_key = $this->getTokenCacheKey($token);
            $data = $this->cache->get($cache_key);
            if ($data !== false) {
                return $data;
            }
        }
        
        // Extract data from token
        list($payload, $signature) = explode('.', $token, 2);
        $data = json_decode(base64_decode($payload), true);
        
        if (!$data) {
            return null;
        }
        
        $user_id = $data['user_id'] ?? 0;
        
        if ($user_id > 0) {
            // Check user meta
            $tokens = get_user_meta($user_id, self::SESSION_KEY, true) ?: [];
            $stored = $tokens[$token] ?? null;
        } else {
            // Check transient
            $session_id = $data['session_id'] ?? '';
            $key = 'zippicks_csrf_' . $session_id;
            $tokens = get_transient($key) ?: [];
            $stored = $tokens[$token] ?? null;
        }
        
        // Update cache if found
        if ($stored && $this->cache) {
            $ttl = ($stored['expires'] ?? time()) - time();
            if ($ttl > 0) {
                $cache_key = $this->getTokenCacheKey($token);
                $this->cache->set($cache_key, $stored, $ttl);
            }
        }
        
        return $stored;
    }
    
    /**
     * Remove token from all storage locations
     * 
     * @param string $token
     */
    private function removeToken(string $token): void {
        // Remove from cache
        if ($this->cache) {
            $cache_key = $this->getTokenCacheKey($token);
            $this->cache->delete($cache_key);
        }
        
        // Extract data from token
        list($payload, $signature) = explode('.', $token, 2);
        $data = json_decode(base64_decode($payload), true);
        
        if (!$data) {
            return;
        }
        
        $user_id = $data['user_id'] ?? 0;
        
        if ($user_id > 0) {
            // Remove from user meta
            $tokens = get_user_meta($user_id, self::SESSION_KEY, true) ?: [];
            unset($tokens[$token]);
            update_user_meta($user_id, self::SESSION_KEY, $tokens);
        } else {
            // Remove from transient
            $session_id = $data['session_id'] ?? '';
            $key = 'zippicks_csrf_' . $session_id;
            $tokens = get_transient($key) ?: [];
            unset($tokens[$token]);
            
            if (empty($tokens)) {
                delete_transient($key);
            } else {
                $ttl = max(array_column($tokens, 'expires')) - time();
                set_transient($key, $tokens, max($ttl, 60));
            }
        }
    }
    
    /**
     * Check if token was already used
     * 
     * @param string $token
     * @return bool
     */
    private function isTokenUsed(string $token): bool {
        if (!$this->cache) {
            return false;
        }
        
        $used_key = 'csrf_used_' . md5($token);
        return (bool) $this->cache->get($used_key);
    }
    
    /**
     * Mark token as used
     * 
     * @param string $token
     */
    private function markTokenUsed(string $token): void {
        if (!$this->cache) {
            return;
        }
        
        $used_key = 'csrf_used_' . md5($token);
        $ttl = $this->tokenLifetime; // Keep for full token lifetime
        $this->cache->set($used_key, true, $ttl);
    }
    
    /**
     * Set token cookie for double-submit
     * 
     * @param string $token
     * @param int $ttl
     */
    private function setTokenCookie(string $token, int $ttl): void {
        // Only set first part of token in cookie
        list($payload, $signature) = explode('.', $token, 2);
        $cookie_value = substr($signature, 0, 32);
        
        // Set cookie with security flags
        setcookie(
            self::COOKIE_NAME,
            $cookie_value,
            [
                'expires' => time() + $ttl,
                'path' => COOKIEPATH ?: '/',
                'domain' => COOKIE_DOMAIN ?: '',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }
    
    /**
     * Verify double-submit cookie
     * 
     * @param string $token
     * @return bool
     */
    private function verifyDoubleSubmit(string $token): bool {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return false;
        }
        
        $cookie_value = $_COOKIE[self::COOKIE_NAME];
        list($payload, $signature) = explode('.', $token, 2);
        $expected_cookie = substr($signature, 0, 32);
        
        return hash_equals($expected_cookie, $cookie_value);
    }
    
    /**
     * Validate token expiry with custom TTL support
     * 
     * @param array $token_data
     * @param int|null $custom_ttl Override TTL
     * @return bool
     */
    private function validateTokenExpiry(array $token_data, ?int $custom_ttl = null): bool {
        $current_time = time();
        
        // Use explicit expiry if available
        if (isset($token_data['expires'])) {
            return $current_time <= $token_data['expires'];
        }
        
        // Fall back to timestamp + TTL
        $timestamp = $token_data['timestamp'] ?? 0;
        $ttl = $custom_ttl ?? $this->tokenLifetime;
        
        return ($current_time - $timestamp) <= $ttl;
    }
    
    /**
     * Validate context with flexibility
     * 
     * @param array $token_data
     * @param string $expected_context
     * @param array $options
     * @return bool
     */
    private function validateContext(array $token_data, string $expected_context, array $options): bool {
        $token_context = $token_data['context'] ?? 'default';
        
        // Exact match
        if ($token_context === $expected_context) {
            return true;
        }
        
        // Allow wildcard matching
        if ($options['allow_wildcard'] ?? false) {
            // Check if token context is more specific than expected
            if (str_starts_with($token_context, $expected_context . ':')) {
                return true;
            }
            
            // Check if expected context is wildcard
            if ($expected_context === '*' || $expected_context === 'any') {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate session binding
     * 
     * @param array $token_data
     * @return bool
     */
    private function validateSessionBinding(array $token_data): bool {
        $token_session = $token_data['session_id'] ?? '';
        $current_session = $this->getSessionId();
        
        return hash_equals($token_session, $current_session);
    }
    
    /**
     * Validate IP binding
     * 
     * @param array $token_data
     * @return bool
     */
    private function validateIPBinding(array $token_data): bool {
        // Check if IP validation is disabled globally
        if (!apply_filters('zippicks_csrf_check_ip', $this->enforceIpBinding)) {
            return true;
        }
        
        $token_ip = $token_data['ip'] ?? '';
        $current_ip = $this->getClientIP();
        
        // Allow if both are empty (privacy mode)
        if (empty($token_ip) && $current_ip === '0.0.0.0') {
            return true;
        }
        
        return $token_ip === $current_ip;
    }
    
    /**
     * Validate user agent
     * 
     * @param array $token_data
     * @return bool
     */
    private function validateUserAgent(array $token_data): bool {
        $token_ua = $token_data['user_agent'] ?? '';
        $current_ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        
        // Fuzzy matching - check if starts with same string
        if (strlen($token_ua) >= 50 && strlen($current_ua) >= 50) {
            return str_starts_with($current_ua, substr($token_ua, 0, 50));
        }
        
        return $token_ua === $current_ua;
    }
    
    /**
     * Get session ID with initialization
     * 
     * @return string
     */
    private function getSessionId(): string {
        // Check if session is already started
        if (session_status() === PHP_SESSION_NONE) {
            // Use WordPress session handling if available
            if (function_exists('wp_session_start')) {
                wp_session_start();
            } else {
                session_start([
                    'cookie_secure' => is_ssl(),
                    'cookie_httponly' => true,
                    'cookie_samesite' => 'Strict'
                ]);
            }
        }
        
        return session_id() ?: wp_generate_password(32, false);
    }
    
    /**
     * Get client IP
     * 
     * @return string
     */
    private function getClientIP(): string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Get cache key for token
     * 
     * @param string $token
     * @return string
     */
    private function getTokenCacheKey(string $token): string {
        return 'csrf_token_' . md5($token);
    }
    
    /**
     * Clean expired tokens from array
     * 
     * @param array $tokens
     * @return array
     */
    private function cleanExpiredTokens(array $tokens): array {
        $current_time = time();
        
        return array_filter($tokens, function($data) use ($current_time) {
            $expires = $data['expires'] ?? ($data['timestamp'] + $this->tokenLifetime);
            return $expires > $current_time;
        });
    }
    
    /**
     * Log security event
     * 
     * @param string $event_type
     * @param string $context
     * @param array $extra
     */
    private function logSecurityEvent(string $event_type, string $context, array $extra = []): void {
        if ($this->logger) {
            $this->logger->warning('CSRF: ' . $event_type, array_merge([
                'ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'context' => $context,
                'user_id' => get_current_user_id()
            ], $extra));
        }
    }
    
    /**
     * Log token event
     * 
     * @param string $action
     * @param string $context
     * @param array $extra
     */
    private function logTokenEvent(string $action, string $context, array $extra = []): void {
        if ($this->logger) {
            $this->logger->info('CSRF token ' . $action, array_merge([
                'context' => $context,
                'user_id' => get_current_user_id(),
                'ip' => $this->getClientIP()
            ], $extra));
        }
    }
    
    /**
     * Clean expired tokens (maintenance task)
     * 
     * @return int Number of tokens cleaned
     */
    public function cleanExpiredTokens(): int {
        $cleaned = 0;
        
        // Clean user meta tokens
        $users = get_users(['fields' => 'ID', 'number' => 100]);
        foreach ($users as $user_id) {
            $tokens = get_user_meta($user_id, self::SESSION_KEY, true) ?: [];
            $clean_tokens = $this->cleanExpiredTokens($tokens);
            
            if (count($clean_tokens) !== count($tokens)) {
                $cleaned += count($tokens) - count($clean_tokens);
                
                if (empty($clean_tokens)) {
                    delete_user_meta($user_id, self::SESSION_KEY);
                } else {
                    update_user_meta($user_id, self::SESSION_KEY, $clean_tokens);
                }
            }
        }
        
        // Clean transient tokens (limited scope due to performance)
        global $wpdb;
        $transients = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_zippicks_csrf_%' 
             LIMIT 100"
        );
        
        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient);
            $tokens = get_transient($key) ?: [];
            
            if (empty($tokens)) {
                delete_transient($key);
                $cleaned++;
                continue;
            }
            
            $clean_tokens = $this->cleanExpiredTokens($tokens);
            if (count($clean_tokens) !== count($tokens)) {
                $cleaned += count($tokens) - count($clean_tokens);
                
                if (empty($clean_tokens)) {
                    delete_transient($key);
                } else {
                    $ttl = max(array_column($clean_tokens, 'expires')) - time();
                    set_transient($key, $clean_tokens, max($ttl, 60));
                }
            }
        }
        
        if ($this->logger && $cleaned > 0) {
            $this->logger->info('Cleaned expired CSRF tokens', ['count' => $cleaned]);
        }
        
        return $cleaned;
    }
    
    /**
     * Get token statistics for monitoring
     * 
     * @return array Statistics
     */
    public function getTokenStats(): array {
        $stats = [
            'total_tokens' => 0,
            'expired_tokens' => 0,
            'active_tokens' => 0,
            'user_tokens' => 0,
            'guest_tokens' => 0
        ];
        
        $current_time = time();
        
        // Count user tokens
        $users = get_users(['fields' => 'ID', 'number' => 1000]);
        foreach ($users as $user_id) {
            $tokens = get_user_meta($user_id, self::SESSION_KEY, true) ?: [];
            foreach ($tokens as $token => $data) {
                $stats['total_tokens']++;
                $stats['user_tokens']++;
                
                $expires = $data['expires'] ?? ($data['timestamp'] + $this->tokenLifetime);
                if ($expires > $current_time) {
                    $stats['active_tokens']++;
                } else {
                    $stats['expired_tokens']++;
                }
            }
        }
        
        // Estimate guest tokens (sampling)
        global $wpdb;
        $transient_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_zippicks_csrf_%'"
        );
        
        $stats['guest_tokens'] = (int) $transient_count;
        $stats['total_tokens'] += $stats['guest_tokens'];
        
        return $stats;
    }
}