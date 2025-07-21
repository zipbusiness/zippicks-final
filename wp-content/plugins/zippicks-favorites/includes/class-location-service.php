<?php
namespace ZipPicks\Favorites;

if (!defined('ABSPATH')) {
    exit;
}

class Location_Service {
    
    private $cache_ttl;
    private $geocoding_provider;
    
    public function __construct() {
        $this->cache_ttl = get_option('zippicks_favorites_cache_ttl', 86400);
        $this->geocoding_provider = get_option('zippicks_favorites_geocoding_provider', 'nominatim');
    }
    
    /**
     * Get location data from business post
     */
    public function get_business_location($business_id) {
        $location_data = [
            'latitude' => null,
            'longitude' => null,
            'city' => null,
            'state' => null,
            'country' => 'US',
            'neighborhood' => null,
            'zip_code' => null,
            'formatted_address' => null
        ];
        
        // Get location from post meta
        $latitude = get_post_meta($business_id, 'latitude', true);
        $longitude = get_post_meta($business_id, 'longitude', true);
        
        if ($latitude && $longitude) {
            $location_data['latitude'] = floatval($latitude);
            $location_data['longitude'] = floatval($longitude);
        }
        
        // Get address components
        $location_data['city'] = get_post_meta($business_id, 'city', true);
        $location_data['state'] = get_post_meta($business_id, 'state', true);
        $location_data['neighborhood'] = get_post_meta($business_id, 'neighborhood', true);
        $location_data['zip_code'] = get_post_meta($business_id, 'zip_code', true);
        
        // Get formatted address
        $address = get_post_meta($business_id, 'address', true);
        if ($address) {
            $location_data['formatted_address'] = $address;
            
            // If no coordinates, try to geocode
            if (!$location_data['latitude'] || !$location_data['longitude']) {
                $geocoded = $this->geocode_address($address);
                if ($geocoded) {
                    $location_data = array_merge($location_data, $geocoded);
                }
            }
        }
        
        return $location_data;
    }
    
    /**
     * Geocode an address to get coordinates
     */
    public function geocode_address($address) {
        $cache_key = 'zippicks_geocode_' . md5($address);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $result = null;
        
        switch ($this->geocoding_provider) {
            case 'google':
                $result = $this->geocode_with_google($address);
                break;
            case 'mapbox':
                $result = $this->geocode_with_mapbox($address);
                break;
            default:
                $result = $this->geocode_with_nominatim($address);
        }
        
        if ($result) {
            set_transient($cache_key, $result, $this->cache_ttl);
        }
        
        return $result;
    }
    
    /**
     * Geocode using OpenStreetMap Nominatim (free)
     */
    private function geocode_with_nominatim($address) {
        $url = 'https://nominatim.openstreetmap.org/search';
        $args = [
            'q' => $address,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'us',
            'addressdetails' => 1
        ];
        
        $response = wp_remote_get(add_query_arg($args, $url), [
            'headers' => ['User-Agent' => 'ZipPicks/1.0']
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($data) || !isset($data[0])) {
            return null;
        }
        
        $result = $data[0];
        $address_parts = $result['address'] ?? [];
        
        return [
            'latitude' => floatval($result['lat']),
            'longitude' => floatval($result['lon']),
            'city' => $address_parts['city'] ?? $address_parts['town'] ?? null,
            'state' => $this->normalize_state($address_parts['state'] ?? null),
            'country' => 'US',
            'neighborhood' => $address_parts['suburb'] ?? $address_parts['neighbourhood'] ?? null,
            'zip_code' => $address_parts['postcode'] ?? null,
            'formatted_address' => $result['display_name'] ?? null
        ];
    }
    
    /**
     * Geocode using Google Maps API
     */
    private function geocode_with_google($address) {
        $api_key = get_option('zippicks_google_maps_api_key');
        if (!$api_key) {
            return null;
        }
        
        $url = 'https://maps.googleapis.com/maps/api/geocode/json';
        $args = [
            'address' => $address,
            'key' => $api_key,
            'components' => 'country:US'
        ];
        
        $response = wp_remote_get(add_query_arg($args, $url));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($data['status'] !== 'OK' || empty($data['results'])) {
            return null;
        }
        
        $result = $data['results'][0];
        $location_data = [
            'latitude' => $result['geometry']['location']['lat'],
            'longitude' => $result['geometry']['location']['lng'],
            'formatted_address' => $result['formatted_address']
        ];
        
        // Parse address components
        foreach ($result['address_components'] as $component) {
            $types = $component['types'];
            
            if (in_array('locality', $types)) {
                $location_data['city'] = $component['long_name'];
            } elseif (in_array('administrative_area_level_1', $types)) {
                $location_data['state'] = $component['short_name'];
            } elseif (in_array('neighborhood', $types)) {
                $location_data['neighborhood'] = $component['long_name'];
            } elseif (in_array('postal_code', $types)) {
                $location_data['zip_code'] = $component['long_name'];
            }
        }
        
        return $location_data;
    }
    
    /**
     * Geocode using Mapbox API
     */
    private function geocode_with_mapbox($address) {
        $api_key = get_option('zippicks_mapbox_api_key');
        if (!$api_key) {
            return null;
        }
        
        $url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . urlencode($address) . '.json';
        $args = [
            'access_token' => $api_key,
            'country' => 'US',
            'limit' => 1
        ];
        
        $response = wp_remote_get(add_query_arg($args, $url));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($data['features'])) {
            return null;
        }
        
        $feature = $data['features'][0];
        $location_data = [
            'latitude' => $feature['center'][1],
            'longitude' => $feature['center'][0],
            'formatted_address' => $feature['place_name']
        ];
        
        // Parse context for location components
        if (isset($feature['context'])) {
            foreach ($feature['context'] as $context) {
                if (strpos($context['id'], 'place.') === 0) {
                    $location_data['city'] = $context['text'];
                } elseif (strpos($context['id'], 'region.') === 0) {
                    $location_data['state'] = $context['short_code'] ?? $context['text'];
                } elseif (strpos($context['id'], 'neighborhood.') === 0) {
                    $location_data['neighborhood'] = $context['text'];
                } elseif (strpos($context['id'], 'postcode.') === 0) {
                    $location_data['zip_code'] = $context['text'];
                }
            }
        }
        
        return $location_data;
    }
    
    /**
     * Calculate distance between two coordinates
     */
    public function calculate_distance($lat1, $lon1, $lat2, $lon2, $unit = 'km') {
        $earth_radius = ($unit === 'mi') ? 3959 : 6371; // miles or kilometers
        
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
        
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        
        $a = sin($dlat / 2) * sin($dlat / 2) + 
             cos($lat1) * cos($lat2) * 
             sin($dlon / 2) * sin($dlon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earth_radius * $c;
        
        return round($distance, 2);
    }
    
    /**
     * Get user's current location from IP
     */
    public function get_user_location_from_ip($ip = null) {
        if (!$ip) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        // Skip for local IPs
        if (in_array($ip, ['127.0.0.1', '::1']) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }
        
        $cache_key = 'zippicks_ip_location_' . md5($ip);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Use ip-api.com (free tier: 45 requests per minute)
        $response = wp_remote_get("http://ip-api.com/json/{$ip}");
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($data['status'] !== 'success') {
            return null;
        }
        
        $location = [
            'latitude' => $data['lat'],
            'longitude' => $data['lon'],
            'city' => $data['city'],
            'state' => $this->normalize_state($data['regionName']),
            'country' => $data['countryCode'],
            'zip_code' => $data['zip']
        ];
        
        set_transient($cache_key, $location, $this->cache_ttl);
        
        return $location;
    }
    
    /**
     * Normalize state names to abbreviations
     */
    private function normalize_state($state) {
        if (!$state || strlen($state) === 2) {
            return $state;
        }
        
        $states = [
            'Alabama' => 'AL', 'Alaska' => 'AK', 'Arizona' => 'AZ', 'Arkansas' => 'AR',
            'California' => 'CA', 'Colorado' => 'CO', 'Connecticut' => 'CT', 'Delaware' => 'DE',
            'Florida' => 'FL', 'Georgia' => 'GA', 'Hawaii' => 'HI', 'Idaho' => 'ID',
            'Illinois' => 'IL', 'Indiana' => 'IN', 'Iowa' => 'IA', 'Kansas' => 'KS',
            'Kentucky' => 'KY', 'Louisiana' => 'LA', 'Maine' => 'ME', 'Maryland' => 'MD',
            'Massachusetts' => 'MA', 'Michigan' => 'MI', 'Minnesota' => 'MN', 'Mississippi' => 'MS',
            'Missouri' => 'MO', 'Montana' => 'MT', 'Nebraska' => 'NE', 'Nevada' => 'NV',
            'New Hampshire' => 'NH', 'New Jersey' => 'NJ', 'New Mexico' => 'NM', 'New York' => 'NY',
            'North Carolina' => 'NC', 'North Dakota' => 'ND', 'Ohio' => 'OH', 'Oklahoma' => 'OK',
            'Oregon' => 'OR', 'Pennsylvania' => 'PA', 'Rhode Island' => 'RI', 'South Carolina' => 'SC',
            'South Dakota' => 'SD', 'Tennessee' => 'TN', 'Texas' => 'TX', 'Utah' => 'UT',
            'Vermont' => 'VT', 'Virginia' => 'VA', 'Washington' => 'WA', 'West Virginia' => 'WV',
            'Wisconsin' => 'WI', 'Wyoming' => 'WY'
        ];
        
        return $states[$state] ?? $state;
    }
    
    /**
     * Get popular cities from favorites
     */
    public function get_popular_cities($limit = 10) {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT city, state, COUNT(*) as count
            FROM $table
            WHERE city IS NOT NULL AND state IS NOT NULL
            GROUP BY city, state
            ORDER BY count DESC
            LIMIT %d
        ", $limit));
        
        return array_map(function($row) {
            return [
                'city' => $row->city,
                'state' => $row->state,
                'count' => intval($row->count),
                'display_name' => $row->city . ', ' . $row->state
            ];
        }, $results);
    }
}