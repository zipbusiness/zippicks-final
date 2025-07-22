<?php
/**
 * Geo Cache Class
 * 
 * Handles Redis caching for location data, distance calculations,
 * and nearby search results
 * 
 * @package ZipPicks_Geo_Service
 */

namespace ZipPicks\Geo;

class Geo_Cache {
    
    /**
     * Redis instance
     * @var \Redis|null
     */
    private $redis;
    
    /**
     * Cache key prefix
     */
    const KEY_PREFIX = 'zippicks:geo:';
    
    /**
     * TTL configurations (in seconds)
     */
    private $ttl = [
        'user_location' => 300,      // 5 minutes
        'geocode_result' => 86400,   // 24 hours
        'distance_calc' => 3600,     // 1 hour
        'nearby_results' => 300,     // 5 minutes
        'ip_location' => 3600,       // 1 hour
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_redis();
    }
    
    /**
     * Initialize Redis connection
     */
    private function init_redis() {
        // Check if Redis is available through Foundation
        if (function_exists('zippicks') && zippicks()->has('redis')) {
            $this->redis = zippicks()->get('redis');
            return;
        }
        
        // Try to connect directly if available
        if (class_exists('\Redis')) {
            try {
                $this->redis = new \Redis();
                
                // Get Redis configuration from environment or defaults
                $host = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
                $port = defined('REDIS_PORT') ? REDIS_PORT : 6379;
                
                if ($this->redis->connect($host, $port, 2.0)) {
                    // Set Redis options
                    $this->redis->setOption(\Redis::OPT_PREFIX, self::KEY_PREFIX);
                    $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
                    
                    // Test connection
                    $this->redis->ping();
                } else {
                    $this->redis = null;
                }
            } catch (\Exception $e) {
                $this->redis = null;
                
                if (function_exists('zippicks') && zippicks()->has('logger')) {
                    $logger = zippicks()->get('logger');
                    $logger->error('Redis connection failed', [
                        'error' => $e->getMessage(),
                        'code' => ZIPPICKS_GEO_ERRORS['GEO005'],
                    ]);
                }
            }
        }
        
        // Fall back to WordPress transients if Redis not available
        if (!$this->redis) {
            add_filter('zippicks_geo_cache_fallback', '__return_true');
        }
    }
    
    /**
     * Get user location from cache
     * 
     * @param string $identifier Session or user identifier
     * @return array|null
     */
    public function get_user_location($identifier) {
        $key = $this->make_key('location', $identifier);
        return $this->get($key);
    }
    
    /**
     * Set user location in cache
     * 
     * @param string $identifier
     * @param array $location
     * @param int|null $ttl
     * @return bool
     */
    public function set_user_location($identifier, $location, $ttl = null) {
        $key = $this->make_key('location', $identifier);
        $ttl = $ttl ?? $this->ttl['user_location'];
        
        return $this->set($key, $location, $ttl);
    }
    
    /**
     * Get geocode result from cache
     * 
     * @param string $input Input string (ZIP, address, etc.)
     * @param string $type Input type
     * @return array|null
     */
    public function get_geocode_result($input, $type) {
        $key = $this->make_key('geocode', md5($type . ':' . $input));
        return $this->get($key);
    }
    
    /**
     * Set geocode result in cache
     * 
     * @param string $input
     * @param string $type
     * @param array $result
     * @return bool
     */
    public function set_geocode_result($input, $type, $result) {
        $key = $this->make_key('geocode', md5($type . ':' . $input));
        return $this->set($key, $result, $this->ttl['geocode_result']);
    }
    
    /**
     * Get distance calculation from cache
     * 
     * @param string $cache_key
     * @return float|null
     */
    public function get_distance_calculation($cache_key) {
        $key = $this->make_key('distance', $cache_key);
        return $this->get($key);
    }
    
    /**
     * Set distance calculation in cache
     * 
     * @param string $cache_key
     * @param float $distance
     * @return bool
     */
    public function set_distance_calculation($cache_key, $distance) {
        $key = $this->make_key('distance', $cache_key);
        return $this->set($key, $distance, $this->ttl['distance_calc']);
    }
    
    /**
     * Get nearby results from cache
     * 
     * @param float $lat
     * @param float $lng
     * @param float $radius
     * @param array $filters
     * @return array|null
     */
    public function get_nearby_results($lat, $lng, $radius, $filters = []) {
        $cache_key = md5(sprintf('%f:%f:%f:%s', $lat, $lng, $radius, serialize($filters)));
        $key = $this->make_key('nearby', $cache_key);
        
        return $this->get($key);
    }
    
    /**
     * Set nearby results in cache
     * 
     * @param float $lat
     * @param float $lng
     * @param float $radius
     * @param array $results
     * @param array $filters
     * @return bool
     */
    public function set_nearby_results($lat, $lng, $radius, $results, $filters = []) {
        $cache_key = md5(sprintf('%f:%f:%f:%s', $lat, $lng, $radius, serialize($filters)));
        $key = $this->make_key('nearby', $cache_key);
        
        return $this->set($key, $results, $this->ttl['nearby_results']);
    }
    
    /**
     * Clear user location cache
     * 
     * @param string $identifier
     * @return bool
     */
    public function clear_user_location($identifier) {
        $key = $this->make_key('location', $identifier);
        return $this->delete($key);
    }
    
    /**
     * Clear all nearby results for a location
     * 
     * @param float $lat
     * @param float $lng
     * @return int Number of keys deleted
     */
    public function clear_nearby_results($lat, $lng) {
        if (!$this->redis) {
            return 0;
        }
        
        // Find all keys matching the pattern
        $pattern = $this->make_key('nearby', sprintf('%f:%f:*', $lat, $lng));
        $keys = $this->redis->keys($pattern);
        
        if (empty($keys)) {
            return 0;
        }
        
        return $this->redis->del($keys);
    }
    
    /**
     * Get from cache (with fallback to transients)
     * 
     * @param string $key
     * @return mixed
     */
    private function get($key) {
        if ($this->redis) {
            try {
                return $this->redis->get($key);
            } catch (\Exception $e) {
                // Fall through to transient
            }
        }
        
        // Fallback to WordPress transients
        return get_transient($key);
    }
    
    /**
     * Set in cache (with fallback to transients)
     * 
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    private function set($key, $value, $ttl) {
        if ($this->redis) {
            try {
                return $this->redis->setex($key, $ttl, $value);
            } catch (\Exception $e) {
                // Fall through to transient
            }
        }
        
        // Fallback to WordPress transients
        return set_transient($key, $value, $ttl);
    }
    
    /**
     * Delete from cache
     * 
     * @param string $key
     * @return bool
     */
    private function delete($key) {
        if ($this->redis) {
            try {
                return (bool) $this->redis->del($key);
            } catch (\Exception $e) {
                // Fall through to transient
            }
        }
        
        // Fallback to WordPress transients
        return delete_transient($key);
    }
    
    /**
     * Make cache key
     * 
     * @param string $type
     * @param string $identifier
     * @return string
     */
    private function make_key($type, $identifier) {
        // Remove prefix if using Redis (already set as option)
        if ($this->redis) {
            return "{$type}:{$identifier}";
        }
        
        // Include prefix for transients
        return self::KEY_PREFIX . "{$type}:{$identifier}";
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function get_stats() {
        $stats = [
            'driver' => $this->redis ? 'redis' : 'transients',
            'connected' => (bool) $this->redis,
            'keys' => 0,
            'memory' => 0,
        ];
        
        if ($this->redis) {
            try {
                $info = $this->redis->info();
                $stats['keys'] = $info['db0']['keys'] ?? 0;
                $stats['memory'] = $info['used_memory_human'] ?? '0B';
            } catch (\Exception $e) {
                // Keep defaults
            }
        }
        
        return $stats;
    }
    
    /**
     * Warm cache for popular locations
     * 
     * @param array $locations Array of [lat, lng] pairs
     * @param float $radius Default radius
     */
    public function warm_cache($locations, $radius = 5) {
        if (!$this->redis || empty($locations)) {
            return;
        }
        
        // This would be called by a cron job to pre-populate cache
        // for popular search areas
        foreach ($locations as $location) {
            if (isset($location['lat']) && isset($location['lng'])) {
                // Trigger a nearby search to populate cache
                $calc = new Distance_Calculator();
                $calc->set_cache($this);
                $calc->find_within_radius($location['lat'], $location['lng'], $radius);
            }
        }
    }
}