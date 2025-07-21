<?php
/**
 * City State Helper
 *
 * Production-grade helper class for city-to-state mapping using
 * data generated from the city_list.py source.
 *
 * @package ZipPicks_Master_Critic
 * @subpackage MasterCritic\Helpers
 * @since 2.0.0
 */

class ZipPicks_Master_Critic_CityStateHelper {
    
    /**
     * City-state mapping data
     *
     * @var array|null
     */
    private static $city_state_map = null;
    
    /**
     * Path to the JSON data file
     *
     * @var string|null
     */
    private static $json_file_path = null;
    
    /**
     * Cache key for the mapping data
     */
    const CACHE_KEY = 'zippicks_city_state_map';
    
    /**
     * Cache TTL (24 hours)
     */
    const CACHE_TTL = 86400;
    
    /**
     * Get state code for a given city
     *
     * @param string $city_identifier City slug, name, or variant
     * @return string|null State code (e.g., 'CA') or null if not found
     */
    public static function get_state_for_city($city_identifier) {
        if (empty($city_identifier)) {
            return null;
        }
        
        // Load the mapping data if not already loaded
        if (self::$city_state_map === null) {
            self::load_city_state_map();
        }
        
        // Normalize the city identifier for lookup
        $normalized = self::normalize_city_identifier($city_identifier);
        
        // Direct lookup
        if (isset(self::$city_state_map[$normalized])) {
            return self::$city_state_map[$normalized]['state'];
        }
        
        // Try with hyphens replaced by spaces
        $alt_normalized = str_replace('-', ' ', $normalized);
        if (isset(self::$city_state_map[$alt_normalized])) {
            return self::$city_state_map[$alt_normalized]['state'];
        }
        
        // Try with spaces replaced by hyphens
        $alt_normalized2 = str_replace(' ', '-', $normalized);
        if (isset(self::$city_state_map[$alt_normalized2])) {
            return self::$city_state_map[$alt_normalized2]['state'];
        }
        
        return null;
    }
    
    /**
     * Get full city information
     *
     * @param string $city_identifier City slug, name, or variant
     * @return array|null City data array or null if not found
     */
    public static function get_city_info($city_identifier) {
        if (empty($city_identifier)) {
            return null;
        }
        
        // Load the mapping data if not already loaded
        if (self::$city_state_map === null) {
            self::load_city_state_map();
        }
        
        $normalized = self::normalize_city_identifier($city_identifier);
        
        // Try various lookups
        $variants = [
            $normalized,
            str_replace('-', ' ', $normalized),
            str_replace(' ', '-', $normalized)
        ];
        
        foreach ($variants as $variant) {
            if (isset(self::$city_state_map[$variant])) {
                return self::$city_state_map[$variant];
            }
        }
        
        return null;
    }
    
    /**
     * Get all cities for a given state
     *
     * @param string $state_code State code (e.g., 'CA')
     * @return array Array of city data for the state
     */
    public static function get_cities_by_state($state_code) {
        if (empty($state_code)) {
            return [];
        }
        
        // Load the mapping data if not already loaded
        if (self::$city_state_map === null) {
            self::load_city_state_map();
        }
        
        $state_code = strtoupper($state_code);
        $cities = [];
        $seen_names = [];
        
        foreach (self::$city_state_map as $slug => $city_data) {
            if ($city_data['state'] === $state_code) {
                $city_name = $city_data['name'];
                
                // Avoid duplicates by checking if we already have this city name
                if (!in_array($city_name, $seen_names, true)) {
                    $seen_names[] = $city_name;
                    $cities[] = array_merge(['slug' => $slug], $city_data);
                }
            }
        }
        
        return $cities;
    }
    
    /**
     * Check if a city exists in our data
     *
     * @param string $city_identifier City slug, name, or variant
     * @return bool True if city exists
     */
    public static function city_exists($city_identifier) {
        return self::get_state_for_city($city_identifier) !== null;
    }
    
    /**
     * Load city-state mapping from JSON file
     *
     * @return void
     */
    private static function load_city_state_map() {
        // Check memory cache first
        $cached = wp_cache_get(self::CACHE_KEY);
        if ($cached !== false && is_array($cached)) {
            self::$city_state_map = $cached;
            return;
        }
        
        // Determine JSON file path
        if (self::$json_file_path === null) {
            self::$json_file_path = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 
                'includes/mastercritic/data/city-state-map.json';
        }
        
        // Check if file exists
        if (!file_exists(self::$json_file_path)) {
            // Log error and fall back to empty map
            error_log('ZipPicks CityStateHelper: city-state-map.json not found at ' . self::$json_file_path);
            self::$city_state_map = [];
            return;
        }
        
        // Read and parse JSON file
        $json_content = file_get_contents(self::$json_file_path);
        if ($json_content === false) {
            error_log('ZipPicks CityStateHelper: Failed to read city-state-map.json');
            self::$city_state_map = [];
            return;
        }
        
        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ZipPicks CityStateHelper: Invalid JSON in city-state-map.json - ' . json_last_error_msg());
            self::$city_state_map = [];
            return;
        }
        
        // Validate data structure
        if (!is_array($data)) {
            error_log('ZipPicks CityStateHelper: Invalid data structure in city-state-map.json');
            self::$city_state_map = [];
            return;
        }
        
        self::$city_state_map = $data;
        
        // Cache in memory
        wp_cache_set(self::CACHE_KEY, self::$city_state_map, '', self::CACHE_TTL);
    }
    
    /**
     * Normalize city identifier for lookup
     *
     * @param string $city_identifier City slug, name, or variant
     * @return string Normalized identifier
     */
    private static function normalize_city_identifier($city_identifier) {
        // Convert to lowercase
        $normalized = strtolower(trim($city_identifier));
        
        // Remove extra spaces
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        // Remove common suffixes that might be added
        $normalized = preg_replace('/\s+(city|town)$/i', '', $normalized);
        
        return $normalized;
    }
    
    /**
     * Refresh the city-state mapping from the JSON file
     *
     * @return bool True if successful
     */
    public static function refresh_mapping() {
        // Clear cache
        wp_cache_delete(self::CACHE_KEY);
        
        // Reset static property
        self::$city_state_map = null;
        
        // Reload
        self::load_city_state_map();
        
        return !empty(self::$city_state_map);
    }
    
    /**
     * Get all available cities
     *
     * @return array Array of unique cities with their data
     */
    public static function get_all_cities() {
        if (self::$city_state_map === null) {
            self::load_city_state_map();
        }
        
        $cities = [];
        $seen_names = [];
        
        foreach (self::$city_state_map as $slug => $city_data) {
            $city_name = $city_data['name'];
            
            // Skip if we've already seen this city name
            if (in_array($city_name, $seen_names, true)) {
                continue;
            }
            
            $seen_names[] = $city_name;
            $cities[] = array_merge(['slug' => $slug], $city_data);
        }
        
        // Sort by city name
        usort($cities, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        return $cities;
    }
    
    /**
     * Get states with available cities
     *
     * @return array Array of state codes that have cities
     */
    public static function get_available_states() {
        if (self::$city_state_map === null) {
            self::load_city_state_map();
        }
        
        $states = [];
        
        foreach (self::$city_state_map as $city_data) {
            $state = $city_data['state'];
            if (!in_array($state, $states, true)) {
                $states[] = $state;
            }
        }
        
        sort($states);
        return $states;
    }
    
    /**
     * Set custom JSON file path (useful for testing)
     *
     * @param string $path Path to JSON file
     */
    public static function set_json_file_path($path) {
        self::$json_file_path = $path;
        self::refresh_mapping();
    }
}