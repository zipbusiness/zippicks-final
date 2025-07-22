<?php
/**
 * Geohash Class
 * 
 * Implements geohash encoding/decoding for efficient spatial indexing
 * 
 * @package ZipPicks_Geo_Service
 */

namespace ZipPicks\Geo;

class Geohash {
    
    /**
     * Base32 character set for geohash
     */
    private $base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
    
    /**
     * Encode latitude/longitude to geohash
     * 
     * @param float $latitude
     * @param float $longitude
     * @param int $precision Number of characters (default 8)
     * @return string
     */
    public function encode($latitude, $longitude, $precision = 8) {
        $lat_range = [-90.0, 90.0];
        $lng_range = [-180.0, 180.0];
        $geohash = '';
        $bits = 0;
        $ch = 0;
        
        while (strlen($geohash) < $precision) {
            if ($bits % 2 === 0) {
                // Even bit: longitude
                $mid = ($lng_range[0] + $lng_range[1]) / 2;
                if ($longitude > $mid) {
                    $ch |= (1 << (4 - ($bits % 5)));
                    $lng_range[0] = $mid;
                } else {
                    $lng_range[1] = $mid;
                }
            } else {
                // Odd bit: latitude
                $mid = ($lat_range[0] + $lat_range[1]) / 2;
                if ($latitude > $mid) {
                    $ch |= (1 << (4 - ($bits % 5)));
                    $lat_range[0] = $mid;
                } else {
                    $lat_range[1] = $mid;
                }
            }
            
            $bits++;
            
            if ($bits % 5 === 0) {
                $geohash .= $this->base32[$ch];
                $ch = 0;
            }
        }
        
        return $geohash;
    }
    
    /**
     * Decode geohash to latitude/longitude bounds
     * 
     * @param string $geohash
     * @return array ['lat' => [min, max], 'lng' => [min, max]]
     */
    public function decode($geohash) {
        $lat_range = [-90.0, 90.0];
        $lng_range = [-180.0, 180.0];
        $is_lng = true;
        
        for ($i = 0; $i < strlen($geohash); $i++) {
            $cd = strpos($this->base32, $geohash[$i]);
            
            for ($mask = 16; $mask > 0; $mask >>= 1) {
                if ($is_lng) {
                    $lng_err = ($lng_range[1] - $lng_range[0]) / 2;
                    if ($cd & $mask) {
                        $lng_range[0] = ($lng_range[0] + $lng_range[1]) / 2;
                    } else {
                        $lng_range[1] = ($lng_range[0] + $lng_range[1]) / 2;
                    }
                } else {
                    $lat_err = ($lat_range[1] - $lat_range[0]) / 2;
                    if ($cd & $mask) {
                        $lat_range[0] = ($lat_range[0] + $lat_range[1]) / 2;
                    } else {
                        $lat_range[1] = ($lat_range[0] + $lat_range[1]) / 2;
                    }
                }
                $is_lng = !$is_lng;
            }
        }
        
        return [
            'lat' => $lat_range,
            'lng' => $lng_range,
        ];
    }
    
    /**
     * Get center point of geohash
     * 
     * @param string $geohash
     * @return array ['lat' => float, 'lng' => float]
     */
    public function decode_center($geohash) {
        $bounds = $this->decode($geohash);
        
        return [
            'lat' => ($bounds['lat'][0] + $bounds['lat'][1]) / 2,
            'lng' => ($bounds['lng'][0] + $bounds['lng'][1]) / 2,
        ];
    }
    
    /**
     * Get neighboring geohashes
     * 
     * @param string $geohash
     * @return array Array of 8 neighboring geohashes
     */
    public function neighbors($geohash) {
        $center = $this->decode_center($geohash);
        $bounds = $this->decode($geohash);
        
        // Calculate approximate step size
        $lat_step = ($bounds['lat'][1] - $bounds['lat'][0]) * 1.5;
        $lng_step = ($bounds['lng'][1] - $bounds['lng'][0]) * 1.5;
        
        $neighbors = [];
        $directions = [
            'n'  => [1, 0],    // North
            'ne' => [1, 1],    // Northeast
            'e'  => [0, 1],    // East
            'se' => [-1, 1],   // Southeast
            's'  => [-1, 0],   // South
            'sw' => [-1, -1],  // Southwest
            'w'  => [0, -1],   // West
            'nw' => [1, -1],   // Northwest
        ];
        
        foreach ($directions as $dir => $offset) {
            $lat = $center['lat'] + ($offset[0] * $lat_step);
            $lng = $center['lng'] + ($offset[1] * $lng_step);
            
            // Ensure coordinates are within valid range
            $lat = max(-90, min(90, $lat));
            $lng = max(-180, min(180, $lng));
            
            $neighbors[$dir] = $this->encode($lat, $lng, strlen($geohash));
        }
        
        return $neighbors;
    }
    
    /**
     * Get all geohashes within a bounding box
     * 
     * @param float $min_lat
     * @param float $max_lat
     * @param float $min_lng
     * @param float $max_lng
     * @param int $precision
     * @return array
     */
    public function get_covering_geohashes($min_lat, $max_lat, $min_lng, $max_lng, $precision = 6) {
        $geohashes = [];
        
        // Estimate step size based on precision
        $lat_step = $this->get_lat_step($precision);
        $lng_step = $this->get_lng_step($precision, ($min_lat + $max_lat) / 2);
        
        // Generate grid of points
        for ($lat = $min_lat; $lat <= $max_lat; $lat += $lat_step) {
            for ($lng = $min_lng; $lng <= $max_lng; $lng += $lng_step) {
                $hash = $this->encode($lat, $lng, $precision);
                $geohashes[$hash] = true;
            }
        }
        
        return array_keys($geohashes);
    }
    
    /**
     * Get approximate latitude step for precision level
     * 
     * @param int $precision
     * @return float
     */
    private function get_lat_step($precision) {
        // Approximate degrees per geohash cell
        $steps = [
            1 => 45.0,
            2 => 5.6,
            3 => 0.7,
            4 => 0.087,
            5 => 0.011,
            6 => 0.0014,
            7 => 0.00017,
            8 => 0.000022,
        ];
        
        return $steps[$precision] ?? 0.000022;
    }
    
    /**
     * Get approximate longitude step for precision level
     * 
     * @param int $precision
     * @param float $latitude
     * @return float
     */
    private function get_lng_step($precision, $latitude) {
        $lat_step = $this->get_lat_step($precision);
        
        // Adjust for latitude (longitude lines converge at poles)
        return $lat_step / cos(deg2rad($latitude));
    }
    
    /**
     * Calculate geohash precision needed for radius
     * 
     * @param float $radius_miles
     * @return int
     */
    public function get_precision_for_radius($radius_miles) {
        // Approximate precision levels for radius coverage
        if ($radius_miles > 1000) return 2;
        if ($radius_miles > 100) return 3;
        if ($radius_miles > 20) return 4;
        if ($radius_miles > 5) return 5;
        if ($radius_miles > 1) return 6;
        if ($radius_miles > 0.2) return 7;
        return 8;
    }
    
    /**
     * Check if two geohashes are adjacent
     * 
     * @param string $hash1
     * @param string $hash2
     * @return bool
     */
    public function are_adjacent($hash1, $hash2) {
        // Same hash
        if ($hash1 === $hash2) {
            return true;
        }
        
        // Check if hash2 is in hash1's neighbors
        $neighbors = $this->neighbors($hash1);
        return in_array($hash2, $neighbors);
    }
    
    /**
     * Get common prefix of two geohashes
     * 
     * @param string $hash1
     * @param string $hash2
     * @return string
     */
    public function common_prefix($hash1, $hash2) {
        $prefix = '';
        $len = min(strlen($hash1), strlen($hash2));
        
        for ($i = 0; $i < $len; $i++) {
            if ($hash1[$i] === $hash2[$i]) {
                $prefix .= $hash1[$i];
            } else {
                break;
            }
        }
        
        return $prefix;
    }
}