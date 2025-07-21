<?php
/**
 * Enterprise Rate Limiter for Master Critic Plugin
 *
 * Provides IP-based rate limiting with Redis support
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

class ZipPicks_Master_Critic_Rate_Limiter {
    
    /**
     * Rate limit configurations
     */
    const LIMITS = array(
        'ai_generation' => array(
            'requests' => 10,
            'window' => 3600,     // 1 hour
            'block_duration' => 3600
        ),
        'api_call' => array(
            'requests' => 100,
            'window' => 3600,     // 1 hour
            'block_duration' => 1800
        ),
        'admin_action' => array(
            'requests' => 50,
            'window' => 300,      // 5 minutes
            'block_duration' => 300
        ),
        'public_view' => array(
            'requests' => 1000,
            'window' => 3600,     // 1 hour
            'block_duration' => 600
        )
    );
    
    /**
     * Check if request is allowed
     *
     * @param string $action_type
     * @param string $identifier Optional identifier (defaults to IP)
     * @return array
     */
    public static function check_limit($action_type, $identifier = null) {
        if (!isset(self::LIMITS[$action_type])) {
            return array(
                'allowed' => true,
                'remaining' => null,
                'reset_at' => null
            );
        }
        
        $config = self::LIMITS[$action_type];
        $identifier = $identifier ?: self::get_client_ip();
        
        // Check if using Redis
        if (self::is_redis_available()) {
            return self::check_redis_limit($action_type, $identifier, $config);
        } else {
            return self::check_transient_limit($action_type, $identifier, $config);
        }
    }
    
    /**
     * Check rate limit using WordPress transients
     *
     * @param string $action_type
     * @param string $identifier
     * @param array $config
     * @return array
     */
    private static function check_transient_limit($action_type, $identifier, $config) {
        $key = 'zippicks_rate_' . md5($action_type . '_' . $identifier);
        $block_key = $key . '_blocked';
        
        // Check if blocked
        if (get_transient($block_key)) {
            $block_expires = get_option('_transient_timeout_' . $block_key);
            
            // Log rate limit violation
            ZipPicks_Master_Critic_Audit_Logger::log_security_event(
                ZipPicks_Master_Critic_Audit_Logger::EVENT_RATE_LIMIT,
                'Rate limit exceeded - request blocked',
                array(
                    'action' => $action_type,
                    'identifier' => $identifier,
                    'blocked_until' => date('Y-m-d H:i:s', $block_expires)
                )
            );
            
            return array(
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => $block_expires,
                'retry_after' => $block_expires - time()
            );
        }
        
        // Get current count
        $current = get_transient($key);
        
        if ($current === false) {
            // First request in window
            set_transient($key, 1, $config['window']);
            
            return array(
                'allowed' => true,
                'remaining' => $config['requests'] - 1,
                'reset_at' => time() + $config['window']
            );
        }
        
        if ($current >= $config['requests']) {
            // Limit exceeded, block the identifier
            set_transient($block_key, true, $config['block_duration']);
            
            // Log rate limit violation
            ZipPicks_Master_Critic_Audit_Logger::log_security_event(
                ZipPicks_Master_Critic_Audit_Logger::EVENT_RATE_LIMIT,
                'Rate limit exceeded - blocking identifier',
                array(
                    'action' => $action_type,
                    'identifier' => $identifier,
                    'requests' => $current,
                    'limit' => $config['requests']
                )
            );
            
            return array(
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => time() + $config['block_duration'],
                'retry_after' => $config['block_duration']
            );
        }
        
        // Increment counter
        set_transient($key, $current + 1, $config['window']);
        
        return array(
            'allowed' => true,
            'remaining' => $config['requests'] - ($current + 1),
            'reset_at' => get_option('_transient_timeout_' . $key)
        );
    }
    
    /**
     * Check rate limit using Redis
     *
     * @param string $action_type
     * @param string $identifier
     * @param array $config
     * @return array
     */
    private static function check_redis_limit($action_type, $identifier, $config) {
        $redis = self::get_redis_client();
        
        if (!$redis) {
            // Fallback to transient
            return self::check_transient_limit($action_type, $identifier, $config);
        }
        
        $key = 'zippicks:rate:' . $action_type . ':' . $identifier;
        $block_key = $key . ':blocked';
        
        // Check if blocked
        if ($redis->exists($block_key)) {
            $ttl = $redis->ttl($block_key);
            
            return array(
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => time() + $ttl,
                'retry_after' => $ttl
            );
        }
        
        // Use Redis INCR with expiry
        $current = $redis->incr($key);
        
        if ($current === 1) {
            $redis->expire($key, $config['window']);
        }
        
        if ($current > $config['requests']) {
            // Block the identifier
            $redis->setex($block_key, $config['block_duration'], 1);
            
            return array(
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => time() + $config['block_duration'],
                'retry_after' => $config['block_duration']
            );
        }
        
        $ttl = $redis->ttl($key);
        
        return array(
            'allowed' => true,
            'remaining' => $config['requests'] - $current,
            'reset_at' => time() + $ttl
        );
    }
    
    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Check if Redis is available
     *
     * @return bool
     */
    private static function is_redis_available() {
        // Check if Redis extension is loaded
        if (!extension_loaded('redis')) {
            return false;
        }
        
        // Check if Redis is configured in wp-config
        if (!defined('WP_REDIS_HOST')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get Redis client
     *
     * @return Redis|false
     */
    private static function get_redis_client() {
        static $redis = null;
        
        if ($redis !== null) {
            return $redis;
        }
        
        if (!self::is_redis_available()) {
            return false;
        }
        
        try {
            $redis = new Redis();
            $host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1';
            $port = defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379;
            
            $redis->connect($host, $port, 1); // 1 second timeout
            
            if (defined('WP_REDIS_PASSWORD')) {
                $redis->auth(WP_REDIS_PASSWORD);
            }
            
            if (defined('WP_REDIS_DATABASE')) {
                $redis->select(WP_REDIS_DATABASE);
            }
            
            return $redis;
            
        } catch (Exception $e) {
            error_log('Redis connection failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear rate limits for an identifier
     *
     * @param string $identifier
     * @param string $action_type Optional specific action
     */
    public static function clear_limits($identifier, $action_type = null) {
        if (self::is_redis_available()) {
            $redis = self::get_redis_client();
            if ($redis) {
                if ($action_type) {
                    $redis->del('zippicks:rate:' . $action_type . ':' . $identifier);
                    $redis->del('zippicks:rate:' . $action_type . ':' . $identifier . ':blocked');
                } else {
                    // Clear all action types
                    foreach (array_keys(self::LIMITS) as $action) {
                        $redis->del('zippicks:rate:' . $action . ':' . $identifier);
                        $redis->del('zippicks:rate:' . $action . ':' . $identifier . ':blocked');
                    }
                }
            }
        } else {
            // Clear transients
            if ($action_type) {
                $key = 'zippicks_rate_' . md5($action_type . '_' . $identifier);
                delete_transient($key);
                delete_transient($key . '_blocked');
            } else {
                // Clear all action types
                foreach (array_keys(self::LIMITS) as $action) {
                    $key = 'zippicks_rate_' . md5($action . '_' . $identifier);
                    delete_transient($key);
                    delete_transient($key . '_blocked');
                }
            }
        }
    }
    
    /**
     * Apply rate limit headers to response
     *
     * @param array $rate_info
     */
    public static function apply_headers($rate_info) {
        if (!headers_sent()) {
            header('X-RateLimit-Limit: ' . ($rate_info['remaining'] + 1));
            header('X-RateLimit-Remaining: ' . $rate_info['remaining']);
            header('X-RateLimit-Reset: ' . $rate_info['reset_at']);
            
            if (!$rate_info['allowed'] && isset($rate_info['retry_after'])) {
                header('Retry-After: ' . $rate_info['retry_after']);
                http_response_code(429); // Too Many Requests
            }
        }
    }
    
    /**
     * Format rate limit error response
     *
     * @param array $rate_info
     * @return array
     */
    public static function format_error_response($rate_info) {
        return array(
            'error' => 'rate_limit_exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => isset($rate_info['retry_after']) ? $rate_info['retry_after'] : null,
            'reset_at' => isset($rate_info['reset_at']) ? date('Y-m-d H:i:s', $rate_info['reset_at']) : null
        );
    }
}