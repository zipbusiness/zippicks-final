<?php
/**
 * Distance Calculator Class
 * 
 * Implements Haversine formula for distance calculations
 * and efficient radius-based searches
 * 
 * @package ZipPicks_Geo_Service
 */

namespace ZipPicks\Geo;

class Distance_Calculator {
    
    /**
     * Earth radius constants
     */
    const EARTH_RADIUS_MILES = 3959;
    const EARTH_RADIUS_KM = 6371;
    
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
     * Calculate distance between two points using Haversine formula
     * 
     * @param float $lat1 First point latitude
     * @param float $lng1 First point longitude
     * @param float $lat2 Second point latitude
     * @param float $lng2 Second point longitude
     * @param string $unit Distance unit ('miles' or 'km')
     * @return float Distance
     */
    public function calculate_distance($lat1, $lng1, $lat2, $lng2, $unit = 'miles') {
        // Check cache first
        if ($this->cache) {
            $cache_key = md5("distance:{$lat1}:{$lng1}:{$lat2}:{$lng2}:{$unit}");
            $cached = $this->cache->get_distance_calculation($cache_key);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        // Convert to radians
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);
        
        // Calculate differences
        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;
        
        // Haversine formula
        $a = sin($dlat / 2) * sin($dlat / 2) +
             cos($lat1) * cos($lat2) *
             sin($dlng / 2) * sin($dlng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        // Calculate distance
        $radius = ($unit === 'km') ? self::EARTH_RADIUS_KM : self::EARTH_RADIUS_MILES;
        $distance = $radius * $c;
        
        // Cache result
        if ($this->cache) {
            $this->cache->set_distance_calculation($cache_key, $distance);
        }
        
        return $distance;
    }
    
    /**
     * Calculate distances for multiple points from a center
     * 
     * @param float $center_lat Center latitude
     * @param float $center_lng Center longitude
     * @param array $points Array of points with 'latitude' and 'longitude' keys
     * @param string $unit Distance unit
     * @return array Points with added 'distance' key
     */
    public function calculate_batch_distances($center_lat, $center_lng, $points, $unit = 'miles') {
        $results = [];
        
        foreach ($points as $point) {
            if (!isset($point['latitude']) || !isset($point['longitude'])) {
                continue;
            }
            
            $distance = $this->calculate_distance(
                $center_lat,
                $center_lng,
                $point['latitude'],
                $point['longitude'],
                $unit
            );
            
            $point['distance'] = $distance;
            $results[] = $point;
        }
        
        // Sort by distance
        usort($results, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });
        
        return $results;
    }
    
    /**
     * Find businesses within radius using API
     * 
     * @param float $center_lat Center latitude
     * @param float $center_lng Center longitude
     * @param float $radius_miles Search radius in miles
     * @param int $limit Maximum results
     * @param array $filters Additional filters
     * @return array
     */
    public function find_within_radius($center_lat, $center_lng, $radius_miles, $limit = 20, $filters = []) {
        // Check cache first
        if ($this->cache) {
            $cached = $this->cache->get_nearby_results($center_lat, $center_lng, $radius_miles, $filters);
            if ($cached) {
                return $cached;
            }
        }
        
        // Use API client if available
        if ($this->api_client) {
            $api_response = $this->api_client->find_nearby($center_lat, $center_lng, $radius_miles, $limit);
            
            if ($api_response && isset($api_response['results'])) {
                $results = $api_response['results'];
                
                // Cache results
                if ($this->cache && is_array($results)) {
                    $this->cache->set_nearby_results($center_lat, $center_lng, $radius_miles, $results, $filters);
                }
                
                return $results;
            }
        }
        
        // Fallback: return empty array if API is not available
        error_log('ZipPicks Geo: API client not available for finding nearby restaurants');
        return [];
    }
    
    /**
     * Find businesses within radius from WordPress posts
     * 
     * @param float $center_lat
     * @param float $center_lng
     * @param float $radius_miles
     * @param int $limit
     * @param string $post_type
     * @return array
     */
    public function find_posts_within_radius($center_lat, $center_lng, $radius_miles, $limit = 20, $post_type = 'zippicks_business') {
        global $wpdb;
        
        // Calculate bounding box
        $lat_range = $radius_miles / 69.0;
        $lng_range = $radius_miles / (69.0 * cos(deg2rad($center_lat)));
        
        // Query posts with location meta
        $query = $wpdb->prepare("
            SELECT 
                p.ID,
                p.post_title,
                lat.meta_value AS latitude,
                lng.meta_value AS longitude,
                (%f * acos(
                    cos(radians(%f)) * cos(radians(CAST(lat.meta_value AS DECIMAL(10,8)))) * 
                    cos(radians(CAST(lng.meta_value AS DECIMAL(10,8))) - radians(%f)) + 
                    sin(radians(%f)) * sin(radians(CAST(lat.meta_value AS DECIMAL(10,8))))
                )) AS distance
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} lat ON p.ID = lat.post_id AND lat.meta_key = 'latitude'
            INNER JOIN {$wpdb->postmeta} lng ON p.ID = lng.post_id AND lng.meta_key = 'longitude'
            WHERE p.post_type = %s
                AND p.post_status = 'publish'
                AND CAST(lat.meta_value AS DECIMAL(10,8)) BETWEEN %f AND %f
                AND CAST(lng.meta_value AS DECIMAL(10,8)) BETWEEN %f AND %f
            HAVING distance < %f
            ORDER BY distance ASC
            LIMIT %d
        ",
            self::EARTH_RADIUS_MILES,
            $center_lat, $center_lng, $center_lat,
            $post_type,
            $center_lat - $lat_range, $center_lat + $lat_range,
            $center_lng - $lng_range, $center_lng + $lng_range,
            $radius_miles,
            $limit
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Add additional post data
        if (is_array($results)) {
            foreach ($results as &$result) {
                $result['permalink'] = get_permalink($result['ID']);
                $result['bearing'] = $this->calculate_bearing(
                    $center_lat,
                    $center_lng,
                    $result['latitude'],
                    $result['longitude']
                );
                $result['distance'] = round($result['distance'], 2);
            }
        } else {
            $results = [];
        }
        
        return $results;
    }
    
    /**
     * Calculate bearing between two points
     * 
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return string Compass direction (N, NE, E, SE, S, SW, W, NW)
     */
    private function calculate_bearing($lat1, $lng1, $lat2, $lng2) {
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $lng_diff = deg2rad($lng2 - $lng1);
        
        $x = sin($lng_diff) * cos($lat2);
        $y = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($lng_diff);
        
        $bearing = atan2($x, $y);
        $bearing = rad2deg($bearing);
        $bearing = fmod($bearing + 360, 360);
        
        // Convert to compass direction
        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = round($bearing / 45) % 8;
        
        return $directions[$index];
    }
    
    /**
     * Check if a point is within a radius
     * 
     * @param float $center_lat
     * @param float $center_lng
     * @param float $point_lat
     * @param float $point_lng
     * @param float $radius_miles
     * @return bool
     */
    public function is_within_radius($center_lat, $center_lng, $point_lat, $point_lng, $radius_miles) {
        $distance = $this->calculate_distance($center_lat, $center_lng, $point_lat, $point_lng);
        return $distance <= $radius_miles;
    }
    
    /**
     * Get bounding box for a radius
     * 
     * @param float $center_lat
     * @param float $center_lng
     * @param float $radius_miles
     * @return array
     */
    public function get_bounding_box($center_lat, $center_lng, $radius_miles) {
        $lat_range = $radius_miles / 69.0;
        $lng_range = $radius_miles / (69.0 * cos(deg2rad($center_lat)));
        
        return [
            'min_lat' => $center_lat - $lat_range,
            'max_lat' => $center_lat + $lat_range,
            'min_lng' => $center_lng - $lng_range,
            'max_lng' => $center_lng + $lng_range,
        ];
    }
    
    /**
     * Convert between distance units
     * 
     * @param float $value
     * @param string $from_unit
     * @param string $to_unit
     * @return float
     */
    public function convert_distance($value, $from_unit, $to_unit) {
        if ($from_unit === $to_unit) {
            return $value;
        }
        
        $conversions = [
            'miles_to_km' => 1.60934,
            'km_to_miles' => 0.621371,
            'miles_to_meters' => 1609.34,
            'meters_to_miles' => 0.000621371,
            'km_to_meters' => 1000,
            'meters_to_km' => 0.001,
        ];
        
        $key = $from_unit . '_to_' . $to_unit;
        
        if (isset($conversions[$key])) {
            return $value * $conversions[$key];
        }
        
        // Handle two-step conversions
        if ($from_unit === 'meters' && $to_unit === 'km') {
            return $value * $conversions['meters_to_km'];
        } elseif ($from_unit === 'km' && $to_unit === 'meters') {
            return $value * $conversions['km_to_meters'];
        }
        
        return $value;
    }
}