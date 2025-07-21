<?php
/**
 * ZipBusiness.ai API Client with enterprise-grade reliability
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Clients;

use ZipPicks\BusinessIntelligence\Services\ConfigService;
use ZipPicks\BusinessIntelligence\Services\CacheService;
use ZipPicks\BusinessIntelligence\Services\LoggerService;
use ZipPicks\BusinessIntelligence\Models\BusinessProfile;

class ZipBusinessAPIClient {
    
    /**
     * Configuration service
     *
     * @var ConfigService
     */
    private $config;
    
    /**
     * Cache service
     *
     * @var CacheService
     */
    private $cache;
    
    /**
     * Logger service
     *
     * @var LoggerService
     */
    private $logger;
    
    /**
     * API base URL
     *
     * @var string
     */
    private $base_url;
    
    /**
     * API key
     *
     * @var string
     */
    private $api_key;
    
    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private $timeout;
    
    /**
     * Number of retry attempts
     *
     * @var int
     */
    private $retry_attempts;
    
    /**
     * Rate limiter
     *
     * @var array
     */
    private $rate_limiter = [];
    
    /**
     * Constructor
     *
     * @param ConfigService $config
     * @param CacheService $cache
     * @param LoggerService $logger
     */
    public function __construct(ConfigService $config, CacheService $cache, LoggerService $logger) {
        $this->config = $config;
        $this->cache = $cache;
        $this->logger = $logger;
        
        $this->base_url = $config->get('api_url');
        $this->api_key = $config->get('api_key');
        $this->timeout = $config->get('timeout', 30);
        $this->retry_attempts = $config->get('retry_attempts', 3);
    }
    
    /**
     * Get a specific business by ZPID
     *
     * @param string $zpid Business ZPID
     * @return array|null Business data or null if not found
     * @throws \Exception
     */
    public function get_business_by_zpid(string $zpid): ?array {
        // Check rate limit
        $this->check_rate_limit();
        
        // Cache-first logic with proper key
        $cache_key = "bi_restaurant_{$zpid}";
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }
        
        try {
            // Make API request to new endpoint
            $endpoint = "/restaurants/by-zpid/{$zpid}";
            $response = $this->request('GET', $endpoint);
            
            // Extract only needed fields
            $business_data = $this->extract_business_fields($response);
            
            if (empty($business_data)) {
                return null;
            }
            
            // Cache with TTL from config
            $cache_ttl = $this->config->get('cache_ttl', 3600);
            $this->cache->set($cache_key, $business_data, $cache_ttl);
            
            return $business_data;
            
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                // Cache negative result for shorter time
                $this->cache->set($cache_key, null, 300);
                return null;
            }
            throw $e;
        }
    }
    
    /**
     * Get businesses by location (ZIP code)
     *
     * @param string $zip ZIP code
     * @param int $limit Maximum number of results
     * @return array Array of business data
     * @throws \Exception
     */
    public function get_businesses_by_location(string $zip, int $limit = 50): array {
        // Check rate limit
        $this->check_rate_limit();
        
        // Cache-first logic
        $cache_key = "bi_location_{$zip}_{$limit}";
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }
        
        try {
            // Make API request
            $endpoint = "/restaurants/by-location";
            $params = [
                'zip' => $zip,
                'limit' => $limit
            ];
            
            $response = $this->request('GET', $endpoint, $params);
            
            // Extract businesses with only needed fields
            $businesses = [];
            $raw_businesses = $this->extract_response_array($response);
            
            foreach ($raw_businesses as $business) {
                $extracted = $this->extract_business_fields($business);
                if (!empty($extracted)) {
                    $businesses[] = $extracted;
                }
            }
            
            // Cache results
            $cache_ttl = $this->config->get('cache_ttl', 3600);
            $this->cache->set($cache_key, $businesses, $cache_ttl);
            
            return $businesses;
            
        } catch (\Exception $e) {
            $this->logger->log_api_error('/restaurants/by-location', 'GET', $e->getMessage(), ['zip' => $zip]);
            throw $e;
        }
    }
    
    /**
     * Search businesses
     *
     * @param string $query Search query
     * @param array $filters Optional filters (zip, city, state)
     * @param int $limit Maximum number of results
     * @return array Array of business data
     * @throws \Exception
     */
    public function search_businesses(string $query, array $filters = [], int $limit = 50): array {
        // Check rate limit
        $this->check_rate_limit();
        
        // Build cache key from parameters
        $cache_key = "bi_search_" . md5($query . serialize($filters) . $limit);
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }
        
        try {
            // Build request parameters
            $params = array_merge([
                'q' => $query,
                'limit' => $limit
            ], $filters);
            
            // Make API request
            $endpoint = "/restaurants/search";
            $response = $this->request('GET', $endpoint, $params);
            
            // Extract businesses
            $businesses = [];
            $raw_businesses = $this->extract_response_array($response);
            
            foreach ($raw_businesses as $business) {
                $extracted = $this->extract_business_fields($business);
                if (!empty($extracted)) {
                    $businesses[] = $extracted;
                }
            }
            
            // Cache results
            $cache_ttl = $this->config->get('cache_ttl', 3600);
            $this->cache->set($cache_key, $businesses, $cache_ttl);
            
            return $businesses;
            
        } catch (\Exception $e) {
            $this->logger->log_api_error('/restaurants/search', 'GET', $e->getMessage(), ['query' => $query, 'filters' => $filters]);
            throw $e;
        }
    }
    
    /**
     * Extract only needed fields from business data
     *
     * @param array $data Raw business data
     * @return array Filtered business data
     */
    private function extract_business_fields($data): array {
        if (!is_array($data) || empty($data['zpid'])) {
            return [];
        }
        
        // Map fields from zipbusiness database
        $fields = [
            'zpid' => $data['zpid'] ?? '',
            'place_id' => $data['google_place_id'] ?? $data['place_id'] ?? '',
            'source' => $data['source'] ?? 'zipbusiness',
            'name' => $data['name'] ?? '',
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? 'CA',
            'zip_code' => $data['zip_code'] ?? '',
            'latitude' => $this->parse_coordinate($data['latitude'] ?? null),
            'longitude' => $this->parse_coordinate($data['longitude'] ?? null),
            'price_level' => $data['price_range'] ?? $data['price_level'] ?? '',
            'cuisine_type' => $this->parse_cuisine_type($data),
            'vibe_attributes' => $this->parse_vibe_attributes($data),
            'elite_category' => $data['elite_category'] ?? '',
            'hours' => $data['hours'] ?? '',
            'is_closed' => $this->parse_boolean($data['is_closed'] ?? false),
            'rating_average' => $this->parse_rating($data),
            'rating_count' => $this->parse_rating_count($data),
            'review_summary_text' => $data['review_excerpts'] ?? $data['review_summary_text'] ?? '',
            'categories' => $this->parse_categories($data),
            'website' => $data['website'] ?? '',
            'phone_number' => $data['phone'] ?? $data['phone_number'] ?? '',
            'last_updated' => $data['last_updated'] ?? $data['updated_at'] ?? date('Y-m-d H:i:s')
        ];
        
        // Remove null/empty values
        return array_filter($fields, function($value) {
            return $value !== '' && $value !== null;
        });
    }
    
    /**
     * Parse coordinate value
     *
     * @param mixed $value
     * @return float|null
     */
    private function parse_coordinate($value): ?float {
        if (is_numeric($value)) {
            return (float) $value;
        }
        return null;
    }
    
    /**
     * Parse boolean value
     *
     * @param mixed $value
     * @return bool
     */
    private function parse_boolean($value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Parse rating average from multiple possible sources
     *
     * @param array $data
     * @return float|null
     */
    private function parse_rating($data): ?float {
        // Try different rating fields
        $rating = $data['rating_average'] ?? 
                  $data['google_rating'] ?? 
                  $data['yelp_rating'] ?? 
                  $data['rating'] ?? 
                  null;
        
        return is_numeric($rating) ? (float) $rating : null;
    }
    
    /**
     * Parse rating count from multiple possible sources
     *
     * @param array $data
     * @return int
     */
    private function parse_rating_count($data): int {
        $count = $data['rating_count'] ?? 
                 $data['google_review_count'] ?? 
                 $data['yelp_review_count'] ?? 
                 $data['review_count'] ?? 
                 0;
        
        return (int) $count;
    }
    
    /**
     * Parse categories from various formats
     *
     * @param array $data
     * @return array
     */
    private function parse_categories($data): array {
        // Handle different category formats
        if (isset($data['categories']) && is_array($data['categories'])) {
            return $data['categories'];
        }
        
        if (isset($data['categories']) && is_string($data['categories'])) {
            // Try to decode JSON or split string
            $decoded = json_decode($data['categories'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
            return array_map('trim', explode(',', $data['categories']));
        }
        
        return [];
    }
    
    /**
     * Parse cuisine type from data
     *
     * @param array $data
     * @return string
     */
    private function parse_cuisine_type($data): string {
        // Direct cuisine_type field
        if (isset($data['cuisine_type']) && is_string($data['cuisine_type'])) {
            return $data['cuisine_type'];
        }
        
        // Try to extract from categories
        if (isset($data['categories']) && is_array($data['categories']) && !empty($data['categories'])) {
            return $data['categories'][0]; // Use first category as cuisine type
        }
        
        // Try other cuisine-related fields
        return $data['cuisine'] ?? $data['food_type'] ?? '';
    }
    
    /**
     * Parse vibe attributes from data
     *
     * @param array $data
     * @return array
     */
    private function parse_vibe_attributes($data): array {
        // Direct vibe_attributes field
        if (isset($data['vibe_attributes']) && is_array($data['vibe_attributes'])) {
            return $data['vibe_attributes'];
        }
        
        // Try to decode JSON string
        if (isset($data['vibe_attributes']) && is_string($data['vibe_attributes'])) {
            $decoded = json_decode($data['vibe_attributes'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        
        // Extract vibe-related attributes from other fields
        $vibes = [];
        
        // Map common attributes to vibes
        if (isset($data['atmosphere'])) {
            $vibes['atmosphere'] = $data['atmosphere'];
        }
        if (isset($data['ambiance'])) {
            $vibes['ambiance'] = $data['ambiance'];
        }
        if (isset($data['mood'])) {
            $vibes['mood'] = $data['mood'];
        }
        if (isset($data['style'])) {
            $vibes['style'] = $data['style'];
        }
        
        return $vibes;
    }
    
    /**
     * Extract array of businesses from various API response formats
     *
     * @param mixed $response API response
     * @return array Array of businesses
     */
    private function extract_response_array($response): array {
        if (!is_array($response)) {
            return [];
        }
        
        // Handle different response formats
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        if (isset($response['restaurants']) && is_array($response['restaurants'])) {
            return $response['restaurants'];
        }
        if (isset($response['items']) && is_array($response['items'])) {
            return $response['items'];
        }
        if (isset($response['results']) && is_array($response['results'])) {
            return $response['results'];
        }
        
        // Response might be the array directly
        if (isset($response[0])) {
            return $response;
        }
        
        return [];
    }
    
    /**
     * Trigger data collection for a city
     *
     * @param string $city City name
     * @return bool Success status
     * @throws \Exception
     */
    public function trigger_city_collection(string $city): bool {
        $endpoint = "/businesses/collect";
        
        // Check rate limit
        $this->check_rate_limit();
        
        try {
            $response = $this->request('POST', $endpoint, [
                'city' => $city
            ]);
            
            return isset($response['success']) && $response['success'] === true;
            
        } catch (\Exception $e) {
            // Log the error
            $this->logger->log_api_error('/businesses/collect', 'POST', $e->getMessage(), ['city' => $city]);
            return false;
        }
    }
    
    /**
     * Get business count for a city
     *
     * @param string $city City name
     * @return int Business count
     * @throws \Exception
     */
    public function get_business_count(string $city): int {
        $endpoint = "/businesses/city/{$city}/count";
        
        // Check rate limit
        $this->check_rate_limit();
        
        // Try to get from cache first
        $cache_key = "business_count_{$city}";
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            return (int) $cached;
        }
        
        // Make API request
        $response = $this->request('GET', $endpoint);
        
        $count = isset($response['count']) ? (int) $response['count'] : 0;
        
        // Cache for shorter duration
        $this->cache->set($cache_key, $count, 300); // 5 minutes
        
        return $count;
    }
    
    /**
     * Health check
     *
     * @return array Health status
     */
    public function health_check(): array {
        try {
            $start = microtime(true);
            $response = $this->request('GET', '/health', [], false);
            $duration = microtime(true) - $start;
            
            return [
                'status' => 'healthy',
                'response_time' => round($duration * 1000, 2), // ms
                'api_version' => $response['version'] ?? 'unknown'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Make HTTP request with retry logic
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param bool $use_cache Whether to use cache
     * @return array Response data
     * @throws \Exception
     */
    private function request(string $method, string $endpoint, array $data = [], bool $use_cache = true): array {
        // Ensure base URL has no trailing slash
        $base = rtrim($this->base_url, '/');
        
        // Build the full URL
        $url = $base . '/' . ltrim($endpoint, '/');
        
        // Add query parameters for GET requests
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        $attempt = 0;
        $last_error = null;
        
        // Request ID for correlation
        $request_id = wp_generate_uuid4();
        
        while ($attempt < $this->retry_attempts) {
            $attempt++;
            
            try {
                $args = [
                    'method' => $method,
                    'timeout' => $this->timeout,
                    'headers' => [
                        'X-API-Key' => $this->api_key,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Request-ID' => $request_id,
                        'X-Client-Version' => ZIPPICKS_BI_VERSION
                    ]
                ];
                
                if ($method === 'POST' && !empty($data)) {
                    $args['body'] = json_encode($data);
                }
                
                $start_time = microtime(true);
                $response = wp_remote_request($url, $args);
                $duration = microtime(true) - $start_time;
                
                if (is_wp_error($response)) {
                    throw new \Exception($response->get_error_message());
                }
                
                $status_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                
                // Log successful response
                $this->logger->log_api_request(
                    $endpoint,
                    $method,
                    $data,
                    $status_code,
                    $duration,
                    ['request_id' => $request_id, 'attempt' => $attempt]
                );
                
                // Handle rate limiting
                if ($status_code === 429) {
                    $retry_after = wp_remote_retrieve_header($response, 'retry-after');
                    $wait_time = $retry_after ? (int) $retry_after : min(pow(2, $attempt), 60);
                    
                    if ($attempt < $this->retry_attempts) {
                        sleep($wait_time);
                        continue;
                    }
                }
                
                // Handle errors
                if ($status_code >= 400) {
                    $error_message = "API request failed with status {$status_code}";
                    if ($body) {
                        $decoded = json_decode($body, true);
                        if (isset($decoded['message'])) {
                            $error_message = $decoded['message'];
                        }
                    }
                    throw new \Exception($error_message);
                }
                
                // Parse response
                $decoded = json_decode($body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON response from API');
                }
                
                return $decoded;
                
            } catch (\Exception $e) {
                $last_error = $e;
                
                // Log error with context
                $this->logger->log_api_error(
                    $endpoint,
                    $method,
                    $e->getMessage(),
                    ['request_id' => $request_id, 'attempt' => $attempt, 'url' => $url]
                );
                
                // Exponential backoff for retries
                if ($attempt < $this->retry_attempts) {
                    $wait_time = min(pow(2, $attempt - 1), 30);
                    sleep($wait_time);
                }
            }
        }
        
        // All attempts failed
        throw $last_error ?: new \Exception('API request failed after all retry attempts');
    }
    
    /**
     * Check rate limit
     *
     * @throws \Exception
     */
    private function check_rate_limit() {
        $rate_limit = $this->config->get('rate_limit', 60);
        $current_minute = floor(time() / 60);
        
        if (!isset($this->rate_limiter[$current_minute])) {
            $this->rate_limiter = [$current_minute => 0];
        }
        
        $this->rate_limiter[$current_minute]++;
        
        if ($this->rate_limiter[$current_minute] > $rate_limit) {
            throw new \Exception('Rate limit exceeded. Please try again later.');
        }
    }
    
    /**
     * Log API request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param string $request_id Request ID
     */
    private function log_request(string $method, string $endpoint, string $request_id) {
        if ($this->config->get('debug_mode')) {
            error_log(sprintf(
                '[ZipPicks BI] API Request: %s %s (ID: %s)',
                $method,
                $endpoint,
                $request_id
            ));
        }
    }
    
    /**
     * Log API response
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param int $status_code HTTP status code
     * @param float $duration Request duration
     * @param string $request_id Request ID
     */
    private function log_response(string $method, string $endpoint, int $status_code, float $duration, string $request_id) {
        global $wpdb;
        
        // Log to database
        $wpdb->insert(
            $wpdb->prefix . 'zippicks_bi_api_log',
            [
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $status_code,
                'response_time' => $duration,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%d', '%f', '%s']
        );
        
        if ($this->config->get('debug_mode')) {
            error_log(sprintf(
                '[ZipPicks BI] API Response: %s %s - Status: %d, Time: %.2fs (ID: %s)',
                $method,
                $endpoint,
                $status_code,
                $duration,
                $request_id
            ));
        }
    }
    
}