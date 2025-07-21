<?php
/**
 * Google Places API Service
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Services;

class GooglePlacesService {
    
    /**
     * API endpoint base URL
     */
    const API_BASE_URL = 'https://places.googleapis.com/v1/places';
    
    /**
     * API key
     *
     * @var string
     */
    private $api_key;
    
    /**
     * Cache service
     *
     * @var CacheService
     */
    private $cache;
    
    /**
     * Configuration service
     *
     * @var ConfigService
     */
    private $config;
    
    /**
     * Constructor
     *
     * @param CacheService $cache
     * @param ConfigService $config
     */
    public function __construct(CacheService $cache, ConfigService $config) {
        $this->cache = $cache;
        $this->config = $config;
        
        // Get API key from environment or WordPress options
        $this->api_key = $this->get_api_key();
    }
    
    /**
     * Search for places by text query
     *
     * @param string $query Search query
     * @param array $options Additional options
     * @return array
     * @throws \Exception
     */
    public function search_places(string $query, array $options = []): array {
        if (empty($this->api_key)) {
            throw new \Exception('Google Places API key not configured');
        }
        
        // Build cache key
        $cache_key = 'google_places_search_' . md5($query . serialize($options));
        
        // Check cache first
        $cached = $this->cache->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Build request
        $url = self::API_BASE_URL . ':searchText';
        
        $body = [
            'textQuery' => $query,
            'languageCode' => 'en',
            'includedType' => 'restaurant'
        ];
        
        // Add location bias if provided
        if (!empty($options['location_bias'])) {
            $body['locationBias'] = $options['location_bias'];
        }
        
        // Add fields to return
        $fields = [
            'places.id',
            'places.displayName',
            'places.formattedAddress',
            'places.location',
            'places.rating',
            'places.userRatingCount',
            'places.priceLevel',
            'places.types',
            'places.primaryType',
            'places.businessStatus',
            'places.phoneNumber',
            'places.websiteUri',
            'places.googleMapsUri',
            'places.photos'
        ];
        
        $headers = [
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $this->api_key,
            'X-Goog-FieldMask' => implode(',', $fields)
        ];
        
        // Make API request
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception('Google Places API request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            $error = json_decode($body, true);
            $error_message = $error['error']['message'] ?? 'Unknown error';
            throw new \Exception("Google Places API error ({$status_code}): {$error_message}");
        }
        
        $data = json_decode($body, true);
        $places = $data['places'] ?? [];
        
        // Cache results
        $cache_ttl = $this->config->get('google_places_cache_ttl', 3600); // 1 hour default
        $this->cache->set($cache_key, $places, $cache_ttl);
        
        return $places;
    }
    
    /**
     * Get place details by place ID
     *
     * @param string $place_id Google Place ID
     * @return array|null
     * @throws \Exception
     */
    public function get_place_details(string $place_id): ?array {
        if (empty($this->api_key)) {
            throw new \Exception('Google Places API key not configured');
        }
        
        // Build cache key
        $cache_key = 'google_place_details_' . $place_id;
        
        // Check cache first
        $cached = $this->cache->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Build request URL
        $url = self::API_BASE_URL . '/' . $place_id;
        
        // Define fields to retrieve
        $fields = [
            'id',
            'displayName',
            'formattedAddress',
            'location',
            'rating',
            'userRatingCount',
            'priceLevel',
            'types',
            'primaryType',
            'businessStatus',
            'phoneNumber',
            'websiteUri',
            'googleMapsUri',
            'regularOpeningHours',
            'currentOpeningHours',
            'photos',
            'reviews',
            'editorialSummary',
            'paymentOptions',
            'accessibilityOptions',
            'parkingOptions',
            'allowsDogs',
            'takeout',
            'delivery',
            'dineIn',
            'reservable',
            'servesBreakfast',
            'servesLunch',
            'servesDinner',
            'servesBeer',
            'servesWine',
            'servesVegetarianFood'
        ];
        
        $headers = [
            'X-Goog-Api-Key' => $this->api_key,
            'X-Goog-FieldMask' => implode(',', $fields)
        ];
        
        // Make API request
        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception('Google Places API request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 404) {
            return null;
        }
        
        if ($status_code !== 200) {
            $error = json_decode($body, true);
            $error_message = $error['error']['message'] ?? 'Unknown error';
            throw new \Exception("Google Places API error ({$status_code}): {$error_message}");
        }
        
        $place = json_decode($body, true);
        
        // Cache results
        $cache_ttl = $this->config->get('google_places_cache_ttl', 86400); // 24 hours for details
        $this->cache->set($cache_key, $place, $cache_ttl);
        
        return $place;
    }
    
    /**
     * Search businesses near a location
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param int $radius Radius in meters (max 50000)
     * @param array $options Additional options
     * @return array
     * @throws \Exception
     */
    public function search_nearby_businesses(float $lat, float $lng, int $radius = 5000, array $options = []): array {
        $location_bias = [
            'circle' => [
                'center' => [
                    'latitude' => $lat,
                    'longitude' => $lng
                ],
                'radius' => min($radius, 50000) // Max 50km
            ]
        ];
        
        $query = $options['query'] ?? 'businesses';
        
        return $this->search_places($query, [
            'location_bias' => $location_bias
        ]);
    }
    
    /**
     * Convert Google Places data to ZipPicks format
     *
     * @param array $place Google Places data
     * @return array ZipPicks formatted data
     */
    public function convert_to_zippicks_format(array $place): array {
        // Extract location coordinates
        $lat = $place['location']['latitude'] ?? null;
        $lng = $place['location']['longitude'] ?? null;
        
        // Convert price level to dollar signs
        $price_range = $this->convert_price_level($place['priceLevel'] ?? null);
        
        // Extract cuisine types from types array
        $cuisine_types = $this->extract_cuisine_types($place['types'] ?? []);
        
        return [
            'google_place_id' => $place['id'] ?? '',
            'name' => $place['displayName']['text'] ?? '',
            'address' => [
                'street' => $this->extract_street_address($place['formattedAddress'] ?? ''),
                'city' => $this->extract_city($place['formattedAddress'] ?? ''),
                'state' => $this->extract_state($place['formattedAddress'] ?? ''),
                'zip' => $this->extract_zip($place['formattedAddress'] ?? ''),
                'country' => 'US',
                'latitude' => $lat,
                'longitude' => $lng
            ],
            'contact' => [
                'phone' => $place['phoneNumber'] ?? '',
                'website' => $place['websiteUri'] ?? '',
                'google_maps_url' => $place['googleMapsUri'] ?? ''
            ],
            'rating' => $place['rating'] ?? null,
            'review_count' => $place['userRatingCount'] ?? 0,
            'price_range' => $price_range,
            'cuisine_types' => $cuisine_types,
            'primary_cuisine' => $place['primaryType'] ?? array_shift($cuisine_types),
            'business_status' => $place['businessStatus'] ?? 'OPERATIONAL',
            'hours' => $this->format_hours($place['regularOpeningHours'] ?? []),
            'features' => $this->extract_features($place),
            'photos' => $this->format_photos($place['photos'] ?? [])
        ];
    }
    
    /**
     * Get API key from environment or options
     *
     * @return string
     */
    public function get_api_key(): string {
        // Check environment variable first
        if (defined('GOOGLE_PLACES_API_KEY')) {
            return GOOGLE_PLACES_API_KEY;
        }
        
        // Check .env file
        $env_key = $_ENV['GOOGLE_PLACES_API_KEY'] ?? '';
        if (!empty($env_key)) {
            return $env_key;
        }
        
        // Check WordPress options - multiple possible locations
        $possible_options = [
            'master_critic_google_places_api_key',
            'zippicks_master_critic_google_places_api_key',
            'zippicks_google_places_api_key',
            'google_places_api_key'
        ];
        
        foreach ($possible_options as $option_name) {
            $key = get_option($option_name, '');
            if (!empty($key)) {
                return $key;
            }
        }
        
        return '';
    }
    
    /**
     * Convert Google price level to dollar signs
     *
     * @param string|null $price_level
     * @return array
     */
    private function convert_price_level(?string $price_level): array {
        $map = [
            'PRICE_LEVEL_FREE' => ['symbol' => '', 'min' => 0, 'max' => 0],
            'PRICE_LEVEL_INEXPENSIVE' => ['symbol' => '$', 'min' => 1, 'max' => 15],
            'PRICE_LEVEL_MODERATE' => ['symbol' => '$$', 'min' => 15, 'max' => 30],
            'PRICE_LEVEL_EXPENSIVE' => ['symbol' => '$$$', 'min' => 30, 'max' => 60],
            'PRICE_LEVEL_VERY_EXPENSIVE' => ['symbol' => '$$$$', 'min' => 60, 'max' => null]
        ];
        
        return $map[$price_level] ?? ['symbol' => '', 'min' => null, 'max' => null];
    }
    
    /**
     * Extract cuisine types from Google types
     *
     * @param array $types
     * @return array
     */
    private function extract_cuisine_types(array $types): array {
        // Map of Google types to cuisine names
        $cuisine_map = [
            'chinese_restaurant' => 'Chinese',
            'italian_restaurant' => 'Italian',
            'mexican_restaurant' => 'Mexican',
            'japanese_restaurant' => 'Japanese',
            'indian_restaurant' => 'Indian',
            'thai_restaurant' => 'Thai',
            'vietnamese_restaurant' => 'Vietnamese',
            'korean_restaurant' => 'Korean',
            'french_restaurant' => 'French',
            'spanish_restaurant' => 'Spanish',
            'greek_restaurant' => 'Greek',
            'american_restaurant' => 'American',
            'seafood_restaurant' => 'Seafood',
            'steak_house' => 'Steakhouse',
            'sushi_restaurant' => 'Sushi',
            'barbecue_restaurant' => 'BBQ',
            'pizza_restaurant' => 'Pizza',
            'hamburger_restaurant' => 'Burgers',
            'sandwich_shop' => 'Sandwiches',
            'coffee_shop' => 'Coffee',
            'bakery' => 'Bakery',
            'bar' => 'Bar',
            'pub' => 'Pub',
            'brewery' => 'Brewery',
            'wine_bar' => 'Wine Bar',
            'vegetarian_restaurant' => 'Vegetarian',
            'vegan_restaurant' => 'Vegan'
        ];
        
        $cuisines = [];
        foreach ($types as $type) {
            if (isset($cuisine_map[$type])) {
                $cuisines[] = $cuisine_map[$type];
            }
        }
        
        return array_unique($cuisines);
    }
    
    /**
     * Extract street address from formatted address
     *
     * @param string $formatted_address
     * @return string
     */
    private function extract_street_address(string $formatted_address): string {
        $parts = explode(',', $formatted_address);
        return trim($parts[0] ?? '');
    }
    
    /**
     * Extract city from formatted address
     *
     * @param string $formatted_address
     * @return string
     */
    private function extract_city(string $formatted_address): string {
        $parts = explode(',', $formatted_address);
        return trim($parts[1] ?? '');
    }
    
    /**
     * Extract state from formatted address
     *
     * @param string $formatted_address
     * @return string
     */
    private function extract_state(string $formatted_address): string {
        if (preg_match('/,\s*([A-Z]{2})\s+\d{5}/', $formatted_address, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    /**
     * Extract ZIP code from formatted address
     *
     * @param string $formatted_address
     * @return string
     */
    private function extract_zip(string $formatted_address): string {
        if (preg_match('/\b(\d{5})(?:-\d{4})?\b/', $formatted_address, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    /**
     * Format opening hours
     *
     * @param array $hours
     * @return array
     */
    private function format_hours(array $hours): array {
        if (empty($hours['periods'])) {
            return [];
        }
        
        $formatted = [];
        foreach ($hours['periods'] as $period) {
            $day = $period['open']['day'] ?? 0;
            $formatted[$day] = [
                'open' => $period['open']['time'] ?? '',
                'close' => $period['close']['time'] ?? ''
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Extract features from place data
     *
     * @param array $place
     * @return array
     */
    private function extract_features(array $place): array {
        $features = [];
        
        $feature_map = [
            'takeout' => 'Takeout',
            'delivery' => 'Delivery',
            'dineIn' => 'Dine In',
            'reservable' => 'Reservations',
            'servesBreakfast' => 'Breakfast',
            'servesLunch' => 'Lunch',
            'servesDinner' => 'Dinner',
            'servesBeer' => 'Beer',
            'servesWine' => 'Wine',
            'servesVegetarianFood' => 'Vegetarian Options',
            'allowsDogs' => 'Dog Friendly'
        ];
        
        foreach ($feature_map as $key => $label) {
            if (!empty($place[$key])) {
                $features[] = $label;
            }
        }
        
        // Add accessibility features
        if (!empty($place['accessibilityOptions'])) {
            if ($place['accessibilityOptions']['wheelchairAccessibleEntrance'] ?? false) {
                $features[] = 'Wheelchair Accessible';
            }
        }
        
        // Add parking options
        if (!empty($place['parkingOptions'])) {
            if ($place['parkingOptions']['freeParkingLot'] ?? false) {
                $features[] = 'Free Parking';
            }
            if ($place['parkingOptions']['paidParkingLot'] ?? false) {
                $features[] = 'Paid Parking';
            }
        }
        
        return $features;
    }
    
    /**
     * Format photos data
     *
     * @param array $photos
     * @return array
     */
    private function format_photos(array $photos): array {
        $formatted = [];
        
        foreach ($photos as $photo) {
            $formatted[] = [
                'name' => $photo['name'] ?? '',
                'width' => $photo['widthPx'] ?? 0,
                'height' => $photo['heightPx'] ?? 0,
                'attributions' => $photo['authorAttributions'] ?? []
            ];
        }
        
        return array_slice($formatted, 0, 10); // Limit to 10 photos
    }
    
    /**
     * Build photo URL
     *
     * @param string $photo_name Photo resource name
     * @param int $max_width Maximum width
     * @param int $max_height Maximum height
     * @return string
     */
    public function build_photo_url(string $photo_name, int $max_width = 400, int $max_height = 400): string {
        if (empty($this->api_key) || empty($photo_name)) {
            return '';
        }
        
        return sprintf(
            'https://places.googleapis.com/v1/%s/media?maxWidthPx=%d&maxHeightPx=%d&key=%s',
            $photo_name,
            $max_width,
            $max_height,
            $this->api_key
        );
    }
}