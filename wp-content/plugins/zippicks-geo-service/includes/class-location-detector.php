<?php
/**
 * Location Detector Class
 * 
 * Implements multi-source location cascade:
 * 1. Session cache
 * 2. User profile ZIP
 * 3. IP geolocation
 * 4. Browser GPS (handled client-side)
 * 5. Default fallback
 * 
 * @package ZipPicks_Geo_Service
 */

namespace ZipPicks\Geo;

class Location_Detector {
    
    /**
     * Cache instance
     * @var Geo_Cache
     */
    private $cache;
    
    /**
     * API client instance
     * @var Geo_API_Client
     */
    private $api_client;
    
    /**
     * IP geolocation service
     * @var IP_Geolocation
     */
    private $ip_service;
    
    /**
     * Default location (Los Angeles)
     */
    const DEFAULT_LOCATION = [
        'latitude' => 34.0522,
        'longitude' => -118.2437,
        'city' => 'Los Angeles',
        'state' => 'CA',
        'country' => 'US',
        'accuracy' => 'default',
        'accuracy_meters' => 50000,
        'source' => 'default',
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->ip_service = new IP_Geolocation();
    }
    
    /**
     * Set cache instance
     */
    public function set_cache(Geo_Cache $cache) {
        $this->cache = $cache;
    }
    
    /**
     * Set API client instance
     */
    public function set_api_client(Geo_API_Client $api_client) {
        $this->api_client = $api_client;
    }
    
    /**
     * Get user location using cascade strategy
     * 
     * @param int|null $user_id WordPress user ID
     * @param string|null $session_id Session identifier
     * @return array Location data
     */
    public function get_user_location($user_id = null, $session_id = null) {
        // Get user ID if not provided
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Get session ID if not provided
        if (!$session_id) {
            $session_id = $this->get_session_id();
        }
        
        // Use API client if available
        if ($this->api_client) {
            $api_location = $this->api_client->detect_location($session_id);
            if ($api_location) {
                // Cache locally if cache is available
                if ($this->cache && $session_id) {
                    $this->cache->set_user_location($session_id, $api_location);
                }
                return $api_location;
            }
        }
        
        // Fallback to local detection if API is not available
        
        // 1. Check session cache (fastest)
        if ($this->cache && $session_id) {
            $cached_location = $this->cache->get_user_location($session_id);
            if ($cached_location) {
                $cached_location['cached'] = true;
                return $cached_location;
            }
        }
        
        // 2. Check user profile ZIP
        if ($user_id) {
            $profile_location = $this->get_user_zip_location($user_id);
            if ($profile_location) {
                $this->cache_location($session_id, $profile_location);
                return $profile_location;
            }
        }
        
        // 3. IP geolocation
        $ip_location = $this->get_ip_location();
        if ($ip_location) {
            $this->cache_location($session_id, $ip_location);
            return $ip_location;
        }
        
        // 4. Browser GPS handled client-side via AJAX
        
        // 5. Default fallback
        $default = $this->get_default_location();
        $this->cache_location($session_id, $default, 300); // Cache for 5 minutes
        
        return $default;
    }
    
    /**
     * Get location from user profile ZIP code
     * 
     * @param int $user_id
     * @return array|null
     */
    private function get_user_zip_location($user_id) {
        $preferences = get_user_meta($user_id, 'zippicks_location_preferences', true);
        
        if (empty($preferences['zip_code'])) {
            return null;
        }
        
        // Check geocode cache for ZIP
        if ($this->cache) {
            $cached = $this->cache->get_geocode_result($preferences['zip_code'], 'zip');
            if ($cached) {
                $cached['source'] = 'user_profile';
                $cached['cached'] = true;
                return $cached;
            }
        }
        
        // Look up ZIP code coordinates
        $location = $this->geocode_zip($preferences['zip_code']);
        if ($location) {
            $location['source'] = 'user_profile';
            $location['cached'] = false;
            
            // Cache the geocode result
            if ($this->cache) {
                $this->cache->set_geocode_result($preferences['zip_code'], 'zip', $location);
            }
            
            return $location;
        }
        
        return null;
    }
    
    /**
     * Get location from IP address
     * 
     * @return array|null
     */
    private function get_ip_location() {
        $location = $this->ip_service->get_location_by_ip();
        
        if ($location) {
            $location['cached'] = false;
            $location['timestamp'] = time();
            return $location;
        }
        
        return null;
    }
    
    /**
     * Get default location
     * 
     * @return array
     */
    private function get_default_location() {
        $location = self::DEFAULT_LOCATION;
        $location['timestamp'] = time();
        $location['cached'] = false;
        
        return $location;
    }
    
    /**
     * Update user location from client
     * 
     * @param array $location Location data from client
     * @param int|null $user_id
     * @param string|null $session_id
     * @return bool
     */
    public function update_user_location($location, $user_id = null, $session_id = null) {
        // Validate location data
        if (!$this->validate_location($location)) {
            return false;
        }
        
        // Get identifiers
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$session_id) {
            $session_id = $this->get_session_id();
        }
        
        // Add metadata
        $location['timestamp'] = time();
        $location['cached'] = false;
        
        // Store in database if user is logged in
        if ($user_id) {
            $this->store_user_location($user_id, $location);
        }
        
        // Store in cache
        $this->cache_location($session_id, $location);
        
        // Update user's last known location
        if ($user_id) {
            $preferences = get_user_meta($user_id, 'zippicks_location_preferences', true);
            if (!is_array($preferences)) {
                $preferences = [];
            }
            
            $preferences['last_known_location'] = [
                'lat' => $location['latitude'],
                'lng' => $location['longitude'],
                'timestamp' => time(),
            ];
            
            update_user_meta($user_id, 'zippicks_location_preferences', $preferences);
        }
        
        return true;
    }
    
    /**
     * Validate location data
     * 
     * @param array $location
     * @return bool
     */
    private function validate_location($location) {
        // Check required fields
        if (!isset($location['latitude']) || !isset($location['longitude'])) {
            return false;
        }
        
        // Validate latitude range
        if ($location['latitude'] < -90 || $location['latitude'] > 90) {
            return false;
        }
        
        // Validate longitude range
        if ($location['longitude'] < -180 || $location['longitude'] > 180) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Cache location data
     * 
     * @param string $identifier Session or user identifier
     * @param array $location
     * @param int $ttl Time to live in seconds
     */
    private function cache_location($identifier, $location, $ttl = null) {
        if ($this->cache && $identifier) {
            $this->cache->set_user_location($identifier, $location, $ttl);
        }
    }
    
    /**
     * Store user location in database
     * 
     * @param int $user_id
     * @param array $location
     */
    private function store_user_location($user_id, $location) {
        // Use API client if available
        if ($this->api_client) {
            // API will handle storing location in PostgreSQL
            $additional_data = [
                'wp_user_id' => $user_id,
                'session_id' => $this->get_session_id(),
                'source' => $location['source'] ?? 'unknown',
                'accuracy' => $location['accuracy'] ?? 'unknown',
                'accuracy_meters' => $location['accuracy_meters'] ?? null,
                'city' => $location['city'] ?? null,
                'state' => $location['state'] ?? null,
                'zip_code' => $location['zip_code'] ?? null,
            ];
            
            $this->api_client->update_location(
                $location['latitude'],
                $location['longitude'],
                $additional_data
            );
            
            return;
        }
        
        // Fallback: log that we couldn't store location
        error_log('ZipPicks Geo: Cannot store user location - API client not available');
    }
    
    /**
     * Geocode ZIP code to coordinates
     * 
     * @param string $zip_code
     * @return array|null
     */
    private function geocode_zip($zip_code) {
        // Use API client if available
        if ($this->api_client) {
            $result = $this->api_client->geocode($zip_code, 'zip');
            if ($result && isset($result['latitude']) && isset($result['longitude'])) {
                return [
                    'latitude' => $result['latitude'],
                    'longitude' => $result['longitude'],
                    'city' => $result['city'] ?? null,
                    'state' => $result['state'] ?? null,
                    'zip_code' => $zip_code,
                    'country' => 'US',
                    'accuracy' => $result['accuracy'] ?? 'zip',
                    'accuracy_meters' => $result['accuracy_meters'] ?? 5000,
                ];
            }
        }
        
        // Fallback to hardcoded values for MVP
        $zip_coords = [
            '90210' => ['lat' => 34.0901, 'lng' => -118.4065, 'city' => 'Beverly Hills', 'state' => 'CA'],
            '10001' => ['lat' => 40.7506, 'lng' => -73.9972, 'city' => 'New York', 'state' => 'NY'],
            '60601' => ['lat' => 41.8858, 'lng' => -87.6181, 'city' => 'Chicago', 'state' => 'IL'],
            '33101' => ['lat' => 25.7814, 'lng' => -80.1870, 'city' => 'Miami', 'state' => 'FL'],
            '94102' => ['lat' => 37.7816, 'lng' => -122.4156, 'city' => 'San Francisco', 'state' => 'CA'],
        ];
        
        if (isset($zip_coords[$zip_code])) {
            return [
                'latitude' => $zip_coords[$zip_code]['lat'],
                'longitude' => $zip_coords[$zip_code]['lng'],
                'city' => $zip_coords[$zip_code]['city'],
                'state' => $zip_coords[$zip_code]['state'],
                'zip_code' => $zip_code,
                'country' => 'US',
                'accuracy' => 'zip',
                'accuracy_meters' => 5000,
            ];
        }
        
        // Default to city center for unknown ZIPs
        return null;
    }
    
    /**
     * Get session ID
     * 
     * @return string
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['zippicks_geo_session'])) {
            $_SESSION['zippicks_geo_session'] = wp_generate_password(32, false);
        }
        
        return $_SESSION['zippicks_geo_session'];
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}