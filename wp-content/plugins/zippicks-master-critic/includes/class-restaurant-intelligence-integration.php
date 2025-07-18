<?php
/**
 * Restaurant Intelligence Plugin Integration
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

class ZipPicks_Master_Critic_Restaurant_Intelligence_Integration {
    
    /**
     * Check if Restaurant Intelligence plugin is active
     *
     * @return bool
     */
    public static function is_available() {
        // Check for plugin class or Foundation service
        if (class_exists('RestaurantIntelligencePlugin') || 
            (function_exists('zippicks') && zippicks()->has('restaurant_intelligence'))) {
            return true;
        }
        
        // Check if ZipBusiness API is configured
        $api_url = get_option('zippicks_zipbusiness_api_url');
        $api_key = get_option('zippicks_zipbusiness_api_key');
        
        return !empty($api_url) && !empty($api_key);
    }
    
    /**
     * Get restaurants for a specific city
     *
     * @param string $city City name
     * @param array $filters Optional filters
     * @return array
     */
    public static function get_city_restaurants($city, $filters = []) {
        // Try Foundation service first
        if (function_exists('zippicks') && zippicks()->has('restaurant_intelligence')) {
            $service = zippicks()->get('restaurant_intelligence');
            $result = $service->getRestaurantsByCity($city, $filters);
            if (!empty($result)) {
                return $result;
            }
        }
        
        // Try direct plugin access
        if (class_exists('RestaurantIntelligencePlugin')) {
            $plugin = RestaurantIntelligencePlugin::get_instance();
            if (method_exists($plugin, 'get_restaurants_by_city')) {
                $result = $plugin->get_restaurants_by_city($city, $filters);
                if (!empty($result)) {
                    return $result;
                }
            }
        }
        
        // Fallback to ZipBusiness API if available
        if (self::is_available()) {
            return self::call_zipbusiness_api($city, $filters);
        }
        
        // Log if nothing available
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Restaurant Intelligence] No data source available for city: ' . $city);
        }
        
        return [];
    }
    
    /**
     * Get restaurant by ZPID
     *
     * @param string $zpid Restaurant ZPID
     * @return array|null
     */
    public static function get_restaurant_by_zpid($zpid) {
        if (!self::is_available()) {
            return null;
        }
        
        // Try Foundation service first
        if (function_exists('zippicks') && zippicks()->has('restaurant_intelligence')) {
            $service = zippicks()->get('restaurant_intelligence');
            return $service->getRestaurantByZpid($zpid);
        }
        
        // Fallback to direct plugin access
        if (class_exists('RestaurantIntelligencePlugin')) {
            $plugin = RestaurantIntelligencePlugin::get_instance();
            if (method_exists($plugin, 'get_restaurant')) {
                return $plugin->get_restaurant($zpid);
            }
        }
        
        return null;
    }
    
    /**
     * Build restaurant context for Claude prompts
     *
     * @param string $city
     * @param string $category
     * @return string
     */
    public static function build_restaurant_context($city, $category = 'restaurant') {
        $restaurants = self::get_city_restaurants($city, [
            'category' => $category,
            'status' => 'active',
            'verified' => true
        ]);
        
        if (empty($restaurants)) {
            return "No verified restaurant data available for {$city}.";
        }
        
        $context = "Verified Restaurant Data for {$city}:\n";
        $context .= "Total Restaurants: " . count($restaurants) . "\n\n";
        
        // Group by cuisine type
        $by_cuisine = [];
        foreach ($restaurants as $restaurant) {
            $cuisine = $restaurant['cuisine'] ?? 'Other';
            if (!isset($by_cuisine[$cuisine])) {
                $by_cuisine[$cuisine] = [];
            }
            $by_cuisine[$cuisine][] = $restaurant;
        }
        
        $context .= "Cuisine Distribution:\n";
        foreach ($by_cuisine as $cuisine => $items) {
            $context .= "- {$cuisine}: " . count($items) . " restaurants\n";
        }
        $context .= "\n";
        
        // Include top restaurants by rating
        $sorted = $restaurants;
        usort($sorted, function($a, $b) {
            return ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0);
        });
        
        $context .= "Top Rated Restaurants:\n";
        $top_10 = array_slice($sorted, 0, 10);
        foreach ($top_10 as $restaurant) {
            $context .= sprintf(
                "- %s (%s) - Rating: %.1f, Price: %s, Address: %s\n",
                $restaurant['name'],
                $restaurant['cuisine'],
                $restaurant['rating'] ?? 0,
                $restaurant['price_range'] ?? '$$',
                $restaurant['address'] ?? 'N/A'
            );
        }
        
        return $context;
    }
    
    /**
     * Format restaurants for Claude prompt
     *
     * @param array $restaurants
     * @return string
     */
    public static function format_restaurants_for_prompt($restaurants) {
        if (empty($restaurants)) {
            return '[]';
        }
        
        $formatted = [];
        foreach ($restaurants as $restaurant) {
            $formatted[] = [
                'zpid' => $restaurant['zpid'] ?? '',
                'name' => $restaurant['name'] ?? '',
                'address' => $restaurant['address'] ?? '',
                'cuisine' => $restaurant['cuisine'] ?? '',
                'price_range' => $restaurant['price_range'] ?? '$$',
                'rating' => $restaurant['rating'] ?? 0,
                'review_count' => $restaurant['review_count'] ?? 0,
                'neighborhood' => $restaurant['neighborhood'] ?? '',
                'specialties' => $restaurant['specialties'] ?? [],
                'features' => $restaurant['features'] ?? []
            ];
        }
        
        return json_encode($formatted, JSON_PRETTY_PRINT);
    }
    
    /**
     * Get restaurants matching specific criteria
     *
     * @param string $city
     * @param string $list_category
     * @param array $additional_filters
     * @return array
     */
    public static function get_restaurants_for_category($city, $list_category, $additional_filters = []) {
        $base_filters = [
            'status' => 'active',
            'verified' => true
        ];
        
        // Add category-specific filters
        switch ($list_category) {
            case 'best_value':
                $base_filters['price_range'] = ['$', '$$'];
                $base_filters['min_rating'] = 3.5;
                break;
                
            case 'date_night':
                $base_filters['features'] = ['romantic', 'intimate', 'candlelit'];
                $base_filters['min_rating'] = 4.0;
                break;
                
            case 'business_lunch':
                $base_filters['features'] = ['quick_service', 'business_friendly', 'quiet'];
                $base_filters['lunch_hours'] = true;
                break;
                
            case 'family_friendly':
                $base_filters['features'] = ['kids_menu', 'high_chairs', 'family_friendly'];
                break;
                
            case 'food_truck':
                $base_filters['type'] = 'food_truck';
                break;
                
            case 'fine_dining':
                $base_filters['price_range'] = ['$$$', '$$$$'];
                $base_filters['min_rating'] = 4.5;
                $base_filters['features'] = ['fine_dining', 'tasting_menu', 'chef_driven'];
                break;
                
            case 'brunch':
                $base_filters['features'] = ['brunch', 'weekend_brunch'];
                $base_filters['brunch_hours'] = true;
                break;
        }
        
        // Merge with additional filters
        $filters = array_merge($base_filters, $additional_filters);
        
        return self::get_city_restaurants($city, $filters);
    }
    
    /**
     * Validate restaurant exists and is in the specified city
     *
     * @param string $zpid
     * @param string $city
     * @return bool
     */
    public static function validate_restaurant_location($zpid, $city) {
        $restaurant = self::get_restaurant_by_zpid($zpid);
        
        if (!$restaurant) {
            return false;
        }
        
        // Check if restaurant city matches
        $restaurant_city = $restaurant['city'] ?? '';
        return strcasecmp($restaurant_city, $city) === 0;
    }
    
    /**
     * Enrich restaurant data with additional details
     *
     * @param array $restaurant
     * @return array
     */
    public static function enrich_restaurant_data($restaurant) {
        if (!isset($restaurant['zpid'])) {
            return $restaurant;
        }
        
        // Get full details
        $full_details = self::get_restaurant_by_zpid($restaurant['zpid']);
        
        if ($full_details) {
            // Merge enriched data
            $restaurant = array_merge($restaurant, [
                'full_address' => $full_details['full_address'] ?? $restaurant['address'] ?? '',
                'phone' => $full_details['phone'] ?? '',
                'website' => $full_details['website'] ?? '',
                'hours' => $full_details['hours'] ?? [],
                'amenities' => $full_details['amenities'] ?? [],
                'payment_methods' => $full_details['payment_methods'] ?? [],
                'social_links' => $full_details['social_links'] ?? []
            ]);
        }
        
        return $restaurant;
    }
    
    /**
     * Get restaurants with caching
     *
     * @param string $city City name
     * @param int $ttl Cache time to live in seconds
     * @return array
     */
    public static function get_restaurants_with_cache($city, $ttl = 3600) {
        $cache_key = 'ri_restaurants_' . md5($city);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $restaurants = self::get_city_restaurants($city);
        if (!empty($restaurants)) {
            set_transient($cache_key, $restaurants, $ttl);
        }
        
        return $restaurants;
    }
    
    /**
     * Get category-filtered restaurants with caching
     *
     * @param string $city City name
     * @param string $category Restaurant category
     * @param int $ttl Cache time to live in seconds
     * @return array
     */
    public static function get_category_filtered_with_cache($city, $category, $ttl = 1800) {
        $cache_key = 'ri_cat_' . md5($city . '_' . $category);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $restaurants = self::get_restaurants_for_category($city, $category);
        if (!empty($restaurants)) {
            set_transient($cache_key, $restaurants, $ttl);
        }
        
        return $restaurants;
    }
    
    /**
     * Call ZipBusiness API to get restaurants
     *
     * @param string $city City name
     * @param array $filters Optional filters
     * @return array
     */
    private static function call_zipbusiness_api($city, $filters = []) {
        $api_url = get_option('zippicks_zipbusiness_api_url');
        $api_key = get_option('zippicks_zipbusiness_api_key');
        
        if (empty($api_url) || empty($api_key)) {
            return [];
        }
        
        // Build query parameters
        $params = [
            'city' => $city,
            'verified_only' => 'true',
            'page_size' => 100
        ];
        
        // Extract state if provided in city string (e.g., "Austin, TX")
        if (strpos($city, ',') !== false) {
            list($city_name, $state) = array_map('trim', explode(',', $city));
            $params['city'] = $city_name;
            if (strlen($state) === 2) {
                $params['state'] = strtoupper($state);
            }
        }
        
        $url = rtrim($api_url, '/') . '/api/v1/restaurants?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'X-API-Key' => $api_key,
                'Accept' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log('[Restaurant Intelligence] API Error: ' . $response->get_error_message());
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['success']) || !$data['success']) {
            error_log('[Restaurant Intelligence] API returned error: ' . json_encode($data));
            return [];
        }
        
        return isset($data['data']) ? $data['data'] : [];
    }
    
    /**
     * Get restaurant by ZPID from ZipBusiness API
     *
     * @param string $zpid
     * @return array|null
     */
    private static function get_restaurant_from_api($zpid) {
        $api_url = get_option('zippicks_zipbusiness_api_url');
        $api_key = get_option('zippicks_zipbusiness_api_key');
        
        if (empty($api_url) || empty($api_key)) {
            return null;
        }
        
        $url = rtrim($api_url, '/') . '/api/v1/restaurant/' . $zpid;
        
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'X-API-Key' => $api_key,
                'Accept' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log('[Restaurant Intelligence] API Error for ZPID ' . $zpid . ': ' . $response->get_error_message());
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['success']) || !$data['success']) {
            return null;
        }
        
        return isset($data['data']) ? $data['data'] : null;
    }
    
}