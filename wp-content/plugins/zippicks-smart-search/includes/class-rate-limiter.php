<?php
/**
 * Rate Limiter for ZipPicks Smart Search
 * 
 * Implements fixed window rate limiting with Redis/Transient fallback
 * TODO: Upgrade to sliding window implementation using Redis sorted sets
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Rate_Limiter {
    
    /**
     * Cache prefix for rate limit keys
     * @var string
     */
    const CACHE_PREFIX = 'zippicks_rate_limit_';
    
    /**
     * Default rate limits by endpoint type
     * @var array
     */
    private static $default_limits = [
        'search' => [
            'requests' => 30,
            'window' => 60, // 30 requests per minute
        ],
        'autocomplete' => [
            'requests' => 60,
            'window' => 60, // 60 requests per minute (higher for typing)
        ],
        'notify' => [
            'requests' => 5,
            'window' => 300, // 5 notifications per 5 minutes
        ],
        'track' => [
            'requests' => 100,
            'window' => 60, // 100 tracking events per minute
        ],
        'global' => [
            'requests' => 200,
            'window' => 60, // 200 total requests per minute per IP
        ]
    ];
    
    /**
     * Check if request is rate limited
     * 
     * @param string $endpoint Endpoint identifier
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @param array $custom_limits Optional custom limits
     * @return bool|array True if allowed, array with error details if blocked
     */
    public static function check($endpoint, $identifier = null, $custom_limits = null) {
        // Get identifier
        if ($identifier === null) {
            $identifier = self::get_client_identifier();
        }
        
        // Get limits
        $limits = $custom_limits ?: self::get_limits($endpoint);
        
        // Check global rate limit first
        if ($endpoint !== 'global') {
            $global_check = self::check('global', $identifier);
            if (is_array($global_check)) {
                return $global_check;
            }
        }
        
        // Generate cache key
        $cache_key = self::get_cache_key($endpoint, $identifier);
        
        // Get current request count
        $requests = self::get_request_count($cache_key);
        
        // Check if limit exceeded
        if ($requests >= $limits['requests']) {
            return [
                'error' => 'rate_limit_exceeded',
                'message' => sprintf(
                    __('Rate limit exceeded. Please try again in %d seconds.', 'zippicks-smart-search'),
                    $limits['window']
                ),
                'retry_after' => $limits['window'],
                'limit' => $limits['requests'],
                'remaining' => 0,
                'reset' => time() + $limits['window']
            ];
        }
        
        // Increment counter
        self::increment_request_count($cache_key, $limits['window']);
        
        // Return success with rate limit headers
        return [
            'allowed' => true,
            'limit' => $limits['requests'],
            'remaining' => $limits['requests'] - $requests - 1,
            'reset' => time() + $limits['window']
        ];
    }
    
    /**
     * Get rate limit headers for response
     * 
     * @param array $rate_limit_result Result from check() method
     * @return array Headers to add to response
     */
    public static function get_headers($rate_limit_result) {
        $headers = [];
        
        if (isset($rate_limit_result['limit'])) {
            $headers['X-RateLimit-Limit'] = $rate_limit_result['limit'];
        }
        
        if (isset($rate_limit_result['remaining'])) {
            $headers['X-RateLimit-Remaining'] = max(0, $rate_limit_result['remaining']);
        }
        
        if (isset($rate_limit_result['reset'])) {
            $headers['X-RateLimit-Reset'] = $rate_limit_result['reset'];
        }
        
        if (isset($rate_limit_result['retry_after'])) {
            $headers['Retry-After'] = $rate_limit_result['retry_after'];
        }
        
        return $headers;
    }
    
    /**
     * Get client identifier
     * 
     * @return string
     */
    private static function get_client_identifier() {
        // For logged-in users, use user ID
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        
        // For anonymous users, use IP address with proper validation
        $ip = self::get_client_ip();
        
        return 'ip_' . $ip;
    }
    
    /**
     * Get client IP address with trusted proxy validation
     * 
     * @return string
     */
    private static function get_client_ip() {
        return IP_Detector::get_client_ip();
    }
    
    /**
     * Get rate limits for endpoint
     * 
     * @param string $endpoint
     * @return array
     */
    private static function get_limits($endpoint) {
        // Check for custom limits in options
        $custom_limits = get_option('zippicks_search_rate_limits', []);
        
        if (isset($custom_limits[$endpoint])) {
            return $custom_limits[$endpoint];
        }
        
        // Return default limits
        return self::$default_limits[$endpoint] ?? self::$default_limits['global'];
    }
    
    /**
     * Get cache key
     * 
     * @param string $endpoint
     * @param string $identifier
     * @return string
     */
    private static function get_cache_key($endpoint, $identifier) {
        return self::CACHE_PREFIX . md5($endpoint . '_' . $identifier);
    }
    
    /**
     * Get request count from cache
     * 
     * @param string $cache_key
     * @return int
     */
    private static function get_request_count($cache_key) {
        // Try Redis first if available
        if (self::has_redis()) {
            global $wp_object_cache;
            $count = $wp_object_cache->get($cache_key, 'rate_limit');
            return $count === false ? 0 : intval($count);
        }
        
        // Fallback to transients
        $count = get_transient($cache_key);
        return $count === false ? 0 : intval($count);
    }
    
    /**
     * Increment request count
     * 
     * @param string $cache_key
     * @param int $window Window in seconds
     */
    private static function increment_request_count($cache_key, $window) {
        // Try Redis first if available
        if (self::has_redis()) {
            global $wp_object_cache;
            
            // Use atomic INCR operation to avoid race conditions
            // INCR returns the value after incrementing
            $new_count = $wp_object_cache->incr($cache_key, 1, 'rate_limit');
            
            // Set expiration only if this is the first request (count = 1)
            // This prevents resetting the window on every request
            if ($new_count === 1) {
                // Use Redis EXPIRE command if available through cache implementation
                if (method_exists($wp_object_cache, 'expire')) {
                    $wp_object_cache->expire($cache_key, $window, 'rate_limit');
                } else {
                    // Fallback: Re-set with same value to update expiry
                    // Note: This has a small race condition but is better than the original
                    $wp_object_cache->set($cache_key, 1, 'rate_limit', $window);
                }
            }
            
            // TODO: For true sliding window rate limiting, implement using Redis sorted sets:
            // - ZADD with timestamp as score and unique request ID as member
            // - ZREMRANGEBYSCORE to remove old entries outside the window
            // - ZCARD to count requests within the window
            // This would provide more accurate rate limiting but requires more Redis operations
            
            return;
        }
        
        // Fallback to transients (still has race condition but no better option with transients)
        $count = get_transient($cache_key);
        
        if ($count === false) {
            set_transient($cache_key, 1, $window);
        } else {
            // Note: This approach has a race condition but WordPress transients don't support atomic operations
            set_transient($cache_key, intval($count) + 1, $window);
        }
    }
    
    /**
     * Check if Redis is available
     * 
     * @return bool
     */
    private static function has_redis() {
        global $wp_object_cache;
        
        if (!isset($wp_object_cache)) {
            return false;
        }
        
        // Check for common Redis object cache implementations
        $cache_class = get_class($wp_object_cache);
        $redis_implementations = [
            'Redis_Object_Cache',
            'WP_Redis_Object_Cache',
            'Predis_Object_Cache'
        ];
        
        foreach ($redis_implementations as $implementation) {
            if (strpos($cache_class, $implementation) !== false) {
                return true;
            }
        }
        
        // Check if incr method exists (Redis-specific)
        return method_exists($wp_object_cache, 'incr');
    }
    
    /**
     * Clear rate limit for identifier
     * 
     * @param string $endpoint
     * @param string $identifier
     */
    public static function clear($endpoint, $identifier) {
        $cache_key = self::get_cache_key($endpoint, $identifier);
        
        if (self::has_redis()) {
            global $wp_object_cache;
            $wp_object_cache->delete($cache_key, 'rate_limit');
        } else {
            delete_transient($cache_key);
        }
    }
    
    /**
     * Add rate limiting to WP_Error response
     * 
     * @param WP_Error $error
     * @param array $rate_limit_result
     * @return WP_Error
     */
    public static function add_to_error($error, $rate_limit_result) {
        if (isset($rate_limit_result['retry_after'])) {
            $error->add_data([
                'retry_after' => $rate_limit_result['retry_after'],
                'limit' => $rate_limit_result['limit'] ?? null,
                'reset' => $rate_limit_result['reset'] ?? null
            ], 'rate_limit_exceeded');
        }
        
        return $error;
    }
}