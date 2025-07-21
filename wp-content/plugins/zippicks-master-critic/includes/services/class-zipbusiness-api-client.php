<?php
/**
 * ZipBusiness API Client
 *
 * Handles all interactions with the ZipBusiness API for fetching
 * real restaurant data with enterprise-grade caching and error handling.
 *
 * @package ZipPicks_Master_Critic
 * @since 2.0.0
 */

class ZipPicks_Master_Critic_ZipBusiness_API_Client {
    
    const CACHE_GROUP = 'zipbusiness_api';
    const CITY_CACHE_TTL = 86400; // 24 hours
    const ENRICHED_CACHE_TTL = 604800; // 7 days
    const MAX_BATCH_SIZE = 10;
    const REQUEST_TIMEOUT = 30;
    
    /**
     * Logger instance
     *
     * @var object
     */
    private $logger;
    
    /**
     * API key
     *
     * @var string
     */
    private $api_key;
    
    /**
     * API base URL
     *
     * @var string
     */
    private $api_base_url;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
        $this->api_key = $this->get_api_key();
        $this->api_base_url = $this->get_api_base_url();
    }
    
    /**
     * Fetch all restaurants for a city
     *
     * @param string $city City name
     * @param string $state State code (2 letters)
     * @return array Restaurant data with zpids
     */
    public function get_city_restaurants($city, $state) {
        $cache_key = $this->get_cache_key('city', $city, $state);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if ($cached !== false) {
            if ($this->logger) {
                $this->logger->info('ZipBusiness API cache hit', [
                    'city' => $city,
                    'state' => $state,
                    'count' => count($cached)
                ]);
            }
            return $cached;
        }
        
        $endpoint = '/api/v1/restaurants';
        $params = [
            'city' => $city,
            'state' => $state,
            'verified_only' => 'true',
            'page_size' => 100  // API uses page_size, not limit
        ];
        
        try {
            $response = $this->make_request($endpoint, $params);
            
            // The API might return data in different formats
            if (is_array($response)) {
                if (isset($response['items'])) {
                    $restaurants = $response['items'];
                } elseif (isset($response['restaurants'])) {
                    $restaurants = $response['restaurants'];
                } elseif (isset($response['data'])) {
                    $restaurants = $response['data'];
                } else {
                    // Response might be the array directly
                    $restaurants = $response;
                }
            } else {
                throw new Exception('Invalid API response structure');
            }
            
            // Cache the full response
            wp_cache_set($cache_key, $restaurants, self::CACHE_GROUP, self::CITY_CACHE_TTL);
            
            // Also cache in database for persistence
            $this->cache_to_database($city, $state, $restaurants);
            
            if ($this->logger) {
                $this->logger->info('ZipBusiness API city fetch success', [
                    'city' => $city,
                    'state' => $state,
                    'count' => count($restaurants)
                ]);
            }
            
            return $restaurants;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('ZipBusiness API city fetch failed', [
                    'city' => $city,
                    'state' => $state,
                    'error' => $e->getMessage(),
                    'api_status' => 'The ZipBusiness API server appears to be experiencing issues'
                ]);
            }
            
            // Try database cache as fallback
            $cached_data = $this->get_from_database_cache($city, $state);
            
            // If no cached data and API verification is disabled, return empty array
            if (empty($cached_data) && !get_option('zippicks_enable_api_verification', false)) {
                if ($this->logger) {
                    $this->logger->info('ZipBusiness API verification disabled, returning empty array');
                }
                return [];
            }
            
            return $cached_data;
        }
    }
    
    /**
     * Enrich specific restaurants with additional data
     *
     * @param array $zpids Array of ZipBusiness IDs
     * @return array Enriched restaurant data keyed by zpid
     */
    public function enrich_restaurants($zpids) {
        if (empty($zpids)) {
            return [];
        }
        
        $enriched = [];
        $to_fetch = [];
        
        // Check cache for each zpid
        foreach ($zpids as $zpid) {
            $cache_key = $this->get_cache_key('enriched', $zpid);
            $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
            
            if ($cached !== false) {
                $enriched[$zpid] = $cached;
            } else {
                $to_fetch[] = $zpid;
            }
        }
        
        // Batch fetch missing enrichments
        if (!empty($to_fetch)) {
            $batches = array_chunk($to_fetch, self::MAX_BATCH_SIZE);
            
            foreach ($batches as $batch) {
                try {
                    $batch_data = $this->fetch_enrichment_batch($batch);
                    
                    foreach ($batch_data as $zpid => $data) {
                        $enriched[$zpid] = $data;
                        
                        // Cache individual enrichments
                        $cache_key = $this->get_cache_key('enriched', $zpid);
                        wp_cache_set($cache_key, $data, self::CACHE_GROUP, self::ENRICHED_CACHE_TTL);
                    }
                    
                } catch (Exception $e) {
                    if ($this->logger) {
                        $this->logger->error('ZipBusiness API enrichment batch failed', [
                            'batch' => $batch,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        return $enriched;
    }
    
    /**
     * Get single restaurant by ZPID
     *
     * @param string $zpid ZipBusiness ID
     * @return array|null Restaurant data or null if not found
     */
    public function get_restaurant_by_zpid($zpid) {
        $cache_key = $this->get_cache_key('restaurant', $zpid);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if ($cached !== false) {
            return $cached;
        }
        
        try {
            $endpoint = "/restaurants/{$zpid}";
            $response = $this->make_request($endpoint);
            
            if (!is_array($response) || !isset($response['restaurant'])) {
                return null;
            }
            
            $restaurant = $response['restaurant'];
            wp_cache_set($cache_key, $restaurant, self::CACHE_GROUP, self::CITY_CACHE_TTL);
            
            return $restaurant;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('ZipBusiness API single fetch failed', [
                    'zpid' => $zpid,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Search restaurants by query
     *
     * @param string $query Search query
     * @param array $filters Optional filters (cuisine, price, vibes)
     * @return array Search results
     */
    public function search_restaurants($query, $filters = []) {
        $params = array_merge([
            'q' => $query,
            'verified_only' => 'true'
        ], $filters);
        
        try {
            $endpoint = '/api/v1/restaurants';
            $response = $this->make_request($endpoint, $params);
            
            if (!is_array($response) || !isset($response['restaurants'])) {
                return [];
            }
            
            return $response['restaurants'];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('ZipBusiness API search failed', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }
    
    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param string $method HTTP method
     * @return array Decoded response
     * @throws Exception on error
     */
    private function make_request($endpoint, $params = [], $method = 'GET') {
        if (empty($this->api_key)) {
            throw new Exception('ZipBusiness API key not configured');
        }
        
        if (empty($this->api_base_url)) {
            throw new Exception('ZipBusiness API URL not configured');
        }
        
        $url = $this->api_base_url . $endpoint;
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $args = [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'X-API-Key' => $this->api_key,
                'Accept' => 'application/json',
                'X-Client' => 'ZipPicks-Master-Critic/2.0',
                'User-Agent' => 'ZipPicks-Master-Critic/2.0 (WordPress)'
            ],
            'sslverify' => true
        ];
        
        if ($method === 'POST' && !empty($params)) {
            $args['body'] = json_encode($params);
            $args['headers']['Content-Type'] = 'application/json';
        }
        
        // Log the request details for debugging
        if ($this->logger) {
            $this->logger->debug('ZipBusiness API request', [
                'url' => $url,
                'method' => $method,
                'headers' => $args['headers'],
                'params' => $params
            ]);
        }
        
        $response = $method === 'POST' ? 
            wp_remote_post($url, $args) : 
            wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = 'API request failed: ' . $response->get_error_message();
            if ($this->logger) {
                $this->logger->error('ZipBusiness API request error', [
                    'url' => $url,
                    'method' => $method,
                    'error' => $error_message,
                    'wp_error_code' => $response->get_error_code(),
                    'wp_error_data' => $response->get_error_data()
                ]);
            }
            throw new Exception($error_message . ' (URL: ' . $url . ')');
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        if ($status_code !== 200) {
            $error_message = "API returned status {$status_code}";
            
            // Try to parse error details from response body
            $error_details = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($error_details['detail'])) {
                $error_message .= ': ' . (is_string($error_details['detail']) ? $error_details['detail'] : json_encode($error_details['detail']));
            } else {
                $error_message .= ': ' . substr($body, 0, 200); // Limit body length in error message
            }
            
            if ($this->logger) {
                $this->logger->error('ZipBusiness API HTTP error', [
                    'url' => $url,
                    'method' => $method,
                    'status_code' => $status_code,
                    'response_body' => $body,
                    'response_headers' => $response_headers
                ]);
            }
            
            // Special handling for internal server errors
            if ($status_code === 500) {
                throw new Exception('ZipBusiness API server error. The restaurant data service is temporarily unavailable. (URL: ' . $url . ')');
            }
            
            throw new Exception($error_message . ' (URL: ' . $url . ')');
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Fetch enrichment data for a batch of ZPIDs
     *
     * @param array $zpids Array of ZPIDs
     * @return array Enriched data keyed by ZPID
     * @throws Exception on error
     */
    private function fetch_enrichment_batch($zpids) {
        $endpoint = '/restaurants/enrich';
        $params = [
            'zpids' => $zpids,
            'include' => ['vibes', 'hours', 'photos', 'amenities']
        ];
        
        $response = $this->make_request($endpoint, $params, 'POST');
        
        if (!is_array($response) || !isset($response['enrichments'])) {
            throw new Exception('Invalid enrichment response');
        }
        
        return $response['enrichments'];
    }
    
    /**
     * Get API key from settings
     *
     * @return string API key
     */
    private function get_api_key() {
        // Load security class for encrypted option retrieval
        $security_path = dirname(__FILE__) . '/../class-security.php';
        if (file_exists($security_path)) {
            require_once $security_path;
            
            // Get encrypted API key using same method as settings page
            $api_key = ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_zipbusiness_api_key', '');
            
            if (!empty($api_key)) {
                return $api_key;
            }
        }
        
        // Check environment variable as fallback
        if (defined('ZIPBUSINESS_API_KEY')) {
            return ZIPBUSINESS_API_KEY;
        }
        
        // Return empty string if no API key is configured
        return '';
    }
    
    /**
     * Get API base URL from settings
     *
     * @return string API base URL
     */
    private function get_api_base_url() {
        $url = get_option('zippicks_zipbusiness_api_url', 'https://zipbusiness-api.onrender.com');
        
        // Remove trailing slash to ensure clean URL construction
        $url = rtrim($url, '/');
        
        // Return base URL without /api/v1 - endpoints will include it
        return $url;
    }
    
    /**
     * Generate cache key
     *
     * @param string $type Cache type
     * @param mixed ...$params Additional parameters
     * @return string Cache key
     */
    private function get_cache_key($type, ...$params) {
        $parts = array_merge([$type], $params);
        return implode(':', array_map('sanitize_key', $parts));
    }
    
    /**
     * Cache restaurant data to database
     *
     * @param string $city City name
     * @param string $state State code
     * @param array $restaurants Restaurant data
     */
    private function cache_to_database($city, $state, $restaurants) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_api_restaurant_cache';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }
        
        foreach ($restaurants as $restaurant) {
            if (empty($restaurant['zpid'])) {
                continue;
            }
            
            $data = [
                'zpid' => $restaurant['zpid'],
                'city' => $city,
                'state' => $state,
                'restaurant_data' => json_encode($restaurant),
                'cache_time' => current_time('mysql')
            ];
            
            $wpdb->replace($table, $data);
        }
    }
    
    /**
     * Get restaurants from database cache
     *
     * @param string $city City name
     * @param string $state State code
     * @return array Restaurant data
     */
    private function get_from_database_cache($city, $state) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_api_restaurant_cache';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT restaurant_data FROM {$table} 
             WHERE city = %s AND state = %s 
             AND cache_time > DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY cache_time DESC",
            $city,
            $state
        ));
        
        $restaurants = [];
        foreach ($results as $row) {
            $data = json_decode($row->restaurant_data, true);
            if ($data) {
                $restaurants[] = $data;
            }
        }
        
        return $restaurants;
    }
    
    /**
     * Clear cache for a city
     *
     * @param string $city City name
     * @param string $state State code
     */
    public function clear_city_cache($city, $state) {
        $cache_key = $this->get_cache_key('city', $city, $state);
        wp_cache_delete($cache_key, self::CACHE_GROUP);
        
        // Also clear from database
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_api_restaurant_cache';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            $wpdb->delete($table, [
                'city' => $city,
                'state' => $state
            ]);
        }
    }
    
    /**
     * Get API status
     *
     * @return array Status information
     */
    public function get_api_status() {
        try {
            // Health endpoint is at root, not under /api/v1
            $base_url = get_option('zippicks_zipbusiness_api_url', 'https://zipbusiness-api.onrender.com');
            $base_url = rtrim($base_url, '/');
            
            // For health check, don't send API key if it's empty
            $headers = [
                'Accept' => 'application/json',
                'X-Client' => 'ZipPicks-Master-Critic/2.0'
            ];
            
            // Only add API key header if we have one
            if (!empty($this->api_key)) {
                $headers['X-API-Key'] = $this->api_key;
            }
            
            $args = [
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => $headers,
                'sslverify' => true  // Enable SSL verification
            ];
            
            // Try the API root endpoint which returns status info
            $response = wp_remote_get($base_url, $args);
            
            if (is_wp_error($response)) {
                throw new Exception('Health check failed: ' . $response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code !== 200) {
                throw new Exception("Health check returned status {$status_code}");
            }
            
            $data = json_decode($body, true);
            
            // Health check passes, but note that restaurant endpoints may have issues
            $api_verification_enabled = get_option('zippicks_enable_api_verification', false);
            
            return [
                'connected' => true,
                'status' => $data['status'] ?? 'unknown',
                'version' => $data['version'] ?? 'unknown',
                'timestamp' => current_time('mysql'),
                'api_verification' => $api_verification_enabled ? 'enabled' : 'disabled',
                'note' => 'Health check passed. Restaurant data endpoints may be experiencing issues.'
            ];
            
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'timestamp' => current_time('mysql'),
                'api_verification' => get_option('zippicks_enable_api_verification', false) ? 'enabled' : 'disabled',
                'note' => 'The API can work without ZipBusiness verification when disabled in settings.'
            ];
        }
    }
}