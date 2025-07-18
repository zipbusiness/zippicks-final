<?php
/**
 * Business Service - Main business logic
 * 
 * CRITICAL FIX APPLIED: 
 * - Fixed fatal "Cannot redeclare search_businesses()" error
 * - Added enterprise-level input validation and type safety
 * - Implemented proper error handling and graceful degradation
 * - Added data validation for all cache operations
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Services;

use ZipPicks\BusinessIntelligence\Clients\ZipBusinessAPIClient;
use ZipPicks\BusinessIntelligence\Models\Business;

class BusinessService {
    
    /**
     * API client
     *
     * @var ZipBusinessAPIClient
     */
    private $api_client;
    
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
     * @param ZipBusinessAPIClient $api_client
     * @param CacheService $cache
     * @param ConfigService $config
     */
    public function __construct(
        ZipBusinessAPIClient $api_client, 
        CacheService $cache, 
        ConfigService $config
    ) {
        $this->api_client = $api_client;
        $this->cache = $cache;
        $this->config = $config;
    }
    
    /**
     * Get businesses for a city
     *
     * @param string $city City name
     * @param string $state State code (defaults to 'CA')
     * @return array Array of BusinessProfile objects
     * @throws \Exception
     */
    public function get_city_businesses(string $city, string $state = 'CA'): array {
        // ENTERPRISE VALIDATION: Input sanitization
        if (empty(trim($city))) {
            throw new \InvalidArgumentException('City name cannot be empty');
        }
        
        if (strlen($state) !== 2) {
            throw new \InvalidArgumentException('State must be a 2-character code');
        }
        
        $city = $this->normalize_city_name($city);
        
        // Try cache first (memory/Redis)
        $cache_key = "city_businesses_{$city}_{$state}";
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            // ENTERPRISE SAFETY: Validate cached data is array
            $validated_cache = is_array($cached) ? $cached : [];
            if (!empty($validated_cache)) {
                return $this->hydrate_businesses($validated_cache);
            }
        }
        
        // Try database cache
        $db_cached = $this->cache->get_cached_businesses($city);
        if (!empty($db_cached) && is_array($db_cached)) {
            // Store in memory cache for faster access
            $this->cache->set($cache_key, $db_cached, 300); // 5 minutes
            return $this->hydrate_businesses($db_cached);
        }
        
        // Fetch from API
        try {
            $api_data = $this->api_client->get_city_businesses($city, $state);
            
            // ENTERPRISE SAFETY: Validate API response
            if (empty($api_data) || !is_array($api_data)) {
                return [];
            }
            
            // Store in both caches
            $this->cache->set($cache_key, $api_data);
            
            // Store individual businesses in database
            foreach ($api_data as $business_data) {
                if (isset($business_data['zpid'])) {
                    $this->cache->store_business(
                        $business_data['zpid'],
                        $city,
                        $business_data
                    );
                }
            }
            
            return $this->hydrate_businesses($api_data);
            
        } catch (\Exception $e) {
            // Log error
            $this->log_error('get_city_businesses', $e->getMessage(), ['city' => $city]);
            
            // Return cached data if available (even if expired)
            $expired_cache = $this->get_expired_cache($city);
            if (!empty($expired_cache)) {
                return $this->hydrate_businesses($expired_cache);
            }
            
            throw $e;
        }
    }
    
    /**
     * Get business by ZPID
     *
     * @param string $zpid Business ZPID
     * @return Business|null
     * @throws \Exception
     */
    public function get_business_by_zpid(string $zpid): ?Business {
        // ENTERPRISE VALIDATION: ZPID validation
        if (empty(trim($zpid))) {
            throw new \InvalidArgumentException('ZPID cannot be empty');
        }
        
        // Try cache first
        $cache_key = "business_{$zpid}";
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            // ENTERPRISE SAFETY: Validate cached data
            if (is_array($cached) && !empty($cached)) {
                return new Business($cached);
            }
        }
        
        // Try database cache
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_business_cache';
        
        $db_cached = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT data FROM {$table} WHERE zpid = %s AND expires_at > NOW()",
                $zpid
            )
        );
        
        if ($db_cached) {
            $data = json_decode($db_cached, true);
            if ($data) {
                $this->cache->set($cache_key, $data, 300); // 5 minutes
                return new Business($data);
            }
        }
        
        // Fetch from API
        try {
            $api_data = $this->api_client->get_business_by_zpid($zpid);
            
            if (!$api_data) {
                return null;
            }
            
            // Store in cache
            $this->cache->set($cache_key, $api_data);
            
            // Store in database cache
            if (isset($api_data['city'])) {
                $this->cache->store_business(
                    $zpid,
                    $api_data['city'],
                    $api_data
                );
            }
            
            return new Business($api_data);
            
        } catch (\Exception $e) {
            // Log error
            $this->log_error('get_business_by_zpid', $e->getMessage(), ['zpid' => $zpid]);
            
            // Check expired cache
            $expired = $this->get_expired_business($zpid);
            if ($expired) {
                return new Business($expired);
            }
            
            throw $e;
        }
    }
    
    /**
     * Trigger city collection
     *
     * @param string $city City name
     * @return bool Success status
     */
    public function trigger_city_collection(string $city): bool {
        $city = $this->normalize_city_name($city);
        
        try {
            $result = $this->api_client->trigger_city_collection($city);
            
            if ($result) {
                // Clear cache for this city to force refresh
                $this->cache->delete("city_businesses_{$city}");
                $this->cache->delete("business_count_{$city}");
                
                // Log successful trigger
                $this->log_info('City collection triggered', ['city' => $city]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->log_error('trigger_city_collection', $e->getMessage(), ['city' => $city]);
            return false;
        }
    }
    
    /**
     * Get business count for city
     *
     * @param string $city City name
     * @return int Business count
     */
    public function get_business_count(string $city): int {
        $city = $this->normalize_city_name($city);
        
        try {
            return $this->api_client->get_business_count($city);
        } catch (\Exception $e) {
            $this->log_error('get_business_count', $e->getMessage(), ['city' => $city]);
            return 0;
        }
    }
    
    /**
     * Get businesses by ZIP code for location-based discovery
     *
     * @param string $zip ZIP code
     * @param int $limit Maximum results
     * @return array Array of business data
     */
    public function get_businesses_by_zip(string $zip, int $limit = 50): array {
        try {
            $businesses = $this->api_client->get_businesses_by_location($zip, $limit);
            
            // Log successful fetch
            $this->log_info('Businesses fetched by ZIP', [
                'zip' => $zip,
                'count' => count($businesses)
            ]);
            
            return $businesses;
            
        } catch (\Exception $e) {
            $this->log_error('get_businesses_by_zip', $e->getMessage(), ['zip' => $zip]);
            return [];
        }
    }
    
    /**
     * Search businesses with query
     *
     * @param string $query Search query
     * @param array $filters Optional filters
     * @return array Search results
     */
    public function search_businesses(string $query, array $filters = []): array {
        try {
            $limit = $filters['limit'] ?? 50;
            unset($filters['limit']);
            
            $businesses = $this->api_client->search_businesses($query, $filters, $limit);
            
            // Log search
            $this->log_info('Business search performed', [
                'query' => $query,
                'filters' => $filters,
                'results' => count($businesses)
            ]);
            
            return $businesses;
            
        } catch (\Exception $e) {
            $this->log_error('search_businesses', $e->getMessage(), [
                'query' => $query,
                'filters' => $filters
            ]);
            return [];
        }
    }
    
    /**
     * Get businesses for Master Critic analysis
     *
     * @param string $city City name
     * @param array $filters Optional filters
     * @return array Array of formatted business data
     */
    public function get_businesses_for_analysis(string $city, array $filters = []): array {
        $state = $filters['state'] ?? 'CA';
        $businesses = $this->get_city_businesses($city, $state);
        
        // Apply filters
        if (!empty($filters['cuisine_types'])) {
            $businesses = array_filter($businesses, function($business) use ($filters) {
                $cuisines = array_map('strtolower', $business->cuisine_types);
                $filter_cuisines = array_map('strtolower', $filters['cuisine_types']);
                return !empty(array_intersect($cuisines, $filter_cuisines));
            });
        }
        
        if (isset($filters['price_range'])) {
            $max_price = (int) $filters['price_range'];
            $businesses = array_filter($businesses, function($business) use ($max_price) {
                return $business->price_range->is_within_budget($max_price);
            });
        }
        
        if (isset($filters['min_rating'])) {
            $min_rating = (float) $filters['min_rating'];
            $businesses = array_filter($businesses, function($business) use ($min_rating) {
                return $business->rating !== null && $business->rating >= $min_rating;
            });
        }
        
        // Format for Master Critic
        return array_map(function($business) {
            return $this->format_for_master_critic($business);
        }, array_values($businesses));
    }
    
    /**
     * Batch get businesses by ZPIDs
     *
     * @param array $zpids Array of ZPIDs
     * @return array Array of BusinessProfile objects
     */
    public function get_businesses_by_zpids(array $zpids): array {
        if (empty($zpids)) {
            return [];
        }
        
        $businesses = [];
        
        // Try to get from cache first
        $cache_keys = array_map(function($zpid) {
            return "business_{$zpid}";
        }, $zpids);
        
        $cached_data = $this->cache->get_multi($cache_keys);
        
        $missing_zpids = [];
        foreach ($zpids as $index => $zpid) {
            $cache_key = $cache_keys[$index];
            if (isset($cached_data[$cache_key])) {
                $businesses[$zpid] = new BusinessProfile($cached_data[$cache_key]);
            } else {
                $missing_zpids[] = $zpid;
            }
        }
        
        // Fetch missing from API
        foreach ($missing_zpids as $zpid) {
            try {
                $business = $this->get_business_by_zpid($zpid);
                if ($business) {
                    $businesses[$zpid] = $business;
                }
            } catch (\Exception $e) {
                // Continue with other businesses
                continue;
            }
        }
        
        return $businesses;
    }
    
    /**
     * Advanced business search with parameters
     *
     * @param array $params Search parameters
     * @return array Search results
     */
    public function search_businesses_advanced(array $params): array {
        $city = $params['city'] ?? '';
        if (empty($city)) {
            return [];
        }
        
        $state = $params['state'] ?? 'CA';
        $businesses = $this->get_city_businesses($city, $state);
        
        // Apply search filters
        if (!empty($params['query'])) {
            $query = strtolower($params['query']);
            $businesses = array_filter($businesses, function($business) use ($query) {
                return stripos($business->name, $query) !== false ||
                       $this->search_in_cuisines($business->cuisine_types, $query);
            });
        }
        
        // Apply other filters (same as get_businesses_for_analysis)
        $filters = array_intersect_key($params, array_flip(['cuisine_types', 'price_range', 'min_rating']));
        if (!empty($filters)) {
            $businesses = $this->get_businesses_for_analysis($city, $filters);
            $businesses = array_map(function($data) {
                return new Business($data);
            }, $businesses);
        }
        
        // Sort results
        $sort_by = $params['sort_by'] ?? 'rating';
        $businesses = $this->sort_businesses($businesses, $sort_by);
        
        // Pagination
        $page = max(1, (int) ($params['page'] ?? 1));
        $per_page = max(1, min(100, (int) ($params['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;
        
        $total = count($businesses);
        $businesses = array_slice($businesses, $offset, $per_page);
        
        return [
            'results' => array_values($businesses),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'pages' => ceil($total / $per_page)
        ];
    }
    
    /**
     * Get statistics
     *
     * @return array
     */
    public function get_statistics(): array {
        global $wpdb;
        
        $stats = [
            'total_businesses_cached' => 0,
            'total_cities' => 0,
            'api_requests_today' => 0,
            'api_errors_today' => 0,
            'cache_stats' => $this->cache->get_stats()
        ];
        
        // Get statistics from Business Intelligence cache table only
        $cache_table = $wpdb->prefix . 'zippicks_business_cache';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$cache_table}'");
        
        if ($table_exists) {
            $stats['total_businesses_cached'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT zpid) FROM {$cache_table}");
            $stats['total_cities'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT city) FROM {$cache_table}");
        }
        
        // Get API stats
        $log_table = $wpdb->prefix . 'zippicks_bi_api_log';
        $log_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$log_table}'");
        
        if ($log_table_exists) {
            $today = date('Y-m-d');
            
            $stats['api_requests_today'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$log_table} WHERE DATE(created_at) = %s AND status_code IS NOT NULL",
                    $today
                )
            );
            
            $stats['api_errors_today'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$log_table} WHERE DATE(created_at) = %s AND (status_code >= 400 OR error_message IS NOT NULL)",
                    $today
                )
            );
            
            // Average response time
            $avg_response_time = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT AVG(response_time) FROM {$log_table} WHERE DATE(created_at) = %s AND response_time IS NOT NULL",
                    $today
                )
            );
            
            $stats['avg_api_response_time'] = $avg_response_time ? round($avg_response_time, 3) : 0;
        }
        
        return $stats;
    }
    
    /**
     * Hydrate business data into models
     *
     * @param array $data_array Array of business data
     * @return array Array of Business objects
     */
    private function hydrate_businesses(array $data_array): array {
        // ENTERPRISE SAFETY: Validate each business data entry
        return array_map(function($data) {
            if (!is_array($data)) {
                // Log invalid data but don't crash
                $this->log_error('hydrate_businesses', 'Invalid business data type', ['data_type' => gettype($data)]);
                return new Business([]); // Return empty business object
            }
            return new Business($data);
        }, $data_array);
    }
    
    /**
     * Format business for Master Critic
     *
     * @param BusinessProfile $business
     * @return array
     */
    private function format_for_master_critic(BusinessProfile $business): array {
        return array_merge($business->to_array(), [
            'formatted_address' => $business->get_formatted_address(),
            'price_display' => $business->get_price_display(),
            'rating_display' => $business->get_rating_display(),
            'primary_cuisine' => $business->get_primary_cuisine(),
            'is_open_now' => $business->is_open_now(),
            'google_maps_url' => $business->address->get_google_maps_url()
        ]);
    }
    
    /**
     * Normalize city name
     *
     * @param string $city
     * @return string
     */
    private function normalize_city_name(string $city): string {
        return strtolower(str_replace([' ', '-'], '_', trim($city)));
    }
    
    /**
     * Search in cuisine types
     *
     * @param array $cuisines
     * @param string $query
     * @return bool
     */
    private function search_in_cuisines(array $cuisines, string $query): bool {
        foreach ($cuisines as $cuisine) {
            if (stripos($cuisine, $query) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Sort businesses
     *
     * @param array $businesses
     * @param string $sort_by
     * @return array
     */
    private function sort_businesses(array $businesses, string $sort_by): array {
        usort($businesses, function($a, $b) use ($sort_by) {
            switch ($sort_by) {
                case 'name':
                    return strcasecmp($a->name, $b->name);
                    
                case 'rating':
                    // Null ratings go last
                    if ($a->rating === null && $b->rating === null) return 0;
                    if ($a->rating === null) return 1;
                    if ($b->rating === null) return -1;
                    return $b->rating <=> $a->rating;
                    
                case 'review_count':
                    return $b->review_count <=> $a->review_count;
                    
                case 'price':
                    return $a->price_range->compare($b->price_range);
                    
                default:
                    return 0;
            }
        });
        
        return $businesses;
    }
    
    /**
     * Get expired cache data
     *
     * @param string $city
     * @return array
     */
    private function get_expired_cache(string $city): array {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_business_cache';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT data FROM {$table} WHERE city = %s ORDER BY updated_at DESC",
                $city
            ),
            ARRAY_A
        );
        
        if (!$results) {
            return [];
        }
        
        $businesses = [];
        foreach ($results as $row) {
            $data = json_decode($row['data'], true);
            if ($data) {
                $businesses[] = $data;
            }
        }
        
        return $businesses;
    }
    
    /**
     * Get expired business data
     *
     * @param string $zpid
     * @return array|null
     */
    private function get_expired_business(string $zpid): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'zippicks_business_cache';
        
        $data = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT data FROM {$table} WHERE zpid = %s ORDER BY updated_at DESC LIMIT 1",
                $zpid
            )
        );
        
        if (!$data) {
            return null;
        }
        
        return json_decode($data, true);
    }
    
    /**
     * Log error
     *
     * @param string $operation
     * @param string $message
     * @param array $context
     */
    private function log_error(string $operation, string $message, array $context = []) {
        if (!$this->config->get('enable_logging')) {
            return;
        }
        
        error_log(sprintf(
            '[ZipPicks BI] Error in %s: %s %s',
            $operation,
            $message,
            !empty($context) ? json_encode($context) : ''
        ));
        
        // Also log to Foundation logger if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->error("Business Intelligence: {$operation} failed", array_merge([
                'message' => $message
            ], $context));
        }
    }
    
    /**
     * Log info
     *
     * @param string $message
     * @param array $context
     */
    private function log_info(string $message, array $context = []) {
        if (!$this->config->get('enable_logging') || !$this->config->get('debug_mode')) {
            return;
        }
        
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info("Business Intelligence: {$message}", $context);
        }
    }
}